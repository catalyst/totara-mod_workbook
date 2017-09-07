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

namespace mod_workbook\itemtype;

defined('MOODLE_INTERNAL') || die();

class statichtml extends \mod_workbook\itemtype\base {
    function display_content() {
        $content = file_rewrite_pluginfile_urls($this->item->content, 'pluginfile.php', $this->context->id, 'mod_workbook', 'workbook_item_content', $this->item->id);

        $out = format_text($content, FORMAT_HTML, array('noclean'=> true, 'overflowdiv'=> true, 'context'=> $this->context));

        return $out;
    }

    function display_response($response) {
        // No responses for static content ;)
        return '';
    }

    function display_response_input($userid, $submission, $disabled=false) {
        // No responses for static content ;)
        return '';
    }

    function supports_grading() {
        return false;
    }
}
