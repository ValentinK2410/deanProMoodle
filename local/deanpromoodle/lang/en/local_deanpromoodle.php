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
$string['seminarysite_button'] = 'Seminary website';
$string['seminarysite_title'] = 'Go to seminary website (Moodle SSO)';
$string['teacherbutton'] = 'Teacher';
$string['teacherbuttontitle'] = 'Teacher panel';

// Student messages
$string['noprogramsfound'] = 'Unfortunately, no programs assigned to you were found. Please contact your teacher or the dean\'s office.';
$string['nocohortsfound'] = 'You are not a member of any study group. Please contact your teacher or the dean\'s office for enrollment.';

$string['admintab_activityfeed'] = 'Applicants';
$string['admintab_studentregister'] = 'Student registration';
$string['studentregister_intro'] = 'The seminary registration form on mbs.ru opens in a new browser tab (the site does not allow embedding in a frame).';
$string['studentregister_open_newtab'] = 'Open registration form in new tab';
$string['feedtype_registration'] = 'Registration (student role)';
$string['feedtype_course'] = 'Course enrolment';
$string['feedtype_cohort'] = 'Cohort enrolment';
$string['feedkind_registration'] = 'New accounts';
$string['feedkind_course'] = 'Course enrolment';
$string['feedkind_cohort'] = 'Global groups';
$string['feedview_active'] = 'Current';
$string['feedview_hidden'] = 'Hidden (restorable)';
$string['feed_dismiss'] = 'Hide from feed';
$string['feed_restore'] = 'Restore to feed';
$string['feed_help'] = 'Three sub-tabs for the last 90 days: «New accounts» — account creation (MBS portal rules, see plugin settings); «Course enrolment» — individual enrolment, excluding cohort sync; «Global groups» — mass enrolment via cohorts (separate tab so the main list stays readable). Column «Form»: green check — required Additional data fields; warning — something missing. Hiding only removes the row; restore from the Hidden tab.';
$string['feedempty_registration'] = 'No new accounts in the last 90 days, or all are hidden.';
$string['feedempty_course'] = 'No individual course enrolments in the last 90 days, or all are hidden.';
$string['feedempty_cohort'] = 'No global group enrolments in the last 90 days, or all are hidden.';
$string['feedempty_hidden'] = 'No hidden entries.';
$string['feed_eventdate'] = 'Event date';
$string['feed_hiddenat'] = 'Hidden at';

$string['additional_registration_block'] = 'Intended course and registration address';
$string['field_intended_course'] = 'Intended course / programme';
$string['field_registration_address'] = 'Registration address (as on application)';
$string['identitydocs_section'] = 'Document scans:';
$string['identitydocs_hint'] = 'You can attach up to four scans: JPG, PNG, PDF up to 5 MB each. Only you, site administrators, and authorised staff can view the files.';
$string['additionaldoc_education'] = 'Education / diploma document scan';
$string['additionaldoc_church_rec'] = 'Church recommendation (scan)';
$string['section_family'] = 'Family';
$string['section_education_work'] = 'Education and employment';
$string['section_church'] = 'Church ministry';
$string['field_marital_status'] = 'Marital status';
$string['field_children_count'] = 'Number of children';
$string['field_student_registry_id'] = 'Student ID (external / registry)';
$string['field_education_general'] = 'Education (completed level, qualification)';
$string['field_speciality'] = 'Specialisation (field of study)';
$string['field_graduated_speciality'] = 'Awarded speciality (as on diploma)';
$string['field_education_doc_series'] = 'Education document series';
$string['field_education_doc_number'] = 'Education document number';
$string['field_workplace'] = 'Place of employment / occupation';
$string['field_district'] = 'District / area';
$string['field_house'] = 'House / building No.';
$string['field_apartment'] = 'Flat / apartment No.';
$string['field_house_apartment_legacy'] = 'House / flat (single line — legacy)';
$string['field_baptism_date'] = 'Date of baptism';
$string['field_ministry'] = 'Ministry in the church';
$string['field_church_name'] = 'Church congregation name';
$string['field_church_pastor_name'] = 'Pastor / leader name';
$string['field_church_pastor_contact'] = 'Pastor contacts';
$string['identitydocs_storage_note'] = 'Files are stored in Moodle protected storage linked to the user account. Use «Download» below each preview or «Open file» for inline view; ZIP-style viewers may open PDFs in the browser first — «Download» still saves the original file.';
$string['identitydoc_download'] = 'Download';
$string['identitydocs_summary_loaded'] = 'Files uploaded: {$a->done} of {$a->total}.';
$string['identitydoc_passport_main'] = 'Passport scan (photo spread)';
$string['identitydoc_passport_reg'] = 'Passport scan (registration page)';
$string['identitydoc_remove'] = 'Remove current file';
$string['identitydoc_openfile'] = 'Open file';
$string['identitydoc_current_file'] = 'Current file';
$string['identitydoc_status_uploaded'] = 'Uploaded';
$string['identitydoc_status_missing'] = 'Not uploaded';
$string['identitydoc_upload_hint'] = 'Choose a file and click «Save» at the bottom of the form.';
$string['identitydoc_uploaded_on'] = 'Uploaded on';

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
