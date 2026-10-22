<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for turning /quiz results into questions.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category   test
 */

namespace qbank_lumination;

/**
 * Tests for {@see \qbank_lumination\quiz_xml} and {@see \qbank_lumination\importer}.
 *
 * @covers \qbank_lumination\quiz_xml
 * @covers \qbank_lumination\importer
 */
final class quiz_xml_test extends \advanced_testcase {
    /**
     * The questions of the fixture, shaped like a real /quiz result.
     *
     * @return array Questions.
     */
    private function fixture(): array {
        $json = file_get_contents(__DIR__ . '/fixtures/quiz_result.json');
        return json_decode($json, true)['questions'];
    }

    /**
     * Test that usable questions are built and broken ones skipped.
     */
    public function test_build_counts(): void {
        $built = quiz_xml::build($this->fixture());
        $this->assertSame(2, $built['count']);
        $this->assertSame(1, $built['skipped']);
        $this->assertStringContainsString('<question type="multichoice">', $built['xml']);
        // Options keep the API order, so explanations that name a letter stay right.
        $this->assertStringContainsString('<shuffleanswers>false</shuffleanswers>', $built['xml']);
        $this->assertStringContainsString('<answernumbering>ABCD</answernumbering>', $built['xml']);
        // Letter labels are removed; Moodle labels the options itself.
        $this->assertStringNotContainsString('B) In the thylakoid', $built['xml']);
        $this->assertStringContainsString('In the thylakoid membranes', $built['xml']);
        $this->assertStringContainsString('<hint format="html">', $built['xml']);
    }

    /**
     * Test the documented flat shape and other answer formats.
     */
    public function test_flat_shape_and_answer_formats(): void {
        $built = quiz_xml::build([
            ['question_text' => 'Flat, text answer', 'options' => ['Red', 'Blue'], 'correct_answer' => ' blue '],
            ['question_text' => 'Number answer', 'options' => ['One', 'Two', 'Three'], 'correct_answer' => '3'],
            ['question_text' => 'No match', 'options' => ['Yes', 'No'], 'correct_answer' => 'Maybe'],
            ['question_type' => 'true_false', 'question_text' => 'Other type', 'options' => ['T', 'F'], 'correct_answer' => 'T'],
            // Not every option is labelled, so "A. Smith" keeps its text.
            ['question_text' => 'Author?', 'options' => ['A. Smith', 'Jones'], 'correct_answer' => 'A. Smith'],
        ]);
        $this->assertSame(3, $built['count']);
        $this->assertSame(2, $built['skipped']);
        $this->assertStringContainsString('A. Smith', $built['xml']);
    }

    /**
     * Test that the questions are imported into a new category with the right correct answers.
     */
    public function test_import(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $parent = question_get_default_category($context->id, true);

        $built = quiz_xml::build($this->fixture());
        $categoryid = importer::import($parent, 'Generated', $built['xml']);

        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        $this->assertSame('Generated', $category->name);
        $this->assertEquals($parent->id, $category->parent);

        $questions = $DB->get_records_sql(
            'SELECT q.id, q.qtype, q.generalfeedback
               FROM {question} q
               JOIN {question_versions} qv ON qv.questionid = q.id
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qbe.questioncategoryid = ?',
            [$categoryid]
        );
        $this->assertCount(2, $questions);
        $correct = [];
        foreach ($questions as $question) {
            $this->assertSame('multichoice', $question->qtype);
            $correct[] = strip_tags($DB->get_field_select(
                'question_answers',
                'answer',
                'question = ? AND fraction > 0.9',
                [$question->id]
            ));
        }
        sort($correct);
        $this->assertSame(['Carbon dioxide', 'In the thylakoid membranes'], $correct);
    }
}
