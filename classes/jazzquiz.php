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

use context_module;
use core\context\module;
use moodle_url;
use question_display_options;
use renderer_base;
use stdClass;

/**
 * The JazzQuiz activity.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz {

    /** @var array $review fields Static review fields to add as options */
    public static array $reviewfields = [
        'attempt' => ['theattempt', 'jazzquiz'],
        'correctness' => ['whethercorrect', 'question'],
        'marks' => ['marks', 'jazzquiz'],
        'specificfeedback' => ['specificfeedback', 'question'],
        'generalfeedback' => ['generalfeedback', 'question'],
        'rightanswer' => ['rightanswer', 'question'],
        'manualcomment' => ['manualcomment', 'jazzquiz'],
    ];

    /** @var stdClass $cm course module */
    public stdClass $cm;

    /** @var stdClass $course */
    public stdClass $course;

    /** @var module $context */
    public module $context;

    /** @var renderer_base $renderer */
    public renderer_base $renderer;

    /** @var stdClass $data The jazzquiz database table row */
    public stdClass $data;

    /** @var jazzquiz_question[] $questions */
    public array $questions;

    /**
     * Constructor.
     *
     * @param int $cmid The course module ID
     */
    public function __construct(int $cmid) {
        global $PAGE, $DB;
        $this->cm = get_coursemodule_from_id('jazzquiz', $cmid, 0, false, MUST_EXIST);

        // TODO: Login requirement must be moved over to caller.
        require_login($this->cm->course, false, $this->cm);

        $this->context = context_module::instance($cmid);
        $PAGE->set_context($this->context);
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz');

        $this->course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
        $this->data = $DB->get_record('jazzquiz', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $this->refresh_questions();
    }

    /**
     * Sets up the display options for the question.
     *
     * @param bool $review
     * @param string|stdClass $reviewoptions
     * @return question_display_options
     */
    public function get_display_options(bool $review, string|stdClass $reviewoptions): question_display_options {
        $options = new question_display_options();
        $options->flags = question_display_options::HIDDEN;
        $options->context = $this->context;
        $options->marks = question_display_options::HIDDEN;
        if ($review) {
            // Default display options for review.
            $options->readonly = true;
            $options->hide_all_feedback();
            // Special case for "edit" review options value.
            if ($reviewoptions === 'edit') {
                $options->correctness = question_display_options::VISIBLE;
                $options->marks = question_display_options::MARK_AND_MAX;
                $options->feedback = question_display_options::VISIBLE;
                $options->numpartscorrect = question_display_options::VISIBLE;
                $options->manualcomment = question_display_options::EDITABLE;
                $options->generalfeedback = question_display_options::VISIBLE;
                $options->rightanswer = question_display_options::VISIBLE;
                $options->history = question_display_options::VISIBLE;
            } else if ($reviewoptions instanceof stdClass) {
                foreach (self::$reviewfields as $field => $unused) {
                    if ($reviewoptions->$field == 1) {
                        if ($field == 'specificfeedback') {
                            $field = 'feedback';
                        }
                        if ($field == 'marks') {
                            $options->$field = question_display_options::MARK_AND_MAX;
                        } else {
                            $options->$field = question_display_options::VISIBLE;
                        }
                    }
                }
            }
        } else {
            // Default options for running quiz.
            $options->rightanswer = question_display_options::HIDDEN;
            $options->numpartscorrect = question_display_options::HIDDEN;
            $options->manualcomment = question_display_options::HIDDEN;
            $options->manualcommentlink = question_display_options::HIDDEN;
        }
        return $options;
    }

    /**
     * Get the open session. Returns null if no session is open.
     *
     * @return ?jazzquiz_session
     */
    public function load_open_session(): ?jazzquiz_session {
        global $DB;
        $sessions = $DB->get_records('jazzquiz_sessions', [
            'jazzquizid' => $this->data->id,
            'sessionopen' => 1,
        ], 'id');
        if (empty($sessions)) {
            return null;
        }
        $session = reset($sessions);
        return new jazzquiz_session($this, $session->id);
    }

    /**
     * Check if a session is open for this quiz.
     *
     * @return bool true if open
     */
    public function is_session_open(): bool {
        global $DB;
        return $DB->record_exists('jazzquiz_sessions', [
            'jazzquizid' => $this->data->id,
            'sessionopen' => 1,
        ]);
    }

    /**
     * Create a new session for this quiz.
     *
     * @param string $name
     * @param int $anonymity
     * @param bool $allowguests
     */
    public function create_session(string $name, int $anonymity, bool $allowguests): void {
        global $DB;
        $this->data->cfganonymity = $anonymity;
        $this->data->cfgallowguests = $allowguests ? 1 : 0;
        $DB->update_record('jazzquiz', $this->data);

        $session = new stdClass();
        $session->name = $name;
        $session->jazzquizid = $this->data->id;
        $session->sessionopen = 1;
        $session->status = 'notrunning';
        $session->slot = 0;
        $session->anonymity = $anonymity;
        $session->showfeedback = false;
        $session->allowguests = $allowguests ? 1 : 0;
        $session->created = time();
        $DB->insert_record('jazzquiz_sessions', $session);
    }

    /**
     * Handles adding a question action from the question bank.
     *
     * Displays a form initially to ask how long they'd like the question to be set up for, and then after
     * valid input saves the question to the quiz at the last position
     *
     * @param int $questionid The question bank's question id
     */
    public function add_question(int $questionid): void {
        global $DB;
        $question = new stdClass();
        $question->jazzquizid = $this->data->id;
        $question->questionid = $questionid;
        $question->questiontime = $this->data->defaultquestiontime;
        $question->slot = count($this->questions) + 1;
        $DB->insert_record('jazzquiz_questions', $question);
        $this->refresh_questions();
    }

    /**
     * Apply a sorted array of jazzquiz_question IDs to the quiz.
     *
     * Questions that are missing from the array will also be removed from the quiz.
     * Duplicate values will silently be removed.
     *
     * @param int[] $order
     */
    public function set_question_order(array $order): void {
        global $DB;
        $order = array_unique($order);
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $slot = array_search($question->id, $order);
            if ($slot === false) {
                $DB->delete_records('jazzquiz_questions', ['id' => $question->id]);
                continue;
            }
            $question->slot = $slot + 1;
            $DB->update_record('jazzquiz_questions', $question);
        }
        $this->refresh_questions();
    }

    /**
     * Get the question order.
     *
     * @return int[] of jazzquiz_question id
     */
    public function get_question_order(): array {
        $order = [];
        foreach ($this->questions as $question) {
            $order[] = $question->data->id;
        }
        return $order;
    }

    /**
     * Edit a JazzQuiz question.
     *
     * @param int $questionid the JazzQuiz question id
     */
    public function edit_question(int $questionid): void {
        global $DB;
        $url = new moodle_url('/mod/jazzquiz/edit.php', ['id' => $this->cm->id]);
        $actionurl = clone($url);
        $actionurl->param('action', 'editquestion');
        $actionurl->param('questionid', $questionid);

        $jazzquizquestion = $DB->get_record('jazzquiz_questions', ['id' => $questionid], '*', MUST_EXIST);
        $question = $DB->get_record('question', ['id' => $jazzquizquestion->questionid], '*', MUST_EXIST);

        $mform = new forms\edit\add_question_form($actionurl, [
            'jazzquiz' => $this,
            'questionname' => $question->name,
            'edit' => true,
        ]);

        // Form handling.
        if ($mform->is_cancelled()) {
            // Redirect back to list questions page.
            $url->remove_params('action');
            redirect($url, null, 0);
        } else if ($data = $mform->get_data()) {
            $question = new stdClass();
            $question->id = $jazzquizquestion->id;
            $question->jazzquizid = $this->data->id;
            $question->questionid = $jazzquizquestion->questionid;
            if ($data->notime) {
                $question->questiontime = 0;
            } else {
                $question->questiontime = $data->questiontime;
            }
            $DB->update_record('jazzquiz_questions', $question);
            // Ensure there is no action or question_id in the base url.
            $url->remove_params('action', 'questionid');
            redirect($url, null, 0);
        } else {
            // Display the form.
            $mform->set_data([
                'questiontime' => $jazzquizquestion->questiontime,
                'notime' => $jazzquizquestion->questiontime < 1,
            ]);
            $this->renderer->header($this, 'edit');
            echo '<div class="generalbox boxaligncenter jazzquiz-box">';
            $mform->display();
            echo '</div>';
            $this->renderer->footer();
        }
    }

    /**
     * Loads the quiz questions from the database, ordered by slot.
     */
    public function refresh_questions(): void {
        global $DB;
        $this->questions = [];
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $jazzquestion = new jazzquiz_question($question);
            if ($jazzquestion->is_valid()) {
                $this->questions[$question->slot] = $jazzquestion;
            } else {
                $DB->delete_records('jazzquiz_questions', ['id' => $question->id]);
            }
        }
    }

    /**
     * Get a JazzQuiz question by id.
     *
     * @param int $jazzquizquestionid
     * @return ?jazzquiz_question
     */
    public function get_question_by_id(int $jazzquizquestionid): ?jazzquiz_question {
        foreach ($this->questions as $question) {
            if ($question->data->id == $jazzquizquestionid) {
                return $question;
            }
        }
        return null;
    }

    /**
     * Check if the current user is an instructor in this quiz.
     *
     * @return bool
     */
    public function is_instructor(): bool {
        return has_capability('mod/jazzquiz:control', $this->context);
    }

    /**
     * Get all sessions for this jazzquiz.
     *
     * @param array $conditions
     * @return stdClass[]
     */
    public function get_sessions(array $conditions = []): array {
        global $DB;
        $conditions = array_merge(['jazzquizid' => $this->data->id], $conditions);
        return $DB->get_records('jazzquiz_sessions', $conditions);
    }

}
