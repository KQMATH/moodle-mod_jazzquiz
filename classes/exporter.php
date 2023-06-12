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

defined('MOODLE_INTERNAL') || die;

/**
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {

    /**
     * Escape the characters used for structuring the CSV contents.
     * @param string $text
     * @return string
     */
    public static function escape_csv(string $text) : string {
        $text = str_replace("\r", '', $text);
        $text = str_replace("\n", '', $text);
        $text = str_replace("\t", '    ', $text);
        return $text;
    }

    /**
     * Sets header to download csv file of given filename.
     * @param string $name filename without extension
     */
    private function csv_file(string $name) {
        header("Content-Disposition: attachment; filename=$name.csv");
        echo "sep=\t\r\n";
    }

    /**
     * Export session data to array.
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt[] $quizattempts
     * @return array
     */
    public function export_session(jazzquiz_session $session, array $quizattempts) : array {
        $quizattempt = reset($quizattempts);
        $qubaslots = $quizattempt->quba->get_slots();
        $slots = [];
        foreach ($qubaslots as $slot) {
            $questionattempt = $quizattempt->quba->get_question_attempt($slot);
            $question = $questionattempt->get_question();
            $qtype = $question->get_type_name();
            $slots[$slot] = [
                'name' => $question->name,
                'qtype' => $qtype,
                'responses' => []
            ];
        }
        $users = [];
        foreach ($quizattempts as $quizattempt) {
            if ($quizattempt->data->status == jazzquiz_attempt::PREVIEW) {
                continue;
            }
            $answers = [];
            foreach ($quizattempt->quba->get_slots() as $slot) {
                $answers[$slot] = $quizattempt->get_response_data($slot);
            }
            $users[] = [
                'name' => $session->user_name_for_answer($quizattempt->data->userid),
                'answers' => $answers
            ];
        }
        $name = 'session_' . $session->data->id . '_' . $session->data->name;
        return [$name, $slots, $users];
    }

    /**
     * Export and print session data as CSV.
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt[] $attempts
     */
    public function export_session_csv(jazzquiz_session $session, array $attempts) {
        list($name, $slots, $users) = $this->export_session($session, $attempts);
        $this->csv_file($name);
        // Header row.
        echo "Student\t";
        foreach ($slots as $slot) {
            $name = self::escape_csv($slot['name']);
            $qtype = self::escape_csv($slot['qtype']);
            echo "$name ($qtype)\t";
        }
        echo "\r\n";
        // Response rows.
        foreach ($users as $user) {
            $fullname = self::escape_csv($user['name']);
            echo "$fullname\t";
            foreach ($user['answers'] as $answer) {
                $answer = self::escape_csv(implode(', ', $answer));
                echo "$answer\t";
            }
            echo "\r\n";
        }
    }

    /**
     * Export session question data to array.
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt $attempt
     * @param int $slot
     * @return array
     */
    public function export_session_question(jazzquiz_session $session, jazzquiz_attempt $attempt, int $slot) : array {
        $qattempt = $attempt->quba->get_question_attempt($slot);
        $question = $qattempt->get_question();
        $session->load_attempts();
        $responses = $session->get_question_results_list($slot);
        $responses = $responses['responses'];
        $name = 'session_ ' . $session->data->id . '_' . $session->data->name . '_' . $question->name;
        return [$name, $question->questiontext, $responses];
    }

    /**
     * Export and print session question data as CSV.
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt $attempt
     * @param int $slot
     */
    public function export_session_question_csv(jazzquiz_session $session, jazzquiz_attempt $attempt, int $slot) {
        list($name, $text, $responses) = $this->export_session_question($session, $attempt, $slot);
        $this->csv_file($name);
        echo "$text\r\n";
        foreach ($responses as $response) {
            echo $response['response'] . "\r\n";
        }
    }

    /**
     * Export and print session attendance data as CSV.
     * @param jazzquiz_session $session
     */
    public function export_attendance_csv(jazzquiz_session $session) {
        $name = $session->data->id . '_' . $session->data->name;
        $attendances = $session->get_attendances();
        $this->csv_file("session_{$name}_attendance");
        echo "IdNumber\tFirst Name\tLast Name\tResponses\r\n";
        foreach ($attendances as $attendance) {
            $idnumber = $attendance['idnumber'];
            $userFirstLastName = explode(', ', $attendance['name']);
            if (count($userFirstLastName) >= 2) {
                $lastName = $userFirstLastName[0];
                $firstName = $userFirstLastName[1];
            } else {
                $lastName = $userFirstLastName[0];
                $firstName = $userFirstLastName[0]; //for anonymous, but it doesn't even make sense since if it's anon, no name should display
            }
            $count = $attendance['count'];
            echo "$idnumber\t$lastName\t$firstName\t$count\r\n";
        }
    }

}
