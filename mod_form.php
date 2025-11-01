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
 * The main configuration form.
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @author    Davo Smith
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module settings form.
 */
class mod_jazzquiz_mod_form extends moodleform_mod {
    /**
     * Create form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform =& $this->_form;

        // Adding the "general" field set, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);

        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description'));

        $mform->addElement('duration', 'defaultquestiontime', get_string('default_question_time', 'jazzquiz'));
        $mform->setDefault('defaultquestiontime', 180);
        $mform->setType('defaultquestiontime', PARAM_INT);
        $mform->addHelpButton('defaultquestiontime', 'default_question_time', 'jazzquiz');

        $mform->addElement('duration', 'waitforquestiontime', get_string('wait_for_question_time', 'jazzquiz'));
        $mform->setDefault('waitforquestiontime', 0);
        $mform->setType('waitforquestiontime', PARAM_INT);
        $mform->addHelpButton('waitforquestiontime', 'wait_for_question_time', 'jazzquiz');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }
}
