<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local lib.
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_jazzquiz\jazzquiz;

/**
 * View tab.
 *
 * @param jazzquiz $jazzquiz
 * @param int $id
 * @param array $row
 * @param string $capability
 * @param string $name
 */
function jazzquiz_view_tab(jazzquiz $jazzquiz, int $id, array &$row, string $capability, string $name): void {
    if (has_capability($capability, $jazzquiz->context)) {
        $url = new moodle_url("/mod/jazzquiz/$name.php", ['id' => $id]);
        $row[] = new tabobject($name, $url, get_string($name, 'jazzquiz'));
    }
}

/**
 * Prints tabs for instructor.
 *
 * @param jazzquiz $jazzquiz
 * @param string $tab
 * @return string HTML string of the tabs
 */
function jazzquiz_view_tabs(jazzquiz $jazzquiz, string $tab): string {
    $tabs = [];
    $row = [];
    $inactive = [];
    $activated = [];
    $id = $jazzquiz->cm->id;
    jazzquiz_view_tab($jazzquiz, $id, $row, 'mod/jazzquiz:attempt', 'view');
    jazzquiz_view_tab($jazzquiz, $id, $row, 'mod/jazzquiz:editquestions', 'edit');
    jazzquiz_view_tab($jazzquiz, $id, $row, 'mod/jazzquiz:seeresponses', 'reports');
    if ($tab === 'view' && count($row) === 1) {
        // No tabs for students.
        return '<br>';
    }
    $tabs[] = $row;
    if ($tab === 'reports' || $tab === 'edit' || $tab === 'view') {
        $activated[] = $tab;
    }
    return print_tabs($tabs, $tab, $inactive, $activated, true);
}
