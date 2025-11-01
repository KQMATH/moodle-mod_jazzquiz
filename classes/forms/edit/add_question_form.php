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
 * Defines form class which instructor uses to add a new question to a quiz.
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\forms\edit;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for confirming question add and get the time for the question to appear on the page.
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_question_form extends \moodleform {
    /**
     * Aaccount for namespace in the class name such that client validation works.
     *
     * @return string
     */
    protected function get_form_identifier(): string {
        $class = get_class($this);
        return preg_replace('/[^a-z0-9_]/i', '_', $class);
    }

    /**
     * Adds form fields to the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $jazzquiz = $this->_customdata['jazzquiz'];
        $defaulttime = $jazzquiz->data->defaultquestiontime;

        $mform->addElement('static', 'questionid', get_string('question', 'jazzquiz'), $this->_customdata['questionname']);

        $mform->addElement('advcheckbox', 'notime', get_string('no_time_limit', 'jazzquiz'));
        $mform->setType('notime', PARAM_INT);
        $mform->addHelpButton('notime', 'no_time_limit', 'jazzquiz');
        $mform->setDefault('notime', 0);

        $mform->addElement('duration', 'questiontime', get_string('question_time', 'jazzquiz'));
        $mform->disabledIf('questiontime', 'notime', 'checked');
        $mform->setType('questiontime', PARAM_INT);
        $mform->setDefault('questiontime', $defaulttime);
        $mform->addHelpButton('questiontime', 'question_time', 'jazzquiz');

        if (!empty($this->_customdata['edit'])) {
            $savestring = get_string('save_question', 'jazzquiz');
        } else {
            $savestring = get_string('add_question', 'jazzquiz');
        }

        $this->add_action_buttons(true, $savestring);
    }

    /**
     * Validate question time.
     *
     * @param array $data
     * @param array $files
     * @return array $errors
     */
    public function validation($data, $files): array {
        $errors = [];
        if (!filter_var($data['questiontime'], FILTER_VALIDATE_INT) && $data['questiontime'] !== 0) {
            $errors['questiontime'] = get_string('invalid_question_time', 'jazzquiz');
        } else if ($data['questiontime'] < 0) {
            $errors['questiontime'] = get_string('invalid_question_time', 'jazzquiz');
        }
        return $errors;
    }
}
