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
 * Start session form displayed to instructors/users who can control the quiz.
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\forms\view;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Start session form displayed to instructors/users who can control the quiz.
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2019 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_session extends moodleform {
    /**
     * Overriding parent function to account for namespace in the class name
     * so that client validation works.
     *
     * @return string
     */
    protected function get_form_identifier(): string {
        $class = get_class($this);
        return preg_replace('/[^a-z0-9_]/i', '_', $class);
    }

    /**
     * Definition of the session start form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $jazzquiz = $this->_customdata['jazzquiz'];
        $mform->addElement('text', 'session_name', get_string('session_name', 'jazzquiz'));
        $mform->setType('session_name', PARAM_TEXT);
        $mform->addRule('session_name', get_string('session_name_required', 'jazzquiz'), 'required', null, 'client');
        $anonymity = [
            $mform->createElement('radio', 'anonymity', '', get_string('anonymous_answers', 'jazzquiz'), 1, []),
            $mform->createElement('radio', 'anonymity', '', get_string('fully_anonymous', 'jazzquiz'), 2, []),
            $mform->createElement('radio', 'anonymity', '', get_string('nonanonymous_session', 'jazzquiz'), 3, []),
        ];
        $mform->addGroup($anonymity, 'anonymity', '', ['<br>', ''], false);
        $mform->setDefault('anonymity', $jazzquiz->data->cfganonymity);
        $mform->addElement('checkbox', 'allowguests', '', get_string('allow_guests', 'jazzquiz'));
        $mform->setDefault('allowguests', $jazzquiz->data->cfgallowguests);
        $mform->addElement('submit', 'submitbutton', get_string('start_session', 'jazzquiz'));
    }

    /**
     * Peform validation on the form.
     *
     * @param array $data
     * @param array $files
     * @return string[] errors
     */
    public function validations($data, $files): array {
        $errors = [];
        if (empty($data['session_name'])) {
            $errors['session_name'] = get_string('session_name_required', 'jazzquiz');
        }
        return $errors;
    }
}
