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

namespace tool_courseuplift\local;

/**
 * Turns import job records into plain summaries for the job-status CLI (cli/jobs.php).
 *
 * A summary carries the job's identity, state and timing plus, once the job has finished,
 * the headline numbers from its stored report (items, created, skipped, sections) and its
 * warnings as plain text. The full stored report can be included on request.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_reporter {
    /** Report keys copied into a summary when present: the headline numbers of a run. */
    private const SUMMARY_KEYS = ['itemcount', 'created', 'skipped', 'sectioncount'];

    /**
     * Summarise one job record.
     *
     * @param \stdClass $job A job record, as launcher::get_job() or launcher::list_jobs() return.
     * @param bool $full Whether to include the whole decoded report under 'report'.
     * @return array The summary, ready for json_encode() or to_table().
     */
    public function summarise(\stdClass $job, bool $full = false): array {
        $summary = [
            'id' => (int) $job->id,
            'kind' => (string) $job->kind,
            'status' => (string) $job->status,
            'progress' => (int) ($job->progress ?? 0),
            'progressmessage' => (string) ($job->progressmessage ?? ''),
            'userid' => (int) $job->userid,
            'categoryid' => (int) $job->categoryid,
            'courseid' => empty($job->courseid) ? null : (int) $job->courseid,
            'package' => $this->package_name($job),
            'errormsg' => (string) ($job->errormsg ?? ''),
            'timecreated' => (int) $job->timecreated,
            'timemodified' => (int) $job->timemodified,
        ];
        $report = $this->decode_report($job);
        if ($report !== null) {
            foreach (self::SUMMARY_KEYS as $key) {
                if (isset($report[$key]) && is_numeric($report[$key])) {
                    $summary[$key] = (int) $report[$key];
                }
            }
            $summary['warnings'] = $this->plain_warnings($report['warnings'] ?? []);
            if ($full) {
                $summary['report'] = $report;
            }
        }
        return $summary;
    }

    /**
     * Render summaries as a fixed-width text table for a terminal.
     *
     * @param array $summaries Summaries from summarise().
     * @return string The table, one job per line after a header.
     */
    public function to_table(array $summaries): string {
        $format = "%-7s %-8s %-8s %5s %-8s %8s %8s  %-15s %s\n";
        $out = sprintf($format, 'Job', 'Kind', 'Status', '%', 'Course', 'Created', 'Skipped', 'Modified', 'Package');
        $datefmt = get_string('strftimedatetimeshort', 'langconfig');
        foreach ($summaries as $row) {
            $out .= sprintf(
                $format,
                $row['id'],
                $row['kind'],
                $row['status'],
                $row['progress'],
                $row['courseid'] ?? '-',
                $row['created'] ?? '-',
                $row['skipped'] ?? '-',
                userdate($row['timemodified'], $datefmt),
                $row['package']
            );
        }
        return $out;
    }

    /**
     * The package a job imports: its source URL, else the stored file's name.
     *
     * @param \stdClass $job The job record.
     * @return string The URL or file name, or '' when neither is known.
     */
    private function package_name(\stdClass $job): string {
        if (!empty($job->packageurl)) {
            return (string) $job->packageurl;
        }
        if (!empty($job->fileid)) {
            $file = get_file_storage()->get_file_by_id((int) $job->fileid);
            if ($file) {
                return $file->get_filename();
            }
        }
        return '';
    }

    /**
     * Decode a job's stored JSON report.
     *
     * @param \stdClass $job The job record.
     * @return array|null The report, or null when the job has none (yet) or it is unreadable.
     */
    private function decode_report(\stdClass $job): ?array {
        if (empty($job->report)) {
            return null;
        }
        $report = json_decode((string) $job->report, true);
        return is_array($report) ? $report : null;
    }

    /**
     * Turn report warnings into plain text. Build reports store rendered strings; analysis
     * reports store language string keys, which are looked up here.
     *
     * @param mixed $warnings The report's warnings.
     * @return array Plain-text warnings.
     */
    private function plain_warnings($warnings): array {
        if (!is_array($warnings)) {
            return [];
        }
        $plain = [];
        foreach ($warnings as $warning) {
            if (!is_string($warning) || $warning === '') {
                continue;
            }
            if (preg_match('/^[a-z0-9_]+$/', $warning) && get_string_manager()->string_exists($warning, 'tool_courseuplift')) {
                $warning = get_string($warning, 'tool_courseuplift');
            }
            $plain[] = trim(html_to_text($warning, 0, false));
        }
        return $plain;
    }
}
