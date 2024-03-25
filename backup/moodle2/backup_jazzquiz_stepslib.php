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
 * Define the complete choice structure for backup, with file and id annotations.
 *
 * @package   mod_jazzquiz
 * @copyright 2024 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_jazzquiz_activity_structure_step extends backup_questions_activity_structure_step {

    /**
     * Defines the structure of the backup element.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $jazzquiz = new backup_nested_element('jazzquiz', ['id'], [
            'name',
            'intro',
            'introformat',
            'timecreated',
            'timemodified',
            'defaultquestiontime',
            'waitforquestiontime',
            'cfganonymity',
            'cfgallowguests',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'questionid',
            'questiontime',
            'slot',
        ]);

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'name',
            'sessionopen',
            'showfeedback',
            'status',
            'slot',
            'currentquestiontime',
            'nextstarttime',
            'created',
            'anonymity',
            'allowguests',
        ]);

        $sessionquestions = new backup_nested_element('sessionquestions');
        $sessionquestion = new backup_nested_element('sessionquestion', ['id'], [
            'sessionid',
            'questionid',
            'questiontime',
            'slot',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'sessionid',
            'userid',
            'questionengid',
            'status',
            'preview',
            'responded',
            'timestart',
            'timefinish',
            'timemodified',
            'guestsession',
        ]);
        $this->add_question_usages($attempt, 'questionengid');

        $merges = new backup_nested_element('merges');
        $merge = new backup_nested_element('merge', ['id'], [
            'sessionid',
            'slot',
            'ordernum',
            'original',
            'merged',
        ]);

        $votes = new backup_nested_element('votes');
        $vote = new backup_nested_element('vote', ['id'], [
            'sessionid',
            'attempt',
            'initialcount',
            'finalcount',
            'userlist',
            'qtype',
            'slot',
        ]);

        $attendances = new backup_nested_element('attendances');
        $attendance = new backup_nested_element('attendance', ['id'], [
            'sessionid',
            'userid',
            'numresponses',
        ]);

        // Build the tree.
        $jazzquiz->add_child($questions);
        $jazzquiz->add_child($sessions);

        $questions->add_child($question);

        $sessions->add_child($session);
        $session->add_child($sessionquestions);
        $session->add_child($attempts);
        $session->add_child($merges);
        $session->add_child($votes);
        $session->add_child($attendances);

        $sessionquestions->add_child($sessionquestion);
        $attempts->add_child($attempt);
        $merges->add_child($merge);
        $votes->add_child($vote);
        $attendances->add_child($attendance);

        // Define sources.
        $jazzquiz->set_source_table('jazzquiz', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('jazzquiz_questions', ['jazzquizid' => backup::VAR_PARENTID]);
        if ($userinfo) {
            $session->set_source_table('jazzquiz_sessions', ['jazzquizid' => backup::VAR_PARENTID]);
            $sessionquestion->set_source_table('jazzquiz_session_questions', ['sessionid' => backup::VAR_PARENTID]);
            $attempt->set_source_table('jazzquiz_attempts', ['sessionid' => backup::VAR_PARENTID]);
            $attendance->set_source_table('jazzquiz_attendance', ['sessionid' => backup::VAR_PARENTID]);
            $merge->set_source_table('jazzquiz_merges', ['sessionid' => backup::VAR_PARENTID]);
            $vote->set_source_table('jazzquiz_votes', [
                'jazzquizid' => backup::VAR_ACTIVITYID,
                'sessionid' => backup::VAR_PARENTID,
            ]);
        }

        // Define id annotations.
        $attempt->annotate_ids('user', 'userid');
        $attendance->annotate_ids('user', 'userid');
        $question->annotate_ids('question', 'questionid');
        $sessionquestion->annotate_ids('question', 'questionid');

        // Define file annotations.
        $jazzquiz->annotate_files('mod_jazzquiz', 'intro', null);

        // Return the root element (choice), wrapped into standard activity structure.
        return $this->prepare_activity_structure($jazzquiz);
    }

}
