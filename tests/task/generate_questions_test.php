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
 * Unit tests for the question generation task.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category   test
 */

namespace qbank_lumination\task;

/**
 * Tests for {@see \qbank_lumination\task\generate_questions}.
 *
 * @covers \qbank_lumination\task\generate_questions
 */
final class generate_questions_test extends \advanced_testcase {
    /**
     * Queue a job with a small text document in a new course question bank.
     *
     * @return array [job id, context id].
     */
    private function queue_job(): array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/questionlib.php');

        $course = $this->getDataGenerator()->create_course();
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $category = question_get_default_category($context->id, true);

        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'Photosynthesis happens in chloroplasts.');

        $jobid = generate_questions::queue($category, (int) $qbank->cmid, $draftid, 5, 'easy', 'en');
        return [$jobid, $context->id];
    }

    /**
     * Test that queue() records the job, keeps the document and queues the task.
     */
    public function test_queue(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$jobid, $contextid] = $this->queue_job();
        $job = $DB->get_record('qbank_lumination_jobs', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('queued', $job->status);
        $this->assertSame('notes.txt', $job->filename);
        $this->assertNotNull(generate_questions::get_source_file($contextid, $jobid));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(generate_questions::class));
    }

    /**
     * Test that without a Lumination provider the job fails cleanly and the document is removed.
     */
    public function test_execute_without_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$jobid, $contextid] = $this->queue_job();
        $tasks = \core\task\manager::get_adhoc_tasks(generate_questions::class);
        $task = reset($tasks);
        ob_start();
        $task->execute();
        ob_end_clean();

        $job = $DB->get_record('qbank_lumination_jobs', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('failed', $job->status);
        $this->assertSame(get_string('noprovider', 'qbank_lumination'), $job->message);
        $this->assertNull(generate_questions::get_source_file($contextid, $jobid));
    }
}
