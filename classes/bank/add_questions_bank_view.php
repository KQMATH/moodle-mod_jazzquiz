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
 * Question bank view for managing JazzQuiz questions.
 *
 * @package mod_jazzquiz
 * @copyright 2024 NTNU
 * @author Sebastian Gundersen <sebastian@sgundersen.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\bank;

use context;

/**
 * Displays button at the bottom of the question bank view for adding selected questions to a JazzQuiz.
 *
 * @package mod_jazzquiz
 * @copyright 2024 NTNU
 * @author Sebastian Gundersen <sebastian@sgundersen.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_questions_bank_view extends \core_question\local\bank\view {

    /**
     * Display button to add selected questions to the quiz.
     *
     * @param context $catcontext
     * @return void
     */
    protected function display_bottom_controls(context $catcontext): void {
        echo '<div class="pt-2">';
        if (has_capability('moodle/question:useall', $catcontext)) {
            echo '<button class="btn btn-primary jazzquiz-add-selected-questions">';
            echo get_string('addselectedquestionstoquiz', 'quiz');
            echo '</button>';
        }
        echo '</div>';
    }

}
