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
 * Conversion jobs: every analyse and build job on the site with live progress, batch totals and a
 * printable error log of failures and warnings (print=1).
 *
 * @package    tool_courseuplift
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_courseuplift\local\job_dashboard;
use tool_courseuplift\local\job_manager;
use tool_courseuplift\output\jobs_view;

$userid = optional_param('user', 0, PARAM_INT);
$kind = optional_param('kind', '', PARAM_ALPHA);
$status = optional_param('status', '', PARAM_ALPHA);
$period = optional_param('period', 'week', PARAM_ALPHA);
$print = optional_param('print', false, PARAM_BOOL);

admin_externalpage_setup('tool_courseuplift_jobs');
$context = context_system::instance();
require_capability('tool/courseuplift:use', $context);

// Ignore unknown filter values rather than failing.
$kinds = [job_manager::KIND_ANALYSE, job_manager::KIND_BUILD];
$statuses = [job_manager::STATUS_QUEUED, job_manager::STATUS_RUNNING, job_manager::STATUS_DONE, job_manager::STATUS_FAILED];
$kind = in_array($kind, $kinds, true) ? $kind : '';
$status = in_array($status, $statuses, true) ? $status : '';
$period = array_key_exists($period, job_dashboard::PERIODS) ? $period : 'week';

$filters = ['user' => $userid, 'kind' => $kind, 'status' => $status, 'period' => $period];
$url = new moodle_url('/admin/tool/courseuplift/jobs.php', $filters);
$PAGE->set_url($url);
$PAGE->set_title(get_string('jobsheading', 'tool_courseuplift'));
$PAGE->set_heading(get_string('jobsheading', 'tool_courseuplift'));
if ($print) {
    $PAGE->set_pagelayout('print');
}

$dashboard = new job_dashboard();
$now = time();
$summaries = $dashboard->summaries($userid, $kind, $status, job_dashboard::period_start($period, $now));
$totals = $dashboard->totals($summaries);
$datefmt = get_string('strftimedatetimeshort', 'langconfig');

// Names of the people who started the listed jobs, for the table and the filter.
$userids = array_unique(array_column($summaries, 'userid'));
if ($userid > 0) {
    $userids[] = $userid;
}
$namefields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
$users = $userids ? $DB->get_records_list('user', 'id', $userids, '', $namefields) : [];
$username = function (int $id) use ($users): string {
    return isset($users[$id]) ? fullname($users[$id]) : get_string('unknownuser');
};

// Keep refreshing while conversions are in flight (not on the printable log). This must be set
// before the header, which writes the refresh into the page head.
$refreshdelay = (!$print && $dashboard->has_active($summaries)) ? 10 : 0;
if ($refreshdelay) {
    $PAGE->set_periodic_refresh_delay($refreshdelay);
}

echo $OUTPUT->header();

if ($print) {
    // Printable error log: totals, then every failure and warning, newest job first.
    echo $OUTPUT->heading(get_string('errorlogheading', 'tool_courseuplift'));
    $periodlabel = get_string('period_' . $period, 'tool_courseuplift');
    echo html_writer::tag('p', get_string('errorloggenerated', 'tool_courseuplift', (object) [
        'date' => userdate($now, $datefmt),
        'site' => format_string($SITE->fullname),
        'period' => $periodlabel,
    ]));
    echo jobs_view::counters($totals);
    $entries = $dashboard->log_entries($summaries);
    if (!$entries) {
        echo $OUTPUT->notification(get_string('errorlognone', 'tool_courseuplift'), 'success');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable tool_courseuplift-errorlog';
        $table->head = [
            get_string('jobscoljob', 'tool_courseuplift'),
            get_string('jobscolpackage', 'tool_courseuplift'),
            get_string('jobscolcourse', 'tool_courseuplift'),
            get_string('errorlogcolseverity', 'tool_courseuplift'),
            get_string('errorlogcolmessage', 'tool_courseuplift'),
        ];
        foreach ($entries as $entry) {
            $table->data[] = [
                $entry['jobid'],
                s($entry['package']),
                jobs_view::course_link($entry['courseid']),
                get_string('severity_' . $entry['severity'], 'tool_courseuplift'),
                s($entry['message']),
            ];
        }
        echo html_writer::table($table);
    }
    $printbutton = html_writer::tag('button', get_string('errorlogprint', 'tool_courseuplift'), [
        'type' => 'button',
        'class' => 'btn btn-primary tool_courseuplift-printbutton',
    ]);
    echo html_writer::div(
        $printbutton . ' ' . html_writer::link(
            new moodle_url('/admin/tool/courseuplift/jobs.php', $filters),
            get_string('errorlogback', 'tool_courseuplift'),
            ['class' => 'btn btn-secondary']
        ),
        'tool_courseuplift-noprint mt-3'
    );
    $PAGE->requires->js_amd_inline("document.querySelector('.tool_courseuplift-printbutton')"
        . ".addEventListener('click', () => window.print());");
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->heading(get_string('jobsheading', 'tool_courseuplift'));

// Filters: a plain GET form, so each view can be bookmarked or shared.
$useroptions = [0 => get_string('jobsfilteranyone', 'tool_courseuplift')];
foreach ($users as $user) {
    $useroptions[$user->id] = fullname($user);
}
$kindoptions = ['' => get_string('jobsfilteranykind', 'tool_courseuplift')];
foreach ($kinds as $option) {
    $kindoptions[$option] = get_string('kind_' . $option, 'tool_courseuplift');
}
$statusoptions = ['' => get_string('jobsfilteranystatus', 'tool_courseuplift')];
foreach ($statuses as $option) {
    $statusoptions[$option] = get_string('status_' . $option, 'tool_courseuplift');
}
$periodoptions = [];
foreach (array_keys(job_dashboard::PERIODS) as $option) {
    $periodoptions[$option] = get_string('period_' . $option, 'tool_courseuplift');
}
$selects = [
    'period' => [$periodoptions, $period, 'jobsfilterperiod'],
    'user' => [$useroptions, $userid, 'jobsfilteruser'],
    'kind' => [$kindoptions, $kind, 'jobsfilterkind'],
    'status' => [$statusoptions, $status, 'jobsfilterstatus'],
];
$fields = '';
foreach ($selects as $name => [$options, $selected, $labelkey]) {
    $id = 'tool_courseuplift-jobs-' . $name;
    $fields .= html_writer::div(
        html_writer::label(get_string($labelkey, 'tool_courseuplift'), $id, true, ['class' => 'mr-1 me-1']) .
        html_writer::select($options, $name, $selected, false, ['id' => $id, 'class' => 'custom-select form-select']),
        'mr-3 me-3 mb-2'
    );
}
$fields .= html_writer::tag('button', get_string('jobsfilterapply', 'tool_courseuplift'), [
    'type' => 'submit',
    'class' => 'btn btn-secondary mb-2',
]);
echo html_writer::tag('form', html_writer::div($fields, 'd-flex flex-wrap align-items-center'), [
    'method' => 'get',
    'action' => (new moodle_url('/admin/tool/courseuplift/jobs.php'))->out(false),
    'class' => 'tool_courseuplift-jobs-filters mb-3',
]);

echo jobs_view::counters($totals);

$printurl = new moodle_url('/admin/tool/courseuplift/jobs.php', $filters + ['print' => 1]);
echo html_writer::div(
    html_writer::link($printurl, get_string('errorlog', 'tool_courseuplift'), ['class' => 'btn btn-outline-secondary']),
    'mb-3'
);

if ($refreshdelay) {
    echo html_writer::tag('p', get_string('jobsautorefresh', 'tool_courseuplift', $refreshdelay), ['class' => 'text-muted']);
}

if (!$summaries) {
    echo $OUTPUT->notification(get_string('jobsnone', 'tool_courseuplift'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable tool_courseuplift-jobs';
$table->head = [
    get_string('jobscoljob', 'tool_courseuplift'),
    get_string('jobscolpackage', 'tool_courseuplift'),
    get_string('jobscolstartedby', 'tool_courseuplift'),
    get_string('jobscolstatus', 'tool_courseuplift'),
    get_string('jobscolprogress', 'tool_courseuplift'),
    get_string('jobscolcourse', 'tool_courseuplift'),
    get_string('jobscolcreated', 'tool_courseuplift'),
    get_string('jobscolskipped', 'tool_courseuplift'),
    get_string('jobscolwarnings', 'tool_courseuplift'),
    get_string('jobscolupdated', 'tool_courseuplift'),
];
foreach ($summaries as $summary) {
    $percent = $summary['status'] === job_manager::STATUS_DONE ? 100 : $summary['progress'];
    $bar = html_writer::div(html_writer::div('', 'progress-bar', [
        'role' => 'progressbar',
        'style' => 'width: ' . $percent . '%;',
        'aria-valuenow' => $percent,
        'aria-valuemin' => 0,
        'aria-valuemax' => 100,
    ]), 'progress');
    $progresstext = $summary['progressmessage'] !== '' ? s($summary['progressmessage']) . ' ' : '';
    $progress = $bar . html_writer::div($progresstext . '(' . $percent . '%)', 'small text-muted');
    if ($summary['status'] === job_manager::STATUS_FAILED && $summary['errormsg'] !== '') {
        $progress .= html_writer::div(s($summary['errormsg']), 'small text-danger');
    }

    // The status page shows a job only to the person who started it, so link it only for them.
    $jobcell = $summary['id'];
    if ($summary['userid'] === (int) $USER->id) {
        $jobcell = html_writer::link(
            new moodle_url('/admin/tool/courseuplift/status.php', ['jobid' => $summary['id']]),
            $summary['id'],
            ['title' => get_string('jobsdetails', 'tool_courseuplift')]
        );
    }
    $badge = jobs_view::status_badge_class($summary['status']);

    $table->data[] = [
        $jobcell . html_writer::div(get_string('kind_' . $summary['kind'], 'tool_courseuplift'), 'small text-muted'),
        s($summary['package']),
        s($username($summary['userid'])),
        html_writer::span(get_string('status_' . $summary['status'], 'tool_courseuplift'), $badge),
        $progress,
        jobs_view::course_link($summary['courseid']),
        $summary['created'] ?? '-',
        $summary['skipped'] ?? '-',
        isset($summary['warnings']) ? count($summary['warnings']) : '-',
        userdate($summary['timemodified'], $datefmt),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
