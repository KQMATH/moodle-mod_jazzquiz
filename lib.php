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
 * Library of functions and constants for module jazzquiz.
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_jazzquiz\jazzquiz_session;

/**
 * Create a new JazzQuiz instance.
 *
 * @param stdClass $jazzquiz An object from the form in mod.html
 * @return int The id of the newly inserted jazzquiz record
 */
function jazzquiz_add_instance(stdClass $jazzquiz): int {
    global $DB;
    $jazzquiz->timemodified = time();
    $jazzquiz->timecreated = time();
    $jazzquiz->id = $DB->insert_record('jazzquiz', $jazzquiz);
    return $jazzquiz->id;
}

/**
 * Update JazzQuiz instance with new data.
 *
 * @param stdClass $jazzquiz An object from the form in mod.html
 * @return bool Success/Fail
 */
function jazzquiz_update_instance(stdClass $jazzquiz): bool {
    global $DB;
    $jazzquiz->timemodified = time();
    $jazzquiz->id = $jazzquiz->instance;
    $DB->update_record('jazzquiz', $jazzquiz);
    return true;
}

/**
 * Permanently delete a JazzQuiz instance and any data that depends on it.
 *
 * @param int $id Module instance ID
 * @return bool Success/Failure
 **/
function jazzquiz_delete_instance(int $id): bool {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/editlib.php');

    $jazzquiz = $DB->get_record('jazzquiz', ['id' => $id], '*', MUST_EXIST);
    // Go through each session and then delete them (also deletes all attempts for them).
    $sessions = $DB->get_records('jazzquiz_sessions', ['jazzquizid' => $jazzquiz->id]);
    foreach ($sessions as $session) {
        jazzquiz_session::delete($session->id);
    }
    $DB->delete_records('jazzquiz_questions', ['jazzquizid' => $jazzquiz->id]);
    $DB->delete_records('jazzquiz', ['id' => $jazzquiz->id]);
    return true;
}

/**
 * Function to be run periodically according to the moodle cron.
 *
 * @return bool
 */
function jazzquiz_cron(): bool {
    return true;
}

/**
 * This function extends the settings navigation block for the site.
 *
 * @param settings_navigation $settings
 * @param navigation_node $jazzquiznode
 * @return void
 */
function jazzquiz_extend_settings_navigation(settings_navigation $settings, navigation_node $jazzquiznode): void {
    global $PAGE, $CFG;

    // Included here as we only ever want to include this file if we really need to.
    require_once($CFG->libdir . '/questionlib.php');

    question_extend_settings_navigation($jazzquiznode, $PAGE->cm->context)->trim_if_empty();
}

/**
 * Plugin file callback for JazzQuiz.
 *
 * @param stdClass|int $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param mixed $forcedownload
 * @param array $options
 * @return bool
 */
function jazzquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []): bool {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    if ($filearea != 'question') {
        return false;
    }
    require_course_login($course, true, $cm);

    $questionid = (int)array_shift($args);
    $quiz = $DB->get_record('jazzquiz', ['id' => $cm->instance]);
    if (!$quiz) {
        return false;
    }
    $question = $DB->get_record('jazzquiz_question', [
        'id' => $questionid,
        'quizid' => $cm->instance,
    ]);
    if (!$question) {
        return false;
    }
    $fs = get_file_storage();
    $relative = implode('/', $args);
    $fullpath = "/$context->id/mod_jazzquiz/$filearea/$questionid/$relative";
    $file = $fs->get_file_by_hash(sha1($fullpath));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file);
    return false;
}

/**
 * Serve files belonging to a question in a question_attempt when that attempt is a quiz attempt.
 *
 * Called via pluginfile.php -> question_pluginfile.
 *
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @package mod_jazzquiz
 * @category files
 */
function mod_jazzquiz_question_pluginfile($course, $context, $component, $filearea, $qubaid, $slot,
                                          $args, $forcedownload, $options = []): void {
    $fs = get_file_storage();
    $relative = implode('/', $args);
    $full = "/$context->id/$component/$filearea/$relative";
    $file = $fs->get_file_by_hash(sha1($full));
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Check whether JazzQuiz supports a certain feature or not.
 *
 * @param string $feature
 * @return bool
 */
function jazzquiz_supports(string $feature): bool {
    return match ($feature) {
        FEATURE_MOD_INTRO,
        FEATURE_BACKUP_MOODLE2,
        FEATURE_SHOW_DESCRIPTION,
        FEATURE_USES_QUESTIONS => true,
        default => false,
    };
}
