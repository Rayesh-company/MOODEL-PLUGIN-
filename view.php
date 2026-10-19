<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiquizgen:generate', $context);

$PAGE->set_course($course);
$PAGE->set_url('/local/aiquizgen/view.php', ['id' => $course->id]);
$PAGE->set_title(get_string('pluginname', 'local_aiquizgen') . ' - ' . $course->fullname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$templatecontext = [
    'courseid' => $course->id,
    'coursename' => $course->fullname,
    'userid' => $USER->id,
    'sesskey' => sesskey(),
    'siteurl' => $CFG->wwwroot,
];

echo $OUTPUT->render_from_template('local_aiquizgen/wizard', $templatecontext);

echo $OUTPUT->footer();
