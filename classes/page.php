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

class page {
    static function delete($page) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // Delete subpages.
        $subpages = $DB->get_records('workbook_page', array('parentid' => $page->id));
        foreach ($subpages as $spage) {
            self::delete($spage);
        }

        // Delete page items.
        $items = $DB->get_records('workbook_page_item', array('pageid' => $page->id));
        if (!empty($items)) {
            $workbook = $DB->get_record('workbook', array('id' => $page->workbookid), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('workbook', $workbook->id, $workbook->course, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);

            foreach ($items as $item) {
                self::delete_item($item->id, $context);
            }
        }

        // Delete page.
        $DB->delete_records('workbook_page', array('id' => $page->id));

        $transaction->allow_commit();
    }


    static function delete_item($itemid, $context) {
        global $DB;

        // Delete comments.
        $DB->delete_records('comments', array('commentarea' => 'workbook_page_item_'.$itemid));

        // Delete submissions.
        $DB->delete_records('workbook_page_item_submit', array('pageitemid' => $itemid));

        // Delete page item.
        $DB->delete_records('workbook_page_item', array('id' => $itemid));

        // Delete any files.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_workbook', 'workbook_item_content', $itemid);
    }


    static function get_pages($workbookid, $parentid=null) {
        global $DB;

        $params = array('workbookid' => $workbookid);
        if ($parentid !== null) {
            $params['parentid'] = $parentid;
        }

        return $DB->get_records('workbook_page', $params, 'sortorder, id');
    }

    static function get_items($pageid) {
        global $DB;

        return $DB->get_records('workbook_page_item', array('pageid' => $pageid), 'sortorder');
    }


    static function reindex_pages($workbookid, $parentid=0, $sortorderstart=1) {
        global $DB;

        $sortorder = $sortorderstart;
        $pages = self::get_pages($workbookid, $parentid);

        foreach ($pages as $page) {
            $page->sortorder = $sortorder;
            $DB->update_record('workbook_page', $page);
            $sortorder++;

            // Re-index any child pages.
            $sortorder = self::reindex_pages($workbookid, $page->id, $sortorder);
        }

        return $sortorder;
    }


    static function get_new_sortorder($page) {
        global $DB;

        if (!empty($page->parentid)) {
            return $DB->get_field_select('workbook_page',
                'MAX(sortorder)+1', 'workbookid = ? AND (id = ? OR parentid = ?)',
                array($page->workbookid, $page->parentid, $page->parentid));
        }

        $sortorder = $DB->get_field('workbook_page', 'MAX(sortorder)+1', array('workbookid' => $page->workbookid));

        return empty($sortorder) ? 1 : $sortorder;
    }


    static function get_next_sortorder($page) {
        global $DB;

        $sql = "SELECT sortorder
            FROM {workbook_page}
            WHERE workbookid = ? AND parentid = ? AND sortorder > ?
            ORDER BY sortorder
            LIMIT 1";

        return $DB->get_field_sql($sql, array($page->workbookid, $page->parentid, $page->sortorder));
    }

    static function get_previous_sortorder($page) {
        global $DB;

        $sql = "SELECT sortorder
            FROM {workbook_page}
            WHERE workbookid = ? AND parentid = ? AND sortorder < ?
            ORDER BY sortorder DESC
            LIMIT 1";

        return $DB->get_field_sql($sql, array($page->workbookid, $page->parentid, $page->sortorder));
    }


    static function increase_sortorder_below($page) {
        global $DB;

        $sql = "UPDATE {workbook_page}
            SET sortorder = sortorder+1
            WHERE workbookid = ? AND id != ? AND sortorder >= ?";
        $params = array($page->workbookid, $page->id, $page->sortorder);

        return $DB->execute($sql, $params);
    }


    static function has_children($pageid) {
        global $DB;

        return $DB->record_exists('workbook_page', array('parentid' => $pageid));
    }


    static function update_sortorder($page, $newsortorder) {
        global $DB;

        if ($page->sortorder == $newsortorder) {
            return;
        }

        // Get page at new sortorder.
        $currentpage = $DB->get_record('workbook_page', array('workbookid' => $page->workbookid, 'sortorder' => $newsortorder));
        if (empty($currentpage)) {
            // No current page at the new sortorder, so nothing to move around - just update the sortorder.
            $page->sortorder = $newsortorder;
            return $DB->update_record('workbook_page', $page);
        }

        $transaction = $DB->start_delegated_transaction();

        // Update the current page's sortorder.
        if ($page->sortorder < $currentpage->sortorder) {
            // Move the current page up one.
            $currentpage->sortorder = $currentpage->sortorder - 1;
        } else {
            // Move the current page down one.
            $currentpage->sortorder = $currentpage->sortorder + 1;
        }
        $DB->update_record('workbook_page', $currentpage);

        // Finally, update the page to the new sortorder.
        $page->sortorder = $newsortorder;
        $DB->update_record('workbook_page', $page);

        self::reindex_pages($page->workbookid);

        $transaction->allow_commit();
    }


    static function item_update_sortorder($item, $newsortorder) {
        global $DB;

        if ($item->sortorder == $newsortorder) {
            return;
        }

        // Get item at new sortorder.
        $currentitem = $DB->get_record('workbook_page_item', array('pageid' => $item->pageid, 'sortorder' => $newsortorder));
        if (empty($currentitem)) {
            // No current item at the new sortorder, so nothing to move around - just update the sortorder.
            $item->sortorder = $newsortorder;
            return $DB->update_record('workbook_page_item', $item);
        }

        $transaction = $DB->start_delegated_transaction();

        // Update the current item's sortorder.
        if ($item->sortorder < $currentitem->sortorder) {
            // Move the current item up one.
            $currentitem->sortorder = $currentitem->sortorder - 1;
        } else {
            // Move the current item down one.
            $currentitem->sortorder = $currentitem->sortorder + 1;
        }
        $DB->update_record('workbook_page_item', $currentitem);

        // Finally, update the page to the new sortorder.
        $item->sortorder = $newsortorder;
        $DB->update_record('workbook_page_item', $item);

        $transaction->allow_commit();
    }


    static function item_get_new_sortorder($item) {
        global $DB;

        $sortorder = $DB->get_field('workbook_page_item', 'MAX(sortorder)+1', array('pageid' => $item->pageid));

        return empty($sortorder) ? 1 : $sortorder;
    }


}
