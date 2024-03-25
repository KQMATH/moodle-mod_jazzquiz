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
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2015 University of Wisconsin - Madison
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

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
        let order = [];
        $('.questionlist li').each(function() {
            order.push($(this).data('question-id'));
        });
        return order;
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
        $('.jazzquiz-add-selected-questions').on('click', function() {
            const $checkboxes = $('#categoryquestions td input[type=checkbox]:checked');
            let questionIds = '';
            for (const checkbox of $checkboxes) {
                questionIds += checkbox.getAttribute('name').slice(1) + ',';
            }
            $.post('edit.php', {
                id: courseModuleId,
                action: 'addquestion',
                questionids: questionIds,
            }, () => location.reload());
        });
    }

    return {
        initialize: courseModuleId => {
            $('.edit-question-action').on('click', function() {
                const action = $(this).data('action');
                const questionId = $(this).data('question-id');
                let order = [];
                switch (action) {
                    case 'up': {
                        order = offsetQuestion(questionId, 1);
                        break;
                    }
                    case 'down': {
                        order = offsetQuestion(questionId, -1);
                        break;
                    }
                    case 'delete': {
                        order = getQuestionOrder();
                        const index = order.indexOf(questionId);
                        if (index !== -1) {
                            order.splice(index, 1);
                        }
                        break;
                    }
                    default: {
                        return;
                    }
                }
                submitQuestionOrder(order, courseModuleId);
            });
            let questionList = document.getElementsByClassName('questionlist')[0];
            if (typeof Sortable !== 'undefined') {
                // eslint-disable-next-line no-undef
                Sortable.create(questionList, {
                    handle: '.dragquestion',
                    onSort: () => submitQuestionOrder(getQuestionOrder(), courseModuleId)
                });
            }
            listenAddToQuiz(courseModuleId);
        }
    };

});
