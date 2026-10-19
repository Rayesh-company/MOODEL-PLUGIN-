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
use core_external\external_value;
use context_course;
use local_aiquizgen\quiz_builder;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API to publish teacher-approved questions and create the Moodle quiz.
 */
class publish_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'quizname' => new external_value(PARAM_TEXT, 'Title of the quiz', VALUE_REQUIRED),
            'categoryname' => new external_value(PARAM_TEXT, 'Question bank category name', VALUE_DEFAULT, ''),
            'questionsjson' => new external_value(PARAM_RAW, 'JSON array of approved questions', VALUE_REQUIRED),
            'timelimit' => new external_value(PARAM_INT, 'Time limit in minutes', VALUE_DEFAULT, 30),
            'attempts' => new external_value(PARAM_INT, 'Allowed attempts', VALUE_DEFAULT, 1),
            'preferredbehaviour' => new external_value(PARAM_ALPHAEXT, 'Feedback behaviour', VALUE_DEFAULT, 'deferredfeedback'),
        ]);
    }

    public static function execute(int $courseid, string $quizname, string $categoryname, string $questionsjson, int $timelimit, int $attempts, string $preferredbehaviour): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'quizname' => $quizname,
            'categoryname' => $categoryname,
            'questionsjson' => $questionsjson,
            'timelimit' => $timelimit,
            'attempts' => $attempts,
            'preferredbehaviour' => $preferredbehaviour,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/aiquizgen:generate', $context);

        $questions = json_decode($params['questionsjson'], true);
        if (empty($questions) || !is_array($questions)) {
            throw new \moodle_exception('invalid_questions_payload', 'local_aiquizgen');
        }

        // 1. Get or create question category.
        $category = quiz_builder::get_or_create_category($params['courseid'], $params['categoryname']);

        // 2. Save each question to Question Bank.
        $createdqids = [];
        foreach ($questions as $q) {
            $qid = quiz_builder::create_question($params['courseid'], $category, $q);
            $createdqids[] = $qid;
        }

        // 3. Assemble the Moodle Quiz.
        $quizinfo = quiz_builder::create_quiz_with_questions($params['courseid'], $params['quizname'], $createdqids, [
            'timelimit' => $params['timelimit'],
            'attempts' => $params['attempts'],
            'preferredbehaviour' => $params['preferredbehaviour'],
        ]);
        $quizid = $quizinfo['quizid'];
        $cmid = $quizinfo['cmid'];

        $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $cmid]);

        // 4. Record session and questions for tracking, monitoring, and quality feedback.
        global $USER;
        $qtypes = array_unique(array_map(function($q) {
            return $q['qtype'] ?? 'multichoice';
        }, $questions));

        $sessionid = $DB->insert_record('local_aiquizgen_sessions', [
            'courseid' => $params['courseid'],
            'userid' => $USER->id,
            'prompt' => '',
            'difficulty' => 'standard',
            'qtypes' => implode(',', $qtypes),
            'targetqcount' => count($createdqids),
            'status' => 'published',
            'quizid' => $quizid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        foreach ($questions as $idx => $q) {
            $qid = $createdqids[$idx] ?? null;
            $DB->insert_record('local_aiquizgen_questions', [
                'sessionid' => $sessionid,
                'qtype' => $q['qtype'] ?? 'multichoice',
                'questiontext' => $q['questiontext'] ?? '',
                'questiondata' => json_encode($q, JSON_UNESCAPED_UNICODE),
                'editedbyuser' => !empty($q['is_edited']) ? 1 : 0,
                'repromptcount' => $q['reprompt_count'] ?? 0,
                'moodlequestionid' => $qid,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        return [
            'status' => true,
            'quizid' => $quizid,
            'cmid' => $cmid,
            'quizurl' => $quizurl->out(false),
            'questionscount' => count($createdqids),
            'message' => get_string('publish_success_message', 'local_aiquizgen'),
        ];
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'quizid' => new external_value(PARAM_INT, 'ID of created quiz'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID of created quiz', VALUE_OPTIONAL),
            'quizurl' => new external_value(PARAM_URL, 'URL of created quiz'),
            'questionscount' => new external_value(PARAM_INT, 'Number of questions created'),
            'message' => new external_value(PARAM_TEXT, 'Success message'),
        ]);
    }
}
