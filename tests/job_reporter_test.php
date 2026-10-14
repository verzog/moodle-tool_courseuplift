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
use tool_courseuplift\local\job_manager;
use tool_courseuplift\local\job_reporter;

/**
 * Tests the job summaries behind the job-status CLI (cli/jobs.php).
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(job_reporter::class)]
final class job_reporter_test extends \advanced_testcase {
    /**
     * A finished build is summarised with its course, headline numbers and plain-text warnings;
     * the full report is only included when asked for.
     *
     * @return void
     */
    public function test_summarise_finished_build(): void {
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $jobs = new job_manager();
        $jobid = $jobs->create(2, (int) $category->id, job_manager::KIND_BUILD, null, 'https://example.test/course.imscc');
        $jobs->mark_done($jobid, (int) $course->id, [
            'itemcount' => 154,
            'created' => 154,
            'skipped' => 0,
            'sectioncount' => 15,
            'warnings' => ['Found <b>4</b> rubric(s) not linked to any activity.'],
        ]);

        $reporter = new job_reporter();
        $summary = $reporter->summarise(launcher::get_job($jobid));

        $this->assertSame($jobid, $summary['id']);
        $this->assertSame('build', $summary['kind']);
        $this->assertSame('done', $summary['status']);
        $this->assertSame((int) $course->id, $summary['courseid']);
        $this->assertSame('https://example.test/course.imscc', $summary['package']);
        $this->assertSame(154, $summary['created']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(15, $summary['sectioncount']);
        $this->assertSame(['Found 4 rubric(s) not linked to any activity.'], $summary['warnings']);
        $this->assertArrayNotHasKey('report', $summary);

        $full = $reporter->summarise(launcher::get_job($jobid), true);
        $this->assertSame(154, $full['report']['itemcount']);
    }

    /**
     * A finished analysis's warnings are language string keys, which become readable text.
     *
     * @return void
     */
    public function test_summarise_analysis_translates_warning_keys(): void {
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $jobs = new job_manager();
        $jobid = $jobs->create(2, (int) $category->id, job_manager::KIND_ANALYSE, null, 'https://example.test/a.imscc');
        $jobs->mark_analysed($jobid, ['itemcount' => 20, 'warnings' => ['warnreportquiz']]);

        $summary = (new job_reporter())->summarise(launcher::get_job($jobid));

        $this->assertSame(20, $summary['itemcount']);
        $this->assertNull($summary['courseid']);
        $this->assertSame([get_string('warnreportquiz', 'tool_courseuplift')], $summary['warnings']);
    }

    /**
     * A queued job has no report yet, so its summary has no numbers or warnings, and the table
     * shows dashes for them.
     *
     * @return void
     */
    public function test_summarise_queued_job_and_table(): void {
        $this->resetAfterTest(true);
        $category = $this->getDataGenerator()->create_category();
        $jobs = new job_manager();
        $jobid = $jobs->create(2, (int) $category->id, job_manager::KIND_BUILD, null, 'https://example.test/q.imscc');

        $reporter = new job_reporter();
        $summary = $reporter->summarise(launcher::get_job($jobid));

        $this->assertSame('queued', $summary['status']);
        $this->assertArrayNotHasKey('created', $summary);
        $this->assertArrayNotHasKey('warnings', $summary);

        $table = $reporter->to_table([$summary]);
        $lines = explode("\n", trim($table));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Job', $lines[0]);
        $this->assertMatchesRegularExpression('/^' . $jobid . '\s+build\s+queued\s+0\s+-\s+-\s+-\s+/', $lines[1]);
        $this->assertStringEndsWith('https://example.test/q.imscc', $lines[1]);
    }
}
