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
 * Puts generated questions into a new question category.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination;

/**
 * Puts generated questions into a new question category, through Moodle's own XML importer.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class importer {
    /**
     * Create a subcategory under the given category and import the Moodle XML into it.
     *
     * Runs as the current user, who needs to manage categories and add questions there.
     *
     * @param \stdClass $parent The parent question category record.
     * @param string $name Name of the new category.
     * @param string $xml Moodle XML from quiz_xml::build().
     * @return int The new category id.
     * @throws \moodle_exception If the category cannot be created or the import fails.
     */
    public static function import(\stdClass $parent, string $name, string $xml): int {
        global $CFG, $DB;
        // Cron does not load the question library; the importer needs question_bank from it.
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');

        $context = \context::instance_by_id($parent->contextid);
        $manager = new \core_question\category_manager();
        $categoryid = $manager->add_category($parent->id . ',' . $parent->contextid, $name, '');
        $category = $DB->get_record('question_categories', ['id' => $categoryid], '*', MUST_EXIST);
        $category->context = $context;

        $file = make_request_directory() . '/lumination.xml';
        file_put_contents($file, $xml);

        $format = new \qformat_xml();
        $format->setCategory($category);
        $format->setContexts([$context]);
        $format->setCourse(get_course($context->get_course_context()->instanceid));
        $format->setFilename($file);
        $format->setRealfilename('lumination.xml');
        $format->setMatchgrades('error');
        $format->setCatfromfile(false);
        $format->setContextfromfile(false);
        $format->setStoponerror(true);

        // The importer prints progress for the web page; it is not wanted here.
        ob_start();
        try {
            $ok = $format->importpreprocess() && $format->importprocess() && $format->importpostprocess();
        } finally {
            ob_end_clean();
        }
        if (!$ok) {
            throw new \moodle_exception('importfailed', 'qbank_lumination');
        }
        return $categoryid;
    }
}
