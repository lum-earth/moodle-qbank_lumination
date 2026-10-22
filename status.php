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
 * Progress and result of a question generation job.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

$id = required_param('id', PARAM_INT);

require_login();
$job = $DB->get_record('qbank_lumination_jobs', ['id' => $id], '*', MUST_EXIST);
$context = context::instance_by_id($job->contextid);
require_capability('moodle/question:add', $context);
if ($job->userid != $USER->id && !has_capability('moodle/site:config', context_system::instance())) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('generatetitle', 'qbank_lumination'));
}

$PAGE->set_url(new moodle_url('/question/bank/lumination/status.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('generatetitle', 'qbank_lumination'));
$PAGE->set_heading(get_string('generatetitle', 'qbank_lumination'));

$finished = in_array($job->status, ['completed', 'failed'], true);
if (!$finished) {
    $PAGE->set_periodic_refresh_delay(5);
}

$a = (object) [
    'file' => s($job->filename),
    'count' => $job->questioncount,
    'created' => $job->created,
    'skipped' => $job->skipped,
];

echo $OUTPUT->header();

if ($job->status === 'queued') {
    echo $OUTPUT->notification(get_string('jobqueued', 'qbank_lumination', $a), 'info');
    // Adhoc tasks only run when Moodle's cron runs.
    if (time() - $job->timecreated > 2 * MINSECS) {
        echo $OUTPUT->notification(get_string('jobwaitingcron', 'qbank_lumination'), 'warning');
    }
} else if ($job->status === 'running') {
    echo $OUTPUT->notification(get_string('jobrunning', 'qbank_lumination', $a), 'info');
} else if ($job->status === 'completed') {
    echo $OUTPUT->notification(get_string('jobcompleted', 'qbank_lumination', $a), 'success');
    if ($job->skipped) {
        echo $OUTPUT->notification(get_string('jobskipped', 'qbank_lumination', $a), 'warning');
    }
} else {
    echo $OUTPUT->notification(get_string('jobfailed', 'qbank_lumination', $a), 'error');
    if (!empty($job->message)) {
        echo $OUTPUT->notification(s($job->message), 'error');
    }
}

if ($finished && $job->cmid) {
    $params = ['cmid' => $job->cmid];
    if ($job->newcategoryid) {
        $params['cat'] = $job->newcategoryid . ',' . $job->contextid;
    }
    echo html_writer::link(
        new moodle_url('/question/edit.php', $params),
        get_string($job->newcategoryid ? 'viewquestions' : 'backtobank', 'qbank_lumination'),
        ['class' => 'btn btn-primary mt-3']
    );
} else if (!$finished) {
    echo html_writer::tag('p', get_string('jobcanleave', 'qbank_lumination'));
}

echo $OUTPUT->footer();
