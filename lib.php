<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Extends course settings navigation to add AI Quiz Generator node if secondary navigation hook is not used.
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_aiquizgen_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $CFG;

    if ($context->contextlevel == CONTEXT_COURSE && $context->instanceid != SITEID) {
        if (has_capability('local/aiquizgen:generate', $context)) {
            if ($courseadmin = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
                $url = new moodle_url('/local/aiquizgen/view.php', ['id' => $context->instanceid]);
                $node = navigation_node::create(
                    get_string('pluginname', 'local_aiquizgen'),
                    $url,
                    navigation_node::NODETYPE_LEAF,
                    'local_aiquizgen',
                    'local_aiquizgen',
                    new pix_icon('i/quiz', '')
                );
                $courseadmin->add_node($node);
            }
        }
    }
}
