<?php


namespace mod_workbook\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/workbook/lib.php');
require_once($CFG->dirroot.'/totara/message/messagelib.php');

class send_graded_notifications extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sendgradednotifications', 'mod_workbook');
    }

    /**
     * Send graded notifications to assessors.
     */
    public function execute() {
        global $DB;

		$since = $this->get_last_run_time();

        // Send graded notifications to students.
        $sql = "SELECT pis.id AS submissionid, u.*,
                w.id AS workbookid,
                w.name AS workbookname,
                w.course AS courseid,
                p.id AS pageid,
                c.shortname AS courseshortname,
                pis.gradedby,
                pi.itemtype
            FROM {workbook} w
            JOIN {workbook_page} p ON w.id = p.workbookid
            JOIN {workbook_page_item} pi ON p.id = pi.pageid
            JOIN {workbook_page_item_submit} pis ON pi.id = pis.pageitemid
            JOIN {user} u ON pis.userid = u.id
            JOIN {course} c ON w.course = c.id
            WHERE pis.status IN (?, ?) AND pis.timegraded >= ? AND pis.superseded = 0";
        $gradings = $DB->get_records_sql($sql, array(WORKBOOK_STATUS_GRADED, WORKBOOK_STATUS_PASSED, $since));

        $sentcount = 0;
        foreach ($gradings as $grading) {
            $eventdata = new \stdClass();
            $eventdata->userto = $grading;
            $eventdata->userfrom = \totara_core\totara_user::get_user($grading->gradedby);
            $eventdata->icon = 'elearning-update';
            $eventdata->contexturl = new \moodle_url('/mod/workbook/view.php',
                array(
                    'userid' => $grading->id,
                    'wid' => $grading->workbookid,
                    'pid' => $grading->pageid
                )
            );
            $eventdata->contexturl = $eventdata->contexturl->out();
            $strobj = new \stdClass();
            $strobj->workbook = format_string($grading->workbookname);
            $strobj->courseshortname = format_string($grading->courseshortname);
            $strobj->itemtype = get_string('type'.$grading->itemtype, 'workbook');
            $eventdata->subject = get_string('msg:itemgradedsubject', 'workbook', $strobj);
            $eventdata->fullmessage = get_string('msg:itemgraded', 'workbook', $strobj);
            // $eventdata->sendemail = TOTARA_MSG_EMAIL_NO;

            \tm_alert_send($eventdata);
            $sentcount++;
        }
    }
}

