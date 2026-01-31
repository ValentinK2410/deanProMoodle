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

// Проверка роли пользователя и редирект при необходимости
global $USER;
$isadmin = false;
$isteacher = false;
$isstudent = false;

// Проверяем, является ли пользователь админом
if (has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context)) {
    $isadmin = true;
    // Админ не может заходить на страницу студента - редирект на admin.php
    redirect(new moodle_url('/local/deanpromoodle/pages/admin.php'));
}

// Проверяем, является ли пользователь преподавателем
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

if ($isteacher && !$isadmin) {
    // Преподаватель не может заходить на страницу студента - редирект на teacher.php
    redirect(new moodle_url('/local/deanpromoodle/pages/teacher.php'));
}

// Проверяем, является ли пользователь студентом
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

// Получение параметров
$tab = optional_param('tab', 'courses', PARAM_ALPHA); // courses, programs
$subtab = optional_param('subtab', 'programs', PARAM_ALPHA); // programs, additional для вкладки "Личная информация и статус"
$action = optional_param('action', '', PARAM_ALPHA); // viewprogram
$programid = optional_param('programid', 0, PARAM_INT);
$testmode = optional_param('test', false, PARAM_BOOL); // Параметр для тестирования - показывать цифровую оценку

// Студент может заходить на любые вкладки страницы student.php

// Настройка страницы
$urlparams = ['tab' => $tab];
if ($action) {
    $urlparams['action'] = $action;
}
if ($programid > 0) {
    $urlparams['programid'] = $programid;
}
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/student.php', $urlparams));
$PAGE->set_context(context_system::instance());
// Получение заголовка с проверкой и fallback на русский
$pagetitle = get_string('studentpagetitle', 'local_deanpromoodle');
if (strpos($pagetitle, '[[') !== false || $pagetitle == 'Student Dashboard') {
    $pagetitle = 'Панель студента'; // Fallback на русский
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('standard');

// Подключение CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Вывод страницы
echo $OUTPUT->header();
// Заголовок уже выводится через set_heading(), не нужно дублировать

global $USER, $DB;

// Вкладки
$tabs = [];
$tabs[] = new tabobject('courses', 
    new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']),
    'Мои оценки');
$tabs[] = new tabobject('programs', 
    new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs']),
    'Личная информация и статус');

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
    echo html_writer::link(
        new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs']),
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
                [$USER->id]
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
                $mycourses = enrol_get_my_courses(['id']);
                $mycourseids = array_keys($mycourses);
                
                // Получаем предметы программы
                $subjects = $DB->get_records_sql(
                    "SELECT s.id, s.name, s.code, s.shortdescription, ps.sortorder
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
                    ";
                    echo html_writer::end_tag('style');
                    
                    echo html_writer::start_div('subjects-table');
                    echo html_writer::start_tag('table', ['class' => 'table']);
                    echo html_writer::start_tag('thead');
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('th', '№', ['style' => 'width: 60px;']);
                    echo html_writer::tag('th', 'Название предмета');
                    echo html_writer::tag('th', 'Код', ['style' => 'width: 150px;']);
                    echo html_writer::tag('th', 'Статус', ['style' => 'width: 120px;']);
                    echo html_writer::tag('th', 'Курсы');
                    echo html_writer::end_tag('tr');
                    echo html_writer::end_tag('thead');
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
                        
                        // Определяем статус предмета: "Начат" если есть хотя бы один зачисленный курс
                        $subjectstarted = !empty($enrolledcourses);
                        
                        // Формируем HTML списка курсов - показываем только зачисленные курсы
                        if (!empty($enrolledcourses)) {
                            $courseshtml = '<ul class="subject-courses-list">';
                            foreach ($enrolledcourses as $course) {
                                $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                                $courseshtml .= '<li>' . html_writer::link($courseurl, 
                                    '<i class="fas fa-check"></i> ' . htmlspecialchars($course->fullname, ENT_QUOTES, 'UTF-8'), 
                                    ['class' => 'course-link-enrolled', 'target' => '_blank']
                                ) . '</li>';
                            }
                            $courseshtml .= '</ul>';
                        } else {
                            // Если нет зачисленных курсов, показываем сообщение
                            $courseshtml = '<span class="text-muted">Нет доступных курсов</span>';
                        }
                        
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('td', $index + 1);
                        echo html_writer::tag('td', htmlspecialchars($subject->name, ENT_QUOTES, 'UTF-8'), [
                            'style' => 'font-weight: 500;'
                        ]);
                        echo html_writer::tag('td', $subject->code ? htmlspecialchars($subject->code, ENT_QUOTES, 'UTF-8') : '-');
                        
                        // Статус предмета
                        if ($subjectstarted) {
                            echo html_writer::tag('td', 
                                '<span class="subject-status-started"><i class="fas fa-check-circle"></i> Начат</span>'
                            );
                        } else {
                            echo html_writer::tag('td', 
                                '<span class="subject-status-not-started">Не начат</span>'
                            );
                        }
                        
                        // Курсы - только те, на которые зачислен студент
                        echo html_writer::tag('td', $courseshtml);
                        
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
        echo html_writer::tag('h2', 'Мои оценки', ['style' => 'margin-bottom: 20px;']);
        
        try {
            // Получаем все курсы, на которые записан студент
            $mycourses = enrol_get_my_courses(['id', 'fullname', 'shortname', 'summary', 'startdate', 'enddate', 'visible']);
            
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
                        overflow: hidden;
                    }
                    .courses-table table {
                        margin: 0;
                        width: 100%;
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
                        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
                ";
                echo html_writer::end_tag('style');
                
                echo html_writer::start_div('courses-table');
                echo html_writer::start_tag('table', ['class' => 'table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Название курса', ['style' => 'width: 250px;']);
                echo html_writer::tag('th', 'Статус завершения', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Итоговая оценка', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Количество академических кредитов', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Преподаватели, к которым можно обратиться за разъяснениями', ['style' => 'width: 200px;']);
                echo html_writer::tag('th', 'Задолженности по курсу', ['style' => 'width: 300px;']);
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
                    
                    // Функция для получения итоговой оценки курса
                    // Используем grade_grade::fetch() для получения итоговой оценки с учетом всех переопределений (overridden)
                    // Свойство finalgrade автоматически учитывает принудительно проставленные оценки
                    $coursegrade = null;
                    $finalgradepercent = null;
                    $courseitem = null;
                    try {
                        $courseitem = grade_item::fetch_course_item($course->id);
                        if ($courseitem) {
                            $usergrade = grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $USER->id]);
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
                            $assignmentname = mb_strtolower($assignment->name);
                            // Проверяем отчеты о чтении
                            if (strpos($assignmentname, 'отчет') !== false && strpos($assignmentname, 'чтени') !== false) {
                                $hasassignments = true;
                                // Используем функцию для проверки оценки (включая принудительно проставленные)
                                if (!$checkAssignmentGrade($assignment->id, $USER->id)) {
                                    $allassignmentsgraded = false;
                                }
                            }
                            // Проверяем письменные работы
                            if (strpos($assignmentname, 'письменн') !== false) {
                                $hasassignments = true;
                                // Проверяем наличие файлов или текста
                                $submission = $DB->get_record('assign_submission', [
                                    'assignment' => $assignment->id,
                                    'userid' => $USER->id
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
                                if (!$checkAssignmentGrade($assignment->id, $USER->id) && !$hasfiles) {
                                    $allassignmentsgraded = false;
                                }
                            }
                        }
                        
                        // Проверяем тесты (экзамены)
                        foreach ($quizzes as $quiz) {
                            $quizname = mb_strtolower($quiz->name);
                            if (strpos($quizname, 'экзамен') !== false) {
                                $hasassignments = true;
                                $grade = $DB->get_record('quiz_grades', [
                                    'quiz' => $quiz->id,
                                    'userid' => $USER->id
                                ]);
                                if (!$grade || $grade->grade === null || $grade->grade < 0) {
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
                        // Если нет заданий в курсе - не завершен
                        if (!$hasassignments) {
                            $completionstatus = 'Не завершен';
                            $completionclass = 'completion-status-not-completed';
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
                        // Если оценка ниже 70% - показываем фактическую оценку из gradebook (только целые числа, без максимума)
                        $gradeText = 'курс не пройден';
                        $gradeClass = 'grade-badge-failed';
                        $gradeIcon = '<i class="fas fa-times-circle"></i>';
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
                    
                    // Преподаватели с email-ссылками
                    $coursecontext = context_course::instance($course->id);
                    $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
                    $teachershtml = '';
                    if (!empty($teacherroleids)) {
                        $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
                        $teacherusers = $DB->get_records_sql(
                            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                             FROM {user} u
                             JOIN {role_assignments} ra ON ra.userid = u.id
                             WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)
                             AND u.deleted = 0
                             ORDER BY u.lastname, u.firstname",
                            array_merge([$coursecontext->id], $teacherroleids)
                        );
                        if (!empty($teacherusers)) {
                            $teacherlinks = [];
                            foreach ($teacherusers as $teacher) {
                                $teachername = fullname($teacher);
                                if (!empty($teacher->email)) {
                                    $teacherlinks[] = html_writer::link(
                                        'mailto:' . htmlspecialchars($teacher->email, ENT_QUOTES, 'UTF-8'),
                                        htmlspecialchars($teachername, ENT_QUOTES, 'UTF-8'),
                                        ['class' => 'teacher-email-link']
                                    );
                                } else {
                                    $teacherlinks[] = htmlspecialchars($teachername, ENT_QUOTES, 'UTF-8');
                                }
                            }
                            $teachershtml = implode('', $teacherlinks);
                        }
                    }
                    if (empty($teachershtml)) {
                        $teachershtml = '-';
                    }
                    echo html_writer::tag('td', $teachershtml);
                    
                    // Задолженности по курсу
                    $statushtml = '';
                    $statusitems = [];
                    
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
                        $assignmentname = mb_strtolower($assignment->name);
                        
                        // Определяем тип задания по названию
                        $assignmenttype = '';
                        if (strpos($assignmentname, 'отчет') !== false && strpos($assignmentname, 'чтени') !== false) {
                            $assignmenttype = 'reading_report';
                        } elseif (strpos($assignmentname, 'письменн') !== false) {
                            $assignmenttype = 'written_work';
                        }
                        
                        if ($assignmenttype == 'reading_report') {
                            // Получаем cmid для ссылки
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm) {
                                continue; // Пропускаем, если модуль не найден
                            }
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            // Проверяем статус задания для студента
                            // Получаем любую submission (не только submitted, но и draft и другие статусы)
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $USER->id
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
                            
                            // Проверяем наличие оценки (включая принудительно проставленные)
                            $hasgrade = $checkAssignmentGrade($assignment->id, $USER->id);
                            
                            $statusclass = '';
                            $statustext = '';
                            
                            // Логика согласно требованиям:
                            // 1. Если есть оценка (включая принудительно проставленную) - зеленый "Чтение – сдано" (даже если файл не загружен)
                            if ($hasgrade) {
                                $statusclass = 'assignment-status-green';
                                $statustext = 'Чтение – сдано';
                            } elseif ($hasfiles) {
                                // 2. Если файл загружен, но нет оценки - желтый "Чтение – не проверено"
                                $statusclass = 'assignment-status-yellow';
                                $statustext = 'Чтение – не проверено';
                            } else {
                                // 3. Если файл не загружен и нет оценки - красный "Чтение – не сдано"
                                $statusclass = 'assignment-status-red';
                                $statustext = 'Чтение – не сдано';
                            }
                            
                            $badgecontent = htmlspecialchars($statustext, ENT_QUOTES, 'UTF-8');
                            $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                html_writer::link($assignmenturl, $badgecontent, ['target' => '_blank']) . '</span>';
                        } elseif ($assignmenttype == 'written_work') {
                            // Письменная работа - показываем только если не сдана
                            // Получаем cmid для ссылки
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm) {
                                continue; // Пропускаем, если модуль не найден
                            }
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            // Проверяем, сдано ли задание
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $USER->id
                            ]);
                            
                            // Проверяем наличие файлов или текста
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
                            
                            // Проверяем наличие оценки (включая принудительно проставленные)
                            $hasgrade = $checkAssignmentGrade($assignment->id, $USER->id);
                            
                            // Показываем только если не сдано (нет оценки и нет файлов)
                            if (!$hasgrade && !$hasfiles) {
                                // Если название точно "Сдача письменной работы" - используем его с текстом "не сдано", иначе оригинальное название
                                if (mb_strtolower(trim($assignment->name)) == 'сдача письменной работы') {
                                    $statustext = 'Сдача письменной работы - не сдано';
                                } else {
                                    $statustext = htmlspecialchars($assignment->name, ENT_QUOTES, 'UTF-8');
                                }
                                $badgecontent = $statustext;
                                $statusitems[] = '<span class="badge assignment-status-item assignment-status-red">' . 
                                    html_writer::link($assignmenturl, $badgecontent, ['target' => '_blank']) . '</span>';
                            }
                        } else {
                            // Остальные задания - отображаем с оригинальным названием
                            // Получаем cmid для ссылки
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm) {
                                continue; // Пропускаем, если модуль не найден
                            }
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            // Проверяем статус задания для студента
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $USER->id
                            ]);
                            
                            // Проверяем наличие файлов или текста
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
                            
                            // Проверяем наличие оценки (включая принудительно проставленные)
                            $hasgrade = $checkAssignmentGrade($assignment->id, $USER->id);
                            
                            $statusclass = '';
                            $statustext = '';
                            
                            // Логика для остальных заданий:
                            // 1. Если есть оценка (включая принудительно проставленную) - зеленый "Название – сдано"
                            if ($hasgrade) {
                                $statusclass = 'assignment-status-green';
                                $statustext = htmlspecialchars($assignment->name, ENT_QUOTES, 'UTF-8') . ' – сдано';
                            } elseif ($hasfiles) {
                                // 2. Если файл загружен, но нет оценки - желтый "Название – не проверено"
                                $statusclass = 'assignment-status-yellow';
                                $statustext = htmlspecialchars($assignment->name, ENT_QUOTES, 'UTF-8') . ' – не проверено';
                            } else {
                                // 3. Если файл не загружен и нет оценки - красный "Название – не сдано"
                                $statusclass = 'assignment-status-red';
                                $statustext = htmlspecialchars($assignment->name, ENT_QUOTES, 'UTF-8') . ' – не сдано';
                            }
                            
                            $badgecontent = $statustext;
                            $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                html_writer::link($assignmenturl, $badgecontent, ['target' => '_blank']) . '</span>';
                        }
                    }
                    
                    // Получаем тесты (экзамены) курса
                    try {
                        $quizzes = get_all_instances_in_course('quiz', $course, false);
                    } catch (\Exception $e) {
                        $quizzes = [];
                    }
                    if (!is_array($quizzes)) {
                        $quizzes = [];
                    }
                    
                    foreach ($quizzes as $quiz) {
                        $quizname = mb_strtolower($quiz->name);
                        
                        // Проверяем, является ли это экзаменом
                        if (strpos($quizname, 'экзамен') !== false) {
                            // Получаем cmid для ссылки
                            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
                            if (!$cm) {
                                continue; // Пропускаем, если модуль не найден
                            }
                            $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
                            
                            // Получаем оценку из quiz_grades
                            $grade = $DB->get_record('quiz_grades', [
                                'quiz' => $quiz->id,
                                'userid' => $USER->id
                            ]);
                            
                            $statusclass = '';
                            $statustext = '';
                            
                            // Логика для экзамена: либо сдан, либо не сдан
                            if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                                // Есть оценка - зеленый "Экзамен – сдан"
                                $statusclass = 'assignment-status-green';
                                $statustext = 'Экзамен – сдан';
                            } else {
                                // Нет оценки - красный "Экзамен – не сдан"
                                $statusclass = 'assignment-status-red';
                                $statustext = 'Экзамен – не сдан';
                            }
                            
                            $badgecontent = htmlspecialchars($statustext, ENT_QUOTES, 'UTF-8');
                            $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                html_writer::link($quizurl, $badgecontent, ['target' => '_blank']) . '</span>';
                        }
                    }
                    
                    if (!empty($statusitems)) {
                        $statushtml = implode(' ', $statusitems);
                    } else {
                        $statushtml = '<span class="text-muted">Нет заданий</span>';
                    }
                    
                    echo html_writer::tag('td', $statushtml);
                    
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
                echo html_writer::end_div();
            }
        } catch (\Exception $e) {
            echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
        }
        
        echo html_writer::end_div();
        break;
    
    case 'programs':
        // Вкладка "Личная информация и статус"
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        
        // Заголовок с ФИО и фото
        echo html_writer::start_tag('style');
        echo "
            .student-profile-header {
                display: flex;
                align-items: center;
                margin-bottom: 30px;
                padding: 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                color: white;
            }
            .student-profile-photo {
                margin-right: 25px;
                border-radius: 50%;
                border: 4px solid rgba(255,255,255,0.3);
                overflow: hidden;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
            .student-profile-info {
                flex: 1;
            }
            .student-profile-name {
                margin: 0;
                font-size: 2.2em;
                font-weight: 600;
                color: white;
                text-shadow: 0 2px 4px rgba(0,0,0,0.2);
                margin-bottom: 8px;
            }
            .student-profile-name a {
                color: white;
                text-decoration: none;
                transition: opacity 0.3s;
            }
            .student-profile-name a:hover {
                opacity: 0.9;
                text-decoration: underline;
            }
            .student-profile-email {
                display: flex;
                align-items: center;
                margin-top: 8px;
                font-size: 1.1em;
                opacity: 0.95;
            }
            .student-profile-email a {
                color: white;
                text-decoration: none;
                display: flex;
                align-items: center;
                transition: opacity 0.3s;
            }
            .student-profile-email a:hover {
                opacity: 0.8;
                text-decoration: underline;
            }
            .student-profile-email a:before {
                content: '✉';
                margin-right: 8px;
                font-size: 1.1em;
            }
        ";
        echo html_writer::end_tag('style');
        
        echo html_writer::start_div('student-profile-header');
        // Фото студента
        $userpicture = $OUTPUT->user_picture($USER, ['size' => 120, 'class' => 'userpicture']);
        echo html_writer::div($userpicture, 'student-profile-photo');
        // ФИО и email студента
        echo html_writer::start_div('student-profile-info');
        // ФИО студента как ссылка на профиль
        $profileurl = new moodle_url('/user/profile.php', ['id' => $USER->id]);
        echo html_writer::tag('h1', 
            html_writer::link($profileurl, fullname($USER), [
                'class' => 'student-profile-name-link',
                'target' => '_blank'
            ]), 
            ['class' => 'student-profile-name']
        );
        // Email студента
        if (!empty($USER->email)) {
            echo html_writer::tag('div', 
                html_writer::link('mailto:' . htmlspecialchars($USER->email, ENT_QUOTES, 'UTF-8'), 
                    htmlspecialchars($USER->email, ENT_QUOTES, 'UTF-8')
                ), 
                ['class' => 'student-profile-email']
            );
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Подвкладки
        $subtabs = [];
        $subtabs[] = new tabobject('programs', 
            new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs', 'subtab' => 'programs']),
            'Программы');
        $subtabs[] = new tabobject('additional', 
            new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs', 'subtab' => 'additional']),
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
                [$USER->id]
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
                        $programurl = new moodle_url('/local/deanpromoodle/pages/student.php', [
                            'action' => 'viewprogram',
                            'programid' => $program->id
                        ]);
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
                // Получаем данные из таблицы local_deanpromoodle_student_info
                $studentinfo = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $USER->id]);
                
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
                        [$USER->id]
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
                        [$USER->id]
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
                if ($addressdisplay === '-' && !empty($USER->address)) {
                    $addressdisplay = htmlspecialchars($USER->address, ENT_QUOTES, 'UTF-8');
                }
                
                // СНИЛС - сначала из таблицы, если нет - из профиля
                $snilsdisplay = '-';
                if ($studentinfo && !empty($studentinfo->snils)) {
                    $snilsdisplay = htmlspecialchars($studentinfo->snils, ENT_QUOTES, 'UTF-8');
                } else {
                    // Fallback: получаем СНИЛС (может быть в idnumber или в customfield)
                    $snils = $USER->idnumber ? $USER->idnumber : '';
                    if (empty($snils)) {
                        require_once($CFG->dirroot . '/user/profile/lib.php');
                        $userfields = profile_user_record($USER->id);
                        if (isset($userfields->snils)) {
                            $snils = $userfields->snils;
                        }
                    }
                    if (!empty($snils)) {
                        $snilsdisplay = htmlspecialchars($snils, ENT_QUOTES, 'UTF-8');
                    }
                }
                
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
                
                // Группа
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', 'Группа');
                echo html_writer::tag('td', $cohortdisplay ?: '-');
                echo html_writer::end_tag('tr');
                
                // Дата зачисления
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', 'Дата зачисления');
                echo html_writer::tag('td', $enrollmentdisplay);
                echo html_writer::end_tag('tr');
                
                // Адрес
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', 'Адрес');
                echo html_writer::tag('td', $addressdisplay);
                echo html_writer::end_tag('tr');
                
                // СНИЛС
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', 'СНИЛС');
                echo html_writer::tag('td', $snilsdisplay);
                echo html_writer::end_tag('tr');
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                echo html_writer::end_div();
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
            }
            break;
            
        default:
            // По умолчанию показываем программы
            redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs', 'subtab' => 'programs']));
            break;
        }
        
        echo html_writer::end_div();
        break;
    
    default:
        // По умолчанию показываем курсы
        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']));
        break;
    }
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
