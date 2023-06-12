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

defined('MOODLE_INTERNAL') || die();

/**
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2019 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_session {

    /** @var jazzquiz $jazzquiz */
    public $jazzquiz;

    /** @var \stdClass $data The jazzquiz_session database table row */
    public $data;

    /** @var jazzquiz_attempt[] */
    public $attempts;

    /** @var jazzquiz_attempt|false $attempt The current user's quiz attempt. False if not loaded. */
    public $attempt;

    /** @var \stdClass[] jazzquiz_session_questions */
    public $questions;

    /**
     * Constructor.
     * @param jazzquiz $jazzquiz
     * @param int $sessionid
     */
    public function __construct(jazzquiz $jazzquiz, $sessionid) {
        global $DB;
        $this->jazzquiz = $jazzquiz;
        $this->attempts = [];
        $this->attempt = false;
        $this->questions = [];
        $this->data = $DB->get_record('jazzquiz_sessions', [
            'jazzquizid' => $this->jazzquiz->data->id,
            'id' => $sessionid
        ], '*', MUST_EXIST);
    }

    /**
     * Get array of unasked slots.
     * @return int[] question slots.
     */
    public function get_unasked_slots() : array {
        $slots = [];
        foreach ($this->jazzquiz->questions as $question) {
            $asked = false;
            foreach ($this->questions as $sessionquestion) {
                if ($sessionquestion->questionid == $question->data->questionid) {
                    $asked = true;
                    break;
                }
            }
            if (!$asked) {
                $slots[] = $question->data->slot;
            }
        }
        return $slots;
    }

    private function requires_anonymous_answers() {
        return $this->data->anonymity != 3;
    }

    private function requires_anonymous_attendance() {
        return $this->data->anonymity == 2;
    }

    /**
     * Saves the session object to the database
     * @return bool
     */
    public function save() {
        global $DB;
        // Update if we already have a session, otherwise create it.
        if (isset($this->data->id)) {
            try {
                $DB->update_record('jazzquiz_sessions', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        } else {
            try {
                $this->data->id = $DB->insert_record('jazzquiz_sessions', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Deletes the specified session, as well as the attempts.
     * @param int $sessionid
     * @return bool
     */
    public static function delete($sessionid) {
        global $DB;
        // Delete all attempt quba ids, then all JazzQuiz attempts, and then finally itself.
        $condition = new \qubaid_join('{jazzquiz_attempts} jqa', 'jqa.questionengid', 'jqa.sessionid = :sessionid', [
            'sessionid' => $sessionid
        ]);
        \question_engine::delete_questions_usage_by_activities($condition);
        $DB->delete_records('jazzquiz_attempts', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_session_questions', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_sessions', ['id' => $sessionid]);
        return true;
    }

    public function user_name_for_answer($userid) {
        global $DB;
        if ($this->requires_anonymous_answers() || is_null($userid)) {
            return get_string('anonymous', 'jazzquiz');
        }
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return '?';
        }
        $userFirstLastName = $user->lastname . ', ' . $user->firstname;
        return $userFirstLastName;
    }

    public function user_name_for_attendance($userid) {
        global $DB;
        if ($this->requires_anonymous_attendance() || is_null($userid)) {
            return get_string('anonymous', 'jazzquiz');
        }
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return '?';
        }
        $userFirstLastName = $user->lastname . ', ' . $user->firstname;
        return $userFirstLastName;
    }

    public function user_idnumber_for_attendance($userid) {
        global $DB;
        if ($this->requires_anonymous_attendance() || is_null($userid)) {
            return get_string('anonymous', 'jazzquiz');
        }
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return '?';
        }
        return $user->idnumber;
    }

    /**
     * Anonymize attendance, making it unknown how many questions each user has answered.
     * Only makes sense to do this along with anonymizing answers. This should be called first.
     */
    private function anonymize_attendance() {
        global $DB;
        $attendances = $DB->get_records('jazzquiz_attendance', ['sessionid' => $this->data->id]);
        foreach ($attendances as $attendance) {
            $attendance->userid = null;
            $DB->update_record('jazzquiz_attendance', $attendance);
        }
    }

    private function anonymize_users() {
        if ($this->requires_anonymous_attendance()) {
            $this->anonymize_attendance();
        }
        foreach ($this->attempts as &$attempt) {
            if ($this->requires_anonymous_answers()) {
                $attempt->anonymize_answers();
            }
        }
    }

    /**
     * Closes the attempts and ends the session.
     * @return bool Whether or not this was successful
     */
    public function end_session() {
        $this->anonymize_users();
        $this->data->status = 'notrunning';
        $this->data->sessionopen = 0;
        $this->data->currentquestiontime = null;
        $this->data->nextstarttime = null;
        foreach ($this->attempts as $attempt) {
            $attempt->close_attempt($this->jazzquiz);
        }
        return $this->save();
    }

    /**
     * Merge responses with 'from' to 'into'
     * @param int $slot Session question slot
     * @param string $from Original response text
     * @param string $into Merged response text
     */
    public function merge_responses($slot, string $from, string $into) {
        global $DB;
        $merge = new \stdClass();
        $merge->sessionid = $this->data->id;
        $merge->slot = $slot;
        $merge->ordernum = count($DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ]));
        $merge->original = $from;
        $merge->merged = $into;
        $DB->insert_record('jazzquiz_merges', $merge);
    }

    /**
     * Undo the last merge of the specified question.
     * @param int $slot Session question slot
     */
    public function undo_merge($slot) {
        global $DB;
        $merge = $DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ], 'ordernum desc', '*', 0, 1);
        if (count($merge) > 0) {
            $merge = reset($merge);
            $DB->delete_records('jazzquiz_merges', ['id' => $merge->id]);
        }
    }

    /**
     * Get the merged responses.
     * @param int $slot Session question slot
     * @param string[]['response'] $responses Original responses
     * @return string[]['response'], int Merged responses and count of merges.
     */
    public function get_merged_responses($slot, array $responses) : array {
        global $DB;
        $merges = $DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ]);
        $count = 0;
        foreach ($merges as $merge) {
            foreach ($responses as &$response) {
                if ($merge->original === $response['response']) {
                    $response['response'] = $merge->merged;
                    $count++;
                }
            }
        }
        return [$responses, $count];
    }

    /**
     * Tells the session to go to the specified question number
     * That jazzquiz_question is then returned
     * @param int $questionid (from question bank)
     * @param int $questiontime in seconds ("<0" => no time, "0" => default)
     * @return mixed[] $success, $question_time
     */
    public function start_question($questionid, $questiontime) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();

        $sessionquestion = new \stdClass();
        $sessionquestion->sessionid = $this->data->id;
        $sessionquestion->questionid = $questionid;
        $sessionquestion->questiontime = $questiontime;
        $sessionquestion->slot = count($DB->get_records('jazzquiz_session_questions', ['sessionid' => $this->data->id])) + 1;
        $sessionquestion->id = $DB->insert_record('jazzquiz_session_questions', $sessionquestion);
        $this->questions[$sessionquestion->slot] = $sessionquestion;

        foreach ($this->attempts as &$attempt) {
            $attempt->create_missing_attempts($this);
            $attempt->save();
        }

        $this->data->currentquestiontime = $questiontime;
        $this->data->nextstarttime = time() + $this->jazzquiz->data->waitforquestiontime;
        $this->save();

        $transaction->allow_commit();
        return [true, $questiontime];
    }

    /**
     * Create a quiz attempt for the current user.
     */
    public function initialize_attempt() {
        global $USER;
        // Check if this user has already joined the quiz.
        foreach ($this->attempts as &$attempt) {
            if ($attempt->data->userid == $USER->id) {
                $attempt->create_missing_attempts($this);
                $attempt->save();
                $this->attempt = $attempt;
                return;
            }
        }
        // For users who have not yet joined the quiz.
        $this->attempt = new jazzquiz_attempt($this->jazzquiz->context);
        $this->attempt->data->sessionid = $this->data->id;
        if (isguestuser($USER->id)) {
            $this->attempt->data->guestsession = $USER->sesskey;
        } else {
            $this->attempt->data->userid = $USER->id;
        }
        $this->attempt->data->status = jazzquiz_attempt::NOTSTARTED;
        $this->attempt->data->timemodified = time();
        $this->attempt->data->timestart = time();
        $this->attempt->data->responded = null;
        $this->attempt->data->timefinish = null;
        $this->attempt->create_missing_attempts($this);
        $this->attempt->save();
    }

    public function update_attendance_for_current_user() {
        global $DB, $USER;
        if (isguestuser($USER->id)) {
            return;
        }
        $numresponses = $this->attempt->total_answers();
        if ($numresponses === 0) {
            return;
        }
        $attendance = $DB->get_record('jazzquiz_attendance', [
            'sessionid' => $this->data->id,
            'userid' => $USER->id
        ]);
        if ($attendance) {
            $attendance->numresponses = $numresponses;
            $DB->update_record('jazzquiz_attendance', $attendance);
        } else {
            $attendance = new \stdClass();
            $attendance->sessionid = $this->data->id;
            $attendance->userid = $USER->id;
            $attendance->numresponses = $numresponses;
            $DB->insert_record('jazzquiz_attendance', $attendance);
        }
    }

    /**
     * Load all the attempts for this session.
     */
    public function load_attempts() {
        global $DB;
        $this->attempts = [];
        $attempts = $DB->get_records('jazzquiz_attempts', ['sessionid' => $this->data->id]);
        foreach ($attempts as $attempt) {
            $this->attempts[$attempt->id] = new jazzquiz_attempt($this->jazzquiz->context, $attempt);
        }
    }

    /**
     * Load the current attempt for the user.
     */
    public function load_attempt() {
        global $DB, $USER;
        $sql = 'SELECT *
                  FROM {jazzquiz_attempts}
                 WHERE sessionid = :sessionid
                   AND (userid = :userid OR guestsession = :sesskey)';
        $attempt = $DB->get_record_sql($sql, [
            'sessionid' => $this->data->id,
            'userid' => $USER->id,
            'sesskey' => $USER->sesskey
        ]);
        if (!$attempt) {
            $this->attempt = false;
            return;
        }
        $this->attempt = new jazzquiz_attempt($this->jazzquiz->context, $attempt);
    }

    /**
     * Load all the session questions.
     */
    public function load_session_questions() {
        global $DB;
        $this->questions = $DB->get_records('jazzquiz_session_questions', ['sessionid' => $this->data->id], 'slot');
        foreach ($this->questions as $question) {
            unset($this->questions[$question->id]);
            $this->questions[$question->slot] = $question;
        }
    }

    /**
     * Get the user IDs for who have attempted this session
     * @return int[] user IDs that have attempted this session
     */
    public function get_users() {
        $users = [];
        foreach ($this->attempts as $attempt) {
            // Preview attempt means it's an instructor.
            if ($attempt->data->status != jazzquiz_attempt::PREVIEW) {
                $users[] = isguestuser($attempt->data->userid) ? null : $attempt->data->userid;
            }
        }
        return $users;
    }

    /**
     * Get the total number of students participating in the quiz.
     * @return int
     */
    public function get_student_count() {
        $count = count($this->attempts);
        if ($count > 0) {
            $count--; // The instructor also has an attempt.
            // Can also loop through all to check if "in progress",
            // but usually there is only one instructor, so maybe not?
        }
        return $count;
    }

    /**
     * Get the correct answer rendered in HTML.
     * @return string HTML
     */
    public function get_question_right_response() {
        // Use the current user's attempt to render the question with the right response.
        $quba = $this->attempt->quba;
        $slot = count($this->questions);
        $correctresponse = $quba->get_correct_response($slot);
        if (is_null($correctresponse)) {
            return 'No correct response';
        }
        $quba->process_action($slot, $correctresponse);
        $this->attempt->save();
        $reviewoptions = new \stdClass();
        $reviewoptions->rightanswer = 1;
        $reviewoptions->correctness = 1;
        $reviewoptions->specificfeedback = 1;
        $reviewoptions->generalfeedback = 1;
        /** @var output\renderer $renderer */
        $renderer = $this->jazzquiz->renderer;
        ob_start();
        $html = $renderer->render_question($this->jazzquiz, $this->attempt->quba, $slot, true, $reviewoptions);
        $htmlechoed = ob_get_clean();
        return $html . $htmlechoed;
    }

    /**
     * Gets the results of the current question as an array
     * @param int $slot
     * @return array
     */
    public function get_question_results_list($slot) {
        $responses = [];
        $responded = 0;
        foreach ($this->attempts as $attempt) {
            if ($attempt->data->responded != 1) {
                continue;
            }
            $attemptresponses = $attempt->get_response_data($slot);
            $responses = array_merge($responses, $attemptresponses);
            $responded++;
        }
        foreach ($responses as &$response) {
            $response = ['response' => $response];
        }
        return [
            'responses' => $responses,
            'responded' => $responded,
            'student_count' => $this->get_student_count()
        ];
    }

    public function get_attendances() : array {
        global $DB;
        $attendances = [];
        $records = $DB->get_records('jazzquiz_attendance', ['sessionid' => $this->data->id]);
        foreach ($records as $record) {
            $attendances[] = [
                'idnumber' => $this->user_idnumber_for_attendance($record->userid),
                'name' => $this->user_name_for_attendance($record->userid),
                'count' => $record->numresponses
            ];
        }
        foreach ($this->attempts as $attempt) {
            if (!$attempt->data->userid && $attempt->data->guestsession) {
                $attendances[] = [
                    'name' => get_string('anonymous', 'jazzquiz'),
                    'count' => $attempt->total_answers()
                ];
            }
        }
        return $attendances;
    }

    /**
     * @todo Temporary function. Should be removed at a later time.
     * @param int $slot
     * @return string
     */
    public function get_question_type_by_slot($slot) : string {
        global $DB;
        $id = $this->questions[$slot]->questionid;
        $question = $DB->get_record('question', ['id' => $id], 'qtype');
        return $question->qtype;
    }

}
