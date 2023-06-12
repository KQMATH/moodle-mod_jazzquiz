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
 * Defines table column class for adding a question from the question bank to a JazzQuiz.
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom action column for adding a question to the JazzQuiz from the question bank view
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_add_to_jazzquiz_action_column extends \core_question\local\bank\action_column_base {

    /**
     * Get the name of the column.
     * @return string
     */
    public function get_name() {
        return 'addtojazzquizaction';
    }

    /**
     * Show the button to add the question.
     * @param object $question
     * @param string $rowclasses
     */
    protected function display_content($question, $rowclasses) {
        if (!question_has_capability_on($question, 'use')) {
            return;
        }
        $text = get_string('add_to_quiz', 'jazzquiz');
        $this->print_icon('t/add', $text, $this->qbank->get_add_to_jazzquiz_url($question->id));
    }

    /**
     * Get the required fields.
     * @return string[]
     */
    public function get_required_fields(): array {
        return ['q.id'];
    }

}
