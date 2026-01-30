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

// Получение параметров
$tab = optional_param('tab', 'courses', PARAM_ALPHA); // courses, programs
$action = optional_param('action', '', PARAM_ALPHA); // viewprogram
$programid = optional_param('programid', 0, PARAM_INT);

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
    'Мои курсы');
$tabs[] = new tabobject('programs', 
    new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'programs']),
    'Мои программы');

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
        ['class' => 'btn btn-secondary', 'style' => 'text-decoration: none;']
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
        // Вкладка "Мои курсы"
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        echo html_writer::tag('h2', 'Мои курсы', ['style' => 'margin-bottom: 20px;']);
        
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
                
                echo html_writer::start_div('courses-table');
                echo html_writer::start_tag('table', ['class' => 'table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Название курса');
                echo html_writer::tag('th', 'Краткое название', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Дата начала', ['style' => 'width: 120px;']);
                echo html_writer::tag('th', 'Дата окончания', ['style' => 'width: 120px;']);
                echo html_writer::tag('th', 'Преподаватель', ['style' => 'width: 200px;']);
                echo html_writer::tag('th', 'Статус заданий (ПОСЛЕ СЕССИИ)', ['style' => 'width: 300px;']);
                echo html_writer::tag('th', 'Действие', ['style' => 'width: 100px; text-align: center;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                // Подключаем необходимые функции Moodle
                require_once($CFG->libdir . '/modinfolib.php');
                
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
                    
                    // Название курса
                    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                    echo html_writer::tag('td', 
                        html_writer::link($courseurl, htmlspecialchars($course->fullname, ENT_QUOTES, 'UTF-8'), [
                            'class' => 'course-link'
                        ])
                    );
                    
                    // Краткое название
                    echo html_writer::tag('td', htmlspecialchars($course->shortname, ENT_QUOTES, 'UTF-8'));
                    
                    // Дата начала
                    $startdate = $course->startdate > 0 ? userdate($course->startdate, get_string('strftimedatefullshort')) : '-';
                    echo html_writer::tag('td', $startdate);
                    
                    // Дата окончания
                    $enddate = $course->enddate > 0 ? userdate($course->enddate, get_string('strftimedatefullshort')) : '-';
                    echo html_writer::tag('td', $enddate);
                    
                    // Преподаватель
                    $coursecontext = context_course::instance($course->id);
                    $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
                    $teachers = [];
                    if (!empty($teacherroleids)) {
                        $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
                        $teacherusers = $DB->get_records_sql(
                            "SELECT DISTINCT u.id, u.firstname, u.lastname
                             FROM {user} u
                             JOIN {role_assignments} ra ON ra.userid = u.id
                             WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)
                             AND u.deleted = 0
                             ORDER BY u.lastname, u.firstname",
                            array_merge([$coursecontext->id], $teacherroleids)
                        );
                        foreach ($teacherusers as $teacher) {
                            $teachers[] = fullname($teacher);
                        }
                    }
                    $teachernames = !empty($teachers) ? implode(', ', $teachers) : '-';
                    echo html_writer::tag('td', htmlspecialchars($teachernames, ENT_QUOTES, 'UTF-8'));
                    
                    // Статус заданий (ПОСЛЕ СЕССИИ)
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
                        
                        if ($assignmenttype) {
                            // Получаем cmid для ссылки
                            $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                            if (!$cm) {
                                continue; // Пропускаем, если модуль не найден
                            }
                            $assignmenturl = new moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
                            
                            // Проверяем статус задания для студента
                            $submission = $DB->get_record('assign_submission', [
                                'assignment' => $assignment->id,
                                'userid' => $USER->id,
                                'status' => 'submitted'
                            ]);
                            
                            $grade = $DB->get_record('assign_grades', [
                                'assignment' => $assignment->id,
                                'userid' => $USER->id
                            ]);
                            
                            $statusclass = '';
                            $statusicon = '';
                            $gradetext = '';
                            $islink = false;
                            
                            if ($submission) {
                                // Проверяем, есть ли оценка (grade не null и не -1, что означает "не оценено")
                                if ($grade && $grade->grade !== null && $grade->grade >= 0) {
                                    // Сдано и есть оценка (зеленый) - тоже ссылка для просмотра
                                    $maxgrade = $assignment->grade ? $assignment->grade : 100;
                                    $statusclass = 'assignment-status-green';
                                    $statusicon = '<i class="fas fa-check-circle"></i> ';
                                    $gradetext = ' (Оценка: ' . round($grade->grade, 1);
                                    if ($maxgrade > 0) {
                                        $gradetext .= '/' . $maxgrade;
                                    }
                                    $gradetext .= ')';
                                    $islink = true; // Делаем ссылкой для просмотра результата
                                } else {
                                    // Сдано, но нет оценки (желтый) - ссылка для проверки статуса
                                    $statusclass = 'assignment-status-yellow';
                                    $statusicon = '<i class="fas fa-clock"></i> ';
                                    $islink = true;
                                }
                            } else {
                                // Не сдано (красный) - делаем ссылкой
                                $statusclass = 'assignment-status-red';
                                $statusicon = '<i class="fas fa-times-circle"></i> ';
                                $islink = true;
                            }
                            
                            $assignmentdisplayname = '';
                            if ($assignmenttype == 'reading_report') {
                                $assignmentdisplayname = 'Сдача отчета о чтении';
                            } elseif ($assignmenttype == 'written_work') {
                                $assignmentdisplayname = 'Сдача письменной работы';
                            }
                            
                            $badgecontent = $statusicon . htmlspecialchars($assignmentdisplayname, ENT_QUOTES, 'UTF-8') . $gradetext;
                            
                            if ($islink) {
                                $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                    html_writer::link($assignmenturl, $badgecontent, ['target' => '_blank']) . '</span>';
                            } else {
                                $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                    $badgecontent . '</span>';
                            }
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
                            
                            // Проверяем, есть ли попытка у студента (берем последнюю завершенную попытку)
                            $attempt = $DB->get_record_sql(
                                "SELECT * FROM {quiz_attempts} 
                                 WHERE quiz = ? AND userid = ? AND state = 'finished'
                                 ORDER BY timemodified DESC LIMIT 1",
                                [$quiz->id, $USER->id]
                            );
                            
                            // Получаем оценку из quiz_grades
                            $grade = $DB->get_record('quiz_grades', [
                                'quiz' => $quiz->id,
                                'userid' => $USER->id
                            ]);
                            
                            $statusclass = '';
                            $statusicon = '';
                            $gradetext = '';
                            $islink = false;
                            
                            if ($attempt && $grade && $grade->grade !== null && $grade->grade >= 0) {
                                // Сдан и есть оценка (зеленый) - тоже ссылка для просмотра результата
                                $maxgrade = $quiz->grade ? $quiz->grade : 100;
                                $statusclass = 'assignment-status-green';
                                $statusicon = '<i class="fas fa-check-circle"></i> ';
                                $gradetext = ' (Оценка: ' . round($grade->grade, 1) . '/' . $maxgrade . ')';
                                $islink = true;
                            } else {
                                // Не сдан (красный) - делаем ссылкой
                                $statusclass = 'assignment-status-red';
                                $statusicon = '<i class="fas fa-times-circle"></i> ';
                                $islink = true;
                            }
                            
                            $badgecontent = $statusicon . 'Экзамен' . $gradetext;
                            
                            if ($islink) {
                                $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                    html_writer::link($quizurl, $badgecontent, ['target' => '_blank']) . '</span>';
                            } else {
                                $statusitems[] = '<span class="badge assignment-status-item ' . $statusclass . '">' . 
                                    $badgecontent . '</span>';
                            }
                        }
                    }
                    
                    // Показываем только экзамен, если он найден в курсе
                    
                    if (!empty($statusitems)) {
                        $statushtml = implode(' ', $statusitems);
                    } else {
                        $statushtml = '<span class="text-muted">Нет заданий</span>';
                    }
                    
                    echo html_writer::tag('td', $statushtml);
                    
                    // Действие
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
        // Вкладка "Мои программы"
        echo html_writer::start_div('local-deanpromoodle-student-content', ['style' => 'margin-top: 20px;']);
        echo html_writer::tag('h2', 'Мои программы', ['style' => 'margin-bottom: 20px;']);
        
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
                echo html_writer::div('Вы не состоите ни в одной группе (когорте).', 'alert alert-info');
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
                    echo html_writer::div('Ваши группы не прикреплены ни к одной программе.', 'alert alert-info');
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
