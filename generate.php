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
 * Generate questions with AI: the question bank tab.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');

require_login();
core_question\local\bank\helper::require_plugin_enabled('qbank_lumination');

// Same permission as importing questions: the "import" tab capability (add questions).
[$thispageurl, $contexts, $cmid, $cm, $module, $pagevars] =
    question_edit_setup('import', '/question/bank/lumination/generate.php');

[$catid] = explode(',', $pagevars['cat']);
$category = $DB->get_record('question_categories', ['id' => $catid], '*', MUST_EXIST);

$PAGE->set_url($thispageurl);
$PAGE->set_title(get_string('generatetitle', 'qbank_lumination'));
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->disable();

$form = new \qbank_lumination\form\generate_form($thispageurl);
$bankurl = new moodle_url('/question/edit.php', ['cmid' => $cmid, 'cat' => $pagevars['cat']]);

if ($form->is_cancelled()) {
    redirect($bankurl);
}

$configured = \aiprovider_lumination\api_client::for_site() !== null;

if ($configured && ($data = $form->get_data())) {
    $jobid = \qbank_lumination\task\generate_questions::queue(
        $category,
        $cmid ? (int) $cmid : null,
        (int) $data->sourcefile,
        (int) $data->questioncount,
        $data->difficulty,
        $data->language
    );
    redirect(new moodle_url('/question/bank/lumination/status.php', ['id' => $jobid]));
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('core_question', 'bank');
echo $renderer->render(new \core_question\output\qbank_action_menu($thispageurl));

echo $OUTPUT->heading(get_string('generatetitle', 'qbank_lumination'));
echo html_writer::tag('p', get_string('generateintro', 'qbank_lumination', format_string($category->name)));

if (!$configured) {
    echo $OUTPUT->notification(get_string('noprovider', 'qbank_lumination'), 'warning');
} else {
    $form->display();
}

echo $OUTPUT->footer();
