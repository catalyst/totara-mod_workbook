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
$pageid  = required_param('pid', PARAM_INT);  // Page id.
$itemid = optional_param('id', 0, PARAM_INT);  // Page item id.
$action = optional_param('action', '', PARAM_ALPHA);

$workbook = $DB->get_record('workbook', array('id' => $workbookid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/workbook:manage', $context);

$PAGE->set_url('/mod/workbook/pageitem.php', array('wid' => $workbookid, 'pid' => $pageid, 'id' => $itemid));

$textoptions = $TEXTAREA_OPTIONS;
$textoptions['context'] = $context;

// Handle actions
$redirecturl = new moodle_url('/mod/workbook/manage.php', array('cmid' => $cm->id));
if ($action == 'delete') {
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        echo $OUTPUT->header();
        $confirmurl = $PAGE->url;
        $confirmurl->params(array('action' => 'delete', 'confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->confirm(get_string('confirmitemdelete', 'workbook'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        die();
    }

    require_sesskey();

    \mod_workbook\page::delete_item($itemid, $context);
    totara_set_notification(get_string('itemdeleted', 'workbook'), $redirecturl, array('class' => 'notifysuccess'));
} else if ($action == 'moveup' || $action == 'movedown') {
    $item = $DB->get_record('workbook_page_item', array('id' => $itemid), '*', MUST_EXIST);
    $newsortorder = $action == 'moveup' ? $item->sortorder-1 : $item->sortorder+1;

    \mod_workbook\page::item_update_sortorder($item, $newsortorder);
    totara_set_notification(get_string('itemmoved', 'workbook'), $redirecturl, array('class' => 'notifysuccess'));
}

$form = new workbook_page_item_form(null, array('workbookid' => $workbookid, 'pageid' => $pageid));
if ($data = $form->get_data()) {
    // Save page item.
    $item = new stdClass();
    $item->pageid = $data->pid;
    $item->content = '';  // Will be updated by file_postupdate.
    $item->itemtype = $data->itemtype;
    $item->name = $data->name;
    $item->requiredgrade = empty($data->requiredgrade) ? 0 : $data->requiredgrade;
    $item->allowcomments = $data->allowcomments;
    $item->allowfileuploads = $data->allowfileuploads;

    if (empty($data->id)) {
        // Add.
        $item->sortorder = \mod_workbook\page::item_get_new_sortorder($item);
        $data->id = $DB->insert_record('workbook_page_item', $item);
    } else {
        // Update.
        $item->id = $data->id;
        $DB->update_record('workbook_page_item', $item);
    }

    $data = file_postupdate_standard_editor($data, 'content', $textoptions, $context, 'mod_workbook', 'workbook_item_content', $data->id);
    $DB->set_field('workbook_page_item', 'content', $data->content, array('id' => $data->id));

    redirect(new moodle_url('/mod/workbook/manage.php', array('cmid' => $cm->id)));
}

// Print the page header.
$actionstr = empty($itemid) ? get_string('additem', 'workbook') : get_string('edititem', 'workbook');
$PAGE->set_title(format_string($workbook->name));
$PAGE->set_heading(format_string($workbook->name).' - '.$actionstr);

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($PAGE->heading);

if (!empty($itemid)) {
    $item = $DB->get_record('workbook_page_item', array('id' => $itemid), '*', MUST_EXIST);
    $item->contentformat = FORMAT_HTML;
    $item = file_prepare_standard_editor($item, 'content', $textoptions, $context,
        'mod_workbook', 'workbook_item_content', $item->id);
    $form->set_data($item);
}

// Display
$form->display();

// Finish the page.
echo $OUTPUT->footer();
