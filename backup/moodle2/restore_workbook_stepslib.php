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
 * Structure step to restore one workbook activity
 */
class restore_workbook_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        $paths = array();
        $paths[] = new restore_path_element('workbook', '/activity/workbook');
        $paths[] = new restore_path_element('workbook_page', '/activity/workbook/pages/page');
        $paths[] = new restore_path_element('workbook_page_item', '/activity/workbook/pages/page/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('workbook_page_item_submit', '/activity/workbook/pages/page/items/item/submissions/submission');
            $paths[] = new restore_path_element('workbook_page_item_comment', '/activity/workbook/pages/page/items/item/comments/comment');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data for the workbook activity
     *
     * @param array $data parsed element data
     */
    protected function process_workbook($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();

        // Create the workbook instance.
        $newitemid = $DB->insert_record('workbook', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the given restore path element data for workbook pages.
     *
     * @param array $data parsed element data
     */
    protected function process_workbook_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->workbookid = $this->get_new_parentid('workbook');
        // If page has parent, map it (it has been already restored)
        if (!empty($data->parentid)) {
            $data->parentid = $this->get_mappingid('workbook_page', $data->parentid);
        }

        // Add workbook page.
        $newitemid = $DB->insert_record('workbook_page', $data);
        $this->set_mapping('workbook_page', $oldid, $newitemid);
    }

    /**
     * Process the given restore path element data for workbook page items
     *
     * @param array $data parsed element data
     */
    protected function process_workbook_page_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->pageid = $this->get_new_parentid('workbook_page');

        // Add workbook page item.
        $newitemid = $DB->insert_record('workbook_page_item', $data);
        $this->set_mapping('workbook_page_item', $oldid, $newitemid, true);  // Set true for files.
    }

    /**
     * Process the given restore path element data for workbook item submissions.
     *
     * @param array $data parsed element data
     */
    protected function process_workbook_page_item_submit($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->pageitemid = $this->get_new_parentid('workbook_page_item');
        $data->modifiedby = $this->get_mappingid('user', $data->modifiedby);
        $data->gradedby = $this->get_mappingid('user', $data->gradedby);

        // Add workbook item submission.
        $newitemid = $DB->insert_record('workbook_page_item_submit', $data);
    }

    /**
     * Process the given restore path element data for workbook item comments.
     *
     * @param array $data parsed element data
     */
    protected function process_workbook_page_item_comment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->contextid = $this->task->get_contextid();
        $data->commentarea = 'workbook_page_item_'.$this->get_new_parentid('workbook_page_item');
        $data->itemid = $this->get_mappingid('user', $data->itemid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Add workbook item comment.
        $newitemid = $DB->insert_record('comments', $data);
    }




    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add workbook related files.
        $this->add_related_files('mod_workbook', 'workbook_item_content', 'workbook_page_item');
    }
}
