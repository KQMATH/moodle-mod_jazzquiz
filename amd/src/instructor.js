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
import {Quiz, setText} from 'mod_jazzquiz/core';
import {Question} from 'mod_jazzquiz/question';
import selectors from 'mod_jazzquiz/selectors';
import {addMathjaxElement, renderMaximaEquation, renderAllMathjax} from 'mod_jazzquiz/math_rendering';

class ResponseView {

    /**
     * @param {Quiz} quiz
     */
    constructor(quiz) {
        this.quiz = quiz;
        this.currentResponses = [];
        this.showVotesUponReview = false;
        this.respondedCount = 0;
        this.showResponses = false;
        this.totalStudents = 0;

        document.addEventListener('click', event => {
            const undoMergeButton = event.target.closest(selectors.quiz.undoMergeButton);
            if (undoMergeButton) {
                this.undoMerge();
            }

            const showNormalResultsButton = event.target.closest(selectors.quiz.showNormalResultsButton);
            if (showNormalResultsButton) {
                this.refresh(false);
            }

            const showVoteResultsButton = event.target.closest(selectors.quiz.showVoteResultsButton);
            if (showVoteResultsButton) {
                this.refreshVotes();
            }

            // Clicking a row to merge.
            if (event.target.classList.contains('bar')) {
                this.startMerge(event.target.id);
            } else if (event.target.parentNode && event.target.parentNode.classList.contains('bar')) {
                this.startMerge(event.target.parentNode.id);
            }
        });
    }

    /**
     * Clear, but not hide responses.
     */
    clear() {
        document.querySelector(selectors.quiz.responses).innerHTML = '';
        document.querySelector(selectors.quiz.responseInfo).innerHTML = '';
    }

    /**
     * Hides the responses
     */
    hide() {
        Instructor.control('responses').querySelector('.fa').classList.replace('fa-check-square-o', 'fa-square-o');
        document.querySelector(selectors.quiz.responses).classList.add('hidden');
        document.querySelector(selectors.quiz.responseInfo).classList.add('hidden');
    }

    /**
     * Shows the responses
     */
    show() {
        Instructor.control('responses').querySelector('.fa').classList.replace('fa-square-o', 'fa-check-square-o');
        document.querySelector(selectors.quiz.responses).classList.remove('hidden');
        document.querySelector(selectors.quiz.responseInfo).classList.remove('hidden');
        if (this.showVotesUponReview) {
            this.refreshVotes();
            this.showVotesUponReview = false;
        } else {
            this.refresh(false);
        }
    }

    /**
     * Toggle whether to show or hide the responses
     */
    toggle() {
        this.showResponses = !this.showResponses;
        if (this.showResponses) {
            this.show();
        } else {
            this.hide();
        }
    }

    /**
     * End the response merge.
     */
    endMerge() {
        document.querySelectorAll(selectors.quiz.mergeInto).forEach(element => element.classList.remove('merge-into'));
        document.querySelectorAll(selectors.quiz.mergeFrom).forEach(element => element.classList.remove('merge-from'));
    }

    /**
     * Undo the last response merge.
     */
    undoMerge() {
        Ajax.post('undo_merge', {}, () => this.refresh(true));
    }

    /**
     * Merges responses based on response string.
     * @param {string} from
     * @param {string} into
     */
    merge(from, into) {
        Ajax.post('merge_responses', {from: from, into: into}, () => this.refresh(false));
    }

    /**
     * Start a merge between two responses.
     * @param {string} fromRowBarId
     */
    startMerge(fromRowBarId) {
        const barCell = document.getElementById(fromRowBarId);
        const row = barCell.parentElement;
        if (row.classList.contains('merge-from')) {
            this.endMerge();
            return;
        }
        if (row.classList.contains('merge-into')) {
            const fromRow = document.querySelector(selectors.quiz.mergeFrom);
            this.merge(fromRow.dataset.response, row.dataset.response);
            this.endMerge();
            return;
        }
        row.classList.add('merge-from');
        row.closest('table').querySelectorAll('tr').forEach(tableRow => {
            const cell = tableRow.querySelector('td:nth-child(2)');
            if (cell && cell.id !== barCell.id) {
                tableRow.classList.add('merge-into');
            }
        });
    }

    /**
     * Create controls to toggle between the responses of the actual question and the vote that followed.
     * @param {string} name Can be either 'vote_response' or 'current_response'
     */
    createControls(name) {
        const quizResponseInfo = document.querySelector(selectors.quiz.responseInfo);
        if (!this.quiz.question.hasVotes) {
            quizResponseInfo.classList.add('hidden');
            return;
        }
        // Add button for instructor to change what to review.
        if (this.quiz.state === 'reviewing') {
            let showNormalResult = document.querySelector(selectors.quiz.showNormalResultsButton);
            let showVoteResult = document.querySelector(selectors.quiz.showVoteResultsButton);
            quizResponseInfo.classList.remove('hidden');
            if (name === 'vote_response') {
                if (!showNormalResult) {
                    setText(quizResponseInfo.html('<h4 class="inline"></h4>').children('h4'), 'showing_vote_results');
                    quizResponseInfo.innerHTML += '<button id="review_show_normal_results" class="btn btn-primary"></button><br>';
                    showNormalResult = document.querySelector(selectors.quiz.showNormalResultsButton);
                    setText(showNormalResult, 'click_to_show_original_results');
                    showVoteResult.remove();
                }
            } else if (name === 'current_response') {
                if (!showVoteResult) {
                    quizResponseInfo.innerHTML = '<h4 class="inline"></h4>';
                    setText(quizResponseInfo.querySelector('h4'), 'showing_original_results');
                    quizResponseInfo.innerHTML += '<button id="review_show_vote_results" class="btn btn-primary"></button><br>';
                    showVoteResult = document.querySelector(selectors.quiz.showVoteResultsButton);
                    setText(showVoteResult, 'click_to_show_vote_results');
                    showNormalResult.remove();
                }
            }
        }
    }

    addBarGraphRow(target, name, response, i, highestResponseCount) {
        // Const percent = (parseInt(responses[i].count) / total) * 100;
        const percent = (parseInt(response.count) / highestResponseCount) * 100;

        // Check if row with same response already exists.
        let rowIndex = -1;
        let currentRowIndex = -1;
        for (let j = 0; j < target.rows.length; j++) {
            if (target.rows[j].dataset.response === response.response) {
                rowIndex = parseInt(target.rows[j].dataset.rowIndex);
                currentRowIndex = j;
                break;
            }
        }

        if (rowIndex === -1) {
            rowIndex = target.rows.length;
            let row = target.insertRow();
            row.dataset.responseIndex = i;
            row.dataset.response = response.response;
            row.dataset.percent = percent;
            row.dataset.rowIndex = rowIndex;
            row.dataset.count = response.count;
            row.classList.add('selected-vote-option');
            if (percent < 15) {
                row.classList.add('outside');
            }

            const countHtml = '<span id="' + name + '_count_' + rowIndex + '">' + response.count + '</span>';
            let responseCell = row.insertCell(0);
            responseCell.onclick = () => responseCell.parentElement.classList.toggle('selected-vote-option');

            let barCell = row.insertCell(1);
            barCell.classList.add('bar');
            barCell.id = name + '_bar_' + rowIndex;
            barCell.innerHTML = '<div style="width:' + percent + '%;">' + countHtml + '</div>';

            const latexId = name + '_latex_' + rowIndex;
            responseCell.innerHTML = '<span id="' + latexId + '"></span>';
            addMathjaxElement(document.getElementById(latexId), response.response);
            if (response.qtype === 'stack') {
                renderMaximaEquation(response.response, latexId);
            }
        } else {
            let currentRow = target.rows[currentRowIndex];
            currentRow.dataset.rowIndex = rowIndex;
            currentRow.dataset.responseIndex = i;
            currentRow.dataset.percent = percent;
            currentRow.dataset.count = response.count;
            const containsOutside = currentRow.classList.contains('outside');
            if (percent > 15 && containsOutside) {
                currentRow.classList.remove('outside');
            } else if (percent < 15 && !containsOutside) {
                currentRow.classList.add('outside');
            }
            let countElement = document.getElementById(name + '_count_' + rowIndex);
            if (countElement !== null) {
                countElement.innerHTML = response.count;
            }
            let barElement = document.getElementById(name + '_bar_' + rowIndex);
            if (barElement !== null) {
                barElement.firstElementChild.style.width = percent + '%';
            }
        }
    }

    /**
     * Create a new and unsorted response bar graph.
     *
     * @param {Array.<Object>} responses
     * @param {string} name
     * @param {string} targetId
     * @param {string} graphId
     * @param {boolean} rebuild If the table should be completely rebuilt or not
     */
    createBarGraph(responses, name, targetId, graphId, rebuild) {
        let target = document.getElementById(targetId);
        if (target === null) {
            return;
        }
        let highestResponseCount = 0;
        for (let i = 0; i < responses.length; i++) {
            let count = parseInt(responses[i].count); // In case count is a string.
            if (count > highestResponseCount) {
                highestResponseCount = count;
            }
        }

        // Remove the rows if it should be rebuilt.
        if (rebuild) {
            target.innerHTML = '';
        }

        // Prune rows.
        for (let i = 0; i < target.rows.length; i++) {
            let prune = true;
            for (let j = 0; j < responses.length; j++) {
                if (target.rows[i].dataset.response === responses[j].response) {
                    prune = false;
                    break;
                }
            }
            if (prune) {
                target.deleteRow(i);
                i--;
            }
        }

        // Add rows.
        this.createControls(name);
        name += graphId;
        for (let i = 0; i < responses.length; i++) {
            this.addBarGraphRow(target, name, responses[i], i, highestResponseCount);
        }
    }

    /**
     * Sort the responses in the graph by how many had the same response.
     * @param {string} targetId
     */
    static sortBarGraph(targetId) {
        let target = document.getElementById(targetId);
        if (target === null) {
            return;
        }
        let isSorting = true;
        while (isSorting) {
            isSorting = false;
            for (let i = 0; i < (target.rows.length - 1); i++) {
                const current = parseInt(target.rows[i].dataset.percent);
                const next = parseInt(target.rows[i + 1].dataset.percent);
                if (current < next) {
                    target.rows[i].parentNode.insertBefore(target.rows[i + 1], target.rows[i]);
                    isSorting = true;
                    break;
                }
            }
        }
    }

    /**
     * Create and sort a bar graph based on the responses passed.
     * @param {string} wrapperId
     * @param {string} tableId
     * @param {Array.<Object>} responses
     * @param {number|undefined} responded How many students responded to the question
     * @param {string} questionType
     * @param {string} graphId
     * @param {boolean} rebuild If the graph should be rebuilt or not.
     */
    set(wrapperId, tableId, responses, responded, questionType, graphId, rebuild) {
        if (responses === undefined) {
            return;
        }
        const quizResponded = document.querySelector(selectors.quiz.responded);

        // Check if any responses to show.
        if (responses.length === 0) {
            quizResponded.classList.remove('hidden');
            setText(quizResponded.querySelector('h4'), 'a_out_of_b_responded', 'jazzquiz', {
                a: 0,
                b: this.totalStudents
            });
            return;
        }

        // Question type specific.
        switch (questionType) {
            case 'shortanswer':
                for (let i = 0; i < responses.length; i++) {
                    responses[i].response = responses[i].response.trim();
                }
                break;
            case 'stack':
                // Remove all spaces from responses.
                for (let i = 0; i < responses.length; i++) {
                    responses[i].response = responses[i].response.replace(/\s/g, '');
                }
                break;
            default:
                break;
        }

        // Update data.
        this.currentResponses = [];
        this.respondedCount = 0;
        for (let i = 0; i < responses.length; i++) {
            let exists = false;
            let count = 1;
            if (responses[i].count !== undefined) {
                count = parseInt(responses[i].count);
            }
            this.respondedCount += count;
            // Check if response is a duplicate.
            for (let j = 0; j < this.currentResponses.length; j++) {
                if (this.currentResponses[j].response === responses[i].response) {
                    this.currentResponses[j].count += count;
                    exists = true;
                    break;
                }
            }
            // Add element if not a duplicate.
            if (!exists) {
                this.currentResponses.push({
                    response: responses[i].response,
                    count: count,
                    qtype: questionType
                });
            }
        }

        // Update responded container.
        if (quizResponded.length !== 0 && responded !== undefined) {
            quizResponded.classList.remove('hidden');
            setText(quizResponded.querySelector('h4'), 'a_out_of_b_responded', 'jazzquiz', {
                a: responded,
                b: this.totalStudents
            });
        }

        // Should we show the responses?
        if (!this.showResponses && this.quiz.state !== 'reviewing') {
            document.querySelector(selectors.quiz.responseInfo).classList.add('hidden');
            document.querySelector(selectors.quiz.responses).classList.add('hidden');
            return;
        }

        if (document.getElementById(tableId) === null) {
            const wrapper = document.getElementById(wrapperId);
            wrapper.innerHTML = `<table id="${tableId}" class="jazzquiz-responses-overview"></table>`;
            wrapper.classList.remove('hidden');
        }
        this.createBarGraph(this.currentResponses, 'current_response', tableId, graphId, rebuild);
        ResponseView.sortBarGraph(tableId);
    }

    /**
     * Fetch and show results for the ongoing or previous question.
     * @param {boolean} rebuild If the response graph should be rebuilt or not.
     */
    refresh(rebuild) {
        Ajax.get('get_results', {}, data => {
            this.quiz.question.hasVotes = data.has_votes;
            this.totalStudents = parseInt(data.total_students);

            this.set('jazzquiz_responses_container', 'current_responses_wrapper',
                data.responses, data.responded, data.question_type, 'results', rebuild);

            const undoMergeButton = document.querySelector(selectors.quiz.undoMergeButton);
            if (undoMergeButton) {
                undoMergeButton.classList.toggle('hidden', data.merge_count <= 0);
            }
        });
    }

    /**
     * Method refresh() equivalent for votes.
     */
    refreshVotes() {
        const quizResponses = document.querySelector(selectors.quiz.responses);
        const quizResponseInfo = document.querySelector(selectors.quiz.responseInfo);
        // Should we show the results?
        if (!this.showResponses && this.quiz.state !== 'reviewing') {
            quizResponseInfo.classList.add('hidden');
            quizResponses.classList.add('hidden');
            return;
        }
        Ajax.get('get_vote_results', {}, data => {
            const answers = data.answers;
            const targetId = 'wrapper_vote_responses';
            let responses = [];
            this.respondedCount = 0;
            this.totalStudents = parseInt(data.total_students);
            for (let i in answers) {
                if (!answers.hasOwnProperty(i)) {
                    continue;
                }
                responses.push({
                    response: answers[i].attempt,
                    count: answers[i].finalcount,
                    qtype: answers[i].qtype,
                    slot: answers[i].slot
                });
                this.respondedCount += parseInt(answers[i].finalcount);
            }
            setText(document.querySelector(selectors.quiz.responded + ' h4'), 'a_out_of_b_voted', 'jazzquiz', {
                a: this.respondedCount,
                b: this.totalStudents
            });
            if (document.getElementById(targetId) === null) {
                quizResponses.innerHTML = `<table id="${targetId}" class="jazzquiz-responses-overview"></table>`;
                quizResponses.classList.remove('hidden');
            }
            this.createBarGraph(responses, 'vote_response', targetId, 'vote', false);
            ResponseView.sortBarGraph(targetId);
        });
    }

}

class Instructor {

    /**
     * @param {Quiz} quiz
     */
    constructor(quiz) {
        this.quiz = quiz;
        this.responses = new ResponseView(quiz);
        this.isShowingCorrectAnswer = false;
        this.totalQuestions = 0;
        this.allowVote = false;

        document.addEventListener('keyup', event => {
            if (event.key === 'Escape') {
                Instructor.closeFullscreenView();
            }
        });

        Instructor.addEvents({
            'repoll': () => this.repollQuestion(),
            'vote': () => this.runVoting(),
            'improvise': () => this.showQuestionListSetup('improvise'),
            'jump': () => this.showQuestionListSetup('jump'),
            'next': () => this.nextQuestion(),
            'random': () => this.randomQuestion(),
            'end': () => this.endQuestion(),
            'fullscreen': () => Instructor.showFullscreenView(),
            'answer': () => this.showCorrectAnswer(),
            'responses': () => this.responses.toggle(),
            'exit': () => this.closeSession(),
            'quit': () => this.closeSession(),
            'startquiz': () => this.startQuiz()
        });

        Instructor.addHotkeys({
            't': 'responses',
            'r': 'repoll',
            'a': 'answer',
            'e': 'end',
            'j': 'jump',
            'i': 'improvise',
            'v': 'vote',
            'n': 'next',
            'm': 'random',
            'f': 'fullscreen'
        });

        document.addEventListener('click', event => {
            Instructor.closeQuestionListMenu(event, 'improvise');
            Instructor.closeQuestionListMenu(event, 'jump');
            if (event.target.closest(selectors.quiz.startQuizButton)) {
                this.startQuiz();
            }
            if (event.target.closest(selectors.quiz.exitQuizButton)) {
                this.closeSession();
            }
        });
    }

    static addHotkeys(keys) {
        for (let key in keys) {
            if (keys.hasOwnProperty(key)) {
                keys[key] = {
                    action: keys[key],
                    repeat: false // TODO: Maybe event.repeat becomes more standard?
                };

                document.addEventListener('keydown', event => {
                    if (keys[key].repeat || event.ctrlKey) {
                        return;
                    }
                    if (event.key.toLowerCase() !== key) {
                        return;
                    }
                    let focusedTag = $(':focus').prop('tagName');
                    if (focusedTag !== undefined) {
                        focusedTag = focusedTag.toLowerCase();
                        if (focusedTag === 'input' || focusedTag === 'textarea') {
                            return;
                        }
                    }
                    event.preventDefault();
                    keys[key].repeat = true;
                    let $control = Instructor.control(keys[key].action);
                    if ($control.length && !$control.prop('disabled')) {
                        $control.click();
                    }
                });

                document.addEventListener('keyup', event => {
                    if (event.key.toLowerCase() === key) {
                        keys[key].repeat = false;
                    }
                });
            }
        }
    }

    static addEvents(events) {
        document.addEventListener('click', event => {
            const controlButton = event.target.closest(selectors.quiz.controlButton);
            if (controlButton) {
                Instructor.enableControls([]);
                events[controlButton.dataset.control]();
            }
        });
    }

    static get controls() {
        return document.querySelector(selectors.quiz.controlsBox);
    }

    static get controlButtons() {
        return document.querySelector(selectors.quiz.controlButtons);
    }

    static control(key) {
        return document.getElementById(`jazzquiz_control_${key}`);
    }

    static get side() {
        return document.querySelector(selectors.quiz.sideContainer);
    }

    static get correctAnswer() {
        return document.querySelector(selectors.quiz.correctAnswerContainer);
    }

    static get isMerging() {
        return $('.merge-from').length !== 0;
    }

    onNotRunning(data) {
        this.responses.totalStudents = data.student_count;
        Instructor.side.classList.add('hidden');
        setText(document.querySelector(selectors.quiz.info), 'instructions_for_instructor');
        Instructor.enableControls([]);
        document.querySelector(selectors.quiz.controlButtons).classList.add('hidden');
        const studentsJoined = document.querySelector(selectors.quiz.studentsJoinedCounter);
        if (data.student_count === 1) {
            setText(studentsJoined, 'one_student_has_joined');
        } else if (data.student_count > 1) {
            setText(studentsJoined, 'x_students_have_joined', 'jazzquiz', data.student_count);
        } else {
            setText(studentsJoined, 'no_students_have_joined');
        }
        const startQuizButton = Instructor.control('startquiz');
        startQuizButton.parentElement.classList.remove('hidden');
    }

    onPreparing(data) {
        Instructor.side.classList.add('hidden');
        setText(document.querySelector(selectors.quiz.info), 'instructions_for_instructor');
        let enabledButtons = ['improvise', 'jump', 'random', 'fullscreen', 'quit'];
        if (data.slot < this.totalQuestions) {
            enabledButtons.push('next');
        }
        Instructor.enableControls(enabledButtons);
    }

    onRunning(data) {
        if (!this.responses.showResponses) {
            this.responses.hide();
        }
        Instructor.side.classList.remove('hidden');
        Instructor.enableControls(['end', 'responses', 'fullscreen']);
        this.quiz.question.questionTime = data.questiontime;
        if (this.quiz.question.isRunning) {
            // Check if the question has already ended.
            // We need to do this because the state does not update unless an instructor is connected.
            if (data.questionTime > 0 && data.delay < -data.questiontime) {
                this.endQuestion();
            }
            // Only rebuild results if we are not merging.
            this.responses.refresh(!Instructor.isMerging);
        } else {
            const started = this.quiz.question.startCountdown(data.questiontime, data.delay);
            if (started) {
                this.quiz.question.isRunning = true;
            }
        }
    }

    onReviewing(data) {
        Instructor.side.classList.remove('hidden');
        let enabledButtons = ['answer', 'repoll', 'fullscreen', 'improvise', 'jump', 'random', 'quit'];
        if (this.allowVote) {
            enabledButtons.push('vote');
        }
        if (data.slot < this.totalQuestions) {
            enabledButtons.push('next');
        }
        Instructor.enableControls(enabledButtons);

        // In case page was refreshed, we should ensure the question is showing.
        if (!Question.isLoaded()) {
            this.quiz.question.refresh();
        }

        // For now, just always show responses while reviewing.
        // In the future, there should be an additional toggle.
        if (this.quiz.isNewState) {
            this.responses.show();
        }
        // No longer in question.
        this.quiz.question.isRunning = false;
    }

    onSessionClosed() {
        Instructor.side.classList.add('hidden');
        Instructor.correctAnswer.classList.add('hidden');
        Instructor.enableControls([]);
        this.responses.clear();
        this.quiz.question.isRunning = false;
    }

    onVoting() {
        if (!this.responses.showResponses) {
            this.responses.hide();
        }
        Instructor.side.classList.remove('hidden');
        Instructor.enableControls(['quit', 'fullscreen', 'answer', 'responses', 'end']);
        this.responses.refreshVotes();
    }

    onStateChange() {
        //$('#region-main').find('ul.nav.nav-tabs').css('display', 'none');
        //$('#region-main-settings-menu').css('display', 'none');
        //$('.region_main_settings_menu_proxy').css('display', 'none');

        Instructor.controlButtons.classList.remove('hidden');
        Instructor.control('startquiz').parentElement.classList.add('hidden');

    }

    onQuestionRefreshed(data) {
        this.allowVote = data.voteable;
    }

    onTimerEnding() {
        this.endQuestion();
    }

    onTimerTick(timeLeft) {
        setText(document.querySelector(selectors.question.timer), 'x_seconds_left', 'jazzquiz', timeLeft);
    }

    /**
     * Start the quiz. Does not start any questions.
     */
    startQuiz() {
        Instructor.control('startquiz').parentElement.classList.add('hidden');
        Ajax.post('start_quiz', {}, () => {
            const controls = document.querySelector(selectors.quiz.controls);
            if (controls) {
                controls.classList.remove('btn-hide');
            }
        });
    }

    /**
     * End the currently ongoing question or vote.
     */
    endQuestion() {
        this.quiz.question.hideTimer();
        Ajax.post('end_question', {}, () => {
            if (this.quiz.state === 'voting') {
                this.responses.showVotesUponReview = true;
            } else {
                this.quiz.question.isRunning = false;
                Instructor.enableControls([]);
            }
        });
    }

    /**
     * Show a question list dropdown.
     * @param {string} name
     */
    showQuestionListSetup(name) {
        let controlButton = Instructor.control(name);
        if (controlButton.classList.contains('active')) {
            // It's already open. Let's not send another request.
            return;
        }
        Ajax.get(`list_${name}_questions`, {}, data => {
            controlButton.classList.add('active');
            const menu = document.querySelector(`#jazzquiz_${name}_menu`);
            menu.innerHTML = '';
            menu.classList.add('active');
            const margin = controlButton.getBoundingClientRect().left - controlButton.parentElement.getBoundingClientRect().left;
            menu.style.marginLeft = margin + 'px';
            const questions = data.questions;
            for (let i in questions) {
                if (!questions.hasOwnProperty(i)) {
                    continue;
                }
                let questionButton = document.createElement('BUTTON');
                questionButton.classList.add('btn');
                addMathjaxElement(questionButton, questions[i].name);
                questionButton.dataset.time = questions[i].time;
                questionButton.dataset.questionId = questions[i].questionid;
                questionButton.dataset.jazzquizQuestionId = questions[i].jazzquizquestionid;
                questionButton.addEventListener('click', () => {
                    const questionId = questionButton.dataset.questionId;
                    const time = questionButton.dataset.time;
                    const jazzQuestionId = questionButton.dataset.jazzquizQuestionId;
                    this.jumpQuestion(questionId, time, jazzQuestionId);
                    menu.innerHTML = '';
                    menu.classList.remove('active');
                    controlButton.classList.remove('active');
                });
                menu.appendChild(questionButton);
            }
        });
    }

    /**
     * Get the selected responses.
     * @returns {Array.<Object>} Vote options
     */
    static getSelectedAnswersForVote() {
        return document.querySelectorAll(selectors.quiz.selectedVoteOption).map(option => {
            return {text: option.dataset.response, count: option.dataset.count};
        });
    }

    /**
     * Start a vote with the responses that are currently selected.
     */
    runVoting() {
        const options = Instructor.getSelectedAnswersForVote();
        const data = {questions: encodeURIComponent(JSON.stringify(options))};
        Ajax.post('run_voting', data);
    }

    /**
     * Start a new question in this session.
     * @param {string} method
     * @param {number} questionId
     * @param {number} questionTime
     * @param {number} jazzquizQuestionId
     */
    startQuestion(method, questionId, questionTime, jazzquizQuestionId) {
        document.querySelector(selectors.quiz.info).classList.add('hidden');
        this.responses.clear();
        this.hideCorrectAnswer();
        Ajax.post('start_question', {
            method: method,
            questionid: questionId,
            questiontime: questionTime,
            jazzquizquestionid: jazzquizQuestionId
        }, data => this.quiz.question.startCountdown(data.questiontime, data.delay));
    }

    /**
     * Jump to a planned question in the quiz.
     * @param {number} questionId
     * @param {number} questionTime
     * @param {number} jazzquizQuestionId
     */
    jumpQuestion(questionId, questionTime, jazzquizQuestionId) {
        this.startQuestion('jump', questionId, questionTime, jazzquizQuestionId);
    }

    /**
     * Repoll the previously asked question.
     */
    repollQuestion() {
        this.startQuestion('repoll', 0, 0, 0);
    }

    /**
     * Continue on to the next preplanned question.
     */
    nextQuestion() {
        this.startQuestion('next', 0, 0, 0);
    }

    /**
     * Start a random question.
     */
    randomQuestion() {
        this.startQuestion('random', 0, 0, 0);
    }

    /**
     * Close the current session.
     */
    closeSession() {
        document.querySelector(selectors.quiz.undoMergeButton).classList.add('hidden');
        document.querySelector(selectors.question.box).classList.add('hidden');
        Instructor.controls.classList.add('hidden');
        setText(document.querySelector(selectors.quiz.info), 'closing_session');
        // eslint-disable-next-line no-return-assign
        Ajax.post('close_session', {}, () => window.location = location.href.split('&')[0]);
    }

    /**
     * Hide the correct answer if showing.
     */
    hideCorrectAnswer() {
        if (this.isShowingCorrectAnswer) {
            Instructor.correctAnswer.classList.add('hidden');
            Instructor.control('answer').querySelector('.fa').classList.replace('fa-check-square-o', 'fa-square-o');
            this.isShowingCorrectAnswer = false;
        }
    }

    /**
     * Request and show the correct answer for the ongoing or previous question.
     */
    showCorrectAnswer() {
        this.hideCorrectAnswer();
        Ajax.get('get_right_response', {}, data => {
            Instructor.correctAnswer.innerHTML = data.right_answer;
            Instructor.correctAnswer.classList.remove('hidden');
            renderAllMathjax();
            Instructor.control('answer').querySelector('.fa').classList.replace('fa-square-o', 'fa-check-square-o');
            this.isShowingCorrectAnswer = true;
        });
    }

    /**
     * Enables all buttons passed in arguments, but disables all others.
     * @param {Array.<string>} buttons The unique part of the IDs of the buttons to be enabled.
     */
    static enableControls(buttons) {
        document.querySelectorAll(selectors.quiz.controlButton).forEach(controlButton => {
            controlButton.disabled = (buttons.indexOf(controlButton.dataset.control) === -1);
        });
    }

    /**
     * Enter fullscreen mode for better use with projectors.
     */
    static showFullscreenView() {
        if (document.querySelector(selectors.main).classList.contains('jazzquiz-fullscreen')) {
            Instructor.closeFullscreenView();
            return;
        }
        // Hide the scrollbar - remember to always set back to auto when closing.
        document.documentElement.style.overflowY = 'hidden';
        // Sets the quiz view to an absolute position that covers the viewport.
        document.querySelector(selectors.main).classList.add('jazzquiz-fullscreen');
    }

    /**
     * Exit the fullscreen mode.
     */
    static closeFullscreenView() {
        document.documentElement.style.overflowY = 'auto';
        document.querySelector(selectors.main).classList.remove('jazzquiz-fullscreen');
    }

    /**
     * Close the dropdown menu for choosing a question.
     * @param {Event} event
     * @param {string} name
     */
    static closeQuestionListMenu(event, name) {
        // Close the menu if the click was not inside.
        if (!event.target.closest(`#jazzquiz_${name}_menu`)) {
            const menu = document.querySelector(`#jazzquiz_${name}_menu`);
            menu.innerHTML = '';
            menu.classList.remove('active');
            const controlButton = document.querySelector(`#jazzquiz_control_${name}`);
            if (controlButton) {
                controlButton.classList.remove('active');
            }
        }
    }

    static addReportEventHandlers() {
        document.addEventListener('click', event => {
            const reportOverviewControlsButton = event.target.closest('#report_overview_controls button');
            if (reportOverviewControlsButton) {
                const action = reportOverviewControlsButton.dataset.action;
                if (action === 'attendance') {
                    document.querySelector(selectors.quiz.reportOverviewResponded).classList.remove('hidden');
                    document.querySelector(selectors.quiz.reportOverviewResponses).classList.add('hidden');
                } else if (action === 'responses') {
                    document.querySelector(selectors.quiz.reportOverviewResponses).classList.remove('hidden');
                    document.querySelector(selectors.quiz.reportOverviewResponded).classList.add('hidden');
                }
            }
        });
    }

}

export const initialize = (totalQuestions, reportView, slots) => {
    const quiz = new Quiz(Instructor);
    quiz.role.totalQuestions = totalQuestions;
    if (reportView) {
        Instructor.addReportEventHandlers();
        quiz.role.responses.showResponses = true;
        slots.forEach(slot => {
            const wrapper = 'jazzquiz_wrapper_responses_' + slot.num;
            const table = 'responses_wrapper_table_' + slot.num;
            const graph = 'report_' + slot.num;
            quiz.role.responses.set(wrapper, table, slot.responses, undefined, slot.type, graph, false);
        });
    } else {
        quiz.poll(500);
    }
};
