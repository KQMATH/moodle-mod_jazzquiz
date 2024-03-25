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
 * Restore Jazzquiz from backup.
 *
 * @package   mod_jazzquiz
 * @copyright 2024 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_jazzquiz_activity_structure_step extends restore_questions_activity_structure_step {

    /** @var stdClass Attempt record for inform_new_usage_id */
    private stdClass $currentattempt;

    /**
     * Define the structure of the restore element.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $userinfo = $this->get_setting_value('userinfo');
        $paths = [];
        $paths[] = new restore_path_element('jazzquiz', '/activity/jazzquiz');
        $paths[] = new restore_path_element('jazzquiz_question',
           '/activity/jazzquiz/questions/question');
        if ($userinfo) {
            $paths[] = new restore_path_element('jazzquiz_session',
               '/activity/jazzquiz/sessions/session');
            $paths[] = new restore_path_element('jazzquiz_session_question',
               '/activity/jazzquiz/sessions/session/sessionquestions/sessionquestion');
            $paths[] = new restore_path_element('jazzquiz_merge',
               '/activity/jazzquiz/sessions/session/merges/merge');
            $paths[] = new restore_path_element('jazzquiz_vote',
               '/activity/jazzquiz/sessions/session/votes/vote');
            $paths[] = new restore_path_element('jazzquiz_attendance',
               '/activity/jazzquiz/sessions/session/attendances/attendance');
            $attempt = new restore_path_element('jazzquiz_attempt',
               '/activity/jazzquiz/sessions/session/attempts/attempt');
            $paths[] = $attempt;
            $this->add_question_usages($attempt, $paths);
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process activity record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('jazzquiz', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process question record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_question(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jazzquizid = $this->get_new_parentid('jazzquiz');
        $data->questionid = $this->get_mappingid('question', $data->questionid);
        if (!$data->questionid) {
            return;
        }
        $newitemid = $DB->insert_record('jazzquiz_questions', $data);
        $this->set_mapping('jazzquiz_question', $oldid, $newitemid);
    }

    /**
     * Process session record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_session(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jazzquizid = $this->get_new_parentid('jazzquiz');
        $data->created = $this->apply_date_offset($data->created);
        $newitemid = $DB->insert_record('jazzquiz_sessions', $data);
        $this->set_mapping('jazzquiz_session', $oldid, $newitemid);
    }

    /**
     * Process attempt record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_attempt(stdClass|array $data): void {
        $data = (object)$data;
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $this->currentattempt = clone($data);
    }

    /**
     * Process question usage for attempt record currently being processed.
     *
     * @param int $newusageid
     * @return void
     */
    protected function inform_new_usage_id($newusageid): void {
        global $DB;
        $data = $this->currentattempt;
        $oldid = $data->id;
        $data->questionengid = $newusageid;
        $newitemid = $DB->insert_record('jazzquiz_attempts', $data);
        $this->set_mapping('jazzquiz_attempt', $oldid, $newitemid);
    }

    /**
     * Process session question record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_session_question(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');
        $data->questionid = $this->get_mappingid('question', $data->questionid);
        if (!$data->questionid) {
            return;
        }
        $oldid = $data->id;
        $newitemid = $DB->insert_record('jazzquiz_session_questions', $data);
        $this->set_mapping('jazzquiz_session_question', $oldid, $newitemid);
    }

    /**
     * Process merge record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_merge(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');
        $newitemid = $DB->insert_record('jazzquiz_merges', $data);
        $this->set_mapping('jazzquiz_merge', $oldid, $newitemid);
    }

    /**
     * Process vote record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_vote(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->jazzquizid = $this->get_mappingid('jazzquiz', $data->jazzquizid);
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');
        $newitemid = $DB->insert_record('jazzquiz_votes', $data);
        $this->set_mapping('jazzquiz_vote', $oldid, $newitemid);
    }

    /**
     * Process attendance record.
     *
     * @param stdClass|array $data
     * @return void
     */
    protected function process_jazzquiz_attendance(stdClass|array $data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');
        $newitemid = $DB->insert_record('jazzquiz_attendance', $data);
        $this->set_mapping('jazzquiz_attendance', $oldid, $newitemid);
    }

    /**
     * After execute.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_jazzquiz', 'intro', null);
    }

}
