<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aiquizgen_scan_resources' => [
        'classname' => 'local_aiquizgen\\external\\scan_resources',
        'description' => 'Scans and returns all available course resources and files',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aiquizgen_generate_questions' => [
        'classname' => 'local_aiquizgen\\external\\generate_questions',
        'description' => 'Generates structured JSON quiz questions using Moodle core AI manager',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aiquizgen_reprompt_question' => [
        'classname' => 'local_aiquizgen\\external\\reprompt_question',
        'description' => 'Re-generates or modifies an individual question based on teacher instructions',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_aiquizgen_publish_quiz' => [
        'classname' => 'local_aiquizgen\\external\\publish_quiz',
        'description' => 'Imports approved questions to question bank and creates the Moodle quiz module',
        'type' => 'write',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
