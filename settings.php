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

    $ADMIN->add('localplugins', $settings);
}
