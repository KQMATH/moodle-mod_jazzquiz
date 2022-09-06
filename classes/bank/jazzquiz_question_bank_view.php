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

/**
 * Defines the class for rendering the question bank for the edit page.
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\bank;

defined('MOODLE_INTERNAL') || die();

use \core_question\bank\search\tag_condition as tag_condition;
use \core_question\bank\search\hidden_condition as hidden_condition;
use \core_question\bank\search\category_condition as category_condition;

/**
 * Subclass of the question bank view class to change the way it works/looks
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_question_bank_view extends \core_question\bank\view {

    /**
     * Define the columns we want to be displayed on the question bank
     * @return array
     */
    protected function wanted_columns() {
        // Full class names for question bank columns.
        $columns = [
            '\\mod_jazzquiz\\bank\\question_bank_add_to_jazzquiz_action_column',
            'core_question\\bank\\checkbox_column',
            'core_question\\bank\\question_type_column',
            'core_question\\bank\\question_name_column',
            'core_question\\bank\\preview_action_column'
        ];
        foreach ($columns as $column) {
            $this->requiredcolumns[$column] = new $column($this);
        }
        return $this->requiredcolumns;
    }


    /**
     * Shows the question bank editing interface.
     * @param string $tabname
     * @param int $page
     * @param int $perpage
     * @param string $cat
     * @param bool $recurse
     * @param bool $showhidden
     * @param bool $showquestiontext
     * @param array $tagids
     * @throws \coding_exception
     */
    public function display($tabname, $page, $perpage, $cat, $recurse, $showhidden, $showquestiontext, $tagids = []) {
        global $PAGE;

        if ($this->process_actions_needing_ui()) {
            return;
        }
        $contexts = $this->contexts->having_one_edit_tab_cap($tabname);
        list($categoryid, $contextid) = explode(',', $cat);
        $catcontext = \context::instance_by_id($contextid);
        $thiscontext = $this->get_most_specific_context();

        // Category selection form.
        $this->display_question_bank_header();
        array_unshift($this->searchconditions, new tag_condition([$catcontext, $thiscontext], $tagids));
        array_unshift($this->searchconditions, new hidden_condition(!$showhidden));
        array_unshift($this->searchconditions, new category_condition($cat, $recurse, $contexts, $this->baseurl, $this->course));
        $this->display_options_form($showquestiontext, '/mod/jazzquiz/edit.php');

        // Continues with list of questions.
        $this->display_question_list(
            $this->contexts->having_one_edit_tab_cap($tabname),
            $this->baseurl,
            $cat,
            $this->cm,
            null,
            $page,
            $perpage,
            $showhidden,
            $showquestiontext,
            $this->contexts->having_cap('moodle/question:add')
        );
        $this->display_add_selected_questions_button();
        $PAGE->requires->js_call_amd('core_question/edit_tags', 'init', ['#questionscontainer']);
    }

    private function display_add_selected_questions_button() {
        $straddtoquiz = get_string('add_to_quiz', 'jazzquiz');
        echo '<button class="btn btn-secondary jazzquiz-add-selected-questions">' . $straddtoquiz . '</button>';
    }

    /**
     * Generate an "add to quiz" url so that when clicked the question will be added to the quiz
     * @param int $questionid
     * @return \moodle_url Moodle url to add the question
     * @throws \moodle_exception
     */
    public function get_add_to_jazzquiz_url($questionid) {
        $params = $this->baseurl->params();
        $params['questionids'] = $questionid;
        $params['action'] = 'addquestion';
        $params['sesskey'] = sesskey();
        return new \moodle_url('/mod/jazzquiz/edit.php', $params);
    }

    /**
     * This has been taken from the base class to allow us to call our own version of
     * create_new_question_button.
     * @param \stdClass $category
     * @param bool $add
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function create_new_question_form($category, $add) {
        echo '<div class="createnewquestion">';
        if ($add) {
            $caption = get_string('create_new_question', 'jazzquiz');
            $this->create_new_question_button($category->id, $this->editquestionurl->params(), $caption);
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

    /**
     * Print a button for creating a new question. This will open question/addquestion.php,
     * which in turn goes to question/question.php before getting back to $params['returnurl']
     * (by default the question bank screen).
     *
     * This has been taken from question/editlib.php and adapted to allow us to use the $allowedqtypes
     * param on print_choose_qtype_to_add_form
     *
     * @param int $categoryid The id of the category that the new question should be added to.
     * @param array $params Other parameters to add to the URL. You need either $params['cmid'] or
     *      $params['courseid'], and you should probably set $params['returnurl']
     * @param string $caption the text to display on the button.
     * @param string $tooltip a tooltip to add to the button (optional).
     * @param bool $disabled if true, the button will be disabled.
     * @throws \moodle_exception
     */
    private function create_new_question_button($categoryid, $params, $caption, $tooltip = '', $disabled = false) {
        global $OUTPUT;

        $params['category'] = $categoryid;
        $url = new \moodle_url('/question/addquestion.php', $params);

        echo $OUTPUT->single_button($url, $caption, 'get', [
            'disabled' => $disabled,
            'title' => $tooltip
        ]);

        static $choiceformprinted = false;
        if (!$choiceformprinted) {
            echo '<div id="qtypechoicecontainer">';
            echo print_choose_qtype_to_add_form([]);
            echo "</div>\n";
            $choiceformprinted = true;
        }

        // Add some top margin to the table (not viable via CSS).
        echo '<br><br>';
    }

}
