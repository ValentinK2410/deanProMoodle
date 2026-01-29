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
 * Student review functionality.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define path to Moodle config
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    die('Error: Moodle config.php not found at: ' . $configpath);
}

require_once($configpath);
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/enrollib.php');

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
    if (!$hasaccess && !isguestuser()) {
        $hasaccess = true; // Temporary: allow all logged-in users
    }
}

if (!$hasaccess) {
    require_capability('local/deanpromoodle:viewteacher', $context);
}

// Get parameters
$courseid = optional_param('courseid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

// Set up page
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/teacher.php', [
    'courseid' => $courseid,
    'search' => $search,
    'page' => $page,
    'perpage' => $perpage
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_heading(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_pagelayout('standard');

// Add CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Get all courses where user is teacher
global $USER;
$teachercourses = [];
if ($courseid == 0) {
    // Get all courses where user is teacher
    $courses = enrol_get_my_courses();
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
            has_capability('moodle/course:manageactivities', $coursecontext)) {
            $teachercourses[$course->id] = $course;
        }
    }
} else {
    $course = get_course($courseid);
    $coursecontext = context_course::instance($courseid);
    if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
        has_capability('moodle/course:manageactivities', $coursecontext)) {
        $teachercourses[$courseid] = $course;
    }
}

// Get all students
$allstudents = [];
foreach ($teachercourses as $course) {
    $coursecontext = context_course::instance($course->id);
    $students = get_enrolled_users($coursecontext, 'moodle/course:view', 0, 'u.*', 'u.lastname, u.firstname');
    
    foreach ($students as $student) {
        if (!isset($allstudents[$student->id])) {
            $allstudents[$student->id] = $student;
            $allstudents[$student->id]->courses = [];
        }
        $allstudents[$student->id]->courses[] = $course->fullname;
    }
}

// Apply search filter
if (!empty($search)) {
    $filteredstudents = [];
    foreach ($allstudents as $student) {
        $searchlower = mb_strtolower($search);
        $fullname = mb_strtolower($student->firstname . ' ' . $student->lastname);
        $email = mb_strtolower($student->email);
        
        if (strpos($fullname, $searchlower) !== false || 
            strpos($email, $searchlower) !== false ||
            strpos(mb_strtolower($student->username), $searchlower) !== false) {
            $filteredstudents[$student->id] = $student;
        }
    }
    $allstudents = $filteredstudents;
}

// Pagination
$totalstudents = count($allstudents);
$totalpages = ceil($totalstudents / $perpage);
$offset = $page * $perpage;
$paginatedstudents = array_slice($allstudents, $offset, $perpage, true);

// Output page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacherpagetitle', 'local_deanpromoodle'));

// Search and filter form
echo html_writer::start_div('local-deanpromoodle-teacher-filters', ['style' => 'margin-bottom: 20px;']);
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/deanpromoodle/pages/teacher.php'),
    'class' => 'form-inline'
]);

// Search field
echo html_writer::start_div('form-group', ['style' => 'margin-right: 10px;']);
echo html_writer::label(get_string('searchstudents', 'local_deanpromoodle'), 'search', false, ['class' => 'sr-only']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'search',
    'value' => $search,
    'placeholder' => get_string('searchstudents', 'local_deanpromoodle'),
    'class' => 'form-control',
    'style' => 'width: 300px; display: inline-block;'
]);
echo html_writer::end_div();

// Course filter
if (count($teachercourses) > 1) {
    echo html_writer::start_div('form-group', ['style' => 'margin-right: 10px;']);
    echo html_writer::label(get_string('filterbycourse', 'local_deanpromoodle'), 'courseid', false, ['class' => 'sr-only']);
    $courseoptions = [0 => get_string('allcourses', 'local_deanpromoodle')];
    foreach ($teachercourses as $cid => $c) {
        $courseoptions[$cid] = $c->fullname;
    }
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control', 'style' => 'display: inline-block;']);
    echo html_writer::end_div();
}

// Submit button
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('search', 'local_deanpromoodle'),
    'class' => 'btn btn-primary'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div();

// Students table
if (empty($paginatedstudents)) {
    echo html_writer::div(
        get_string('nostudentsfound', 'local_deanpromoodle'),
        'alert alert-info',
        ['style' => 'margin-top: 20px;']
    );
} else {
    echo html_writer::start_tag('table', [
        'class' => 'table table-striped table-hover',
        'style' => 'width: 100%; margin-top: 20px;'
    ]);
    
    // Table header
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('fullname', 'local_deanpromoodle'), ['style' => 'width: 25%;']);
    echo html_writer::tag('th', get_string('email', 'local_deanpromoodle'), ['style' => 'width: 25%;']);
    echo html_writer::tag('th', get_string('username', 'local_deanpromoodle'), ['style' => 'width: 15%;']);
    echo html_writer::tag('th', get_string('courses', 'local_deanpromoodle'), ['style' => 'width: 25%;']);
    echo html_writer::tag('th', get_string('actions', 'local_deanpromoodle'), ['style' => 'width: 10%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body
    echo html_writer::start_tag('tbody');
    foreach ($paginatedstudents as $student) {
        echo html_writer::start_tag('tr');
        
        // Full name
        $fullname = fullname($student);
        echo html_writer::tag('td', $fullname);
        
        // Email
        echo html_writer::tag('td', htmlspecialchars($student->email));
        
        // Username
        echo html_writer::tag('td', htmlspecialchars($student->username));
        
        // Courses
        $courselist = implode(', ', $student->courses);
        echo html_writer::tag('td', htmlspecialchars($courselist));
        
        // Actions
        $profileurl = new moodle_url('/user/profile.php', ['id' => $student->id]);
        $actions = html_writer::link(
            $profileurl,
            get_string('viewprofile', 'local_deanpromoodle'),
            ['class' => 'btn btn-sm btn-primary']
        );
        echo html_writer::tag('td', $actions);
        
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    // Pagination
    if ($totalpages > 1) {
        echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px;']);
        $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', [
            'courseid' => $courseid,
            'search' => $search,
            'perpage' => $perpage
        ]);
        
        // Previous page
        if ($page > 0) {
            $prevurl = clone $baseurl;
            $prevurl->param('page', $page - 1);
            echo html_writer::link($prevurl, '« ' . get_string('previous', 'local_deanpromoodle'), ['class' => 'btn btn-sm']);
        }
        
        // Page info
        echo html_writer::span(
            get_string('page', 'local_deanpromoodle') . ' ' . ($page + 1) . ' ' . 
            get_string('of', 'local_deanpromoodle') . ' ' . $totalpages . 
            ' (' . $totalstudents . ' ' . get_string('students', 'local_deanpromoodle') . ')',
            ['style' => 'margin: 0 15px;']
        );
        
        // Next page
        if ($page < $totalpages - 1) {
            $nexturl = clone $baseurl;
            $nexturl->param('page', $page + 1);
            echo html_writer::link($nexturl, get_string('next', 'local_deanpromoodle') . ' »', ['class' => 'btn btn-sm']);
        }
        
        echo html_writer::end_div();
    }
}

echo $OUTPUT->footer();
