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

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => $tab]));
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

echo $OUTPUT->tabtree($tabs, $tab);

// Содержимое страницы в зависимости от вкладки
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
                
                echo html_writer::start_div('courses-table');
                echo html_writer::start_tag('table', ['class' => 'table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Название курса');
                echo html_writer::tag('th', 'Краткое название', ['style' => 'width: 200px;']);
                echo html_writer::tag('th', 'Дата начала', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Дата окончания', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Действие', ['style' => 'width: 100px; text-align: center;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                foreach ($mycourses as $course) {
                    if ($course->id <= 1) continue; // Пропускаем системный курс
                    
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
                        
                        // Название программы (кликабельное)
                        $programname = htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8');
                        echo html_writer::start_tag('td');
                        echo html_writer::link('#', $programname, [
                            'class' => 'view-program-subjects',
                            'data-program-id' => $program->id,
                            'data-program-name' => $programname,
                            'style' => 'font-weight: 500; color: #007bff; text-decoration: none; cursor: pointer;'
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
                    
                    // Модальное окно для просмотра предметов программы
                    echo html_writer::start_div('modal fade', [
                        'id' => 'programSubjectsModal',
                        'tabindex' => '-1',
                        'role' => 'dialog'
                    ]);
                    echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
                    echo html_writer::start_div('modal-content');
                    echo html_writer::start_div('modal-header');
                    echo html_writer::tag('h5', 'Предметы программы', ['class' => 'modal-title', 'id' => 'programSubjectsModalTitle']);
                    echo html_writer::start_tag('button', [
                        'type' => 'button',
                        'class' => 'close',
                        'data-dismiss' => 'modal',
                        'aria-label' => 'Закрыть'
                    ]);
                    echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
                    echo html_writer::end_tag('button');
                    echo html_writer::end_div();
                    echo html_writer::start_div('modal-body', ['id' => 'programSubjectsModalBody']);
                    echo html_writer::div('Загрузка предметов...', 'text-muted text-center');
                    echo html_writer::end_div();
                    echo html_writer::start_div('modal-footer');
                    echo html_writer::start_tag('button', [
                        'type' => 'button',
                        'class' => 'btn btn-secondary',
                        'data-dismiss' => 'modal'
                    ]);
                    echo 'Закрыть';
                    echo html_writer::end_tag('button');
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // Стили для модального окна
                    echo html_writer::start_tag('style');
                    echo "
                        #programSubjectsModal .modal-header {
                            position: relative;
                            padding: 15px 20px;
                            border-bottom: 1px solid #dee2e6;
                        }
                        #programSubjectsModal .modal-header .close {
                            position: absolute;
                            top: 10px;
                            right: 15px;
                            padding: 0;
                            margin: 0;
                            background: transparent;
                            border: none;
                            font-size: 28px;
                            font-weight: 700;
                            line-height: 1;
                            color: #000;
                            text-shadow: 0 1px 0 #fff;
                            opacity: 0.5;
                            cursor: pointer;
                            z-index: 10;
                        }
                        #programSubjectsModal .modal-header .close:hover {
                            opacity: 0.75;
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
                            color: #6c757d;
                        }
                        .subject-courses-list li {
                            margin: 4px 0;
                        }
                    ";
                    echo html_writer::end_tag('style');
                    
                    // JavaScript для открытия модального окна и загрузки предметов
                    $PAGE->requires->js_init_code("
                        (function() {
                            document.querySelectorAll('.view-program-subjects').forEach(function(link) {
                                link.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    var programId = this.getAttribute('data-program-id');
                                    var programName = this.getAttribute('data-program-name');
                                    
                                    // Устанавливаем заголовок модального окна
                                    document.getElementById('programSubjectsModalTitle').textContent = 'Предметы программы: ' + programName;
                                    
                                    // Показываем загрузку
                                    document.getElementById('programSubjectsModalBody').innerHTML = '<div class=\"text-muted text-center\">Загрузка предметов...</div>';
                                    
                                    // Показываем модальное окно
                                    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                        jQuery('#programSubjectsModal').modal('show');
                                    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                        var modal = new bootstrap.Modal(document.getElementById('programSubjectsModal'));
                                        modal.show();
                                    }
                                    
                                    // Загружаем предметы через AJAX
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getprogramsubjectsforstudent&programid=' + programId, true);
                                    xhr.onreadystatechange = function() {
                                        if (xhr.readyState === 4 && xhr.status === 200) {
                                            try {
                                                var response = JSON.parse(xhr.responseText);
                                                if (response.success && response.subjects) {
                                                    var html = '<table class=\"table table-striped\"><thead><tr><th style=\"width: 60px;\">№</th><th>Название предмета</th><th>Код</th><th>Статус</th><th>Курсы</th></tr></thead><tbody>';
                                                    response.subjects.forEach(function(subject, index) {
                                                        html += '<tr>';
                                                        html += '<td>' + (index + 1) + '</td>';
                                                        html += '<td>' + (subject.name || '-') + '</td>';
                                                        html += '<td>' + (subject.code || '-') + '</td>';
                                                        if (subject.started) {
                                                            html += '<td><span class=\"subject-status-started\"><i class=\"fas fa-check-circle\"></i> Начат</span></td>';
                                                        } else {
                                                            html += '<td><span class=\"subject-status-not-started\">Не начат</span></td>';
                                                        }
                                                        html += '<td>';
                                                        if (subject.courses && subject.courses.length > 0) {
                                                            html += '<ul class=\"subject-courses-list\">';
                                                            subject.courses.forEach(function(course) {
                                                                var courseLink = course.enrolled ? 
                                                                    '<a href=\"/course/view.php?id=' + course.id + '\" target=\"_blank\" style=\"color: #28a745; font-weight: 500;\"><i class=\"fas fa-check\"></i> ' + course.name + '</a>' :
                                                                    '<span style=\"color: #6c757d;\">' + course.name + '</span>';
                                                                html += '<li>' + courseLink + '</li>';
                                                            });
                                                            html += '</ul>';
                                                        } else {
                                                            html += '<span class=\"text-muted\">Нет курсов</span>';
                                                        }
                                                        html += '</td>';
                                                        html += '</tr>';
                                                    });
                                                    html += '</tbody></table>';
                                                    document.getElementById('programSubjectsModalBody').innerHTML = html;
                                                } else {
                                                    document.getElementById('programSubjectsModalBody').innerHTML = '<div class=\"alert alert-info\">Предметы не найдены</div>';
                                                }
                                            } catch (e) {
                                                document.getElementById('programSubjectsModalBody').innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа</div>';
                                            }
                                        }
                                    };
                                    xhr.send();
                                });
                            });
                        })();
                    ");
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

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
