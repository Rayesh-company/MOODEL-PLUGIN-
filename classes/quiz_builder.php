<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_aiquizgen;

use stdClass;
use context_course;
use question_bank;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Service to handle question bank insertion and quiz creation.
 */
class quiz_builder {

    /**
     * Creates or retrieves a question category in the course context.
     *
     * @param int $courseid
     * @param string $categoryname
     * @return stdClass The question category record.
     */
    public static function get_or_create_category(int $courseid, string $categoryname = ''): stdClass {
        global $DB;
        $context = context_course::instance($courseid);

        if (empty($categoryname)) {
            $categoryname = get_string('default_category_name', 'local_aiquizgen');
        }

        $existing = $DB->get_record('question_categories', [
            'contextid' => $context->id,
            'name' => $categoryname,
        ]);

        if ($existing) {
            return $existing;
        }

        $parent = question_get_default_category($context->id, true);

        $category = new stdClass();
        $category->name = $categoryname;
        $category->contextid = $context->id;
        $category->info = 'Category created by AI Quiz Generator';
        $category->infoformat = FORMAT_HTML;
        $category->stamp = make_unique_id_code();
        $category->parent = $parent ? $parent->id : 0;
        $category->sortorder = 999;
        $category->idnumber = null;

        $category->id = $DB->insert_record('question_categories', $category);
        return $category;
    }

    /**
     * Saves a parsed question object into Moodle's Question Bank.
     *
     * @param int $courseid
     * @param stdClass $category
     * @param array $qdata Array containing question definition.
     * @return int Created question ID.
     */
    public static function create_question(int $courseid, stdClass $category, array $qdata): int {
        $context = context_course::instance($courseid);
        $qtype = $qdata['qtype'] ?? 'multichoice';

        $formdata = new stdClass();
        $formdata->category = $category->id . ',' . $context->id;
        $formdata->qtype = $qtype;
        $formdata->name = $qdata['name'] ?? substr(strip_tags($qdata['questiontext']), 0, 80);
        $formdata->questiontext = [
            'text' => $qdata['questiontext'],
            'format' => FORMAT_HTML,
        ];
        $formdata->generalfeedback = [
            'text' => $qdata['generalfeedback'] ?? '',
            'format' => FORMAT_HTML,
        ];
        $formdata->defaultmark = $qdata['defaultmark'] ?? 1.0;
        $formdata->penalty = 0.3333333;
        $formdata->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

        switch ($qtype) {
            case 'multichoice':
                $formdata->single = 1;
                $formdata->shuffleanswers = 1;
                $formdata->answernumbering = 'abc';
                $formdata->showstandardinstruction = 0;
                $formdata->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->shownumcorrect = 0;
                $formdata->noanswers = count($qdata['answers'] ?? []);
                $formdata->answer = [];
                $formdata->fraction = [];
                $formdata->feedback = [];

                foreach ($qdata['answers'] as $idx => $ans) {
                    $formdata->answer[$idx] = ['text' => $ans['text'], 'format' => FORMAT_HTML];
                    $formdata->fraction[$idx] = !empty($ans['iscorrect']) ? 1.0 : 0.0;
                    $formdata->feedback[$idx] = ['text' => $ans['feedback'] ?? '', 'format' => FORMAT_HTML];
                }
                break;

            case 'truefalse':
                $isTrue = !empty($qdata['correctanswer']);
                $formdata->correctanswer = $isTrue ? 1 : 0;
                $formdata->feedbacktrue = ['text' => $qdata['feedbacktrue'] ?? 'Correct', 'format' => FORMAT_HTML];
                $formdata->feedbackfalse = ['text' => $qdata['feedbackfalse'] ?? 'Incorrect', 'format' => FORMAT_HTML];
                $formdata->showstandardinstruction = 0;
                break;

            case 'shortanswer':
                $formdata->usecase = 0;
                $formdata->answer = [];
                $formdata->fraction = [];
                $formdata->feedback = [];
                foreach ($qdata['acceptedanswers'] as $idx => $val) {
                    $formdata->answer[$idx] = $val;
                    $formdata->fraction[$idx] = 1.0;
                    $formdata->feedback[$idx] = ['text' => 'Correct', 'format' => FORMAT_HTML];
                }
                break;

            case 'essay':
                $formdata->responseformat = 'editor';
                $formdata->responserequired = 1;
                $formdata->responsefieldlines = 15;
                $formdata->attachments = 0;
                $formdata->attachmentsrequired = 0;
                $formdata->graderinfo = ['text' => $qdata['rubric'] ?? '', 'format' => FORMAT_HTML];
                $formdata->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];
                break;

            case 'match':
                $formdata->shuffleanswers = 1;
                $formdata->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                $formdata->shownumcorrect = 0;
                $formdata->subquestions = [];
                $formdata->subanswers = [];
                foreach ($qdata['pairs'] as $idx => $pair) {
                    $formdata->subquestions[$idx] = ['text' => $pair['question'], 'format' => FORMAT_HTML];
                    $formdata->subanswers[$idx] = $pair['answer'];
                }
                break;

            case 'ordering':
                if (question_bank::is_qtype_installed('ordering')) {
                    $formdata->layouttype = 0; // Vertical
                    $formdata->selecttype = 0; // All items
                    $formdata->selectcount = 0;
                    $formdata->gradingtype = 0; // Relative to correct position
                    $formdata->showgrading = 1;
                    $formdata->numberingstyle = '123';
                    $formdata->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->shownumcorrect = 0;
                    $formdata->answer = [];
                    $items = $qdata['items'] ?? $qdata['answers'] ?? [];
                    foreach ($items as $idx => $it) {
                        $itemtext = is_array($it) ? ($it['text'] ?? '') : (string)$it;
                        $formdata->answer[$idx] = ['text' => $itemtext, 'format' => FORMAT_HTML];
                    }
                } else {
                    // Fallback to multichoice if ordering plugin is not available.
                    $qtype = 'multichoice';
                    $formdata->qtype = 'multichoice';
                    $formdata->single = 1;
                    $formdata->shuffleanswers = 1;
                    $formdata->answernumbering = 'abc';
                    $formdata->showstandardinstruction = 0;
                    $formdata->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
                    $formdata->shownumcorrect = 0;
                    $items = $qdata['items'] ?? [];
                    $formdata->noanswers = count($items);
                    $formdata->answer = [];
                    $formdata->fraction = [];
                    $formdata->feedback = [];
                    foreach ($items as $idx => $it) {
                        $itemtext = is_array($it) ? ($it['text'] ?? '') : (string)$it;
                        $formdata->answer[$idx] = ['text' => $itemtext, 'format' => FORMAT_HTML];
                        $formdata->fraction[$idx] = ($idx === 0) ? 1.0 : 0.0;
                        $formdata->feedback[$idx] = ['text' => '', 'format' => FORMAT_HTML];
                    }
                }
                break;

            default:
                // Fallback to multichoice.
                $formdata->single = 1;
                $formdata->shuffleanswers = 1;
                break;
        }

        $qtypeobj = question_bank::get_qtype($qtype);
        $question = new stdClass();
        $question->qtype = $qtype;
        $saved = $qtypeobj->save_question($question, $formdata);
        return $saved->id;
    }

    /**
     * Programmatically creates a quiz instance and adds question slots to it.
     *
     * @param int $courseid
     * @param string $quizname
     * @param array $questionids
     * @param array $settings
     * @return int The quiz module instance ID.
     */
    public static function create_quiz_with_questions(int $courseid, string $quizname, array $questionids, array $settings = []): array {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $quizdata = new stdClass();
        $quizdata->modulename = 'quiz';
        $quizdata->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $quizdata->course = $course->id;
        $quizdata->section = $settings['section'] ?? 1;
        $quizdata->name = $quizname;
        $quizdata->introeditor = [
            'text' => '<p>' . ($settings['intro'] ?? get_string('default_quiz_intro', 'local_aiquizgen')) . '</p>',
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
        $quizdata->visible = 1;
        $quizdata->visibleoncoursepage = 1;

        $quizdata->timeopen = 0;
        $quizdata->timeclose = 0;
        $quizdata->timelimit = !empty($settings['timelimit']) ? intval($settings['timelimit']) * 60 : 0;
        $quizdata->overduehandling = 'autosubmit';
        $quizdata->preferredbehaviour = $settings['preferredbehaviour'] ?? 'deferredfeedback';
        $quizdata->attempts = $settings['attempts'] ?? 1;
        $quizdata->grademethod = QUIZ_GRADEHIGHEST;
        $quizdata->decimalpoints = 2;
        $quizdata->questiondecimalpoints = -1;
        $quizdata->questionsperpage = $settings['questionsperpage'] ?? 1;
        $quizdata->shuffleanswers = 1;
        $quizdata->sumgrades = 0;
        $quizdata->grade = 10;
        $quizdata->quizpassword = '';
        $quizdata->password = '';

        $createdcm = add_moduleinfo($quizdata, $course);
        $quizid = $createdcm->instance;
        $cmid = $createdcm->coursemodule;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $quiz->cmid = $cmid;

        // Add questions as slots.
        foreach ($questionids as $qid) {
            quiz_add_quiz_question($qid, $quiz, 0, 1.0);
        }

        if (class_exists('\mod_quiz\quiz_settings')) {
            \mod_quiz\quiz_settings::create($quizid)->get_grade_calculator()->recompute_quiz_sumgrades();
        } else if (function_exists('quiz_update_sumgrades')) {
            quiz_update_sumgrades($quiz);
        }
        return [
            'quizid' => $quizid,
            'cmid' => $cmid,
        ];
    }
}
