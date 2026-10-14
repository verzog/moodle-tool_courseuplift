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
 * Data behind the "Conversion jobs" page (jobs.php): the jobs matching a filter, totals across
 * them, and the error log of failures and warnings.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_dashboard {
    /** Error-log severity of a job that failed. */
    public const SEVERITY_ERROR = 'error';

    /** Error-log severity of a warning in a finished job's report. */
    public const SEVERITY_WARNING = 'warning';

    /**
     * Time periods the page can filter on, mapped to a relative date modifier ('' = all time). Calendar
     * arithmetic in the user's timezone, not fixed seconds, so a daylight-saving change can't shift a day.
     */
    public const PERIODS = ['day' => '-1 day', 'week' => '-7 days', 'month' => '-1 month', 'all' => ''];

    /** @var job_manager Reads the job rows. */
    private job_manager $jobs;

    /** @var job_reporter Turns job rows into summaries. */
    private job_reporter $reporter;

    /**
     * Constructor.
     *
     * @param job_manager|null $jobs Job store (defaults to a new one).
     * @param job_reporter|null $reporter Summary builder (defaults to a new one).
     */
    public function __construct(?job_manager $jobs = null, ?job_reporter $reporter = null) {
        $this->jobs = $jobs ?? new job_manager();
        $this->reporter = $reporter ?? new job_reporter();
    }

    /**
     * Summaries of the jobs matching a filter, newest first.
     *
     * @param int $userid Only jobs started by this user; 0 for everyone.
     * @param string $kind Only this kind (job_manager::KIND_*); '' for both.
     * @param string $status Only this status (job_manager::STATUS_*); '' for any.
     * @param int $since Only jobs changed at or after this Unix time; 0 for any time.
     * @return array Summaries from job_reporter::summarise().
     */
    public function summaries(int $userid = 0, string $kind = '', string $status = '', int $since = 0): array {
        $rows = $this->jobs->list_jobs(
            $userid > 0 ? $userid : null,
            $kind !== '' ? $kind : null,
            $status !== '' ? $status : null,
            0,
            $since
        );
        $summaries = [];
        foreach ($rows as $row) {
            $summaries[] = $this->reporter->summarise($row);
        }
        return $summaries;
    }

    /**
     * Totals across a set of jobs: how many are in each status, and the items created and
     * skipped and the warnings raised by the finished ones.
     *
     * @param array $summaries Summaries from summaries().
     * @return array Keys: total, queued, running, done, failed, created, skipped, warnings.
     */
    public function totals(array $summaries): array {
        $totals = [
            'total' => count($summaries),
            job_manager::STATUS_QUEUED => 0,
            job_manager::STATUS_RUNNING => 0,
            job_manager::STATUS_DONE => 0,
            job_manager::STATUS_FAILED => 0,
            'created' => 0,
            'skipped' => 0,
            'warnings' => 0,
        ];
        foreach ($summaries as $summary) {
            if (isset($totals[$summary['status']])) {
                $totals[$summary['status']]++;
            }
            $totals['created'] += $summary['created'] ?? 0;
            $totals['skipped'] += $summary['skipped'] ?? 0;
            $totals['warnings'] += count($summary['warnings'] ?? []);
        }
        return $totals;
    }

    /**
     * Whether any of the jobs is still queued or running, so the page should keep refreshing.
     *
     * @param array $summaries Summaries from summaries().
     * @return bool
     */
    public function has_active(array $summaries): bool {
        foreach ($summaries as $summary) {
            if (in_array($summary['status'], [job_manager::STATUS_QUEUED, job_manager::STATUS_RUNNING], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The error log: one entry per failed job and per warning in a finished job's report,
     * in the same newest-first order as the jobs.
     *
     * @param array $summaries Summaries from summaries().
     * @return array Entries with keys jobid, package, courseid, kind, severity, message.
     */
    public function log_entries(array $summaries): array {
        $entries = [];
        foreach ($summaries as $summary) {
            $base = [
                'jobid' => $summary['id'],
                'package' => $summary['package'],
                'courseid' => $summary['courseid'],
                'kind' => $summary['kind'],
            ];
            if ($summary['status'] === job_manager::STATUS_FAILED) {
                $message = $summary['errormsg'] !== '' ? $summary['errormsg']
                    : get_string('status_' . job_manager::STATUS_FAILED, 'tool_courseuplift');
                $entries[] = $base + ['severity' => self::SEVERITY_ERROR, 'message' => $message];
            }
            foreach ($summary['warnings'] ?? [] as $warning) {
                $entries[] = $base + ['severity' => self::SEVERITY_WARNING, 'message' => $warning];
            }
        }
        return $entries;
    }

    /**
     * The start of a filter period, as a Unix time.
     *
     * @param string $period A key of PERIODS; unknown keys mean all time.
     * @param int $now The current time.
     * @return int The earliest time to include, or 0 for all time.
     */
    public static function period_start(string $period, int $now): int {
        $modifier = self::PERIODS[$period] ?? '';
        if ($modifier === '') {
            return 0;
        }
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone(\core_date::get_user_timezone_object());
        return $date->modify($modifier)->getTimestamp();
    }
}
