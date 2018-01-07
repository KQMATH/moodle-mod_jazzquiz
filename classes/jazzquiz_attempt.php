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
 * An attempt for the quiz. Maps to individual question attempts.
 *
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_attempt {

    /** Constants for the status of the attempt */
    const NOTSTARTED = 0;
    const INPROGRESS = 10;
    const PREVIEW = 20;
    const FINISHED = 30;

    /** @var \stdClass */
    public $data;

    /** @var \question_usage_by_activity $quba the question usage by activity for this attempt */
    public $quba;

    /**
     * Construct the class. If data is passed in we set it, otherwise initialize empty class
     * @param \context_module $context
     * @param \stdClass $data
     */
    public function __construct($context, $data = null) {
        if (empty($data)) {
            // Create new attempt
            $this->data = new \stdClass();
            // Create a new quba since we're creating a new attempt
            $this->quba = \question_engine::make_questions_usage_by_activity('mod_jazzquiz', $context);
            $this->quba->set_preferred_behaviour('immediatefeedback');
        } else {
            // Load it up in this class instance
            $this->data = $data;
            $this->quba = \question_engine::load_questions_usage_by_activity($this->data->questionengid);
        }
    }

    /**
     * Check if this attempt is currently in progress.
     * @return bool
     */
    public function is_active() {
        switch ($this->data->status) {
            case self::INPROGRESS:
            case self::PREVIEW:
                return true;
            default:
                return false;
        }
    }

    /**
     * @param jazzquiz_session $session
     * @return bool false if invalid question id
     */
    public function create_missing_attempts($session) {
        foreach ($session->questions as $slot => $question) {
            if ($this->quba->next_slot_number() > $slot) {
                continue;
            }
            $questiondefinition = reset(question_load_questions([$question->questionid]));
            if (!$questiondefinition) {
                return false;
            }
            $question = \question_bank::make_question($questiondefinition);
            $slot = $this->quba->add_question($question);
            $this->quba->start_question($slot);
            $this->data->responded = 0;
            $this->data->responded_count = 0;
            $this->save();
        }
        return true;
    }

    /**
     * Fetches user from database and returns the full name.
     * @return string
     */
    public function get_user_full_name() {
        global $DB;
        $user = $DB->get_record('user', ['id' => $this->data->userid]);
        return fullname($user);
    }

    /**
     * Saves the current attempt class
     * @return bool
     */
    public function save() {
        global $DB;

        // Save the question usage by activity object.
        \question_engine::save_questions_usage_by_activity($this->quba);

        // Add the quba id as the questionengid.
        // This is here because for new usages there is no id until we save it.
        $this->data->questionengid = $this->quba->get_id();
        $this->data->timemodified = time();

        if (isset($this->data->id)) {
            try {
                $DB->update_record('jazzquiz_attempts', $this->data);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return false;
            }
        } else {
            try {
                $this->data->id = $DB->insert_record('jazzquiz_attempts', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Saves a question attempt from the jazzquiz question
     * @param int $slot
     */
    public function save_question($slot) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->quba->process_all_actions();
        $this->quba->finish_question($slot, time());
        $this->data->timemodified = time();
        $this->data->responded = 1;
        if (empty($this->data->responded_count)) {
            $this->data->responded_count = 0;
        }
        $this->data->responded_count++;
        $this->save();
        $transaction->allow_commit();
    }

    /**
     * Gets the feedback for the specified question slot
     *
     * If no slot is defined, we attempt to get that from the slots param passed
     * back from the form submission
     *
     * @param jazzquiz $jazzquiz
     * @param int $slot The slot for which we want to get feedback
     * @return string HTML fragment of the feedback
     */
    public function get_question_feedback($jazzquiz, $slot = -1) {
        global $PAGE;
        if ($slot === -1) {
            // Attempt to get it from the slots param sent back from a question processing.
            $slots = required_param('slots', PARAM_ALPHANUMEXT);
            $slots = explode(',', $slots);
            $slot = $slots[0]; // Always just get the first thing from explode.
        }
        $question = $this->quba->get_question($slot);
        $renderer = $question->get_renderer($PAGE);
        $displayoptions = $jazzquiz->get_display_options();
        return $renderer->feedback($this->quba->get_question_attempt($slot), $displayoptions);
    }

    /**
     * Returns whether current user has responded
     * @param int $slot
     * @return bool
     */
    public function has_responded($slot) {
        $response = $this->quba->get_question_attempt($slot)->get_response_summary();
        return $response !== null && $response !== '';
    }

    /**
     * Returns response data as an array
     * @param int $slot
     * @return string[]
     */
    public function get_response_data($slot) {
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
     * Closes the attempt
     * @param jazzquiz $jazzquiz
     * @return bool Whether or not it was successful
     */
    public function close_attempt($jazzquiz) {
        $this->quba->finish_all_questions(time());
        // We want the instructor to remain in preview mode.
        if (!$jazzquiz->is_instructor()) {
            $this->data->status = self::FINISHED;
        }
        $this->data->timefinish = time();
        $this->save();
        $params = [
            'objectid' => $this->data->id,
            'context' => $jazzquiz->context,
            'relateduserid' => $this->data->userid
        ];
        $event = event\attempt_ended::create($params);
        $event->add_record_snapshot('jazzquiz_attempts', $this->data);
        $event->trigger();
        return true;
    }

}
