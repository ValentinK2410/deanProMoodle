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
 * Language strings for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Dean Pro Moodle';
$string['deanpromoodle'] = 'Dean Pro Moodle';

// Navigation
$string['studentpage'] = 'Student Page';
$string['teacherpage'] = 'Teacher Page';
$string['adminpage'] = 'Admin Page';

// Capabilities
$string['deanpromoodle:viewstudent'] = 'View student page';
$string['deanpromoodle:viewteacher'] = 'View teacher page';
$string['deanpromoodle:viewadmin'] = 'View admin page';

// Page titles
$string['studentpagetitle'] = 'Student Dashboard';
$string['teacherpagetitle'] = 'Teacher Dashboard';
$string['adminpagetitle'] = 'Admin Dashboard';

// Page content
$string['studentpagecontent'] = 'Welcome to the Student Dashboard. This page is for students.';
$string['teacherpagecontent'] = 'Welcome to the Teacher Dashboard. This page is for teachers.';
$string['adminpagecontent'] = 'Welcome to the Admin Dashboard. This page is for administrators.';

// Access denied
$string['accessdenied'] = 'Access denied. You do not have permission to view this page.';

// Teacher page strings
$string['searchteachers'] = 'Search teachers';
$string['searchstudents'] = 'Search students';
$string['filterbycourse'] = 'Filter by course';
$string['allcourses'] = 'All courses';
$string['search'] = 'Search';
$string['fullname'] = 'Full name';
$string['email'] = 'Email';
$string['username'] = 'Username';
$string['courses'] = 'Courses';
$string['actions'] = 'Actions';
$string['viewprofile'] = 'View profile';
$string['nostudentsfound'] = 'No students found';
$string['noteachersfound'] = 'No teachers found';
$string['students'] = 'students';
$string['teachers'] = 'teachers';
$string['page'] = 'Page';
$string['of'] = 'of';
$string['previous'] = 'Previous';
$string['next'] = 'Next';

// Tabs
$string['assignments'] = 'Assignments';
$string['quizzes'] = 'Quizzes';
$string['forums'] = 'Forums';

// Tab content
$string['noassignmentsfound'] = 'No ungraded assignments found';
$string['noquizzesfound'] = 'No failed quiz attempts found';
$string['noforumspostsfound'] = 'No unreplied forum posts found';

// Subjects
$string['subjects'] = 'Subjects';
$string['addsubject'] = 'Add subject';
$string['createsubject'] = 'Create subject';
$string['editsubject'] = 'Edit subject';
$string['subjectname'] = 'Subject name';
$string['subjectcode'] = 'Subject code';
$string['shortdescription'] = 'Short description';
$string['description'] = 'Description';
$string['sortorder'] = 'Sort order';
$string['subjectcourses'] = 'Subject courses';
$string['addcoursetosubject'] = 'Add course';
$string['attachcoursetosubject'] = 'Attach course to subject';
$string['detachcoursefromsubject'] = 'Detach course from subject';
$string['attachsubjecttoprogram'] = 'Attach subject to program';

// Programs
$string['programs'] = 'Programs';
$string['addprogram'] = 'Add program';
$string['createprogram'] = 'Create program';
$string['editprogram'] = 'Edit program';
$string['programname'] = 'Program name';
$string['programcode'] = 'Program code';
$string['programdescription'] = 'Program description';
$string['programsubjects'] = 'Program subjects';
$string['attachcohorttoprogram'] = 'Attach global group';
$string['attachcohort'] = 'Attach group';
$string['detachcohortfromprogram'] = 'Detach group from program';
$string['selectprogram'] = 'Select program';
$string['searchcohort'] = 'Search cohort';
$string['searchcourse'] = 'Search course';
$string['searchprogram'] = 'Search program';
$string['searchsubject'] = 'Search subject';

// Button texts
$string['lkbutton'] = 'Dean\'s Office';
$string['lkbuttontitle'] = 'Dean\'s Office';
$string['teacherbutton'] = 'Teacher';
$string['teacherbuttontitle'] = 'Teacher panel';

// Student messages
$string['noprogramsfound'] = 'Unfortunately, no programs assigned to you were found. Please contact your teacher or the dean\'s office.';
$string['nocohortsfound'] = 'You are not a member of any study group. Please contact your teacher or the dean\'s office for enrollment.';

$string['admintab_activityfeed'] = 'Applicants';
$string['feedtype_registration'] = 'Registration (student role)';
$string['feedtype_course'] = 'Course enrolment';
$string['feedtype_cohort'] = 'Cohort enrolment';
$string['feedview_active'] = 'Current';
$string['feedview_hidden'] = 'Hidden (restorable)';
$string['feed_dismiss'] = 'Hide from feed';
$string['feed_restore'] = 'Restore to feed';
$string['feed_help'] = 'Lists users with the Student role whose account was created or who received that role in the last 90 days (e.g. program enrolment), and who match the MBS portal rules (Web page, ID number, email domain, or auth — see local_deanpromoodle settings). Column «Form»: green check — required Additional data fields; warning — something missing. Hiding only removes the row; restore from the Hidden tab.';
$string['feed_eventdate'] = 'Event date';
$string['feed_hiddenat'] = 'Hidden at';

$string['additional_registration_block'] = 'Intended course and registration address';
$string['field_intended_course'] = 'Intended course / programme';
$string['field_registration_address'] = 'Registration address (as on application)';
$string['identitydocs_section'] = 'Document scans';
$string['identitydocs_hint'] = 'Allowed: JPG, PNG, PDF up to 5 MB per file. Files are stored in a protected Moodle area; only you, administrators, and authorised staff can view them.';
$string['identitydoc_passport_main'] = 'Passport scan (photo spread)';
$string['identitydoc_passport_reg'] = 'Passport scan (registration page)';
$string['identitydoc_remove'] = 'Remove current file';
$string['identitydoc_openfile'] = 'Open file';

$string['feed_column_form'] = 'Form';
$string['formstatus_ok'] = 'Required fields in Additional data are complete';
$string['formstatus_warn'] = 'Some required fields are missing — open Additional data';
$string['applicants_filter_mode'] = 'Applicants list filter';
$string['applicants_filter_mode_desc'] = '«MBS portal only» lists users who match the rules below (host substring in Web page, ID number, custom profile field, email domain, or auth plugin). «All registrations» lists every new student in the period (legacy behaviour).';
$string['applicants_filter_mbs_only'] = 'MBS portal only (rules below)';
$string['applicants_filter_all_reg'] = 'All users who gained the Student role';
$string['applicants_source_hosts'] = 'MBS portal host substring(s)';
$string['applicants_source_hosts_desc'] = 'Comma-separated. A user matches if this substring appears in the Web page field or in the ID number (idnumber) field if your integration stores a link there (default: mbs.russianseminary.org).';
$string['applicants_email_domain'] = 'Email domains (optional)';
$string['applicants_email_domain_desc'] = 'Comma-separated domains without @. If the user\'s email ends with @domain, they are included. Leave empty if unused.';
$string['applicants_profile_field'] = 'Custom profile field shortname';
$string['applicants_profile_field_desc'] = 'Optional. If set, and the field value contains a host substring from above, the user is included.';
$string['applicants_auth_plugins'] = 'Auth plugins (optional)';
$string['applicants_auth_plugins_desc'] = 'Comma-separated auth plugin shortnames (e.g. oauth2, saml2). Matching users are included.';
