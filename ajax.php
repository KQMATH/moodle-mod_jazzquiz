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
 * Ajax callback script for dealing with quiz data.
 *
 * This callback handles saving questions as well as instructor actions.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

use question_bank;

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

require_login();
require_sesskey();

/**
 * Send a list of all the questions tagged for use with improvisation.
 *
 * @param jazzquiz $jazzquiz
 * @return array
 */
function show_all_improvise_questions(jazzquiz $jazzquiz): array {
    $improviser = new improviser($jazzquiz);
    $questionrecords = $improviser->get_all_improvised_question_definitions();
    if (!$questionrecords) {
        return [
            'status' => 'error',
            'message' => 'No improvisation questions',
        ];
    }
    $questions = [];
    foreach ($questionrecords as $question) {
        $questions[] = [
            'questionid' => $question->id,
            'jazzquizquestionid' => 0,
            'name' => str_replace('{IMPROV}', '', $question->name),
            'time' => $jazzquiz->data->defaultquestiontime,
        ];
    }
    return [
        'status' => 'success',
        'questions' => $questions,
    ];
}

/**
 * Send a list of all the questions added to the quiz.
 *
 * @param jazzquiz $jazzquiz
 * @return array
 */
function show_all_jump_questions(jazzquiz $jazzquiz): array {
    global $DB;
    $sql = 'SELECT q.id AS id, q.name AS name, jq.questiontime AS time, jq.id AS jqid';
    $sql .= '  FROM {jazzquiz_questions} jq';
    $sql .= '  JOIN {question} q ON q.id = jq.questionid';
    $sql .= ' WHERE jq.jazzquizid = ?';
    $sql .= ' ORDER BY jq.slot ASC';
    $questionrecords = $DB->get_records_sql($sql, [$jazzquiz->data->id]);
    $questions = [];
    foreach ($questionrecords as $question) {
        $questions[] = [
            'questionid' => $question->id,
            'jazzquizquestionid' => $question->jqid,
            'name' => $question->name,
            'time' => $question->time,
        ];
    }
    return [
        'status' => 'success',
        'questions' => $questions,
    ];
}

/**
 * Get the form for the current question.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function get_question_form(jazzquiz_session $session): array {
    $session->load_session_questions();
    $slot = optional_param('slot', 0, PARAM_INT);
    if ($slot === 0) {
        $slot = count($session->questions);
    }
    $html = '';
    $js = '';
    $css = [];
    $isalreadysubmitted = true;
    if (!$session->myattempt->has_responded($slot)) {
        $jazzquiz = $session->jazzquiz;
        /** @var output\renderer $renderer */
        $renderer = $jazzquiz->renderer;
        $isinstructor = $jazzquiz->is_instructor();
        list($html, $js, $css) = $renderer->render_question_form($slot, $session->myattempt, $jazzquiz, $isinstructor);
        $isalreadysubmitted = false;
    }
    $qtype = $session->get_question_type_by_slot($slot);
    $voteable = ['multichoice', 'truefalse'];
    return [
        'html' => $html,
        'js' => $js,
        'css' => $css,
        'question_type' => $qtype,
        'is_already_submitted' => $isalreadysubmitted,
        'voteable' => !in_array($qtype, $voteable),
    ];
}

/**
 * Start a new question.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function start_question(jazzquiz_session $session): array {
    $session->load_session_questions();
    $session->load_attempts();
    $method = required_param('method', PARAM_ALPHA);
    // Variable $questionid is a Moodle question id.
    switch ($method) {
        case 'jump':
            $questionid = required_param('questionid', PARAM_INT);
            $questiontime = optional_param('questiontime', 0, PARAM_INT);
            $jazzquizquestionid = optional_param('jazzquizquestionid', 0, PARAM_INT);
            if ($jazzquizquestionid !== 0) {
                $jazzquizquestion = $session->jazzquiz->get_question_by_id($jazzquizquestionid);
                if ($jazzquizquestion) {
                    $session->data->slot = $jazzquizquestion->data->slot;
                }
            }
            break;
        case 'repoll':
            $lastslot = count($session->questions);
            if ($lastslot === 0) {
                return [
                    'status' => 'error',
                    'message' => 'Nothing to repoll.',
                ];
            }
            $questionid = $session->questions[$lastslot]->questionid;
            $questiontime = $session->data->currentquestiontime;
            break;
        case 'next':
            $lastslot = count($session->jazzquiz->questions);
            if ($session->data->slot >= $lastslot) {
                return [
                    'status' => 'error',
                    'message' => 'No next question.',
                ];
            }
            $session->data->slot++;
            $jazzquizquestion = $session->jazzquiz->questions[$session->data->slot];
            $questionid = $jazzquizquestion->question->id;
            $questiontime = $jazzquizquestion->data->questiontime;
            break;
        case 'random':
            $slots = $session->get_unasked_slots();
            if (count($slots) > 0) {
                $session->data->slot = $slots[array_rand($slots)];
            } else {
                $lastslot = count($session->jazzquiz->questions);
                $session->data->slot = random_int(1, $lastslot);
            }
            $jazzquizquestion = $session->jazzquiz->questions[$session->data->slot];
            $questionid = $jazzquizquestion->question->id;
            $questiontime = $jazzquizquestion->data->questiontime;
            break;
        default:
            return [
                'status' => 'error',
                'message' => "Invalid method $method",
            ];
    }
    list($success, $questiontime) = $session->start_question($questionid, $questiontime);
    if (!$success) {
        return [
            'status' => 'error',
            'message' => "Failed to start question $questionid for session",
        ];
    }

    $session->data->status = 'running';
    $session->save();

    return [
        'status' => 'success',
        'questiontime' => $questiontime,
        'delay' => $session->data->nextstarttime - time(),
    ];
}

/**
 * Start the quiz.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function start_quiz(jazzquiz_session $session): array {
    if ($session->data->status !== 'notrunning') {
        return [
            'status' => 'error',
            'message' => 'Quiz is already running',
        ];
    }
    $session->data->status = 'preparing';
    $session->save();
    return ['status' => 'success'];
}

/**
 * Submit a response for the current question.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function save_question(jazzquiz_session $session): array {
    $attempt = $session->myattempt;
    if (!$attempt->belongs_to_current_user()) {
        return [
            'status' => 'error',
            'message' => 'Invalid user',
        ];
    }
    $session->load_session_questions();
    $attempt->save_question(count($session->questions));
    $session->update_attendance_for_current_user();
    // Only give feedback if specified in session.
    $feedback = '';
    if ($session->data->showfeedback) {
        $feedback = $attempt->get_question_feedback($session->jazzquiz);
    }
    return [
        'status' => 'success',
        'feedback' => $feedback,
    ];
}

/**
 * Start a vote.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function run_voting(jazzquiz_session $session): array {
    $questions = required_param('questions', PARAM_RAW);
    $questions = json_decode(urldecode($questions), true);
    if (!$questions) {
        return [
            'status' => 'error',
            'message' => 'Failed to decode questions',
        ];
    }
    $qtype = optional_param('question_type', '', PARAM_ALPHANUM);

    $session->load_session_questions();
    $vote = new jazzquiz_vote($session->data->id);
    $slot = count($session->questions);
    $vote->prepare_options($session->jazzquiz->data->id, $qtype, $questions, $slot);

    $session->data->status = 'voting';
    $session->save();
    return ['status' => 'success'];
}

/**
 * Save a vote.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function save_vote(jazzquiz_session $session): array {
    $voteid = required_param('vote', PARAM_INT);
    $vote = new jazzquiz_vote($session->data->id);
    $status = $vote->save_vote($voteid);
    return ['status' => ($status ? 'success' : 'error')];
}

/**
 * Get the vote results.
 * @param jazzquiz_session $session
 * @return array
 */
function get_vote_results(jazzquiz_session $session): array {
    $session->load_session_questions();
    $session->load_attempts();
    $slot = count($session->questions);
    $vote = new jazzquiz_vote($session->data->id, $slot);
    $votes = $vote->get_results();
    return [
        'answers' => $votes,
        'total_students' => $session->get_student_count(),
    ];
}

/**
 * End the current question.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function end_question(jazzquiz_session $session): array {
    $session->data->status = 'reviewing';
    $session->save();
    return ['status' => 'success'];
}

/**
 * Get the correct answer for the current question.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function get_right_response(jazzquiz_session $session): array {
    $session->load_session_questions();
    return ['right_answer' => $session->get_question_right_response()];
}

/**
 * Close a session.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function close_session(jazzquiz_session $session): array {
    $session->load_attempts();
    $session->end_session();
    return ['status' => 'success'];
}

/**
 * Get the results for a session.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function get_results(jazzquiz_session $session): array {
    $session->load_session_questions();
    $session->load_attempts();
    $slot = count($session->questions);
    $qtype = $session->get_question_type_by_slot($slot);
    $results = $session->get_question_results_list($slot, true);
    list($results['responses'], $mergecount) = $session->get_merged_responses($slot, $results['responses']);

    // Check if this has been voted on before.
    $vote = new jazzquiz_vote($session->data->id, $slot);
    $hasvotes = count($vote->get_results()) > 0;

    return [
        'has_votes' => $hasvotes,
        'question_type' => $qtype,
        'responses' => $results['responses'],
        'responded' => $results['responded'],
        'total_students' => $results['student_count'],
        'merge_count' => $mergecount,
    ];
}

/**
 * Merge a response into another.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function merge_responses(jazzquiz_session $session): array {
    $session->load_session_questions();
    $slot = optional_param('slot', count($session->questions), PARAM_INT);
    if (!isset($session->questions[$slot])) {
        return ['status' => 'error'];
    }
    $from = required_param('from', PARAM_TEXT);
    $into = required_param('into', PARAM_TEXT);
    $session->merge_responses($slot, $from, $into);
    return ['status' => 'success'];
}

/**
 * Undo the last merge.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function undo_merge(jazzquiz_session $session): array {
    $session->load_session_questions();
    $slot = optional_param('slot', count($session->questions), PARAM_INT);
    if (!isset($session->questions[$slot])) {
        return ['status' => 'error'];
    }
    $session->undo_merge($slot);
    return ['status' => 'success'];
}

/**
 * Convert STACK (Maxima) string to LaTeX format.
 *
 * @return array
 */
function stack_to_latex(): array {
    global $DB;
    $input = required_param('input', PARAM_RAW);
    $input = urldecode($input);
    $question = $DB->get_record_sql('SELECT id FROM {question} WHERE qtype = ? AND name LIKE ?', ['stack', '{IMPROV}%']);
    if (!$question) {
        return [
            'message' => 'STACK question not found.',
            'latex' => $input,
            'original' => $input,
        ];
    }

    /** @var \qtype_stack_question $question */
    $question = question_bank::load_question($question->id);
    $question->initialise_question_from_seed();
    $state = $question->get_input_state('ans1', ['ans1' => $input]);
    $latex = $state->contentsdisplayed;

    return [
        'latex' => $latex,
        'original' => $input,
    ];
}

/**
 * Retrieve the current state of the session.
 *
 * @param jazzquiz_session $session
 * @return array
 */
function session_info(jazzquiz_session $session): array {
    global $DB;
    switch ($session->data->status) {
        // Just a generic response with the state.
        case 'notrunning':
            if ($session->jazzquiz->is_instructor()) {
                $session = new jazzquiz_session($session->jazzquiz, $session->data->id);
                $session->load_attempts();
                return [
                    'status' => $session->data->status,
                    'student_count' => $session->get_student_count(),
                ];
            }
            // Fall-through.
        case 'preparing':
        case 'reviewing':
            return [
                'status' => $session->data->status,
                'slot' => $session->data->slot, // For the preplanned questions.
            ];

        case 'voting':
            $voteoptions = $DB->get_records('jazzquiz_votes', ['sessionid' => $session->data->id]);
            $options = [];
            $html = '<div class="jazzquiz-vote jazzquiz-response-container">';
            $i = 0;
            foreach ($voteoptions as $voteoption) {
                $options[] = [
                    'text' => $voteoption->attempt,
                    'id' => $voteoption->id,
                    'question_type' => $voteoption->qtype,
                    'content_id' => "vote_answer_label_$i",
                ];
                $html .= '<label>';
                $html .= '<input class="jazzquiz-select-vote" type="radio" name="vote" value="' . $voteoption->id . '">';
                $html .= '<span id="vote_answer_label_' . $i . '">' . $voteoption->attempt . '</span>';
                $html .= '</label><br>';
                $i++;
            }
            $html .= '</div>';
            $html .= '<button id="jazzquiz_save_vote" class="btn btn-primary">Save</button>';
            return [
                'status' => 'voting',
                'html' => $html,
                'options' => $options,
            ];

        // Send the currently active question.
        case 'running':
            return [
                'status' => 'running',
                'questiontime' => $session->data->currentquestiontime,
                'delay' => $session->data->nextstarttime - time(),
            ];

        // This should not be reached, but if it ever is, let's just assume the quiz is not running.
        default:
            return [
                'status' => 'notrunning',
                'message' => 'Unknown error. State: ' . $session->data->status,
            ];
    }
}

/**
 * Handle an instructor request.
 *
 * @param string $action
 * @param jazzquiz_session $session
 * @return array
 */
function handle_instructor_request(string $action, jazzquiz_session $session): array {
    return match ($action) {
        'start_quiz' => start_quiz($session),
        'get_question_form' => get_question_form($session),
        'save_question' => save_question($session),
        'list_improvise_questions' => show_all_improvise_questions($session->jazzquiz),
        'list_jump_questions' => show_all_jump_questions($session->jazzquiz),
        'run_voting' => run_voting($session),
        'get_vote_results' => get_vote_results($session),
        'get_results' => get_results($session),
        'start_question' => start_question($session),
        'end_question' => end_question($session),
        'get_right_response' => get_right_response($session),
        'merge_responses' => merge_responses($session),
        'undo_merge' => undo_merge($session),
        'close_session' => close_session($session),
        'info' => session_info($session),
        default => ['status' => 'error', 'message' => 'Invalid action'],
    };
}

/**
 * Handle a student request.
 *
 * @param string $action
 * @param jazzquiz_session $session
 * @return array
 */
function handle_student_request(string $action, jazzquiz_session $session): array {
    return match ($action) {
        'save_question' => save_question($session),
        'save_vote' => save_vote($session),
        'get_question_form' => get_question_form($session),
        'info' => session_info($session),
        default => ['status' => 'error', 'message' => 'Invalid action'],
    };
}

/**
 * The entry point to handle instructor and student actions.
 *
 * @return array
 */
function jazzquiz_ajax(): array {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    // TODO: Better solution if more non-session actions are added.
    if ($action === 'stack') {
        return stack_to_latex();
    }

    $cmid = required_param('id', PARAM_INT);
    $sessionid = required_param('sessionid', PARAM_INT);

    $jazzquiz = new jazzquiz($cmid);
    $session = new jazzquiz_session($jazzquiz, $sessionid);
    if (!$session->data->sessionopen) {
        return [
            'status' => 'sessionclosed',
            'message' => 'Session is closed',
        ];
    }

    $session->load_my_attempt();
    if (!$session->myattempt) {
        return [
            'status' => 'error',
            'message' => 'No attempt found',
        ];
    }
    if (!$session->myattempt->is_active()) {
        return [
            'status' => 'error',
            'message' => 'This attempt is not in progress',
        ];
    }

    if ($jazzquiz->is_instructor()) {
        return handle_instructor_request($action, $session);
    } else {
        return handle_student_request($action, $session);
    }
}

$starttime = microtime(true);
$data = jazzquiz_ajax();
$endtime = microtime(true);
$data['debugmu'] = $endtime - $starttime;
echo json_encode($data);
