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

use stdClass;

/**
 * A vote.
 *
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_vote {

    /** @var int $sessionid */
    protected int $sessionid;

    /** @var int $slot */
    protected int $slot;

    /**
     * Constructor.
     *
     * @param int $sessionid
     * @param int $slot
     */
    public function __construct(int $sessionid, int $slot = 0) {
        $this->sessionid = $sessionid;
        $this->slot = $slot;
    }

    /**
     * Get the results for the vote.
     *
     * @return array
     */
    public function get_results(): array {
        global $DB;
        return $DB->get_records('jazzquiz_votes', [
            'sessionid' => $this->sessionid,
            'slot' => $this->slot,
        ]);
    }

    /**
     * Check whether a user has voted or not.
     *
     * @return bool
     */
    public function has_user_voted(): bool {
        global $DB, $USER;
        $allvotes = $DB->get_records('jazzquiz_votes', ['sessionid' => $this->sessionid]);
        if (!$allvotes) {
            return false;
        }
        foreach ($allvotes as $vote) {
            $usersvoted = explode(',', $vote->userlist);
            if (!$usersvoted) {
                continue;
            }
            foreach ($usersvoted as $uservoted) {
                if ($uservoted == $USER->id || $uservoted == $USER->sesskey) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Save the vote for a user.
     *
     * @param int $voteid
     * @return bool
     */
    public function save_vote(int $voteid): bool {
        global $DB, $USER;
        if ($this->has_user_voted()) {
            return false;
        }
        $exists = $DB->record_exists('jazzquiz_votes', ['id' => $voteid]);
        if (!$exists) {
            return false;
        }
        $row = $DB->get_record('jazzquiz_votes', ['id' => $voteid]);
        if (!$row) {
            return false;
        }
        // Likely an honest vote.
        $row->finalcount++;
        if ($row->userlist != '') {
            $row->userlist .= ',';
        }
        $row->userlist .= isguestuser($USER) ? $USER->seskey : $USER->id;
        $DB->update_record('jazzquiz_votes', $row);
        return true;
    }

    /**
     * Insert the options for the vote.
     *
     * @param int $jazzquizid
     * @param string $qtype
     * @param array $options
     * @param int $slot
     */
    public function prepare_options(int $jazzquizid, string $qtype, array $options, int $slot): void {
        global $DB;

        // Delete previous voting options for this session.
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $this->sessionid]);

        // Add to database.
        foreach ($options as $option) {
            $vote = new stdClass();
            $vote->jazzquizid = $jazzquizid;
            $vote->sessionid = $this->sessionid;
            $vote->attempt = $option['text'];
            $vote->initialcount = $option['count'];
            $vote->finalcount = 0;
            $vote->userlist = '';
            $vote->qtype = $qtype;
            $vote->slot = $slot;
            $DB->insert_record('jazzquiz_votes', $vote);
        }
    }

}
