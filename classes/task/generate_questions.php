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
 * Adhoc task that generates questions from a document.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination\task;

use aiprovider_lumination\api_client;
use qbank_lumination\importer;
use qbank_lumination\quiz_xml;

/**
 * Adhoc task that generates questions from a document.
 *
 * Sends the document to the AI Tutor /quiz endpoint, waits for the result, and imports the
 * questions into a new category. Progress and the result are on the job record (status.php).
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_questions extends \core\task\adhoc_task {
    /** @var string File area holding each job's source document, by job id. */
    public const FILEAREA = 'source';

    /**
     * Record a job, keep its source document, and queue the task for the current user.
     *
     * @param \stdClass $category The question category to generate under.
     * @param int|null $cmid The question bank module, for links back.
     * @param int $draftitemid Draft area of the uploaded document.
     * @param int $questioncount Questions to request.
     * @param string $difficulty easy, medium or hard.
     * @param string $language Output language code.
     * @return int The job id.
     */
    public static function queue(
        \stdClass $category,
        ?int $cmid,
        int $draftitemid,
        int $questioncount,
        string $difficulty,
        string $language
    ): int {
        global $DB, $USER;

        $now = time();
        $jobid = $DB->insert_record('qbank_lumination_jobs', (object) [
            'userid' => $USER->id,
            'contextid' => $category->contextid,
            'categoryid' => $category->id,
            'cmid' => $cmid,
            'filename' => '',
            'questioncount' => $questioncount,
            'difficulty' => $difficulty,
            'language' => $language,
            'status' => 'queued',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        file_save_draft_area_files($draftitemid, $category->contextid, 'qbank_lumination', self::FILEAREA, $jobid);
        $file = self::get_source_file($category->contextid, $jobid);
        $DB->set_field('qbank_lumination_jobs', 'filename', $file ? $file->get_filename() : '', ['id' => $jobid]);

        $task = new self();
        $task->set_userid($USER->id);
        $task->set_custom_data(['jobid' => $jobid]);
        \core\task\manager::queue_adhoc_task($task);
        return $jobid;
    }

    /**
     * Do not retry: a retry would spend credits again and create a second category.
     *
     * @return bool False.
     */
    public function retry_until_success(): bool {
        return false;
    }

    /**
     * Generate and import the questions.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $job = $DB->get_record('qbank_lumination_jobs', ['id' => $this->get_custom_data()->jobid]);
        if (!$job || $job->status !== 'queued') {
            return;
        }
        $this->update($job, ['status' => 'running']);

        try {
            $client = api_client::for_site();
            if (!$client) {
                throw new \moodle_exception('noprovider', 'qbank_lumination');
            }
            $file = self::get_source_file($job->contextid, $job->id);
            if (!$file) {
                throw new \moodle_exception('nosourcefile', 'qbank_lumination');
            }

            $result = $client->run('/quiz', [
                'file_b64' => base64_encode($file->get_content()),
                'file_name' => $file->get_filename(),
                'question_count' => (int) $job->questioncount,
                'difficulty_level' => $job->difficulty,
                'language_code' => $job->language,
            ]);
            $built = quiz_xml::build((array) ($result['result']['questions'] ?? []));
            if ($built['count'] === 0) {
                throw new \moodle_exception('noquestions', 'qbank_lumination');
            }

            $parent = $DB->get_record('question_categories', ['id' => $job->categoryid], '*', MUST_EXIST);
            $name = get_string('categoryname', 'qbank_lumination', (object) [
                'file' => $job->filename,
                'date' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig')),
            ]);
            $newcategoryid = importer::import($parent, \core_text::substr($name, 0, 255), $built['xml']);

            $this->update($job, [
                'status' => 'completed',
                'newcategoryid' => $newcategoryid,
                'created' => $built['count'],
                'skipped' => $built['skipped'],
            ]);
        } catch (\Throwable $e) {
            $this->update($job, ['status' => 'failed', 'message' => $e->getMessage()]);
            mtrace('Lumination question generation failed: ' . $e->getMessage());
        } finally {
            // The document was only needed for this request.
            get_file_storage()->delete_area_files($job->contextid, 'qbank_lumination', self::FILEAREA, $job->id);
        }
    }

    /**
     * The source document of a job.
     *
     * @param int $contextid Question bank context.
     * @param int $jobid Job id.
     * @return \stored_file|null The file, or null if there is none.
     */
    public static function get_source_file(int $contextid, int $jobid): ?\stored_file {
        $files = get_file_storage()->get_area_files($contextid, 'qbank_lumination', self::FILEAREA, $jobid, 'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * Write changed fields to the job record.
     *
     * @param \stdClass $job The job (updated in place).
     * @param array $fields Fields to change.
     * @return void
     */
    private function update(\stdClass $job, array $fields): void {
        global $DB;
        foreach ($fields as $name => $value) {
            $job->$name = $value;
        }
        $job->timemodified = time();
        $DB->update_record('qbank_lumination_jobs', $job);
    }
}
