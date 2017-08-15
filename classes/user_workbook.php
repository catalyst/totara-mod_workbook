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

class user_workbook {
    public $workbook;
    public $pages;
    public $userid;
    public $modcontext;
    public $can_assess;
    public $can_submit;


    function __construct($workbookid, $userid) {
        global $DB, $USER;

        $this->workbook = $DB->get_record('workbook', array('id' => $workbookid), '*', MUST_EXIST);
        $this->pages = \mod_workbook\page::get_pages($workbookid);
        $this->userid = $userid;
        $cm = get_coursemodule_from_instance('workbook', $this->workbook->id, $this->workbook->course, false, MUST_EXIST);
        $this->modcontext = \context_module::instance($cm->id);
        $this->can_assess = \mod_workbook\helper::can_assess($this->modcontext, $userid);
        $this->can_submit = \mod_workbook\helper::can_submit($this->modcontext, $userid);


        // Build the pages, including any current item submissions.
        foreach ($this->pages as $pageid => $page) {
            $this->pages[$pageid]->items = \mod_workbook\page::get_items($pageid);

            // Also get the recent item submissions for this page.
            $this->pages[$pageid]->items = array_map(
                function($item) {
                    $item->submission = null;  // Add empty submission items to make referencing easier later.
                    return $item;
                },
                $this->pages[$pageid]->items);
            $itemsubmissions = $this->get_item_submissions($pageid);
            foreach ($itemsubmissions as $submit) {
                $this->pages[$pageid]->items[$submit->pageitemid]->submission = $submit;
            }
        }
    }


    function get_item_submissions($pageid) {
        global $DB;

        $sql = "SELECT pis.*
            FROM {workbook_page_item_submit} pis
            JOIN {workbook_page_item} pi on pis.pageitemid = pi.id
            WHERE pi.pageid = ? AND pis.superseded = 0 AND pis.userid = ?";

        return $DB->get_records_sql($sql, array($pageid, $this->userid));
    }


    function get_item($itemid) {
        $item = null;

        // Search for the item.
        foreach ($this->pages as $page) {
            foreach ($page->items as $item) {
                if ($item->id == $itemid) {
                    return $item;
                }
            }
        }

        return $item;
    }


    function can_submit_item($item) {
        if (!$this->can_submit) {
            return false;
        }

        if (empty($this->pages[$item->pageid]->items[$item->id]->submission)) {
            // No current submission, so allow.
            return true;
        }
        $currentsubmission = $this->pages[$item->pageid]->items[$item->id]->submission;

        return in_array($currentsubmission->status, array(WORKBOOK_STATUS_DRAFT, WORKBOOK_STATUS_GRADED));
    }


    function can_assess_submission($submission) {
        if (!$this->can_assess || empty($submission)) {
            return false;
        }

        return in_array($submission->status, array(WORKBOOK_STATUS_SUBMITTED, WORKBOOK_STATUS_GRADED));
    }


    function get_submission($itemid, $pageid=null) {
        // Find item.
        $submission = null;
        if (!empty($pageid) && !empty($this->pages[$pageid]->items[$itemid])) {
            return $this->pages[$pageid]->items[$itemid]->submission;
        }

        // Search for the item.
        if ($item = $this->get_item($itemid)) {
            return $item->submission;
        }

        return $submission;
    }

    function save_submission_response($itemid, $response) {
        global $DB, $USER;

        $timenow = time();
        if (($submission = $this->get_submission($itemid)) && $submission->status == WORKBOOK_STATUS_DRAFT) {
            // Update the existing submission response.
            $submission->response = $response;  // todo: itemclass->format_response?
            $submission->timemodified = $timenow;
            $submission->modifiedby = $USER->id;

            $DB->update_record('workbook_page_item_submit', $submission);
        } else {
            // Create a new submission for this item.
            $submission = $this->create_new_submission($itemid, $response);
        }

        $this->update_pagesobj_submission($itemid, $submission);

        return $submission;
    }


    function submit_item($itemid, $response) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $submission = $this->create_new_submission($itemid, $response);
        $submission->status = WORKBOOK_STATUS_SUBMITTED;
        $DB->update_record('workbook_page_item_submit', $submission);

        $this->update_pagesobj_submission($itemid, $submission);

        $transaction->allow_commit();

        return $submission;
    }


    function create_new_submission($itemid, $response) {
        global $DB, $USER;

        $timenow = time();
        $currentsubmission = $this->get_submission($itemid);

        $transaction = $DB->start_delegated_transaction();

        // Supersede the current submission.
        if (!empty($currentsubmission)) {
            $currentsubmission->status = WORKBOOK_STATUS_SUPERSEDED;
            $currentsubmission->superseded = 1;
            $DB->update_record('workbook_page_item_submit', $currentsubmission);
        }

        // Create a new submission for this item.
        $submission = new \stdClass();
        $submission->userid = $this->userid;
        $submission->pageitemid = $itemid;
        $submission->response = $response;  // todo: itemclass->format_response?
        $submission->timemodified = $timenow;
        $submission->modifiedby = $USER->id;
        if (!empty($currentsubmission->grade)) {
            // Copy the grade details from the currentsubmission to this new one.
            $submission->grade = $currentsubmission->grade;
            $submission->gradedby = $currentsubmission->gradedby;
            $submission->timegraded = $currentsubmission->timegraded;
        }
        $submission->id = $DB->insert_record('workbook_page_item_submit', $submission);
        $submission = $DB->get_record('workbook_page_item_submit', array('id' => $submission->id));

        $transaction->allow_commit();

        return $submission;
    }


    function grade_item_submission($itemid, $newgrade) {
        global $DB, $USER;

        $transaction = $DB->start_delegated_transaction();

        $item = $this->get_item($itemid);
        $submission = $item->submission;

        $submission->grade = $newgrade;
        $submission->status = $newgrade >= $item->requiredgrade ? WORKBOOK_STATUS_PASSED : WORKBOOK_STATUS_GRADED;
        $submission->timegraded = time();
        $submission->gradedby = $USER->id;

        $DB->update_record('workbook_page_item_submit', $submission);

        if ($submission->status == WORKBOOK_STATUS_PASSED) {
            $this->update_activity_completion();
        }

        $transaction->allow_commit();

        $this->update_pagesobj_submission($itemid, $submission);


        return $submission;
    }

    function get_next_page($pageid) {
        foreach ($this->pages as $page) {
            if ($page->id == $pageid) {
                $nextpage = current($this->pages);
                return $nextpage->sortorder > $page->sortorder ? $nextpage : false;
            }
        }

        return false;
    }

    function get_previous_page($pageid) {
        $previouspage = false;
        foreach ($this->pages as $page) {
            if ($page->id == $pageid) {
                return $previouspage;
            }
            $previouspage = $page;
        }

        return false;
    }


    function is_complete() {
        foreach ($this->pages as $page) {
            foreach ($page->items as $item) {
                $itemtype = \mod_workbook\helper::get_itemtype_instance($this->workbook->id, $item);
                if (!$itemtype->supports_grading()) {
                    continue;
                }
                if (empty($item->submission) || $item->submission->status != WORKBOOK_STATUS_PASSED) {
                    return false;
                }
            }
        }

        return true;
    }

    function update_activity_completion() {
        global $DB;

        if (!$this->workbook->completionitems) {
            return;
        }

        $course = $DB->get_record('course', array('id' => $this->workbook->course), '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('workbook', $this->workbook->id, $course->id, false, MUST_EXIST);
        $ccompletion = new \completion_info($course);
        if ($ccompletion->is_enabled($cm)) {
            if ($this->is_complete()) {
                $ccompletion->update_state($cm, COMPLETION_COMPLETE, $this->userid);
            } else {
                $ccompletion->update_state($cm, COMPLETION_INCOMPLETE, $this->userid);
            }
        }
    }

    function page_requires_attention($pageid) {
        foreach ($this->pages[$pageid]->items as $item) {
            $itemtype = \mod_workbook\helper::get_itemtype_instance($this->workbook->id, $item);
            if (!$itemtype->supports_grading()) {
                continue;
            }
            if ($this->can_assess && !empty($item->submission)) {
                if ($item->submission->status == WORKBOOK_STATUS_SUBMITTED) {
                    return true;
                }
            }
            if ($this->can_submit) {
                if (empty($item->submission)) {
                    return true;
                }

                if (in_array($item->submission->status, array(WORKBOOK_STATUS_GRADED, WORKBOOK_STATUS_DRAFT))) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * Update a submission in $this->pages
     */
    private function update_pagesobj_submission($itemid, $submission) {
        foreach ($this->pages as $page) {
            foreach ($page->items as $item) {
                if ($itemid == $item->id) {
                    $this->pages[$page->id]->items[$item->id]->submission = $submission;
                    break;
                }
            }
        }
    }


}
