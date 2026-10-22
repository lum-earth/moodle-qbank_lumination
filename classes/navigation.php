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
 * The "Generate with AI" tab of the question bank.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination;

/**
 * The "Generate with AI" tab of the question bank.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation extends \core_question\local\bank\navigation_node_base {
    #[\Override]
    public function get_navigation_title(): string {
        return get_string('generatetab', 'qbank_lumination');
    }

    #[\Override]
    public function get_navigation_key(): string {
        return 'lumination';
    }

    #[\Override]
    public function get_navigation_url(): \moodle_url {
        return new \moodle_url('/question/bank/lumination/generate.php');
    }
}
