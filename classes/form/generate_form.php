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
 * Form to generate questions from a document.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to generate questions from a document.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_form extends \moodleform {
    /** @var string[] Output languages offered, by code. */
    public const LANGUAGES = ['en', 'es', 'fr', 'de', 'pt', 'it', 'nl', 'ca'];

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'filemanager',
            'sourcefile',
            get_string('sourcefile', 'qbank_lumination'),
            null,
            [
                'maxbytes' => 50 * 1024 * 1024,
                'maxfiles' => 1,
                'accepted_types' => ['.pdf', '.docx', '.txt'],
            ]
        );
        $mform->addRule('sourcefile', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('sourcefile', 'sourcefile', 'qbank_lumination');

        $counts = array_combine(range(5, 30, 5), range(5, 30, 5));
        $mform->addElement('select', 'questioncount', get_string('questioncount', 'qbank_lumination'), $counts);
        $mform->setDefault('questioncount', 10);

        $mform->addElement('select', 'difficulty', get_string('difficulty', 'qbank_lumination'), [
            'easy' => get_string('difficulty_easy', 'qbank_lumination'),
            'medium' => get_string('difficulty_medium', 'qbank_lumination'),
            'hard' => get_string('difficulty_hard', 'qbank_lumination'),
        ]);
        $mform->setDefault('difficulty', 'medium');

        $names = get_string_manager()->get_list_of_languages();
        $languages = [];
        foreach (self::LANGUAGES as $code) {
            $languages[$code] = $names[$code] ?? $code;
        }
        $mform->addElement('select', 'language', get_string('language', 'qbank_lumination'), $languages);
        $current = substr(current_language(), 0, 2);
        $mform->setDefault('language', in_array($current, self::LANGUAGES, true) ? $current : 'en');

        $this->add_action_buttons(true, get_string('generate', 'qbank_lumination'));
    }
}
