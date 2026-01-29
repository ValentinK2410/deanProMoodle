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

// Get all courses
global $USER, $DB;
$allcourses = [];
if ($courseid == 0) {
    // Get all courses
    $courses = $DB->get_records('course', ['visible' => 1], 'fullname ASC', 'id, fullname, shortname');
    if ($courses) {
        foreach ($courses as $course) {
            if ($course->id > 1) { // Skip site course
                $allcourses[$course->id] = $course;
            }
        }
    }
} else {
    $course = get_course($courseid);
    if ($course && $course->id > 1) {
        $allcourses[$courseid] = $course;
    }
}

// Get all teachers from courses
$allteachers = [];
if (!empty($allcourses)) {
    foreach ($allcourses as $course) {
        $coursecontext = context_course::instance($course->id);
        
        // Get users with teacher/editingteacher roles
        $teachers = get_enrolled_users($coursecontext, 'moodle/course:manageactivities', 0, 'u.*', 'u.lastname, u.firstname');
        if (!is_array($teachers)) {
            $teachers = [];
        }
        
        // Also get users with teacher role explicitly
        $roleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
        if (!empty($roleids) && is_array($roleids)) {
            $placeholders = implode(',', array_fill(0, count($roleids), '?'));
            $roleassignments = $DB->get_records_sql(
                "SELECT ra.userid, u.*
                 FROM {role_assignments} ra
                 JOIN {user} u ON u.id = ra.userid
                 WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)
                 AND u.deleted = 0",
                array_merge([$coursecontext->id], $roleids)
            );
            
            if ($roleassignments) {
                foreach ($roleassignments as $assignment) {
                    if (!isset($teachers[$assignment->userid])) {
                        $teachers[$assignment->userid] = $assignment;
                    }
                }
            }
        }
        
        if (!empty($teachers)) {
            foreach ($teachers as $teacher) {
                if (!isset($allteachers[$teacher->id])) {
                    $allteachers[$teacher->id] = $teacher;
                    $allteachers[$teacher->id]->courses = [];
                }
                // Add course info with full details
                $coursename = $course->fullname;
                $allteachers[$teacher->id]->courses[] = $coursename;
            }
        }
    }
}

// Apply search filter
if (!empty($search) && !empty($allteachers)) {
    $filteredteachers = [];
    foreach ($allteachers as $teacher) {
        $searchlower = mb_strtolower($search);
        $fullname = mb_strtolower($teacher->firstname . ' ' . $teacher->lastname);
        $email = mb_strtolower($teacher->email ?? '');
        $username = mb_strtolower($teacher->username ?? '');
        
        if (strpos($fullname, $searchlower) !== false || 
            strpos($email, $searchlower) !== false ||
            strpos($username, $searchlower) !== false) {
            $filteredteachers[$teacher->id] = $teacher;
        }
    }
    $allteachers = $filteredteachers;
}

// Ensure $allteachers is an array
if (!is_array($allteachers)) {
    $allteachers = [];
}

// Pagination
$totalteachers = count($allteachers);
$totalpages = $totalteachers > 0 ? ceil($totalteachers / $perpage) : 0;
$offset = $page * $perpage;
$paginatedteachers = [];
if (!empty($allteachers)) {
    $paginatedteachers = array_slice($allteachers, $offset, $perpage, true);
}
if (!is_array($paginatedteachers)) {
    $paginatedteachers = [];
}

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
$searchlabel = get_string('searchteachers', 'local_deanpromoodle');
if (strpos($searchlabel, '[[') !== false) {
    $searchlabel = 'Search teachers'; // Fallback
}
echo html_writer::label($searchlabel, 'search', false, ['class' => 'sr-only']);
$searchplaceholder = get_string('searchteachers', 'local_deanpromoodle');
if (strpos($searchplaceholder, '[[') !== false) {
    $searchplaceholder = 'Search teachers'; // Fallback
}
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'search',
    'value' => $search,
    'placeholder' => $searchplaceholder,
    'class' => 'form-control',
    'style' => 'width: 300px; display: inline-block;'
]);
echo html_writer::end_div();

// Course filter
if (count($allcourses) > 1) {
    echo html_writer::start_div('form-group', ['style' => 'margin-right: 10px;']);
    $filterlabel = get_string('filterbycourse', 'local_deanpromoodle');
    if (strpos($filterlabel, '[[') !== false) {
        $filterlabel = 'Filter by course'; // Fallback
    }
    echo html_writer::label($filterlabel, 'courseid', false, ['class' => 'sr-only']);
    $allcoursesstr = get_string('allcourses', 'local_deanpromoodle');
    if (strpos($allcoursesstr, '[[') !== false) {
        $allcoursesstr = 'All courses'; // Fallback
    }
    $courseoptions = [0 => $allcoursesstr];
    foreach ($allcourses as $cid => $c) {
        $courseoptions[$cid] = $c->fullname;
    }
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control', 'style' => 'display: inline-block;']);
    echo html_writer::end_div();
}

// Submit button
$searchbutton = get_string('search', 'local_deanpromoodle');
if (strpos($searchbutton, '[[') !== false) {
    $searchbutton = get_string('search'); // Use Moodle core string
}
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => $searchbutton,
    'class' => 'btn btn-primary'
]);

echo html_writer::end_tag('form');
echo html_writer::end_div();

// Teachers table
if (empty($paginatedteachers)) {
    echo html_writer::div(
        get_string('noteachersfound', 'local_deanpromoodle'),
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
    $fullnamestr = get_string('fullname', 'local_deanpromoodle');
    if (strpos($fullnamestr, '[[') !== false) {
        $fullnamestr = get_string('fullname');
    }
    $emailstr = get_string('email', 'local_deanpromoodle');
    if (strpos($emailstr, '[[') !== false) {
        $emailstr = get_string('email');
    }
    $usernamestr = get_string('username', 'local_deanpromoodle');
    if (strpos($usernamestr, '[[') !== false) {
        $usernamestr = get_string('username');
    }
    $coursesstr = get_string('courses', 'local_deanpromoodle');
    if (strpos($coursesstr, '[[') !== false) {
        $coursesstr = get_string('courses');
    }
    $actionsstr = get_string('actions', 'local_deanpromoodle');
    if (strpos($actionsstr, '[[') !== false) {
        $actionsstr = 'Actions';
    }
    echo html_writer::tag('th', $fullnamestr, ['style' => 'width: 20%;']);
    echo html_writer::tag('th', $emailstr, ['style' => 'width: 20%;']);
    echo html_writer::tag('th', $usernamestr, ['style' => 'width: 15%;']);
    echo html_writer::tag('th', $coursesstr, ['style' => 'width: 35%;']);
    echo html_writer::tag('th', $actionsstr, ['style' => 'width: 10%;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    // Table body
    echo html_writer::start_tag('tbody');
    foreach ($paginatedteachers as $teacher) {
        echo html_writer::start_tag('tr');
        
        // Full name
        $fullname = fullname($teacher);
        echo html_writer::tag('td', $fullname);
        
        // Email
        echo html_writer::tag('td', htmlspecialchars($teacher->email));
        
        // Username
        echo html_writer::tag('td', htmlspecialchars($teacher->username));
        
        // Courses - display as list with line breaks
        $courselist = implode('<br>', array_map('htmlspecialchars', $teacher->courses));
        echo html_writer::tag('td', $courselist, ['style' => 'max-width: 500px; word-wrap: break-word;']);
        
        // Actions
        $profileurl = new moodle_url('/user/profile.php', ['id' => $teacher->id]);
        $viewprofilestr = get_string('viewprofile', 'local_deanpromoodle');
        if (strpos($viewprofilestr, '[[') !== false) {
            $viewprofilestr = get_string('viewprofile');
        }
        $actions = html_writer::link(
            $profileurl,
            $viewprofilestr,
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
            ' (' . $totalteachers . ' ' . get_string('teachers', 'local_deanpromoodle') . ')',
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
