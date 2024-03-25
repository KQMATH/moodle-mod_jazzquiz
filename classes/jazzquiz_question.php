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

namespace mod_jazzquiz;

use stdClass;

/**
 * A question slot in a JazzQuiz.
 *
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_question {

    /** @var stdClass */
    public stdClass $data;

    /** @var ?stdClass Question definition data */
    public ?stdClass $question;

    /**
     * Constructor.
     *
     * @param stdClass $data jazzquiz_question
     */
    public function __construct(stdClass $data) {
        global $DB;
        $this->data = $data;
        $this->question = $DB->get_record('question', ['id' => $data->questionid]) ?: null;
    }

    /**
     * Check if the question exists.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->question !== null;
    }

}
