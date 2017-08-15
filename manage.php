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

$id = optional_param('cmid', 0, PARAM_INT); // Course_module ID
$w  = optional_param('w', 0, PARAM_INT);  // Workbook instance ID

if ($id) {
    $cm         = get_coursemodule_from_id('workbook', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $workbook  = $DB->get_record('workbook', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($w) {
    $workbook  = $DB->get_record('workbook', array('id' => $w), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);
require_capability('mod/workbook:manage', context_module::instance($cm->id));

// Print the page header.
$PAGE->set_url('/mod/workbook/manage.php', array('id' => $cm->id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($workbook->name).' - '.get_string('manage', 'workbook'));

// Output starts here.
echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($PAGE->heading);

$addpageurl = new moodle_url('/mod/workbook/page.php', array('wid' => $workbook->id));
echo html_writer::tag('div', $OUTPUT->single_button($addpageurl, get_string('addpage', 'workbook')),
    array('class' => 'mod-workbook-page-addbtn'));

$pages = $DB->get_records('workbook_page', array('workbookid' => $workbook->id));
$renderer = $PAGE->get_renderer('mod_workbook');
echo $renderer->config_pages($workbook);

// Finish the page.
echo $OUTPUT->footer();
