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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

/**
 * Workbook page form
 */
class workbook_page_form extends moodleform {
    function definition() {
        global $DB;
        $mform =& $this->_form;
        $courseid = $this->_customdata['courseid'];
        $workbookid = $this->_customdata['workbookid'];

        $mform->addElement('text', 'title', get_string('title', 'workbook'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('text', 'navtitle', get_string('navtitle', 'workbook'));
        $mform->setType('navtitle', PARAM_TEXT);
        $mform->addHelpButton('navtitle', 'navtitle', 'workbook');

        $parentoptions = $DB->get_records_select_menu('workbook_page', 'workbookid = ? AND parentid = 0', array($workbookid), 'title', 'id, title');
        $parentoptions = array('0' => get_string('none')) + $parentoptions;
        $mform->addElement('select', 'parentid', get_string('parentpage', 'workbook'), $parentoptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'wid');
        $mform->setType('wid', PARAM_INT);
        $mform->setDefault('wid', $workbookid);

        $this->add_action_buttons(false);
    }
}


/**
 * Workbook page item form.
 */
class workbook_page_item_form extends moodleform {
    function definition() {
        global $CFG, $TEXTAREA_OPTIONS;
        $mform =& $this->_form;
        $workbookid = $this->_customdata['workbookid'];
        $pageid = $this->_customdata['pageid'];


        $itemtypes = array(
            'statichtml' => get_string('typestatichtml', 'workbook'),
            'essay' => get_string('typeessay', 'workbook')
        );
        $mform->addElement('select', 'itemtype', get_string('itemtype', 'workbook'), $itemtypes);
        $mform->setType('itemtype', PARAM_ALPHANUM);

        $mform->addElement('text', 'name', get_string('itemname', 'workbook'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'itemname', 'workbook');

        // TODO: use the itemtype classes here eventually
        $mform->addElement('editor', 'content_editor', get_string('content', 'workbook'), null, $TEXTAREA_OPTIONS);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', null, 'required', null, 'client');

        $mform->addElement('text', 'requiredgrade', get_string('requiredgrade', 'workbook'), array('size' => 4));
        $mform->setType('requiredgrade', PARAM_FLOAT);
        $mform->disabledIf('requiredgrade', 'itemtype', 'eq', 'statichtml');

        if ($CFG->usecomments) {
            $mform->addElement('advcheckbox', 'allowcomments', get_string('allowcomments', 'workbook'));
        } else {
            $mform->addElement('hidden', 'allowcomments', false);
        }
        $mform->setType('allowcomments', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'allowfileuploads', get_string('allowfileuploads', 'workbook'));
        $mform->setType('allowfileuploads', PARAM_BOOL);


        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'wid');
        $mform->setType('wid', PARAM_INT);
        $mform->setDefault('wid', $workbookid);
        $mform->addElement('hidden', 'pid');
        $mform->setType('pid', PARAM_INT);
        $mform->setDefault('pid', $pageid);

        $this->add_action_buttons(false);
    }
}

