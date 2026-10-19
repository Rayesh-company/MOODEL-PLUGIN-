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
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_course;
use context_module;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/modinfolib.php');

/**
 * External API to scan course resources.
 */
class scan_resources extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to scan', VALUE_REQUIRED),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/aiquizgen:generate', $context);

        $modinfo = get_fast_modinfo($params['courseid']);
        $fs = get_file_storage();
        $resources = [];

        // Scan mod_resource.
        foreach ($modinfo->get_instances_of('resource') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $modctx = context_module::instance($cm->id);
            $files = $fs->get_area_files($modctx->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);

            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $resources[] = [
                    'id' => $file->get_id(),
                    'filename' => $file->get_filename(),
                    'filesize' => $file->get_filesize(),
                    'filesizeformatted' => display_size($file->get_filesize()),
                    'mimetype' => $file->get_mimetype(),
                    'cmid' => $cm->id,
                    'sectionname' => $cm->get_section_info()->name ?? ('Section ' . $cm->sectionnum),
                ];
            }
        }

        // Scan mod_folder.
        foreach ($modinfo->get_instances_of('folder') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $modctx = context_module::instance($cm->id);
            $files = $fs->get_area_files($modctx->id, 'mod_folder', 'content', 0, 'sortorder DESC, id ASC', false);

            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $resources[] = [
                    'id' => $file->get_id(),
                    'filename' => $file->get_filename(),
                    'filesize' => $file->get_filesize(),
                    'filesizeformatted' => display_size($file->get_filesize()),
                    'mimetype' => $file->get_mimetype(),
                    'cmid' => $cm->id,
                    'sectionname' => $cm->get_section_info()->name ?? ('Section ' . $cm->sectionnum),
                ];
            }
        }

        return [
            'status' => true,
            'files' => $resources,
            'count' => count($resources),
        ];
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_BOOL, 'Operation status'),
            'count' => new external_value(PARAM_INT, 'Number of files discovered'),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Stored file ID'),
                    'filename' => new external_value(PARAM_TEXT, 'File name'),
                    'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
                    'filesizeformatted' => new external_value(PARAM_TEXT, 'Human readable size'),
                    'mimetype' => new external_value(PARAM_TEXT, 'MIME type'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'sectionname' => new external_value(PARAM_TEXT, 'Course section name'),
                ])
            ),
        ]);
    }
}
