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
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Get possible vote options, given that the session is currently running a vote.
 */
class get_vote_options extends external_api {

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
     * Get the vote options.
     *
     * @param int $cmid Course module id
     * @param int $sessionid Session id
     */
    public static function execute(int $cmid, int $sessionid): array {
        global $DB;
        self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'sessionid' => $sessionid]);
        self::validate_context(context_module::instance($cmid));
        $session = new jazzquiz_session(null, $sessionid);
        if (!$session->sessionopen) {
            throw new moodle_exception('Session is closed.');
        }
        $session->load_my_attempt();
        if (!$session->myattempt || !$session->myattempt->is_active()) {
            throw new moodle_exception('Session is closed.');
        }
        if ($session->status !== jazzquiz_session::STATUS_VOTING) {
            return ['html' => ''];
        }
        $options = [];
        $i = 0;
        foreach ($DB->get_records('jazzquiz_votes', ['sessionid' => $session->id]) as $voteoption) {
            $options[] = [
                'index' => $i,
                'voteid' => $voteoption->id,
                'qtype' => $voteoption->qtype,
                'text' => $voteoption->attempt,
            ];
            $i++;
        }
        $jazzquiz = new jazzquiz($cmid);
        return ['html' => $jazzquiz->renderer->render_from_template('jazzquiz/vote', ['options' => $options])];
    }

    /**
     * Describe what we return.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_TEXT, 'HTML for vote options', VALUE_REQUIRED),
        ]);
    }

}
