<?php
/*
 * Copyright (C) 2015 onwards Catalyst IT
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author  Eugene Venter <eugene@catalyst.net.nz>
 * @package mod_workbook
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Handles ajax requests made by the workbook.
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/workbook/lib.php');

$workbookid = required_param('wid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$userid = optional_param('userid', $USER->id, PARAM_INT);

$workbook = $DB->get_record('workbook', array('id' => $workbookid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

if ($USER->id != $userid) {
    require_capability('mod/workbook:viewall', $context);
} else {
    require_capability('mod/workbook:view', $context);
}

$renderer = $PAGE->get_renderer('workbook');
$userworkbook = new \mod_workbook\user_workbook($workbook->id, $userid);

try {
    switch ($action) {
        case 'getpage':
            $pageid = required_param('pid', PARAM_INT);

            $PAGE->requires->strings_for_js(array('confirmsubmit'), 'workbook');
            $content = $renderer->user_workbook_page($userworkbook, $pageid);
            $content .= $PAGE->requires->get_end_code(false);  // Any javascript that might be required.

            $jsonparams = array(
                'status' => 'success',
                'content' => $content
            );

            echo json_encode($jsonparams);

            return;

        case 'itemdraft':
        case 'itemsubmit':
            $itemid = required_param('iid', PARAM_INT);
            $response = required_param('response', PARAM_TEXT);
            if (!$item = $userworkbook->get_item($itemid)) {
                print_error('accessdenied', 'workbook');
            }
            if (!$userworkbook->can_submit_item($item)) {
                print_error('error:cannotsubmititem', 'workbook');
            }
            $currentsubmission = $item->submission;

            if ($action == 'itemsubmit') {
                $submission = $userworkbook->submit_item($itemid, $response);
            } else {
                $submission = $userworkbook->save_submission_response($itemid, $response);
            }

            $jsonparams = array(
                'status' => 'success',
                'submissionstatus' => $renderer->submission_status($submission->status),
                'timemodified' => get_string('modifiedonx', 'workbook', userdate($submission->timemodified, get_string('strftimedatetimeshort'))),
                'pageattrequired' => $userworkbook->page_requires_attention($item->pageid)
            );

            echo json_encode($jsonparams);
            return;

        case 'grade':
            $itemid = required_param('iid', PARAM_INT);
            $grade = required_param('grade', PARAM_FLOAT);
            if (!$item = $userworkbook->get_item($itemid)) {
                print_error('accessdenied', 'workbook');
            }
            if (!$userworkbook->can_assess_submission($item->submission)) {
                print_error('error:cannotgradeitem', 'workbook');
            }

            $submission = $userworkbook->grade_item_submission($itemid, $grade);

            $jsonparams = array(
                'status' => 'success',
                'submissionstatus' => $renderer->submission_status($submission->status),
                'gradinghtml' => $renderer->submission_grading($submission, $item->requiredgrade, !$userworkbook->can_assess_submission($submission)),
                'grade' => $submission->grade,
                'pageattrequired' => $userworkbook->page_requires_attention($item->pageid)
            );

            echo json_encode($jsonparams);
            return;
        default:
            echo '';
            return;
    }
} catch (Exception $e) {
    $jsonparams = array(
        'status' => 'error',
        'msg' => $e->getMessage()
    );

    echo json_encode($jsonparams);
}
