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
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/config', 'core/str', 'core/yui', 'core/event'], function($, mConfig, mString, Y, mEvent) {

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

    class Question {

        constructor(quiz) {
            this.quiz = quiz;
            this.isRunning = false;
            this.isSaving = false;
            this.endTime = 0;
            this.isVoteRunning = false;
            this.hasVotes = false;
            this.countdownTimeLeft = 0;
            this.questionTime = 0;
            this.countdownInterval = 0;
            this.timerInterval = 0;
        }

        static get box() {
            return $('#jazzquiz_question_box');
        }

        static get timer() {
            return $('#jazzquiz_question_timer');
        }

        static get form() {
            return $('#jazzquiz_question_form');
        }

        /**
         * Request the current question form.
         */
        refresh() {
            Ajax.get('get_question_form', {}, data => {
                if (data.is_already_submitted) {
                    setText(Quiz.info, 'wait_for_instructor');
                    return;
                }
                Quiz.show(Question.box.html(data.html));
                // eslint-disable-next-line no-eval
                eval(data.js);
                data.css.forEach(cssUrl => {
                    let head = document.getElementsByTagName('head')[0];
                    let style = document.createElement('link');
                    style.rel = 'stylesheet';
                    style.type = 'text/css';
                    style.href = cssUrl;
                    head.appendChild(style);
                });
                if (this.quiz.role.onQuestionRefreshed !== undefined) {
                    this.quiz.role.onQuestionRefreshed(data);
                }
                Quiz.renderAllMathjax();
            });
        }

        /**
         * Hide the question "ending in" timer, and clears the interval.
         */
        hideTimer() {
            Quiz.hide(Question.timer);
            clearInterval(this.timerInterval);
            this.timerInterval = 0;
        }

        /**
         * Is called for every second of the question countdown.
         * @param {number} questionTime in seconds
         */
        onCountdownTick(questionTime) {
            this.countdownTimeLeft--;
            if (this.countdownTimeLeft <= 0) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = 0;
                this.startAttempt(questionTime);
            } else if (this.countdownTimeLeft !== 0) {
                setText(Quiz.info, 'question_will_start_in_x_seconds', 'jazzquiz', this.countdownTimeLeft);
            } else {
                setText(Quiz.info, 'question_will_start_now');
            }
        }

        /**
         * Start a countdown for the question which will eventually start the question attempt.
         * The question attempt might start before this function return, depending on the arguments.
         * If a countdown has already been started, this call will return true and the current countdown will continue.
         * @param {number} questionTime
         * @param {number} countdownTimeLeft
         * @return {boolean} true if countdown is active
         */
        startCountdown(questionTime, countdownTimeLeft) {
            if (this.countdownInterval !== 0) {
                return true;
            }
            questionTime = parseInt(questionTime);
            countdownTimeLeft = parseInt(countdownTimeLeft);
            this.countdownTimeLeft = countdownTimeLeft;
            if (countdownTimeLeft < 1) {
                // Check if the question has already ended.
                if (questionTime > 0 && countdownTimeLeft < -questionTime) {
                    return false;
                }
                // No need to start the countdown. Just start the question.
                if (questionTime > 1) {
                    this.startAttempt(questionTime + countdownTimeLeft);
                } else {
                    this.startAttempt(0);
                }
                return true;
            }
            this.countdownInterval = setInterval(() => this.onCountdownTick(questionTime), 1000);
            return true;
        }

        /**
         * When the question "ending in" timer reaches 0 seconds, this will be called.
         */
        onTimerEnding() {
            this.isRunning = false;
            this.quiz.role.onTimerEnding();
        }

        /**
         * Is called for every second of the "ending in" timer.
         */
        onTimerTick() {
            const currentTime = new Date().getTime();
            if (currentTime > this.endTime) {
                this.hideTimer();
                this.onTimerEnding();
            } else {
                const timeLeft = parseInt((this.endTime - currentTime) / 1000);
                this.quiz.role.onTimerTick(timeLeft);
            }
        }

        /**
         * Request the current question from the server.
         * @param {number} questionTime
         */
        startAttempt(questionTime) {
            Quiz.hide(Quiz.info);
            this.refresh();
            // Set this to true so that we don't keep calling this over and over.
            this.isRunning = true;
            questionTime = parseInt(questionTime);
            if (questionTime === 0) {
                // 0 means no timer.
                return;
            }
            this.quiz.role.onTimerTick(questionTime); // TODO: Is it worth having this line?
            this.endTime = new Date().getTime() + questionTime * 1000;
            this.timerInterval = setInterval(() => this.onTimerTick(), 1000);
        }

        static isLoaded() {
            return Question.box.html() !== '';
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
            return $('#jazzquiz');
        }

        static get info() {
            return $('#jazzquiz_info_container');
        }

        static get responded() {
            return $('#jazzquiz_responded_container');
        }

        static get responses() {
            return $('#jazzquiz_responses_container');
        }

        static get responseInfo() {
            return $('#jazzquiz_response_info_container');
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
    function setText($element, key, from, args) {
        from = (from !== undefined) ? from : 'jazzquiz';
        args = (args !== undefined) ? args : [];
        $.when(mString.get_string(key, from, args)).done(text => Quiz.show($element.html(text)));
    }

    return {
        initialize: (courseModuleId, activityId, sessionId, attemptId, sessionKey) => {
            session.courseModuleId = courseModuleId;
            session.activityId = activityId;
            session.sessionId = sessionId;
            session.attemptId = attemptId;
            session.sessionKey = sessionKey;
        },
        Quiz: Quiz,
        Question: Question,
        Ajax: Ajax,
        setText: setText
    };

});
