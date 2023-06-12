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

namespace mod_jazzquiz\output;

use mod_jazzquiz\forms\view\student_start_form;
use mod_jazzquiz\jazzquiz;
use mod_jazzquiz\jazzquiz_attempt;
use mod_jazzquiz\jazzquiz_session;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/questionlib.php');

/**
 * To load a question without refreshing the page, we need the JavaScript for the question.
 * Moodle stores this in page_requirements_manager, but there is no way to read the JS that is required.
 * This class takes in the manager and keeps the JS for when we want to get a diff.
 * NOTE: This class is placed here because it will only ever be used by renderer::render_question_form()
 * TODO: Look into removing this class in the future.
 * @package   mod_jazzquiz\output
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_requirements_diff extends \page_requirements_manager {

    /** @var array $beforeinitjs */
    private $beforeinitjs;

    /** @var array $beforeamdjs */
    private $beforeamdjs;

    /** @var array $beforecss */
    private $beforecss;

    /**
     * Constructor.
     * @param \page_requirements_manager $manager
     */
    public function __construct(\page_requirements_manager $manager) {
        $this->beforeinitjs = $manager->jsinitcode;
        $this->beforeamdjs = $manager->amdjscode;
        $this->beforecss = $manager->cssurls;
    }

    /**
     * Run an array_diff on the required JavaScript when this
     * was constructed and the one passed to this function.
     * @param \page_requirements_manager $manager
     * @return array the JavaScript that was added in-between constructor and this call.
     */
    public function get_js_diff(\page_requirements_manager $manager) : array {
        $jsinitcode = array_diff($manager->jsinitcode, $this->beforeinitjs);
        $amdjscode = array_diff($manager->amdjscode, $this->beforeamdjs);
        return array_merge($jsinitcode, $amdjscode);
    }

    /**
     * Run an array_diff on the required CSS when this
     * was constructed and the one passed to this function.
     * @param \page_requirements_manager $manager
     * @return array the CSS that was added in-between constructor and this call.
     */
    public function get_css_diff(\page_requirements_manager $manager) : array {
        return array_keys(array_diff($manager->cssurls, $this->beforecss));
    }

}

/**
 * Quiz renderer
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2019 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the header for the page.
     * @param jazzquiz $jazzquiz
     * @param string $tab The active tab on the page
     */
    public function header(jazzquiz $jazzquiz, string $tab) {
        echo $this->output->header();
        echo jazzquiz_view_tabs($jazzquiz, $tab);
    }

    /**
     * Render the footer for the page.
     */
    public function footer() {
        echo $this->output->footer();
    }

    /**
     * For instructors.
     * @param \moodleform $sessionform
     */
    public function start_session_form(\moodleform $sessionform) {
        echo $this->render_from_template('jazzquiz/start_session', ['form' => $sessionform->render()]);
    }

    /**
     * For instructors.
     * @param jazzquiz $jazzquiz
     */
    public function continue_session_form(jazzquiz $jazzquiz) {
        $cmid = $jazzquiz->cm->id;
        $id = $jazzquiz->data->id;
        echo $this->render_from_template('jazzquiz/continue_session', [
            'path' => $this->page->url->get_path() . "?id=$cmid&quizid=$id&action=quizstart"
        ]);
    }

    /**
     * Show the "join quiz" form for students.
     * @param student_start_form $form
     * @param jazzquiz_session $session
     * @throws \moodle_exception
     */
    public function join_quiz_form(student_start_form $form, jazzquiz_session $session) {
        $anonstr = ['', 'anonymous_answers_info', 'fully_anonymous_info', 'nonanonymous_session_info'];
        echo $this->render_from_template('jazzquiz/join_session', [
            'name' => $session->data->name,
            'started' => ($session->attempt !== false),
            'anonymity_info' => get_string($anonstr[$session->data->anonymity], 'jazzquiz'),
            'form' => $form->render()
        ]);
    }

    /**
     * Show the "quiz not running" page for students.
     * @param int $cmid the course module id for the quiz
     * @throws \moodle_exception
     */
    public function quiz_not_running($cmid) {
        echo $this->render_from_template('jazzquiz/no_session', [
            'reload' => $this->page->url->get_path() . '?id=' . $cmid
        ]);
    }


    /**
     * Shows the "guests not allowed" page when trying to access a 
     * quiz which does not allow guests in guest mode.
     * @throws \moodle_exception
     */
    public function guests_not_allowed() {
        echo $this->render_from_template('jazzquiz/guests_not_allowed', []);
    }



    /**
     * Renders the quiz to the page
     * @param jazzquiz_session $session
     * @throws \moodle_exception
     */
    public function render_quiz(jazzquiz_session $session) {
        $this->require_quiz($session);
        $buttons = function($buttons) {
            $result = [];
            foreach ($buttons as $button) {
                $result[] = [
                    'icon' => $button[0],
                    'id' => $button[1],
                    'text' => get_string($button[1], 'jazzquiz')
                ];
            }
            return $result;
        };
        echo $this->render_from_template('jazzquiz/quiz', [
            'buttons' => $buttons([
                ['repeat', 'repoll'],
                ['bar-chart', 'vote'],
                ['edit', 'improvise'],
                ['bars', 'jump'],
                ['forward', 'next'],
                ['random', 'random'],
                ['close', 'end'],
                ['expand', 'fullscreen'],
                ['window-close', 'quit'],
                ['square-o', 'responses'],
                ['square-o', 'answer']
            ]),
            'instructor' => $session->jazzquiz->is_instructor()
        ]);
    }

    /**
     * Render the question specified by slot
     * @param jazzquiz $jazzquiz
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param bool $review Whether or not we're reviewing the attempt
     * @param string|\stdClass $reviewoptions Can be string for overall actions like "edit" or an object of review options
     * @return string the HTML fragment for the question
     */
    public function render_question(jazzquiz $jazzquiz, $quba, $slot, bool $review, $reviewoptions) : string {
        $displayoptions = $jazzquiz->get_display_options($review, $reviewoptions);
        $quba->render_question_head_html($slot);
        return $quba->render_question($slot, $displayoptions, $slot);
    }

    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     * @param int $slot the id of the question we're rendering
     * @param jazzquiz_attempt $attempt
     * @param jazzquiz $jazzquiz
     * @param bool $instructor
     * @return string[] html, javascript, css
     * @throws \moodle_exception
     */
    public function render_question_form($slot, jazzquiz_attempt $attempt, jazzquiz $jazzquiz, bool $instructor) : array {
        $differ = new page_requirements_diff($this->page->requires);
        ob_start();
        $questionhtml = $this->render_question($jazzquiz, $attempt->quba, $slot, false, '');
        $questionhtmlechoed = ob_get_clean();
        $js = implode("\n", $differ->get_js_diff($this->page->requires));
        $css = $differ->get_css_diff($this->page->requires);
        $output = $this->render_from_template('jazzquiz/question', [
            'instructor' => $instructor,
            'question' => $questionhtml . $questionhtmlechoed,
            'slot' => $slot
        ]);
        return [$output, $js, $css];
    }

    /**
     * Renders and echos the home page for the responses section
     * @param \moodle_url $url
     * @param \stdClass[] $sessions
     * @param int $selectedid
     * @return array
     * @throws \coding_exception
     */
    public function get_select_session_context(\moodle_url $url, array $sessions, $selectedid) : array {
        $selecturl = clone($url);
        $selecturl->param('action', 'view');
        usort($sessions, function ($a, $b) {
            return strcmp(strtolower($a->name), strtolower($b->name));
        });
        return [
            'method' => 'get',
            'action' => $selecturl->out_omit_querystring(),
            'formid' => 'jazzquiz_select_session_form',
            'id' => 'jazzquiz_select_session',
            'name' => 'sessionid',
            'options' => array_map(function ($session) use ($selectedid) {
                return [
                    'name' => $session->name,
                    'value' => $session->id,
                    'selected' => intval($selectedid) === intval($session->id),
                    'optgroup' => false
                ];
            }, $sessions),
            'params' => array_map(function ($key, $value) {
                return [
                    'name' => $key,
                    'value' => $value
                ];
            }, array_keys($selecturl->params()), $selecturl->params()),
        ];
    }

    /**
     * Render the list questions view for the edit page
     * @param jazzquiz $jazzquiz
     * @param array $questions Array of questions
     * @param string $questionbankview HTML for the question bank view
     * @param \moodle_url $url
     * @throws \moodle_exception
     */
    public function list_questions(jazzquiz $jazzquiz, array $questions, string $questionbankview, \moodle_url $url) {
        $slot = 1;
        $list = [];
        foreach ($questions as $question) {
            $editurl = clone($url);
            $editurl->param('action', 'editquestion');
            $editurl->param('questionid', $question->data->id);
            $list[] = [
                'id' => $question->data->id,
                'name' => $question->question->name,
                'first' => $slot === 1,
                'last' => $slot === count($questions),
                'slot' => $slot,
                'editurl' => $editurl,
                'icon' => print_question_icon($question->question)
            ];
            $slot++;
        }
        echo $this->render_from_template('jazzquiz/edit_question_list', [
            'questions' => $list,
            'qbank' => $questionbankview
        ]);
        $this->require_edit($jazzquiz->cm->id);
    }

    public function session_is_open_error() {
        echo \html_writer::tag('h3', get_string('edit_page_open_session_error', 'jazzquiz'));
    }

    /**
     * @param jazzquiz_session $session
     * @param \moodle_url $url
     * @return mixed[]
     * @throws \coding_exception
     */
    public function view_session_report(jazzquiz_session $session, \moodle_url $url) : array {
        $attempt = reset($session->attempts);
        if (!$attempt) {
            $strnoattempts = get_string('no_attempts_found', 'jazzquiz');
            echo '<div class="jazzquiz-box"><p>' . $strnoattempts . '</p></div>';
            return [];
        }
        $slots = [];
        foreach ($attempt->quba->get_slots() as $qubaslot) {
            $qattempt = $attempt->quba->get_question_attempt($qubaslot);
            $question = $qattempt->get_question();
            $results = $session->get_question_results_list($qubaslot);
            list($results['responses'], $mergecount) = $session->get_merged_responses($qubaslot, $results['responses']);
            $slots[] = [
                'num' => $qubaslot,
                'name' => str_replace('{IMPROV}', '', $question->name),
                'type' => $attempt->quba->get_question_attempt($qubaslot)->get_question()->get_type_name(),
                'description' => $question->questiontext,
                'responses' => $results['responses']
            ];
        }

        // TODO: Slots should not be passed as parameter to AMD module.
        // It quickly gets over 1KB, which shows debug warning.
        $this->require_review($session, $slots);

        $attendances = $session->get_attendances();
        $jazzquiz = $session->jazzquiz;
        $sessions = $jazzquiz->get_sessions();
        return [
            'select_session' => $jazzquiz->renderer->get_select_session_context($url, $sessions, $session->data->id),
            'session' => [
                'slots' => $slots,
                'students' => $attendances,
                'count_total' => count($session->attempts) - 1, // TODO: For loop and check if preview instead?
                'count_answered' => count($attendances),
                'cmid' => $jazzquiz->cm->id,
                'quizid' => $jazzquiz->data->id,
                'id' => $session->data->id
            ]
        ];
    }

    /**
     * @param jazzquiz_session $session
     */
    public function require_core(jazzquiz_session $session) {
        $this->page->requires->js_call_amd('mod_jazzquiz/core', 'initialize', [
            $session->jazzquiz->cm->id,
            $session->jazzquiz->data->id,
            $session->data->id,
            $session->attempt ? $session->attempt->data->id : 0,
            sesskey()
        ]);
    }

    /**
     * @param jazzquiz_session $session
     */
    public function require_quiz(jazzquiz_session $session) {
        $this->require_core($session);
        $this->page->requires->js('/question/qengine.js');
        if ($session->jazzquiz->is_instructor()) {
            $count = count($session->jazzquiz->questions);
            $params = [$count, false, []];
            $this->page->requires->js_call_amd('mod_jazzquiz/instructor', 'initialize', $params);
        } else {
            $this->page->requires->js_call_amd('mod_jazzquiz/student', 'initialize');
        }
    }

    /**
     * @param int $cmid
     */
    public function require_edit($cmid) {
        $this->page->requires->js('/mod/jazzquiz/js/sortable.min.js');
        $this->page->requires->js_call_amd('mod_jazzquiz/edit', 'initialize', [$cmid]);
    }

    /**
     * @param jazzquiz_session $session
     * @param array $slots
     */
    public function require_review(jazzquiz_session $session, array $slots) {
        $this->require_core($session);
        $count = count($session->jazzquiz->questions);
        $params = [$count, true, $slots];
        $this->page->requires->js_call_amd('mod_jazzquiz/instructor', 'initialize', $params);
    }

}
