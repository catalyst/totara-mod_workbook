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
 * Library of interface functions and constants for module workbook
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the workbook specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Workbook submission statuses
 */
define('WORKBOOK_STATUS_DRAFT', 0);
define('WORKBOOK_STATUS_SUBMITTED', 1);
define('WORKBOOK_STATUS_GRADED', 2);
define('WORKBOOK_STATUS_PASSED', 3);
define('WORKBOOK_STATUS_SUPERSEDED', 4);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function workbook_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the workbook into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $workbook Submitted data from the form in mod_form.php
 * @param mod_workbook_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted workbook record
 */
function workbook_add_instance(stdClass $workbook, mod_workbook_mod_form $mform = null) {
    global $DB;

    $workbook->timecreated = time();

    // You may have to add extra stuff in here.

    $workbook->id = $DB->insert_record('workbook', $workbook);

    return $workbook->id;
}

/**
 * Updates an instance of the workbook in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $workbook An object from the form in mod_form.php
 * @param mod_workbook_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function workbook_update_instance(stdClass $workbook, mod_workbook_mod_form $mform = null) {
    global $DB;

    $workbook->timemodified = time();
    $workbook->id = $workbook->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('workbook', $workbook);

    return $result;
}

/**
 * Removes an instance of the workbook from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function workbook_delete_instance($id) {
    global $DB;

    if (!$workbook = $DB->get_record('workbook', array('id' => $id))) {
        return false;
    }

    $transaction = $DB->start_delegated_transaction();

    // Delete pages.
    $pages = $DB->get_records('workbook_page', array('workbookid' => $id, 'parentid' => 0));
    foreach ($pages as $page) {
        \mod_workbook\page::delete($page);
    }

    // Delete the workbook instance.
    $DB->delete_records('workbook', array('id' => $workbook->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $workbook The workbook instance record
 * @return stdClass|null
 */
function workbook_user_outline($course, $user, $mod, $workbook) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $workbook the module instance record
 */
function workbook_user_complete($course, $user, $mod, $workbook) {
}

/**
 * Obtains the specific requirements for completion.
 *
 * @param object $cm Course-module
 * @return array Requirements for completion
 */
function workbook_get_completion_requirements($cm) {
    global $DB;

    $workbook = $DB->get_record('workbook', array('id' => $cm->instance));

    $result = array();

    if ($workbook->completionitems) {
        $result[] = get_string('completionitems', 'workbook');
    }

    return $result;
}

/**
 * Obtains the completion progress.
 *
 * @param object $cm      Course-module
 * @param int    $userid  User ID
 * @return string The current status of completion for the user
 */
function workbook_get_completion_progress($cm, $userid) {
    global $DB;

    // Get workbook details.
    $userworkbook = new \mod_workbook\user_workbook($cm->instance, $userid);

    $result = array();

    if ($userworkbook->workbook->completionitems && $userworkbook->is_complete()) {
        $result[] = get_string('completionitems', 'workbook');
    }

    return $result;
}


/**
 * Obtains the automatic completion state for this workbook activity based on any conditions
 * in workbook settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function workbook_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get workbook for user.
    $userworkbook = new \mod_workbook\user_workbook($cm->instance, $userid);

    // This means that if only view is required we don't end up with a false state.
    if (empty($userworkbook->workbook->completionitems)) {
        return $type;
    }

    return $userworkbook->is_complete();
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in workbook activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function workbook_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link workbook_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function workbook_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link workbook_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function workbook_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function workbook_get_extra_capabilities() {
    return array(
        'mod/workbook:assess',
        'mod/workbook:manage'
    );
}


/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function workbook_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for workbook file areas
 *
 * @package mod_workbook
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function workbook_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the workbook file areas
 *
 * @package mod_workbook
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the workbook's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function workbook_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    if ($filearea == 'submissions') {
        // Check permissions to submission files.
        $submissionid = $args[0];
        $submission = $DB->get_record('workbook_page_item_submit', array('id' => $submissionid), '*', MUST_EXIST);
        if (!\mod_workbook\helper::can_submit($context, $submission->userid)  && !\mod_workbook\helper::can_assess($context, $submission->userid)) {
            return false;
        }
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_workbook/$filearea/$relativepath";
    if ((!$file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        send_file_not_found();
    }

    // finally send the file
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding workbook nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the workbook module instance
 * @param stdClass $course current course record
 * @param stdClass $module current workbook instance record
 * @param cm_info $cm course module information
 */
function workbook_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    $context = context_module::instance($cm->id);
    if (has_capability('mod/workbook:assess', $context)) {
        $link = new moodle_url('/mod/workbook/report.php', array('cmid' => $cm->id));
        $node = $navref->add(get_string('assesssubmissions', 'workbook'), $link, navigation_node::TYPE_SETTING);
    }
}

/**
 * Extends the settings navigation with the workbook settings
 *
 * This function is called when the context for the page is a workbook module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $workbooknode workbook administration node
 */
function workbook_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $workbooknode=null) {
    global $PAGE;

    if (has_capability('mod/workbook:assess', $PAGE->cm->context)) {
        $link = new moodle_url('/mod/workbook/report.php', array('cmid' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('assesssubmissions', 'workbook'),
                new moodle_url('/mod/workbook/report.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_workbook_assess',
                new pix_icon('i/valid', ''));
        $workbooknode->add_node($node);
    }

    if (has_capability('mod/workbook:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('editpages', 'workbook'),
                new moodle_url('/mod/workbook/manage.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_workbook_manage',
                new pix_icon('t/edit', ''));
        $workbooknode->add_node($node);
    }
}


/**
 * Comments helper functions and callbacks
 *
 */

/**
 * Validate comment parameters, before other comment actions are performed
 *
 * @param stdClass $comment_param {
 *              context  => context the context object
 *              courseid => int course id
 *              cm       => stdClass course module object
 *              commentarea => string comment area
 *              itemid      => int itemid
 * }
 * @return boolean
 */
function workbook_comment_validate($comment_param) {
    if (!strstr($comment_param->commentarea, 'workbook_page_item_')) {
        throw new comment_exception('invalidcommentarea');
    }
    if (empty($comment_param->itemid)) {
        throw new comment_exception('invalidcommentitemid');
    }

    return true;
}


/**
 * Running addtional permission check on plugins
 *
 * @param stdClass $comment_param
 * @return array
 */
function workbook_comment_permissions($comment_param) {
    global $USER;

    $userid = $comment_param->itemid;
    $cansubmit = \mod_workbook\helper::can_submit($comment_param->context, $userid);
    $canassess = \mod_workbook\helper::can_assess($comment_param->context, $userid);

    if ($cansubmit || $canassess) {
        return array('post'=>true, 'view'=>true);
    }

    return array('post'=>false, 'view'=>true);
}

function workbook_comment_template() {
    global $OUTPUT, $PAGE;

    // Use the totara default comment template
    $renderer = $PAGE->get_renderer('totara_core');

    return $renderer->comment_template();
}

