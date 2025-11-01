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

import {Ajax, setText} from 'mod_jazzquiz/core';
import selectors from 'mod_jazzquiz/selectors';
import {renderAllMathjax} from 'mod_jazzquiz/math_rendering';

/**
 * Current question.
 *
 * @module    mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2024 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export class Question {

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

    /**
     * Request the current question form.
     */
    refresh() {
        Ajax.get('get_question_form', {}, data => {
            if (data.is_already_submitted) {
                setText(document.querySelector(selectors.quiz.info), 'wait_for_instructor');
                return;
            }
            const questionBox = document.querySelector(selectors.question.box);
            questionBox.innerHTML = data.html;
            questionBox.classList.remove('hidden');
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
            renderAllMathjax();
        });
    }

    /**
     * Hide the question "ending in" timer, and clears the interval.
     */
    hideTimer() {
        document.querySelector(selectors.question.timer).classList.add('hidden');
        clearInterval(this.timerInterval);
        this.timerInterval = 0;
    }

    /**
     * Is called for every second of the question countdown.
     * @param {number} questionTime in seconds
     */
    onCountdownTick(questionTime) {
        const quizInfo = document.querySelector(selectors.quiz.info);
        this.countdownTimeLeft--;
        if (this.countdownTimeLeft <= 0) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = 0;
            this.startAttempt(questionTime);
        } else if (this.countdownTimeLeft !== 0) {
            setText(quizInfo, 'question_will_start_in_x_seconds', 'jazzquiz', this.countdownTimeLeft);
        } else {
            setText(quizInfo, 'question_will_start_now');
        }
    }

    /**
     * Start a countdown for the question which will eventually start the question attempt.
     * The question attempt might start before this function return, depending on the arguments.
     * If a countdown has already been started, this call will return true and the current countdown will continue.
     * @param {number|string} questionTime
     * @param {number|string} countdownTimeLeft
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
        if (this.quiz.role.onTimerEnding !== undefined) {
            this.quiz.role.onTimerEnding();
        }
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
            const timeLeft = (this.endTime - currentTime) / 1000;
            this.quiz.role.onTimerTick(Math.round(timeLeft));
        }
    }

    /**
     * Request the current question from the server.
     * @param {number|string} questionTime
     */
    startAttempt(questionTime) {
        document.querySelector(selectors.quiz.info).classList.add('hidden');
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
        return document.querySelector(selectors.question.box).innerHTML.length > 0;
    }

}
