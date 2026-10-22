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
 * Turns AI Tutor /quiz results into Moodle XML.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination;

/**
 * Turns AI Tutor /quiz results into Moodle XML, which Moodle's own importer then turns into questions.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_xml {
    /** @var int Characters of the question text used as the question name. */
    private const NAME_LENGTH = 60;

    /**
     * Build Moodle XML for the usable questions of a /quiz result.
     *
     * A question is skipped when it is not multiple choice, has no text, has fewer than two
     * options, or its correct answer matches none of the options.
     *
     * @param array $questions The result's questions[]: question_type, question_text, source_pages, and
     *                         question_json with options, correct_answer, explanation and hint.
     * @return array ['xml' => string, 'count' => int questions written, 'skipped' => int].
     */
    public static function build(array $questions): array {
        $out = ['<?xml version="1.0" encoding="UTF-8"?>', '<quiz>'];
        $count = 0;
        $skipped = 0;
        foreach ($questions as $question) {
            $xml = self::question($question);
            if ($xml === null) {
                $skipped++;
                continue;
            }
            $out[] = $xml;
            $count++;
        }
        $out[] = '</quiz>';
        return ['xml' => implode("\n", $out), 'count' => $count, 'skipped' => $skipped];
    }

    /**
     * Moodle XML for one multiple-choice question, or null if it is not usable.
     *
     * @param mixed $question One question from the result.
     * @return string|null The <question> element.
     */
    private static function question($question): ?string {
        if (!is_array($question) || ($question['question_type'] ?? 'multiple_choice') !== 'multiple_choice') {
            return null;
        }
        // The API puts the detail in question_json; the documented shape has it at the top level.
        $detail = is_array($question['question_json'] ?? null) ? $question['question_json'] + $question : $question;

        $text = trim((string) ($detail['question_text'] ?? ''));
        $options = array_values(array_filter(
            array_map(fn($o) => trim((string) $o), (array) ($detail['options'] ?? [])),
            fn($o) => $o !== ''
        ));
        if ($text === '' || count($options) < 2) {
            return null;
        }
        $options = self::strip_letters($options);
        $correct = self::correct_index($options, (string) ($detail['correct_answer'] ?? ''));
        if ($correct === null) {
            return null;
        }

        $name = \core_text::substr(trim(preg_replace('/\s+/u', ' ', strip_tags($text))), 0, self::NAME_LENGTH);
        $feedback = '';
        $explanation = trim((string) ($detail['explanation'] ?? ''));
        if ($explanation !== '') {
            $feedback .= '<p>' . s($explanation) . '</p>';
        }
        $pages = array_filter(array_map('intval', (array) ($question['source_pages'] ?? [])));
        if ($pages) {
            $feedback .= '<p>' . get_string('sourcepages', 'qbank_lumination', implode(', ', $pages)) . '</p>';
        }
        $hint = trim((string) ($detail['hint'] ?? ''));
        $hintxml = $hint === ''
            ? ''
            : '    <hint format="html"><text>' . self::cdata('<p>' . s($hint) . '</p>') . "</text></hint>\n";

        $answers = '';
        foreach ($options as $i => $option) {
            $fraction = $i === $correct ? 100 : 0;
            $answers .= "    <answer fraction=\"{$fraction}\" format=\"html\">\n"
                . '      <text>' . self::cdata(s($option)) . "</text>\n"
                . "      <feedback format=\"html\"><text></text></feedback>\n"
                . "    </answer>\n";
        }

        return "  <question type=\"multichoice\">\n"
            . '    <name><text>' . self::cdata($name) . "</text></name>\n"
            . '    <questiontext format="html"><text>' . self::cdata('<p>' . s($text) . '</p>') . "</text></questiontext>\n"
            . '    <generalfeedback format="html"><text>' . self::cdata($feedback) . "</text></generalfeedback>\n"
            . "    <defaultgrade>1</defaultgrade>\n"
            . "    <penalty>0.3333333</penalty>\n"
            . "    <hidden>0</hidden>\n"
            . "    <single>true</single>\n"
            . "    <shuffleanswers>true</shuffleanswers>\n"
            . "    <answernumbering>abc</answernumbering>\n"
            . $answers
            . $hintxml
            . "  </question>";
    }

    /**
     * Remove "A) ", "B. ", "(C) " labels when every option has one, in order.
     *
     * Moodle numbers and shuffles the options itself, so the API's labels would show twice and
     * point at the wrong option. Options are left alone unless all of them are labelled a, b, c...
     *
     * @param string[] $options The options.
     * @return string[] The options without labels.
     */
    private static function strip_letters(array $options): array {
        $pattern = '/^\(?([A-Za-z])[\)\.:]\s+/u';
        $stripped = [];
        foreach ($options as $i => $option) {
            if (!preg_match($pattern, $option, $m) || \core_text::strtolower($m[1]) !== chr(ord('a') + $i)) {
                return $options;
            }
            $stripped[] = trim(preg_replace($pattern, '', $option));
        }
        return in_array('', $stripped, true) ? $options : $stripped;
    }

    /**
     * Index of the option that matches the correct answer, ignoring case and spacing.
     *
     * Also accepts a letter ("B") or a 1-based number ("2") pointing at an option.
     *
     * @param string[] $options The options.
     * @param string $correct The correct answer as returned by the API.
     * @return int|null The index, or null if nothing matches.
     */
    private static function correct_index(array $options, string $correct): ?int {
        $normalise = fn(string $s) => \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
        $target = $normalise($correct);
        if ($target === '') {
            return null;
        }
        foreach ($options as $i => $option) {
            if ($normalise($option) === $target) {
                return $i;
            }
        }
        if (preg_match('/^[a-z]$/', $target)) {
            $i = ord($target) - ord('a');
            return $i < count($options) ? $i : null;
        }
        if (ctype_digit($target)) {
            $i = (int) $target - 1;
            return $i >= 0 && $i < count($options) ? $i : null;
        }
        return null;
    }

    /**
     * Wrap text in CDATA, splitting any "]]>" inside it.
     *
     * @param string $text The text.
     * @return string The CDATA section.
     */
    private static function cdata(string $text): string {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]>';
    }
}
