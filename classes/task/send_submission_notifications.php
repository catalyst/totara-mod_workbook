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

namespace mod_workbook\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/workbook/lib.php');
require_once($CFG->dirroot.'/totara/message/messagelib.php');

class send_submission_notifications extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sendsubmissionnotifications', 'mod_workbook');
    }

    /**
     * Send submission notifications to assessors.
     */
    public function execute() {
        global $DB;

		$since = $this->get_last_run_time();
        $sql = "SELECT pis.id AS submissionid, u.*,
                w.id AS workbookid,
                w.name AS workbookname,
                w.course AS courseid,
                p.id AS pageid,
                c.shortname AS courseshortname,
                pi.itemtype
            FROM {workbook} w
            JOIN {workbook_page} p ON w.id = p.workbookid
            JOIN {workbook_page_item} pi ON p.id = pi.pageid
            JOIN {workbook_page_item_submit} pis ON pi.id = pis.pageitemid
            JOIN {user} u ON pis.userid = u.id
            JOIN {course} c ON w.course = c.id
            WHERE pis.status = ? AND pis.timemodified >= ? AND pis.superseded = 0";
        $submissions = $DB->get_records_sql($sql, array(WORKBOOK_STATUS_SUBMITTED, $since));

        $sentcount = 0;
        foreach ($submissions as $usersubmission) {
            $assessors = \mod_workbook\helper::get_assessors($usersubmission->workbookid, $usersubmission->courseid, $usersubmission->id);
            foreach ($assessors as $assessor) {
                $eventdata = new \stdClass();
                $eventdata->userto = $assessor;
                $eventdata->userfrom = $usersubmission;
                $eventdata->icon = 'elearning-complete';
                $eventdata->contexturl = new \moodle_url('/mod/workbook/view.php',
                    array(
                        'userid' => $usersubmission->id,
                        'wid' => $usersubmission->workbookid,
                        'pid' => $usersubmission->pageid
                    )
                );
                $eventdata->contexturl = $eventdata->contexturl->out();
                $strobj = new \stdClass();
                $strobj->user = fullname($usersubmission);
                $strobj->workbook = format_string($usersubmission->workbookname);
                $strobj->courseshortname = format_string($usersubmission->courseshortname);
                $strobj->itemtype = get_string('type'.$usersubmission->itemtype, 'workbook');
                $eventdata->subject = get_string('msg:itemsubmissionsubject', 'workbook', $strobj);
                $eventdata->fullmessage = get_string('msg:itemsubmission', 'workbook', $strobj);
                // $eventdata->sendemail = TOTARA_MSG_EMAIL_NO;

                \tm_task_send($eventdata);
                $sentcount++;
            }
        }
    }
}

