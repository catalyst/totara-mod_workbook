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
 * Workbook item submission history.
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/workbook/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

$pageitemid  = required_param('piid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$format = optional_param('format', '', PARAM_TEXT);
$sid = optional_param('sid', '0', PARAM_INT);
$debug = optional_param('debug', 0, PARAM_INT);

$workbook = \mod_workbook\helper::get_workbook_for_pageitem($pageitemid);
$pageitem = $DB->get_record('workbook_page_item', array('id' => $pageitemid), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $workbook->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('workbook', $workbook->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

$modcontext = context_module::instance($cm->id);
if (!\mod_workbook\helper::can_assess($modcontext, $userid) && !\mod_workbook\helper::can_submit($modcontext, $userid)) {
    print_error('accessdenied', 'workbook');
}

if (!$report = reportbuilder_get_embedded_report('workbook_user_submission_history', array('pageitemid' => $pageitemid, 'userid' => $userid), false, $sid)) {
    print_error('error:couldnotgenerateembeddedreport', 'totara_reportbuilder');
}

if (groups_get_activity_groupmode($cm) != NOGROUPS) {
    // Restrict report by groups the user belongs to - if user belongs any group(s).
    $groups = groups_get_all_groups($course->id, $USER->id);
    $enrolledsql = $enrolledparams = array();
    foreach ($groups as $group) {
        list($esql, $eparams) = get_enrolled_sql($modcontext, '', $group->id);
        $enrolledsql[] = "(base.userid IN ($esql))";
        $enrolledparams = array_merge($enrolledparams, $eparams);
    }
    if (!empty($enrolledsql)) {
        $enrolledsql = implode(' OR ', $enrolledsql);
        $report->set_post_config_restrictions(array("($enrolledsql)", $enrolledparams));
    }
}

$PAGE->set_url('/mod/workbook/submissionhistory.php', array('piid' => $pageitemid, 'userid' => $userid));
$page = $DB->get_record('workbook_page', array('id' => $pageitem->pageid), '*', MUST_EXIST);
$pagenavtitle = empty($page->navtitle) ? format_string($page->title) : format_string($page->navtitle);
$PAGE->set_title(format_string($workbook->name));
$headingstr = format_string($workbook->name).' - '.get_string('itemsubmissionhistory', 'workbook');
$PAGE->set_heading($headingstr);
$PAGE->navbar->add($pagenavtitle, new moodle_url('/mod/workbook/view.php', array('id' => $cm->id, 'pid' => $page->id, 'userid' => $userid)));


$renderer = $PAGE->get_renderer('totara_reportbuilder');

if ($format != '') {
    $report->export_data($format);
    die;
}

$report->include_js();

echo $OUTPUT->header();

echo $OUTPUT->heading($headingstr);

// Standard report stuff.
echo $OUTPUT->container_start('', 'workbook_user_submission_history');

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();

if ($debug) {
    $report->debug($debug);
}
echo $renderer->print_description($report->description, $report->_id);

$report->display_search();
$report->display_sidebar_search();

$report->display_table();

// Export button.
$renderer->export_select($report->_id, $sid);

echo $OUTPUT->container_end();

echo $OUTPUT->footer();

