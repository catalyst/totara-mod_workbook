<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package mod
 * @subpackage workbook
 */

/**
 * Upload a file to a workbook essay page item
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('uploadfile_form.php');
require_once('lib.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

// Check that workbook exists.
$sql = "SELECT w.*, p.id AS pageid, pi.id AS itemid, pi.allowfileuploads
    FROM {workbook_page_item_submit} pis
    JOIN {workbook_page_item} pi ON pis.pageitemid = pi.id
    JOIN {workbook_page} p ON pi.pageid = p.id
    JOIN {workbook} w ON p.workbookid = w.id
    WHERE pis.id = ?";
if (!$workbook = $DB->get_record_sql($sql, array($submissionid))) {
    print_error('workbook not found');
}

if (!$workbook->allowfileuploads) {
    print_error('accessdenied', 'workbook');
}

$course = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);
$modcontext = context_module::instance($cm->id);

require_login($course, true, $cm);

if (!\mod_workbook\helper::can_submit($modcontext, $userid)  && !\mod_workbook\helper::can_assess($modcontext, $userid)) {
    print_error('accessdenied', 'workbook');
}

$viewurl = new moodle_url('/mod/workbook/view.php', array('userid' => $userid, 'wid' => $workbook->id, 'pid' => $workbook->pageid));

$userworkbook = new \mod_workbook\user_workbook($cm->instance, $userid);
// Ensure the user can actually change this item.
$item = $userworkbook->get_item($workbook->itemid);
if (!$userworkbook->can_submit_item($item)) {
    print_error('accessdenied', 'workbook', $viewurl);
}

$PAGE->set_pagelayout('noblocks');
$PAGE->set_url('/mod/workbook/uploadfile.php', array('submission' => $submissionid, 'userid' => $userid));

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('user not found');
}


$fileoptions = $FILEPICKER_OPTIONS;
$fileoptions['maxfiles'] = 10;

$item = new stdClass();
$item->submissionid = $submissionid;
$item->userid = $userid;
$item = file_prepare_standard_filemanager($item, 'submissions',
        $fileoptions, $modcontext, 'mod_workbook', 'submissions', $submissionid);

$mform = new workbook_pageitem_files_form(
    null,
    array(
        'submissionid' => $submissionid,
        'userid' => $userid,
        'fileoptions' => $fileoptions
    )
);
$mform->set_data($item);

if ($data = $mform->get_data()) {
    // Update the current submission (which might result in a new record), as we've fiddled with the files.
    $submission = $userworkbook->get_submission($workbook->itemid);
    $submission = $userworkbook->save_submission_response($workbook->itemid, $submission->response);

    // Process files. Use latest submission id, so we save draft files to correct filearea.
    $data = file_postupdate_standard_filemanager($data, 'submissions',
            $fileoptions, $modcontext, 'mod_workbook', 'submissions', $submission->id);

    totara_set_notification(get_string('filesupdated', 'workbook'), $viewurl, array('class' => 'notifysuccess'));
} else if ($mform->is_cancelled()) {
    redirect($viewurl);
}

$strheading = get_string('updatefiles', 'workbook');
$PAGE->navbar->add(fullname($user), $viewurl);
$PAGE->navbar->add(get_string('updatefiles', 'workbook'));
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

echo $OUTPUT->header();

echo $OUTPUT->heading($strheading, 1);

$mform->display();

echo $OUTPUT->footer();
