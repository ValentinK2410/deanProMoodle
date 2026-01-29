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
 * Teacher page for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define path to Moodle config
// From pages/teacher.php: ../ (to deanpromoodle) -> ../ (to local) -> ../ (to moodle root) = ../../../config.php
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    die('Error: Moodle config.php not found at: ' . $configpath);
}

require_once($configpath);

// Check access
require_login();

// Check if plugin is installed
if (!file_exists($CFG->dirroot . '/local/deanpromoodle/version.php')) {
    die('Error: Plugin not found. Please install the plugin through Moodle admin interface.');
}

// Check access - try capability first, then check user roles
$context = context_system::instance();
$hasaccess = false;

// Check capability
if (has_capability('local/deanpromoodle:viewteacher', $context)) {
    $hasaccess = true;
} else {
    // Fallback: check if user has teacher/editingteacher/manager role
    global $USER;
    $roles = get_user_roles($context, $USER->id, false);
    $teacherroles = ['teacher', 'editingteacher', 'manager', 'coursecreator'];
    foreach ($roles as $role) {
        if (in_array($role->shortname, $teacherroles)) {
            $hasaccess = true;
            break;
        }
    }
    
    // Also check system roles
    if (!$hasaccess) {
        $systemcontext = context_system::instance();
        $systemroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($systemroles as $role) {
            if (in_array($role->shortname, $teacherroles)) {
                $hasaccess = true;
                break;
            }
        }
    }
    
    // Allow access for all logged-in users if capability is not set (for testing)
    // Remove this after capabilities are properly assigned
    if (!$hasaccess && !isguestuser()) {
        $hasaccess = true; // Temporary: allow all logged-in users
    }
}

if (!$hasaccess) {
    require_capability('local/deanpromoodle:viewteacher', $context);
}

// Set up page
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/teacher.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_heading(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_pagelayout('standard');

// Output page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacherpagetitle', 'local_deanpromoodle'));

// Page content
echo html_writer::start_div('local-deanpromoodle-teacher-content');
echo html_writer::tag('p', get_string('teacherpagecontent', 'local_deanpromoodle'));
echo html_writer::end_div();

echo $OUTPUT->footer();
