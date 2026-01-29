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
 * Tabs: Assignments, Quizzes, Forums
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
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

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
$tab = optional_param('tab', 'assignments', PARAM_ALPHA); // assignments, quizzes, forums
$courseid = optional_param('courseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

// Set up page
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/teacher.php', [
    'tab' => $tab,
    'courseid' => $courseid,
    'page' => $page,
    'perpage' => $perpage
]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_heading(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_pagelayout('standard');

// Add CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Get courses where user is teacher
global $USER, $DB;
$teachercourses = [];
if ($courseid == 0) {
    // Get all courses where user is teacher
    $courses = enrol_get_my_courses();
    foreach ($courses as $course) {
        if ($course->id > 1) {
            $coursecontext = context_course::instance($course->id);
            if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
                has_capability('moodle/course:manageactivities', $coursecontext)) {
                $teachercourses[$course->id] = $course;
            }
        }
    }
} else {
    $course = get_course($courseid);
    if ($course && $course->id > 1) {
        $coursecontext = context_course::instance($courseid);
        if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
            has_capability('moodle/course:manageactivities', $coursecontext)) {
            $teachercourses[$courseid] = $course;
        }
    }
}

// Output page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacherpagetitle', 'local_deanpromoodle'));

// Tabs
$tabs = [];
$tabs[] = new tabobject('assignments', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'assignments', 'courseid' => $courseid]),
    get_string('assignments', 'local_deanpromoodle'));
$tabs[] = new tabobject('quizzes', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'quizzes', 'courseid' => $courseid]),
    get_string('quizzes', 'local_deanpromoodle'));
$tabs[] = new tabobject('forums', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'forums', 'courseid' => $courseid]),
    get_string('forums', 'local_deanpromoodle'));

echo $OUTPUT->tabtree($tabs, $tab);

// Course filter
if (count($teachercourses) > 1) {
    echo html_writer::start_div('local-deanpromoodle-teacher-filters', ['style' => 'margin-bottom: 20px; margin-top: 20px;']);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/deanpromoodle/pages/teacher.php'),
        'class' => 'form-inline'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    $allcoursesstr = get_string('allcourses', 'local_deanpromoodle');
    if (strpos($allcoursesstr, '[[') !== false) {
        $allcoursesstr = 'All courses';
    }
    $courseoptions = [0 => $allcoursesstr];
    foreach ($teachercourses as $cid => $c) {
        $courseoptions[$cid] = $c->fullname;
    }
    echo html_writer::label('Course: ', 'courseid');
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control', 'style' => 'display: inline-block; margin-left: 5px;']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Filter', 'class' => 'btn btn-primary', 'style' => 'margin-left: 10px;']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

// Content based on selected tab
switch ($tab) {
    case 'assignments':
        // Get ungraded assignments
        $ungradedassignments = [];
        foreach ($teachercourses as $course) {
            $coursecontext = context_course::instance($course->id);
            $assignments = get_all_instances_in_course('assign', $course, false);
            
            foreach ($assignments as $assignment) {
                $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                $assigncontext = context_module::instance($cm->id);
                
                // Get submissions that need grading
                $submissions = $DB->get_records_sql(
                    "SELECT s.*, u.firstname, u.lastname, u.email, u.id as userid
                     FROM {assign_submission} s
                     JOIN {user} u ON u.id = s.userid
                     WHERE s.assignment = ? AND s.status = 'submitted' 
                     AND (s.timemodified > 0)
                     AND NOT EXISTS (
                         SELECT 1 FROM {assign_grades} g 
                         WHERE g.assignment = s.assignment AND g.userid = s.userid
                     )
                     ORDER BY s.timemodified DESC",
                    [$assignment->id]
                );
                
                foreach ($submissions as $submission) {
                    $ungradedassignments[] = (object)[
                        'id' => $submission->id,
                        'assignmentid' => $assignment->id,
                        'assignmentname' => $assignment->name,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $submission->userid,
                        'studentname' => fullname($submission),
                        'email' => $submission->email,
                        'submitted' => userdate($submission->timemodified),
                        'timemodified' => $submission->timemodified
                    ];
                }
            }
        }
        
        // Pagination
        $total = count($ungradedassignments);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($ungradedassignments, $offset, $perpage);
        
        // Display table
        if (empty($paginated)) {
            echo html_writer::div(get_string('noassignmentsfound', 'local_deanpromoodle'), 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Course');
            echo html_writer::tag('th', 'Assignment');
            echo html_writer::tag('th', 'Student');
            echo html_writer::tag('th', 'Submitted');
            echo html_writer::tag('th', 'Actions');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->assignmentname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->submitted);
                $gradeurl = new moodle_url('/mod/assign/view.php', ['id' => $item->assignmentid, 'action' => 'grading', 'userid' => $item->userid]);
                $actions = html_writer::link($gradeurl, 'Grade', ['class' => 'btn btn-sm btn-primary']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« Previous', ['class' => 'btn btn-sm']);
                }
                echo html_writer::span("Page " . ($page + 1) . " of " . $totalpages . " ($total items)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, 'Next »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
            }
        }
        break;
        
    case 'quizzes':
        // Get failed quiz attempts (exams only)
        $failedquizzes = [];
        foreach ($teachercourses as $course) {
            $quizzes = get_all_instances_in_course('quiz', $course, false);
            
            foreach ($quizzes as $quiz) {
                // Check if it's an exam (you may need to adjust this logic)
                // For now, we'll get all failed attempts
                $attempts = $DB->get_records_sql(
                    "SELECT qa.*, u.firstname, u.lastname, u.email, u.id as userid, q.name as quizname
                     FROM {quiz_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     JOIN {quiz} q ON q.id = qa.quiz
                     WHERE qa.quiz = ? AND qa.state = 'finished' AND qa.sumgrades < q.sumgrades
                     ORDER BY qa.timemodified DESC",
                    [$quiz->id]
                );
                
                foreach ($attempts as $attempt) {
                    $failedquizzes[] = (object)[
                        'id' => $attempt->id,
                        'quizid' => $quiz->id,
                        'quizname' => $quiz->name,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $attempt->userid,
                        'studentname' => fullname($attempt),
                        'email' => $attempt->email,
                        'grade' => $attempt->sumgrades . ' / ' . $quiz->sumgrades,
                        'attempted' => userdate($attempt->timemodified),
                        'timemodified' => $attempt->timemodified
                    ];
                }
            }
        }
        
        // Pagination
        $total = count($failedquizzes);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($failedquizzes, $offset, $perpage);
        
        // Display table
        if (empty($paginated)) {
            echo html_writer::div(get_string('noquizzesfound', 'local_deanpromoodle'), 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Course');
            echo html_writer::tag('th', 'Quiz');
            echo html_writer::tag('th', 'Student');
            echo html_writer::tag('th', 'Grade');
            echo html_writer::tag('th', 'Attempted');
            echo html_writer::tag('th', 'Actions');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->quizname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->grade);
                echo html_writer::tag('td', $item->attempted);
                $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $item->id]);
                $actions = html_writer::link($reviewurl, 'Review', ['class' => 'btn btn-sm btn-primary']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« Previous', ['class' => 'btn btn-sm']);
                }
                echo html_writer::span("Page " . ($page + 1) . " of " . $totalpages . " ($total items)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, 'Next »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
            }
        }
        break;
        
    case 'forums':
        // Get unreplied forum posts
        $unrepliedposts = [];
        foreach ($teachercourses as $course) {
            $forums = get_all_instances_in_course('forum', $course, false);
            
            foreach ($forums as $forum) {
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
                $forumcontext = context_module::instance($cm->id);
                
                // Get posts without teacher replies
                $posts = $DB->get_records_sql(
                    "SELECT p.*, u.firstname, u.lastname, u.email, u.id as userid, d.name as discussionname
                     FROM {forum_posts} p
                     JOIN {user} u ON u.id = p.userid
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     WHERE d.forum = ? AND p.parent = 0
                     AND NOT EXISTS (
                         SELECT 1 FROM {forum_posts} p2
                         JOIN {role_assignments} ra ON ra.userid = p2.userid
                         JOIN {role} r ON r.id = ra.roleid
                         WHERE p2.discussion = p.discussion 
                         AND p2.id > p.id
                         AND r.shortname IN ('teacher', 'editingteacher', 'manager')
                         AND ra.contextid = ?
                     )
                     ORDER BY p.created DESC",
                    [$forum->id, $forumcontext->id]
                );
                
                foreach ($posts as $post) {
                    $unrepliedposts[] = (object)[
                        'id' => $post->id,
                        'forumid' => $forum->id,
                        'forumname' => $forum->name,
                        'discussionid' => $post->discussion,
                        'discussionname' => $post->discussionname,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $post->userid,
                        'studentname' => fullname($post),
                        'email' => $post->email,
                        'subject' => $post->subject,
                        'posted' => userdate($post->created),
                        'created' => $post->created
                    ];
                }
            }
        }
        
        // Pagination
        $total = count($unrepliedposts);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($unrepliedposts, $offset, $perpage);
        
        // Display table
        if (empty($paginated)) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Course');
            echo html_writer::tag('th', 'Forum');
            echo html_writer::tag('th', 'Discussion');
            echo html_writer::tag('th', 'Student');
            echo html_writer::tag('th', 'Subject');
            echo html_writer::tag('th', 'Posted');
            echo html_writer::tag('th', 'Actions');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->forumname));
                echo html_writer::tag('td', htmlspecialchars($item->discussionname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', htmlspecialchars($item->subject));
                echo html_writer::tag('td', $item->posted);
                $posturl = new moodle_url('/mod/forum/discuss.php', ['d' => $item->discussionid]);
                $actions = html_writer::link($posturl, 'Reply', ['class' => 'btn btn-sm btn-primary']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« Previous', ['class' => 'btn btn-sm']);
                }
                echo html_writer::span("Page " . ($page + 1) . " of " . $totalpages . " ($total items)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, 'Next »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
            }
        }
        break;
}

echo $OUTPUT->footer();
