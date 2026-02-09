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
 * Student page for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define path to Moodle config
// From pages/student.php: ../ (to deanpromoodle) -> ../ (to local) -> ../ (to moodle root) = ../../../config.php
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
if (has_capability('local/deanpromoodle:viewstudent', $context)) {
    $hasaccess = true;
} else {
    // Fallback: check if user has student role in any course or system
    global $USER;
    $roles = get_user_roles($context, $USER->id, false);
    foreach ($roles as $role) {
        if ($role->shortname == 'student') {
            $hasaccess = true;
            break;
        }
    }
    
    // Also check system roles
    if (!$hasaccess) {
        $systemcontext = context_system::instance();
        $systemroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($systemroles as $role) {
            if ($role->shortname == 'student') {
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
    require_capability('local/deanpromoodle:viewstudent', $context);
}

// Получение параметров ДО проверки ролей и редиректов
$tab = optional_param('tab', 'courses', PARAM_ALPHA); // courses, programs, notes
$subtab = optional_param('subtab', 'programs', PARAM_ALPHA); // programs, additional для вкладки "Личная информация и статус"
$action = optional_param('action', '', PARAM_ALPHANUMEXT); // viewprogram, addnote, editnote, deletenote
$programid = optional_param('programid', 0, PARAM_INT);
$testmode = optional_param('test', false, PARAM_BOOL); // Параметр для тестирования - показывать цифровую оценку
$studentid = optional_param('studentid', 0, PARAM_INT); // ID студента для просмотра (для админов и преподавателей)
$userimportsetting = optional_param('userimportsetting', false, PARAM_BOOL); // Параметр для показа кнопки импорта из Excel
$noteid = optional_param('noteid', 0, PARAM_INT); // ID заметки для редактирования/удаления

// Проверка роли пользователя и редирект при необходимости
global $USER, $DB;
$isadmin = false;
$isteacher = false;
$isstudent = false;

// Определяем, какого студента просматриваем
$viewingstudent = $USER;
$isviewingotherstudent = false;

// Если передан studentid, проверяем права доступа и разрешаем админам/преподавателям просмотр
if ($studentid > 0) {
    // Проверяем, является ли текущий пользователь администратором или преподавателем
    $isadmin = has_capability('moodle/site:config', $context) || 
               has_capability('local/deanpromoodle:viewadmin', $context);
    
    $isteacher = false;
    if (!$isadmin) {
        $teacherroles = ['teacher', 'editingteacher', 'coursecreator'];
        $roles = get_user_roles($context, $USER->id, false);
        foreach ($roles as $role) {
            if (in_array($role->shortname, $teacherroles)) {
                $isteacher = true;
                break;
            }
        }
        if (!$isteacher) {
            $systemcontext = context_system::instance();
            $systemroles = get_user_roles($systemcontext, $USER->id, false);
            foreach ($systemroles as $role) {
                if (in_array($role->shortname, $teacherroles)) {
                    $isteacher = true;
                    break;
                }
            }
        }
    }
    
    if ($isadmin || $isteacher) {
        // Админ или преподаватель может просматривать данные студента
        $viewingstudent = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0]);
        if (!$viewingstudent) {
            print_error('studentnotfound', 'local_deanpromoodle');
        }
        $isviewingotherstudent = true;
        // Не делаем редирект для админа/преподавателя при просмотре другого студента
    } else {
        // Для студентов - редирект, если пытаются посмотреть другого студента
        if ($studentid != $USER->id) {
            redirect(new moodle_url('/local/deanpromoodle/pages/student.php'));
        }
    }
} else {
    // Если studentid не передан, проверяем роли
    // Проверяем, является ли пользователь админом
    $isadmin = has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context);
    
    // Проверяем, является ли пользователь преподавателем
    $isteacher = false;
    $teacherroles = ['teacher', 'editingteacher', 'coursecreator'];
    $roles = get_user_roles($context, $USER->id, false);
    foreach ($roles as $role) {
        if (in_array($role->shortname, $teacherroles)) {
            $isteacher = true;
            break;
        }
    }
    if (!$isteacher) {
        $systemcontext = context_system::instance();
        $systemroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($systemroles as $role) {
            if (in_array($role->shortname, $teacherroles)) {
                $isteacher = true;
                break;
            }
        }
    }
    
    // Проверяем, является ли пользователь студентом
    $isstudent = false;
    $studentroles = ['student'];
    foreach ($roles as $role) {
        if (in_array($role->shortname, $studentroles)) {
            $isstudent = true;
            break;
        }
    }
    if (!$isstudent) {
        $systemcontext = context_system::instance();
        $systemroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($systemroles as $role) {
            if (in_array($role->shortname, $studentroles)) {
                $isstudent = true;
                break;
            }
        }
    }
    
    // Разрешаем админам и преподавателям просматривать страницу студента без редиректа
    // Они будут видеть свою собственную информацию (если она есть) или пустую страницу
    // Редирект убран по запросу пользователя
}

// Студент может заходить на любые вкладки страницы student.php

// Настройка страницы
$urlparams = ['tab' => $tab];
if ($action) {
    $urlparams['action'] = $action;
}
if ($programid > 0) {
    $urlparams['programid'] = $programid;
}
if ($studentid > 0) {
    $urlparams['studentid'] = $studentid;
}
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/student.php', $urlparams));
$PAGE->set_context(context_system::instance());
// Получение заголовка с проверкой и fallback на русский
$pagetitle = get_string('studentpagetitle', 'local_deanpromoodle');
if (strpos($pagetitle, '[[') !== false || $pagetitle == 'Student Dashboard') {
    $pagetitle = 'Панель студента'; // Fallback на русский
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading(''); // Убираем стандартный заголовок
$PAGE->set_pagelayout('standard');

// Подключение CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Вывод страницы
echo $OUTPUT->header();

global $USER, $DB;

// Индикатор просмотра другого студента (для админов и преподавателей)
if ($isviewingotherstudent) {
    $studentfullname = fullname($viewingstudent);
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 20px; background-color: #d1ecf1; border-color: #bee5eb; color: #0c5460;']);
    echo html_writer::tag('strong', 'Просмотр данных студента: ') . htmlspecialchars($studentfullname, ENT_QUOTES, 'UTF-8');
    echo html_writer::end_div();
}

// Красивый заголовок с фото студента вместо стандартного
echo html_writer::start_tag('style');
echo "
    .student-profile-header-main {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        color: white;
    }
    .student-profile-photo-main {
        margin-right: 25px;
        border-radius: 50%;
        border: 4px solid rgba(255,255,255,0.3);
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .student-profile-info-main {
        flex: 1;
    }
    .student-profile-name-main {
        margin: 0;
        font-size: 2.2em;
        font-weight: 600;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        margin-bottom: 8px;
    }
    .student-profile-name-main a {
        color: white;
        text-decoration: none;
        transition: opacity 0.3s;
    }
    .student-profile-name-main a:hover {
        opacity: 0.9;
        text-decoration: underline;
    }
    .student-profile-email-main {
        display: flex;
        align-items: center;
        margin-top: 8px;
        font-size: 1.1em;
        opacity: 0.95;
    }
    .student-profile-email-main a {
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        transition: opacity 0.3s;
    }
    .student-profile-email-main a:hover {
        opacity: 0.8;
        text-decoration: underline;
    }
    .student-profile-email-main a:before {
        content: '✉';
        margin-right: 8px;
        font-size: 1.1em;
    }
";
echo html_writer::end_tag('style');

// Заголовок с информацией о студенте
echo html_writer::start_div('student-profile-header-main');
// Фото студента
$userpicture = $OUTPUT->user_picture($viewingstudent, ['size' => 120, 'class' => 'userpicture']);
echo html_writer::div($userpicture, 'student-profile-photo-main');
// ФИО и email студента
echo html_writer::start_div('student-profile-info-main');
$profileurl = new moodle_url('/user/profile.php', ['id' => $viewingstudent->id]);
echo html_writer::tag('h1', 
    html_writer::link($profileurl, fullname($viewingstudent), [
        'class' => 'student-profile-name-link',
        'target' => '_blank'
    ]),
    ['class' => 'student-profile-name-main']
);
if (!empty($viewingstudent->email)) {
    echo html_writer::div(
        html_writer::link('mailto:' . htmlspecialchars($viewingstudent->email, ENT_QUOTES, 'UTF-8'), htmlspecialchars($viewingstudent->email, ENT_QUOTES, 'UTF-8')),
        'student-profile-email-main'
    );
}
echo html_writer::end_div(); // student-profile-info-main
echo html_writer::end_div(); // student-profile-header-main

// Вкладки
$tabs = [];
$taburlparams = ['tab' => 'courses'];
if ($studentid > 0) {
    $taburlparams['studentid'] = $studentid;
}
$tabs[] = new tabobject('courses', 
    new moodle_url('/local/deanpromoodle/pages/student.php', $taburlparams),
    'Мои оценки');
$taburlparams['tab'] = 'programs';
$tabs[] = new tabobject('programs', 
    new moodle_url('/local/deanpromoodle/pages/student.php', $taburlparams),
    'Личная информация и статус');
// Вкладка "Заметки" видна только администратору и преподавателю
if ($isadmin || $isteacher) {
    $taburlparams['tab'] = 'notes';
    $tabs[] = new tabobject('notes', 
        new moodle_url('/local/deanpromoodle/pages/student.php', $taburlparams),
        'Заметки');
}

// Если это просмотр программы, не показываем вкладки
if ($action != 'viewprogram') {
    echo $OUTPUT->tabtree($tabs, $tab);
}

// Содержимое страницы в зависимости от вкладки или действия
if ($action == 'viewprogram' && $programid > 0) {
    // Страница просмотра программы с предметами
    echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
    
    // Кнопка "Назад"
    echo html_writer::start_div('', ['style' => 'margin-bottom: 20px;']);
    $backurlparams = ['tab' => 'programs'];
    if ($studentid > 0) {
        $backurlparams['studentid'] = $studentid;
    }
    echo html_writer::link(
        new moodle_url('/local/deanpromoodle/pages/student.php', $backurlparams),
        '<i class="fas fa-arrow-left"></i> Назад к программам',
        ['class' => 'btn btn-secondary', 'style' => 'text-decoration: none;', 'target' => '_blank']
    );
    echo html_writer::end_div();
    
    try {
        // Получаем информацию о программе
        $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid, 'visible' => 1]);
        
        if (!$program) {
            echo html_writer::div('Программа не найдена.', 'alert alert-danger');
        } else {
            // Проверяем, что студент имеет доступ к этой программе (через когорты)
            $studentcohorts = $DB->get_records_sql(
                "SELECT c.id
                 FROM {cohort_members} cm
                 JOIN {cohort} c ON c.id = cm.cohortid
                 WHERE cm.userid = ?",
                [$viewingstudent->id]
            );
            
            $cohortids = array_keys($studentcohorts);
            if (!empty($cohortids)) {
                $placeholders = implode(',', array_fill(0, count($cohortids), '?'));
                $hasaccess = $DB->record_exists_sql(
                    "SELECT 1
                     FROM {local_deanpromoodle_program_cohorts} pc
                     WHERE pc.programid = ? AND pc.cohortid IN ($placeholders)",
                    array_merge([$programid], $cohortids)
                );
            } else {
                $hasaccess = false;
            }
            
            if (!$hasaccess) {
                echo html_writer::div('У вас нет доступа к этой программе.', 'alert alert-danger');
            } else {
                echo html_writer::tag('h2', 'Программа: ' . htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8'), ['style' => 'margin-bottom: 20px;']);
                
                // Получаем предметы программы через AJAX или напрямую
                require_once($CFG->libdir . '/enrollib.php');
                // Получаем курсы для просматриваемого студента
                $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
                if ($studentroleid) {
                    $mycourses = $DB->get_records_sql(
                        "SELECT DISTINCT c.id
                         FROM {course} c
                         JOIN {enrol} e ON e.courseid = c.id
                         JOIN {user_enrolments} ue ON ue.enrolid = e.id
                         JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                         JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                         WHERE ue.userid = ? AND ra.roleid = ? AND ue.status = 0 AND e.status = 0",
                        [$viewingstudent->id, $studentroleid]
                    );
                } else {
                    $mycourses = [];
                }
                $mycourseids = array_keys($mycourses);
                
                // Получаем предметы программы (включая кредиты)
                $subjects = $DB->get_records_sql(
                    "SELECT s.id, s.name, s.code, s.shortdescription, s.credits, ps.sortorder
                     FROM {local_deanpromoodle_program_subjects} ps
                     JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
                     WHERE ps.programid = ?
                     ORDER BY ps.sortorder ASC",
                    [$programid]
                );
                
                if (empty($subjects)) {
                    echo html_writer::div('В программе пока нет предметов.', 'alert alert-info');
                } else {
                    // Стили для таблицы предметов
                    echo html_writer::start_tag('style');
                    echo "
                        .subjects-table {
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            overflow: hidden;
                        }
                        .subjects-table table {
                            margin: 0;
                            width: 100%;
                        }
                        .subjects-table thead th {
                            background-color: #f8f9fa;
                            padding: 12px 16px;
                            text-align: left;
                            font-weight: 600;
                            color: #495057;
                            border-bottom: 2px solid #dee2e6;
                        }
                        .subjects-table tbody tr {
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .subjects-table tbody tr:hover {
                            background-color: #f8f9fa;
                        }
                        .subjects-table tbody td {
                            padding: 12px 16px;
                            vertical-align: top;
                        }
                        .subject-status-started {
                            color: #28a745;
                            font-weight: 500;
                        }
                        .subject-status-not-started {
                            color: #6c757d;
                        }
                        .subject-courses-list {
                            margin-top: 8px;
                            padding-left: 20px;
                            font-size: 0.9em;
                        }
                        .subject-courses-list li {
                            margin: 4px 0;
                        }
                        .course-link-enrolled {
                            color: #28a745;
                            font-weight: 500;
                            text-decoration: none;
                        }
                        .course-link-enrolled:hover {
                            text-decoration: underline;
                        }
                        .course-link-not-enrolled {
                            color: #6c757d;
                        }
                        /* Стили для статуса завершения */
                        .completion-status-not-completed {
                            color: #dc3545;
                            font-weight: 500;
                        }
                        .completion-status-partial {
                            color: #ffc107;
                            font-weight: 500;
                        }
                        .completion-status-completed {
                            color: #28a745;
                            font-weight: 500;
                        }
                        /* Стили для итоговых оценок */
                        .grade-badge {
                            display: inline-block;
                            padding: 8px 16px;
                            border-radius: 20px;
                            font-weight: 600;
                            font-size: 14px;
                            transition: all 0.3s ease;
                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                        }
                        .grade-badge:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                        }
                        .grade-badge-no-grade {
                            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                            color: #ffffff;
                        }
                        .grade-badge-satisfactory {
                            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                            color: #212529;
                        }
                        .grade-badge-good {
                            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                            color: #ffffff;
                        }
                        .grade-badge-excellent {
                            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
                            color: #ffffff;
                        }
                        .grade-badge i {
                            margin-right: 6px;
                            font-size: 16px;
                        }
                    ";
                    echo html_writer::end_tag('style');
                    
                    echo html_writer::start_div('subjects-table');
                    echo html_writer::start_tag('table', ['class' => 'table']);
                    echo html_writer::start_tag('thead');
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('th', '№', ['style' => 'width: 60px;']);
                    echo html_writer::tag('th', 'Название предмета');
                    echo html_writer::tag('th', 'Кол-во кредитов', ['style' => 'width: 120px;']);
                    echo html_writer::tag('th', 'Статус завершения', ['style' => 'width: 180px;']);
                    echo html_writer::tag('th', 'Оценка', ['style' => 'width: 150px;']);
                    echo html_writer::end_tag('tr');
                    echo html_writer::end_tag('thead');
                    // Функция для вычисления статуса завершения и оценки курса
                    $getCourseCompletionAndGrade = function($courseid, $studentid) use ($DB, $CFG) {
                        require_once($CFG->libdir . '/gradelib.php');
                        
                        $course = get_course($courseid);
                        $coursegrade = null;
                        $finalgradepercent = null;
                        
                        // Получаем итоговую оценку курса
                        try {
                            $courseitem = grade_item::fetch_course_item($courseid);
                            if ($courseitem) {
                                $usergrade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $studentid]);
                                if ($usergrade && $usergrade->finalgrade !== null) {
                                    $coursegrade = $usergrade->finalgrade;
                                    $finalgradepercent = $coursegrade;
                                }
                            }
                        } catch (\Exception $e) {
                            // Игнорируем ошибки
                        }
                        
                        // Проверяем, все ли задания имеют оценку
                        $allassignmentsgraded = true;
                        $hasassignments = false;
                        
                        // Функция для проверки оценки задания (включая принудительно проставленные)
                        $checkAssignmentGrade = function($assignmentid, $userid) use ($DB, $CFG) {
                            require_once($CFG->libdir . '/gradelib.php');
                            $gradeitem = grade_item::fetch([
                                'itemtype' => 'mod',
                                'itemmodule' => 'assign',
                                'iteminstance' => $assignmentid
                            ]);
                            if ($gradeitem) {
                                $usergrade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
                                return $usergrade && $usergrade->finalgrade !== null;
                            }
                            return false;
                        };
                        
                        try {
                            $assignments = get_all_instances_in_course('assign', $course, false);
                            $quizzes = get_all_instances_in_course('quiz', $course, false);
                            
                            if (!is_array($assignments)) {
                                $assignments = [];
                            }
                            if (!is_array($quizzes)) {
                                $quizzes = [];
                            }
                            
                            // Проверяем задания
                            foreach ($assignments as $assignment) {
                                $cm = get_coursemodule_from_instance('assign', $assignment->id, $courseid);
                                if (!$cm || !$cm->visible || !$cm->visibleoncoursepage) {
                                    continue;
                                }
                                
                                $assignmentname = mb_strtolower($assignment->name);
                                if (strpos($assignmentname, 'отчет') !== false && strpos($assignmentname, 'чтени') !== false) {
                                    $hasassignments = true;
                                    if (!$checkAssignmentGrade($assignment->id, $studentid)) {
                                        $allassignmentsgraded = false;
                                    }
                                }
                                if (strpos($assignmentname, 'письменн') !== false) {
                                    $hasassignments = true;
                                    $submission = $DB->get_record('assign_submission', [
                                        'assignment' => $assignment->id,
                                        'userid' => $studentid
                                    ]);
                                    $hasfiles = false;
                                    if ($submission) {
                                        $filecount = $DB->count_records_sql(
                                            "SELECT COUNT(*) FROM {assignsubmission_file} WHERE submission = ?",
                                            [$submission->id]
                                        );
                                        $textcount = $DB->count_records_sql(
                                            "SELECT COUNT(*) FROM {assignsubmission_onlinetext} WHERE submission = ? AND onlinetext IS NOT NULL AND onlinetext != ''",
                                            [$submission->id]
                                        );
                                        $hasfiles = ($filecount > 0 || $textcount > 0);
                                    }
                                    if (!$checkAssignmentGrade($assignment->id, $studentid) && !$hasfiles) {
                                        $allassignmentsgraded = false;
                                    }
                                }
                            }
                            
                            // Проверяем тесты (экзамены)
                            foreach ($quizzes as $quiz) {
                                $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
                                if (!$cm || !$cm->visible || !$cm->visibleoncoursepage) {
                                    continue;
                                }
                                
                                $quizname = mb_strtolower($quiz->name);
                                if (strpos($quizname, 'экзамен') !== false) {
                                    $hasassignments = true;
                                    
                                    $grade = $DB->get_record('quiz_grades', [
                                        'quiz' => $quiz->id,
                                        'userid' => $studentid
                                    ]);
                                    
                                    $hasgrade = false;
                                    if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                                        $hasgrade = true;
                                    } else {
                                        try {
                                            $gradeitem = grade_item::fetch([
                                                'itemtype' => 'mod',
                                                'itemmodule' => 'quiz',
                                                'iteminstance' => $quiz->id,
                                                'courseid' => $courseid
                                            ]);
                                            
                                            if ($gradeitem) {
                                                $usergrade = grade_grade::fetch([
                                                    'itemid' => $gradeitem->id,
                                                    'userid' => $studentid
                                                ]);
                                                
                                                if ($usergrade && $usergrade->finalgrade !== null && $usergrade->finalgrade >= 0) {
                                                    $hasgrade = true;
                                                }
                                            }
                                        } catch (\Exception $e) {
                                            // Игнорируем ошибки
                                        }
                                    }
                                    
                                    if (!$hasgrade) {
                                        $allassignmentsgraded = false;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Игнорируем ошибки
                        }
                        
                        // Определяем статус завершения
                        $completionstatus = '';
                        $completionclass = '';
                        if (($finalgradepercent === null || $finalgradepercent < 70) && $allassignmentsgraded && $hasassignments) {
                            $completionstatus = 'Завершен полностью';
                            $completionclass = 'completion-status-completed';
                        } elseif ($finalgradepercent === null || $finalgradepercent < 70) {
                            $completionstatus = 'Не завершен';
                            $completionclass = 'completion-status-not-completed';
                        } elseif ($finalgradepercent >= 70) {
                            if (!$hasassignments) {
                                $completionstatus = 'Завершен полностью';
                                $completionclass = 'completion-status-completed';
                            } elseif (!$allassignmentsgraded) {
                                $completionstatus = 'Завершен частично';
                                $completionclass = 'completion-status-partial';
                            } else {
                                $completionstatus = 'Завершен полностью';
                                $completionclass = 'completion-status-completed';
                            }
                        }
                        
                        // Добавляем процент завершения к статусу через дефис
                        if ($finalgradepercent !== null) {
                            $completionstatus .= ' - ' . round($finalgradepercent, 2) . '%';
                        }
                        
                        // Определяем оценку
                        $gradeText = '';
                        $gradeClass = '';
                        $gradeIcon = '';
                        $percentForDisplay = $finalgradepercent;
                        
                        if ($finalgradepercent === null && $percentForDisplay === null) {
                            $gradeText = 'нет оценки';
                            $gradeClass = 'grade-badge-no-grade';
                            $gradeIcon = '<i class="fas fa-minus-circle"></i>';
                        } elseif ($percentForDisplay !== null && $percentForDisplay < 70) {
                            $gradeText = 'нет оценки';
                            $gradeClass = 'grade-badge-no-grade';
                            $gradeIcon = '<i class="fas fa-minus-circle"></i>';
                        } elseif ($percentForDisplay >= 70 && $percentForDisplay < 80) {
                            $gradeText = '3 (удовлетворительно)';
                            $gradeClass = 'grade-badge-satisfactory';
                            $gradeIcon = '<i class="fas fa-check-circle"></i>';
                        } elseif ($percentForDisplay >= 80 && $percentForDisplay < 90) {
                            $gradeText = '4 (хорошо)';
                            $gradeClass = 'grade-badge-good';
                            $gradeIcon = '<i class="fas fa-star"></i>';
                        } elseif ($percentForDisplay >= 90) {
                            $gradeText = '5 (отлично)';
                            $gradeClass = 'grade-badge-excellent';
                            $gradeIcon = '<i class="fas fa-trophy"></i>';
                        }
                        
                        return [
                            'completionstatus' => $completionstatus,
                            'completionclass' => $completionclass,
                            'gradetext' => $gradeText,
                            'gradeclass' => $gradeClass,
                            'gradeicon' => $gradeIcon,
                            'finalgradepercent' => $finalgradepercent
                        ];
                    };
                    
                    echo html_writer::start_tag('tbody');
                    
                    foreach ($subjects as $index => $subject) {
                        // Получаем курсы предмета
                        $subjectcourses = $DB->get_records_sql(
                            "SELECT c.id, c.fullname, c.shortname
                             FROM {local_deanpromoodle_subject_courses} sc
                             JOIN {course} c ON c.id = sc.courseid
                             WHERE sc.subjectid = ?
                             ORDER BY sc.sortorder ASC, c.fullname ASC",
                            [$subject->id]
                        );
                        
                        // Фильтруем курсы: оставляем только те, на которые зачислен студент
                        $enrolledcourses = [];
                        foreach ($subjectcourses as $course) {
                            if (in_array($course->id, $mycourseids)) {
                                $enrolledcourses[] = $course;
                            }
                        }
                        
                        // Вычисляем статус завершения и оценку для каждого курса
                        $bestCompletion = null;
                        $bestGrade = null;
                        $bestGradePercent = null;
                        
                        foreach ($enrolledcourses as $course) {
                            $result = $getCourseCompletionAndGrade($course->id, $viewingstudent->id);
                            
                            // Выбираем лучший статус завершения (приоритет: Завершен полностью > Завершен частично > Не завершен)
                            if ($bestCompletion === null) {
                                $bestCompletion = $result;
                            } else {
                                $currentPriority = 0;
                                $bestPriority = 0;
                                
                                if (strpos($result['completionstatus'], 'Завершен полностью') !== false) {
                                    $currentPriority = 3;
                                } elseif (strpos($result['completionstatus'], 'Завершен частично') !== false) {
                                    $currentPriority = 2;
                                } else {
                                    $currentPriority = 1;
                                }
                                
                                if (strpos($bestCompletion['completionstatus'], 'Завершен полностью') !== false) {
                                    $bestPriority = 3;
                                } elseif (strpos($bestCompletion['completionstatus'], 'Завершен частично') !== false) {
                                    $bestPriority = 2;
                                } else {
                                    $bestPriority = 1;
                                }
                                
                                if ($currentPriority > $bestPriority) {
                                    $bestCompletion = $result;
                                }
                            }
                            
                            // Выбираем лучшую оценку (наибольший процент)
                            if ($result['finalgradepercent'] !== null) {
                                if ($bestGradePercent === null || $result['finalgradepercent'] > $bestGradePercent) {
                                    $bestGrade = $result;
                                    $bestGradePercent = $result['finalgradepercent'];
                                }
                            }
                        }
                        
                        // Используем лучший результат для отображения
                        $displayCompletion = $bestCompletion;
                        $displayGrade = $bestGrade;
                        
                        // Если нет курсов с оценками, используем лучший статус завершения
                        if ($displayGrade === null && $displayCompletion !== null) {
                            $displayGrade = $displayCompletion;
                        }
                        
                        // Если нет курсов вообще
                        if ($displayCompletion === null) {
                            $displayCompletion = [
                                'completionstatus' => '-',
                                'completionclass' => '',
                                'gradetext' => '-',
                                'gradeclass' => '',
                                'gradeicon' => '',
                                'finalgradepercent' => null
                            ];
                            $displayGrade = $displayCompletion;
                        }
                        
                        // Формируем tooltip и ссылку для названия предмета
                        $subjectNameHtml = '';
                        $tooltipText = '';
                        $firstCourseUrl = null;
                        
                        if (!empty($enrolledcourses)) {
                            // Формируем список курсов для tooltip
                            $courseNames = [];
                            foreach ($enrolledcourses as $course) {
                                $courseNames[] = htmlspecialchars($course->fullname, ENT_QUOTES, 'UTF-8');
                                if ($firstCourseUrl === null) {
                                    $firstCourseUrl = new moodle_url('/course/view.php', ['id' => $course->id]);
                                }
                            }
                            $tooltipText = 'Курсы: ' . implode(', ', $courseNames);
                            
                            // Делаем название предмета кликабельным
                            $subjectNameHtml = html_writer::link(
                                $firstCourseUrl,
                                htmlspecialchars($subject->name, ENT_QUOTES, 'UTF-8'),
                                [
                                    'style' => 'font-weight: 500; text-decoration: none; color: inherit; cursor: pointer;',
                                    'title' => $tooltipText,
                                    'target' => '_blank',
                                    'onmouseover' => 'this.style.textDecoration="underline"; this.style.color="#007bff";',
                                    'onmouseout' => 'this.style.textDecoration="none"; this.style.color="inherit";'
                                ]
                            );
                        } else {
                            // Если нет курсов, просто текст
                            $subjectNameHtml = htmlspecialchars($subject->name, ENT_QUOTES, 'UTF-8');
                            $tooltipText = 'Нет доступных курсов';
                        }
                        
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', $index + 1);
                        echo html_writer::tag('td', $subjectNameHtml);
                        
                        // Кол-во кредитов
                        $credits = '-';
                        if (isset($subject->credits) && $subject->credits !== null && $subject->credits > 0) {
                            $credits = (string)$subject->credits;
                        }
                        echo html_writer::tag('td', htmlspecialchars($credits, ENT_QUOTES, 'UTF-8'));
                        
                        // Статус завершения
                        $completionHtml = '<span class="' . $displayCompletion['completionclass'] . '">' . 
                            htmlspecialchars($displayCompletion['completionstatus'], ENT_QUOTES, 'UTF-8') . '</span>';
                        echo html_writer::tag('td', $completionHtml);
                        
                        // Оценка
                        $gradeBadgeContent = $displayGrade['gradeicon'] . htmlspecialchars($displayGrade['gradetext'], ENT_QUOTES, 'UTF-8');
                        $gradeBadge = '<span class="grade-badge ' . $displayGrade['gradeclass'] . '">' . $gradeBadgeContent . '</span>';
                        echo html_writer::tag('td', $gradeBadge);
                        
                        echo html_writer::end_tag('tr');
                    }
                    
                    echo html_writer::end_tag('tbody');
                    echo html_writer::end_tag('table');
                    echo html_writer::end_div();
                }
            }
        }
    } catch (\Exception $e) {
        echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
    }
    
    echo html_writer::end_div();
} else {
    // Обычные вкладки
    switch ($tab) {
    case 'courses':
        // Вкладка "Мои оценки"
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        
        try {
            // Получаем все курсы, на которые записан просматриваемый студент
            require_once($CFG->libdir . '/enrollib.php');
            $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
            if ($studentroleid) {
                $mycourses = $DB->get_records_sql(
                    "SELECT DISTINCT c.id, c.fullname, c.shortname, c.summary, c.startdate, c.enddate, c.visible
                     FROM {course} c
                     JOIN {enrol} e ON e.courseid = c.id
                     JOIN {user_enrolments} ue ON ue.enrolid = e.id
                     JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                     JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id
                     WHERE ue.userid = ? AND ra.roleid = ? AND ue.status = 0 AND e.status = 0
                     ORDER BY c.fullname",
                    [$viewingstudent->id, $studentroleid]
                );
            } else {
                $mycourses = [];
            }
            
            if (empty($mycourses)) {
                echo html_writer::div('Вы не записаны ни на один курс.', 'alert alert-info');
            } else {
                // Стили для таблицы курсов
                echo html_writer::start_tag('style');
                echo "
                    .courses-table {
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        overflow-x: auto;
                        overflow-y: visible;
                    }
                    .courses-table table {
                        margin: 0;
                        width: 100%;
                        min-width: 1200px;
                        table-layout: auto;
                    }
                    .courses-table thead th {
                        background-color: #f8f9fa;
                        padding: 12px 16px;
                        text-align: left;
                        font-weight: 600;
                        color: #495057;
                        border-bottom: 2px solid #dee2e6;
                    }
                    .courses-table tbody tr {
                        border-bottom: 1px solid #f0f0f0;
                    }
                    .courses-table tbody tr:hover {
                        background-color: #f8f9fa;
                    }
                    .courses-table tbody td {
                        padding: 12px 16px;
                        vertical-align: middle;
                    }
                    .course-link {
                        color: #007bff;
                        text-decoration: none;
                        font-weight: 500;
                    }
                    .course-link:hover {
                        text-decoration: underline;
                    }
                    .courses-table-container {
                        position: relative;
                    }
                    .courses-table-fullscreen {
                        border-radius: 0;
                        overflow: auto;
                        height: 100vh;
                    }
                    .courses-table-fullscreen table {
                        min-width: 100%;
                    }
                    .fullscreen-toggle-btn {
                        background: #007bff;
                        color: white;
                        border: none;
                        border-radius: 5px;
                        padding: 8px 16px;
                        cursor: pointer;
                        font-size: 14px;
                        display: flex;
                        align-items: center;
                        gap: 5px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                        transition: background 0.3s;
                    }
                    .fullscreen-toggle-btn:hover {
                        background: #0056b3;
                    }
                    .courses-table-container:not(.fullscreen-mode) .fullscreen-toggle-btn {
                        position: relative;
                        top: auto;
                        right: auto;
                        margin-bottom: 10px;
                        display: inline-block;
                    }
                    .courses-table-container.fullscreen-mode .fullscreen-toggle-btn {
                        position: fixed;
                        top: 10px;
                        right: 10px;
                        z-index: 10000;
                    }
                    .courses-table-container.fullscreen-mode {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100vw;
                        height: 100vh;
                        z-index: 9999;
                        background: white;
                    }
                ";
                echo html_writer::end_tag('style');
                
                // Добавляем стили для статусов заданий
                echo html_writer::start_tag('style');
                echo "
                    .assignment-status-yellow {
                        background-color: #ffc107;
                        color: #000;
                    }
                    .assignment-status-green {
                        background-color: #28a745;
                        color: #fff;
                    }
                    .assignment-status-red {
                        background-color: #dc3545;
                        color: #fff;
                    }
                    .assignment-status-item {
                        margin: 4px 4px 4px 0;
                        font-size: 0.85em;
                        display: inline-block;
                    }
                    .assignment-status-item a {
                        color: inherit;
                        text-decoration: none;
                    }
                    .assignment-status-item a:hover {
                        text-decoration: underline;
                    }
                ";
                echo html_writer::end_tag('style');
                
                // Добавляем стили для статусов завершения курса
                echo html_writer::start_tag('style');
                echo "
                    .completion-status-not-completed {
                        color: #dc3545;
                        font-weight: 500;
                    }
                    .completion-status-partial {
                        color: #ffc107;
                        font-weight: 500;
                    }
                    .completion-status-completed {
                        color: #28a745;
                        font-weight: 500;
                    }
                    /* Стили для итоговых оценок */
                    .grade-badge {
                        display: inline-block;
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-weight: 600;
                        font-size: 14px;
                        text-align: center;
                        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
                        transition: all 0.3s ease;
                        white-space: nowrap;
                    }
                    .grade-badge:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                    }
                    .grade-badge-no-grade {
                        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                        color: #ffffff;
                    }
                    .grade-badge-failed {
                        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                        color: #ffffff;
                    }
                    .grade-badge-satisfactory {
                        background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                        color: #212529;
                    }
                    .grade-badge-good {
                        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
                        color: #ffffff;
                    }
                    .grade-badge-excellent {
                        background: linear-gradient(135deg, #28a745 0%, #218838 100%);
                        color: #ffffff;
                    }
                    .grade-badge i {
                        margin-right: 6px;
                        font-size: 16px;
                    }
                    .grade-numeric-value {
                        font-size: 12px;
                        opacity: 0.9;
                        margin-left: 6px;
                        font-weight: 500;
                    }
                    .teacher-email-link {
                        color: #007bff;
                        text-decoration: none;
                        display: block;
                        margin-bottom: 4px;
                    }
                    .teacher-email-link:hover {
                        text-decoration: underline;
                    }
                    .teachers-icons-container {
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        align-items: center;
                    }
                    .teacher-icon-wrapper {
                        position: relative;
                        display: inline-block;
                        cursor: pointer;
                    }
                    .teacher-icon-wrapper .teacher-avatar {
                        border-radius: 50%;
                        border: 2px solid #dee2e6;
                        transition: all 0.3s ease;
                        display: block;
                    }
                    .teacher-icon-wrapper:hover .teacher-avatar {
                        border-color: #007bff;
                        transform: scale(1.1);
                        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
                    }
                    .teacher-icon-wrapper[title]:hover::after {
                        content: attr(title);
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        margin-bottom: 8px;
                        padding: 8px 12px;
                        background-color: #333;
                        color: #fff;
                        border-radius: 4px;
                        font-size: 12px;
                        white-space: pre-line;
                        z-index: 1000;
                        pointer-events: none;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                        max-width: 250px;
                        line-height: 1.4;
                    }
                    .teacher-icon-wrapper[title]:hover::before {
                        content: '';
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        margin-bottom: 2px;
                        border: 5px solid transparent;
                        border-top-color: #333;
                        z-index: 1001;
                        pointer-events: none;
                    }
                ";
                echo html_writer::end_tag('style');
                
                echo html_writer::start_div('courses-table-container', ['id' => 'courses-table-container']);
                echo html_writer::tag('button', '<i class="fas fa-expand"></i> Развернуть на весь экран', [
                    'class' => 'fullscreen-toggle-btn',
                    'id' => 'fullscreen-toggle-btn',
                    'onclick' => 'toggleFullscreen()'
                ]);
                echo html_writer::start_div('courses-table', ['id' => 'courses-table']);
                echo html_writer::start_tag('table', ['class' => 'table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Название курса', ['style' => 'width: 250px; text-align: center;']);
                echo html_writer::tag('th', 'Кол-во<br>кредитов', ['style' => 'width: 150px; text-align: center;']);
                echo html_writer::tag('th', 'Преподаватели', ['style' => 'width: 200px; text-align: center;']);
                echo html_writer::tag('th', 'Задолженности по курсу', ['style' => 'width: 300px; text-align: center;']);
                echo html_writer::tag('th', 'Статус<br>завершения', ['style' => 'width: 150px; text-align: center;']);
                echo html_writer::tag('th', 'Итоговая<br>оценка', ['style' => 'width: 150px; text-align: center;']);
                echo html_writer::tag('th', 'Перейти к курсу', ['style' => 'width: 100px; text-align: center;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                // Подключаем необходимые функции Moodle
                require_once($CFG->libdir . '/modinfolib.php');
                require_once($CFG->libdir . '/gradelib.php');
                
                foreach ($mycourses as $courseobj) {
                    if ($courseobj->id <= 1) continue; // Пропускаем системный курс
                    
                    // Загружаем полный объект курса для корректной работы функций
                    try {
                        $course = get_course($courseobj->id);
                    } catch (\Exception $e) {
                        // Если не удалось загрузить курс, используем исходный объект
                        $course = $courseobj;
                    }
                    
                    echo html_writer::start_tag('tr');
                    
                    // Название курса (краткое название как гиперссылка)
                    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                    echo html_writer::tag('td', 
                        html_writer::link($courseurl, htmlspecialchars($course->shortname, ENT_QUOTES, 'UTF-8'), [
                            'class' => 'course-link',
                            'target' => '_blank'
                        ])
                    );
                    
                    // Количество академических кредитов
                    $credits = '-';
                    try {
                        $subject = $DB->get_record_sql(
                            "SELECT s.credits
                             FROM {local_deanpromoodle_subject_courses} sc
                             JOIN {local_deanpromoodle_subjects} s ON s.id = sc.subjectid
                             WHERE sc.courseid = ?
                             LIMIT 1",
                            [$course->id]
                        );
                        if ($subject && $subject->credits !== null && $subject->credits > 0) {
                            $credits = (string)$subject->credits;
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки
                    }
                    echo html_writer::tag('td', htmlspecialchars($credits, ENT_QUOTES, 'UTF-8'));
                    
                    // Преподаватели с email-ссылками (только роль teacher)
                    // Показываем пользователей, у которых есть роль teacher, даже если у них есть и другие роли
                    $coursecontext = context_course::instance($course->id);
                    // Используем роль с id 3
                    $teacherroleid = 3;
                    $teachershtml = '';
                    $debuginfo = '';
                    // Используем SQL запрос для получения преподавателей с ролью teacher
                    // DISTINCT гарантирует, что каждый пользователь показывается только один раз,
                    // даже если у него несколько ролей (например, teacher и editingteacher)
                    $teacherusers = $DB->get_records_sql(
                        "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                         FROM {user} u
                         JOIN {role_assignments} ra ON ra.userid = u.id
                         WHERE ra.contextid = ? AND ra.roleid = ?
                         AND u.deleted = 0 AND u.suspended = 0
                         ORDER BY u.lastname, u.firstname",
                        [$coursecontext->id, $teacherroleid]
                    );
                    
                    // Отладочная информация в тестовом режиме
                    if ($testmode) {
                        $debuginfo = [];
                        $debuginfo[] = 'Контекст курса: ' . $coursecontext->id;
                        $debuginfo[] = 'ID роли: ' . $teacherroleid;
                        $debuginfo[] = 'Найдено преподавателей: ' . count($teacherusers);
                        if (!empty($teacherusers)) {
                            $debuginfo[] = 'Преподаватели: ' . implode(', ', array_map(function($t) {
                                return fullname($t);
                            }, $teacherusers));
                        }
                        $debuginfo = '<div style="font-size: 10px; color: #666; margin-top: 5px;">' . implode(' | ', $debuginfo) . '</div>';
                    }
                    if (!empty($teacherusers)) {
                        $teachericons = [];
                        foreach ($teacherusers as $teacher) {
                            $teachername = fullname($teacher);
                            $teacherinfo = [];
                            $teacherinfo[] = 'ФИО: ' . $teachername;
                            if (!empty($teacher->email)) {
                                $teacherinfo[] = 'Email: ' . htmlspecialchars($teacher->email, ENT_QUOTES, 'UTF-8');
                            }
                            $tooltiptext = implode("\n", $teacherinfo);
                            
                            // Получаем полный объект пользователя для user_picture
                            $teacheruser = $DB->get_record('user', ['id' => $teacher->id]);
                            if ($teacheruser) {
                                // Отображаем иконку преподавателя
                                $userpicture = $OUTPUT->user_picture($teacheruser, [
                                    'size' => 35,
                                    'class' => 'teacher-avatar',
                                    'link' => false
                                ]);
                                
                                // Обертываем в span с tooltip
                                $iconcontent = '';
                                if (!empty($teacher->email)) {
                                    $iconcontent = html_writer::link(
                                        'mailto:' . htmlspecialchars($teacher->email, ENT_QUOTES, 'UTF-8'),
                                        $userpicture,
                                        ['class' => 'teacher-email-link', 'target' => '_blank', 'style' => 'text-decoration: none; display: inline-block;']
                                    );
                                } else {
                                    $iconcontent = $userpicture;
                                }
                                
                                $iconhtml = html_writer::tag('span', $iconcontent, [
                                    'class' => 'teacher-icon-wrapper',
                                    'title' => $tooltiptext,
                                    'data-toggle' => 'tooltip',
                                    'data-placement' => 'top'
                                ]);
                                
                                $teachericons[] = $iconhtml;
                            }
                        }
                        $teachershtml = html_writer::div(implode(' ', $teachericons), 'teachers-icons-container') . $debuginfo;
                    } else {
                        if ($testmode) {
                            $teachershtml = '-' . $debuginfo;
                        } else {
                            $teachershtml = '-';
                        }
                    }
                    echo html_writer::tag('td', $teachershtml);
                    
                    // Задолженности по курсу
                    $statushtml = '';
                    $statusitems = [];
                    
                    // Вспомогательная функция для проверки наличия оценки за задание (включая принудительно проставленные)
                    // Проверяет как assign_grades, так и gradebook для учета принудительно проставленных оценок
                    global $CFG;
                    $checkAssignmentGrade = function($assignmentid, $userid) use ($DB, $course, $CFG) {
                        // Сначала проверяем assign_grades
                        $grade = $DB->get_record('assign_grades', [
                            'assignment' => $assignmentid,
                            'userid' => $userid
                        ]);
                        
                        if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                            return true; // Есть оценка в assign_grades
                        }
                        
                        // Если нет оценки в assign_grades, проверяем через gradebook API
                        // Это учитывает принудительно проставленные оценки
                        try {
                            require_once($CFG->dirroot . '/lib/gradelib.php');
                            $cm = get_coursemodule_from_instance('assign', $assignmentid, $course->id);
                            if ($cm) {
                                $gradeitem = grade_item::fetch([
                                    'itemtype' => 'mod',
                                    'itemmodule' => 'assign',
                                    'iteminstance' => $assignmentid,
                                    'courseid' => $course->id
                                ]);
                                
                                if ($gradeitem) {
                                    $usergrade = grade_grade::fetch([
                                        'itemid' => $gradeitem->id,
                                        'userid' => $userid
                                    ]);
                                    
                                    // Проверяем finalgrade, который учитывает принудительно проставленные оценки
                                    if ($usergrade && $usergrade->finalgrade !== null && $usergrade->finalgrade >= 0) {
                                        return true; // Есть оценка в gradebook (включая принудительно проставленную)
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // Игнорируем ошибки
                        }
                        
                        return false; // Нет оценки
                    };
                    
                    // Получаем задания курса
                    try {
                        $assignments = get_all_instances_in_course('assign', $course, false);
                    } catch (\Exception $e) {
                        $assignments = [];
                    }
                    if (!is_array($assignments)) {
                        $assignments = [];
                    }
                    
                    foreach ($assignments as $assignment) {
                        // Получаем cmid для проверки видимости
                        $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                        if (!$cm) {
                            continue; // Пропускаем, если модуль не найден
                        }
                        
                        // Пропускаем скрытые элементы курса
                        if (!$cm->visible || !$cm->visibleoncoursepage) {
                            continue;
                        }
                        
                        $assignmentname = mb_strtolower($assignment->name);
                        
                        // Определяем тип задания по названию
                        $assignmenttype = '';
                        if (strpos($assignmentname, 'отчет') !== false && strpos($assignmentname, 'чтени') !== false) {
                            $assignmenttype = 'reading_report';
                        } elseif (strpos($assignmentname, 'письменн') !== false) {
                            $assignmenttype = 'written_work';
                        }
                        
                        if ($assignmenttype == 'reading_report') {
                            // Получаем cmid для ссылки (уже получен выше)
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            // Проверяем статус задания для студента
                            // Получаем любую submission (не только submitted, но и draft и другие статусы)
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $viewingstudent->id
                            ]);
                            
                            // Проверяем, есть ли файлы или текст в submission
                            $hasfiles = false;
                            if ($submission) {
                                // Проверяем наличие файлов через assignsubmission_file (плагин file)
                                $filecount = $DB->count_records_sql(
                                    "SELECT COUNT(*) FROM {assignsubmission_file} WHERE submission = ?",
                                    [$submission->id]
                                );
                                
                                // Проверяем наличие текста через assignsubmission_onlinetext (плагин onlinetext)
                                $textcount = $DB->count_records_sql(
                                    "SELECT COUNT(*) FROM {assignsubmission_onlinetext} WHERE submission = ? AND onlinetext IS NOT NULL AND onlinetext != ''",
                                    [$submission->id]
                                );
                                
                                $hasfiles = ($filecount > 0 || $textcount > 0);
                            }
                            
                            // Используем функцию для проверки оценки (включая принудительно проставленные)
                            $hasgrade = $checkAssignmentGrade($assignment->id, $viewingstudent->id);
                            
                            if ($hasgrade) {
                                // Есть оценка - зеленый "Чтение – сдано"
                                $badgecontent = html_writer::link($assignmenturl, 'Чтение – сдано', [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-green">' . $badgecontent . '</span>';
                            } elseif ($hasfiles) {
                                // Файл загружен, но нет оценки - желтый "Чтение – не проверено"
                                $badgecontent = html_writer::link($assignmenturl, 'Чтение – не проверено', [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-yellow">' . $badgecontent . '</span>';
                            } else {
                                // Нет файлов и нет оценки - красный "Чтение – не сдано"
                                $badgecontent = html_writer::link($assignmenturl, 'Чтение – не сдано', [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-red">' . $badgecontent . '</span>';
                            }
                        } elseif ($assignmenttype == 'written_work') {
                            // Письменная работа
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm) {
                                continue;
                            }
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $viewingstudent->id
                            ]);
                            
                            $hasfiles = false;
                            if ($submission) {
                                $filecount = $DB->count_records_sql(
                                    "SELECT COUNT(*) FROM {assignsubmission_file} WHERE submission = ?",
                                    [$submission->id]
                                );
                                $textcount = $DB->count_records_sql(
                                    "SELECT COUNT(*) FROM {assignsubmission_onlinetext} WHERE submission = ? AND onlinetext IS NOT NULL AND onlinetext != ''",
                                    [$submission->id]
                                );
                                $hasfiles = ($filecount > 0 || $textcount > 0);
                            }
                            
                            // Используем функцию для проверки оценки (включая принудительно проставленные)
                            $hasgrade = $checkAssignmentGrade($assignment->id, $viewingstudent->id);
                            
                            // Определяем текст для отображения
                            if (mb_strtolower(trim($assignment->name)) == 'сдача письменной работы') {
                                $basename = 'Письменная работа';
                            } else {
                                $basename = htmlspecialchars($assignment->name, ENT_QUOTES, 'UTF-8');
                            }
                            
                            // Показываем письменную работу во всех состояниях
                            if ($hasgrade) {
                                // Есть оценка - зеленый "Письменная работа - сдано"
                                $statustext = $basename . ' - сдано';
                                $badgecontent = html_writer::link($assignmenturl, $statustext, [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-green">' . $badgecontent . '</span>';
                            } elseif ($hasfiles) {
                                // Файл загружен, но нет оценки - желтый "Письменная работа - не проверено"
                                $statustext = $basename . ' - не проверено';
                                $badgecontent = html_writer::link($assignmenturl, $statustext, [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-yellow">' . $badgecontent . '</span>';
                            } else {
                                // Нет файлов и нет оценки - красный "Письменная работа - не сдано"
                                $statustext = $basename . ' - не сдано';
                                $badgecontent = html_writer::link($assignmenturl, $statustext, [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-red">' . $badgecontent . '</span>';
                            }
                        }
                    }
                    
                    // Получаем тесты (экзамены)
                    try {
                        $quizzes = get_all_instances_in_course('quiz', $course, false);
                    } catch (\Exception $e) {
                        $quizzes = [];
                    }
                    if (!is_array($quizzes)) {
                        $quizzes = [];
                    }
                    
                    foreach ($quizzes as $quiz) {
                        // Получаем cmid для проверки видимости
                        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
                        if (!$cm) {
                            continue;
                        }
                        
                        // Пропускаем скрытые элементы курса
                        if (!$cm->visible || !$cm->visibleoncoursepage) {
                            continue;
                        }
                        
                        $quizname = mb_strtolower($quiz->name);
                        if (strpos($quizname, 'экзамен') !== false) {
                            $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
                            
                            // Проверяем оценку через quiz_grades
                            $grade = $DB->get_record('quiz_grades', [
                                'quiz' => $quiz->id,
                                'userid' => $viewingstudent->id
                            ]);
                            
                            $hasgrade = false;
                            if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                                $hasgrade = true;
                            } else {
                                // Если нет оценки в quiz_grades, проверяем через gradebook API (учитывает принудительно проставленные оценки)
                                try {
                                    require_once($CFG->dirroot . '/lib/gradelib.php');
                                    $gradeitem = grade_item::fetch([
                                        'itemtype' => 'mod',
                                        'itemmodule' => 'quiz',
                                        'iteminstance' => $quiz->id,
                                        'courseid' => $course->id
                                    ]);
                                    
                                    if ($gradeitem) {
                                        $usergrade = grade_grade::fetch([
                                            'itemid' => $gradeitem->id,
                                            'userid' => $viewingstudent->id
                                        ]);
                                        
                                        // Проверяем finalgrade, который учитывает принудительно проставленные оценки
                                        if ($usergrade && $usergrade->finalgrade !== null && $usergrade->finalgrade >= 0) {
                                            $hasgrade = true;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    // Игнорируем ошибки
                                }
                            }
                            
                            if ($hasgrade) {
                                // Экзамен сдан - зеленый
                                $badgecontent = html_writer::link($quizurl, 'Экзамен – сдан', [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-green">' . $badgecontent . '</span>';
                            } else {
                                // Экзамен не сдан - красный
                                $badgecontent = html_writer::link($quizurl, 'Экзамен – не сдан', [
                                    'class' => 'assignment-status-link',
                                    'target' => '_blank'
                                ]);
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-red">' . $badgecontent . '</span>';
                            }
                        }
                    }
                    
                    if (!empty($statusitems)) {
                        $statushtml = implode(' ', $statusitems);
                    } else {
                        $statushtml = '<span class="text-muted">Нет домашних заданий</span>';
                    }
                    
                    echo html_writer::tag('td', $statushtml);
                    
                    // Функция для получения итоговой оценки курса
                    // Используем grade_grade::fetch() для получения итоговой оценки с учетом всех переопределений (overridden)
                    // Свойство finalgrade автоматически учитывает принудительно проставленные оценки
                    $coursegrade = null;
                    $finalgradepercent = null;
                    $courseitem = null;
                    try {
                        $courseitem = grade_item::fetch_course_item($course->id);
                        if ($courseitem) {
                            $usergrade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $viewingstudent->id]);
                            if ($usergrade && $usergrade->finalgrade !== null) {
                                // finalgrade уже учитывает переопределенные (overridden) оценки в Moodle
                                // Это финальная оценка с учетом всех принудительно проставленных оценок
                                $coursegrade = $usergrade->finalgrade;
                                // Используем оценку напрямую из Moodle как процент
                                // Предполагаем, что оценка уже в процентах или максимум всегда 100
                                $finalgradepercent = $coursegrade;
                            }
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки получения оценки
                        $courseitem = null;
                    }
                    
                    // Функция для проверки, все ли задания имеют оценку
                    $allassignmentsgraded = true;
                    $hasassignments = false;
                    try {
                        $assignments = get_all_instances_in_course('assign', $course, false);
                        $quizzes = get_all_instances_in_course('quiz', $course, false);
                        
                        if (!is_array($assignments)) {
                            $assignments = [];
                        }
                        if (!is_array($quizzes)) {
                            $quizzes = [];
                        }
                        
                        // Проверяем задания
                        foreach ($assignments as $assignment) {
                            // Проверяем видимость модуля
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm || !$cm->visible || !$cm->visibleoncoursepage) {
                                continue; // Пропускаем скрытые элементы
                            }
                            
                            $assignmentname = mb_strtolower($assignment->name);
                            // Проверяем отчеты о чтении
                            if (strpos($assignmentname, 'отчет') !== false && strpos($assignmentname, 'чтени') !== false) {
                                $hasassignments = true;
                                // Используем функцию для проверки оценки (включая принудительно проставленные)
                                if (!$checkAssignmentGrade($assignment->id, $viewingstudent->id)) {
                                    $allassignmentsgraded = false;
                                }
                            }
                            // Проверяем письменные работы
                            if (strpos($assignmentname, 'письменн') !== false) {
                                $hasassignments = true;
                                // Проверяем наличие файлов или текста
                                $submission = $DB->get_record('assign_submission', [
                                    'assignment' => $assignment->id,
                                    'userid' => $viewingstudent->id
                                ]);
                                $hasfiles = false;
                                if ($submission) {
                                    $filecount = $DB->count_records_sql(
                                        "SELECT COUNT(*) FROM {assignsubmission_file} WHERE submission = ?",
                                        [$submission->id]
                                    );
                                    $textcount = $DB->count_records_sql(
                                        "SELECT COUNT(*) FROM {assignsubmission_onlinetext} WHERE submission = ? AND onlinetext IS NOT NULL AND onlinetext != ''",
                                        [$submission->id]
                                    );
                                    $hasfiles = ($filecount > 0 || $textcount > 0);
                                }
                                // Письменная работа считается сданной, если есть оценка (включая принудительно проставленную) ИЛИ есть файлы
                                if (!$checkAssignmentGrade($assignment->id, $viewingstudent->id) && !$hasfiles) {
                                    $allassignmentsgraded = false;
                                }
                            }
                        }
                        
                        // Проверяем тесты (экзамены)
                        foreach ($quizzes as $quiz) {
                            // Проверяем видимость модуля
                            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
                            if (!$cm || !$cm->visible || !$cm->visibleoncoursepage) {
                                continue; // Пропускаем скрытые элементы
                            }
                            
                            $quizname = mb_strtolower($quiz->name);
                            if (strpos($quizname, 'экзамен') !== false) {
                                $hasassignments = true;
                                
                                // Проверяем оценку через quiz_grades
                                $grade = $DB->get_record('quiz_grades', [
                                    'quiz' => $quiz->id,
                                    'userid' => $viewingstudent->id
                                ]);
                                
                                $hasgrade = false;
                                if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                                    $hasgrade = true;
                                } else {
                                    // Если нет оценки в quiz_grades, проверяем через gradebook API (учитывает принудительно проставленные оценки)
                                    try {
                                        require_once($CFG->dirroot . '/lib/gradelib.php');
                                        $gradeitem = grade_item::fetch([
                                            'itemtype' => 'mod',
                                            'itemmodule' => 'quiz',
                                            'iteminstance' => $quiz->id,
                                            'courseid' => $course->id
                                        ]);
                                        
                                        if ($gradeitem) {
                                            $usergrade = grade_grade::fetch([
                                                'itemid' => $gradeitem->id,
                                                'userid' => $viewingstudent->id
                                            ]);
                                            
                                            // Проверяем finalgrade, который учитывает принудительно проставленные оценки
                                            if ($usergrade && $usergrade->finalgrade !== null && $usergrade->finalgrade >= 0) {
                                                $hasgrade = true;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // Игнорируем ошибки
                                    }
                                }
                                
                                if (!$hasgrade) {
                                    $allassignmentsgraded = false;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки
                    }
                    
                    // Статус завершения курса
                    $completionstatus = '';
                    $completionclass = '';
                    // Если итоговая оценка ниже 70%, но все задания имеют оценку - "Завершен полностью" (зеленым)
                    if (($finalgradepercent === null || $finalgradepercent < 70) && $allassignmentsgraded && $hasassignments) {
                        $completionstatus = 'Завершен полностью';
                        $completionclass = 'completion-status-completed';
                    } elseif ($finalgradepercent === null || $finalgradepercent < 70) {
                        $completionstatus = 'Не завершен';
                        $completionclass = 'completion-status-not-completed';
                    } elseif ($finalgradepercent >= 70) {
                        // Если нет заданий в курсе, но оценка >= 70% - завершен полностью
                        if (!$hasassignments) {
                            $completionstatus = 'Завершен полностью';
                            $completionclass = 'completion-status-completed';
                        } elseif (!$allassignmentsgraded) {
                            // Есть задания и не все оценены - частично завершен
                            $completionstatus = 'Завершен частично';
                            $completionclass = 'completion-status-partial';
                        } else {
                            // Все задания оценены - полностью завершен
                            $completionstatus = 'Завершен полностью';
                            $completionclass = 'completion-status-completed';
                        }
                    }
                    
                    // Добавляем процент завершения к статусу через дефис
                    if ($finalgradepercent !== null) {
                        $completionstatus .= ' - ' . round($finalgradepercent, 2) . '%';
                    }
                    
                    echo html_writer::tag('td', 
                        '<span class="' . $completionclass . '">' . htmlspecialchars($completionstatus, ENT_QUOTES, 'UTF-8') . '</span>'
                    );
                    
                    // Итоговая оценка
                    $gradeText = '';
                    $gradeClass = '';
                    $gradeIcon = '';
                    $numericGrade = null; // Числовая оценка для тестового режима
                    
                    // В тестовом режиме используем округленную оценку для определения текста
                    $percentForDisplay = $finalgradepercent;
                    if ($testmode && $coursegrade !== null) {
                        // Используем округленную оценку напрямую как процент
                        $percentForDisplay = round($coursegrade, 2);
                    }
                    
                    if ($finalgradepercent === null && $percentForDisplay === null) {
                        // Если нет оценки вообще
                        $gradeText = 'нет оценки';
                        $gradeClass = 'grade-badge-no-grade';
                        $gradeIcon = '<i class="fas fa-minus-circle"></i>';
                        $numericGrade = null;
                    } elseif ($percentForDisplay !== null && $percentForDisplay < 70) {
                        // Если оценка ниже 70% - показываем "нет оценки" серым цветом
                        $gradeText = 'нет оценки';
                        $gradeClass = 'grade-badge-no-grade';
                        $gradeIcon = '<i class="fas fa-minus-circle"></i>';
                        $numericGrade = round($percentForDisplay, 1);
                    } elseif ($percentForDisplay >= 70 && $percentForDisplay < 80) {
                        $gradeText = '3 (удовлетворительно)';
                        $gradeClass = 'grade-badge-satisfactory';
                        $gradeIcon = '<i class="fas fa-check-circle"></i>';
                        $numericGrade = round($percentForDisplay, 1);
                    } elseif ($percentForDisplay >= 80 && $percentForDisplay < 90) {
                        $gradeText = '4 (хорошо)';
                        $gradeClass = 'grade-badge-good';
                        $gradeIcon = '<i class="fas fa-star"></i>';
                        $numericGrade = round($percentForDisplay, 1);
                    } elseif ($percentForDisplay >= 90) {
                        $gradeText = '5 (отлично)';
                        $gradeClass = 'grade-badge-excellent';
                        $gradeIcon = '<i class="fas fa-trophy"></i>';
                        $numericGrade = round($percentForDisplay, 1);
                    }
                    
                    // Формируем бейдж с оценкой
                    $gradeBadgeContent = $gradeIcon . htmlspecialchars($gradeText, ENT_QUOTES, 'UTF-8');
                    
                    // Если тестовый режим, показываем все цифры итоговой оценки
                    if ($testmode) {
                        $testInfo = [];
                        if ($coursegrade !== null) {
                            $roundedGrade = round($coursegrade, 2);
                            $testInfo[] = 'Оценка: ' . $roundedGrade;
                        }
                        if ($percentForDisplay !== null) {
                            // Используем оценку напрямую как процент
                            $testInfo[] = 'Процент: ' . round($percentForDisplay, 2) . '%';
                        }
                        if (!empty($testInfo)) {
                            $gradeBadgeContent .= ' <span class="grade-numeric-value" style="display: block; font-size: 11px; margin-top: 4px; opacity: 0.8;">' . implode(' | ', $testInfo) . '</span>';
                        }
                    }
                    
                    $gradeBadge = '<span class="grade-badge ' . $gradeClass . '">' . $gradeBadgeContent . '</span>';
                    echo html_writer::tag('td', $gradeBadge);
                    
                    // Перейти к курсу
                    echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
                    echo html_writer::link($courseurl, '<i class="fas fa-external-link-alt"></i>', [
                        'class' => 'btn btn-sm btn-primary',
                        'title' => 'Перейти к курсу',
                        'target' => '_blank'
                    ]);
                    echo html_writer::end_tag('td');
                    
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                echo html_writer::end_div(); // courses-table
                echo html_writer::end_div(); // courses-table-container
            }
        } catch (\Exception $e) {
            echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
        }
        
        echo html_writer::end_div();
        break;
    
    case 'programs':
        // Вкладка "Личная информация и статус"
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        
        // Подвкладки
        $subtabs = [];
        $subtaburlparams = ['tab' => 'programs', 'subtab' => 'programs'];
        if ($studentid > 0) {
            $subtaburlparams['studentid'] = $studentid;
        }
        $subtabs[] = new tabobject('programs', 
            new moodle_url('/local/deanpromoodle/pages/student.php', $subtaburlparams),
            'Программы');
        $subtaburlparams['subtab'] = 'additional';
        $subtabs[] = new tabobject('additional', 
            new moodle_url('/local/deanpromoodle/pages/student.php', $subtaburlparams),
            'Дополнительные данные');
        
        echo $OUTPUT->tabtree($subtabs, $subtab);
        
        // Содержимое подвкладок
        switch ($subtab) {
        case 'programs':
            // Подвкладка "Программы"
            try {
                // Получаем когорты, к которым принадлежит студент
            $studentcohorts = $DB->get_records_sql(
                "SELECT c.id, c.name, c.idnumber, c.description
                 FROM {cohort_members} cm
                 JOIN {cohort} c ON c.id = cm.cohortid
                 WHERE cm.userid = ?
                 ORDER BY c.name ASC",
                [$viewingstudent->id]
            );
            
            if (empty($studentcohorts)) {
                echo html_writer::div(get_string('nocohortsfound', 'local_deanpromoodle'), 'alert alert-warning');
            } else {
                // Получаем программы, связанные с когортами студента
                $cohortids = array_keys($studentcohorts);
                $placeholders = implode(',', array_fill(0, count($cohortids), '?'));
                
                $programs = $DB->get_records_sql(
                    "SELECT DISTINCT p.id, p.name, p.code, p.description, p.institution,
                            GROUP_CONCAT(DISTINCT c.id ORDER BY c.name SEPARATOR ',') as cohortids,
                            GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as cohortnames
                     FROM {local_deanpromoodle_programs} p
                     JOIN {local_deanpromoodle_program_cohorts} pc ON pc.programid = p.id
                     JOIN {cohort} c ON c.id = pc.cohortid
                     WHERE pc.cohortid IN ($placeholders)
                     AND p.visible = 1
                     GROUP BY p.id, p.name, p.code, p.description, p.institution
                     ORDER BY p.name ASC",
                    $cohortids
                );
                
                if (empty($programs)) {
                    echo html_writer::div(get_string('noprogramsfound', 'local_deanpromoodle'), 'alert alert-warning');
                } else {
                    // Стили для таблицы программ
                    echo html_writer::start_tag('style');
                    echo "
                        .programs-table {
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            overflow: hidden;
                        }
                        .programs-table table {
                            margin: 0;
                            width: 100%;
                        }
                        .programs-table thead th {
                            background-color: #f8f9fa;
                            padding: 12px 16px;
                            text-align: left;
                            font-weight: 600;
                            color: #495057;
                            border-bottom: 2px solid #dee2e6;
                        }
                        .programs-table tbody tr {
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .programs-table tbody tr:hover {
                            background-color: #f8f9fa;
                        }
                        .programs-table tbody td {
                            padding: 12px 16px;
                            vertical-align: top;
                        }
                        .cohort-badge {
                            display: inline-block;
                            margin: 2px 4px 2px 0;
                            padding: 4px 8px;
                            background-color: #6c757d;
                            color: white;
                            border-radius: 4px;
                            font-size: 0.85em;
                        }
                        .program-description {
                            max-width: 400px;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                    ";
                    echo html_writer::end_tag('style');
                    
                    echo html_writer::start_div('programs-table');
                    echo html_writer::start_tag('table', ['class' => 'table']);
                    echo html_writer::start_tag('thead');
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('th', 'Название программы');
                    echo html_writer::tag('th', 'Код', ['style' => 'width: 150px;']);
                    echo html_writer::tag('th', 'Учебное заведение', ['style' => 'width: 200px;']);
                    echo html_writer::tag('th', 'Группы', ['style' => 'width: 200px;']);
                    echo html_writer::tag('th', 'Описание', ['style' => 'width: 300px;']);
                    echo html_writer::end_tag('tr');
                    echo html_writer::end_tag('thead');
                    echo html_writer::start_tag('tbody');
                    
                    foreach ($programs as $program) {
                        echo html_writer::start_tag('tr');
                        
                        // Название программы (кликабельное - открывает новую страницу)
                        $programname = htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8');
                        $programurlparams = [
                            'action' => 'viewprogram',
                            'programid' => $program->id
                        ];
                        if ($studentid > 0) {
                            $programurlparams['studentid'] = $studentid;
                        }
                        $programurl = new moodle_url('/local/deanpromoodle/pages/student.php', $programurlparams);
                        echo html_writer::start_tag('td');
                        echo html_writer::link($programurl, $programname, [
                            'style' => 'font-weight: 500; color: #007bff; text-decoration: none;',
                            'target' => '_blank'
                        ]);
                        echo html_writer::end_tag('td');
                        
                        // Код
                        $code = is_string($program->code) ? $program->code : '';
                        echo html_writer::tag('td', $code ? htmlspecialchars($code, ENT_QUOTES, 'UTF-8') : '-');
                        
                        // Учебное заведение
                        $institution = is_string($program->institution) ? $program->institution : '';
                        echo html_writer::tag('td', $institution ? htmlspecialchars($institution, ENT_QUOTES, 'UTF-8') : '-');
                        
                        // Группы (когорты)
                        echo html_writer::start_tag('td');
                        if (!empty($program->cohortnames)) {
                            $cohortnamesarray = explode(', ', $program->cohortnames);
                            foreach ($cohortnamesarray as $cohortname) {
                                echo html_writer::tag('span', htmlspecialchars(trim($cohortname), ENT_QUOTES, 'UTF-8'), [
                                    'class' => 'cohort-badge',
                                    'title' => 'Группа: ' . htmlspecialchars(trim($cohortname), ENT_QUOTES, 'UTF-8')
                                ]);
                            }
                        } else {
                            echo '-';
                        }
                        echo html_writer::end_tag('td');
                        
                        // Описание
                        echo html_writer::start_tag('td');
                        if (!empty($program->description)) {
                            $description = strip_tags($program->description);
                            if (mb_strlen($description) > 100) {
                                $description = mb_substr($description, 0, 100) . '...';
                            }
                            echo html_writer::tag('div', htmlspecialchars($description, ENT_QUOTES, 'UTF-8'), [
                                'class' => 'program-description',
                                'title' => htmlspecialchars($program->description, ENT_QUOTES, 'UTF-8')
                            ]);
                        } else {
                            echo '-';
                        }
                        echo html_writer::end_tag('td');
                        
                        echo html_writer::end_tag('tr');
                    }
                    
                    echo html_writer::end_tag('tbody');
                    echo html_writer::end_tag('table');
                    echo html_writer::end_div();
                }
            }
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
            }
            break;
            
        case 'additional':
            // Подвкладка "Дополнительные данные"
            try {
                // Проверяем права на редактирование
                $canedit = false;
                if ($isadmin || $isteacher) {
                    // Админ и преподаватель могут редактировать любые данные
                    $canedit = true;
                } elseif ($isstudent && $viewingstudent->id == $USER->id) {
                    // Студент может редактировать только свои данные
                    $canedit = true;
                }
                
                // Обработка импорта Excel
                // Обработка скачивания списка не найденных студентов
                if ($action == 'downloadnotfound' && ($isadmin || $isteacher)) {
                    require_sesskey();
                    
                    // Получаем данные из сессии
                    $sessionkey = 'notfound_students_' . $USER->id;
                    $notfounddata = $SESSION->$sessionkey;
                    
                    if (!empty($notfounddata) && is_array($notfounddata) && isset($notfounddata['students'])) {
                        // Устанавливаем заголовки для скачивания CSV
                        header('Content-Type: text/csv; charset=UTF-8');
                        header('Content-Disposition: attachment; filename="not_found_students_' . date('Y-m-d_H-i-s') . '.csv"');
                        
                        // Добавляем BOM для правильного отображения кириллицы в Excel
                        echo "\xEF\xBB\xBF";
                        
                        // Открываем поток вывода
                        $output = fopen('php://output', 'w');
                        
                        // Заголовки CSV
                        fputcsv($output, [
                            '№ строки',
                            'Фамилия',
                            'Имя',
                            'Отчество',
                            'Email',
                            'Группа',
                            'Попытки поиска'
                        ], ';');
                        
                        // Записываем данные
                        foreach ($notfounddata['students'] as $student) {
                            $lastname = isset($student['lastname']) ? $student['lastname'] : '';
                            $firstname = isset($student['firstname']) ? $student['firstname'] : '';
                            $middlename = isset($student['middlename']) ? $student['middlename'] : '';
                            
                            // Объединяем попытки поиска в одну строку
                            $attempts = isset($student['attempts']) ? str_replace(['<br>', '<br />'], '; ', strip_tags($student['attempts'])) : '';
                            
                            fputcsv($output, [
                                isset($student['row']) ? $student['row'] : '',
                                $lastname,
                                $firstname,
                                $middlename,
                                isset($student['email']) ? $student['email'] : '',
                                isset($student['cohort']) ? $student['cohort'] : '',
                                $attempts
                            ], ';');
                        }
                        
                        fclose($output);
                        
                        // Очищаем данные из сессии после скачивания
                        unset($SESSION->$sessionkey);
                        exit;
                    }
                    
                    echo html_writer::div('Ошибка: данные для скачивания не найдены', 'alert alert-danger');
                }
                
                if ($action == 'importexcel' && ($isadmin || $isteacher)) {
                    require_sesskey();
                    
                    $importsubmitted = optional_param('import_submit', 0, PARAM_INT);
                    if ($importsubmitted) {
                        $file = $_FILES['excelfile'] ?? null;
                        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                            echo html_writer::div('Ошибка загрузки файла. Убедитесь, что файл выбран и не превышает максимальный размер.', 'alert alert-danger');
                        } else {
                            // Проверяем тип файла
                            $fileext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (!in_array($fileext, ['xlsx', 'xls', 'csv'])) {
                                echo html_writer::div('Неверный тип файла. Загрузите файл в формате Excel (.xlsx, .xls) или CSV.', 'alert alert-danger');
                            } else {
                                // Парсим файл
                                $imported = 0;
                                $skipped = 0;
                                $errors = [];
                                
                                try {
                                    $rows = [];
                                    
                                    // Проверяем доступность PhpSpreadsheet
                                    $phpspreadsheetpaths = [
                                        $CFG->libdir . '/phpspreadsheet/vendor/autoload.php',
                                        $CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php',
                                        $CFG->dirroot . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php',
                                        $CFG->dirroot . '/vendor/autoload.php'
                                    ];
                                    
                                    $usePhpSpreadsheet = false;
                                    $phpspreadsheetpath = null;
                                    
                                    foreach ($phpspreadsheetpaths as $path) {
                                        if (file_exists($path)) {
                                            $phpspreadsheetpath = $path;
                                            $usePhpSpreadsheet = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($usePhpSpreadsheet && ($fileext == 'xlsx' || $fileext == 'xls')) {
                                        try {
                                            require_once($phpspreadsheetpath);
                                            
                                            // Используем PhpSpreadsheet для Excel файлов
                                            if (strpos($phpspreadsheetpath, 'IOFactory.php') !== false) {
                                                // Прямой путь к IOFactory
                                                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                                            } else {
                                                // Через autoload
                                                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                                            }
                                            $worksheet = $spreadsheet->getActiveSheet();
                                            $rows = $worksheet->toArray();
                                        } catch (\Exception $e) {
                                            // Если PhpSpreadsheet не работает, пробуем альтернативные методы
                                            throw new Exception('Ошибка при чтении Excel файла через PhpSpreadsheet: ' . $e->getMessage() . '. Попробуйте сохранить файл как CSV.');
                                        }
                                    } elseif ($fileext == 'csv') {
                                        // Обработка CSV файлов
                                        if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                                            // Определяем кодировку и разделитель
                                            $firstline = fgets($handle);
                                            rewind($handle);
                                            
                                            // Пробуем определить разделитель
                                            $delimiter = ',';
                                            if (strpos($firstline, ';') !== false) {
                                                $delimiter = ';';
                                            } elseif (strpos($firstline, "\t") !== false) {
                                                $delimiter = "\t";
                                            }
                                            
                                            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                                                // Конвертируем из разных кодировок в UTF-8
                                                $data = array_map(function($field) {
                                                    if (!empty($field)) {
                                                        $encoding = mb_detect_encoding($field, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);
                                                        if ($encoding !== 'UTF-8' && $encoding !== false) {
                                                            return mb_convert_encoding($field, 'UTF-8', $encoding);
                                                        }
                                                    }
                                                    return $field;
                                                }, $data);
                                                $rows[] = $data;
                                            }
                                            fclose($handle);
                                        }
                                    } else {
                                        // Для Excel файлов без PhpSpreadsheet пробуем альтернативные методы
                                        // Метод 1: Попытка прочитать .xlsx как ZIP архив (только для чтения структуры)
                                        if ($fileext == 'xlsx') {
                                            // .xlsx файлы - это ZIP архивы с XML файлами
                                            // Можно попробовать извлечь sharedStrings.xml, но это сложно
                                            // Лучше предложить CSV
                                            throw new Exception('PhpSpreadsheet не доступна для чтения Excel файлов. Пожалуйста, сохраните файл как CSV: в Excel выберите "Файл" → "Сохранить как" → выберите формат "CSV UTF-8 (разделитель - запятая)" и загрузите CSV файл.');
                                        } elseif ($fileext == 'xls') {
                                            // Старый формат Excel - еще сложнее без библиотеки
                                            throw new Exception('PhpSpreadsheet не доступна для чтения старых Excel файлов (.xls). Пожалуйста, откройте файл в Excel и сохраните как CSV: "Файл" → "Сохранить как" → выберите формат "CSV UTF-8 (разделитель - запятая)" и загрузите CSV файл.');
                                        } else {
                                            throw new Exception('Неподдерживаемый формат файла. Поддерживаются форматы: .xlsx, .xls, .csv');
                                        }
                                    }
                                    
                                    if (empty($rows) || count($rows) < 2) {
                                        echo html_writer::div('Файл пуст или содержит только заголовки.', 'alert alert-warning');
                                    } else {
                                        // Первая строка - заголовки
                                        // Нормализуем заголовки: убираем пробелы, приводим к нижнему регистру, заменяем подчеркивания на пробелы
                                        $headers = array_map(function($header) {
                                            $header = trim($header);
                                            $header = mb_strtolower($header, 'UTF-8');
                                            // Заменяем множественные пробелы и подчеркивания на одинарные пробелы
                                            $header = preg_replace('/[\s_]+/u', ' ', $header);
                                            return trim($header);
                                        }, $rows[0]);
                                        
                                        // Маппинг названий колонок (разные варианты написания)
                                        $columnmap = [
                                            'lastname' => ['фамилия', 'фамилия студента', 'lastname', 'surname', 'last name', 'фамилиястудента'],
                                            'firstname' => ['имя', 'имя студента', 'firstname', 'name', 'first name', 'имястудента'],
                                            'middlename' => ['отчество', 'отчество студента', 'middlename', 'patronymic', 'middle name', 'отчествостудента'],
                                            'email' => ['email', 'e-mail', 'электронная почта', 'почта', 'e mail', 'эл почта', 'электроннаяпочта'],
                                            'status' => ['статус', 'status'],
                                            'enrollment_year' => ['год поступления', 'годпоступления', 'enrollment year', 'enrollment_year', 'year', 'год поступления', 'год_поступления'],
                                            'gender' => ['пол', 'gender', 'sex'],
                                            'birthdate' => ['дата рождения', 'датарождения', 'birthdate', 'birth date', 'birth_date', 'дата рождения', 'дата_рождения'],
                                            'snils' => ['снилс', 'снилс студента', 'snils', 'снилсстудента'],
                                            'mobile' => ['мобильный', 'мобильный телефон', 'mobile', 'phone', 'телефон', 'мобильныйтелефон', 'моб телефон'],
                                            'citizenship' => ['гражданство', 'citizenship'],
                                            'birthplace' => ['место рождения', 'место рождения', 'месторождения', 'birthplace', 'birth place', 'birth_place'],
                                            'id_type' => ['тип удостоверения', 'типудостоверения', 'id type', 'id_type', 'document type', 'document_type', 'тип документа'],
                                            'passport_number' => ['номер паспорта', 'номерпаспорта', 'passport number', 'passport_number', 'passport', 'номер паспорта', 'номер_паспорта'],
                                            'passport_issued_by' => ['кем выдан паспорт', 'кемвыданпаспорт', 'passport issued by', 'passport_issued_by', 'issued by', 'issued_by', 'кем выдан'],
                                            'passport_issue_date' => ['дата выдачи паспорта', 'датавыдачипаспорта', 'passport issue date', 'passport_issue_date', 'issue date', 'issue_date', 'дата выдачи'],
                                            'passport_division_code' => ['код подразделения', 'кодподразделения', 'passport division code', 'passport_division_code', 'division code', 'division_code', 'код подразделения'],
                                            'postal_index' => ['индекс', 'почтовый индекс', 'postal index', 'postal_index', 'index', 'почтовыйиндекс', 'почтовый индекс'],
                                            'country' => ['страна', 'country'],
                                            'region' => ['регион область', 'регион', 'область', 'region', 'регион/область', 'регион область'],
                                            'city' => ['город', 'city'],
                                            'street' => ['улица', 'street'],
                                            'house_apartment' => ['дом квартира', 'дом', 'квартира', 'house apartment', 'house_apartment', 'address', 'дом/квартира', 'дом квартира'],
                                            'previous_institution' => ['предыдущее учебное заведение', 'предыдущееучебноезаведение', 'previous institution', 'previous_institution', 'предыдущее заведение'],
                                            'previous_institution_year' => ['год окончания предыдущего учебного заведения', 'годокончанияпредыдущегоучебногозаведения', 'previous institution year', 'previous_institution_year', 'год окончания'],
                                            'cohort' => ['группа', 'cohort', 'group']
                                        ];
                                        
                                        // Находим индексы колонок с более гибким поиском
                                        $columnindexes = [];
                                        foreach ($columnmap as $field => $variants) {
                                            foreach ($variants as $variant) {
                                                // Нормализуем вариант для поиска
                                                $normalizedvariant = preg_replace('/[\s_]+/u', ' ', trim(mb_strtolower($variant, 'UTF-8')));
                                                
                                                // Ищем точное совпадение
                                                $index = array_search($normalizedvariant, $headers);
                                                if ($index !== false) {
                                                    $columnindexes[$field] = $index;
                                                    break; // Используем первое найденное совпадение
                                                }
                                                
                                                // Если не найдено, пробуем частичное совпадение
                                                foreach ($headers as $idx => $header) {
                                                    if (strpos($header, $normalizedvariant) !== false || strpos($normalizedvariant, $header) !== false) {
                                                        $columnindexes[$field] = $idx;
                                                        break 2; // Выходим из обоих циклов
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // Отладочная информация о найденных колонках
                                        $foundcolumns = [];
                                        $missingcolumns = [];
                                        if (isset($columnindexes['lastname'])) $foundcolumns[] = 'Фамилия';
                                        else $missingcolumns[] = 'Фамилия';
                                        if (isset($columnindexes['firstname'])) $foundcolumns[] = 'Имя';
                                        else $missingcolumns[] = 'Имя';
                                        if (isset($columnindexes['email'])) $foundcolumns[] = 'Email';
                                        else $missingcolumns[] = 'Email';
                                        
                                        // Проверяем обязательные поля
                                        if (!isset($columnindexes['lastname']) || !isset($columnindexes['firstname']) || !isset($columnindexes['email'])) {
                                            $errorMsg = 'В файле отсутствуют обязательные колонки: ' . implode(', ', $missingcolumns) . '.';
                                            $errorMsg .= '<br>Найденные колонки: ' . (empty($foundcolumns) ? 'нет' : implode(', ', $foundcolumns)) . '.';
                                            $errorMsg .= '<br>Заголовки в файле: ' . implode(', ', array_slice($rows[0], 0, 10)) . (count($rows[0]) > 10 ? '...' : '');
                                            echo html_writer::div($errorMsg, 'alert alert-danger');
                                        } else {
                                            $transaction = $DB->start_delegated_transaction();
                                            try {
                                                // Инициализируем массив для не найденных студентов
                                                $notfoundstudents = [];
                                                
                                                // Обрабатываем каждую строку данных
                                                for ($i = 1; $i < count($rows); $i++) {
                                                    $row = $rows[$i];
                                                    
                                                    // Пропускаем пустые строки
                                                    if (empty(array_filter($row))) {
                                                        continue;
                                                    }
                                                    
                                                    // Извлекаем данные
                                                    $lastname = isset($columnindexes['lastname']) && isset($row[$columnindexes['lastname']]) ? trim($row[$columnindexes['lastname']]) : '';
                                                    $firstname = isset($columnindexes['firstname']) && isset($row[$columnindexes['firstname']]) ? trim($row[$columnindexes['firstname']]) : '';
                                                    $middlename = isset($columnindexes['middlename']) && isset($row[$columnindexes['middlename']]) ? trim($row[$columnindexes['middlename']]) : '';
                                                    $email = isset($columnindexes['email']) && isset($row[$columnindexes['email']]) ? trim($row[$columnindexes['email']]) : '';
                                                    $cohort = isset($columnindexes['cohort']) && isset($row[$columnindexes['cohort']]) ? trim($row[$columnindexes['cohort']]) : '';
                                                    
                                                    if (empty($lastname) || empty($firstname)) {
                                                        $skipped++;
                                                        $errors[] = "Строка " . ($i + 1) . ": отсутствуют обязательные данные (Фамилия или Имя)";
                                                        continue;
                                                    }
                                                    
                                                    // Ищем студента в Moodle
                                                    $user = null;
                                                    
                                                    // ШАГ 1: Сначала ищем по email (без учета регистра)
                                                    if (!empty($email)) {
                                                        $searchemail = mb_strtolower(trim($email), 'UTF-8');
                                                        $user = $DB->get_record_sql(
                                                            "SELECT * FROM {user} 
                                                             WHERE deleted = 0 
                                                             AND LOWER(TRIM(email)) = ?",
                                                            [$searchemail]
                                                        );
                                                    }
                                                    
                                                    // ШАГ 2: Если по email не найден, ищем по ФИО
                                                    if (!$user) {
                                                        // Нормализуем данные для поиска (без учета регистра)
                                                        $searchfirstname = mb_strtolower(trim($firstname), 'UTF-8');
                                                        $searchlastname = mb_strtolower(trim($lastname), 'UTF-8');
                                                        $searchmiddlename = !empty($middlename) ? mb_strtolower(trim($middlename), 'UTF-8') : '';
                                                        
                                                        // Поиск по ФИО с учетом отчества (если отчество указано)
                                                        if (!empty($searchmiddlename)) {
                                                            $sql = "SELECT * FROM {user} 
                                                                    WHERE deleted = 0 
                                                                    AND LOWER(TRIM(firstname)) = ?
                                                                    AND LOWER(TRIM(lastname)) = ?
                                                                    AND LOWER(TRIM(COALESCE(middlename, ''))) = ?";
                                                            $params = [$searchfirstname, $searchlastname, $searchmiddlename];
                                                        } else {
                                                            // Если отчество не указано, ищем только по имени и фамилии
                                                            $sql = "SELECT * FROM {user} 
                                                                    WHERE deleted = 0 
                                                                    AND LOWER(TRIM(firstname)) = ?
                                                                    AND LOWER(TRIM(lastname)) = ?
                                                                    AND (middlename IS NULL OR middlename = '' OR LOWER(TRIM(middlename)) = '')";
                                                            $params = [$searchfirstname, $searchlastname];
                                                        }
                                                        
                                                        $users = $DB->get_records_sql($sql, $params);
                                                        
                                                        if (count($users) == 1) {
                                                            // Одно совпадение - используем его
                                                            $user = reset($users);
                                                        } elseif (count($users) > 1) {
                                                            // Несколько совпадений - используем первое
                                                            $user = reset($users);
                                                        } else {
                                                            // Если не найдено с отчеством, пробуем без отчества (только имя и фамилия)
                                                            if (!empty($searchmiddlename)) {
                                                                $sql = "SELECT * FROM {user} 
                                                                        WHERE deleted = 0 
                                                                        AND LOWER(TRIM(firstname)) = ?
                                                                        AND LOWER(TRIM(lastname)) = ?";
                                                                $params = [$searchfirstname, $searchlastname];
                                                                $users = $DB->get_records_sql($sql, $params);
                                                                
                                                                if (count($users) == 1) {
                                                                    $user = reset($users);
                                                                } elseif (count($users) > 1) {
                                                                    // Несколько совпадений - используем первое
                                                                    $user = reset($users);
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    // ШАГ 3: Если все еще не найден, пробуем поиск по фамилии + группе
                                                    if (!$user && !empty($lastname) && !empty($cohort)) {
                                                        $searchlastname = mb_strtolower(trim($lastname), 'UTF-8');
                                                        $searchcohort = mb_strtolower(trim($cohort), 'UTF-8');
                                                        
                                                        // Ищем пользователей по фамилии и группе через cohort_members
                                                        $sql = "SELECT DISTINCT u.* 
                                                                FROM {user} u
                                                                INNER JOIN {cohort_members} cm ON cm.userid = u.id
                                                                INNER JOIN {cohort} c ON c.id = cm.cohortid
                                                                WHERE u.deleted = 0
                                                                AND LOWER(TRIM(u.lastname)) = ?
                                                                AND LOWER(TRIM(c.name)) = ?";
                                                        $params = [$searchlastname, $searchcohort];
                                                        
                                                        $users = $DB->get_records_sql($sql, $params);
                                                        
                                                        if (count($users) == 1) {
                                                            $user = reset($users);
                                                        } elseif (count($users) > 1) {
                                                            // Если несколько совпадений, пробуем уточнить по имени
                                                            if (!empty($firstname)) {
                                                                $searchfirstname = mb_strtolower(trim($firstname), 'UTF-8');
                                                                foreach ($users as $candidate) {
                                                                    if (mb_strtolower(trim($candidate->firstname), 'UTF-8') == $searchfirstname) {
                                                                        $user = $candidate;
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                            // Если не нашли по имени, используем первое совпадение
                                                            if (!$user) {
                                                                $user = reset($users);
                                                            }
                                                        }
                                                    }
                                                    
                                                    if (!$user) {
                                                        $skipped++;
                                                        // Формируем детальное сообщение о параметрах поиска
                                                        $searchdetails = [];
                                                        $searchdetails[] = "Строка " . ($i + 1);
                                                        $searchdetails[] = "ФИО из Excel: " . trim("$lastname $firstname $middlename");
                                                        $searchdetails[] = "Email из Excel: " . ($email ?: "не указан");
                                                        $searchdetails[] = "Группа из Excel: " . ($cohort ?: "не указана");
                                                        
                                                        // Показываем, какие попытки поиска были сделаны
                                                        $attempts = [];
                                                        if (!empty($email)) {
                                                            $attempts[] = "✓ Поиск по Email (без учета регистра): '$email' - не найдено";
                                                        } else {
                                                            $attempts[] = "✗ Поиск по Email: не выполнен (email не указан)";
                                                        }
                                                        
                                                        if (!empty($firstname) && !empty($lastname)) {
                                                            if (!empty($middlename)) {
                                                                $attempts[] = "✓ Поиск по ФИО (с отчеством, без учета регистра): '$lastname $firstname $middlename' - не найдено";
                                                                $attempts[] = "✓ Поиск по ФИО (без отчества, без учета регистра): '$lastname $firstname' - не найдено";
                                                            } else {
                                                                $attempts[] = "✓ Поиск по ФИО (без учета регистра): '$lastname $firstname' - не найдено";
                                                            }
                                                        }
                                                        
                                                        if (!empty($lastname) && !empty($cohort)) {
                                                            $attempts[] = "✓ Поиск по Фамилии + Группе (без учета регистра): '$lastname' + '$cohort' - не найдено";
                                                        } else {
                                                            if (empty($cohort)) {
                                                                $attempts[] = "✗ Поиск по Фамилии + Группе: не выполнен (группа не указана)";
                                                            }
                                                        }
                                                        
                                                        $errormsg = implode(" | ", $searchdetails) . "\n" . implode(" | ", $attempts);
                                                        $errors[] = $errormsg;
                                                        
                                                        // Сохраняем данные для скачивания
                                                        $notfoundstudents[] = [
                                                            'row' => $i + 1,
                                                            'lastname' => $lastname,
                                                            'firstname' => $firstname,
                                                            'middlename' => $middlename,
                                                            'fio' => trim("$lastname $firstname $middlename"),
                                                            'email' => $email ?: 'не указан',
                                                            'cohort' => $cohort ?: 'не указана',
                                                            'attempts' => implode('; ', $attempts)
                                                        ];
                                                        
                                                        continue;
                                                    }
                                                    
                                                    // Подготавливаем данные для импорта
                                                    $data = new stdClass();
                                                    $data->userid = $user->id;
                                                    
                                                    // Максимальные длины полей из схемы БД
                                                    $fieldlengths = [
                                                        'lastname' => 255,
                                                        'firstname' => 255,
                                                        'middlename' => 255,
                                                        'status' => 100,
                                                        'gender' => 10,
                                                        'snils' => 20,
                                                        'mobile' => 50,
                                                        'email' => 255,
                                                        'citizenship' => 100,
                                                        'birthplace' => 255,
                                                        'id_type' => 50,
                                                        'passport_number' => 50,
                                                        'passport_issued_by' => 255,
                                                        'passport_division_code' => 20,
                                                        'postal_index' => 20,
                                                        'country' => 100,
                                                        'region' => 255,
                                                        'city' => 255,
                                                        'street' => 255,
                                                        'house_apartment' => 100,
                                                        'previous_institution' => 255,
                                                        'cohort' => 255
                                                    ];
                                                    
                                                    // Заполняем все поля из Excel
                                                    foreach ($columnindexes as $field => $index) {
                                                        if (isset($row[$index])) {
                                                            $value = trim($row[$index]);
                                                            
                                                            // Обработка специальных полей
                                                            if ($field == 'enrollment_year' || $field == 'previous_institution_year') {
                                                                // Год - целое число
                                                                $data->$field = !empty($value) ? (int)$value : 0;
                                                                // Проверка диапазона года
                                                                if ($data->$field < 1900 || $data->$field > 2100) {
                                                                    $data->$field = 0;
                                                                }
                                                            } elseif ($field == 'birthdate' || $field == 'passport_issue_date') {
                                                                // Конвертируем дату в timestamp
                                                                if (!empty($value)) {
                                                                    $timestamp = false;
                                                                    
                                                                    // Если значение - число (формат Excel)
                                                                    if (is_numeric($value) && $value > 0) {
                                                                        // Excel дата (число дней с 1900-01-01)
                                                                        if ($value > 1 && $value < 100000) {
                                                                            $timestamp = ($value - 25569) * 86400; // Excel epoch to Unix timestamp
                                                                        }
                                                                    }
                                                                    
                                                                    // Если не получилось, пробуем парсить как строку
                                                                    if ($timestamp === false) {
                                                                        // Пробуем разные форматы даты
                                                                        $timestamp = strtotime($value);
                                                                        
                                                                        // Если не получилось, пробуем форматы типа DD.MM.YYYY
                                                                        if ($timestamp === false && preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $value, $matches)) {
                                                                            $timestamp = mktime(0, 0, 0, $matches[2], $matches[1], $matches[3]);
                                                                        }
                                                                    }
                                                                    
                                                                    $data->$field = $timestamp !== false && $timestamp > 0 ? $timestamp : 0;
                                                                } else {
                                                                    $data->$field = 0;
                                                                }
                                                            } else {
                                                                // Обычные текстовые поля - обрезаем до максимальной длины
                                                                if (isset($fieldlengths[$field])) {
                                                                    $value = mb_substr($value, 0, $fieldlengths[$field], 'UTF-8');
                                                                }
                                                                $data->$field = $value;
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Устанавливаем обязательные поля, если они не были заполнены
                                                    if (empty($data->lastname)) $data->lastname = mb_substr($lastname, 0, 255, 'UTF-8');
                                                    if (empty($data->firstname)) $data->firstname = mb_substr($firstname, 0, 255, 'UTF-8');
                                                    if (empty($data->middlename)) $data->middlename = mb_substr($middlename, 0, 255, 'UTF-8');
                                                    if (empty($data->email)) $data->email = mb_substr($email, 0, 255, 'UTF-8');
                                                    
                                                    $data->timemodified = time();
                                                    
                                                    try {
                                                        // Проверяем, существует ли запись
                                                        $existing = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $user->id]);
                                                        
                                                        if ($existing) {
                                                            // Обновляем существующую запись
                                                            $data->id = $existing->id;
                                                            $DB->update_record('local_deanpromoodle_student_info', $data);
                                                        } else {
                                                            // Создаем новую запись
                                                            $data->timecreated = time();
                                                            $DB->insert_record('local_deanpromoodle_student_info', $data);
                                                        }
                                                        
                                                        $imported++;
                                                    } catch (\dml_exception $dbex) {
                                                        // Ошибка при записи в БД для конкретного студента
                                                        $skipped++;
                                                        $errormsg = "Строка " . ($i + 1) . ": ошибка при сохранении данных для студента $lastname $firstname (ID: {$user->id})";
                                                        if ($dbex->getMessage()) {
                                                            $errormsg .= ": " . $dbex->getMessage();
                                                        }
                                                        $errors[] = $errormsg;
                                                        continue;
                                                    } catch (\Exception $dbex) {
                                                        // Общая ошибка при записи в БД
                                                        $skipped++;
                                                        $errormsg = "Строка " . ($i + 1) . ": ошибка при сохранении данных для студента $lastname $firstname (ID: {$user->id})";
                                                        if ($dbex->getMessage()) {
                                                            $errormsg .= ": " . $dbex->getMessage();
                                                        }
                                                        $errors[] = $errormsg;
                                                        continue;
                                                    }
                                                }
                                                
                                                $transaction->allow_commit();
                                                
                                                // Формируем сообщение об успехе
                                                $message = "Импорт завершен. Успешно импортировано: $imported";
                                                if ($skipped > 0) {
                                                    $message .= ", пропущено: $skipped";
                                                }
                                                
                                                // Показываем детальную информацию о пропущенных записях
                                                if (!empty($errors)) {
                                                    $message .= "<br><br><strong>Список студентов, не найденных в Moodle:</strong><br>";
                                                    
                                                    
                                                    $message .= "<div style='max-height: 500px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-top: 10px; background: #f9f9f9;'>";
                                                    $message .= "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
                                                    $message .= "<thead><tr style='background: #e9ecef; border-bottom: 2px solid #ddd;'>";
                                                    $message .= "<th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>№ строки</th>";
                                                    $message .= "<th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>ФИО из Excel</th>";
                                                    $message .= "<th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Email из Excel</th>";
                                                    $message .= "<th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Группа из Excel</th>";
                                                    $message .= "<th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Попытки поиска</th>";
                                                    $message .= "</tr></thead><tbody>";
                                                    
                                                    // Используем данные из $notfoundstudents, если они есть
                                                    if (isset($notfoundstudents) && !empty($notfoundstudents)) {
                                                        foreach ($notfoundstudents as $student) {
                                                            $message .= "<tr style='border-bottom: 1px solid #ddd;'>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($student['row'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($student['fio'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($student['cohort'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd; font-size: 11px;'>" . htmlspecialchars($student['attempts'], ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "</tr>";
                                                        }
                                                    } else {
                                                        // Fallback на старый формат, если $notfoundstudents не создан
                                                        foreach ($errors as $error) {
                                                            // Парсим сообщение об ошибке
                                                            $parts = explode(" | ", $error);
                                                            $rowNum = "";
                                                            $fio = "";
                                                            $email = "";
                                                            $cohort = "";
                                                            $attempts = "";
                                                            
                                                            foreach ($parts as $part) {
                                                                if (strpos($part, "Строка") !== false) {
                                                                    $rowNum = trim(str_replace("Строка", "", $part));
                                                                } elseif (strpos($part, "ФИО из Excel:") !== false) {
                                                                    $fio = trim(str_replace("ФИО из Excel:", "", $part));
                                                                } elseif (strpos($part, "Email из Excel:") !== false) {
                                                                    $email = trim(str_replace("Email из Excel:", "", $part));
                                                                } elseif (strpos($part, "Группа из Excel:") !== false) {
                                                                    $cohort = trim(str_replace("Группа из Excel:", "", $part));
                                                                } elseif (strpos($part, "Поиск") !== false) {
                                                                    $attempts .= ($attempts ? "<br>" : "") . htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
                                                                }
                                                            }
                                                            
                                                            // Если формат не распознан, показываем как есть
                                                            if (empty($rowNum)) {
                                                                $rowNum = "?";
                                                                $fio = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
                                                                $email = "-";
                                                                $cohort = "-";
                                                                $attempts = "-";
                                                            }
                                                            
                                                            $message .= "<tr style='border-bottom: 1px solid #ddd;'>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($rowNum, ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($cohort, ENT_QUOTES, 'UTF-8') . "</td>";
                                                            $message .= "<td style='padding: 8px; border: 1px solid #ddd; font-size: 11px;'>" . $attempts . "</td>";
                                                            $message .= "</tr>";
                                                        }
                                                    }
                                                    
                                                    $message .= "</tbody></table></div>";
                                                    
                                                    $message .= "<br><em style='color: #666;'>Всего не найдено: " . count($errors) . " студентов.</em>";
                                                    
                                                    // Добавляем подсказку по улучшению поиска
                                                    $message .= "<br><br><small style='color: #666;'><strong>Как исправить:</strong><br>";
                                                    $message .= "1. Проверьте, что Email в Excel точно совпадает с Email в Moodle (регистр не важен, но пробелы важны)<br>";
                                                    $message .= "2. Проверьте, что ФИО в Excel точно совпадает с ФИО в Moodle (регистр не важен, но пробелы и дефисы важны)<br>";
                                                    $message .= "3. Если в Excel указано отчество, а в Moodle нет (или наоборот), поиск может не сработать<br>";
                                                    $message .= "4. Убедитесь, что студенты не удалены в Moodle (deleted = 0)</small>";
                                                }
                                                
                                                if ($imported > 0) {
                                                    echo html_writer::div($message, 'alert alert-success');
                                                } else {
                                                    echo html_writer::div($message, 'alert alert-warning');
                                                }
                                                
                                            } catch (\Exception $e) {
                                                $transaction->rollback($e);
                                                $errormsg = 'Ошибка при импорте: ' . $e->getMessage();
                                                if ($e->getCode()) {
                                                    $errormsg .= ' (Код ошибки: ' . $e->getCode() . ')';
                                                }
                                                // Добавляем информацию о стеке вызовов для отладки
                                                if (debugging()) {
                                                    $errormsg .= '<br><small>Трассировка: ' . $e->getTraceAsString() . '</small>';
                                                }
                                                echo html_writer::div($errormsg, 'alert alert-danger');
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    echo html_writer::div('Ошибка при обработке файла: ' . $e->getMessage(), 'alert alert-danger');
                                }
                            }
                        }
                    }
                }
                
                // Обработка сохранения формы
                if ($action == 'save' && $canedit) {
                    require_sesskey();
                    
                    // Получаем данные из формы
                    $data = new stdClass();
                    $data->userid = $viewingstudent->id;
                    $data->lastname = optional_param('lastname', '', PARAM_TEXT);
                    $data->firstname = optional_param('firstname', '', PARAM_TEXT);
                    $data->middlename = optional_param('middlename', '', PARAM_TEXT);
                    $data->status = optional_param('status', '', PARAM_TEXT);
                    $data->enrollment_year = optional_param('enrollment_year', 0, PARAM_INT);
                    $data->gender = optional_param('gender', '', PARAM_TEXT);
                    $birthdate = optional_param('birthdate', '', PARAM_TEXT);
                    $data->birthdate = !empty($birthdate) ? strtotime($birthdate) : 0;
                    $data->snils = optional_param('snils', '', PARAM_TEXT);
                    $data->mobile = optional_param('mobile', '', PARAM_TEXT);
                    $data->email = optional_param('email', '', PARAM_TEXT);
                    $data->citizenship = optional_param('citizenship', '', PARAM_TEXT);
                    $data->birthplace = optional_param('birthplace', '', PARAM_TEXT);
                    $data->id_type = optional_param('id_type', '', PARAM_TEXT);
                    $data->passport_number = optional_param('passport_number', '', PARAM_TEXT);
                    $data->passport_issued_by = optional_param('passport_issued_by', '', PARAM_TEXT);
                    $passport_issue_date = optional_param('passport_issue_date', '', PARAM_TEXT);
                    $data->passport_issue_date = !empty($passport_issue_date) ? strtotime($passport_issue_date) : 0;
                    $data->passport_division_code = optional_param('passport_division_code', '', PARAM_TEXT);
                    $data->postal_index = optional_param('postal_index', '', PARAM_TEXT);
                    $data->country = optional_param('country', '', PARAM_TEXT);
                    $data->region = optional_param('region', '', PARAM_TEXT);
                    $data->city = optional_param('city', '', PARAM_TEXT);
                    $data->street = optional_param('street', '', PARAM_TEXT);
                    $data->house_apartment = optional_param('house_apartment', '', PARAM_TEXT);
                    $data->previous_institution = optional_param('previous_institution', '', PARAM_TEXT);
                    $data->previous_institution_year = optional_param('previous_institution_year', 0, PARAM_INT);
                    $data->cohort = optional_param('cohort', '', PARAM_TEXT);
                    $data->timemodified = time();
                    
                    // Проверяем, существует ли запись
                    $existing = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $viewingstudent->id]);
                    
                    if ($existing) {
                        // Обновляем существующую запись
                        $data->id = $existing->id;
                        $DB->update_record('local_deanpromoodle_student_info', $data);
                        echo html_writer::div('Данные успешно сохранены', 'alert alert-success');
                    } else {
                        // Создаем новую запись
                        $data->timecreated = time();
                        $DB->insert_record('local_deanpromoodle_student_info', $data);
                        echo html_writer::div('Данные успешно сохранены', 'alert alert-success');
                    }
                }
                
                // Получаем данные из таблицы local_deanpromoodle_student_info
                $studentinfo = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $viewingstudent->id]);
                
                // Определяем режим отображения
                $editmode = ($action == 'edit' && $canedit);
                
                // Группа (cohort) - сначала из таблицы, если нет - из cohort_members
                $cohortdisplay = '';
                if ($studentinfo && !empty($studentinfo->cohort)) {
                    $cohortdisplay = htmlspecialchars($studentinfo->cohort, ENT_QUOTES, 'UTF-8');
                } else {
                    // Fallback: получаем группы (когорты) студента из Moodle
                    $studentcohorts = $DB->get_records_sql(
                        "SELECT c.id, c.name, c.idnumber, c.description
                         FROM {cohort_members} cm
                         JOIN {cohort} c ON c.id = cm.cohortid
                         WHERE cm.userid = ?
                         ORDER BY c.name ASC",
                        [$viewingstudent->id]
                    );
                    if (!empty($studentcohorts)) {
                        $cohortnames = [];
                        foreach ($studentcohorts as $cohort) {
                            $cohortnames[] = htmlspecialchars($cohort->name, ENT_QUOTES, 'UTF-8');
                        }
                        $cohortdisplay = implode(', ', $cohortnames);
                    }
                }
                
                // Дата зачисления - сначала из таблицы (enrollment_year), если нет - из user_enrolments
                $enrollmentdisplay = '-';
                if ($studentinfo && !empty($studentinfo->enrollment_year)) {
                    $enrollmentdisplay = htmlspecialchars($studentinfo->enrollment_year, ENT_QUOTES, 'UTF-8');
                } else {
                    // Fallback: получаем дату зачисления (берем самую раннюю дату зачисления в любой курс)
                    $enrollments = $DB->get_records_sql(
                        "SELECT MIN(ue.timestart) as earliest_enrollment
                         FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE ue.userid = ? AND ue.status = 0 AND e.status = 0",
                        [$viewingstudent->id]
                    );
                    if (!empty($enrollments)) {
                        $enrollment = reset($enrollments);
                        if ($enrollment->earliest_enrollment > 0) {
                            $enrollmentdisplay = userdate($enrollment->earliest_enrollment, get_string('strftimedatefullshort'));
                        }
                    }
                }
                
                // Адрес - собираем из полей таблицы или из профиля пользователя
                $addressdisplay = '-';
                if ($studentinfo) {
                    $addressparts = [];
                    if (!empty($studentinfo->postal_index)) {
                        $addressparts[] = htmlspecialchars($studentinfo->postal_index, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($studentinfo->country)) {
                        $addressparts[] = htmlspecialchars($studentinfo->country, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($studentinfo->region)) {
                        $addressparts[] = htmlspecialchars($studentinfo->region, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($studentinfo->city)) {
                        $addressparts[] = htmlspecialchars($studentinfo->city, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($studentinfo->street)) {
                        $addressparts[] = htmlspecialchars($studentinfo->street, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($studentinfo->house_apartment)) {
                        $addressparts[] = htmlspecialchars($studentinfo->house_apartment, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($addressparts)) {
                        $addressdisplay = implode(', ', $addressparts);
                    }
                }
                // Fallback: получаем адрес из профиля пользователя
                if ($addressdisplay === '-' && !empty($viewingstudent->address)) {
                    $addressdisplay = htmlspecialchars($viewingstudent->address, ENT_QUOTES, 'UTF-8');
                }
                
                // СНИЛС - сначала из таблицы, если нет - из профиля
                $snilsdisplay = '-';
                if ($studentinfo && !empty($studentinfo->snils)) {
                    $snilsdisplay = htmlspecialchars($studentinfo->snils, ENT_QUOTES, 'UTF-8');
                } else {
                    // Fallback: получаем СНИЛС (может быть в idnumber или в customfield)
                    $snils = $viewingstudent->idnumber ? $viewingstudent->idnumber : '';
                    if (empty($snils)) {
                        require_once($CFG->dirroot . '/user/profile/lib.php');
                        $userfields = profile_user_record($viewingstudent->id);
                        if (isset($userfields->snils)) {
                            $snils = $userfields->snils;
                        }
                    }
                    if (!empty($snils)) {
                        $snilsdisplay = htmlspecialchars($snils, ENT_QUOTES, 'UTF-8');
                    }
                }
                
                // Кнопки действий (редактирование и импорт)
                if ($canedit || ($isadmin || $isteacher)) {
                    echo html_writer::start_div('', ['style' => 'margin-bottom: 20px; display: flex; gap: 10px;']);
                    
                    // Кнопка редактирования
                    if ($canedit && !$editmode) {
                        $editurl = new moodle_url('/local/deanpromoodle/pages/student.php', [
                            'tab' => 'programs',
                            'subtab' => 'additional',
                            'action' => 'edit',
                            'studentid' => $viewingstudent->id
                        ]);
                        echo html_writer::link($editurl, 'Редактировать', ['class' => 'btn btn-primary']);
                    }
                    
                    // Кнопка импорта из Excel (только для админов и преподавателей и только если userimportsetting=true)
                    if (($isadmin || $isteacher) && $userimportsetting) {
                        echo html_writer::link('#', 'Импорт из Excel', [
                            'class' => 'btn btn-success',
                            'id' => 'import-excel-btn'
                        ]);
                    }
                    
                    echo html_writer::end_div();
                    
                    // Модальное окно для импорта Excel (только для админов и преподавателей и только если userimportsetting=true)
                    if (($isadmin || $isteacher) && $userimportsetting) {
                        echo html_writer::start_div('modal fade', [
                            'id' => 'importExcelModal',
                            'tabindex' => '-1',
                            'role' => 'dialog',
                            'aria-labelledby' => 'importExcelModalLabel',
                            'aria-hidden' => 'true'
                        ]);
                        echo html_writer::start_div('modal-dialog', ['role' => 'document']);
                        echo html_writer::start_div('modal-content');
                        
                        // Заголовок модального окна
                        echo html_writer::start_div('modal-header');
                        echo html_writer::tag('h5', 'Импорт данных из Excel', ['class' => 'modal-title', 'id' => 'importExcelModalLabel']);
                        echo html_writer::tag('button', '×', [
                            'type' => 'button',
                            'class' => 'close',
                            'data-dismiss' => 'modal',
                            'aria-label' => 'Close',
                            'onclick' => 'jQuery(\'#importExcelModal\').modal(\'hide\');'
                        ]);
                        echo html_writer::end_div();
                        
                        // Тело модального окна
                        echo html_writer::start_div('modal-body');
                        $importurl = new moodle_url('/local/deanpromoodle/pages/student.php', [
                            'tab' => 'programs',
                            'subtab' => 'additional',
                            'action' => 'importexcel',
                            'studentid' => $viewingstudent->id
                        ]);
                        echo html_writer::start_tag('form', [
                            'method' => 'post',
                            'action' => $importurl,
                            'enctype' => 'multipart/form-data'
                        ]);
                        echo html_writer::input_hidden_params($importurl);
                        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'import_submit', 'value' => '1']);
                        
                        echo html_writer::start_div('form-group');
                        echo html_writer::tag('label', 'Выберите Excel файл (.xlsx, .xls) или CSV файл', ['for' => 'excelfile']);
                        echo html_writer::empty_tag('input', [
                            'type' => 'file',
                            'name' => 'excelfile',
                            'id' => 'excelfile',
                            'class' => 'form-control',
                            'accept' => '.xlsx,.xls,.csv',
                            'required' => true
                        ]);
                        echo html_writer::tag('small', 'Файл должен содержать колонки: Фамилия, Имя, Отчество, Email и другие поля из таблицы личной информации.', ['class' => 'form-text text-muted']);
                        echo html_writer::end_div();
                        
                        echo html_writer::start_div('modal-footer');
                        echo html_writer::empty_tag('input', [
                            'type' => 'submit',
                            'value' => 'Импортировать',
                            'class' => 'btn btn-primary'
                        ]);
                        echo html_writer::tag('button', 'Отмена', [
                            'type' => 'button',
                            'class' => 'btn btn-secondary',
                            'data-dismiss' => 'modal',
                            'onclick' => 'jQuery(\'#importExcelModal\').modal(\'hide\');'
                        ]);
                        echo html_writer::end_div();
                        
                        echo html_writer::end_tag('form');
                        echo html_writer::end_div(); // modal-body
                        echo html_writer::end_div(); // modal-content
                        echo html_writer::end_div(); // modal-dialog
                        echo html_writer::end_div(); // modal
                        
                        // JavaScript для открытия модального окна
                        echo html_writer::start_tag('script');
                        echo "
                        document.addEventListener('DOMContentLoaded', function() {
                            var importBtn = document.getElementById('import-excel-btn');
                            if (importBtn) {
                                importBtn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    // Используем jQuery если доступен, иначе Bootstrap 5
                                    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                        jQuery('#importExcelModal').modal('show');
                                    } else if (typeof bootstrap !== 'undefined') {
                                        var modal = new bootstrap.Modal(document.getElementById('importExcelModal'));
                                        modal.show();
                                    } else {
                                        var modal = document.getElementById('importExcelModal');
                                        modal.style.display = 'block';
                                        modal.classList.add('show');
                                    }
                                });
                            }
                        });
                        ";
                        echo html_writer::end_tag('script');
                    }
                }
                
                if ($editmode) {
                    // Форма редактирования - код формы будет добавлен ниже
                    $saveurl = new moodle_url('/local/deanpromoodle/pages/student.php', [
                        'tab' => 'programs',
                        'subtab' => 'additional',
                        'action' => 'save',
                        'studentid' => $viewingstudent->id
                    ]);
                    $cancelurl = new moodle_url('/local/deanpromoodle/pages/student.php', [
                        'tab' => 'programs',
                        'subtab' => 'additional',
                        'studentid' => $viewingstudent->id
                    ]);
                    
                    echo html_writer::start_tag('form', [
                        'method' => 'post',
                        'action' => $saveurl,
                        'class' => 'student-info-form'
                    ]);
                    echo html_writer::input_hidden_params($saveurl);
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                    
                    // Стили для формы
                    echo html_writer::start_tag('style');
                    echo "
                        .student-info-form {
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            padding: 30px;
                        }
                        .student-info-form .form-group {
                            margin-bottom: 20px;
                        }
                        .student-info-form label {
                            display: block;
                            font-weight: 600;
                            margin-bottom: 8px;
                            color: #495057;
                        }
                        .student-info-form input[type='text'],
                        .student-info-form input[type='email'],
                        .student-info-form input[type='tel'],
                        .student-info-form input[type='date'],
                        .student-info-form input[type='number'],
                        .student-info-form select,
                        .student-info-form textarea {
                            width: 100%;
                            padding: 10px 12px;
                            border: 1px solid #ced4da;
                            border-radius: 6px;
                            font-size: 14px;
                            transition: border-color 0.2s;
                        }
                        .student-info-form input:focus,
                        .student-info-form select:focus,
                        .student-info-form textarea:focus {
                            outline: none;
                            border-color: #007bff;
                            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
                        }
                        .student-info-form .form-row {
                            display: grid;
                            grid-template-columns: 1fr 1fr;
                            gap: 20px;
                        }
                        .student-info-form .form-actions {
                            margin-top: 30px;
                            display: flex;
                            gap: 10px;
                        }
                    ";
                    echo html_writer::end_tag('style');
                    
                    // ФИО
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Фамилия');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'lastname',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->lastname, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Имя');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'firstname',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->firstname, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Отчество');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'middlename',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->middlename, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Статус, год поступления, пол
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Статус');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'status',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->status, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Год поступления');
                    echo html_writer::empty_tag('input', [
                        'type' => 'number',
                        'name' => 'enrollment_year',
                        'value' => $studentinfo && $studentinfo->enrollment_year ? $studentinfo->enrollment_year : '',
                        'class' => 'form-control',
                        'min' => '1900',
                        'max' => '2100'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Пол');
                    $genderoptions = [
                        '' => 'Не указан',
                        'М' => 'Мужской',
                        'Ж' => 'Женский'
                    ];
                    $genderselect = html_writer::start_tag('select', ['name' => 'gender', 'class' => 'form-control']);
                    foreach ($genderoptions as $value => $label) {
                        $selected = ($studentinfo && $studentinfo->gender == $value) ? 'selected' : '';
                        $genderselect .= html_writer::tag('option', $label, ['value' => $value, 'selected' => $selected]);
                    }
                    $genderselect .= html_writer::end_tag('select');
                    echo $genderselect;
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Дата рождения, СНИЛС, мобильный, email
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Дата рождения');
                    $birthdatevalue = '';
                    if ($studentinfo && $studentinfo->birthdate > 0) {
                        $birthdatevalue = date('Y-m-d', $studentinfo->birthdate);
                    }
                    echo html_writer::empty_tag('input', [
                        'type' => 'date',
                        'name' => 'birthdate',
                        'value' => $birthdatevalue,
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'СНИЛС');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'snils',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->snils, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Мобильный телефон');
                    echo html_writer::empty_tag('input', [
                        'type' => 'tel',
                        'name' => 'mobile',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->mobile, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Email');
                    echo html_writer::empty_tag('input', [
                        'type' => 'email',
                        'name' => 'email',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->email, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Гражданство, место рождения
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Гражданство');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'citizenship',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->citizenship, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Место рождения');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'birthplace',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->birthplace, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Паспортные данные
                    echo html_writer::start_tag('h3', ['style' => 'margin-top: 30px; margin-bottom: 20px; color: #495057;']);
                    echo 'Паспортные данные';
                    echo html_writer::end_tag('h3');
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Тип удостоверения');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'id_type',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->id_type, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control',
                        'placeholder' => 'Например: Паспорт РФ'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Номер паспорта');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'passport_number',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->passport_number, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Кем выдан паспорт');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'passport_issued_by',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->passport_issued_by, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Дата выдачи паспорта');
                    $passportissuedatevalue = '';
                    if ($studentinfo && $studentinfo->passport_issue_date > 0) {
                        $passportissuedatevalue = date('Y-m-d', $studentinfo->passport_issue_date);
                    }
                    echo html_writer::empty_tag('input', [
                        'type' => 'date',
                        'name' => 'passport_issue_date',
                        'value' => $passportissuedatevalue,
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Код подразделения');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'passport_division_code',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->passport_division_code, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Адрес
                    echo html_writer::start_tag('h3', ['style' => 'margin-top: 30px; margin-bottom: 20px; color: #495057;']);
                    echo 'Адрес';
                    echo html_writer::end_tag('h3');
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Индекс');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'postal_index',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->postal_index, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Страна');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'country',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->country, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Регион/Область');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'region',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->region, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Город');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'city',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->city, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Улица');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'street',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->street, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Дом/Квартира');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'house_apartment',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->house_apartment, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Предыдущее учебное заведение
                    echo html_writer::start_tag('h3', ['style' => 'margin-top: 30px; margin-bottom: 20px; color: #495057;']);
                    echo 'Предыдущее учебное заведение';
                    echo html_writer::end_tag('h3');
                    
                    echo html_writer::start_div('form-row');
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Название учебного заведения');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'previous_institution',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->previous_institution, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Год окончания');
                    echo html_writer::empty_tag('input', [
                        'type' => 'number',
                        'name' => 'previous_institution_year',
                        'value' => $studentinfo && $studentinfo->previous_institution_year ? $studentinfo->previous_institution_year : '',
                        'class' => 'form-control',
                        'min' => '1900',
                        'max' => '2100'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Группа
                    echo html_writer::start_div('form-group');
                    echo html_writer::tag('label', 'Группа');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'name' => 'cohort',
                        'value' => $studentinfo ? htmlspecialchars($studentinfo->cohort, ENT_QUOTES, 'UTF-8') : '',
                        'class' => 'form-control'
                    ]);
                    echo html_writer::end_div();
                    
                    // Кнопки действий
                    echo html_writer::start_div('form-actions');
                    echo html_writer::empty_tag('input', [
                        'type' => 'submit',
                        'value' => 'Сохранить',
                        'class' => 'btn btn-primary'
                    ]);
                    echo html_writer::link($cancelurl, 'Отмена', ['class' => 'btn btn-secondary']);
                    echo html_writer::end_div();
                    
                    echo html_writer::end_tag('form');
                } else {
                    // Режим просмотра
                    // Стили для таблицы дополнительных данных
                    echo html_writer::start_tag('style');
                    echo "
                        .additional-data-table {
                            background: white;
                            border-radius: 8px;
                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                            overflow: hidden;
                        }
                        .additional-data-table table {
                            margin: 0;
                            width: 100%;
                        }
                        .additional-data-table tbody tr {
                            border-bottom: 1px solid #f0f0f0;
                        }
                        .additional-data-table tbody tr:last-child {
                            border-bottom: none;
                        }
                        .additional-data-table tbody td {
                            padding: 16px;
                            vertical-align: middle;
                        }
                        .additional-data-table tbody td:first-child {
                            font-weight: 600;
                            width: 250px;
                            color: #495057;
                        }
                        .additional-data-table tbody td:last-child {
                            color: #212529;
                        }
                    ";
                    echo html_writer::end_tag('style');
                    
                    echo html_writer::start_div('additional-data-table');
                    echo html_writer::start_tag('table', ['class' => 'table']);
                    echo html_writer::start_tag('tbody');
                    
                    // ФИО
                    if ($studentinfo) {
                        $fullname = trim(($studentinfo->lastname ?? '') . ' ' . ($studentinfo->firstname ?? '') . ' ' . ($studentinfo->middlename ?? ''));
                        if (!empty($fullname)) {
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', 'ФИО');
                            echo html_writer::tag('td', htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'));
                            echo html_writer::end_tag('tr');
                        }
                    }
                    
                    // Статус
                    if ($studentinfo && !empty($studentinfo->status)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Статус');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->status, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Год поступления
                    if ($studentinfo && !empty($studentinfo->enrollment_year)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Год поступления');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->enrollment_year, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Пол
                    if ($studentinfo && !empty($studentinfo->gender)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Пол');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->gender, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Дата рождения
                    if ($studentinfo && $studentinfo->birthdate > 0) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Дата рождения');
                        echo html_writer::tag('td', userdate($studentinfo->birthdate, get_string('strftimedatefullshort')));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // СНИЛС
                    if ($studentinfo && !empty($studentinfo->snils)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'СНИЛС');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->snils, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Мобильный
                    if ($studentinfo && !empty($studentinfo->mobile)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Мобильный телефон');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->mobile, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Email
                    if ($studentinfo && !empty($studentinfo->email)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Email');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->email, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Гражданство
                    if ($studentinfo && !empty($studentinfo->citizenship)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Гражданство');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->citizenship, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Место рождения
                    if ($studentinfo && !empty($studentinfo->birthplace)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Место рождения');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->birthplace, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Паспортные данные
                    if ($studentinfo && (!empty($studentinfo->id_type) || !empty($studentinfo->passport_number))) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Тип удостоверения');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->id_type ?? '', ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                        
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Номер паспорта');
                        echo html_writer::tag('td', htmlspecialchars($studentinfo->passport_number ?? '', ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_tag('tr');
                        
                        if (!empty($studentinfo->passport_issued_by)) {
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', 'Кем выдан паспорт');
                            echo html_writer::tag('td', htmlspecialchars($studentinfo->passport_issued_by, ENT_QUOTES, 'UTF-8'));
                            echo html_writer::end_tag('tr');
                        }
                        
                        if ($studentinfo->passport_issue_date > 0) {
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', 'Дата выдачи паспорта');
                            echo html_writer::tag('td', userdate($studentinfo->passport_issue_date, get_string('strftimedatefullshort')));
                            echo html_writer::end_tag('tr');
                        }
                        
                        if (!empty($studentinfo->passport_division_code)) {
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', 'Код подразделения');
                            echo html_writer::tag('td', htmlspecialchars($studentinfo->passport_division_code, ENT_QUOTES, 'UTF-8'));
                            echo html_writer::end_tag('tr');
                        }
                    }
                    
                    // Адрес
                    if ($studentinfo) {
                        $addressparts = [];
                        if (!empty($studentinfo->postal_index)) $addressparts[] = htmlspecialchars($studentinfo->postal_index, ENT_QUOTES, 'UTF-8');
                        if (!empty($studentinfo->country)) $addressparts[] = htmlspecialchars($studentinfo->country, ENT_QUOTES, 'UTF-8');
                        if (!empty($studentinfo->region)) $addressparts[] = htmlspecialchars($studentinfo->region, ENT_QUOTES, 'UTF-8');
                        if (!empty($studentinfo->city)) $addressparts[] = htmlspecialchars($studentinfo->city, ENT_QUOTES, 'UTF-8');
                        if (!empty($studentinfo->street)) $addressparts[] = htmlspecialchars($studentinfo->street, ENT_QUOTES, 'UTF-8');
                        if (!empty($studentinfo->house_apartment)) $addressparts[] = htmlspecialchars($studentinfo->house_apartment, ENT_QUOTES, 'UTF-8');
                        
                        if (!empty($addressparts)) {
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', 'Адрес');
                            echo html_writer::tag('td', implode(', ', $addressparts));
                            echo html_writer::end_tag('tr');
                        }
                    }
                    
                    // Предыдущее учебное заведение
                    if ($studentinfo && !empty($studentinfo->previous_institution)) {
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', 'Предыдущее учебное заведение');
                        $previnst = htmlspecialchars($studentinfo->previous_institution, ENT_QUOTES, 'UTF-8');
                        if ($studentinfo->previous_institution_year > 0) {
                            $previnst .= ' (' . $studentinfo->previous_institution_year . ')';
                        }
                        echo html_writer::tag('td', $previnst);
                        echo html_writer::end_tag('tr');
                    }
                    
                    // Группа
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('td', 'Группа');
                    echo html_writer::tag('td', $cohortdisplay ?: '-');
                    echo html_writer::end_tag('tr');
                    
                    echo html_writer::end_tag('tbody');
                    echo html_writer::end_tag('table');
                    echo html_writer::end_div();
                }
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
            }
            break;
            
        default:
            // По умолчанию показываем программы
            $redirectparams = ['tab' => 'programs', 'subtab' => 'programs'];
            if ($studentid > 0) {
                $redirectparams['studentid'] = $studentid;
            }
            redirect(new moodle_url('/local/deanpromoodle/pages/student.php', $redirectparams));
            break;
        }
        
        echo html_writer::end_div();
        break;
    
    case 'notes':
        // Вкладка "Заметки" - только для администратора и преподавателя
        if (!$isadmin && !$isteacher) {
            // Если пользователь не админ и не преподаватель, скрываем вкладку
            redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses', 'studentid' => $studentid]));
        }
        
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        
        // Обработка действий с заметками (только для администратора)
        if ($isadmin) {
            // Добавление новой заметки
            if ($action == 'addnote' || $action == 'editnote') {
                $notetext = optional_param('notetext', '', PARAM_TEXT);
                if ($notetext) {
                    try {
                        if ($action == 'addnote') {
                            // Добавляем новую заметку
                            $note = new stdClass();
                            $note->studentid = $viewingstudent->id;
                            $note->note = $notetext;
                            $note->createdby = $USER->id;
                            $note->timecreated = time();
                            $note->timemodified = time();
                            $DB->insert_record('local_deanpromoodle_student_notes', $note);
                            $message = 'Заметка успешно добавлена';
                        } else if ($action == 'editnote' && $noteid > 0) {
                            // Редактируем существующую заметку
                            $note = $DB->get_record('local_deanpromoodle_student_notes', ['id' => $noteid, 'studentid' => $viewingstudent->id]);
                            if ($note) {
                                $note->note = $notetext;
                                $note->timemodified = time();
                                $DB->update_record('local_deanpromoodle_student_notes', $note);
                                $message = 'Заметка успешно обновлена';
                            } else {
                                $message = 'Заметка не найдена';
                            }
                        }
                        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid]), $message, null, \core\output\notification::NOTIFY_SUCCESS);
                    } catch (\Exception $e) {
                        echo html_writer::div('Ошибка при сохранении заметки: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
                    }
                }
            }
            
            // Удаление заметки
            if ($action == 'deletenote' && $noteid > 0) {
                try {
                    $note = $DB->get_record('local_deanpromoodle_student_notes', ['id' => $noteid, 'studentid' => $viewingstudent->id]);
                    if ($note) {
                        $DB->delete_records('local_deanpromoodle_student_notes', ['id' => $noteid]);
                        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid]), 'Заметка успешно удалена', null, \core\output\notification::NOTIFY_SUCCESS);
                    } else {
                        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid]), 'Заметка не найдена', null, \core\output\notification::NOTIFY_WARNING);
                    }
                } catch (\Exception $e) {
                    echo html_writer::div('Ошибка при удалении заметки: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
                }
            }
        }
        
        // Получаем все заметки по студенту
        $notes = $DB->get_records_sql(
            "SELECT n.*, u.firstname, u.lastname, u.email
             FROM {local_deanpromoodle_student_notes} n
             JOIN {user} u ON u.id = n.createdby
             WHERE n.studentid = ?
             ORDER BY n.timecreated DESC",
            [$viewingstudent->id]
        );
        
        echo html_writer::tag('h2', 'Заметки по студенту: ' . fullname($viewingstudent), ['style' => 'margin-bottom: 20px;']);
        
        // Форма добавления/редактирования заметки (только для администратора)
        if ($isadmin) {
            $editnote = null;
            if ($action == 'editnote' && $noteid > 0) {
                $editnote = $DB->get_record('local_deanpromoodle_student_notes', ['id' => $noteid, 'studentid' => $viewingstudent->id]);
            }
            
            echo html_writer::start_div('card', ['style' => 'margin-bottom: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);']);
            echo html_writer::tag('h3', $editnote ? 'Редактировать заметку' : 'Добавить заметку', ['style' => 'margin-top: 0; margin-bottom: 15px;']);
            
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid, 'action' => $editnote ? 'editnote' : 'addnote', 'noteid' => $editnote ? $noteid : 0])
            ]);
            
            echo html_writer::start_div('form-group');
            echo html_writer::label('Текст заметки:', 'notetext');
            echo html_writer::start_tag('textarea', [
                'name' => 'notetext',
                'id' => 'notetext',
                'class' => 'form-control',
                'rows' => '5',
                'required' => true,
                'style' => 'width: 100%;'
            ]);
            echo htmlspecialchars($editnote ? $editnote->note : '', ENT_QUOTES, 'UTF-8');
            echo html_writer::end_tag('textarea');
            echo html_writer::end_div();
            
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => $editnote ? 'Сохранить изменения' : 'Добавить заметку',
                'class' => 'btn btn-primary',
                'style' => 'margin-right: 10px;'
            ]);
            if ($editnote) {
                echo html_writer::link(
                    new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid]),
                    'Отмена',
                    ['class' => 'btn btn-secondary']
                );
            }
            echo html_writer::end_div();
            
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
        }
        
        // Список заметок
        if (empty($notes)) {
            echo html_writer::div('Заметок пока нет.', 'alert alert-info');
        } else {
            echo html_writer::start_div('notes-list', ['style' => 'background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px;']);
            echo html_writer::tag('h3', 'Список заметок', ['style' => 'margin-top: 0; margin-bottom: 15px;']);
            
            foreach ($notes as $note) {
                echo html_writer::start_div('card', ['style' => 'margin-bottom: 15px; padding: 15px; border: 1px solid #dee2e6; border-radius: 6px;']);
                
                // Текст заметки
                echo html_writer::start_div('note-text', ['style' => 'margin-bottom: 10px;']);
                echo htmlspecialchars($note->note, ENT_QUOTES, 'UTF-8');
                echo html_writer::end_div();
                
                // Информация о создателе и дате
                echo html_writer::start_div('note-meta', ['style' => 'font-size: 12px; color: #6c757d; border-top: 1px solid #f0f0f0; padding-top: 10px;']);
                echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center;']);
                echo html_writer::start_div('');
                echo 'Добавлено: ' . fullname((object)['firstname' => $note->firstname, 'lastname' => $note->lastname]) . ' ';
                echo ' (' . userdate($note->timecreated, '%d.%m.%Y %H:%M') . ')';
                if ($note->timemodified > $note->timecreated) {
                    echo ' | Изменено: ' . userdate($note->timemodified, '%d.%m.%Y %H:%M');
                }
                echo html_writer::end_div();
                
                // Кнопки действий (только для администратора)
                if ($isadmin) {
                    echo html_writer::start_div('', ['style' => 'display: flex; gap: 10px;']);
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid, 'action' => 'editnote', 'noteid' => $note->id]),
                        '<i class="fas fa-edit"></i> Редактировать',
                        ['class' => 'btn btn-sm btn-primary', 'style' => 'text-decoration: none;']
                    );
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'notes', 'studentid' => $studentid, 'action' => 'deletenote', 'noteid' => $note->id]),
                        '<i class="fas fa-trash"></i> Удалить',
                        [
                            'class' => 'btn btn-sm btn-danger',
                            'style' => 'text-decoration: none;',
                            'onclick' => 'return confirm(\'Вы уверены, что хотите удалить эту заметку?\');'
                        ]
                    );
                    echo html_writer::end_div();
                }
                
                echo html_writer::end_div();
                echo html_writer::end_div();
                echo html_writer::end_div();
            }
            
            echo html_writer::end_div();
        }
        
        echo html_writer::end_div();
        break;
    
    default:
        // По умолчанию показываем курсы
        $redirectparams = ['tab' => 'courses'];
        if ($studentid > 0) {
            $redirectparams['studentid'] = $studentid;
        }
        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', $redirectparams));
        break;
    }
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

// JavaScript для полноэкранного режима таблицы
echo html_writer::start_tag('script');
echo "
function toggleFullscreen() {
    var container = document.getElementById('courses-table-container');
    var table = document.getElementById('courses-table');
    var btn = document.getElementById('fullscreen-toggle-btn');
    
    if (container.classList.contains('fullscreen-mode')) {
        // Выход из полноэкранного режима
        container.classList.remove('fullscreen-mode');
        table.classList.remove('courses-table-fullscreen');
        btn.innerHTML = '<i class=\"fas fa-expand\"></i> Развернуть на весь экран';
    } else {
        // Вход в полноэкранный режим
        container.classList.add('fullscreen-mode');
        table.classList.add('courses-table-fullscreen');
        btn.innerHTML = '<i class=\"fas fa-compress\"></i> Вернуть как было';
    }
}
";
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
