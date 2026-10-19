<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiquizgen', get_string('pluginname', 'local_aiquizgen'));

    $settings->add(new admin_setting_heading(
        'local_aiquizgen/general_heading',
        get_string('settings_general', 'local_aiquizgen'),
        get_string('settings_general_desc', 'local_aiquizgen')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_aiquizgen/enablecoursetab',
        get_string('enablecoursetab', 'local_aiquizgen'),
        get_string('enablecoursetab_desc', 'local_aiquizgen'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquizgen/maxquestions',
        get_string('maxquestions', 'local_aiquizgen'),
        get_string('maxquestions_desc', 'local_aiquizgen'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_aiquizgen/mastra_heading',
        get_string('settings_mastra', 'local_aiquizgen'),
        get_string('settings_mastra_desc', 'local_aiquizgen')
    ));

    $settings->add(new admin_setting_configselect(
        'local_aiquizgen/ai_engine',
        get_string('ai_engine', 'local_aiquizgen'),
        get_string('ai_engine_desc', 'local_aiquizgen'),
        'mastra_hybrid',
        [
            'mastra_hybrid' => get_string('engine_mastra_hybrid', 'local_aiquizgen'),
            'moodle_ai' => get_string('engine_moodle_ai', 'local_aiquizgen'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_aiquizgen/mastra_endpoint',
        get_string('mastra_endpoint', 'local_aiquizgen'),
        get_string('mastra_endpoint_desc', 'local_aiquizgen'),
        'http://localhost:3000/api/workflows/quiz-generation-workflow',
        PARAM_URL
    ));

    $ADMIN->add('localplugins', $settings);
}
