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

// Fetch AI quiz generation sessions for this course to power the Analytics Dashboard.
$sessions = $DB->get_records_sql("
    SELECT s.*, q.name AS quizname, cm.id AS cmid
      FROM {local_aiquizgen_sessions} s
      LEFT JOIN {quiz} q ON s.quizid = q.id
      LEFT JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = s.courseid
     WHERE s.courseid = ?
     ORDER BY s.timecreated DESC
", [$course->id]);

$quizsessions = [];
$total_students_participated = 0;
$total_ai_quizzes = count($sessions);
$all_grades = [];
$total_questions_all = 0;
$total_edited_questions = 0;

foreach ($sessions as $s) {
    $hasquiz = !empty($s->quizid) && !empty($s->cmid);
    $attempts_count = 0;
    $finished_attempts = 0;
    $avg_grade = null;

    if ($hasquiz) {
        $attempts_count = $DB->count_records('quiz_attempts', ['quiz' => $s->quizid]);
        $finished_attempts = $DB->count_records('quiz_attempts', ['quiz' => $s->quizid, 'state' => 'finished']);
        $avg_grade_raw = $DB->get_field_sql("SELECT AVG(grade) FROM {quiz_grades} WHERE quiz = ?", [$s->quizid]);
        if ($avg_grade_raw !== false && $avg_grade_raw !== null) {
            $avg_grade = round(floatval($avg_grade_raw), 2);
            $all_grades[] = $avg_grade;
        }
    }
    $total_students_participated += $finished_attempts;

    $q_stats = $DB->get_record_sql("
        SELECT COUNT(id) as total_q,
               SUM(editedbyuser) as edited_q,
               SUM(repromptcount) as reprompt_q
          FROM {local_aiquizgen_questions}
         WHERE sessionid = ?
    ", [$s->id]);

    $total_q = (int)($q_stats->total_q ?? $s->targetqcount);
    $edited_q = (int)($q_stats->edited_q ?? 0);
    $total_questions_all += $total_q;
    $total_edited_questions += $edited_q;

    $acceptance_rate = $total_q > 0 ? round((($total_q - $edited_q) / $total_q) * 100) : 100;

    $quizsessions[] = [
        'sessionid' => $s->id,
        'quizid' => $s->quizid,
        'cmid' => $s->cmid,
        'quizname' => $s->quizname ?: ('آزمون شماره ' . $s->id),
        'difficulty' => $s->difficulty,
        'qtypes' => $s->qtypes,
        'targetqcount' => $total_q,
        'timecreatedstr' => userdate($s->timecreated, get_string('strftimedatetime', 'langconfig')),
        'hasquiz' => $hasquiz,
        'attempts_count' => $attempts_count,
        'finished_attempts' => $finished_attempts,
        'avg_grade' => $avg_grade !== null ? $avg_grade : '-',
        'acceptance_rate' => $acceptance_rate,
        'quiz_url' => $hasquiz ? (new moodle_url('/mod/quiz/view.php', ['id' => $s->cmid]))->out(false) : '',
        'overview_report_url' => $hasquiz ? (new moodle_url('/mod/quiz/report.php', ['id' => $s->cmid, 'mode' => 'overview']))->out(false) : '',
        'statistics_report_url' => $hasquiz ? (new moodle_url('/mod/quiz/report.php', ['id' => $s->cmid, 'mode' => 'statistics']))->out(false) : '',
        'responses_report_url' => $hasquiz ? (new moodle_url('/mod/quiz/report.php', ['id' => $s->cmid, 'mode' => 'responses']))->out(false) : '',
        'grading_report_url' => $hasquiz ? (new moodle_url('/mod/quiz/report.php', ['id' => $s->cmid, 'mode' => 'grading']))->out(false) : '',
    ];
}

$overall_quality_score = $total_questions_all > 0
    ? round((($total_questions_all - $total_edited_questions) / $total_questions_all) * 100)
    : 100;

$overall_avg_grade = count($all_grades) > 0
    ? round(array_sum($all_grades) / count($all_grades), 1)
    : '-';

$templatecontext = [
    'courseid' => $course->id,
    'coursename' => $course->fullname,
    'userid' => $USER->id,
    'sesskey' => sesskey(),
    'siteurl' => $CFG->wwwroot,
    'total_ai_quizzes' => $total_ai_quizzes,
    'has_sessions' => ($total_ai_quizzes > 0),
    'sessions' => $quizsessions,
    'total_participated' => $total_students_participated,
    'overall_quality_score' => $overall_quality_score,
    'overall_avg_grade' => $overall_avg_grade,
];

echo $OUTPUT->render_from_template('local_aiquizgen/wizard', $templatecontext);

echo $OUTPUT->footer();
