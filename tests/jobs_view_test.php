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
use tool_courseuplift\output\jobs_view;

/**
 * Tests the HTML helpers of the "Conversion jobs" page.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(jobs_view::class)]
final class jobs_view_test extends \advanced_testcase {
    /**
     * A package is labelled by its file name, from a URL (decoded) or a plain name.
     *
     * @return void
     */
    public function test_package_label(): void {
        $this->assertSame('ST-110.imscc', jobs_view::package_label('https://canvas.example.edu/exports/ST-110.imscc?x=1'));
        $this->assertSame('CVT 205.imscc', jobs_view::package_label('https://e.test/a/CVT%20205.imscc'));
        $this->assertSame('course.imscc', jobs_view::package_label('course.imscc'));
        $this->assertSame('', jobs_view::package_label(''));
    }

    /**
     * Each status gets its badge colour, with a neutral one for anything unknown.
     *
     * @return void
     */
    public function test_status_badge_class(): void {
        $this->assertSame('badge bg-danger badge-danger', jobs_view::status_badge_class('failed'));
        $this->assertSame('badge bg-success badge-success', jobs_view::status_badge_class('done'));
        $this->assertSame('badge bg-secondary badge-secondary', jobs_view::status_badge_class('other'));
    }
}
