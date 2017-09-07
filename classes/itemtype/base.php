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

abstract class base {
    protected $workbookid;
    protected $item;
    protected $context;

    function __construct($workbookid, $item) {
        $this->item = $item;
        $this->workbookid = $workbookid;
        $cm = get_coursemodule_from_instance('workbook', $workbookid, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
    }

    function display_content() {
        $out = format_text($this->item->content, FORMAT_HTML, array('noclean'=> true, 'overflowdiv'=> true, 'context'=> $this->context));

        return $out;
    }

    function display_response($response) {
        $out = format_text($response, FORMAT_HTML);

        return $out;
    }

    abstract function display_response_input($userid, $submission, $disabled=false);

    function supports_grading() {
        return true;
    }

    static function supports_file_uploads() {
        return false;
    }

    function file_uploads($userid, $submissionid, $static=false) {
        global $OUTPUT;

        $out = '';

        if (empty($this->item->allowfileuploads) || !$this->supports_file_uploads()) {
            return '';
        }

        $out .= \html_writer::tag('strong', get_string('itemfiles', 'workbook'), array('class' => 'workbook-item-files-heading'));
        $out .= \html_writer::tag('div', $this->list_page_item_files($userid, $submissionid),
            array('class' => 'workbook-item-files'));

        if ($static) {
            // Only display static file list.
            return $out;
        }

        $itemfilesurl = new \moodle_url('/mod/workbook/uploadfile.php', array('userid' => $userid, 'submissionid' => $submissionid));
        $out .= $OUTPUT->single_button($itemfilesurl, get_string('updatefiles', 'workbook'));
        $out .= \html_writer::end_tag('div');

        return $out;
    }

    function list_page_item_files($userid, $submissionid) {
        if (!$this->supports_file_uploads()) {
            return '';
        }

        $out = array();

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_workbook', 'submissions', $submissionid, 'itemid, filepath, filename', false);

        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            $filename = $file->get_filename();
            $url = \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
            $out[] = \html_writer::link($url, $filename);
        }
        $br = \html_writer::empty_tag('br');

        return implode($br, $out);
    }
}
