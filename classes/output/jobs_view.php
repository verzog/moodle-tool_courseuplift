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

namespace tool_courseuplift\output;

use html_writer;
use moodle_url;
use tool_courseuplift\local\job_manager;

/**
 * Small HTML helpers for the "Conversion jobs" page (jobs.php) and its printable error log.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs_view {
    /**
     * The batch totals as a row of counters.
     *
     * @param array $totals From job_dashboard::totals().
     * @return string HTML.
     */
    public static function counters(array $totals): string {
        $counters = [
            'total' => get_string('jobstotal', 'tool_courseuplift'),
            job_manager::STATUS_QUEUED => get_string('status_queued', 'tool_courseuplift'),
            job_manager::STATUS_RUNNING => get_string('status_running', 'tool_courseuplift'),
            job_manager::STATUS_DONE => get_string('status_done', 'tool_courseuplift'),
            job_manager::STATUS_FAILED => get_string('status_failed', 'tool_courseuplift'),
            'created' => get_string('jobstotalcreated', 'tool_courseuplift'),
            'skipped' => get_string('jobstotalskipped', 'tool_courseuplift'),
            'warnings' => get_string('jobstotalwarnings', 'tool_courseuplift'),
        ];
        $out = '';
        foreach ($counters as $key => $label) {
            $out .= html_writer::div(
                html_writer::div((string) $totals[$key], 'tool_courseuplift-jobs-count') .
                html_writer::div(s($label), 'tool_courseuplift-jobs-label'),
                'tool_courseuplift-jobs-counter tool_courseuplift-jobs-counter-' . $key
            );
        }
        return html_writer::div($out, 'tool_courseuplift-jobs-counters');
    }

    /**
     * A link to the course a job built, or a dash when it built none.
     *
     * @param int|null $courseid The course id.
     * @return string HTML.
     */
    public static function course_link(?int $courseid): string {
        if (!$courseid) {
            return '-';
        }
        return html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), (string) $courseid);
    }

    /**
     * The Bootstrap badge classes for a job status.
     *
     * @param string $status A job_manager::STATUS_* value.
     * @return string CSS classes.
     */
    public static function status_badge_class(string $status): string {
        $colours = [
            job_manager::STATUS_QUEUED => 'secondary',
            job_manager::STATUS_RUNNING => 'info',
            job_manager::STATUS_DONE => 'success',
            job_manager::STATUS_FAILED => 'danger',
        ];
        $colour = $colours[$status] ?? 'secondary';
        return 'badge bg-' . $colour . ' badge-' . $colour;
    }
}
