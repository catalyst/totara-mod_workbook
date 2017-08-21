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
 * Prints a particular instance of workbook
 *
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/workbook/lib.php');
require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$wid  = optional_param('wid', 0, PARAM_INT);  // workbook instance ID.
$userid = optional_param('userid', $USER->id, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('workbook', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $workbook  = $DB->get_record('workbook', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($wid) {
    $workbook  = $DB->get_record('workbook', array('id' => $wid), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

if ($USER->id != $userid) {
    require_capability('mod/workbook:viewall', $context);
} else {
    require_capability('mod/workbook:view', $context);
}

$renderer = $PAGE->get_renderer('workbook');
$userworkbook = new \mod_workbook\user_workbook($workbook->id, $userid);

// Page header.
$PAGE->set_url('/mod/workbook/print.php', array('id' => $cm->id, 'userid' => $userid));
$strhead = format_string($workbook->name).' - '.get_string('printthisworkbook', 'workbook');
$PAGE->set_title($strhead);
$PAGE->set_heading($strhead);

$args = array('args' => '{"workbookid":'.$userworkbook->workbook->id.
    ', "userid":'.$userid.
    '}');
$jsmodule = array(
    'name' => 'mod_workbook_view',
    'fullpath' => '/mod/workbook/print.js',
    'requires' => array('json')
);
$PAGE->requires->js_init_call('M.mod_workbook_print.init', $args, true, $jsmodule);


// Output starts here.
echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($workbook->name).' - '.fullname(\core_user::get_user($userid)));

echo html_writer::start_tag('div', array('id' => 'mod-workbook-content', 'class' => 'mod-workbook-print'));
foreach ($userworkbook->pages as $page) {
    echo $renderer->user_workbook_page($userworkbook, $page->id);
}
echo html_writer::end_tag('div');

// Finish the page.
echo $OUTPUT->footer();
