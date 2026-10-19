<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_aiquizgen\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_course;
use local_aiquizgen\ai_service;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API to generate questions via Moodle AI manager.
 */
class generate_questions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'prompt' => new external_value(PARAM_RAW, 'Teacher custom prompt or instructions', VALUE_DEFAULT, ''),
            'fileids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Stored file ID'),
                'List of file IDs to use as context',
                VALUE_DEFAULT,
                []
            ),
            'qcount' => new external_value(PARAM_INT, 'Number of questions', VALUE_DEFAULT, 10),
            'difficulty' => new external_value(PARAM_ALPHAEXT, 'Difficulty level', VALUE_DEFAULT, 'analytical'),
            'qtypes' => new external_multiple_structure(
                new external_value(PARAM_ALPHAEXT, 'Question type'),
                'Allowed question types',
                VALUE_DEFAULT,
                ['multichoice', 'truefalse', 'shortanswer', 'essay', 'match']
            ),
            'lang' => new external_value(PARAM_ALPHA, 'Language', VALUE_DEFAULT, 'fa'),
        ]);
    }

    public static function execute(int $courseid, string $prompt, array $fileids, int $qcount, string $difficulty, array $qtypes, string $lang): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'prompt' => $prompt,
            'fileids' => $fileids,
            'qcount' => $qcount,
            'difficulty' => $difficulty,
            'qtypes' => $qtypes,
            'lang' => $lang,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/aiquizgen:generate', $context);

        // Gather text excerpts from selected files using robust text extractor.
        $fs = get_file_storage();
        $excerpts = [];
        foreach ($params['fileids'] as $fid) {
            $file = $fs->get_file_by_id($fid);
            if ($file && !$file->is_directory()) {
                $extracted = \local_aiquizgen\text_extractor::extract_text($file, 4000);
                if (!empty($extracted)) {
                    $excerpts[] = "--- File: " . $file->get_filename() . " ---\n" . $extracted;
                }
            }
        }
        $resourcetext = implode("\n\n", $excerpts);

        // Build prompt and dispatch to Moodle core_ai.
        $systemprompt = ai_service::build_system_prompt(
            $resourcetext,
            $params['prompt'],
            $params['qcount'],
            $params['difficulty'],
            $params['qtypes'],
            $params['lang']
        );

        // Track generation session in database.
        $session = new stdClass();
        $session->courseid = $params['courseid'];
        $session->userid = $USER->id;
        $session->prompt = $params['prompt'];
        $session->difficulty = $params['difficulty'];
        $session->qtypes = implode(',', $params['qtypes']);
        $session->targetqcount = $params['qcount'];
        $session->status = 'generating';
        $session->timecreated = time();
        $session->timemodified = time();
        $sessionid = $DB->insert_record('local_aiquizgen_sessions', $session);

        $questions = ai_service::call_moodle_ai($context->id, $USER->id, $systemprompt);

        // Save generated questions to session.
        foreach ($questions as $q) {
            $record = new stdClass();
            $record->sessionid = $sessionid;
            $record->qtype = $q['qtype'] ?? 'multichoice';
            $record->questiontext = $q['questiontext'] ?? '';
            $record->questiondata = json_encode($q);
            $record->editedbyuser = 0;
            $record->repromptcount = 0;
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('local_aiquizgen_questions', $record);
        }

        $DB->set_field('local_aiquizgen_sessions', 'status', 'ready', ['id' => $sessionid]);

        return [
            'status' => true,
            'sessionid' => $sessionid,
            'questionsjson' => json_encode($questions),
            'count' => count($questions),
        ];
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'sessionid' => new external_value(PARAM_INT, 'Session ID'),
            'questionsjson' => new external_value(PARAM_RAW, 'JSON encoded list of questions'),
            'count' => new external_value(PARAM_INT, 'Number of questions generated'),
        ]);
    }
}
