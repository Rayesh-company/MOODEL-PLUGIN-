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
use local_aiquizgen\ai_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API to regenerate a single question based on teacher re-prompt.
 */
class reprompt_question extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
            'currentquestionjson' => new external_value(PARAM_RAW, 'Current JSON of the question', VALUE_REQUIRED),
            'instruction' => new external_value(PARAM_TEXT, 'Teacher modification instruction', VALUE_REQUIRED),
        ]);
    }

    public static function execute(int $courseid, string $currentquestionjson, string $instruction): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'currentquestionjson' => $currentquestionjson,
            'instruction' => $instruction,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/aiquizgen:generate', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $coursename = format_string($course->fullname, true, ['context' => $context]);

        $prompt = <<<PROMPT
You are an expert university professor and exam editor.
ACADEMIC COURSE TITLE: {$coursename}

CRITICAL GUARDRAIL:
The updated question MUST strictly assess knowledge of "{$coursename}". Pedagogical instructions like "مخصوص پیش‌خوانی" (pre-reading), "ساده‌تر" (simpler), or "کوییز" (quiz) define the question's difficulty and depth, NEVER the subject itself. Do NOT generate questions about study skills or reading techniques.

The teacher wants you to modify/regenerate this single exam question based on their feedback:

CURRENT QUESTION (JSON):
{$params['currentquestionjson']}

TEACHER MODIFICATION INSTRUCTION:
{$params['instruction']}

OUTPUT INSTRUCTION:
Output ONLY a single valid JSON object containing the updated question in the exact same schema. Wrap in {"questions": [ ...updated question... ]}. No markdown code fences and no surrounding prose.
PROMPT;

        $questions = ai_service::execute_generation($context->id, $USER->id, [], $prompt);

        return [
            'status' => true,
            'questionjson' => json_encode($questions[0] ?? []),
        ];
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_BOOL, 'Status'),
            'questionjson' => new external_value(PARAM_RAW, 'Updated question JSON'),
        ]);
    }
}
