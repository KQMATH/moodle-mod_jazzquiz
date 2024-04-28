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

declare(strict_types=1);

namespace mod_jazzquiz\external;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_jazzquiz\jazzquiz;
use mod_jazzquiz\jazzquiz_session;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Poll session.
 */
class poll extends external_api {

    /**
     * Get the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
        ]);
    }

    /**
     * Poll session.
     *
     * @param int $cmid Course module id
     * @param int $sessionid Session id
     */
    public static function execute(int $cmid, int $sessionid): array {
        global $DB;
        self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'sessionid' => $sessionid]);
        self::validate_context(context_module::instance($cmid));
        $jazzquiz = new jazzquiz($cmid);
        $session = new jazzquiz_session(null, $sessionid);
        if (!$session->sessionopen) {
            return ['status' => jazzquiz_session::STATUS_SESSIONCLOSED];
        }
        $session->load_my_attempt();
        if (!$session->myattempt || !$session->myattempt->is_active()) {
            return ['status' => jazzquiz_session::STATUS_SESSIONCLOSED];
        }
        switch ($session->status) {
            // Just a generic response with the state.
            case jazzquiz_session::STATUS_NOTRUNNING:
                if ($jazzquiz->is_instructor()) {
                    $session = new jazzquiz_session(null, $session->id);
                    $session->load_attempts();
                    return ['status' => $session->status, 'student_count' => $session->get_student_count()];
                } else {
                    return ['status' => $session->status];
                }

            case jazzquiz_session::STATUS_PREPARING:
            case jazzquiz_session::STATUS_REVIEWING:
                return ['status' => $session->status, 'slot' => $session->slot];

            case jazzquiz_session::STATUS_VOTING:
                return ['status' => $session->status];

            // Send the currently active question.
            case jazzquiz_session::STATUS_RUNNING:
                return [
                    'status' => $session->status,
                    'questiontime' => $session->currentquestiontime,
                    'delay' => $session->nextstarttime - time(),
                ];

            // This should not be reached, but if it ever is, let's just assume the quiz is not running.
            default:
                return ['status' => jazzquiz_session::STATUS_NOTRUNNING];
        }
    }

    /**
     * Describe what we return.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Current session status', VALUE_REQUIRED),
        ]);
    }
}
