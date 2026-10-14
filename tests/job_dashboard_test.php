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

namespace tool_courseuplift;

use PHPUnit\Framework\Attributes\CoversClass;
use tool_courseuplift\local\job_dashboard;
use tool_courseuplift\local\job_manager;

/**
 * Tests the data behind the "Conversion jobs" page: totals, the error log and the filters.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(job_dashboard::class)]
final class job_dashboard_test extends \advanced_testcase {
    /**
     * Queue a done build, a failed build, a running build and a queued analysis.
     *
     * @param int $userid The user who starts them.
     * @param int $categoryid Target category.
     * @return array Job ids keyed done, failed, running, queued.
     */
    private function make_jobs(int $userid, int $categoryid): array {
        $jobs = new job_manager();
        $ids = [];
        $ids['done'] = $jobs->create($userid, $categoryid, job_manager::KIND_BUILD, null, 'https://e.test/done.imscc');
        $jobs->mark_done($ids['done'], 5, [
            'itemcount' => 10,
            'created' => 9,
            'skipped' => 1,
            'warnings' => ['Found 2 rubric(s) not linked.', 'Imported 3 outcome(s).'],
        ]);
        $ids['failed'] = $jobs->create($userid, $categoryid, job_manager::KIND_BUILD, null, 'https://e.test/bad.imscc');
        $jobs->mark_failed($ids['failed'], 'Not a valid package.');
        $ids['running'] = $jobs->create($userid, $categoryid, job_manager::KIND_BUILD, null, 'https://e.test/run.imscc');
        $jobs->mark_running($ids['running']);
        $jobs->set_progress($ids['running'], 40, 'Building quizzes');
        $ids['queued'] = $jobs->create($userid, $categoryid, job_manager::KIND_ANALYSE, null, 'https://e.test/q.imscc');
        return $ids;
    }

    /**
     * Totals count each status and add up created, skipped and warnings; a run with a queued or
     * running job is active.
     *
     * @return void
     */
    public function test_totals_and_active(): void {
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $this->make_jobs(2, (int) $category->id);
        $dashboard = new job_dashboard();

        $summaries = $dashboard->summaries();
        $totals = $dashboard->totals($summaries);

        $this->assertSame(4, $totals['total']);
        $this->assertSame(1, $totals[job_manager::STATUS_DONE]);
        $this->assertSame(1, $totals[job_manager::STATUS_FAILED]);
        $this->assertSame(1, $totals[job_manager::STATUS_RUNNING]);
        $this->assertSame(1, $totals[job_manager::STATUS_QUEUED]);
        $this->assertSame(9, $totals['created']);
        $this->assertSame(1, $totals['skipped']);
        $this->assertSame(2, $totals['warnings']);
        $this->assertTrue($dashboard->has_active($summaries));
        $this->assertFalse($dashboard->has_active($dashboard->summaries(0, '', job_manager::STATUS_DONE)));
    }

    /**
     * The error log has one error per failed job and one warning per report warning, and the
     * running job's progress is carried in its summary.
     *
     * @return void
     */
    public function test_log_entries(): void {
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $ids = $this->make_jobs(2, (int) $category->id);
        $dashboard = new job_dashboard();
        $summaries = $dashboard->summaries();

        $entries = $dashboard->log_entries($summaries);

        $errors = array_values(array_filter($entries, fn($e) => $e['severity'] === job_dashboard::SEVERITY_ERROR));
        $warnings = array_values(array_filter($entries, fn($e) => $e['severity'] === job_dashboard::SEVERITY_WARNING));
        $this->assertCount(1, $errors);
        $this->assertSame($ids['failed'], $errors[0]['jobid']);
        $this->assertSame('Not a valid package.', $errors[0]['message']);
        $this->assertSame('https://e.test/bad.imscc', $errors[0]['package']);
        $this->assertCount(2, $warnings);
        $this->assertSame($ids['done'], $warnings[0]['jobid']);
        $this->assertSame(5, $warnings[0]['courseid']);

        $running = array_values(array_filter($summaries, fn($s) => $s['id'] === $ids['running']))[0];
        $this->assertSame(40, $running['progress']);
        $this->assertSame('Building quizzes', $running['progressmessage']);
    }

    /**
     * The filters narrow by user, kind, status and the time a job last changed.
     *
     * @return void
     */
    public function test_filters(): void {
        global $DB;
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $ids = $this->make_jobs(2, (int) $category->id);
        $other = $this->getDataGenerator()->create_user();
        $this->make_jobs((int) $other->id, (int) $category->id);
        // Age one job so the time filter excludes it.
        $DB->set_field('tool_courseuplift_jobs', 'timemodified', time() - 10 * DAYSECS, ['id' => $ids['done']]);
        $dashboard = new job_dashboard();

        $this->assertCount(8, $dashboard->summaries());
        $this->assertCount(4, $dashboard->summaries((int) $other->id));
        $this->assertCount(2, $dashboard->summaries(0, job_manager::KIND_ANALYSE));
        $this->assertCount(2, $dashboard->summaries(0, '', job_manager::STATUS_FAILED));
        $week = job_dashboard::period_start('week', time());
        $recent = $dashboard->summaries(2, '', '', $week);
        $this->assertCount(3, $recent);
        $this->assertNotContains($ids['done'], array_column($recent, 'id'));
    }

    /**
     * Each period starts the right calendar distance back; "all" means no limit.
     *
     * @return void
     */
    public function test_period_start(): void {
        $this->resetAfterTest(true);
        $this->setTimezone('Australia/Sydney');
        // Midday 04/10/2026 in Sydney, the day daylight saving starts (2am becomes 3am): "a day ago" is
        // midday the day before, only 23 real hours earlier.
        $now = (new \DateTimeImmutable('2026-10-04 12:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp();
        $day = (new \DateTimeImmutable('2026-10-03 12:00', new \DateTimeZone('Australia/Sydney')))->getTimestamp();

        $this->assertSame($day, job_dashboard::period_start('day', $now));
        $this->assertSame(23 * HOURSECS, $now - $day);
        $this->assertSame(0, job_dashboard::period_start('all', $now));
        $this->assertSame(0, job_dashboard::period_start('unknown', $now));
        $this->assertLessThan($now, job_dashboard::period_start('month', $now));
    }
}
