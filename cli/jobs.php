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
 * CLI: show CourseUplift import jobs and their status.
 *
 * Read-only. Lists analyse and build jobs (or one job) with their state, progress, the course a
 * build created and the headline numbers from the job's report, as a table or as JSON for
 * scripts that queue imports in bulk and need to follow them to completion.
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use tool_courseuplift\launcher;
use tool_courseuplift\local\job_manager;
use tool_courseuplift\local\job_reporter;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'json' => false,
        'full' => false,
        'job' => 0,
        'user' => 0,
        'kind' => '',
        'status' => '',
        'since' => 0,
        'limit' => 50,
    ],
    [
        'h' => 'help',
        'j' => 'json',
    ]
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    echo "Show CourseUplift import jobs and their status (read-only).

Options:
  -h, --help          Print this help.
  -j, --json          Print JSON instead of a table: an array of jobs, or one object with --job.
      --full          With --json, include each finished job's whole stored report.
      --job=ID        Show only this job. Exits with status 1 if it does not exist.
      --user=ID       Only jobs started by this user.
      --kind=KIND     Only 'analyse' or 'build' jobs.
      --status=STATUS Only 'queued', 'running', 'done' or 'failed' jobs.
      --since=TS      Only jobs changed at or after this Unix timestamp.
      --limit=N       At most N jobs, newest first (default 50; 0 = all).

Examples:
  # Recent jobs as a table:
  \$ php admin/tool/courseuplift/cli/jobs.php

  # Follow one job from a script:
  \$ php admin/tool/courseuplift/cli/jobs.php --job=42 --json

  # Every failed build since a given time, as JSON:
  \$ php admin/tool/courseuplift/cli/jobs.php --kind=build --status=failed --since=1760400000 --limit=0 --json
";
    exit(0);
}

$kinds = [job_manager::KIND_ANALYSE, job_manager::KIND_BUILD];
$statuses = [job_manager::STATUS_QUEUED, job_manager::STATUS_RUNNING, job_manager::STATUS_DONE, job_manager::STATUS_FAILED];
if ($options['kind'] !== '' && !in_array($options['kind'], $kinds, true)) {
    cli_error("--kind must be one of: " . implode(', ', $kinds));
}
if ($options['status'] !== '' && !in_array($options['status'], $statuses, true)) {
    cli_error("--status must be one of: " . implode(', ', $statuses));
}
foreach (['job', 'user', 'since', 'limit'] as $name) {
    if (!is_numeric($options[$name]) || (int) $options[$name] < 0) {
        cli_error("--{$name} must be a whole number of 0 or more.");
    }
}

$reporter = new job_reporter();
$full = (bool) $options['full'];

if ((int) $options['job'] > 0) {
    $job = launcher::get_job((int) $options['job']);
    if ($job === null) {
        cli_error('No CourseUplift job with id ' . (int) $options['job'] . '.');
    }
    $summary = $reporter->summarise($job, $full);
    if ($options['json']) {
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo $reporter->to_table([$summary]);
    }
    exit(0);
}

// The --since filter is applied after the query, so fetch every match and apply --limit after it.
$since = (int) $options['since'];
$limit = (int) $options['limit'];
$jobs = launcher::list_jobs(
    (int) $options['user'] > 0 ? (int) $options['user'] : null,
    $options['kind'] !== '' ? $options['kind'] : null,
    $options['status'] !== '' ? $options['status'] : null,
    $since > 0 ? 0 : $limit
);
$summaries = [];
foreach ($jobs as $job) {
    if ($since > 0 && (int) $job->timemodified < $since) {
        continue;
    }
    $summaries[] = $reporter->summarise($job, $full);
    if ($limit > 0 && count($summaries) >= $limit) {
        break;
    }
}

if ($options['json']) {
    echo json_encode($summaries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo $reporter->to_table($summaries);
}
exit(0);
