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

defined('MOODLE_INTERNAL') || die;

class backup_workbook_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the workbook instance.
        $workbook = new backup_nested_element('workbook', array('id'), array(
            'name', 'grade'));

        $pages = new backup_nested_element('pages');
        $page = new backup_nested_element('page', array('id'), array(
            'title', 'navtitle', 'parentid', 'sortorder'));

        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', array('id'), array(
            'name', 'itemtype', 'content', 'requiredgrade', 'allowcomments', 'sortorder'));

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', array('id'), array(
            'userid', 'response', 'grade', 'status', 'timemodified', 'modifiedby', 'timegraded', 'gradedby', 'superseded'));

        $comments = new backup_nested_element('comments');
        $comment = new backup_nested_element('comment', array('id'), array(
            'commentarea', 'itemid', 'content', 'format', 'userid', 'timecreated'));

        // Build the tree.
        $workbook->add_child($pages);

        $pages->add_child($page);

        $items->add_child($item);

        $item->add_child($comments);
        $comments->add_child($comment);

        $item->add_child($submissions);
        $submissions->add_child($submission);

        $page->add_child($items);

        // Define data sources.
        $workbook->set_source_table('workbook', array('id' => backup::VAR_ACTIVITYID));

        $page->set_source_sql('
            SELECT *
              FROM {workbook_page}
             WHERE workbookid = ?
          ORDER BY sortorder',
            array(backup::VAR_PARENTID));

        $item->set_source_sql('
            SELECT *
              FROM {workbook_page_item}
             WHERE pageid = ?
          ORDER BY sortorder',
            array(backup::VAR_PARENTID));

        if ($userinfo) {
            // Item submissions.
            $submission->set_source_sql('
                SELECT *
                  FROM {workbook_page_item_submit}
                 WHERE pageitemid = ?',
                array(backup::VAR_PARENTID));
            // Item comments.
            $comment->set_source_sql("
                SELECT *
                  FROM {comments}
                 WHERE commentarea = 'workbook_page_item_'||?",
                array(backup::VAR_PARENTID));
        }

        // If we were referring to other tables, we would annotate the relation
        // with the element's annotate_ids() method.
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'modifiedby');
        $submission->annotate_ids('user', 'gradedby');

        $comment->annotate_ids('user', 'userid');
        $comment->annotate_ids('user', 'itemid');

        $page->annotate_ids('workbook_page', 'parentid');

        // Define file annotations.
        $item->annotate_files('mod_workbook', 'workbook_item_content', 'id');

        // Return the root element (workbook), wrapped into standard activity structure.
        return $this->prepare_activity_structure($workbook);
    }
}
