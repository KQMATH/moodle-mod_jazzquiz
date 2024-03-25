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
use question_bank;
use question_engine;
use question_usage_by_activity;
use stdClass;

/**
 * An attempt for the quiz. Maps to individual question attempts.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2019 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_attempt {

    /** The attempt has not started. */
    const NOTSTARTED = 0;

    /** The attempt is in progress. */
    const INPROGRESS = 10;

    /** The attempt is only for preview. */
    const PREVIEW = 20;

    /** The attempt is finished. */
    const FINISHED = 30;

    /** @var int The jazzquiz attempt id */
    public int $id;

    /** @var int The jazzquiz session id */
    public int $sessionid;

    /** @var int|null The user id */
    public ?int $userid;

    /** @var int The question usage by activity id */
    public int $questionengid;

    /** @var string|null The guest session key */
    public ?string $guestsession;

    /** @var int Current status of this attempt */
    public int $status;

    /** @var int|null Response status for the current question */
    public ?int $responded;

    /** @var int When this quiz attempt started */
    public int $timestart;

    /** @var int|null When this quiz attempt finished */
    public ?int $timefinish;

    /** @var int When this quiz attempt was last modified */
    public int $timemodified;

    /** @var question_usage_by_activity The question usage by activity for this attempt */
    public question_usage_by_activity $quba;

    /**
     * Constructor.
     *
     * @param stdClass $record
     */
    private function __construct(stdClass $record) {
        $this->id = $record->id;
        $this->sessionid = $record->sessionid;
        $this->userid = $record->userid;
        $this->questionengid = $record->questionengid;
        $this->guestsession = $record->guestsession;
        $this->status = $record->status;
        $this->responded = $record->responded;
        $this->timestart = $record->timestart;
        $this->timefinish = $record->timefinish;
        $this->timemodified = $record->timemodified;
        $this->quba = question_engine::load_questions_usage_by_activity($this->questionengid);
    }

    /**
     * Check if the attempt belongs to the current user.
     *
     * @return bool
     */
    public function belongs_to_current_user(): bool {
        global $USER;
        return $this->userid == $USER->id || $this->guestsession === $USER->sesskey;
    }

    /**
     * Check if this attempt is currently in progress.
     *
     * @return bool
     */
    public function is_active(): bool {
        return match ($this->status) {
            self::INPROGRESS, self::PREVIEW => true,
            default => false,
        };
    }

    /**
     * Create missing question attempts.
     *
     * This is necessary when new questions have been added by the teacher during the quiz.
     *
     * @param jazzquiz_session $session
     * @return bool false if invalid question id
     */
    public function create_missing_attempts(jazzquiz_session $session): bool {
        foreach ($session->questions as $slot => $question) {
            if ($this->quba->next_slot_number() > $slot) {
                continue;
            }
            $questiondefinitions = question_load_questions([$question->questionid]);
            $questiondefinition = reset($questiondefinitions);
            if (!$questiondefinition) {
                return false;
            }
            $question = question_bank::make_question($questiondefinition);
            $slot = $this->quba->add_question($question);
            $this->quba->start_question($slot);
            $this->responded = 0;
        }
        return true;
    }

    /**
     * Anonymize all the question attempt steps for this quiz attempt.
     *
     * It will be impossible to identify which user answered all questions linked to this question usage.
     *
     * @return void
     */
    public function anonymize_answers(): void {
        global $DB;
        $attempts = $DB->get_records('question_attempts', ['questionusageid' => $this->questionengid]);
        foreach ($attempts as $attempt) {
            $steps = $DB->get_records('question_attempt_steps', ['questionattemptid' => $attempt->id]);
            foreach ($steps as $step) {
                $step->userid = null;
                $DB->update_record('question_attempt_steps', $step);
            }
        }
        $this->userid = null;
        $this->save();
    }

    /**
     * Helper function to properly create a new attempt for a JazzQuiz session.
     *
     * @param jazzquiz_session $session
     * @return jazzquiz_attempt
     */
    public static function create(jazzquiz_session $session): jazzquiz_attempt {
        global $DB, $USER;
        $quba = question_engine::make_questions_usage_by_activity('mod_jazzquiz', $session->jazzquiz->context);
        $quba->set_preferred_behaviour('immediatefeedback');
        // TODO: Don't suppress the error if it becomes possible to save QUBAs without slots.
        @question_engine::save_questions_usage_by_activity($quba);
        $id = $DB->insert_record('jazzquiz_attempts', [
            'sessionid' => $session->data->id,
            'userid' => isguestuser($USER->id) ? null : $USER->id,
            'questionengid' => $quba->get_id(),
            'guestsession' => isguestuser($USER->id) ? $USER->sesskey : null,
            'status' => self::NOTSTARTED,
            'responded' => null,
            'timestart' => time(),
            'timefinish' => null,
            'timemodified' => time(),
        ]);
        $attempt = self::get_by_id($id);
        $attempt->create_missing_attempts($session);
        return $attempt;
    }

    /**
     * Helper function to properly load a JazzQuiz attempt by its ID.
     *
     * @param int $id
     * @return jazzquiz_attempt
     */
    public static function get_by_id(int $id): jazzquiz_attempt {
        global $DB;
        $record = $DB->get_record('jazzquiz_attempts', ['id' => $id], '*', MUST_EXIST);
        return new jazzquiz_attempt($record);
    }

    /**
     * Helper function to properly load a JazzQuiz attempt for the current user in a session.
     *
     * @param jazzquiz_session $session
     * @return jazzquiz_attempt
     */
    public static function get_by_session_for_current_user(jazzquiz_session $session): jazzquiz_attempt {
        global $DB, $USER;
        $sql = 'SELECT *
                  FROM {jazzquiz_attempts}
                 WHERE sessionid = :sessionid
                   AND (userid = :userid OR guestsession = :sesskey)';
        $record = $DB->get_record_sql($sql, [
            'sessionid' => $session->data->id,
            'userid' => $USER->id,
            'sesskey' => $USER->sesskey,
        ]);
        if (empty($record)) {
            return self::create($session);
        }
        return new jazzquiz_attempt($record);
    }

    /**
     * Save attempt.
     */
    public function save(): void {
        global $DB;
        if ($this->quba->question_count() > 0) {
            question_engine::save_questions_usage_by_activity($this->quba);
        }
        $this->timemodified = time();
        $DB->update_record('jazzquiz_attempts', [
            'id' => $this->id,
            'sessionid' => $this->sessionid,
            'userid' => $this->userid,
            'questionengid' => $this->questionengid,
            'guestsession' => $this->guestsession,
            'status' => $this->status,
            'responded' => $this->responded,
            'timestart' => $this->timestart,
            'timefinish' => $this->timefinish,
            'timemodified' => $this->timemodified,
        ]);
    }

    /**
     * Saves a question attempt from the jazzquiz question.
     *
     * @param int $slot
     */
    public function save_question(int $slot): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->quba->process_all_actions();
        $this->quba->finish_question($slot, time());
        $this->timemodified = time();
        $this->responded = 1;
        $this->save();
        $transaction->allow_commit();
    }

    /**
     * Gets the feedback for the specified question slot.
     *
     * If no slot is defined, we attempt to get that from the slots param passed
     * back from the form submission.
     *
     * @param jazzquiz $jazzquiz
     * @param int $slot The slot for which we want to get feedback
     * @return string HTML fragment of the feedback
     */
    public function get_question_feedback(jazzquiz $jazzquiz, int $slot = -1): string {
        global $PAGE;
        if ($slot === -1) {
            // Attempt to get it from the slots param sent back from a question processing.
            $slots = required_param('slots', PARAM_ALPHANUMEXT);
            $slots = explode(',', $slots);
            $slot = $slots[0]; // Always just get the first thing from explode.
        }
        $question = $this->quba->get_question($slot);
        $renderer = $question->get_renderer($PAGE);
        $displayoptions = $jazzquiz->get_display_options(false, '');
        return $renderer->feedback($this->quba->get_question_attempt($slot), $displayoptions);
    }

    /**
     * Returns whether current user has responded.
     *
     * @param int $slot
     * @return bool
     */
    public function has_responded(int $slot): bool {
        $questionattempt = $this->quba->get_question_attempt($slot);
        return !empty($questionattempt->get_response_summary());
    }

    /**
     * Returns response data as an array.
     *
     * @param int $slot
     * @return string[]
     */
    public function get_response_data(int $slot): array {
        $questionattempt = $this->quba->get_question_attempt($slot);
        $response = $questionattempt->get_response_summary();
        if ($response === null || $response === '') {
            return [];
        }
        $qtype = $questionattempt->get_question()->get_type_name();
        switch ($qtype) {
            case 'stack':
                // TODO: Figure out a better way to get rid of the input name.
                $response = str_replace('ans1: ', '', $response);
                $response = str_replace(' [valid]', '', $response);
                $response = str_replace(' [score]', '', $response);
                return [$response];
            case 'multichoice':
                return explode("\n; ", trim($response, "\n"));
            default:
                return [$response];
        }
    }

    /**
     * Closes the attempt.
     *
     * @param jazzquiz $jazzquiz
     */
    public function close_attempt(jazzquiz $jazzquiz): void {
        $this->quba->finish_all_questions(time());
        // We want the instructor to remain in preview mode.
        if (!$jazzquiz->is_instructor()) {
            $this->status = self::FINISHED;
        }
        $this->timefinish = time();
        $this->save();
    }

    /**
     * Get total answers.
     *
     * @return int
     */
    public function total_answers(): int {
        $count = 0;
        foreach ($this->quba->get_slots() as $slot) {
            if ($this->has_responded($slot)) {
                $count++;
            }
        }
        return $count;
    }

}
