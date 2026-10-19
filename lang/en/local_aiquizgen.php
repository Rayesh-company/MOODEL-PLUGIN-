<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Quiz Generator';
$string['aiquizgen:view'] = 'View AI Quiz Generator';
$string['aiquizgen:generate'] = 'Generate quizzes using AI';
$string['aiquizgen:manage'] = 'Manage AI Quiz Generator settings';
$string['settings_general'] = 'General Configuration';
$string['settings_general_desc'] = 'Configure global settings for AI-powered quiz generation.';
$string['enablecoursetab'] = 'Enable Course Navigation Tab';
$string['enablecoursetab_desc'] = 'When enabled, adds an "AI Quiz Generator" tab to the secondary navigation of courses.';
$string['maxquestions'] = 'Maximum Questions per Request';
$string['maxquestions_desc'] = 'The maximum number of questions a teacher can generate in a single session.';
$string['default_category_name'] = 'AI Generated Questions';
$string['default_quiz_intro'] = 'This quiz was generated with AI assistance and approved by the course instructor.';
$string['publish_success_message'] = 'The questions were successfully added to the Question Bank and the Quiz activity is live in your course.';
$string['ai_generation_failed'] = 'AI generation request failed: {$a}';
$string['json_parse_error'] = 'Could not parse the AI response as structured JSON. Please retry.';
$string['invalid_questions_payload'] = 'Invalid questions payload provided.';
