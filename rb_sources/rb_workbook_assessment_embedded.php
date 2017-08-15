<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package mod
 * @subpackage workbook
 */

global $CFG;
require_once($CFG->dirroot.'/totara/reportbuilder/classes/rb_base_content.php');
require_once($CFG->dirroot.'/mod/workbook/lib.php');

class rb_workbook_assessment_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $workbookid = array_key_exists('workbookid', $data) ? $data['workbookid'] : null;

        $url = new moodle_url('/mod/workbook/report.php', $data);
        $this->url = $url->out_as_local_url();
        $this->source = 'workbook_submission';
        $this->defaultsortcolumn = 'user_namelink';
        $this->shortname = 'workbook_assessment';
        $this->fullname = get_string('workbookassessment', 'rb_source_workbook_submission');
        $this->columns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('name', 'rb_source_user'),
            ),
            array(
                'type' => 'workbook',
                'value' => 'name',
                'heading' => get_string('workbookname', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'workbook_page_item',
                'value' => 'nameorcontent',
                'heading' => get_string('item', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
                'heading' => get_string('grade', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'base',
                'value' => 'status',
                'heading' => get_string('status', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'workbook',
                'value' => 'assesslink',
                'heading' => ' ',
            ),
        );

        // Filters.
        $this->filters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'base',
                'value' => 'status',
                'advanced' => 0,
            ),
        );

        // Params.
        $this->embeddedparams = array('superseded' => 0);
        if (!empty($workbookid)) {
            $this->embeddedparams['workbookid'] = $workbookid;
        }

        parent::__construct($data);
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        return true;
    }
}
