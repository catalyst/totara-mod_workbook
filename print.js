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


M.mod_workbook_print = M.mod_workbook_print || {

    Y: null,

    // Optional php params and defaults defined here, args passed to init method
    // below will override these values.
    config: {},

    /**
     * Module initialisation method called by php js_init_call().
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args) {
        // Save a reference to the Y instance (all of its dependencies included).
        this.Y = Y;

        // If defined, parse args into this module's config object.
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // Check jQuery dependency is available.
        if (typeof $ === 'undefined') {
            throw new Error('M.mod_workbook_print.init()-> jQuery dependency required for this module.');
        }

        // Ensure textareas show all their content.
        $('textarea').each(function() {
            $(this).height($(this).prop('scrollHeight'));
        });


        window.print();
        window.close()
    },
}

