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
 * This page lists all the instances of jazzquiz in a particular course
 *
 * @package   mod_jazzquiz
 * @author    Davo Smith
 * @copyright Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once('lib.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/jazzquiz/index.php', ['id' => $course->id]));
require_course_login($course);
$PAGE->set_pagelayout('incourse');

// Get all required strings.
$strjazzquizzes = get_string('modulenameplural', 'jazzquiz');
$strjazzquiz = get_string('modulename', 'jazzquiz');

$PAGE->navbar->add($strjazzquizzes);
$PAGE->set_title(strip_tags($course->shortname . ': ' . $strjazzquizzes));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Get all the appropriate data.
$jazzquizzes = get_all_instances_in_course('jazzquiz', $course);
if (!$jazzquizzes) {
    notice('There are no jazzquizes', "../../course/view.php?id=$course->id");
    die;
}

// Print the list of instances.
$timenow = time();
$strname = get_string('name');
$strweek = get_string('week');
$strtopic = get_string('topic');

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head = [$strweek, $strname];
    $table->align = ['center', 'left'];
} else if ($course->format == "topics") {
    $table->head = [$strtopic, $strname];
    $table->align = ['center', 'left'];
} else {
    $table->head = [$strname];
    $table->align = ['left', 'left'];
}

foreach ($jazzquizzes as $jazzquiz) {
    $url = new moodle_url('/mod/jazzquiz/view.php', ['cmid' => $jazzquiz->coursemodule]);
    if (!$jazzquiz->visible) {
        // Show dimmed if the mod is hidden.
        $link = '<a class="dimmed" href="' . $url . '">' . $jazzquiz->name . '</a>';
    } else {
        // Show normal if the mod is visible.
        $link = '<a href="' . $url . '">' . $jazzquiz->name . '</a>';
    }
    if ($course->format == 'weeks' || $course->format == 'topics') {
        $table->data[] = [$jazzquiz->section, $link];
    } else {
        $table->data[] = [$link];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
