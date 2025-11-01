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
 * @module     mod_jazzquiz
 * @author     Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright  2015 University of Wisconsin - Madison
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import selectors from 'mod_jazzquiz/selectors';

/**
 * Submit the question order to the server. An empty array will delete all questions.
 * @param {Array.<number>} order
 * @param {number} courseModuleId
 */
function submitQuestionOrder(order, courseModuleId) {
    $.post('edit.php', {
        id: courseModuleId,
        action: 'order',
        order: JSON.stringify(order)
    }, () => location.reload()); // TODO: Correct locally instead, but for now just refresh.
}

/**
 * @returns {Array} The current question order.
 */
function getQuestionOrder() {
    const questions = document.querySelectorAll('.questionlist li');
    return questions.map(question => question.dataset.questionId);
}

/**
 * Move a question up or down by a specified offset.
 * @param {number} questionId
 * @param {number} offset Negative to move down, positive to move up
 * @returns {Array}
 */
function offsetQuestion(questionId, offset) {
    let order = getQuestionOrder();
    let originalIndex = order.indexOf(questionId);
    if (originalIndex === -1) {
        return order;
    }
    for (let i = 0; i < order.length; i++) {
        if (i + offset === originalIndex) {
            order[originalIndex] = order[i];
            order[i] = questionId;
            break;
        }
    }
    return order;
}

/**
 * Add click-listener to a quiz by module id.
 * @param {number} courseModuleId
 */
function listenAddToQuiz(courseModuleId) {
    const addSelectedQuestionsButton = document.querySelector(selectors.edit.addSelectedQuestions);
    addSelectedQuestionsButton.addEventListener('click', function() {
        let questionIds = '';
        for (const checkbox of document.querySelectorAll(selectors.edit.questionCheckedCheckbox)) {
            questionIds += checkbox.getAttribute('name').slice(1) + ',';
        }
        $.post('edit.php', {
            id: courseModuleId,
            action: 'addquestion',
            questionids: questionIds,
        }, () => location.reload());
    });
}

/**
 * Initialize edit page.
 * @param {number} courseModuleId
 */
export function initialize(courseModuleId) {
    document.addEventListener('click', event => {
        const editQuestionAction = event.target.closest(selectors.edit.editQuestionAction);
        if (editQuestionAction) {
            let order = [];
            switch (editQuestionAction.dataset.action) {
                case 'up':
                    order = offsetQuestion(editQuestionAction.dataset.questionId, 1);
                    break;
                case 'down':
                    order = offsetQuestion(editQuestionAction.dataset.questionId, -1);
                    break;
                case 'delete': {
                    order = getQuestionOrder();
                    const index = order.indexOf(editQuestionAction.dataset.questionId);
                    if (index !== -1) {
                        order.splice(index, 1);
                    }
                    break;
                }
                default:
                    return;
            }
            submitQuestionOrder(order, courseModuleId);
        }
    });
    const questionList = document.getElementsByClassName('questionlist')[0];
    if (typeof Sortable !== 'undefined') {
        // eslint-disable-next-line no-undef
        Sortable.create(questionList, {
            handle: '.dragquestion',
            onSort: () => submitQuestionOrder(getQuestionOrder(), courseModuleId)
        });
    }
    listenAddToQuiz(courseModuleId);
}
