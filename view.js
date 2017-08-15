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


M.mod_workbook_view = M.mod_workbook_view || {

    Y: null,

    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // if defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.mod_workbook_view.init()-> jQuery dependency required for this module.');
        }

        this.initNavigation();

        this.initResponses();

        this.initSubmits();

        this.initGrades();
    },

    loadingImg: function (size) {
        var size = size || 'small';
        var attrs = ' alt="Loading..." class="mod-workbook-loadingimg" ';
        if (size == 'small') {
            src = M.util.image_url('i/loading_small', 'moodle');
        } else if (size == 'large') {
            src = M.util.image_url('i/loading', 'moodle');
        }
        return $('<img src="'+src+'" '+attrs+'/>');
    },

    loadPageContent: function(pageid) {
        var objscope = this;
        var content = $('#mod-workbook-content');

        $('.workbook-nav-pages div').removeClass('workbook-nav-currentpage');
        $('.workbook-nav-pages div[pageid='+pageid+']').addClass('workbook-nav-currentpage');

        $.ajax({
            url: M.cfg.wwwroot+'/mod/workbook/ajax.php',
            type: 'GET',
            data: {
                'action': 'getpage',
                'wid': this.config.workbookid,
                'userid': this.config.userid,
                'pid': pageid
            },
            beforeSend: function() {
                content.html('').append(objscope.loadingImg('large'));
            },
            success: function(data) {
                var data = $.parseJSON(data);
                if (data.status == 'success') {
                    content.html(data.content);
                    //window.scrollTo(0, 0);
                    $('html, body').animate({ scrollTop: 0 }, 'medium');
                } else {
                    content.html('Could not retrieve page...');
                    alert(data.msg);
                }

            },
            error: function (data) {
                console.log(data);
                alert('Error saving completion...');
            }
        });
    },

    initNavigation: function() {
        var objscope = this;

        // Init navigation block.
        $('.workbook-nav-pages div').on('click', function() {
            if ($(this).hasClass('workbook-nav-currentpage')) {
                return;
            }

            var pageid = $(this).attr('pageid');

            objscope.loadPageContent(pageid);
        });

        // Init page navigation.
        $('#mod-workbook-content').on('click', '.mod-workbook-page-navigation img', function() {
            var pageid = $(this).attr('pageid');
            objscope.loadPageContent(pageid);

        });
    },

    initResponses: function() {
        var objscope = this;

        $('#mod-workbook-content').on('change', '.mod-workbook-item-response textarea', function() {
            var textarea = $(this);
            var workbookitem = textarea.closest('.mod-workbook-item');
            var itemid = workbookitem.attr('itemid');

            $.ajax({
                url: M.cfg.wwwroot+'/mod/workbook/ajax.php',
                type: 'POST',
                data: {
                    'action': 'itemdraft',
                    'wid': objscope.config.workbookid,
                    'userid': objscope.config.userid,
                    'iid': itemid,
                    'response': textarea.val()
                },
                beforeSend: function() {
                    workbookitem.children('.mod-workbook-submission-timemodified').append(objscope.loadingImg());
                },
                success: function(data) {
                    workbookitem.find('.mod-workbook-loadingimg').remove();
                    var data = $.parseJSON(data);
                    if (data.status == 'success') {
                        workbookitem.find('.mod-workbook-submission-status').html(data.submissionstatus);
                        workbookitem.find('.mod-workbook-submission-timemodified').html(data.timemodified);
                        workbookitem.find('.mod-workbook-item-sitrep').fadeIn();
                    } else {
                        alert(data.msg);
                    }

                },
                error: function (data) {
                    console.log(data);
                    alert('Error saving completion...');
                }
            });
        });
    },

    initSubmits: function() {
        var objscope = this;

        $('#mod-workbook-content').on('click', '.workbook-btnsubmit', function() {
            var submitbtn = $(this);
            var workbookitem = $(this).closest('.mod-workbook-item');
            var pageid = $('.mod-workbook-user-page').attr('pageid');
            var itemid = workbookitem.attr('itemid');
            var responsecontainer = submitbtn.closest('.mod-workbook-item-response');

            if (!confirm(M.util.get_string('confirmsubmit', 'workbook'))) {
                return;
            }

            $.ajax({
                url: M.cfg.wwwroot+'/mod/workbook/ajax.php',
                type: 'POST',
                data: {
                    'action': 'itemsubmit',
                    'wid': objscope.config.workbookid,
                    'userid': objscope.config.userid,
                    'iid': itemid,
                    'response': responsecontainer.children('textarea').val()
                },
                beforeSend: function() {
                    submitbtn.attr('disabled', 'disabled');
                    responsecontainer.children('textarea').attr('disabled', 'disabled');
                    workbookitem.children('.mod-workbook-submission-timemodified').append(objscope.loadingImg());
                },
                success: function(data) {
                    var data = $.parseJSON(data);
                    if (data.status == 'success') {
                        workbookitem.find('.mod-workbook-submission-status').html(data.submissionstatus);
                        workbookitem.find('.mod-workbook-submission-timemodified').html(data.timemodified);
                        if (!data.pageattrequired) {
                            $('.workbook-nav-pages div[pageid='+pageid+'] img.req').remove();
                        }
                    } else {
                        alert(data.msg);
                    }

                },
                error: function (data) {
                    console.log(data);
                    alert('Error saving completion...');
                }
            });
        });
    },

    initGrades: function() {
        var objscope = this;

        $('#mod-workbook-content').on('change', '.mod-workbook-submission-grade input', function() {
            var gradeinput = $(this);
            var gradecontainer = gradeinput.closest('.mod-workbook-submission-grade');
            var workbookitem = gradecontainer.closest('.mod-workbook-item');
            var pageid = $('.mod-workbook-user-page').attr('pageid');
            var itemid = workbookitem.attr('itemid');

            $.ajax({
                url: M.cfg.wwwroot+'/mod/workbook/ajax.php',
                type: 'POST',
                data: {
                    'action': 'grade',
                    'wid': objscope.config.workbookid,
                    'userid': objscope.config.userid,
                    'iid': itemid,
                    'grade': gradecontainer.children('input').val()
                },
                beforeSend: function() {
                    gradecontainer.append(objscope.loadingImg());
                },
                success: function(data) {
                    gradecontainer.find('.mod-workbook-loadingimg').remove();
                    var data = $.parseJSON(data);
                    if (data.status == 'success') {
                        workbookitem.find('.mod-workbook-submission-status').html(data.submissionstatus);
                        workbookitem.find('.mod-workbook-submission-grading').html(data.gradinghtml);
                        if (!data.pageattrequired) {
                            $('.workbook-nav-pages div[pageid='+pageid+'] img.req').remove();
                        }
                    } else {
                        alert(data.msg);
                    }

                },
                error: function (data) {
                    console.log(data);
                    alert('Error saving completion...');
                }
            });
        });
    },
}

