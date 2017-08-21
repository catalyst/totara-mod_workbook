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

class housekeeping extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('housekeeping', 'mod_workbook');
    }

    public function execute() {
        global $DB;

        // Clean orphan submission files.
        $sql = "SELECT f.*
            FROM {files} f
            LEFT JOIN {workbook_page_item_submit} pis ON f.itemid = pis.id
            LEFT JOIN {workbook_page_item} pi ON pis.pageitemid = pi.id
            WHERE f.component = 'mod_workbook'
            AND f.filearea = 'submissions'
            AND pi.id IS NULL";
        $orphanfiles = $DB->get_recordset_sql($sql);

        $fs = \get_file_storage();
        $deletedcount = 0;
        foreach ($orphanfiles as $filerecord) {
            $fs->get_file_instance($filerecord)->delete();
            $deletedcount++;
        }
        $orphanfiles->close();

        mtrace("{$deletedcount} orphan files deleted.");
    }
}

