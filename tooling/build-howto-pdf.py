#!/usr/bin/env python3
# This file is part of Moodle - https://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
"""Build docs/bulk-canvas-migration-howto.pdf, the printable bulk-migration guide.

Usage (needs ReportLab: pip install reportlab):

    python3 tooling/build-howto-pdf.py [output.pdf]

Edit the content below and re-run it whenever the bulk workflow changes, then
commit the regenerated PDF together with this script.

@package    tool_courseuplift
@copyright  2026 Vernon Spain
@license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
"""

import os
import sys
from datetime import date

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (HRFlowable, KeepTogether, PageBreak, Paragraph, Preformatted, SimpleDocTemplate, Spacer, Table,
                                TableStyle)

# What this edition of the guide covers: both plugins support Moodle 5.0-5.3 LTS from these releases.
APPLIES_TO = 'CourseUplift 1.3.0+ and Automate 1.4.2+, on Moodle 5.0&#8211;5.3 LTS'
TITLE = 'Bulk Canvas to Moodle Migration'
FOOTER_LEFT = 'tool_courseuplift &#183; tool_automate'

ACCENT = colors.HexColor('#8a2318')
RULE = colors.HexColor('#c0392b')
TEXT = colors.HexColor('#333333')
MUTED = colors.HexColor('#5f6b75')
LINE = colors.HexColor('#d9dde1')
CALLOUT_BG = colors.HexColor('#fbeceb')
CALLOUT_EDGE = colors.HexColor('#e8b4ae')
CODE_BG = colors.HexColor('#f1f2f4')

ARROW = '<font face="Symbol">→</font>'

base = ParagraphStyle('base', fontName='Helvetica', fontSize=9.5, leading=14.5, textColor=TEXT, alignment=TA_LEFT,
                      spaceAfter=5)
styles = {
    'title': ParagraphStyle('title', base, fontName='Helvetica-Bold', fontSize=28, leading=34, textColor=ACCENT),
    'subtitle': ParagraphStyle('subtitle', base, fontSize=13, leading=18, textColor=MUTED),
    'lead': ParagraphStyle('lead', base, fontSize=11, leading=16, textColor=MUTED),
    'h1': ParagraphStyle('h1', base, fontName='Helvetica-Bold', fontSize=17, leading=22, textColor=ACCENT,
                         spaceBefore=6, spaceAfter=8),
    'h2': ParagraphStyle('h2', base, fontName='Helvetica-Bold', fontSize=11.5, leading=15, spaceBefore=6, spaceAfter=4),
    'step': ParagraphStyle('step', base, fontName='Helvetica-Bold', fontSize=10.5, leading=14, textColor=ACCENT,
                           spaceBefore=5, spaceAfter=2),
    'body': base,
    'bullet': ParagraphStyle('bullet', base, leftIndent=14, bulletIndent=4, spaceAfter=2),
    'cell': ParagraphStyle('cell', base, fontSize=8.8, leading=13, spaceAfter=0),
    'cellhead': ParagraphStyle('cellhead', base, fontName='Helvetica-Bold', fontSize=8.8, leading=13, spaceAfter=0),
    'callouthead': ParagraphStyle('callouthead', base, fontName='Helvetica-Bold', fontSize=8.8, leading=12,
                                  textColor=ACCENT, spaceAfter=2),
    'callout': ParagraphStyle('callout', base, fontSize=8.8, leading=13, textColor=MUTED, spaceAfter=3),
    'code': ParagraphStyle('code', base, fontName='Courier', fontSize=7.8, leading=11),
    'small': ParagraphStyle('small', base, fontSize=7.5, leading=11, textColor=MUTED),
}


def code(text):
    """Inline monospace."""
    return '<font face="Courier">%s</font>' % text


def path(text):
    """A Moodle navigation path, in italics."""
    return '<i>%s</i>' % text


def para(text, style='body'):
    """A paragraph in one of the named styles."""
    return Paragraph(text, styles[style])


def bullets(items):
    """A bulleted list."""
    return [Paragraph(item, styles['bullet'], bulletText='•') for item in items]


def step(title, text):
    """A numbered step heading and its text, kept together."""
    return KeepTogether([para(title, 'step'), para(text)])


def callout(title, text):
    """A tinted box with a heading, for warnings and asides."""
    inner = [para(title, 'callouthead')]
    inner += [para(t, 'callout') for t in (text if isinstance(text, list) else [text])]
    box = Table([[inner]], colWidths=[None])
    box.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), CALLOUT_BG),
        ('BOX', (0, 0), (-1, -1), 0.5, CALLOUT_EDGE),
        ('LINEBEFORE', (0, 0), (0, -1), 2.5, RULE),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 7),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
    ]))
    return KeepTogether([Spacer(1, 4), box, Spacer(1, 8)])


def keyvalue(rows, firstwidth=48 * mm):
    """A two-column table of bold labels and their text, with light rules between rows."""
    data = [[para(k, 'cellhead'), para(v, 'cell')] for k, v in rows]
    table = Table(data, colWidths=[firstwidth, None])
    table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LINEBELOW', (0, 0), (-1, -2), 0.5, LINE),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 8),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ]))
    return table


def codeblock(text):
    """A shaded block of command-line examples."""
    box = Table([[Preformatted(text, styles['code'])]], colWidths=[None])
    box.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), CODE_BG),
        ('BOX', (0, 0), (-1, -1), 0.5, LINE),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
    ]))
    return KeepTogether([Spacer(1, 2), box, Spacer(1, 8)])


def cover(generated):
    """Page 1."""
    rule = Table([['']], colWidths=[62 * mm], rowHeights=[1.6])
    rule.setStyle(TableStyle([('BACKGROUND', (0, 0), (-1, -1), RULE), ('LEFTPADDING', (0, 0), (-1, -1), 0)]))
    rule.hAlign = 'LEFT'
    return [
        Spacer(1, 48 * mm),
        para('Bulk Canvas %s Moodle Migration' % ARROW, 'title'),
        para('A step-by-step guide for administrators', 'subtitle'),
        Spacer(1, 6 * mm), rule, Spacer(1, 4 * mm),
        para('Migrating many courses off Canvas at once with the <b>CourseUplift</b> (%s) and <b>Automate</b> (%s) '
             'Moodle admin tools.' % (code('tool_courseuplift'), code('tool_automate')), 'lead'),
        Spacer(1, 28 * mm),
        keyvalue([
            ('Applies to', APPLIES_TO),
            ('Audience', 'Site administrators and managers running a Canvas migration'),
            ('Generated', generated),
        ], firstwidth=40 * mm),
        PageBreak(),
    ]


def overview():
    """Sections 1 and 2."""
    return [
        para('1.&#160; Overview', 'h1'),
        para('CourseUplift imports a single Canvas Common Cartridge (%s) export into Moodle: it can <b>analyse</b> a '
             'package (report what it contains and how cleanly each part maps to Moodle) or <b>build</b> a brand-new '
             'Moodle course from it. For a handful of courses you can drive it by hand from its own upload page.'
             % code('.imscc')),
        para('When you are moving <b>many</b> courses, the Automate plugin drives CourseUplift in bulk. You feed it a '
             'list of Canvas backup URLs and/or a server directory of packages; Automate turns each package into one '
             'background CourseUplift job. This guide covers that bulk workflow end to end.'),
        para('The two ways each package can be handled', 'h2'),
        keyvalue([
            ('Build courses now', 'A new, hidden Moodle course is created from each package automatically. Best '
                                  'when you trust the packages and want courses ready to review.'),
            ('Analyse for later', 'Each package is fetched and a conversion report produced, but no course is built. '
                                  'An administrator reviews each report and builds the course manually from '
                                  'CourseUplift when ready.'),
        ]),
        callout('Why background jobs?', [
            'Nothing is converted inside your browser request. Every package becomes a background adhoc task, so a '
            'directory or URL list of hundreds of courses is queued instantly and then worked through by cron '
            'instead of timing out the page.',
            'CourseUplift caps how many run at once with its <b>Maximum simultaneous conversions</b> setting '
            '(default 2 analyse jobs and 2 build jobs), so a large batch never takes every cron worker and the rest '
            'of the site\'s background work, such as notifications, keeps flowing. Jobs beyond the limit wait in the '
            'queue and start as others finish.',
        ]),
        para('2.&#160; Before you start', 'h1'),
    ] + bullets([
        '<b>Both plugins installed.</b> CourseUplift provides the import engine; Automate provides the bulk driver. '
        'Automate detects CourseUplift automatically &#8212; the bulk-import feature only appears when it is present.',
        '<b>Capabilities.</b> You need %s to run Automate, and %s for the import itself and the '
        '<i>Conversion jobs</i> page. Building courses additionally needs %s in the target category.'
        % (code('tool/automate:manage'), code('tool/courseuplift:use'), code('moodle/course:create')),
        '<b>Cron running.</b> The imports run as background tasks, so Moodle cron must be active '
        '(%s).' % path('Site administration &gt; Server &gt; Tasks &gt; Task processing'),
        '<b>Trusted packages only.</b> Imported HTML is stored and displayed as authored. Only import %s packages '
        'from sources you trust.' % code('.imscc'),
    ]) + [
        callout('Install or upgrade without locking the site', [
            'While any plugin installs or upgrades through <i>Notifications</i>, Moodle shows "Site is being '
            'upgraded, please retry later" to everyone and pauses cron until it finishes. To avoid that, run the '
            'upgrade from the command line. When the first command exits with code 3 (maintenance not required, as '
            'for these plugins), the second upgrades while users carry on working:',
            code('php admin/cli/upgrade.php --is-maintenance-required') + '<br/>'
            + code('php admin/cli/upgrade.php --non-interactive --maintenance=false'),
        ]),
        PageBreak(),
    ]


def setup():
    """Sections 3 and 4."""
    return [
        para('3.&#160; Turn on bulk Canvas import', 'h1'),
        para('The feature ships behind an off-by-default kill-switch. Enable it once:'),
        step('Step 1 &#8212; Open Automate\'s settings',
             'Go to %s.' % path('Site administration &gt; Plugins &gt; Admin tools &gt; Automate &gt; Settings')),
        step('Step 2 &#8212; Allow bulk Canvas import',
             'Tick <b>Allow bulk Canvas import</b>. The background import task re-checks this switch before it '
             'runs, so clearing it later also stops any still-pending bulk import from starting &#8212; but it does '
             'not cancel a CourseUplift job that has already been created, so a build already under way still '
             'finishes.'),
        step('Step 3 &#8212; (Optional) set a source directory',
             'If you will import from a folder of packages on the server, set <b>Bulk Canvas import source '
             'directory</b> to that folder (a staging area holding %s / %s files). You can skip this if you only '
             'import from URLs.' % (code('.imscc'), code('.zip'))),
        step('Step 4 &#8212; (Optional) choose how many conversions run at once',
             'In %s, set <b>Maximum simultaneous conversions</b>. The default of 2 suits most sites; raise it on a '
             'server with spare cron capacity to finish a large batch sooner, or lower it to 1 to leave more room for '
             'other background work. The limit applies to analyse jobs and build jobs separately.'
             % path('Site administration &gt; Plugins &gt; Admin tools &gt; CourseUplift')),
        para('4.&#160; Prepare your packages', 'h1'),
        para('Option A &#8212; A list of Canvas backup URLs', 'h2'),
        para('In Canvas, export each course as a Common Cartridge and collect the download URLs &#8212; one per '
             'line. Each is fetched in the background.'),
        codeblock('https://school.instructure.com/exports/course-101.imscc\n'
                  'https://school.instructure.com/exports/course-102.imscc\n'
                  'https://school.instructure.com/exports/course-103.imscc'),
        callout('Security: server-side URL fetch (SSRF)',
                'When you import from a download URL, the site fetches it server-side through Moodle\'s SSRF-aware '
                'cURL layer, governed by %s. On Moodle 5.0+ these settings block loopback and private ranges by '
                'default. Keep those defaults; uploading the file directly avoids the fetch entirely.'
                % path('Site administration &gt; Security &gt; HTTP security')),
        para('Option B &#8212; A directory of packages', 'h2'),
        para('Copy the exported %s files into the source directory you configured in step 3. The picker searches '
             'server-side and type-to-filters, so it scales to hundreds of files.' % code('.imscc')),
        para('You can use either source, or both together in one run.'),
        PageBreak(),
    ]


def run():
    """Sections 5 and 6."""
    return [
        para('5.&#160; Run a bulk import', 'h1'),
        step('Step 1 &#8212; Open the bulk import page',
             'Go to %s.' % path('Plugins &gt; Admin tools &gt; Automate &gt; Bulk Canvas import')),
        step('Step 2 &#8212; Choose your sources',
             'Paste your URL list into the text area, and/or pick packages from the server directory. Preview the '
             'selection before queueing.'),
        KeepTogether([para('Step 3 &#8212; Choose what happens to each package', 'step')] + bullets([
            '<b>Build courses now</b> &#8212; a new course is created from each package (needs course-create '
            'permission in the target category).',
            '<b>Analyse for later</b> &#8212; each package is analysed only, ready for you to review and build '
            'manually.',
        ])),
        step('Step 4 &#8212; Set the target category and options',
             'Pick the course category new courses land in. Optional build settings (such as building a runnable '
             'quiz from each standalone question bank, or combining consecutive pages into a book/lesson) carry '
             'through to each job.'),
        step('Step 5 &#8212; Queue the jobs and follow them',
             'Confirm to queue every package as a background job. Follow the whole batch on CourseUplift\'s '
             '<b>Conversion jobs</b> page (%s) &#8212; see section 8. The tasks also appear in %s as <b>Analyse '
             'Canvas package</b> and <b>Build course from Canvas package</b> (queued under <b>Bulk Canvas '
             'import</b>).'
             % (path('Site administration &gt; Courses &gt; Conversion jobs'),
                path('Site administration &gt; Server &gt; Tasks &gt; Task logs'))),
        para('6.&#160; Review &amp; build staged imports', 'h1'),
        para('Packages you queued with <b>Analyse for later</b> appear under %s once analysed. From there you can:'
             % path('Automate &gt; Staged Canvas imports')),
    ] + bullets([
        '<b>Review a report and build the course</b> &#8212; open a completed analyse job to reach CourseUplift\'s '
        'status page, read the conversion report, and use <b>Build this course</b> when you are happy.',
        '<b>Jump to a built course</b> &#8212; a completed build links straight to the course it created.',
        '<b>See storage used</b> &#8212; the top of the page shows how much space your stored packages occupy.',
        '<b>Reclaim space</b> &#8212; tick finished imports and <b>Delete selected</b> to free their stored '
        'packages.',
    ]) + [
        callout('Deleting is safe',
                'Deleting a finished import frees its stored %s package <b>once no other job still references it</b> '
                '(an analyse job and the build made from it share one package, so it is freed only when the last of '
                'them is deleted) but <b>keeps any course a build already created</b> &#8212; it reclaims cache, it '
                'does not undo an import. Only finished (done or failed) imports can be deleted, so a delete never '
                'interrupts an in-flight conversion.' % code('.imscc')),
        PageBreak(),
    ]


def cli_and_monitor():
    """Sections 7 and 8, and the closing note."""
    return [
        para('7.&#160; Driving it from the command line', 'h1'),
        para('The same bulk job can run from the CLI &#8212; handy for very large migrations or scripting. Run with '
             '%s for all options, or %s to inspect the sources without queueing.' % (code('--help'), code('--list'))),
        codeblock('# Build a course from every URL in a file, into category 5:\n'
                  'php admin/tool/automate/cli/import_canvas.php \\\n'
                  '    --urls=/data/urls.txt --category=5 --mode=build --execute\n'
                  '\n'
                  '# Analyse a directory of packages for later review:\n'
                  'php admin/tool/automate/cli/import_canvas.php \\\n'
                  '    --source=/data/canvas --category=5 --mode=analyse --execute\n'
                  '\n'
                  '# Inspect what would be queued, without queueing anything:\n'
                  'php admin/tool/automate/cli/import_canvas.php --urls=/data/urls.txt --list\n'
                  '\n'
                  '# Follow the jobs (add --json for scripts, --status=failed for failures only):\n'
                  'php admin/tool/courseuplift/cli/jobs.php'),
        para('8.&#160; Monitor &amp; troubleshoot', 'h1'),
        para('CourseUplift\'s <b>Conversion jobs</b> page (%s) lists every analyse and build job on the site:'
             % path('Site administration &gt; Courses &gt; Conversion jobs')),
    ] + bullets([
        '<b>Totals</b> &#8212; queued, running, done and failed, plus items created, items skipped and warnings.',
        '<b>Live progress</b> &#8212; each job\'s progress bar and current step, the course a build created and its '
        'counts. The page refreshes itself every 10 seconds while anything is queued or running.',
        '<b>Filters</b> &#8212; by time (last 24 hours, 7 days, month or all time), who started the job, type and '
        'status. Each view has its own address, so it can be bookmarked or shared.',
        '<b>Printable error log</b> &#8212; every failure and warning, to print or save as a PDF.',
    ]) + [
        Spacer(1, 6),
        keyvalue([
            ('A job failed', 'The <i>Conversion jobs</i> page and its printable error log show the error. Failed '
                             'jobs can be deleted from <i>Staged Canvas imports</i> and re-queued.'),
            ('Jobs sit in the queue', 'Expected: only <b>Maximum simultaneous conversions</b> of each type run at '
                                      'once, and the rest start as those finish. Raise the limit if the server has '
                                      'spare capacity, and check that cron is running.'),
            ('Quiz has no questions', 'Canvas sometimes exports a quiz shell without its item bank. It builds as a '
                                      'hidden placeholder; re-export from Canvas with item banks included.'),
            ('A URL will not fetch', 'Check %s &#8212; the host or port may be blocked. Upload the file directly to '
                                     'bypass the server-side fetch.' % path('Security &gt; HTTP security')),
            ('"Site is being upgraded"', 'A plugin install or upgrade is running through <i>Notifications</i>. Use '
                                         'the command-line upgrade in section 2 to avoid locking the site.'),
            ('Feature missing', 'Confirm both plugins are installed and <b>Allow bulk Canvas import</b> is ticked. '
                                'Use Automate 1.4.2+ with CourseUplift 1.3.0+.'),
        ]),
        Spacer(1, 6),
        HRFlowable(width='100%', thickness=0.5, color=LINE),
        Spacer(1, 5),
        para('CourseUplift and Automate are independent interoperability tools. "Canvas" is a trademark of '
             'Instructure, Inc.; these tools are not affiliated with or endorsed by Instructure. Distributed under the '
             'GNU GPL v3 or later.', 'small'),
    ]


def decorate(canvas, doc):
    """Running header (after the cover) and footer."""
    width, height = A4
    left, right = doc.leftMargin, width - doc.rightMargin
    canvas.saveState()
    canvas.setFillColor(MUTED)
    canvas.setStrokeColor(LINE)
    canvas.setLineWidth(0.5)
    if doc.page > 1:
        header = Paragraph('Bulk Canvas %s Moodle Migration' % ARROW, styles['small'])
        header.wrapOn(canvas, 120 * mm, 10 * mm)
        header.drawOn(canvas, left, height - 17 * mm)
        canvas.setFont('Helvetica', 7.5)
        canvas.drawRightString(right, height - 16 * mm, 'CourseUplift')
        canvas.line(left, height - 18.5 * mm, right, height - 18.5 * mm)
    canvas.line(left, 16.5 * mm, right, 16.5 * mm)
    footer = Paragraph(FOOTER_LEFT, styles['small'])
    footer.wrapOn(canvas, 120 * mm, 10 * mm)
    footer.drawOn(canvas, left, 12 * mm)
    canvas.setFont('Helvetica', 7.5)
    canvas.drawRightString(right, 12.8 * mm, 'Page %d' % doc.page)
    canvas.restoreState()


def main():
    """Write the PDF."""
    here = os.path.dirname(os.path.abspath(__file__))
    out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(here, '..', 'docs', 'bulk-canvas-migration-howto.pdf')
    generated = date.today().strftime('%d/%m/%Y')
    doc = SimpleDocTemplate(out, pagesize=A4, leftMargin=22 * mm, rightMargin=22 * mm, topMargin=24 * mm,
                            bottomMargin=22 * mm, title='Bulk Canvas to Moodle Migration - How-to',
                            author='tool_courseuplift', subject='Bulk Canvas migration guide', invariant=1)
    story = cover(generated) + overview() + setup() + run() + cli_and_monitor()
    doc.build(story, onFirstPage=decorate, onLaterPages=decorate)
    print('Wrote %s' % os.path.normpath(out))


if __name__ == '__main__':
    main()
