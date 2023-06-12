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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/jazzquiz/backup/moodle2/restore_jazzquiz_stepslib.php');

/**
 * JazzQuiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_jazzquiz_activity_task extends restore_activity_task {

    /**
     * This should define settings. Not used at the moment.
     */
    protected function define_my_settings() {

    }

    /**
     * Define the structure steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_jazzquiz_activity_structure_step('jazzquiz_structure', 'jazzquiz.xml'));
    }

    /**
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('jazzquiz', ['intro'])
        ];
    }

    /**
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('JAZZQUIZVIEWBYID', '/mod/jazzquiz/view.php?id=$1', 'course_module'),
            new restore_decode_rule('JAZZQUIZINDEX', '/mod/jazzquiz/index.php?id=$1', 'course')
        ];
    }

    /**
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }

}
