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

defined('MOODLE_INTERNAL') || die();

class rb_source_workbook_submission extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    public function __construct($groupid, rb_global_restriction_set $globalrestrictionset = null) {
        if ($groupid instanceof rb_global_restriction_set) {
            throw new coding_exception('Wrong parameter orders detected during report source instantiation.');
        }
        // Remember the active global restriction set.
        $this->globalrestrictionset = $globalrestrictionset;

        // Apply global user restrictions.
        $this->add_global_report_restriction_join('base', 'userid');

        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/workbook/lib.php');

        $this->base = '{workbook_page_item_submit}';

        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('workbooksubmission', 'rb_source_workbook_submission');
        $this->sourcewhere = $this->define_sourcewhere();

        parent::__construct();
    }

    /**
     * Global report restrictions are implemented in this source.
     * @return boolean
     */
    public function global_restrictions_supported() {
        return true;
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;

        // to get access to constants
        require_once($CFG->dirroot.'/mod/workbook/lib.php');
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        $joinlist = array(
            new rb_join(
                'workbook_page_item',
                'INNER',
                '{workbook_page_item}',
                'base.pageitemid = workbook_page_item.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'workbook_page',
                'INNER',
                '{workbook_page}',
                'workbook_page_item.pageid = workbook_page.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'workbook_page_item'
            ),
            new rb_join(
                'workbook',
                'INNER',
                '{workbook}',
                'workbook_page.workbookid = workbook.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'workbook_page'
            ),
            new rb_join(
                'modifyuser',
                'LEFT',
                '{user}',
                'base.modifiedby = modifyuser.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'gradeuser',
                'LEFT',
                '{user}',
                'base.gradedby = gradeuser.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'workbook', 'course');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_job_assignment_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_core_tag_tables_to_joinlist('core', 'course', $joinlist, 'course', 'id');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'course', 'id');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $usednamefieldsgrade = totara_get_all_user_name_fields(false, 'gradeuser', null, null, true);
        $allnamefieldsgrade = totara_get_all_user_name_fields(false, 'gradeuser');

        $usednamefieldsmodify = totara_get_all_user_name_fields(false, 'modifyuser', null, null, true);
        $allnamefieldsmodify = totara_get_all_user_name_fields(false, 'modifyuser');

        $columnoptions = array(
            new rb_column_option(
                'workbook',
                'name',
                get_string('workbook', 'rb_source_workbook_submission'),
                'workbook.name',
                array(
                    'joins' => 'workbook',
                    'displayfunc' => 'workbook_link',
                    'extrafields' => array('userid' => 'base.userid', 'workbookid' => 'workbook.id')
                )
            ),
            new rb_column_option(
                'workbook_page',
                'title',
                get_string('pagetitle', 'rb_source_workbook_submission'),
                'workbook_page.title',
                array(
                    'joins' => 'workbook_page',
                    'displayfunc' => 'workbook_page_link',
                    'extrafields' => array(
                        'userid' => 'base.userid',
                        'workbookid' => 'workbook.id',
                        'pageid' => 'workbook_page.id'
                    )
                )
            ),
            new rb_column_option(
                'workbook_page',
                'navtitle',
                get_string('pagenavtitle', 'rb_source_workbook_submission'),
                'workbook_page.navtitle',
                array('joins' => 'workbook_page')
            ),
            new rb_column_option(
                'workbook_page_item',
                'itemtype',
                get_string('itemtype', 'rb_source_workbook_submission'),
                'workbook_page_item.itemtype',
                array('joins' => 'workbook_page_item', 'displayfunc' => 'workbook_itemtype')
            ),
            new rb_column_option(
                'workbook_page_item',
                'name',
                get_string('itemname', 'rb_source_workbook_submission'),
                'workbook_page_item.name',
                array('joins' => 'workbook_page_item')
            ),
            new rb_column_option(
                'workbook_page_item',
                'nameorcontent',
                get_string('itemnamecontent', 'rb_source_workbook_submission'),
                'workbook_page_item.name',
                array(
                    'joins' => 'workbook_page_item',
                    'displayfunc' => 'workbook_item_nameorcontent',
                    'extrafields' => array(
                        'content' => 'workbook_page_item.content',
                        'type' => 'workbook_page_item.itemtype'
                    )
                )
            ),
            new rb_column_option(
                'workbook_page_item',
                'content',
                get_string('content', 'rb_source_workbook_submission'),
                'workbook_page_item.content',
                array(
                    'joins' => 'workbook_page_item',
                    'displayfunc' => 'workbook_item_content',
                    'extrafields' => array('type' => 'workbook_page_item.itemtype')
                )
            ),
            new rb_column_option(
                'workbook_page_item',
                'requiredgrade',
                get_string('requiredgrade', 'rb_source_workbook_submission'),
                'workbook_page_item.requiredgrade',
                array('joins' => 'workbook_page_item')
            ),
            new rb_column_option(
                'base',
                'response',
                get_string('response', 'rb_source_workbook_submission'),
                'base.response',
                array(
                    'displayfunc' => 'workbook_item_response',
                    'extrafields' => array('type' => 'workbook_page_item.itemtype')
                )
            ),
            new rb_column_option(
                'base',
                'grade',
                get_string('grade', 'rb_source_workbook_submission'),
                'base.grade'
            ),
            new rb_column_option(
                'base',
                'status',
                get_string('status', 'rb_source_workbook_submission'),
                'base.status',
                array('displayfunc' => 'workbook_submission_status')
            ),
            new rb_column_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_workbook_submission'),
                'base.timemodified',
                array('displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'base',
                'modifiedby',
                get_string('modifiedby', 'rb_source_workbook_submission'),
                $DB->sql_concat_join("' '", $usednamefieldsmodify),
                array(
                      'joins' => 'modifyuser',
                      'displayfunc' => 'link_user',
                      'extrafields' => array_merge(array('id' => 'modifyuser.id'), $allnamefieldsmodify)
                )
            ),
            new rb_column_option(
                'base',
                'timegraded',
                get_string('timegraded', 'rb_source_workbook_submission'),
                'base.timegraded',
                array('displayfunc' => 'nice_datetime')
            ),
            new rb_column_option(
                'base',
                'gradedby',
                get_string('gradedby', 'rb_source_workbook_submission'),
                $DB->sql_concat_join("' '", $usednamefieldsgrade),
                array(
                      'joins' => 'gradeuser',
                      'displayfunc' => 'link_user',
                      'extrafields' => array_merge(array('id' => 'gradeuser.id'), $allnamefieldsgrade)
                )
            ),
            new rb_column_option(
                'base',
                'superseded',
                get_string('superseded', 'rb_source_workbook_submission'),
                'base.superseded',
                array('displayfunc' => 'workbook_submission_superseded')
            ),
            new rb_column_option(
                'workbook',
                'assesslink',
                get_string('assesslink', 'rb_source_workbook_submission'),
                'workbook.name',
                array(
                    'joins' => 'workbook',
                    'displayfunc' => 'workbook_assess_link',
                    'extrafields' => array(
                        'userid' => 'base.userid',
                        'workbookid' => 'workbook.id',
                        'status' => 'base.status',
                        'pageid' => 'workbook_page.id'
                    )
                )
            ),
            new rb_column_option(
                'base',
                'submissionfiles',
                get_string('files', 'rb_source_workbook_submission'),
                'base.id',
                array(
                    'joins' => 'workbook_page_item',
                    'displayfunc' => 'workbook_submission_files',
                    'extrafields' => array(
                        'userid' => 'base.userid',
                        'workbookid' => 'workbook.id',
                        'pageid' => 'workbook_page_item.pageid',
                        'itemtype' => 'workbook_page_item.itemtype',
                        'name' => 'workbook_page_item.name',
                        'content' => 'workbook_page_item.content',
                        'requiredgrade' => 'workbook_page_item.requiredgrade',
                        'allowcomments' => 'workbook_page_item.allowcomments',
                        'allowfileuploads' => 'workbook_page_item.allowfileuploads',
                    )
                )
            ),
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_job_assignment_fields_to_columns($columnoptions);
        $this->add_core_tag_fields_to_columns('core', 'course', $columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(

            new rb_filter_option(
                'workbook',
                'name',
                get_string('workbookname', 'rb_source_workbook_submission'),
                'text'
            ),
            new rb_filter_option(
                'workbook_page',
                'title',
                get_string('pagetitle', 'rb_source_workbook_submission'),
                'text'
            ),
            new rb_filter_option(
                'workbook_page',
                'navtitle',
                get_string('pagenavtitle', 'rb_source_workbook_submission'),
                'text'
            ),
            new rb_filter_option(
                'base',
                'grade',
                get_string('grade', 'rb_source_workbook_submission'),
                'number'
            ),
            new rb_filter_option(
                'base',
                'status',
                get_string('status', 'rb_source_workbook_submission'),
                'select',
                array(
                    'selectfunc' => 'workbook_submission_status_list',
                )
            ),
            new rb_filter_option(
                'base',
                'timemodified',
                get_string('timemodified', 'rb_source_workbook_submission'),
                'date'
            ),
            new rb_filter_option(
                'base',
                'timegraded',
                get_string('timegraded', 'rb_source_workbook_submission'),
                'date'
            ),
            new rb_filter_option(
                'workbook_page_item',
                'itemtype',
                get_string('itemtype', 'rb_source_workbook_submission'),
                'select',
                array(
                    'selectfunc' => 'workbook_type_list',
                )
            ),
            new rb_filter_option(
                'workbook_page_item',
                'name',
                get_string('itemname', 'rb_source_workbook_submission'),
                'text'
            ),
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_job_assignment_fields_to_filters($filteroptions);
        $this->add_core_tag_fields_to_filters('core', 'course', $filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array();
        $this->add_basic_user_content_options($contentoptions);

        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'workbookid',
                'workbook.id'
            ),
            new rb_param_option(
                'pageitemid',
                'base.pageitemid'
            ),
            new rb_param_option(
                'superseded',
                'base.superseded'
            ),
            new rb_param_option(
                'userid',
                'base.userid'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'workbook',
                'value' => 'name',
            ),
            array(
                'type' => 'workbook_page',
                'value' => 'navtitle',
            ),
            array(
                'type' => 'workbook_page_item',
                'value' => 'content',
            ),
            array(
                'type' => 'base',
                'value' => 'status',
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
            ),
            array(
                'type' => 'base',
                'value' => 'timegraded',
            ),
            array(
                'type' => 'base',
                'value' => 'gradedby',
            ),

        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'workbook',
                'value' => 'name',
            ),
            array(
                'type' => 'workbook_page',
                'value' => 'navtitle',
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
            ),
            array(
                'type' => 'base',
                'value' => 'status',
            ),
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
        );

        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array(
            new rb_column(
                'workbook',         // type
                'id',               // value
                '',                 // heading
                'workbook.id',      // field
                array('joins' => 'workbook', 'noexport' => true)     // options
            )
        );

        return $requiredcolumns;
    }

    protected function define_sourcewhere() {
        // Only consider enrolled users.
        $sourcewhere = 'base.userid IN (
            SELECT DISTINCT userid
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = workbook.course
        )';

        return $sourcewhere;
    }


    //
    //
    // Source specific column display methods
    //
    //

    function rb_display_workbook_item_nameorcontent($itemname, $row, $isexport) {
        if (!empty($itemname)) {
            return $itemname;
        }

        return $this->rb_display_workbook_item_content($row->content, $row, $isexport);
    }



    function rb_display_workbook_item_content($content, $row, $isexport) {
        if ($isexport) {
            return $content;
        }

        return $content; // todo: use itemtype class here
    }

    function rb_display_workbook_item_response($response, $row, $isexport) {
        if ($isexport) {
            return $response;
        }

        return $response; // todo: user itemtype class here
    }

    function rb_display_workbook_submission_status($status, $row, $isexport) {
            return get_string('status'.$status, 'workbook');
    }

    function rb_display_workbook_itemtype($type, $row, $isexport) {
        return get_string('type'.$type, 'workbook');
    }

    function rb_display_workbook_link($workbookname, $row, $isexport) {
        return html_writer::link(new moodle_url('/mod/workbook/view.php',
            array('userid' => $row->userid, 'wid' => $row->workbookid)), $workbookname);
    }

    function rb_display_workbook_page_link($title, $row, $isexport) {
        return html_writer::link(new moodle_url('/mod/workbook/view.php',
            array('userid' => $row->userid, 'wid' => $row->workbookid, 'pageid' => $row->pageid)), $title);
    }

    function rb_display_workbook_assess_link($workbookname, $row, $isexport) {
        if (!in_array($row->status, array(WORKBOOK_STATUS_SUBMITTED, WORKBOOK_STATUS_GRADED))) {
            return '';
        }

        return html_writer::link(new moodle_url('/mod/workbook/view.php',
            array('userid' => $row->userid, 'wid' => $row->workbookid, 'pid' => $row->pageid)), get_string('assess', 'rb_source_workbook_submission'));
    }

    function rb_display_workbook_submission_superseded($superseded, $row, $isexport) {
        return !empty($superseded) ? get_string('yes') : get_string('no');
    }

    function rb_display_workbook_submission_files($submissionid, $row, $isexport) {
        if (empty($row->allowfileuploads)) {
            return '';
        }
        $itemtypeclass = '\mod_workbook\itemtype\\'.$row->itemtype;
        if (!$itemtypeclass::supports_file_uploads()) {
            return '';
        }

        $item = new stdClass();
        $item->pageid = $row->pageid;
        $item->itemtype = $row->itemtype;
        $item->name = $row->name;
        $item->content = $row->content;
        $item->requiredgrade = $row->requiredgrade;
        $item->allowcomments = $row->allowcomments;
        $item->allowfileuploads = $row->allowfileuploads;
        $itemtype = \mod_workbook\helper::get_itemtype_instance($row->workbookid, $item);

        return $itemtype->list_page_item_files($row->userid, $submissionid);
    }




    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_workbook_submission_status_list() {
        $statuses = array(WORKBOOK_STATUS_DRAFT, WORKBOOK_STATUS_SUBMITTED, WORKBOOK_STATUS_GRADED,
            WORKBOOK_STATUS_PASSED, WORKBOOK_STATUS_SUPERSEDED);
        $statuslist = array();
        foreach ($statuses as $status) {
            $statuslist[$status] = get_string('status'.$status, 'workbook');
        }

        return $statuslist;
    }

    function rb_filter_workbook_type_list() {
        $types = array('statichtml', 'essay');
        $typelist = array();
        foreach ($types as $type) {
            $typelist[$type] = get_string('type'.$type, 'workbook');
        }

        return $typelist;
    }

    /**
     * Inject column_test data into database.
     * @param totara_reportbuilder_column_testcase $testcase
     */
    public function phpunit_column_test_add_data(totara_reportbuilder_column_testcase $testcase) {
       global $DB;

       if (!PHPUNIT_TEST) {
           throw new coding_exception('phpunit_prepare_test_data() cannot be used outside of unit tests');
       }
       $data = array(
            'workbook' => array(
                array('id' => 1, 'course' => 1, 'name' => 'test workbook', 'intro' => '', 'timecreated' => 1)
            ),
            'workbook_page' => array(
                array('id' => 1, 'workbookid' => 1, 'name' => 'test workbook page')
            ),
            'workbook_page_item' => array(
                array('id' => 1, 'workbookid' => 1, 'pageid' => 1, 'name' => 'test workbook page item', 'content' => 'some test content'),
            ),
            'workbook_page_item_submit' => array(
                array('id' => 1, 'userid' => 2, 'pageitemid' => 1, 'status' => 1, 'timemodified' => 1, 'modifiedby' => 1),
            ),
            'user_enrolments' => array(
                array('id' => 1, 'status' => 0, 'enrolid' => 1, 'userid' => 2)
            ),
        );

        foreach ($data as $table => $data) {
            foreach($data as $datarow) {
                $DB->import_record($table, $datarow);
            }
            $DB->get_manager()->reset_sequence(new xmldb_table($table));
        }
    }
}

