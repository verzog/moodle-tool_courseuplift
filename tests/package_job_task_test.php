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
use tool_courseuplift\task\analyse_package_task;
use tool_courseuplift\task\build_course_task;
use tool_courseuplift\task\package_job_task;

/**
 * Tests the cap on how many conversion jobs run at once.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(package_job_task::class)]
final class package_job_task_test extends \advanced_testcase {
    /**
     * Both task types default to two at once, and follow the "maxconcurrent" setting.
     *
     * @return void
     */
    public function test_limit_follows_setting(): void {
        $this->resetAfterTest(true);

        $this->assertSame(package_job_task::DEFAULT_MAX_CONCURRENT, (new build_course_task())->get_concurrency_limit());
        $this->assertSame(package_job_task::DEFAULT_MAX_CONCURRENT, (new analyse_package_task())->get_concurrency_limit());

        set_config('maxconcurrent', 5, 'tool_courseuplift');
        $this->assertSame(5, (new build_course_task())->get_concurrency_limit());
        $this->assertSame(5, (new analyse_package_task())->get_concurrency_limit());

        // Zero or a negative value is not "unlimited": it falls back to the default.
        set_config('maxconcurrent', 0, 'tool_courseuplift');
        $this->assertSame(package_job_task::DEFAULT_MAX_CONCURRENT, (new build_course_task())->get_concurrency_limit());
    }

    /**
     * A per-class limit in config.php wins over the setting, for that class only.
     *
     * @return void
     */
    public function test_config_override_wins(): void {
        global $CFG;
        $this->resetAfterTest(true);
        set_config('maxconcurrent', 5, 'tool_courseuplift');

        $CFG->task_concurrency_limit = [build_course_task::class => 1];

        $this->assertSame(1, (new build_course_task())->get_concurrency_limit());
        $this->assertSame(5, (new analyse_package_task())->get_concurrency_limit());
    }
}
