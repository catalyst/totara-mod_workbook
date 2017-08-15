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

namespace mod_workbook;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/message/messagelib.php');

class helper {
    static function is_complete($workbookid, $userid) {
        global $DB;

        $sql = "SELECT pi.*
            FROM {workbook_page} p
            JOIN {workbook_page_item} pi ON p.id = pi.pageid AND pi.requiredgrade > 0
            LEFT JOIN {workbook_page_item_submit} pis ON pi.id = pis.pageitemid AND pis.superseded = 0 AND pis.status = ?
            WHERE p.workbookid = ? AND pis.id IS NULL";

        return !$DB->record_exists_sql($sql, array(WORKBOOK_STATUS_PASSED, $workbookid));
    }


    static function get_assessors($workbookid, $courseid, $userid) {
        $cm = get_coursemodule_from_instance('workbook', $workbookid, $courseid, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $groups = groups_get_all_groups($courseid, $userid);
        if (empty($groups) || groups_get_activity_groupmode($cm) == NOGROUPS) {
            // Return all assessors in the course.
            return get_enrolled_users($context, 'mod/workbook:assess');
        }

        // Get all assessors in the user's group.
        $users = array();
        foreach ($groups as $group) {
            $users = $users + get_enrolled_users($context, 'mod/workbook:assess', $group->id);
        }

        return $users;
    }


    static function get_workbook_for_pageitem($pageitemid) {
        global $DB;

        $sql = "SELECT w.*, pi.pageid
            FROM {workbook_page_item} pi
            JOIN {workbook_page} p ON pi.pageid = p.id
            JOIN {workbook} w ON p.workbookid = w.id
            WHERE pi.id = ?";

        return $DB->get_record_sql($sql, array($pageitemid), MUST_EXIST);
    }


    static function get_navigation_block($userworkbook, $currentpageid, $renderer) {
        $bc = new \block_contents();
        $bc->attributes['id'] = 'mod_workbook_navblock';
        $bc->title = get_string('workbooknavigation', 'workbook');
        $bc->content = $renderer->navigation($userworkbook, $currentpageid);

        return $bc;
    }


    static function get_itemtype_instance($workbookid, $item) {
        $itemclass = '\mod_workbook\itemtype\\'.$item->itemtype;
        return new $itemclass($workbookid, $item);
    }


    static function can_assess(\context_module $context, $workbookuserid) {
        return is_enrolled($context, $workbookuserid) && has_capability('mod/workbook:assess', $context);
    }


    static function can_submit(\context_module $context, $workbookuserid) {
        global $USER;

        return is_enrolled($context, $workbookuserid) && $USER->id == $workbookuserid && has_capability('mod/workbook:view', $context);
    }

}
