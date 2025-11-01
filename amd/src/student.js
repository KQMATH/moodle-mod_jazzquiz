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

import Ajax from 'core/ajax';
import {Quiz, setText} from 'mod_jazzquiz/core';
import selectors from 'mod_jazzquiz/selectors';
import {addMathjaxElement, renderMaximaEquation} from 'mod_jazzquiz/math_rendering';

class Student {
    /**
     * @param {Quiz} quiz
     */
    constructor(quiz) {
        this.quiz = quiz;
        this.voteAnswer = undefined;

        document.addEventListener('submit', event => {
            const questionForm = event.target.closest(selectors.question.form);
            if (questionForm) {
                event.preventDefault();
                this.submitAnswer();
            }
        });

        document.addEventListener('click', event => {
            const saveVoteButton = event.target.closest(selectors.quiz.saveVoteButton);
            if (saveVoteButton) {
                this.saveVote();
            }
            const selectVoteButton = event.target.closest(selectors.quiz.selectVoteButton);
            if (selectVoteButton) {
                this.voteAnswer = selectVoteButton.value;
            }
        });
    }

    onNotRunning() {
        setText(document.querySelector(selectors.quiz.info), 'instructions_for_student');
    }

    onPreparing() {
        setText(document.querySelector(selectors.quiz.info), 'wait_for_instructor');
    }

    onRunning(data) {
        if (this.quiz.question.isRunning) {
            return;
        }
        const started = this.quiz.question.startCountdown(data.questiontime, data.delay);
        if (!started) {
            setText(document.querySelector(selectors.quiz.info), 'wait_for_instructor');
        }
    }

    onReviewing() {
        this.quiz.question.isVoteRunning = false;
        this.quiz.question.isRunning = false;
        this.quiz.question.hideTimer();
        document.querySelector(selectors.question.box).classList.add('hidden');
        setText(document.querySelector(selectors.quiz.info), 'wait_for_instructor');
    }

    onSessionClosed() {
        window.location = location.href.split('&')[0];
    }

    onVoting(data) {
        if (this.quiz.question.isVoteRunning) {
            return;
        }
        const quizInfo = document.querySelector(selectors.quiz.info);
        quizInfo.innerHTML = data.html;
        quizInfo.classList.remove('hidden');
        quizInfo.querySelectorAll('.jazzquiz-select-vote-label').forEach(label => {
            const attemptSpan = label.querySelector('.jazzquiz-select-vote-attempt');
            addMathjaxElement(attemptSpan, attemptSpan.innerHTML);
            if (label.dataset.qtype === 'stack') {
                renderMaximaEquation(attemptSpan.innerHTML, attemptSpan.id);
            }
        });
        this.quiz.question.isVoteRunning = true;
    }

    onTimerTick(timeLeft) {
        setText(document.querySelector(selectors.question.timer), 'question_will_end_in_x_seconds', 'jazzquiz', timeLeft);
    }

    /**
     * Submit answer for the current question.
     */
    submitAnswer() {
        if (this.quiz.question.isSaving) {
            // Don't save twice.
            return;
        }
        this.quiz.question.isSaving = true;
        if (typeof tinyMCE !== 'undefined') {
            // eslint-disable-next-line no-undef
            tinyMCE.triggerSave();
        }
        const serialized = document.querySelector(selectors.question.form).serializeArray();
        let data = {};
        for (let name in serialized) {
            if (serialized.hasOwnProperty(name)) {
                data[serialized[name].name] = serialized[name].value;
            }
        }
        Ajax.post('save_question', data, data => {
            const quizInfo = document.querySelector(selectors.quiz.info);
            if (data.feedback.length > 0) {
                quizInfo.innerHTML = data.feedback;
                quizInfo.classList.remove('hidden');
            } else {
                setText(quizInfo, 'wait_for_instructor');
            }
            this.quiz.question.isSaving = false;
            if (!this.quiz.question.isRunning) {
                return;
            }
            if (this.quiz.question.isVoteRunning) {
                return;
            }
            document.querySelector(selectors.question.box).classList.add('hidden');
            this.quiz.question.hideTimer();
        }).fail(() => {
            this.quiz.question.isSaving = false;
        });
    }

    saveVote() {
        Ajax.post('save_vote', {vote: this.voteAnswer}, data => {
            const quizInfo = document.querySelector(selectors.quiz.info);
            if (data.status === 'success') {
                setText(quizInfo, 'wait_for_instructor');
            } else {
                setText(quizInfo, 'you_already_voted');
            }
        });
    }

}

/**
 * Initialize student session.
 */
export function initialize() {
    const quiz = new Quiz(Student);
    quiz.poll(2000);
}
