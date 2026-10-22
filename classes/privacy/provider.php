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
 * Privacy provider for the Lumination AI Question Generator.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_lumination\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the Lumination AI Question Generator.
 *
 * Stores a job record per generation (who started it, where, and the result) and sends the
 * source document to the AI Tutor API. The document is deleted after the job.
 *
 * @package    qbank_lumination
 * @copyright  2026 Lumination AI <https://lumination.ai>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('qbank_lumination_jobs', [
            'userid' => 'privacy:metadata:jobs:userid',
            'filename' => 'privacy:metadata:jobs:filename',
            'status' => 'privacy:metadata:jobs:status',
            'timecreated' => 'privacy:metadata:jobs:timecreated',
        ], 'privacy:metadata:jobs');
        $collection->add_external_location_link('aitutor', [
            'document' => 'privacy:metadata:aitutor:document',
        ], 'privacy:metadata:aitutor');
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {qbank_lumination_jobs} WHERE userid = :userid',
            ['userid' => $userid]
        );
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {qbank_lumination_jobs} WHERE contextid = :contextid',
            ['contextid' => $userlist->get_context()->id]
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $jobs = $DB->get_records('qbank_lumination_jobs', ['userid' => $userid, 'contextid' => $context->id]);
            if (!$jobs) {
                continue;
            }
            $data = [];
            foreach ($jobs as $job) {
                $data[] = (object) [
                    'filename' => $job->filename,
                    'status' => $job->status,
                    'questions' => $job->created,
                    'timecreated' => transform::datetime($job->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'qbank_lumination')],
                (object) ['jobs' => $data]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $DB->delete_records('qbank_lumination_jobs', ['contextid' => $context->id]);
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['contextid'] = $userlist->get_context()->id;
        $DB->delete_records_select('qbank_lumination_jobs', "contextid = :contextid AND userid {$insql}", $params);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('qbank_lumination_jobs', ['userid' => $userid, 'contextid' => $context->id]);
        }
    }
}
