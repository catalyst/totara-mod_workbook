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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/forms.php');

$workbookid = required_param('wid', PARAM_INT); // Workbook instance id.
$pageid  = optional_param('id', 0, PARAM_INT);  // Page id.
$action = optional_param('action', '', PARAM_ALPHA);

$workbook = $DB->get_record('workbook', array('id' => $workbookid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/workbook:manage', context_module::instance($cm->id));

$PAGE->set_url('/mod/workbook/page.php', array('wid' => $workbookid, 'id' => $pageid));

// Handle actions
$redirecturl = new moodle_url('/mod/workbook/manage.php', array('cmid' => $cm->id));
if ($action == 'delete') {
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        echo $OUTPUT->header();
        $confirmurl = $PAGE->url;
        $confirmurl->params(array('action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->confirm(get_string('confirmpagedelete', 'workbook'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        die();
    }

    $page = $DB->get_record('workbook_page', array('id' => $pageid), '*', MUST_EXIST);
    \mod_workbook\page::delete($page);
    \mod_workbook\page::reindex_pages($workbookid);
    totara_set_notification(get_string('pagedeleted', 'workbook'), $redirecturl, array('class' => 'notifysuccess'));
} else if ($action == 'moveup' || $action == 'movedown') {
    $page = $DB->get_record('workbook_page', array('id' => $pageid), '*', MUST_EXIST);
    if ($action == 'movedown') {
        $newsortorder = \mod_workbook\page::get_next_sortorder($page);
    } else {
        $newsortorder = \mod_workbook\page::get_previous_sortorder($page);
    }
    \mod_workbook\page::update_sortorder($page, $newsortorder);
    totara_set_notification(get_string('pagemoved', 'workbook'), $redirecturl, array('class' => 'notifysuccess'));
}

$form = new workbook_page_form(null, array('courseid' => $course->id, 'workbookid' => $workbookid));
if ($data = $form->get_data()) {
    // Save page
    $page = new stdClass();
    $page->workbookid = $data->wid;
    $page->title = $data->title;
    $page->navtitle = $data->navtitle;
    $page->parentid = $data->parentid;

    if (empty($data->id)) {
        // Add
        $transaction = $DB->start_delegated_transaction();
        $page->sortorder = \mod_workbook\page::get_new_sortorder($page);
        $page->id = $DB->insert_record('workbook_page', $page);
        \mod_workbook\page::increase_sortorder_below($page);
        $transaction->allow_commit();
    } else {
        // Update
        $page->id = $data->id;

        $oldpage = $DB->get_record('workbook_page', array('id' => $page->id));

        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('workbook_page', $page);

        if ($oldpage->parentid != $page->parentid) {
            \mod_workbook\page::reindex_pages($workbookid);
        }
        $transaction->allow_commit();
    }

    redirect(new moodle_url('/mod/workbook/manage.php', array('cmid' => $cm->id)));
}

// Print the page header.
$actionstr = empty($pageid) ? get_string('addpage', 'workbook') : get_string('editpage', 'workbook');
$PAGE->set_title(format_string($workbook->name));
$PAGE->set_heading(format_string($workbook->name).' - '.$actionstr);

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($PAGE->heading);

if (!empty($pageid)) {
    $page = $DB->get_record('workbook_page', array('id' => $pageid), '*', MUST_EXIST);
    $form->set_data($page);
}

// Display
$form->display();

// Finish the page.
echo $OUTPUT->footer();
