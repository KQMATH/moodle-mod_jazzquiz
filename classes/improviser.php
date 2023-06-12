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

namespace mod_jazzquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class improviser {

    /** @var jazzquiz $jazzquiz */
    private $jazzquiz;

    /**
     * @param jazzquiz $jazzquiz
     */
    public function __construct(jazzquiz $jazzquiz) {
        $this->jazzquiz = $jazzquiz;
    }

    /**
     * Check whether a question type exists or not.
     * @param string $name Name of the question type
     * @return bool
     */
    private function question_type_exists(string $name) : bool {
        $qtypes = \core_plugin_manager::instance()->get_plugins_of_type('qtype');
        foreach ($qtypes as $qtype) {
            if ($qtype->name === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all the improvised question records in our activity's question category.
     * @return \stdClass[]|false
     */
    public function get_all_improvised_question_definitions() {
        global $DB;
        $questions = [];
        $context = \context_module::instance($this->jazzquiz->cm->id);
        $parts = explode('/', $context->path);
        debugging( "get_all_improvised: ".$parts ) ;
        foreach ($parts as $part) {
            // Selecting name first, to prevent duplicate improvise questions.
            debugging( "get_all_improvised: ".$part ) ;
            $sql = "SELECT q.name,
                           q.id
                      FROM {question} q
                      JOIN {question_versions} qv
                        ON q.id = qv.questionid
                      JOIN {question_bank_entries} qbe
                        ON qbe.id = qv.questionbankentryid
                      JOIN {question_categories} qc
                        ON qc.id = qbe.questioncategoryid
                      JOIN {context} ctx
                        ON ctx.id = qc.contextid
                       AND ctx.path LIKE :path
                     WHERE q.name LIKE :prefix
                  ORDER BY q.name";
            $questions += $DB->get_records_sql($sql, ['prefix' => '{IMPROV}%', 'path' => '%/' . $part]);
        }
        return $questions ? $questions : false;
    }

    /**
     * Get the specified question name is an improvisational question.
     * @param string $name The name of the improvised question without the prefix.
     * @return \stdClass|false
     */
    private function get_improvised_question_definition(string $name) {
        global $DB;
        $category = $this->get_default_question_category();
        if (!$category) {
            return false;
        }
        $sql = "SELECT qbe.id
                  FROM {question_bank_entries} qbe
                  JOIN {question} q
                    ON q.id = qbe.id
                 WHERE qbe.questioncategoryid = :catid
                   AND q.name LIKE :name";
        $questions = $DB->get_records_sql($sql, ['catid' => $category->id, 'name' => '{IMPROV}' . $name]);
        if (!$questions) {
            return false;
        }
        return reset($questions);
    }

    /**
     * Deletes the improvised question definition with matching name if it exists.
     * @param string $name of question
     */
    private function delete_improvised_question(string $name) {
        $question = $this->get_improvised_question_definition($name);
        if ($question !== false) {
            question_delete_question($question->id);
        }
    }

    /**
     * Returns the default question category for the activity.
     * @return object
     */
    private function get_default_question_category() {
        $context = \context_module::instance($this->jazzquiz->cm->id);
        $category = question_get_default_category($context->id);
        if (!$category) {
            $contexts = new \core_question\local\bank\question_edit_contexts($context);
            $category = question_make_default_categories($contexts->all());
        }
        return $category;
    }

    /**
     * Create a question bank entry for the database.
     * @return \stdClass | null
     */
    private function make_generic_question_bank_entry() {
        $category = $this->get_default_question_category();
        if (!$category) {
            return null;
        }

        /* debugging( "Making generic question bank entry." ) ; */
        $question = new \stdClass();
        $question->questioncategoryid = $category->id;
        $question->ownerid = null;
        $question->idnumber = null;
        return $question;
    }

    /**
     * Create a question database object.
     * @param string $qtype What question type to create
     * @param string $name The name of the question to create
     * @return \stdClass | null
     */
    private function make_generic_question_definition(string $qtype, string $name) {
        if (!$this->question_type_exists($qtype)) {
            /* debugging( "make_generic_question_definition: question type does not exist" ) ; */
            return null;
        }
        $existing = $this->get_improvised_question_definition($name);
        if ($existing !== false) {
            /* debugging( "make_generic_question_definition: existing: ".$name ) ; */
            return null;
        }
        $question = new \stdClass();
        $question->parent = 0;
        $question->name = '{IMPROV}' . $name;
        $question->questiontext = '&nbsp;';
        $question->questiontextformat = 1;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = 1;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = $qtype;
        $question->length = 1;
        $question->stamp = '';
        $question->timecreated = time();
        $question->timemodified = $question->timecreated;
        $question->createdby = null;
        $question->modifiedby = null;
        return $question;
    }

    /**
     * Create a multichoice options database object.
     * @param int $questionid The ID of the question to make options for
     * @return \stdClass
     */
    private function make_multichoice_options($questionid) {
        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->layout = 0;
        $options->single = 1;
        $options->shuffleanswers = 0;
        $options->correctfeedback = '';
        $options->correctfeedbackformat = 1;
        $options->partiallycorrectfeedback = '';
        $options->partiallycorrectfeedbackformat = 1;
        $options->incorrectfeedback = '';
        $options->incorrectfeedbackformat = 1;
        $options->answernumbering = 'none';
        $options->shownumcorrect = 1;
        return $options;
    }

    /**
     * Make a short answer question options database object.
     * @param int $questionid The ID of the question to make options for
     * @return \stdClass
     */
    private function make_shortanswer_options($questionid) {
        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->usecase = 0;
        return $options;
    }

    /**
     * Make an answer for a question.
     * @param int $questionid The ID of the question to make the answer for
     * @param string $format Which format the answer has
     * @param string $answertext The answer text
     * @return \stdClass
     */
    private function make_generic_question_answer($questionid, string $format, string $answertext) {
        $answer = new \stdClass();
        $answer->question = $questionid;
        $answer->answer = $answertext;
        $answer->answerformat = $format;
        $answer->fraction = 1;
        $answer->feedback = '';
        $answer->feedbackformat = 1;
        return $answer;
    }

    /**
     * Insert a multichoice question to the database.
     * @param string $name The name of the question
     * @param string[] $answers Answers to the question
     */
    private function insert_multichoice_question_definition(string $name, array $answers) {
        global $DB;
        /* debugging("insert_multichoice_question_definition: ".$name ) ; */
        $question = $this->make_generic_question_definition('multichoice', $name);
        if (!$question) {
            return;
        }
        /* debugging("insert_multichoice_question_definition 2") ; */
        $qbankentry = $this->make_generic_question_bank_entry();
        if (!$qbankentry) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        $qbankentry->id = $DB->insert_record('question_bank_entries', $qbankentry);
        $this->insert_question_version( $qbankentry->id, $question->id ) ;
        // Add options.
        $options = $this->make_multichoice_options($question->id);
        $DB->insert_record('qtype_multichoice_options', $options);
        // Add answers.
        foreach ($answers as $answer) {
            $qanswer = $this->make_generic_question_answer($question->id, 1, $answer);
            $DB->insert_record('question_answers', $qanswer);
        }
        /* debugging( "Question ID ".$question->id ); */
    }

    /* Create a question version database object, linking the given question
     * and question bank entry. 
     * @param int $qbankid The ID of the question banke entry 
     * @param int $qid The ID of the question 
     * @return \stdClass
     */
    private function insert_question_version(int $qbankid, int $qid) {
        global $DB;
        $qv = new \stdClass();
        $qv->questionbankentryid = $qbankid ;
        $qv->version = 1 ;
        $qv->questionid = $qid ;
        $qv->id = $DB->insert_record('question_versions', $qv);
        return $qv;
    }

    /**
     * Insert a short answer question to the database.
     * @param string $name The name of the short answer question
     */
    private function insert_shortanswer_question_definition(string $name) {
        global $DB;
        /* debugging("insert_shortanswer_question_definition: ".$name ) ; */
        $question = $this->make_generic_question_definition('shortanswer', $name);
        if (!$question) {
            return;
        }
        $qbankentry = $this->make_generic_question_bank_entry();
        if (!$qbankentry) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        $qbankentry->id = $DB->insert_record('question_bank_entries', $qbankentry);
        $this->insert_question_version( $qbankentry->id, $question->id ) ;

        // Add options.
        $options = $this->make_shortanswer_options($question->id);
        $DB->insert_record('qtype_shortanswer_options', $options);
        // Add answer.
        $answer = $this->make_generic_question_answer($question->id, 0, '*');
        $DB->insert_record('question_answers', $answer);
    }

    private function insert_shortmath_question_definition(string $name) {
        global $DB;
        /* debugging("insert_shortmath_question_definition: ".$name ) ; */
        $question = $this->make_generic_question_definition('shortmath', $name);
        if (!$question) {
            return;
        }
        $qbankentry = $this->make_generic_question_bank_entry();
        if (!$qbankentry) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        $qbankentry->id = $DB->insert_record('question_bank_entries', $qbankentry);
        $this->insert_question_version( $qbankentry->id, $question->id ) ;
        // Add options. Important: If shortmath changes options table in the future, this must be changed too.
        $options = $this->make_shortanswer_options($question->id);
        $DB->insert_record('qtype_shortmath_options', $options);
        // Add answer.
        $answer = $this->make_generic_question_answer($question->id, 0, '*');
        $DB->insert_record('question_answers', $answer);
    }

    /**
     * Insert all the improvised question definitions to the question bank.
     * Every question will have a prefix of {IMPROV}
     */
    public function insert_default_improvised_question_definitions() {
        $multichoice = get_string('multichoice_options', 'jazzquiz');
        $yes = get_string('yes');
        $no = get_string('no');
        $this->insert_multichoice_question_definition("3 $multichoice", ['A', 'B', 'C']);
        $this->insert_multichoice_question_definition("4 $multichoice", ['A', 'B', 'C', 'D']);
        $this->insert_multichoice_question_definition("5 $multichoice", ['A', 'B', 'C', 'D', 'E']);
        $this->insert_shortanswer_question_definition(get_string('short_answer', 'jazzquiz'));
        // TODO: Remove the check in the future, 2020+. Avoid messing with as many existing instances as possible.
        if (!$this->get_improvised_question_definition('True / False')) {
            $this->insert_multichoice_question_definition("$yes / $no", [$yes, $no]);
        }
        $this->insert_shortmath_question_definition(get_string('short_math_answer', 'jazzquiz'));
    }

}
