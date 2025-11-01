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
 * @module    mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {Question} from 'mod_jazzquiz/question';

// Contains the needed values for using the ajax script.
let session = {
    courseModuleId: 0,
    sessionId: 0,
    attemptId: 0,
    sessionKey: ''
};

export class Quiz {

    constructor(Role) {
        this.state = '';
        this.isNewState = false;
        this.question = new Question(this);
        this.role = new Role(this);
        this.events = {
            notrunning: 'onNotRunning',
            preparing: 'onPreparing',
            running: 'onRunning',
            reviewing: 'onReviewing',
            sessionclosed: 'onSessionClosed',
            voting: 'onVoting'
        };
    }

    changeQuizState(data) {
        this.isNewState = (this.state !== data.state);
        this.state = data.state;
        if (this.role.onStateChange !== undefined) {
            this.role.onStateChange();
        }
        const event = this.events[data.state];
        this.role[event](data);
    }

    /**
     * Start polling for the current session state.
     * @param {number} ms interval in milliseconds
     */
    poll(ms) {
        Ajax.call({
            methodname: 'mod_jazzquiz_poll',
            args: {cmid: session.courseModuleId, sessionid: session.sessionId}
        })[0].done(data => {
            this.changeQuizState(data);
            setTimeout(() => this.poll(ms), ms);
        }).fail(Notification.exception);
    }

}

/**
 * Retrieve a language string that was sent along with the page.
 * @param {HTMLElement} element
 * @param {string} key Which string in the language file we want.
 * @param {string} [from=jazzquiz] Which language file we want the string from. Default is jazzquiz.
 * @param {array} [args=[]] This is {$a} in the string for the key.
 */
export async function setText(element, key, from, args) {
    from = (from !== undefined) ? from : 'jazzquiz';
    args = (args !== undefined) ? args : [];
    element.innerHTML = await getString(key, from, args);
    element.classList.remove('hidden');
}

/**
 * Initialize session data.
 *
 * @param {number} courseModuleId
 * @param {number} sessionId
 * @param {number} attemptId
 * @param {string} sessionKey
 */
export function initialize(courseModuleId, sessionId, attemptId, sessionKey) {
    session.courseModuleId = courseModuleId;
    session.sessionId = sessionId;
    session.attemptId = attemptId;
    session.sessionKey = sessionKey;
}
