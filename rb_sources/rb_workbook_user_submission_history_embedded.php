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

class rb_workbook_user_submission_history_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $pageitemid = empty($data['pageitemid']) ? null : $data['pageitemid'];
        $userid = empty($data['userid']) ? null : $data['userid'];

        $url = new moodle_url('/mod/workbook/submissionhistory.php', $data);
        $this->url = $url->out_as_local_url();
        $this->source = 'workbook_submission';
        $this->defaultsortcolumn = 'timemodified';
        $this->defaultsortorder = 3;  // descending
        $this->shortname = 'workbook_user_submission_history';
        $this->fullname = get_string('workbooksubmissionhistory', 'rb_source_workbook_submission');
        $this->columns = array(
            array(
                'type' => 'base',
                'value' => 'response',
                'heading' => get_string('response', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'base',
                'value' => 'submissionfiles',
                'heading' => get_string('files', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
                'heading' => get_string('grade', 'rb_source_workbook_submission'),
            ),
            array(
                'type' => 'base',
                'value' => 'timemodified',
                'heading' => get_string('timemodified', 'rb_source_workbook_submission'),
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
        $this->embeddedparams = array('superseded' => 1);
        if (!empty($pageitemid)) {
            $this->embeddedparams['pageitemid'] = $pageitemid;
        }
        if (!empty($userid)) {
            $this->embeddedparams['userid'] = $userid;
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
