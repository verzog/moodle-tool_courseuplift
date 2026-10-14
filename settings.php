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
 * Adds the CourseUplift run page under Site administration > Courses, and a
 * CourseUplift settings page (chunked upload) under Admin tools.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('courses', new admin_externalpage(
        'tool_courseuplift',
        get_string('pluginname', 'tool_courseuplift'),
        new moodle_url('/admin/tool/courseuplift/index.php'),
        'tool/courseuplift:use'
    ));
    // Every analyse and build job on the site, with progress, totals and a printable error log.
    $ADMIN->add('courses', new admin_externalpage(
        'tool_courseuplift_jobs',
        get_string('jobsheading', 'tool_courseuplift'),
        new moodle_url('/admin/tool/courseuplift/jobs.php'),
        'tool/courseuplift:use'
    ));

    // Group everything for this plugin under a "CourseUplift" category in Admin
    // tools, so its settings (and any future admin pages) sit one level under the
    // plugin name rather than as a loose leaf beside other tools.
    $category = new admin_category(
        'tool_courseuplift_cat',
        get_string('pluginname', 'tool_courseuplift')
    );
    $ADMIN->add('tools', $category);

    // Settings for the chunked-upload field on the package upload form (folded in
    // from the former local_chunkupload plugin).
    $settings = new admin_settingpage(
        'tool_courseuplift_settings',
        get_string('settings', 'tool_courseuplift')
    );
    $settings->add(new admin_setting_heading(
        'tool_courseuplift/chunkheading',
        get_string('settings', 'tool_courseuplift'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'tool_courseuplift/chunksize',
        new lang_string('setting:chunksize', 'tool_courseuplift'),
        new lang_string('setting:chunksize_desc', 'tool_courseuplift'),
        20,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configduration(
        'tool_courseuplift/state0duration',
        new lang_string('setting:state0duration', 'tool_courseuplift'),
        new lang_string('setting:state0duration_desc', 'tool_courseuplift'),
        3600,
        3600
    ));
    $settings->add(new admin_setting_configduration(
        'tool_courseuplift/state1duration',
        new lang_string('setting:state1duration', 'tool_courseuplift'),
        new lang_string('setting:state1duration_desc', 'tool_courseuplift'),
        3600,
        3600
    ));
    $settings->add(new admin_setting_configduration(
        'tool_courseuplift/state2duration',
        new lang_string('setting:state2duration', 'tool_courseuplift'),
        new lang_string('setting:state2duration_desc', 'tool_courseuplift'),
        86400,
        86400
    ));
    // Cap simultaneous conversions so a bulk import can't take every cron worker.
    $settings->add(new admin_setting_configtext(
        'tool_courseuplift/maxconcurrent',
        new lang_string('setting:maxconcurrent', 'tool_courseuplift'),
        new lang_string('setting:maxconcurrent_desc', 'tool_courseuplift'),
        \tool_courseuplift\task\package_job_task::DEFAULT_MAX_CONCURRENT,
        PARAM_INT
    ));
    // A legitimate multi-gigabyte package download must not be killed by a fixed
    // overall timeout — abort only when the connection has actually stalled. The
    // ingest url_fetcher enforces this and reads the value here (with a floor of
    // 30 seconds and a ceiling of 1 hour).
    $settings->add(new admin_setting_configduration(
        'tool_courseuplift/transferstall',
        new lang_string('setting:transferstall', 'tool_courseuplift'),
        new lang_string('setting:transferstall_desc', 'tool_courseuplift'),
        120,
        60
    ));
    // Extra patterns for recognising LTI links that launch Moodle-hosted content (e.g. STACK
    // questions delivered over LTI), so the conversion report can flag them. Built-in detection
    // (enrol_lti/mod-lti URLs, and "stack") works without any configuration here.
    $settings->add(new admin_setting_configtextarea(
        'tool_courseuplift/ltimoodlepatterns',
        new lang_string('setting:ltimoodlepatterns', 'tool_courseuplift'),
        new lang_string('setting:ltimoodlepatterns_desc', 'tool_courseuplift'),
        ''
    ));
    $ADMIN->add('tool_courseuplift_cat', $settings);
}
