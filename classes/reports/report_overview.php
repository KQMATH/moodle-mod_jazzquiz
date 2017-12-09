<?php

namespace mod_jazzquiz\reports;

class report_overview
{
    /**
     * @var \mod_jazzquiz\jazzquiz $jazzquiz
     */
    protected $jazzquiz;

    /**
     * @var \mod_jazzquiz\output\report_overview_renderer $renderer
     */
    protected $renderer;

    /**
     * report_overview constructor.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function __construct(\mod_jazzquiz\jazzquiz $jazzquiz)
    {
        global $PAGE;
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', 'report_overview');
        $this->jazzquiz = $jazzquiz;
    }

    private function output_csv_response($session, $quiz_attempt)
    {
        $slot = required_param('slot', PARAM_INT);
        $quba = $quiz_attempt->get_quba();
        $question_attempt = $quba->get_question_attempt($slot);
        $question = $question_attempt->get_question();
        $responses = $session->get_question_results_list($slot, 'all');
        $responses = $responses['responses'];
        $session_data = $session->get_session();
        $name = $session_data->id . '_' . $session_data->name . '_' . $question->name;
        header('Content-Disposition: attachment; filename=session_' . $name . '.csv');
        foreach ($responses as $response) {
            echo $response['response'] . "\r\n";
        }
    }

    private function output_csv_attendance($session, $quiz_attempt)
    {
        global $DB;

        $all_user_ids = $session->get_session_users();

        // This starts with all the ids, but is filtered below.
        // Should probably be refactored in the future.
        $not_responded_user_ids = $all_user_ids;
        $total_responded = [];
        $row = $quiz_attempt->get_attempt();
        $slots = explode(',', $row->qubalayout);
        $quba = $quiz_attempt->get_quba();

        foreach ($slots as $slot) {
            $question_attempt = $quba->get_question_attempt($slot);
            $responded = $session->get_responded_list($slot, 'all');
            if ($responded) {
                $total_responded = array_merge($total_responded, $responded);
            }
        }

        $name = $session->get_session()->id . '_' . $session->get_session()->name;
        header('Content-Disposition: attachment; filename=session_' . $name . '_attendance.csv');

        if ($total_responded) {
            $responded_with_count = [];
            foreach ($total_responded as $responded_user_id) {
                foreach ($not_responded_user_ids as $not_responded_index => $not_responded_user_id) {
                    if ($not_responded_user_id->userid === $responded_user_id) {
                        unset($not_responded_user_ids[$not_responded_index]);
                        break;
                    }
                }
                if (!isset($responded_with_count[$responded_user_id])) {
                    $responded_with_count[$responded_user_id] = 1;
                } else {
                    $responded_with_count[$responded_user_id]++;
                }
            }
            if ($responded_with_count) {
                foreach ($responded_with_count as $responded_user_id => $responded_count) {
                    $user = $DB->get_record('user', [
                        'id' => $responded_user_id
                    ]);
                    $user_full_name = fullname($user);
                    echo $user_full_name . ',' . $responded_count . "\r\n";
                }
                foreach ($not_responded_user_ids as $not_responded_user_id) {
                    $user = $DB->get_record('user', [
                        'id' => $not_responded_user_id->userid
                    ]);
                    $user_full_name = fullname($user);
                    echo $user_full_name . ",0\r\n";
                }
            }
        }
    }

    private function output_csv()
    {
        $session_id = required_param('sessionid', PARAM_INT);
        $session = $this->jazzquiz->get_session($session_id);
        $quiz_attempts = $session->getall_attempts(false);
        $quiz_attempt = reset($quiz_attempts);
        if (!$quiz_attempt) {
            return;
        }
        header('Content-Type: application/csv');
        $csv_type = required_param('csvtype', PARAM_ALPHANUM);
        switch ($csv_type) {
            case 'response':
                $this->output_csv_response($session, $quiz_attempt);
                break;
            case 'attendance':
                $this->output_csv_attendance($session, $quiz_attempt);
                break;
            default:
                break;
        }
    }

    private function view_session($page_url, $page_vars)
    {
        global $DB, $PAGE;

        $session_id = required_param('sessionid', PARAM_INT);
        if (empty($session_id)) {

            // If no session id just go to the home page
            $redirect_url = new \moodle_url('/mod/jazzquiz/reports.php', [
                'id' => $this->jazzquiz->getCM()->id,
                'quizid' => $this->jazzquiz->getRTQ()->id
            ]);
            redirect($redirect_url, null, 3);
        }

        $session = $this->jazzquiz->get_session($session_id);
        $page_url->param('sessionid', $session_id);

        $sessions = $this->jazzquiz->get_sessions();
        $this->renderer->select_session($sessions, $session_id);

        $quiz_attempts = $session->getall_attempts(false);
        $quiz_attempt = reset($quiz_attempts);

        if (!$quiz_attempt) {
            echo '<div class="jazzquizbox"><p>No attempt found.</p></div>';
            return;
        }

        $row = $quiz_attempt->get_attempt();
        $slots = explode(',', $row->qubalayout);
        $quba = $quiz_attempt->get_quba();

        $PAGE->requires->js('/mod/jazzquiz/js/core.js');
        $PAGE->requires->js('/mod/jazzquiz/js/instructor.js');

        echo '<script>';
        echo 'var jazzquiz_responses = [];';
        //echo 'var jazzquiz_responded_users = [];';
        echo '</script>';

        $total_responded = [];

        $id = required_param('id', PARAM_INT);
        $quizid = required_param('quizid', PARAM_INT);
        $sessionid = required_param('sessionid', PARAM_INT);

        foreach ($slots as $slot) {
            $question_attempt = $quba->get_question_attempt($slot);
            $question = $question_attempt->get_question();
            $responses = $session->get_question_results_list($slot, 'all');
            $responses = $responses['responses'];

            $responded = $session->get_responded_list($slot, 'closed');
            if ($responded) {
                $total_responded = array_merge($total_responded, $responded);
                /*echo '<script>';
                foreach ($responded as $responded_user_id) {
                    echo 'jazzquiz_responded_users.push(\'' . $responded_user_id . '\');';
                }
                echo '</script>';*/
            }

            $wrapper_id = 'jazzquiz_wrapper_responses_' . intval($slot);
            $table_id = 'responses_wrapper_table_' . intval($slot);

            $question_name = str_replace('{IMPROV}', '', $question->name);
            $question_type = $quba->get_question_attempt($slot)->get_question()->get_type_name();

            echo '<div class="jazzquizbox" id="' . $wrapper_id . '">'
                . "<h2>$question_name</h2>"
                . '<span class="jazzquiz-latex-wrapper">'
                . '<span class="filter_mathjaxloader_equation">' . $question->questiontext . '</span>'
                . '</span>'
                . '<table id="' . $table_id . '" class="jazzquiz-responses-overview"></table>';

            // Output CSV
            $url_params = "?id=$id&quizid=$quizid&reporttype=overview&action=csv&csvtype=response&download&sessionid=$sessionid&slot=$slot";
            echo '<br><a href="reports.php' . $url_params . '">Download CSV</a>';
            /*echo '<details>';
            echo '<summary style="cursor:pointer;">Show as CSV</summary>';
            echo '<pre style="font-family:monospace;background:white;padding:8px;border:1px solid #666;">';
            foreach ($responses as $response) {
                $response = $response['response'];
                echo "$response\n";
            }
            echo '</pre>';
            echo '</details><br>';*/

            echo '</div>';

            echo '<script>'
                . "jazzquiz_responses[$slot] = " . json_encode($responses) . ';'
                . 'setTimeout(function() {'
                . 'jazzquiz.quiz.attempt_id = ' . $row->id . ';'

                // TODO: This is kind of a hack... Should refactor the JavaScript.
                . 'jazzquiz.options.show_responses = true;'
                . 'jazzquiz.state = "reviewing";'

                . 'jazzquiz.quiz_info_responses("'
                . $wrapper_id . '", "' . $table_id . '", jazzquiz_responses[' . $slot . '], "' . $question_type . '", ' . $slot
                . ');'
                . '}, 1000);'
                . '</script>';

        }

        $all_user_ids = $session->get_session_users();

        // This starts with all the ids, but is filtered below.
        // Should probably be refactored in the future.
        $not_responded_user_ids = $all_user_ids;

        echo '<div id="report_overview_responded" class="jazzquizbox">';
        echo '<h2>' . get_string('attendance_list', 'jazzquiz') . '</h2>';
        if ($total_responded) {
            $responded_with_count = [];
            foreach ($total_responded as $responded_user_id) {
                foreach ($not_responded_user_ids as $not_responded_index => $not_responded_user_id) {
                    if ($not_responded_user_id->userid === $responded_user_id) {
                        unset($not_responded_user_ids[$not_responded_index]);
                        break;
                    }
                }
                if (!isset($responded_with_count[$responded_user_id])) {
                    $responded_with_count[$responded_user_id] = 1;
                } else {
                    $responded_with_count[$responded_user_id]++;
                }
            }
            if ($responded_with_count) {
                $attendance_list_csv = '';
                echo '<table>';
                echo '<tr><th>Student</th><th>Responses</th></tr>';
                // TODO: Refactor
                foreach ($responded_with_count as $responded_user_id => $responded_count) {
                    $user = $DB->get_record('user', [
                        'id' => $responded_user_id
                    ]);
                    $user_full_name = fullname($user);
                    echo '<tr>';
                    echo '<td>' . $user_full_name . '</td>';
                    echo '<td>' . $responded_count . ' responses</td>';
                    echo '</tr>';
                    $attendance_list_csv .= $user_full_name . ',' . $responded_count . '<br>';
                }
                foreach ($not_responded_user_ids as $not_responded_user_id) {
                    $user = $DB->get_record('user', [
                        'id' => $not_responded_user_id->userid
                    ]);
                    $user_full_name = fullname($user);
                    echo '<tr>';
                    echo '<td>' . $user_full_name . '</td>';
                    echo '<td>0 responses</td>';
                    echo '</tr>';
                    $attendance_list_csv .= $user_full_name . ',0<br>';
                }

                echo '</table>';
                echo '<br>';
                echo '<p><b>' . count($all_user_ids) . '</b> students joined the quiz.</p>';
                echo '<p><b>' . count($responded_with_count) . '</b> students answered at least one question.</p>';
                echo '<br>';
                $url_params = "?id=$id&quizid=$quizid&reporttype=overview&action=csv&csvtype=attendance&download&sessionid=$sessionid";
                echo '<a href="reports.php' . $url_params . '">Download CSV</a>';
                /*echo '<details>';
                echo '<summary style="cursor:pointer;">Show as CSV</summary>';
                echo '<pre style="font-family:monospace;background:white;padding:8px;border:1px solid #666;">' . $attendance_list_csv . '</pre>';
                echo '</details>';*/
            }
        }
        echo '</div>';
    }

    /**
     * Handle the request for this specific report
     *
     * @param \moodle_url $page_url
     * @param array $page_vars
     * @return void
     */
    public function handle_request($page_url, $page_vars)
    {
        $this->renderer->init($this->jazzquiz, $page_url, $page_vars);

        switch ($page_vars['action']) {

            case 'viewsession':
                $this->view_session($page_url, $page_vars);
                break;

            case 'csv':
                $this->output_csv();
                break;

            default:
                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->select_session($sessions);
                break;
        }
    }

}