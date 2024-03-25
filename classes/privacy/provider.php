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
 * Privacy Subsystem implementation for mod_jazzquiz.
 *
 * @package     mod_jazzquiz
 * @author      André Storhaug <andr3.storhaug@gmail.com>
 * @author      Sebastian Søviknes Gundersen <sebastian@sgundersen.com>
 * @copyright   2019 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use stdClass;

/**
 * Privacy Subsystem implementation for mod_jazzquiz.
 *
 * @author      André Storhaug <andr3.storhaug@gmail.com>
 * @author      Sebastian Søviknes Gundersen <sebastian@sgundersen.com>
 * @copyright   2019 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin has data.
    \core_privacy\local\metadata\provider,

    // This plugin currently implements the original plugin_provider interface.
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata about this system.
     *
     * @param collection $items The initialised collection to add metadata to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection {
        // The table 'jazzquiz' stores a record for each JazzQuiz.
        // It does not contain user personal data, but data is returned from it for contextual requirements.

        // The table 'jazzquiz_attempts' stores a record of each JazzQuiz attempt.
        // It contains a userid which links to the user making the attempt and contains information about that attempt.
        $items->add_database_table('jazzquiz_attempts', [
            'userid' => 'privacy:metadata:jazzquiz_attempts:userid',
            'responded' => 'privacy:metadata:jazzquiz_attempts:responded',
            'timestart' => 'privacy:metadata:jazzquiz_attempts:timestart',
            'timefinish' => 'privacy:metadata:jazzquiz_attempts:timefinish',
            'timemodified' => 'privacy:metadata:jazzquiz_attempts:timemodified',
        ], 'privacy:metadata:jazzquiz_attempts');

        // The 'jazzquiz_merges' table is used to merge one question attempt into another in a JazzQuiz session.
        // It does not contain user data.

        // The 'jazzquiz_questions' table is used to map the usage of a question used in a JazzQuiz activity.
        // It does not contain user data.

        // The 'jazzquiz_sessions' table is used to instantiate a JazzQuiz activity as a session.
        // It does not contain user data.

        // The 'jazzquiz_session_questions' table is used to instantiate the questions in the jazzquiz_questions to a session.
        // It does not contain user data.

        // The 'jazzquiz_votes' table is used to allow users to vote on a question attempt (text field).
        // It contains a list of user IDs that voted on an attempt. Is this worth exporting?

        // JazzQuiz links to the 'core_question' subsystem for all question functionality.
        $items->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');
        return $items;
    }

    /**
     * Get the list of contexts where the specified user has attempted a JazzQuiz.
     *
     * @param int $userid The user to search.
     * @return contextlist  $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = 'SELECT cx.id
                  FROM {context} cx
                  JOIN {course_modules} cm
                    ON cm.id = cx.instanceid
                   AND cx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {jazzquiz} jq
                    ON jq.id = cm.instance
                  JOIN {jazzquiz_sessions} js
                    ON js.jazzquizid = jq.id
                  JOIN {jazzquiz_attempts} ja
                    ON ja.sessionid = js.id
                   AND ja.userid = :userid';
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'jazzquiz',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id AS cmid
                  FROM {context} cx
                  JOIN {course_modules} cm
                    ON cm.id = cx.instanceid
                   AND cx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {jazzquiz} jq
                    ON jq.id = cm.instance
                  JOIN {jazzquiz_sessions} js
                    ON js.jazzquizid = jq.id
                  JOIN {jazzquiz_attempts} ja
                    ON ja.sessionid = js.id
                   AND ja.userid = :userid
                 WHERE cx.id {$contextsql}";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'jazzquiz',
            'userid' => $user->id,
        ];
        $params += $contextparams;

        // Fetch the individual quizzes.
        $quizzes = $DB->get_recordset_sql($sql, $params);
        foreach ($quizzes as $quiz) {
            $context = context_module::instance($quiz->cmid);
            $data = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $data);
        }
        $quizzes->close();
        static::export_quiz_attempts($contextlist);
    }

    /**
     * Export all quiz attempts for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    protected static function export_quiz_attempts(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $sql = "SELECT cx.id              AS contextid,
                       cm.id              AS cmid,
                       js.name            AS sessionname,
                       ja.id              AS id,
                       ja.sessionid       AS sessionid,
                       ja.responded       AS responded,
                       ja.timestart       AS timestart,
                       ja.timefinish      AS timefinish,
                       ja.timemodified    AS timemodified,
                       ja.questionengid   AS qubaid
                  FROM {context} cx
                  JOIN {course_modules} cm
                    ON cm.id = cx.instanceid
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {jazzquiz_sessions} js
                    ON js.jazzquizid = cm.instance
                  JOIN {jazzquiz_attempts} ja
                    ON ja.sessionid = js.id
                   AND ja.userid = :userid
                 WHERE cx.contextlevel = :contextlevel";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'modname' => 'jazzquiz',
        ];
        $attempts = $DB->get_recordset_sql($sql, $params);
        foreach ($attempts as $attempt) {
            $options = new \question_display_options();
            $options->context = context_module::instance($attempt->cmid);
            $attemptsubcontext = [$attempt->sessionname . ' ' . $attempt->sessionid . ' ' . $attempt->id];
            // This attempt was made by the user. They 'own' all data on it. Store the question usage data.
            \core_question\privacy\provider::export_question_usage(
                $userid,
                $options->context,
                $attemptsubcontext,
                $attempt->qubaid,
                $options,
                true,
            );
            // Store the quiz attempt data.
            $data = new stdClass();
            $data->responded = $attempt->responded;
            $data->timestart = transform::datetime($attempt->timestart);
            $data->timefinish = transform::datetime($attempt->timefinish);
            $data->timemodified = transform::datetime($attempt->timemodified);
            writer::with_context($options->context)->export_data($attemptsubcontext, $data);
        }
        $attempts->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('jazzquiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        $sessions = $DB->get_records('jazzquiz_sessions', ['jazzquizid' => $cm->instance]);
        foreach ($sessions as $session) {
            $DB->delete_records('jazzquiz_attempts', ['sessionid' => $session->id]);
        }
        \core_question\privacy\provider::delete_data_for_all_users_in_context($context);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('jazzquiz', $context->instanceid);
            $sessions = $DB->get_records('jazzquiz_sessions', ['jazzquizid' => $cm->instance]);
            foreach ($sessions as $session) {
                $DB->delete_records('jazzquiz_attempts', ['userid' => $userid, 'sessionid' => $session->id]);
            }
        }
        \core_question\privacy\provider::delete_data_for_user($contextlist);
    }

}
