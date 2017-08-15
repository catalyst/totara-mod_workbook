<?php


namespace mod_workbook\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/message/messagelib.php');

class send_comment_notifications extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sendcommentnotifications', 'mod_workbook');
    }

    /**
     * Send workbook comment notifications.
     */
    public function execute() {
        global $DB;

		$since = $this->get_last_run_time();

        $commentarea = 'workbook_page_item_';

        // Get all recent comments.
        $sql = "SELECT *
            FROM {comments} c
            WHERE commentarea LIKE '{$commentarea}%' AND timecreated >= ?";
        $comments = $DB->get_records_sql($sql, array($since));

        $sentcount = 0;
        foreach ($comments as $comment) {
            $commentuser = \core_user::get_user($comment->userid);
            $pageitemid = substr($comment->commentarea, strlen($commentarea));
            $workbookuserid = $comment->itemid;
            if (empty($pageitemid)) {
                // Something's not right - skip this one.
                continue;
            }
            $workbook = \mod_workbook\helper::get_workbook_for_pageitem($pageitemid);

            if ($workbookuserid == $comment->userid) {
                // Workbook owner comment - send notification to other participants.
                $sql = "SELECT *
                    FROM {user}
                    WHERE id IN (
                        SELECT DISTINCT userid
                        FROM {comments} c
                        WHERE commentarea = ? AND itemid = ? AND userid != ?
                    )";
                $participants = $DB->get_records_sql($sql, array($comment->commentarea, $comment->userid, $comment->userid));
                if (empty($participants)) {
                    // No comment participants yet - send notification to assessors.
                    $assessors = \mod_workbook\helper::get_assessors($workbook->id, $workbook->course, $workbookuserid);
                    foreach ($assessors as $assessor) {
                        $this->send_comment_notification($commentuser, $assessor, $workbook, $workbookuserid);
                        $sentcount++;
                    }
                } else {
                    foreach ($participants as $participant) {
                        $this->send_comment_notification($commentuser, $participant, $workbook, $workbookuserid);
                        $sentcount++;
                    }
                }
            } else {
                // Send notification to workbook user.
                $workbookuser = \core_user::get_user($comment->itemid);
                $this->send_comment_notification($commentuser, $workbookuser, $workbook, $workbookuserid);
                $sentcount++;
            }
        }
    }

    private function send_comment_notification($userfrom, $userto, $workbook, $workbookuserid) {
        $eventdata = new \stdClass();
        $eventdata->userto = $userto;
        $eventdata->userfrom = $userfrom;
        $eventdata->icon = 'elearning-newcomment';
        $eventdata->contexturl = new \moodle_url('/mod/workbook/view.php',
            array('wid' => $workbook->id, 'userid' => $workbookuserid, 'pid' => $workbook->pageid));
        $eventdata->contexturl = $eventdata->contexturl->out();
        $strobj = new \stdClass();
        $strobj->user = fullname($userfrom);
        $strobj->workbook = format_string($workbook->name);
        $eventdata->subject = get_string('msg:commentsubject', 'workbook', $strobj);
        $eventdata->fullmessage = get_string('msg:comment', 'workbook', $strobj);
        // $eventdata->sendemail = TOTARA_MSG_EMAIL_NO;

        \tm_alert_send($eventdata);
    }
}

