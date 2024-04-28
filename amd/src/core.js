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

import $ from 'jquery';
import mString from 'core/str';
import mEvent from 'core_filters/events';
import selectors from 'mod_jazzquiz/selectors';
import Question from "mod_jazzquiz/question";

// Contains the needed values for using the ajax script.
let session = {
    courseModuleId: 0,
    activityId: 0, // TODO: Remove activityId? Unsure if used.
    sessionId: 0,
    attemptId: 0,
    sessionKey: ''
};

// Used for caching the latex of maxima input.
let cache = [];

// TODO: Migrate to core/ajax module?
class Ajax {

    /**
     * Send a request using AJAX, with method specified.
     *
     * @param {string} method Which HTTP method to use.
     * @param {string} url Relative to root of jazzquiz module. Does not start with /.
     * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
     * @param {function} success Callback function for when the request was completed successfully.
     * @return {jqXHR} The jQuery XHR object
     */
    static request(method, url, data, success) {
        data.id = session.courseModuleId;
        data.sessionid = session.sessionId;
        data.attemptid = session.attemptId;
        data.sesskey = session.sessionKey;
        return $.ajax({
            type: method,
            url: url,
            data: data,
            dataType: 'json',
            success: success
        }).fail(() => setText(Quiz.info, 'error_with_request'));
    }

    /**
     * Send a GET request using AJAX.
     * @param {string} action Which action to query.
     * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
     * @param {function} success Callback function for when the request was completed successfully.
     * @return {jqXHR} The jQuery XHR object
     */
    static get(action, data, success) {
        data.action = action;
        return Ajax.request('get', 'ajax.php', data, success);
    }

    /**
     * Send a POST request using AJAX.
     * @param {string} action Which action to query.
     * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
     * @param {function} success Callback function for when the request was completed successfully.
     * @return {jqXHR} The jQuery XHR object
     */
    static post(action, data, success) {
        data.action = action;
        return Ajax.request('post', 'ajax.php', data, success);
    }

}

class Quiz {

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

    changeQuizState(state, data) {
        this.isNewState = (this.state !== state);
        this.state = state;
        if (this.role.onStateChange !== undefined) {
            this.role.onStateChange();
        }
        const event = this.events[state];
        this.role[event](data);
    }

    /**
     * Initiate the chained session info calls to ajax.php
     * @param {number} ms interval in milliseconds
     */
    poll(ms) {
        Ajax.get('info', {}, data => {
            this.changeQuizState(data.status, data);
            setTimeout(() => this.poll(ms), ms);
        });
    }

    static get main() {
        return document.querySelector(selectors.main);
    }

    static get info() {
        return document.querySelector(selectors.quiz.info);
    }

    static get responded() {
        return document.querySelector(selectors.quiz.responded);
    }

    static get responses() {
        return document.querySelector(selectors.quiz.responses);
    }

    static get responseInfo() {
        return document.querySelector(selectors.quiz.responseInfo);
    }

    static hide($element) {
        $element.addClass('hidden');
    }

    static show($element) {
        $element.removeClass('hidden');
    }

    static uncheck($element) {
        $element.children('.fa').removeClass('fa-check-square-o').addClass('fa-square-o');
    }

    static check($element) {
        $element.children('.fa').removeClass('fa-square-o').addClass('fa-check-square-o');
    }

    /**
     * Triggers a dynamic content update event, which MathJax listens to.
     */
    static renderAllMathjax() {
        mEvent.notifyFilterContentUpdated(document.getElementsByClassName('jazzquiz-response-container'));
    }

    /**
     * Sets the body of the target, and triggers an event letting MathJax know about the element.
     * @param {*} $target
     * @param {string} latex
     */
    static addMathjaxElement($target, latex) {
        $target.html('<span class="filter_mathjaxloader_equation">' + latex + '</span>');
        Quiz.renderAllMathjax();
    }

    /**
     * Converts the input to LaTeX and renders it to the target with MathJax.
     * @param {string} input
     * @param {string} targetId
     */
    static renderMaximaEquation(input, targetId) {
        const target = document.getElementById(targetId);
        if (target === null) {
            // Log error to console: 'Target element #' + targetId + ' not found.'.
            return;
        }
        if (cache[input] !== undefined) {
            Quiz.addMathjaxElement($('#' + targetId), cache[input]);
            return;
        }
        Ajax.get('stack', {input: encodeURIComponent(input)}, data => {
            cache[data.original] = data.latex;
            Quiz.addMathjaxElement($('#' + targetId), data.latex);
        });
    }

}

/**
 * Retrieve a language string that was sent along with the page.
 * @param {*} $element
 * @param {string} key Which string in the language file we want.
 * @param {string} [from=jazzquiz] Which language file we want the string from. Default is jazzquiz.
 * @param {array} [args=[]] This is {$a} in the string for the key.
 */
export function setText($element, key, from, args) {
    from = (from !== undefined) ? from : 'jazzquiz';
    args = (args !== undefined) ? args : [];
    $.when(mString.get_string(key, from, args))
        .done(text => Quiz.show($element.html(text)));
}

/**
 * Initialize session data.
 *
 * @param {number} courseModuleId
 * @param {number} activityId
 * @param {number} sessionId
 * @param {number} attemptId
 * @param {string} sessionKey
 */
export function initialize(courseModuleId, activityId, sessionId, attemptId, sessionKey) {
    session.courseModuleId = courseModuleId;
    session.activityId = activityId;
    session.sessionId = sessionId;
    session.attemptId = attemptId;
    session.sessionKey = sessionKey;
}
