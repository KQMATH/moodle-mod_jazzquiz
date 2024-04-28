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
 * Selectors.
 *
 * @module    mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2024 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    main: '#jazzquiz',
    question: {
        box: '#jazzquiz_question_box',
        timer: '#jazzquiz_question_timer',
        form: '#jazzquiz_question_form',
    },
    quiz: {
        info: '#jazzquiz_info_container',
        responded: '#jazzquiz_responded_container',
        responses: '#jazzquiz_responses_container',
        responseContainer: '.jazzquiz-response-container',
        responseInfo: '#jazzquiz_response_info_container',
        undoMergeButton: '#jazzquiz_undo_merge',
        showNormalResultsButton: '#review_show_normal_results',
        showVoteResultsButton: '#review_show_vote_results',
        mergeInto: '.merge-into',
        mergeFrom: '.merge-from',
        saveVoteButton: '#jazzquiz_save_vote',
        selectVoteButton: '.jazzquiz-select-vote',
        controls: '#jazzquiz_controls',
        controlsBox: '#jazzquiz_controls_box',
        controlButtons: '.quiz-control-buttons',
        sideContainer: '#jazzquiz_side_container',
        correctAnswerContainer: '#jazzquiz_correct_answer_container',
        controlButton: '.jazzquiz-control-button',
        selectedVoteOption: '.selected-vote-option',
        reportOverviewResponded: '#report_overview_responded',
        reportOverviewResponses: '#report_overview_responses',
        studentsJoinedCounter: '#jazzquiz_students_joined_counter',
        startQuizButton: '#jazzquiz_start_quiz_button',
        exitQuizButton: '#jazzquiz_exit_button',
    },
    edit: {
        addSelectedQuestions: '.jazzquiz-add-selected-questions',
        questionCheckedCheckbox: '#categoryquestions td input[type=checkbox]:checked',
        editQuestionAction: '.edit-question-action',
    },
};
