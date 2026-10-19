<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_aiquizgen;

use core\hook\navigation\secondary_extend;
use navigation_node;
use moodle_url;
use context_course;

/**
 * Hook callbacks for local_aiquizgen.
 */
class hook_callbacks {

    /**
     * Injects the AI Quiz Generator tab into the course secondary navigation.
     *
     * @param secondary_extend $hook
     */
    public static function secondary_extend(secondary_extend $hook): void {
        global $CFG;

        if (get_config('local_aiquizgen', 'enablecoursetab') === '0') {
            return;
        }

        $secondary = $hook->get_secondaryview();
        $context = $secondary->context;

        // Ensure we are inside a course context (not site home).
        if ($context->contextlevel === CONTEXT_COURSE && $context->instanceid != SITEID) {
            $courseid = $context->instanceid;

            // Only show to users with permission to generate quizzes.
            if (has_capability('local/aiquizgen:generate', $context)) {
                $secondary->add(
                    get_string('pluginname', 'local_aiquizgen'),
                    new moodle_url('/local/aiquizgen/view.php', ['id' => $courseid]),
                    navigation_node::TYPE_CUSTOM,
                    null,
                    'local_aiquizgen_tab'
                );
            }
        }
    }
}
