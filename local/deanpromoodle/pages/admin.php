<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin page for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check access
require_login();
require_capability('local/deanpromoodle:viewadmin', context_system::instance());

// Set up page
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/admin.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('adminpagetitle', 'local_deanpromoodle'));
$PAGE->set_heading(get_string('adminpagetitle', 'local_deanpromoodle'));
$PAGE->set_pagelayout('admin');

// Output page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adminpagetitle', 'local_deanpromoodle'));

// Page content
echo html_writer::start_div('local-deanpromoodle-admin-content');
echo html_writer::tag('p', get_string('adminpagecontent', 'local_deanpromoodle'));
echo html_writer::end_div();

echo $OUTPUT->footer();
