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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/workbook/lib.php');

class mod_workbook_renderer extends plugin_renderer_base {

    function config_pages($workbook) {
        $out = '';
        $out .= html_writer::start_tag('div', array('id' => 'config-mod-workbook-pages'));

        $pages = \mod_workbook\page::get_pages($workbook->id, 0);
        if (empty($pages)) {
            return html_writer::tag('p', get_string('nopages', 'workbook'));
        }
        foreach ($pages as $page) {
            $out .= $this->config_page($workbook, $page);
        }

        $out .= html_writer::end_tag('div');

        return $out;
    }


    function config_page($workbook, $page) {
        global $DB;

        $out = '';

        // Page items.
        $out .= html_writer::start_tag('div', array('class' => 'config-mod-workbook-page', 'pageid' => $page->id));
        $out .= html_writer::start_tag('div', array('class' => 'config-mod-workbook-page-heading'));
        $out .= format_string($page->title);
        $additemurl = new moodle_url('/mod/workbook/pageitem.php', array('wid' => $workbook->id, 'pid' => $page->id));
        $out .= $this->output->action_icon($additemurl, new pix_icon('t/add', get_string('additem', 'workbook')));
        $editurl = new moodle_url('/mod/workbook/page.php', array('wid' => $workbook->id, 'id' => $page->id));
        $out .= $this->output->action_icon($editurl, new pix_icon('t/edit', get_string('editpage', 'workbook')));
        $deleteurl = new moodle_url('/mod/workbook/page.php', array('wid' => $workbook->id, 'id' => $page->id, 'action' => 'delete'));
        $out .= $this->output->action_icon($deleteurl, new pix_icon('t/delete', get_string('deletepage', 'workbook')));
        if (\mod_workbook\page::get_previous_sortorder($page)) {
            $moveurl = new moodle_url('/mod/workbook/page.php', array('wid' => $workbook->id, 'id' => $page->id, 'action' => 'moveup'));
            $out .= $this->output->action_icon($moveurl, new pix_icon('t/up', get_string('moveup', 'workbook')));
        }
        if (\mod_workbook\page::get_next_sortorder($page)) {
            $moveurl = new moodle_url('/mod/workbook/page.php', array('wid' => $workbook->id, 'id' => $page->id, 'action' => 'movedown'));
            $out .= $this->output->action_icon($moveurl, new pix_icon('t/down', get_string('movedown', 'workbook')));
        }

        $out .= html_writer::end_tag('div');

        $out .= $this->config_page_items($workbook, $page->id);

        // Sub pages.
        $childpages = \mod_workbook\page::get_pages($workbook->id, $page->id);
        foreach ($childpages as $page) {
            $out .= $this->config_page($workbook, $page);
        }
        $out .= html_writer::end_tag('div');

        return $out;
    }


    function config_page_items($workbook, $pageid) {
        global $DB;

        $out = '';

        $cm = get_coursemodule_from_instance('workbook', $workbook->id, $workbook->course, false, MUST_EXIST);

        $items = $DB->get_records('workbook_page_item', array('pageid' => $pageid), 'sortorder');

        $out .= html_writer::start_tag('div', array('class' => 'config-mod-workbook-page-items'));
        $maxsortorder = $DB->get_field('workbook_page_item', 'MAX(sortorder)', array('pageid' => $pageid));
        foreach ($items as $item) {
            $out .= html_writer::start_tag('div', array('class' => 'config-mod-workbook-page-item'));
            $item->content = file_rewrite_pluginfile_urls($item->content, 'pluginfile.php', context_module::instance($cm->id)->id, 'mod_workbook', 'workbook_item_content', $item->id);
            $out .= format_text($item->content, FORMAT_HTML);
            $editurl = new moodle_url('/mod/workbook/pageitem.php',
                array('wid' => $workbook->id, 'pid' => $pageid, 'id' => $item->id));
            $out .= $this->output->action_icon($editurl, new pix_icon('t/edit', get_string('edititem', 'workbook')));
            $deleteurl = new moodle_url('/mod/workbook/pageitem.php',
                array('wid' => $workbook->id, 'pid' => $pageid, 'id' => $item->id, 'action' => 'delete'));
            $out .= $this->output->action_icon($deleteurl, new pix_icon('t/delete', get_string('deleteitem', 'workbook')));
            if ($item->sortorder > 1) {
                $moveurl = new moodle_url('/mod/workbook/pageitem.php',
                    array('wid' => $workbook->id, 'pid' => $pageid, 'id' => $item->id, 'action' => 'moveup'));
                $out .= $this->output->action_icon($moveurl, new pix_icon('t/up', get_string('moveup', 'workbook')));
            }
            if ($item->sortorder < $maxsortorder) {
                $moveurl = new moodle_url('/mod/workbook/pageitem.php',
                    array('wid' => $workbook->id, 'pid' => $pageid, 'id' => $item->id, 'action' => 'movedown'));
                $out .= $this->output->action_icon($moveurl, new pix_icon('t/down', get_string('movedown', 'workbook')));
            }

            $out .= html_writer::end_tag('div');
        }
        $out .= html_writer::end_tag('div');

        return $out;
    }


    function navigation($userworkbook, $currentpageid) {
        $out = html_writer::start_tag('div', array('class' => 'workbook-nav-pages'));
        foreach ($userworkbook->pages as $page) {
            $title = empty($page->navtitle) ? $page->title : $page->navtitle;
            $classes = $page->parentid ? 'workbook-nav-subpage' : '';
            $classes .= $page->id == $currentpageid ? ' workbook-nav-currentpage ' : '';
            $out .= html_writer::start_tag('div', array('class' => $classes, 'pageid' => $page->id));
            $out .= format_string($title);
            if ($userworkbook->page_requires_attention($page->id)) {
                $out .= $this->pix_icon('req', get_string('attentionrequired', 'workbook'), '', array('class' => 'req'));
            }
            $out .= html_writer::end_tag('div');
        }

        return $out;
    }


    // Build a user's workbook.
    function user_workbook_page($userworkbook, $pageid) {
        global $USER;

        $page = $userworkbook->pages[$pageid];

        $out = html_writer::start_tag('div', array('class' => 'mod-workbook-user-page', 'pageid' => $pageid));
        $out .= $this->navigation_prev_next($userworkbook->get_previous_page($pageid), $userworkbook->get_next_page($pageid));
        $out .= html_writer::tag('h3', format_string($page->title));


        foreach ($page->items as $item) {
            $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-item', 'itemid' => $item->id));

            // Item content.
            $itemclass = \mod_workbook\helper::get_itemtype_instance($userworkbook->workbook->id, $item);
            $out .= html_writer::tag('div', $itemclass->display_content(), array('class' => 'mod-workbook-item-content'));

            // Item response.
            if ($itemclass->supports_grading()) {

                // Response input.
                $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-item-response'));
                $out .= html_writer::tag('h5', $USER->id == $userworkbook->userid ?
                        get_string('yourresponse', 'workbook') :
                        get_string('response:', 'workbook'));
                $response = empty($item->submission->response) ? '' : $item->submission->response;
                $out .= $itemclass->display_response_input($response, !$userworkbook->can_submit_item($item));
                $out .= html_writer::end_tag('div');

                // Response time modified.
                $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-submission-timemodified'));
                if (!empty($item->submission)) {
                    $out .= get_string('modifiedonx', 'workbook', userdate($item->submission->timemodified, get_string('strftimedatetimeshort')));
                }
                $out .= html_writer::end_tag('div');

                $sitrepstyle = empty($item->submission) ? 'display:none' : '';
                $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-item-sitrep', 'style' => $sitrepstyle));

                // Submission status.
                $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-submission-status'));
                if (!empty($item->submission)) {
                    $out .= $this->submission_status($item->submission->status);
                }
                $out .= html_writer::end_tag('div');

                // Grading.
                $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-submission-grading'));
                if (!empty($item->submission)) {
                    // Grade.
                    $out .= $this->submission_grading($item->submission, $item->requiredgrade, !$userworkbook->can_assess_submission($item->submission));
                }
                $out .= html_writer::end_tag('div');

                $out .= html_writer::end_tag('div');  // mod-workbook-item-sitrep
            }

            if (!empty($item->allowcomments)) {
                $out .= $this->item_comments($item, $userworkbook->userid, $userworkbook->modcontext);
            }

            $out .= html_writer::end_tag('div');  // mod-workbook-item
        }

        $out .= $this->navigation_prev_next($userworkbook->get_previous_page($pageid), $userworkbook->get_next_page($pageid));

        $out .= html_writer::end_tag('div');  // mod-workbook-user-page

        return $out;
    }

    function navigation_prev_next($prevpage=false, $nextpage=false) {
        $out = html_writer::start_tag('div', array('class' => 'mod-workbook-page-navigation'));

        if (!empty($prevpage)) {
            $out .= html_writer::empty_tag('img',
                array(
                    'src' => $this->pix_url('t/left'),
                    'class' => 'iconsmall mod-workbook-nav-prev',
                    'alt' => 'previouspage',
                    'pageid' => $prevpage->id,
                    'title' => format_string($prevpage->navtitle)
                )
            );
        }
        if (!empty($nextpage)) {
            $out .= html_writer::empty_tag('img',
                array(
                    'src' => $this->pix_url('t/right'),
                    'class' => 'iconsmall mod-workbook-nav-next',
                    'alt' => 'nextpage',
                    'pageid' => $nextpage->id,
                    'title' => format_string($nextpage->navtitle)
                )
            );
        }

        $out .= html_writer::end_tag('div');


        return $out;
    }

    function submission_status($status) {
        $out = html_writer::tag('span', get_string('status:', 'workbook'), array('class' => 'mod-workbook-label'));
        $out .= get_string("status{$status}", 'workbook');

        return $out;
    }


    function submission_grading($submission, $requiredgrade, $static=true) {
        // Grade.
        $out = html_writer::start_tag('div', array('class' => 'mod-workbook-submission-grade'));
        $gradestr = html_writer::tag('span', get_string('grade:', 'workbook'), array('class' => 'mod-workbook-label'));
        if ($static) {
            $out .= empty($submission->grade) ? get_string('notgraded', 'workbook') : $gradestr.$submission->grade;
        } else {
            $currentgrade = empty($submission->grade) ? '' : $submission->grade;
            $out .= $gradestr;
            $out .= html_writer::empty_tag('input', array(
                'type' => 'text',
                'size' => 3,
                'value' => $currentgrade,
                'submissionid' => $submission->id,
                'name' => 'workbook-submission-grade-input'));
        }
        $out .= html_writer::tag('span', ' ('.get_string('xrequired', 'workbook', $requiredgrade).')',
            array('class' => 'mod-workbook-submission-requiredgrade'));
        $out .= html_writer::end_tag('div');
        // Grader details.
        $gradedby = empty($submission->gradedby) ? '' : get_string('byx', 'workbook', fullname(\core_user::get_user($submission->gradedby)));
        $timegraded = empty($submission->timegraded) ? '' : ' - '.userdate($submission->timegraded, get_string('strftimedatetimeshort'));
        $out .= html_writer::start_tag('div', array('class' => 'mod-workbook-submission-grade-details'));
        $out .= html_writer::tag('span', $gradedby, array('class' => 'mod-workbook-submission-gradedby'));
        $out .= html_writer::tag('span', $timegraded, array('class' => 'mod-workbook-submission-gradedon'));
        $out .= html_writer::end_tag('div');

        return $out;
    }

    function item_comments($item, $userid, context_module $context) {
        global $CFG, $PAGE;

        if (empty($PAGE->url)) {
            $PAGE->set_url('/fauxurl');  // We're using ajax.
        }
        $out = $this->output->heading(get_string('comments', 'workbook'), 6);
        require_once($CFG->dirroot.'/comment/lib.php');
        comment::init();
        $options = new stdClass();
        $options->area    = 'workbook_page_item_'.$item->id;
        $options->context = $context;
        $options->itemid  = $userid;
        $options->showcount = true;
        $options->component = 'workbook';
        $options->autostart = true;
        $options->notoggle = true;
        $comment = new comment($options);
        $out .= $comment->output(true);

        return $out;
    }

}
