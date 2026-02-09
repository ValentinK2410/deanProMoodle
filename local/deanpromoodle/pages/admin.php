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
 * Admin page for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define path to Moodle config
// From pages/admin.php: ../ (to deanpromoodle) -> ../ (to local) -> ../ (to moodle root) = ../../../config.php
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    die('Error: Moodle config.php not found at: ' . $configpath);
}

require_once($configpath);
require_once($CFG->libdir . '/tablelib.php');

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
if (has_capability('local/deanpromoodle:viewadmin', $context)) {
    $hasaccess = true;
} else {
    // Fallback: check if user is site administrator
    if (has_capability('moodle/site:config', $context)) {
        $hasaccess = true;
    } else {
        // Check manager role
        global $USER;
        $roles = get_user_roles($context, $USER->id, false);
        foreach ($roles as $role) {
            if ($role->shortname == 'manager') {
                $hasaccess = true;
                break;
            }
        }
    }
}

if (!$hasaccess) {
    require_capability('local/deanpromoodle:viewadmin', $context);
}


// Проверка роли пользователя и редирект при необходимости
global $USER;
$isadmin = false;
$isteacher = false;
$isstudent = false;

// Проверяем, является ли пользователь админом
if (has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context)) {
    $isadmin = true;
}

// Если пользователь не админ, редиректим на соответствующую страницу
if (!$isadmin) {
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
    
    // Редирект в зависимости от роли
    if ($isteacher && !$isadmin) {
        redirect(new moodle_url('/local/deanpromoodle/pages/teacher.php'));
    } elseif ($isstudent && !$isteacher && !$isadmin) {
        redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']));
    } else {
        // Если не определено, редирект на главную страницу Moodle
        redirect(new moodle_url('/'));
    }
}

// Получение параметров
$tab = optional_param('tab', 'history', PARAM_ALPHA); // history, teachers, students, subjects, programs, categories
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$period = optional_param('period', 'month', PARAM_ALPHA); // day, week, month, year
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);
$studentperiod = optional_param('studentperiod', 'month', PARAM_ALPHA);
$studentdatefrom = optional_param('studentdatefrom', '', PARAM_TEXT);
$studentdateto = optional_param('studentdateto', '', PARAM_TEXT);
// Параметры для предметов
$action = optional_param('action', '', PARAM_ALPHA); // create, view, edit, delete
$subjectid = optional_param('subjectid', 0, PARAM_INT);
$programid = optional_param('programid', 0, PARAM_INT);

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/admin.php', [
    'tab' => $tab,
    'teacherid' => $teacherid,
    'period' => $period,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
    'studentperiod' => $studentperiod,
    'studentdatefrom' => $studentdatefrom,
    'studentdateto' => $studentdateto
]));
$PAGE->set_context(context_system::instance());
// Получение заголовка с проверкой и fallback на русский
$pagetitle = get_string('adminpagetitle', 'local_deanpromoodle');
if (strpos($pagetitle, '[[') !== false || $pagetitle == 'Admin Dashboard') {
    $pagetitle = 'Панель администратора'; // Fallback на русский
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->set_pagelayout('admin');

// Подключение CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Обработка экспорта предметов программы в Excel (до вывода заголовков)
if ($tab == 'programs' && $action == 'export_subjects' && $programid > 0) {
    // Отключаем буферизацию вывода для корректной отправки файла
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
        if (!$program) {
            // Если программа не найдена, выводим ошибку после заголовков
            echo $OUTPUT->header();
            echo html_writer::div('Программа не найдена.', 'alert alert-danger');
            echo $OUTPUT->footer();
            exit;
        }
        
        // Получаем все предметы программы
        $subjects = $DB->get_records_sql(
            "SELECT s.id, s.name, s.code, s.shortdescription, s.description, s.credits, s.academic_hours, s.independent_hours, ps.sortorder
             FROM {local_deanpromoodle_program_subjects} ps
             JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
             WHERE ps.programid = ?
             ORDER BY ps.sortorder ASC",
            [$programid]
        );
        
        // Проверяем доступность PhpSpreadsheet
        $usePhpSpreadsheet = false;
        $phpspreadsheetpath = $CFG->dirroot . '/vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/IOFactory.php';
        if (file_exists($phpspreadsheetpath)) {
            require_once($phpspreadsheetpath);
            $usePhpSpreadsheet = true;
        }
        
        if (!$usePhpSpreadsheet) {
            // Если PhpSpreadsheet недоступна, используем CSV
            $filename = 'program_subjects_' . $programid . '_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            // Добавляем BOM для правильного отображения кириллицы в Excel
            echo "\xEF\xBB\xBF";
            
            $output = fopen('php://output', 'w');
            
            // Заголовки
            fputcsv($output, [
                'Порядок',
                'Название',
                'Код',
                'Краткое описание',
                'Описание',
                'Кредиты',
                'Академические часы',
                'Часы самостоятельной работы'
            ], ';');
            
            // Данные
            foreach ($subjects as $subject) {
                fputcsv($output, [
                    $subject->sortorder,
                    $subject->name ?? '',
                    $subject->code ?? '',
                    $subject->shortdescription ?? '',
                    strip_tags($subject->description ?? ''),
                    $subject->credits ?? '',
                    $subject->academic_hours ?? '',
                    $subject->independent_hours ?? ''
                ], ';');
            }
            
            fclose($output);
            exit;
        } else {
            // Используем PhpSpreadsheet для создания Excel файла
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Устанавливаем название листа
            $sheet->setTitle('Предметы программы');
            
            // Заголовки
            $headers = [
                'Порядок',
                'Название',
                'Код',
                'Краткое описание',
                'Описание',
                'Кредиты',
                'Академические часы',
                'Часы самостоятельной работы'
            ];
            
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $sheet->getStyleByColumnAndRow($col, 1)->getFont()->setBold(true);
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                $col++;
            }
            
            // Данные
            $row = 2;
            foreach ($subjects as $subject) {
                $col = 1;
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->sortorder);
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->name ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->code ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->shortdescription ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, strip_tags($subject->description ?? ''));
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->credits ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->academic_hours ?? '');
                $sheet->setCellValueByColumnAndRow($col++, $row, $subject->independent_hours ?? '');
                $row++;
            }
            
            // Автоподбор ширины колонок
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
            
            // Сохранение файла
            $filename = 'program_subjects_' . $programid . '_' . date('Y-m-d') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
    } catch (\Exception $e) {
        // Если произошла ошибка, выводим её после заголовков
        echo $OUTPUT->header();
        echo html_writer::div('Ошибка при экспорте: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
        echo $OUTPUT->footer();
        exit;
    }
}

// Вывод страницы
echo $OUTPUT->header();
// Заголовок уже выводится через set_heading(), не нужно дублировать

global $DB, $USER;

// Вкладки
$tabs = [];
$tabs[] = new tabobject('history', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'history']),
    'История преподавателя');
$tabs[] = new tabobject('teachers', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'teachers']),
    'Преподаватели');
$tabs[] = new tabobject('students', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'students']),
    'Студенты');
$tabs[] = new tabobject('subjects', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects']),
    'Предметы');
$tabs[] = new tabobject('programs', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']),
    'Программы');
$tabs[] = new tabobject('institutions', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions']),
    'Учебные заведения');
$tabs[] = new tabobject('categories', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'categories']),
    'Категории курсов');
$tabs[] = new tabobject('cohorts', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'cohorts']),
    'Когорты');
$tabs[] = new tabobject('searchstudent', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'searchstudent']),
    'Поиск студента');

echo $OUTPUT->tabtree($tabs, $tab);

// Глобальные стили для кнопок действий во всех таблицах
echo html_writer::start_tag('style');
echo "
    .action-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .action-btn {
        width: 44px;
        height: 44px;
        border: 2px solid transparent;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.25s ease;
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        position: relative;
        overflow: hidden;
        color: black;
        text-shadow: 0 1px 2px rgba(255,255,255,0.5);
        -webkit-font-smoothing: antialiased;
    }
    .action-btn span {
        filter: brightness(0);
        display: inline-block;
    }
    .action-btn i.fas,
    .action-btn i.far,
    .action-btn i.fab {
        filter: none;
        color: white;
        font-size: 18px;
    }
    .action-btn::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }
    .action-btn:hover::after {
        left: 100%;
    }
    .action-btn:hover {
        transform: translateY(-3px) scale(1.08);
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        border-color: rgba(255,255,255,0.5);
    }
    .action-btn:active {
        transform: translateY(-1px) scale(1.02);
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    }
    .action-btn-view {
        background: #3b82f6;
        color: white;
    }
    .action-btn-view:hover {
        background: #2563eb;
        border-color: rgba(255,255,255,0.3);
    }
    .action-btn-edit {
        background: #f59e0b;
        color: white;
    }
    .action-btn-edit:hover {
        background: #d97706;
        border-color: rgba(255,255,255,0.3);
    }
    .action-btn-copy {
        background: #8b5cf6;
        color: white;
    }
    .action-btn-copy:hover {
        background: #7c3aed;
        border-color: rgba(255,255,255,0.3);
    }
    .action-btn-delete {
        background: #ef4444;
        color: white;
    }
    .action-btn-delete:hover {
        background: #dc2626;
        border-color: rgba(255,255,255,0.3);
    }
    .action-btn-link {
        background: #10b981;
        color: white;
    }
    .action-btn-link:hover {
        background: #059669;
        border-color: rgba(255,255,255,0.3);
    }
";
echo html_writer::end_tag('style');

// Получение ID ролей преподавателей
$teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
if (empty($teacherroleids)) {
    $teacherroleids = [];
}

// Получение списка преподавателей из всех контекстов (системный + все курсы)
$teachers = [];
if (!empty($teacherroleids)) {
    $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
    
    // Получаем преподавателей из системного контекста и всех контекстов курсов
    $systemcontext = context_system::instance();
    
    // Получаем все контексты курсов
    $coursecontexts = $DB->get_records_sql(
        "SELECT id FROM {context} WHERE contextlevel = 50"
    );
    $coursecontextids = array_keys($coursecontexts);
    
    // Объединяем системный контекст и контексты курсов
    $allcontextids = array_merge([$systemcontext->id], $coursecontextids);
    $contextplaceholders = implode(',', array_fill(0, count($allcontextids), '?'));
    
    // Получаем преподавателей с их ролями
    $teacherrecords = $DB->get_records_sql(
        "SELECT DISTINCT ra.userid, r.shortname as roleshortname, r.name as rolename
         FROM {role_assignments} ra
         JOIN {role} r ON r.id = ra.roleid
         WHERE ra.contextid IN ($contextplaceholders) 
         AND ra.roleid IN ($placeholders)
         ORDER BY ra.userid, r.shortname",
        array_merge($allcontextids, $teacherroleids)
    );
    
    // Группируем по пользователям и собираем роли
    $teachersdata = [];
    foreach ($teacherrecords as $record) {
        if (!isset($teachersdata[$record->userid])) {
            $teachersdata[$record->userid] = [
                'userid' => $record->userid,
                'roles' => []
            ];
        }
        $teachersdata[$record->userid]['roles'][] = $record->roleshortname;
    }
    
        if (!empty($teachersdata)) {
        $userids = array_keys($teachersdata);
        $userplaceholders = implode(',', array_fill(0, count($userids), '?'));
        $userrecords = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
             FROM {user} u
             WHERE u.id IN ($userplaceholders)
             AND u.deleted = 0
             ORDER BY u.lastname, u.firstname",
            $userids
        );
        
        // Подсчет количества курсов для каждого преподавателя
        $teachercoursescount = [];
        if (!empty($userids) && !empty($teacherroleids)) {
            $placeholders_count = implode(',', array_fill(0, count($teacherroleids), '?'));
            foreach ($userids as $uid) {
                $coursecontextids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT ra.contextid
                     FROM {role_assignments} ra
                     JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE ctx.contextlevel = 50
                     AND ra.userid = ?
                     AND ra.roleid IN ($placeholders_count)",
                    array_merge([$uid], $teacherroleids)
                );
                
                if (!empty($coursecontextids)) {
                    $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
                    $count = $DB->count_records_sql(
                        "SELECT COUNT(DISTINCT c.id)
                         FROM {course} c
                         JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                         WHERE ctx.id IN ($contextplaceholders)
                         AND c.id > 1",
                        $coursecontextids
                    );
                    $teachercoursescount[$uid] = $count;
                } else {
                    $teachercoursescount[$uid] = 0;
                }
            }
        }
        
        // Объединяем данные пользователей с ролями и количеством курсов
        foreach ($userrecords as $user) {
            $roles = isset($teachersdata[$user->id]) ? $teachersdata[$user->id]['roles'] : [];
            $user->roles = array_unique($roles);
            $user->coursescount = isset($teachercoursescount[$user->id]) ? $teachercoursescount[$user->id] : 0;
            $teachers[$user->id] = $user;
        }
    }
}

// Содержимое в зависимости от выбранной вкладки
switch ($tab) {
    case 'history':
        // Форма выбора преподавателя и периода
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'История преподавателя', ['style' => 'margin-bottom: 20px;']);

        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => new moodle_url('/local/deanpromoodle/pages/admin.php'),
            'class' => 'form-inline',
            'style' => 'background-color: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'history']);

        // Выбор преподавателя
        echo html_writer::label('Преподаватель: ', 'teacherid');
        $teacheroptions = [0 => 'Все преподаватели'];
        foreach ($teachers as $tid => $teacher) {
            $teacheroptions[$tid] = fullname($teacher);
        }
        echo html_writer::select($teacheroptions, 'teacherid', $teacherid, false, ['class' => 'form-control', 'style' => 'margin-left: 5px; margin-right: 15px;']);

        // Выбор периода
        echo html_writer::label('Период: ', 'period');
        $periodoptions = [
            'day' => 'День',
            'week' => 'Неделя',
            'month' => 'Месяц',
            'year' => 'Год'
        ];
        echo html_writer::select($periodoptions, 'period', $period, false, ['class' => 'form-control', 'style' => 'margin-left: 5px; margin-right: 15px;']);

        // Даты (опционально)
        echo html_writer::label('С: ', 'datefrom');
        echo html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'datefrom',
            'value' => $datefrom,
            'class' => 'form-control',
            'style' => 'margin-left: 5px; margin-right: 15px;'
        ]);

        echo html_writer::label('По: ', 'dateto');
        echo html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'dateto',
            'value' => $dateto,
            'class' => 'form-control',
            'style' => 'margin-left: 5px; margin-right: 15px;'
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => 'Показать',
            'class' => 'btn btn-primary',
            'style' => 'margin-left: 10px;'
        ]);

        echo html_writer::end_tag('form');

        // Получение данных истории
        $history = [];
        $teacherfilter = $teacherid > 0 ? "AND g.grader = $teacherid" : "";
        
        // Определение диапазона дат
        $now = time();
        switch ($period) {
        case 'day':
            $startdate = $datefrom ? strtotime($datefrom) : mktime(0, 0, 0, date('n', $now), date('j', $now), date('Y', $now));
            $enddate = $dateto ? strtotime($dateto . ' 23:59:59') : mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
            $dateformat = 'Y-m-d';
            break;
        case 'week':
            $startdate = $datefrom ? strtotime($datefrom) : strtotime('monday this week');
            $enddate = $dateto ? strtotime($dateto . ' 23:59:59') : strtotime('sunday this week 23:59:59');
            $dateformat = 'Y-W';
            break;
        case 'month':
            $startdate = $datefrom ? strtotime($datefrom) : mktime(0, 0, 0, date('n', $now), 1, date('Y', $now));
            $enddate = $dateto ? strtotime($dateto . ' 23:59:59') : mktime(23, 59, 59, date('n', $now), date('t', $now), date('Y', $now));
            $dateformat = 'Y-m';
            break;
        case 'year':
            $startdate = $datefrom ? strtotime($datefrom) : mktime(0, 0, 0, 1, 1, date('Y', $now));
            $enddate = $dateto ? strtotime($dateto . ' 23:59:59') : mktime(23, 59, 59, 12, 31, date('Y', $now));
            $dateformat = 'Y';
            break;
        }
        
        // Получение проверенных заданий
        $assignmentshistory = $DB->get_records_sql(
        "SELECT g.id, g.grader, g.timemodified, g.grade,
                a.name as assignmentname, c.fullname as coursename, c.id as courseid,
                u.firstname, u.lastname, u.id as studentid
         FROM {assign_grades} g
         JOIN {assign} a ON a.id = g.assignment
         JOIN {course} c ON c.id = a.course
         JOIN {user} u ON u.id = g.userid
         WHERE g.timemodified >= ? AND g.timemodified <= ?
         $teacherfilter
         ORDER BY g.timemodified DESC",
            [$startdate, $enddate]
        );
        
        // Получение проверенных тестов
        // Для тестов определяем преподавателя по курсу, где он имеет роль преподавателя
        $quizzeshistory = [];
        if (!empty($teacherroleids)) {
        $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
        
        // Получаем все проверенные тесты
        $allquizzes = $DB->get_records_sql(
            "SELECT qg.id, qg.timemodified, qg.grade,
                    q.name as quizname, c.fullname as coursename, c.id as courseid,
                    u.firstname, u.lastname, u.id as studentid
             FROM {quiz_grades} qg
             JOIN {quiz} q ON q.id = qg.quiz
             JOIN {course} c ON c.id = q.course
             JOIN {user} u ON u.id = qg.userid
             WHERE qg.timemodified >= ? AND qg.timemodified <= ?
             ORDER BY qg.timemodified DESC",
            [$startdate, $enddate]
        );
        
        // Определяем преподавателя для каждого теста по курсу
        foreach ($allquizzes as $item) {
                $coursecontext = context_course::instance($item->courseid);
                $teachers_in_course = $DB->get_fieldset_sql(
                    "SELECT DISTINCT ra.userid
                     FROM {role_assignments} ra
                     WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)",
                    array_merge([$coursecontext->id], $teacherroleids)
                );
                
                // Если выбран конкретный преподаватель, проверяем его наличие в курсе
                if ($teacherid > 0) {
                    if (!in_array($teacherid, $teachers_in_course)) {
                        continue;
                    }
                    $item->grader = $teacherid;
                } else {
                    // Берем первого преподавателя из курса
                    if (empty($teachers_in_course)) {
                        continue;
                    }
                    $item->grader = $teachers_in_course[0];
                }
                
                $quizzeshistory[] = $item;
            }
        }
        
        // Получение ответов на форумах от преподавателей
        $forumshistory = [];
        if (!empty($teacherroleids)) {
            $placeholders_forum = implode(',', array_fill(0, count($teacherroleids), '?'));
            $teacherfilter_forum = $teacherid > 0 ? "AND p.userid = $teacherid" : "";
            
            $forumshistory = $DB->get_records_sql(
            "SELECT p.id, p.userid as grader, p.created as timemodified,
                    f.name as forumname, c.fullname as coursename, c.id as courseid,
                    d.name as discussionname, p.subject
             FROM {forum_posts} p
             JOIN {forum_discussions} d ON d.id = p.discussion
             JOIN {forum} f ON f.id = d.forum
             JOIN {course} c ON c.id = f.course
             JOIN {context} cctx ON cctx.instanceid = c.id AND cctx.contextlevel = 50
             WHERE p.created >= ? AND p.created <= ?
             $teacherfilter_forum
             AND EXISTS (
                 SELECT 1 FROM {role_assignments} ra 
                 WHERE ra.userid = p.userid
                 AND ra.contextid = cctx.id
                 AND ra.roleid IN ($placeholders_forum)
             )
             ORDER BY p.created DESC",
                array_merge([$startdate, $enddate], $teacherroleids)
            );
        }
        
        // Группировка данных по периоду
        $groupedhistory = [];
        
        // Группировка заданий
        foreach ($assignmentshistory as $item) {
        $teacherid_item = $item->grader;
        $teachername = $DB->get_field('user', 'CONCAT(firstname, " ", lastname)', ['id' => $teacherid_item]);
        if (!$teachername) {
            $teachername = 'Неизвестно';
        }
        $datekey = date($dateformat, $item->timemodified);
        if (!isset($groupedhistory[$teacherid_item])) {
            $groupedhistory[$teacherid_item] = [
                'teachername' => $teachername,
                'periods' => []
            ];
        }
        if (!isset($groupedhistory[$teacherid_item]['periods'][$datekey])) {
            $groupedhistory[$teacherid_item]['periods'][$datekey] = [
                'assignments' => 0,
                'quizzes' => 0,
                'forums' => 0
            ];
        }
        $groupedhistory[$teacherid_item]['periods'][$datekey]['assignments']++;
    }
    
    // Группировка тестов
    foreach ($quizzeshistory as $item) {
        $teacherid_item = $item->grader;
        $teachername = $DB->get_field('user', 'CONCAT(firstname, " ", lastname)', ['id' => $teacherid_item]);
        if (!$teachername) {
            $teachername = 'Неизвестно';
        }
        $datekey = date($dateformat, $item->timemodified);
        if (!isset($groupedhistory[$teacherid_item])) {
            $groupedhistory[$teacherid_item] = [
                'teachername' => $teachername,
                'periods' => []
            ];
        }
        if (!isset($groupedhistory[$teacherid_item]['periods'][$datekey])) {
            $groupedhistory[$teacherid_item]['periods'][$datekey] = [
                'assignments' => 0,
                'quizzes' => 0,
                'forums' => 0
            ];
        }
        $groupedhistory[$teacherid_item]['periods'][$datekey]['quizzes']++;
    }
    
    // Группировка форумов
    foreach ($forumshistory as $item) {
        $teacherid_item = $item->grader;
        $teachername = $DB->get_field('user', 'CONCAT(firstname, " ", lastname)', ['id' => $teacherid_item]);
        if (!$teachername) {
            $teachername = 'Неизвестно';
        }
        $datekey = date($dateformat, $item->timemodified);
        if (!isset($groupedhistory[$teacherid_item])) {
            $groupedhistory[$teacherid_item] = [
                'teachername' => $teachername,
                'periods' => []
            ];
        }
        if (!isset($groupedhistory[$teacherid_item]['periods'][$datekey])) {
            $groupedhistory[$teacherid_item]['periods'][$datekey] = [
                'assignments' => 0,
                'quizzes' => 0,
                'forums' => 0
            ];
        }
            $groupedhistory[$teacherid_item]['periods'][$datekey]['forums']++;
        }
        
        // Отображение результатов
        if (empty($groupedhistory)) {
            echo html_writer::div('Данные не найдены за выбранный период.', 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%; margin-top: 20px;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Преподаватель');
            echo html_writer::tag('th', 'Период');
            echo html_writer::tag('th', 'Задания');
            echo html_writer::tag('th', 'Тесты');
            echo html_writer::tag('th', 'Форумы');
            echo html_writer::tag('th', 'Всего');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            foreach ($groupedhistory as $tid => $teacherdata) {
                foreach ($teacherdata['periods'] as $periodkey => $data) {
                    $total = $data['assignments'] + $data['quizzes'] + $data['forums'];
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('td', htmlspecialchars($teacherdata['teachername']));
                    echo html_writer::tag('td', htmlspecialchars($periodkey));
                    echo html_writer::tag('td', $data['assignments']);
                    echo html_writer::tag('td', $data['quizzes']);
                    echo html_writer::tag('td', $data['forums']);
                    echo html_writer::tag('td', html_writer::tag('strong', $total));
                    echo html_writer::end_tag('tr');
                }
            }
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }
        echo html_writer::end_div();
        break;
    
    case 'teachers':
        // Вкладка "Преподаватели" - список всех преподавателей
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Список преподавателей', ['style' => 'margin-bottom: 20px;']);
        
        if (empty($teachers)) {
            echo html_writer::div('Преподаватели не найдены.', 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'ID');
            echo html_writer::tag('th', 'ФИО');
            echo html_writer::tag('th', 'Email');
            echo html_writer::tag('th', 'Роль');
            echo html_writer::tag('th', 'Количество курсов');
            echo html_writer::tag('th', 'Дата регистрации');
            echo html_writer::tag('th', 'Последний вход');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            foreach ($teachers as $teacher) {
                $userrecord = $DB->get_record('user', ['id' => $teacher->id]);
                
                // Формируем список ролей
                $rolenames = [];
                if (isset($teacher->roles) && !empty($teacher->roles)) {
                    $roletranslations = [
                        'teacher' => 'Преподаватель',
                        'editingteacher' => 'Редактор курса',
                        'manager' => 'Менеджер'
                    ];
                    foreach ($teacher->roles as $roleshortname) {
                        $rolenames[] = isset($roletranslations[$roleshortname]) 
                            ? $roletranslations[$roleshortname] 
                            : $roleshortname;
                    }
                }
                $rolesdisplay = !empty($rolenames) ? implode(', ', $rolenames) : '-';
                
                // Делаем строку кликабельной для открытия модального окна
                echo html_writer::start_tag('tr', [
                    'class' => 'teacher-row',
                    'data-teacher-id' => $teacher->id,
                    'data-teacher-name' => htmlspecialchars(fullname($teacher)),
                    'style' => 'cursor: pointer;'
                ]);
                echo html_writer::tag('td', $teacher->id);
                echo html_writer::tag('td', htmlspecialchars(fullname($teacher)));
                echo html_writer::tag('td', htmlspecialchars($teacher->email));
                echo html_writer::tag('td', htmlspecialchars($rolesdisplay));
                $coursescount = isset($teacher->coursescount) ? $teacher->coursescount : 0;
                echo html_writer::tag('td', html_writer::tag('strong', $coursescount, ['style' => 'color: #007bff;']));
                echo html_writer::tag('td', $userrecord ? userdate($userrecord->timecreated) : '-');
                echo html_writer::tag('td', $userrecord && $userrecord->lastaccess > 0 ? userdate($userrecord->lastaccess) : 'Никогда');
                echo html_writer::end_tag('tr');
            }
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }
        
        // Модальное окно для отображения курсов преподавателя
        echo html_writer::start_div('modal fade', ['id' => 'teacherCoursesModal', 'tabindex' => '-1', 'role' => 'dialog', 'aria-labelledby' => 'modalTeacherName', 'aria-hidden' => 'true']);
        echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
        echo html_writer::start_div('modal-content');
        echo html_writer::start_div('modal-header');
        echo html_writer::tag('h5', 'Курсы преподавателя', ['class' => 'modal-title', 'id' => 'modalTeacherName']);
        echo html_writer::start_tag('button', [
            'type' => 'button', 
            'class' => 'close', 
            'data-dismiss' => 'modal', 
            'aria-label' => 'Close',
            'onclick' => 'jQuery(\'#teacherCoursesModal\').modal(\'hide\');'
        ]);
        echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
        echo html_writer::end_tag('button');
        echo html_writer::end_div(); // modal-header
        echo html_writer::start_div('modal-body', ['id' => 'modalTeacherCourses']);
        echo html_writer::div('Загрузка...', 'text-center');
        echo html_writer::end_div(); // modal-body
        echo html_writer::start_div('modal-footer');
        echo html_writer::start_tag('button', [
            'type' => 'button', 
            'class' => 'btn btn-secondary', 
            'data-dismiss' => 'modal',
            'onclick' => 'jQuery(\'#teacherCoursesModal\').modal(\'hide\');'
        ]);
        echo 'Закрыть';
        echo html_writer::end_tag('button');
        echo html_writer::end_div(); // modal-footer
        echo html_writer::end_div(); // modal-content
        echo html_writer::end_div(); // modal-dialog
        echo html_writer::end_div(); // modal
        
        // JavaScript для загрузки курсов преподавателя
        $PAGE->requires->js_init_code("
            (function() {
                var teacherRows = document.querySelectorAll('.teacher-row');
                teacherRows.forEach(function(row) {
                    row.addEventListener('click', function() {
                        var teacherId = this.getAttribute('data-teacher-id');
                        var teacherName = this.getAttribute('data-teacher-name');
                        var modal = document.getElementById('teacherCoursesModal');
                        var modalTitle = document.getElementById('modalTeacherName');
                        var modalBody = document.getElementById('modalTeacherCourses');
                        
                        modalTitle.textContent = 'Курсы преподавателя: ' + teacherName;
                        modalBody.innerHTML = '<div class=\"text-center\">Загрузка...</div>';
                        
                        // AJAX запрос для получения курсов
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getteachercourses&teacherid=' + teacherId, true);
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            var html = '<table class=\"table table-striped table-hover\"><thead><tr><th>ID</th><th>Название курса</th><th>Категория</th><th>Дата начала</th><th>Дата окончания</th></tr></thead><tbody>';
                                            if (response.courses && response.courses.length > 0) {
                                                response.courses.forEach(function(course) {
                                                    html += '<tr><td>' + course.id + '</td><td>' + course.fullname + '</td><td>' + (course.categoryname || '-') + '</td><td>' + (course.startdate || '-') + '</td><td>' + (course.enddate || '-') + '</td></tr>';
                                                });
                                            } else {
                                                html += '<tr><td colspan=\"5\" class=\"text-center\">Курсы не найдены</td></tr>';
                                            }
                                            html += '</tbody></table>';
                                            modalBody.innerHTML = html;
                                        } else {
                                            modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка: ' + (response.error || 'Неизвестная ошибка') + '</div>';
                                        }
                                    } catch (e) {
                                        modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа сервера</div>';
                                    }
                                } else {
                                    modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка загрузки данных</div>';
                                }
                            }
                        };
                        xhr.send();
                        
                        // Показываем модальное окно (Bootstrap/jQuery)
                        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                            jQuery(modal).modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var bsModal = new bootstrap.Modal(modal);
                            bsModal.show();
                        } else {
                            // Fallback: просто показываем модальное окно через CSS
                            modal.style.display = 'block';
                            modal.classList.add('show');
                            document.body.classList.add('modal-open');
                        }
                    });
                });
                
                // Обработчик закрытия модального окна при клике вне его
                var modalElement = document.getElementById('teacherCoursesModal');
                if (modalElement) {
                    modalElement.addEventListener('click', function(e) {
                        if (e.target === modalElement) {
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery(modalElement).modal('hide');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var bsModal = bootstrap.Modal.getInstance(modalElement);
                                if (bsModal) {
                                    bsModal.hide();
                                }
                            } else {
                                modalElement.style.display = 'none';
                                modalElement.classList.remove('show');
                                document.body.classList.remove('modal-open');
                            }
                        }
                    });
                }
            })();
        ");
        
        echo html_writer::end_div();
        break;
    
    case 'students':
        // Вкладка "Студенты" - статистика по студентам
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Статистика студентов', ['style' => 'margin-bottom: 20px;']);
        
        // Форма фильтрации
        echo html_writer::start_tag('form', [
            'method' => 'get',
            'action' => new moodle_url('/local/deanpromoodle/pages/admin.php'),
            'class' => 'form-inline',
            'style' => 'background-color: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;'
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'students']);
        
        echo html_writer::label('Период: ', 'studentperiod');
        $periodoptions = [
            'day' => 'День',
            'week' => 'Неделя',
            'month' => 'Месяц',
            'year' => 'Год'
        ];
        echo html_writer::select($periodoptions, 'studentperiod', $studentperiod, false, ['class' => 'form-control', 'style' => 'margin-left: 5px; margin-right: 15px;']);
        
        echo html_writer::label('С: ', 'studentdatefrom');
        echo html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'studentdatefrom',
            'value' => $studentdatefrom,
            'class' => 'form-control',
            'style' => 'margin-left: 5px; margin-right: 15px;'
        ]);
        
        echo html_writer::label('По: ', 'studentdateto');
        echo html_writer::empty_tag('input', [
            'type' => 'date',
            'name' => 'studentdateto',
            'value' => $studentdateto,
            'class' => 'form-control',
            'style' => 'margin-left: 5px; margin-right: 15px;'
        ]);
        
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => 'Показать',
            'class' => 'btn btn-primary',
            'style' => 'margin-left: 10px;'
        ]);
        echo html_writer::end_tag('form');
        
        // Определение диапазона дат
        $now = time();
        switch ($studentperiod) {
            case 'day':
                $startdate = $studentdatefrom ? strtotime($studentdatefrom) : mktime(0, 0, 0, date('n', $now), date('j', $now), date('Y', $now));
                $enddate = $studentdateto ? strtotime($studentdateto . ' 23:59:59') : mktime(23, 59, 59, date('n', $now), date('j', $now), date('Y', $now));
                $dateformat = 'Y-m-d';
                break;
            case 'week':
                $startdate = $studentdatefrom ? strtotime($studentdatefrom) : strtotime('monday this week');
                $enddate = $studentdateto ? strtotime($studentdateto . ' 23:59:59') : strtotime('sunday this week 23:59:59');
                $dateformat = 'Y-W';
                break;
            case 'month':
                $startdate = $studentdatefrom ? strtotime($studentdatefrom) : mktime(0, 0, 0, date('n', $now), 1, date('Y', $now));
                $enddate = $studentdateto ? strtotime($studentdateto . ' 23:59:59') : mktime(23, 59, 59, date('n', $now), date('t', $now), date('Y', $now));
                $dateformat = 'Y-m';
                break;
            case 'year':
                $startdate = $studentdatefrom ? strtotime($studentdatefrom) : mktime(0, 0, 0, 1, 1, date('Y', $now));
                $enddate = $studentdateto ? strtotime($studentdateto . ' 23:59:59') : mktime(23, 59, 59, 12, 31, date('Y', $now));
                $dateformat = 'Y';
                break;
        }
        
        // Получение ID роли студента
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        
        // Получение статистики по зачислениям
        $enrolments = [];
        if ($studentroleid) {
            $enrolments = $DB->get_records_sql(
                "SELECT ue.timestart, ue.timeend, ue.status,
                        u.id as userid, u.firstname, u.lastname, u.email,
                        c.fullname as coursename, c.id as courseid
                 FROM {user_enrolments} ue
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                 JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
                 WHERE ra.roleid = ?
                 AND ue.timestart >= ? AND ue.timestart <= ?
                 ORDER BY ue.timestart DESC",
                [$studentroleid, $startdate, $enddate]
            );
        }
        
        // Получение статистики по отчислениям
        $unenrolments = [];
        if ($studentroleid) {
            $unenrolments = $DB->get_records_sql(
                "SELECT ue.timeend, ue.status,
                        u.id as userid, u.firstname, u.lastname, u.email,
                        c.fullname as coursename, c.id as courseid
                 FROM {user_enrolments} ue
                 JOIN {enrol} e ON e.id = ue.enrolid
                 JOIN {course} c ON c.id = e.courseid
                 JOIN {user} u ON u.id = ue.userid
                 JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                 JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
                 WHERE ra.roleid = ?
                 AND ue.timeend > 0
                 AND ue.timeend >= ? AND ue.timeend <= ?
                 ORDER BY ue.timeend DESC",
                [$studentroleid, $startdate, $enddate]
            );
        }
        
        // Получение статистики по удаленным студентам
        $deletedstudents = [];
        if ($studentroleid) {
            $systemcontext = context_system::instance();
            $deletedstudents = $DB->get_records_sql(
                "SELECT u.id, u.firstname, u.lastname, u.email, u.timemodified, u.deleted
                 FROM {user} u
                 JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ?
                 WHERE ra.roleid = ?
                 AND u.deleted = 1
                 AND u.timemodified >= ? AND u.timemodified <= ?
                 ORDER BY u.timemodified DESC",
                [$systemcontext->id, $studentroleid, $startdate, $enddate]
            );
        }
        
        // Получение статистики по обновлению данных студентов
        $updatedstudents = [];
        if ($studentroleid) {
            $systemcontext = context_system::instance();
            $updatedstudents = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timemodified
                 FROM {user} u
                 JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ?
                 WHERE ra.roleid = ?
                 AND u.deleted = 0
                 AND u.timemodified >= ? AND u.timemodified <= ?
                 AND u.timemodified > u.timecreated
                 ORDER BY u.timemodified DESC
                 LIMIT 1000",
                [$systemcontext->id, $studentroleid, $startdate, $enddate]
            );
        }
        
        // Группировка данных по периоду
        $groupeddata = [];
        
        // Группировка зачислений
        foreach ($enrolments as $item) {
            $datekey = date($dateformat, $item->timestart);
            if (!isset($groupeddata[$datekey])) {
                $groupeddata[$datekey] = [
                    'enrolled' => 0,
                    'unenrolled' => 0,
                    'deleted' => 0,
                    'updated' => 0
                ];
            }
            $groupeddata[$datekey]['enrolled']++;
        }
        
        // Группировка отчислений
        foreach ($unenrolments as $item) {
            $datekey = date($dateformat, $item->timeend);
            if (!isset($groupeddata[$datekey])) {
                $groupeddata[$datekey] = [
                    'enrolled' => 0,
                    'unenrolled' => 0,
                    'deleted' => 0,
                    'updated' => 0
                ];
            }
            $groupeddata[$datekey]['unenrolled']++;
        }
        
        // Группировка удалений
        foreach ($deletedstudents as $item) {
            $datekey = date($dateformat, $item->timemodified);
            if (!isset($groupeddata[$datekey])) {
                $groupeddata[$datekey] = [
                    'enrolled' => 0,
                    'unenrolled' => 0,
                    'deleted' => 0,
                    'updated' => 0
                ];
            }
            $groupeddata[$datekey]['deleted']++;
        }
        
        // Группировка обновлений
        foreach ($updatedstudents as $item) {
            $datekey = date($dateformat, $item->timemodified);
            if (!isset($groupeddata[$datekey])) {
                $groupeddata[$datekey] = [
                    'enrolled' => 0,
                    'unenrolled' => 0,
                    'deleted' => 0,
                    'updated' => 0
                ];
            }
            $groupeddata[$datekey]['updated']++;
        }
        
        // Отображение результатов
        if (empty($groupeddata)) {
            echo html_writer::div('Данные не найдены за выбранный период.', 'alert alert-info');
        } else {
            krsort($groupeddata); // Сортировка по дате (новые сверху)
            
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%; margin-top: 20px;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Период');
            echo html_writer::tag('th', 'Зачислено');
            echo html_writer::tag('th', 'Отчислено');
            echo html_writer::tag('th', 'Удалено');
            echo html_writer::tag('th', 'Обновлено данных');
            echo html_writer::tag('th', 'Всего');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            foreach ($groupeddata as $periodkey => $data) {
                $total = $data['enrolled'] + $data['unenrolled'] + $data['deleted'] + $data['updated'];
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($periodkey));
                echo html_writer::tag('td', html_writer::tag('span', $data['enrolled'], ['style' => 'color: green; font-weight: bold;']));
                echo html_writer::tag('td', html_writer::tag('span', $data['unenrolled'], ['style' => 'color: orange; font-weight: bold;']));
                echo html_writer::tag('td', html_writer::tag('span', $data['deleted'], ['style' => 'color: red; font-weight: bold;']));
                echo html_writer::tag('td', html_writer::tag('span', $data['updated'], ['style' => 'color: blue; font-weight: bold;']));
                echo html_writer::tag('td', html_writer::tag('strong', $total));
                echo html_writer::end_tag('tr');
            }
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }
        echo html_writer::end_div();
        break;
    
    case 'programs':
        // Вкладка "Программы"
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        
        // Проверяем существование таблиц БД
        $tablesexist = false;
        $errormsg = '';
        try {
            // Проверяем существование таблицы через простой запрос
            $test = $DB->get_records('local_deanpromoodle_programs', [], '', 'id', 0, 1);
            $tablesexist = true;
        } catch (\dml_exception $e) {
            $tablesexist = false;
            $errormsg = $e->getMessage();
        } catch (\Exception $e) {
            $tablesexist = false;
            $errormsg = $e->getMessage();
        }
        
        if (!$tablesexist) {
            echo html_writer::div('Таблицы программ еще не созданы. Пожалуйста, обновите плагин через админ-панель Moodle (Настройки сайта → Уведомления → Обновить).', 'alert alert-warning');
            if (!empty($errormsg)) {
                echo html_writer::div('Ошибка: ' . htmlspecialchars($errormsg, ENT_QUOTES, 'UTF-8'), 'alert alert-info', ['style' => 'font-size: 12px; margin-top: 10px;']);
            }
            echo html_writer::end_div();
            break;
        }
        
        // Обработка импорта JSON
        $importaction = optional_param('import', '', PARAM_ALPHA);
        if ($importaction == 'json') {
            $importsubmitted = optional_param('import_submit', 0, PARAM_INT);
            if ($importsubmitted) {
                // Проверяем загруженный файл
                $file = $_FILES['jsonfile'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    echo html_writer::div('Ошибка загрузки файла. Убедитесь, что файл выбран и не превышает максимальный размер.', 'alert alert-danger');
                } else {
                    // Проверяем тип файла
                    $filetype = mime_content_type($file['tmp_name']);
                    $fileext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($fileext !== 'json' && strpos($filetype, 'json') === false && strpos($filetype, 'text') === false) {
                        echo html_writer::div('Неверный тип файла. Загрузите файл в формате JSON.', 'alert alert-danger');
                    } else {
                        // Читаем содержимое файла
                        $jsoncontent = file_get_contents($file['tmp_name']);
                        if ($jsoncontent === false) {
                            echo html_writer::div('Ошибка чтения файла.', 'alert alert-danger');
                        } else {
                            // Парсим JSON
                            $jsondata = json_decode($jsoncontent, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                echo html_writer::div('Ошибка парсинга JSON: ' . json_last_error_msg(), 'alert alert-danger');
                            } else {
                                // Определяем структуру: если есть ключ 'programs', используем его, иначе весь JSON как массив
                                if (is_array($jsondata) && isset($jsondata['programs']) && is_array($jsondata['programs'])) {
                                    $programsdata = $jsondata['programs'];
                                } elseif (is_array($jsondata)) {
                                    $programsdata = $jsondata;
                                } else {
                                    echo html_writer::div('JSON файл должен содержать массив программ или объект с ключом "programs".', 'alert alert-danger');
                                    $programsdata = [];
                                }
                                
                                if (empty($programsdata)) {
                                    echo html_writer::div('Не найдено программ для импорта.', 'alert alert-warning');
                                } else {
                                    // Импортируем программы
                                    $imported = 0;
                                    $skipped = 0;
                                    $errors = [];
                                    
                                    $transaction = $DB->start_delegated_transaction();
                                    try {
                                        foreach ($programsdata as $index => $programdata) {
                                            // Валидация данных
                                            if (empty($programdata['name'])) {
                                                $errors[] = 'Программа #' . ((int)$index + 1) . ': отсутствует название';
                                                $skipped++;
                                                continue;
                                            }
                                            
                                            // Проверяем, существует ли программа с таким названием или кодом
                                            $existing = null;
                                            $programcode = !empty($programdata['code']) ? trim($programdata['code']) : '';
                                            if ($programcode) {
                                                $existing = $DB->get_record('local_deanpromoodle_programs', ['code' => $programcode]);
                                            }
                                            if (!$existing) {
                                                $existing = $DB->get_record('local_deanpromoodle_programs', ['name' => trim($programdata['name'])]);
                                            }
                                            
                                            $programid = null;
                                            if ($existing) {
                                                // Обновляем существующую программу
                                                $programid = $existing->id;
                                                $data = new stdClass();
                                                $data->id = $programid;
                                                $data->name = trim($programdata['name']);
                                                $data->code = $programcode;
                                                $data->description = isset($programdata['description']) ? ($programdata['description'] ?: '') : '';
                                                $data->institution = isset($programdata['institution']['name']) ? trim($programdata['institution']['name']) : (isset($programdata['institution']) && is_string($programdata['institution']) ? trim($programdata['institution']) : '');
                                                $data->visible = isset($programdata['is_active']) ? ($programdata['is_active'] ? 1 : 0) : 1;
                                                $data->timemodified = time();
                                                $DB->update_record('local_deanpromoodle_programs', $data);
                                                
                                                // Удаляем старые связи с предметами
                                                $DB->delete_records('local_deanpromoodle_program_subjects', ['programid' => $programid]);
                                            } else {
                                                // Создаем новую программу
                                                $data = new stdClass();
                                                $data->name = trim($programdata['name']);
                                                $data->code = $programcode;
                                                $data->description = isset($programdata['description']) ? ($programdata['description'] ?: '') : '';
                                                $data->institution = isset($programdata['institution']['name']) ? trim($programdata['institution']['name']) : (isset($programdata['institution']) && is_string($programdata['institution']) ? trim($programdata['institution']) : '');
                                                $data->visible = isset($programdata['is_active']) ? ($programdata['is_active'] ? 1 : 0) : 1;
                                                $data->timecreated = time();
                                                $data->timemodified = time();
                                                $programid = $DB->insert_record('local_deanpromoodle_programs', $data);
                                                $imported++;
                                            }
                                            
                                            // Обрабатываем предметы программы
                                            if (!empty($programdata['subjects']) && is_array($programdata['subjects']) && $programid) {
                                                $sortorder = 0;
                                                foreach ($programdata['subjects'] as $subjectdata) {
                                                    // Ищем предмет по коду или названию
                                                    $subject = null;
                                                    if (!empty($subjectdata['code'])) {
                                                        $subject = $DB->get_record('local_deanpromoodle_subjects', ['code' => trim($subjectdata['code'])]);
                                                    }
                                                    if (!$subject && !empty($subjectdata['name'])) {
                                                        $subject = $DB->get_record('local_deanpromoodle_subjects', ['name' => trim($subjectdata['name'])]);
                                                    }
                                                    
                                                    if ($subject) {
                                                        // Используем order из JSON или порядок по умолчанию
                                                        $subjectorder = isset($subjectdata['order']) ? (int)$subjectdata['order'] : $sortorder;
                                                        
                                                        $psdata = new stdClass();
                                                        $psdata->programid = $programid;
                                                        $psdata->subjectid = $subject->id;
                                                        $psdata->sortorder = $subjectorder;
                                                        $psdata->timecreated = time();
                                                        $psdata->timemodified = time();
                                                        $DB->insert_record('local_deanpromoodle_program_subjects', $psdata);
                                                        $sortorder++;
                                                    }
                                                }
                                            }
                                        }
                                        
                                        $transaction->allow_commit();
                                        
                                        // Сообщение об успехе
                                        $message = 'Импорт завершен. Импортировано программ: ' . $imported;
                                        if ($skipped > 0) {
                                            $message .= ', обновлено (уже существуют): ' . $skipped;
                                        }
                                        if (!empty($errors)) {
                                            $message .= '. Ошибки: ' . implode('; ', array_slice($errors, 0, 5));
                                            if (count($errors) > 5) {
                                                $message .= ' и еще ' . (count($errors) - 5) . ' ошибок';
                                            }
                                        }
                                        echo html_writer::div($message, 'alert alert-success');
                                        
                                        // Редирект на список программ
                                        redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']), $message, null, \core\output\notification::NOTIFY_SUCCESS);
                                    } catch (\Exception $e) {
                                        $transaction->rollback($e);
                                        echo html_writer::div('Ошибка при импорте: ' . $e->getMessage(), 'alert alert-danger');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Обработка копирования программы
        if ($action == 'copy' && $programid > 0) {
            try {
                $sourceprogram = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
                if (!$sourceprogram) {
                    echo html_writer::div('Программа не найдена.', 'alert alert-danger');
                } else {
                    // Определяем название для копии
                    $basename = trim($sourceprogram->name);
                    $copyname = $basename;
                    $copynumber = 1;
                    
                    // Проверяем, есть ли уже копии с таким названием
                    while ($DB->record_exists('local_deanpromoodle_programs', ['name' => $copyname])) {
                        $copynumber++;
                        $copyname = $basename . ' (копия ' . $copynumber . ')';
                    }
                    
                    // Если это первая копия и базовое название не содержит "копия"
                    if ($copynumber == 1 && strpos($basename, 'копия') === false) {
                        $copyname = $basename . ' (копия)';
                        // Проверяем, не существует ли уже такое название
                        if ($DB->record_exists('local_deanpromoodle_programs', ['name' => $copyname])) {
                            $copynumber = 2;
                            $copyname = $basename . ' (копия ' . $copynumber . ')';
                        }
                    }
                    
                    $transaction = $DB->start_delegated_transaction();
                    try {
                        // Создаем новую программу
                        $newprogram = new stdClass();
                        $newprogram->name = $copyname;
                        $newprogram->code = ''; // Код не копируем
                        $newprogram->description = $sourceprogram->description;
                        $newprogram->institution = $sourceprogram->institution ?? '';
                        $newprogram->visible = $sourceprogram->visible;
                        $newprogram->timecreated = time();
                        $newprogram->timemodified = time();
                        $newprogramid = $DB->insert_record('local_deanpromoodle_programs', $newprogram);
                        
                        // Копируем связи с предметами
                        $subjects = $DB->get_records('local_deanpromoodle_program_subjects', ['programid' => $programid], 'sortorder ASC');
                        foreach ($subjects as $subject) {
                            $newsubject = new stdClass();
                            $newsubject->programid = $newprogramid;
                            $newsubject->subjectid = $subject->subjectid;
                            $newsubject->sortorder = $subject->sortorder;
                            $newsubject->timecreated = time();
                            $newsubject->timemodified = time();
                            $DB->insert_record('local_deanpromoodle_program_subjects', $newsubject);
                        }
                        
                        $transaction->allow_commit();
                        
                        // Редирект на список программ
                        redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']), 'Программа успешно скопирована', null, \core\output\notification::NOTIFY_SUCCESS);
                    } catch (\Exception $e) {
                        $transaction->rollback($e);
                        echo html_writer::div('Ошибка при копировании программы: ' . $e->getMessage(), 'alert alert-danger');
                    }
                }
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
            }
        }
        
        // Обработка просмотра программы (только просмотр, без изменения порядка)
        if ($action == 'view' && $programid > 0) {
            try {
                $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
                if (!$program) {
                    echo html_writer::div('Программа не найдена.', 'alert alert-danger');
                } else {
                    // Получаем все предметы программы
                    $subjects = $DB->get_records_sql(
                        "SELECT s.id, s.name, s.code, s.shortdescription, ps.sortorder, ps.id as relation_id
                         FROM {local_deanpromoodle_program_subjects} ps
                         JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
                         WHERE ps.programid = ?
                         ORDER BY ps.sortorder ASC",
                        [$programid]
                    );
                    
                    // Заголовок страницы
                    echo html_writer::start_div('', ['style' => 'margin-bottom: 30px;']);
                    echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
                    echo html_writer::start_div('', ['style' => 'display: flex; align-items: center; gap: 15px;']);
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']),
                        '<i class="fas fa-arrow-left"></i> Назад к списку',
                        ['class' => 'btn btn-secondary', 'style' => 'text-decoration: none;']
                    );
                    echo html_writer::tag('h2', 'Просмотр программы: ' . htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8'), ['style' => 'margin: 0;']);
                    echo html_writer::end_div();
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'edit', 'programid' => $programid]),
                        '<i class="fas fa-edit"></i> Редактировать',
                        ['class' => 'btn btn-primary', 'style' => 'text-decoration: none;']
                    );
                    echo html_writer::end_div();
                    
                    // Информация о программе
                    echo html_writer::start_div('card', ['style' => 'margin-bottom: 20px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);']);
                    echo html_writer::tag('h3', 'Информация о программе', ['style' => 'margin-top: 0; margin-bottom: 15px;']);
                    echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: 200px 1fr; gap: 10px; margin-bottom: 10px;']);
                    echo html_writer::tag('strong', 'Название:');
                    echo html_writer::tag('div', htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8'));
                    echo html_writer::end_div();
                    if ($program->code) {
                        echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: 200px 1fr; gap: 10px; margin-bottom: 10px;']);
                        echo html_writer::tag('strong', 'Код:');
                        echo html_writer::tag('div', htmlspecialchars($program->code, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_div();
                    }
                    if ($program->institution) {
                        echo html_writer::start_div('', ['style' => 'display: grid; grid-template-columns: 200px 1fr; gap: 10px; margin-bottom: 10px;']);
                        echo html_writer::tag('strong', 'Учебное заведение:');
                        echo html_writer::tag('div', htmlspecialchars($program->institution, ENT_QUOTES, 'UTF-8'));
                        echo html_writer::end_div();
                    }
                    if ($program->description) {
                        echo html_writer::start_div('', ['style' => 'margin-top: 10px;']);
                        echo html_writer::tag('strong', 'Описание:');
                        echo html_writer::tag('div', format_text($program->description, FORMAT_HTML), ['style' => 'margin-top: 5px;']);
                        echo html_writer::end_div();
                    }
                    echo html_writer::end_div();
                    
                    // Список предметов
                    echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;']);
                    echo html_writer::tag('h3', 'Предметы программы', ['style' => 'margin: 0;']);
                    if (!empty($subjects)) {
                        echo html_writer::link(
                            new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'export_subjects', 'programid' => $programid]),
                            '<i class="fas fa-file-excel"></i> Экспорт в Excel',
                            [
                                'class' => 'btn btn-success',
                                'style' => 'text-decoration: none;'
                            ]
                        );
                    }
                    echo html_writer::end_div();
                    
                    // Стили для таблицы предметов
                    echo html_writer::start_tag('style');
                    echo "
                    .subjects-list table {
                        border-collapse: collapse;
                        width: 100%;
                    }
                    .subjects-list thead th {
                        background-color: #f8f9fa;
                        padding: 12px 16px;
                        text-align: left;
                        font-weight: 600;
                        color: #495057;
                        border-bottom: 2px solid #dee2e6;
                        font-size: 14px;
                    }
                    .subjects-list tbody tr {
                        border-bottom: 1px solid #f0f0f0;
                        transition: background-color 0.2s;
                    }
                    .subjects-list tbody tr:hover {
                        background-color: #f8f9fa;
                    }
                    .subjects-list tbody td {
                        padding: 12px 16px;
                        vertical-align: middle;
                    }
                    .subjects-list .btn-sm {
                        min-width: 36px;
                    }
                    ";
                    echo html_writer::end_tag('style');
                    
                    if (empty($subjects)) {
                        echo html_writer::div('В программе пока нет предметов.', 'alert alert-info');
                    } else {
                        echo html_writer::start_div('subjects-list', ['id' => 'subjects-list', 'style' => 'background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;']);
                        echo html_writer::start_tag('table', ['class' => 'table', 'style' => 'margin: 0; width: 100%;']);
                        echo html_writer::start_tag('thead');
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('th', 'Порядок', ['style' => 'width: 80px; text-align: center;']);
                        echo html_writer::tag('th', 'Название');
                        echo html_writer::tag('th', 'Код', ['style' => 'width: 150px;']);
                        echo html_writer::tag('th', 'Краткое описание', ['style' => 'width: 200px;']);
                        echo html_writer::tag('th', 'Курсы', ['style' => 'width: 300px;']);
                        echo html_writer::end_tag('tr');
                        echo html_writer::end_tag('thead');
                        echo html_writer::start_tag('tbody', ['id' => 'subjects-tbody']);
                        
                        foreach ($subjects as $subject) {
                            $subjectname = is_string($subject->name) ? $subject->name : (string)$subject->name;
                            $subjectcode = is_string($subject->code) ? $subject->code : '';
                            $subjectshortdesc = is_string($subject->shortdescription) ? $subject->shortdescription : '';
                            $sortorder = (int)$subject->sortorder;
                            $relationid = (int)$subject->relation_id;
                            
                            // Получаем курсы предмета
                            $subjectcourses = $DB->get_records_sql(
                                "SELECT sc.*, c.fullname, c.shortname
                                 FROM {local_deanpromoodle_subject_courses} sc
                                 JOIN {course} c ON c.id = sc.courseid
                                 WHERE sc.subjectid = ?
                                 ORDER BY sc.sortorder ASC, c.fullname ASC",
                                [$subject->id]
                            );
                            
                            echo html_writer::start_tag('tr', [
                                'data-subject-id' => $subject->id,
                                'data-relation-id' => $relationid,
                                'data-sortorder' => $sortorder
                            ]);
                            
                            // Порядок
                            echo html_writer::start_tag('td', ['style' => 'text-align: center; vertical-align: middle;']);
                            echo html_writer::tag('span', $sortorder + 1, ['class' => 'badge', 'style' => 'background-color: #6c757d; color: white; font-size: 14px; padding: 6px 12px;']);
                            echo html_writer::end_tag('td');
                            
                            // Название
                            echo html_writer::start_tag('td');
                            echo htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8');
                            echo html_writer::end_tag('td');
                            
                            // Код
                            echo html_writer::start_tag('td');
                            echo $subjectcode ? htmlspecialchars($subjectcode, ENT_QUOTES, 'UTF-8') : '-';
                            echo html_writer::end_tag('td');
                            
                            // Краткое описание
                            echo html_writer::start_tag('td');
                            echo $subjectshortdesc ? htmlspecialchars(mb_substr($subjectshortdesc, 0, 50), ENT_QUOTES, 'UTF-8') . (mb_strlen($subjectshortdesc) > 50 ? '...' : '') : '-';
                            echo html_writer::end_tag('td');
                            
                            // Курсы
                            echo html_writer::start_tag('td');
                            if (!empty($subjectcourses)) {
                                echo html_writer::start_div('', ['style' => 'display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 5px;']);
                                foreach ($subjectcourses as $sc) {
                                    $coursename = htmlspecialchars($sc->shortname ?: $sc->fullname, ENT_QUOTES, 'UTF-8');
                                    $badgecontent = htmlspecialchars($coursename, ENT_QUOTES, 'UTF-8') . ' ' . 
                                        html_writer::link('#', '<i class="fas fa-times"></i>', [
                                            'class' => 'detach-course-from-subject-btn',
                                            'data-subject-id' => $subject->id,
                                            'data-course-id' => $sc->courseid,
                                            'style' => 'color: white; text-decoration: none; margin-left: 5px;',
                                            'title' => 'Открепить курс'
                                        ]);
                                    echo html_writer::tag('span', $badgecontent, [
                                        'class' => 'badge badge-secondary',
                                        'style' => 'display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; margin: 2px;'
                                    ]);
                                }
                                echo html_writer::end_div();
                            }
                            echo html_writer::link('#', '<i class="fas fa-plus"></i> Добавить курс', [
                                'class' => 'btn btn-sm btn-primary add-course-to-subject-btn',
                                'data-subject-id' => $subject->id,
                                'style' => 'font-size: 12px; padding: 4px 8px;'
                            ]);
                            echo html_writer::end_tag('td');
                            
                            echo html_writer::end_tag('tr');
                        }
                        
                        echo html_writer::end_tag('tbody');
                        echo html_writer::end_tag('table');
                        echo html_writer::end_div();
                    }
                    
                    // Модальное окно для добавления курса к предмету
                    echo html_writer::start_div('modal fade', [
                        'id' => 'addCourseToSubjectModal',
                        'tabindex' => '-1',
                        'role' => 'dialog'
                    ]);
                    echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
                    echo html_writer::start_div('modal-content');
                    echo html_writer::start_div('modal-header');
                    echo html_writer::tag('h5', 'Добавить курс к предмету', ['class' => 'modal-title']);
                    echo html_writer::start_tag('button', [
                        'type' => 'button',
                        'class' => 'close',
                        'data-dismiss' => 'modal',
                        'onclick' => 'jQuery(\'#addCourseToSubjectModal\').modal(\'hide\');'
                    ]);
                    echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
                    echo html_writer::end_tag('button');
                    echo html_writer::end_div();
                    echo html_writer::start_div('modal-body');
                    echo html_writer::start_div('form-group');
                    echo html_writer::label('Поиск курса', 'course-search-subject');
                    echo html_writer::empty_tag('input', [
                        'type' => 'text',
                        'id' => 'course-search-subject',
                        'class' => 'form-control',
                        'placeholder' => 'Введите название или код курса...'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::start_div('', ['id' => 'courses-list-subject', 'style' => 'max-height: 400px; overflow-y: auto;']);
                    echo html_writer::div('Введите текст для поиска курсов...', 'text-muted');
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    echo html_writer::start_div('modal-footer');
                    echo html_writer::start_tag('button', [
                        'type' => 'button',
                        'class' => 'btn btn-secondary',
                        'data-dismiss' => 'modal',
                        'onclick' => 'jQuery(\'#addCourseToSubjectModal\').modal(\'hide\');'
                    ]);
                    echo 'Закрыть';
                    echo html_writer::end_tag('button');
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    echo html_writer::end_div();
                    
                    // JavaScript для работы с курсами предметов
                    $PAGE->requires->js_init_code("
                        (function() {
                            var currentSubjectId = null;
                            var searchInput = document.getElementById('course-search-subject');
                            var coursesList = document.getElementById('courses-list-subject');
                            var searchTimeout;
                            
                            // Функция для загрузки курсов
                            function loadCourses(query) {
                                if (!currentSubjectId) return;
                                
                                var searchQuery = query || '';
                                if (searchQuery.length > 0 && searchQuery.length < 2) {
                                    coursesList.innerHTML = '<div class=\"text-muted\">Введите минимум 2 символа для поиска...</div>';
                                    return;
                                }
                                
                                var xhr = new XMLHttpRequest();
                                var url = '/local/deanpromoodle/pages/admin_ajax.php?action=getcourses&subjectid=' + currentSubjectId;
                                if (searchQuery.length >= 2) {
                                    url += '&search=' + encodeURIComponent(searchQuery);
                                }
                                // Если запрос пустой, не добавляем параметр search - сервер вернет все доступные курсы
                                
                                xhr.open('GET', url, true);
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4 && xhr.status === 200) {
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.success && response.courses && response.courses.length > 0) {
                                                var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>Код</th><th>Действие</th></tr></thead><tbody>';
                                                response.courses.forEach(function(course) {
                                                    html += '<tr><td>' + course.id + '</td><td>' + course.fullname + '</td><td>' + (course.shortname || '-') + '</td><td><button class=\"btn btn-sm btn-primary attach-course-to-subject-btn\" data-course-id=\"' + course.id + '\">Прикрепить</button></td></tr>';
                                                });
                                                html += '</tbody></table>';
                                                coursesList.innerHTML = html;
                                                
                                                // Обработчики кнопок прикрепления
                                                document.querySelectorAll('.attach-course-to-subject-btn').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        var courseId = this.getAttribute('data-course-id');
                                                        var xhr2 = new XMLHttpRequest();
                                                        xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                        xhr2.onreadystatechange = function() {
                                                            if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                                var response2 = JSON.parse(xhr2.responseText);
                                                                if (response2.success) {
                                                                    location.reload();
                                                                } else {
                                                                    alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                                }
                                                            }
                                                        };
                                                        xhr2.send('action=attachcoursetosubject&subjectid=' + currentSubjectId + '&courseid=' + courseId);
                                                    });
                                                });
                                            } else {
                                                coursesList.innerHTML = '<div class=\"alert alert-info\">' + (searchQuery.length >= 2 ? 'Курсы не найдены' : 'Введите текст для поиска курсов (минимум 2 символа)') + '</div>';
                                            }
                                        } catch (e) {
                                            coursesList.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа: ' + e.message + '</div>';
                                        }
                                    }
                                };
                                xhr.send();
                            }
                            
                            // Обработчик открытия модального окна
                            document.querySelectorAll('.add-course-to-subject-btn').forEach(function(btn) {
                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    currentSubjectId = this.getAttribute('data-subject-id');
                                    if (!currentSubjectId) {
                                        alert('Ошибка: не указан ID предмета');
                                        return;
                                    }
                                    
                                    if (!searchInput || !coursesList) {
                                        alert('Ошибка: элементы модального окна не найдены');
                                        return;
                                    }
                                    
                                    searchInput.value = '';
                                    coursesList.innerHTML = '<div class=\"text-muted\">Загрузка курсов...</div>';
                                    
                                    var modalElement = document.getElementById('addCourseToSubjectModal');
                                    if (!modalElement) {
                                        alert('Ошибка: модальное окно не найдено');
                                        return;
                                    }
                                    
                                    // Функция для загрузки курсов после открытия модального окна
                                    var loadCoursesAfterOpen = function() {
                                        setTimeout(function() {
                                            loadCourses('');
                                        }, 300);
                                    };
                                    
                                    if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                        jQuery(modalElement).off('shown.bs.modal'); // Удаляем предыдущие обработчики
                                        jQuery(modalElement).on('shown.bs.modal', loadCoursesAfterOpen);
                                        jQuery(modalElement).modal('show');
                                    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                        var modal = new bootstrap.Modal(modalElement);
                                        modalElement.addEventListener('shown.bs.modal', function handler() {
                                            modalElement.removeEventListener('shown.bs.modal', handler);
                                            loadCoursesAfterOpen();
                                        }, { once: true });
                                        modal.show();
                                    } else {
                                        // Fallback
                                        modalElement.style.display = 'block';
                                        modalElement.classList.add('show');
                                        document.body.classList.add('modal-open');
                                        loadCoursesAfterOpen();
                                    }
                                });
                            });
                            
                            // Поиск курсов
                            if (searchInput) {
                                searchInput.addEventListener('input', function() {
                                    clearTimeout(searchTimeout);
                                    var query = this.value.trim();
                                    
                                    if (!currentSubjectId) {
                                        coursesList.innerHTML = '<div class=\"alert alert-warning\">Ошибка: не выбран предмет</div>';
                                        return;
                                    }
                                    
                                    searchTimeout = setTimeout(function() {
                                        loadCourses(query);
                                    }, 500);
                                });
                            } else {
                                console.error('Элемент поиска курсов не найден');
                            }
                            
                            if (!coursesList) {
                                console.error('Элемент списка курсов не найден');
                            }
                            
                            // Обработчик открепления курса
                            document.querySelectorAll('.detach-course-from-subject-btn').forEach(function(btn) {
                                btn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    if (!confirm('Вы уверены, что хотите открепить этот курс от предмета?')) {
                                        return;
                                    }
                                    var subjectId = this.getAttribute('data-subject-id');
                                    var courseId = this.getAttribute('data-course-id');
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                    xhr.onreadystatechange = function() {
                                        if (xhr.readyState === 4 && xhr.status === 200) {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.success) {
                                                location.reload();
                                            } else {
                                                alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                            }
                                        }
                                    };
                                    xhr.send('action=detachcoursefromsubject&subjectid=' + subjectId + '&courseid=' + courseId);
                                });
                            });
                        })();
                    ");
                    
                    echo html_writer::end_div();
                }
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
            }
        } else if ($action == 'create' || ($action == 'edit' && $programid > 0)) {
            // Создание или редактирование программы
            $program = null;
            $isedit = ($action == 'edit' && $programid > 0);
            
            if ($isedit) {
                try {
                    $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
                    if (!$program) {
                        echo html_writer::div('Программа не найдена.', 'alert alert-danger');
                        break;
                    }
                } catch (\Exception $e) {
                    echo html_writer::div('Ошибка при получении программы: ' . $e->getMessage(), 'alert alert-danger');
                    break;
                }
            }
            
            // Обработка отправки формы
            $formsubmitted = optional_param('submit', 0, PARAM_INT);
            if ($formsubmitted) {
                $name = optional_param('name', '', PARAM_TEXT);
                $code = optional_param('code', '', PARAM_TEXT);
                $description = optional_param('description', '', PARAM_RAW);
                $institution = optional_param('institution', '', PARAM_TEXT);
                $visible = optional_param('visible', 1, PARAM_INT);
                $subjectsorder = optional_param('subjects_order', '', PARAM_TEXT);
                
                if (empty($name)) {
                    echo html_writer::div('Название программы обязательно для заполнения.', 'alert alert-danger');
                } else {
                    $transaction = $DB->start_delegated_transaction();
                    try {
                        $data = new stdClass();
                        $data->name = $name;
                        $data->code = $code;
                        $data->description = $description;
                        $data->institution = $institution;
                        $data->visible = $visible;
                        $data->timemodified = time();
                        
                        if ($isedit) {
                            $data->id = $programid;
                            $DB->update_record('local_deanpromoodle_programs', $data);
                            $programid = $data->id;
                        } else {
                            $data->timecreated = time();
                            $programid = $DB->insert_record('local_deanpromoodle_programs', $data);
                        }
                        
                        // Обрабатываем предметы из скрытого поля (формат: "relation_id:subject_id,relation_id:subject_id")
                        // relation_id может быть числом (существующая связь) или строкой вида "new_123" (новая связь)
                        if ($isedit) {
                            // Получаем текущие связи
                            $currentrelations = $DB->get_records('local_deanpromoodle_program_subjects', ['programid' => $programid]);
                            $currentrelationids = array_keys($currentrelations);
                            
                            // Парсим строку с порядком предметов
                            $subjectsarray = [];
                            if (!empty($subjectsorder)) {
                                $parts = explode(',', $subjectsorder);
                                foreach ($parts as $part) {
                                    $part = trim($part);
                                    if (empty($part)) continue;
                                    $subparts = explode(':', $part);
                                    if (count($subparts) == 2) {
                                        $subjectsarray[] = [
                                            'relation_id' => $subparts[0], // Может быть числом или строкой "new_XXX"
                                            'subject_id' => (int)$subparts[1]
                                        ];
                                    }
                                }
                            }
                            
                            // Обновляем порядок существующих связей и добавляем новые
                            $sortorder = 0;
                            $usedrelationids = [];
                            foreach ($subjectsarray as $item) {
                                $relationidstr = $item['relation_id'];
                                
                                // Проверяем, является ли это новой связью (начинается с "new_")
                                if (strpos($relationidstr, 'new_') === 0) {
                                    // Создаем новую связь
                                    $psdata = new stdClass();
                                    $psdata->programid = $programid;
                                    $psdata->subjectid = $item['subject_id'];
                                    $psdata->sortorder = $sortorder++;
                                    $psdata->timecreated = time();
                                    $psdata->timemodified = time();
                                    $DB->insert_record('local_deanpromoodle_program_subjects', $psdata);
                                } else {
                                    // Обновляем существующую связь
                                    $relationid = (int)$relationidstr;
                                    if ($relationid > 0 && in_array($relationid, $currentrelationids)) {
                                        $psdata = new stdClass();
                                        $psdata->id = $relationid;
                                        $psdata->sortorder = $sortorder++;
                                        $psdata->timemodified = time();
                                        $DB->update_record('local_deanpromoodle_program_subjects', $psdata);
                                        $usedrelationids[] = $relationid;
                                    }
                                }
                            }
                            
                            // Удаляем связи, которых нет в новом списке
                            $todelete = array_diff($currentrelationids, $usedrelationids);
                            if (!empty($todelete)) {
                                list($sql, $params) = $DB->get_in_or_equal($todelete);
                                $DB->delete_records_select('local_deanpromoodle_program_subjects', "id $sql", $params);
                            }
                        } else {
                            // При создании новой программы добавляем предметы
                            if (!empty($subjectsorder)) {
                                $parts = explode(',', $subjectsorder);
                                $sortorder = 0;
                                foreach ($parts as $part) {
                                    $part = trim($part);
                                    if (empty($part)) continue;
                                    $subparts = explode(':', $part);
                                    if (count($subparts) == 2 && (int)$subparts[1] > 0) {
                                        $psdata = new stdClass();
                                        $psdata->programid = $programid;
                                        $psdata->subjectid = (int)$subparts[1];
                                        $psdata->sortorder = $sortorder++;
                                        $psdata->timecreated = time();
                                        $psdata->timemodified = time();
                                        $DB->insert_record('local_deanpromoodle_program_subjects', $psdata);
                                    }
                                }
                            }
                        }
                        
                        $transaction->allow_commit();
                        
                        // Редирект на список программ
                        redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']));
                    } catch (\Exception $e) {
                        $transaction->rollback($e);
                        echo html_writer::div('Ошибка при сохранении: ' . $e->getMessage(), 'alert alert-danger');
                    }
                }
            }
            
            // Получаем предметы программы с порядком
            $programsubjects = [];
            $maxrelationid = 0;
            if ($isedit && $program) {
                try {
                    $programsubjects = $DB->get_records_sql(
                        "SELECT s.id, s.name, s.code, s.shortdescription, ps.sortorder, ps.id as relation_id
                         FROM {local_deanpromoodle_program_subjects} ps
                         JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
                         WHERE ps.programid = ?
                         ORDER BY ps.sortorder ASC",
                        [$programid]
                    );
                    // Вычисляем максимальный relation_id
                    if (!empty($programsubjects)) {
                        $relationids = [];
                        foreach ($programsubjects as $ps) {
                            $relationids[] = (int)$ps->relation_id;
                        }
                        $maxrelationid = !empty($relationids) ? max($relationids) : 0;
                    }
                } catch (\Exception $e) {
                    $programsubjects = [];
                    $maxrelationid = 0;
                }
            }
            
            // Отображение формы
            $formtitle = $isedit ? 'Редактировать программу' : 'Создать программу';
            echo html_writer::tag('h2', $formtitle, ['style' => 'margin-bottom: 20px;']);
            
            $formparams = [
                'tab' => 'programs',
                'action' => $action
            ];
            if ($isedit && $programid > 0) {
                $formparams['programid'] = $programid;
            }
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', $formparams),
                'style' => 'max-width: 800px;'
            ]);
            
            // Название программы *
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Название программы *', 'name');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'name',
                'id' => 'name',
                'class' => 'form-control',
                'value' => $program ? htmlspecialchars($program->name, ENT_QUOTES, 'UTF-8') : '',
                'required' => true
            ]);
            echo html_writer::end_div();
            
            // Код программы
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Код программы', 'code');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'code',
                'id' => 'code',
                'class' => 'form-control',
                'value' => $program ? htmlspecialchars($program->code ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Описание программы
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Описание программы', 'description');
            echo html_writer::start_tag('textarea', [
                'name' => 'description',
                'id' => 'description',
                'class' => 'form-control',
                'rows' => '5'
            ]);
            echo $program ? htmlspecialchars($program->description ?? '', ENT_QUOTES, 'UTF-8') : '';
            echo html_writer::end_tag('textarea');
            echo html_writer::end_div();
            
            // Учебное заведение
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Учебное заведение', 'institution');
            
            // Получаем все учебные заведения из БД
            $institutions = [];
            try {
                $institutions = $DB->get_records('local_deanpromoodle_institutions', ['visible' => 1], 'name ASC');
            } catch (\Exception $e) {
                // Если таблица не существует, используем пустой массив
                $institutions = [];
            }
            
            // Формируем массив для select
            $institutionoptions = ['' => '-- Выберите учебное заведение --'];
            foreach ($institutions as $inst) {
                $institutionoptions[$inst->name] = htmlspecialchars($inst->name, ENT_QUOTES, 'UTF-8');
            }
            
            // Определяем выбранное значение
            $selectedinstitution = '';
            if ($program && !empty($program->institution)) {
                // Если есть значение в БД, проверяем, существует ли оно в списке
                $selectedinstitution = htmlspecialchars($program->institution, ENT_QUOTES, 'UTF-8');
                // Если значение не найдено в списке, добавляем его
                if (!isset($institutionoptions[$selectedinstitution])) {
                    $institutionoptions[$selectedinstitution] = $selectedinstitution;
                }
            }
            
            echo html_writer::select(
                $institutionoptions,
                'institution',
                $selectedinstitution,
                false,
                ['class' => 'form-control', 'id' => 'institution']
            );
            echo html_writer::end_div();
            
            // Предметы программы
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;']);
            echo html_writer::label('Предметы программы', 'subjects', ['style' => 'margin: 0;']);
            echo html_writer::link('#', '<i class="fas fa-plus"></i> Добавить предмет', [
                'class' => 'btn btn-sm btn-success',
                'id' => 'add-subject-to-program-btn',
                'style' => 'text-decoration: none;'
            ]);
            echo html_writer::end_div();
            
            // Скрытое поле для хранения ID предметов в правильном порядке
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'subjects_order',
                'id' => 'subjects-order',
                'value' => ''
            ]);
            
            // Список предметов программы
            echo html_writer::start_div('program-subjects-list', [
                'id' => 'program-subjects-list',
                'style' => 'min-height: 100px; border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background: #f8f9fa;'
            ]);
            
            if (empty($programsubjects)) {
                echo html_writer::div('Предметы не добавлены. Нажмите "Добавить предмет" для добавления.', 'text-muted', ['style' => 'padding: 20px; text-align: center;']);
            } else {
                echo html_writer::start_tag('table', ['class' => 'table table-sm', 'style' => 'margin: 0; background: white;']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Порядок', ['style' => 'width: 80px; text-align: center;']);
                echo html_writer::tag('th', 'Название');
                echo html_writer::tag('th', 'Код', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Действия', ['style' => 'width: 150px; text-align: center;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody', ['id' => 'program-subjects-tbody']);
                
                foreach ($programsubjects as $subject) {
                    $subjectname = is_string($subject->name) ? $subject->name : (string)$subject->name;
                    $subjectcode = is_string($subject->code) ? $subject->code : '';
                    $sortorder = (int)$subject->sortorder;
                    $relationid = (int)$subject->relation_id;
                    
                    echo html_writer::start_tag('tr', [
                        'data-subject-id' => $subject->id,
                        'data-relation-id' => $relationid,
                        'data-sortorder' => $sortorder
                    ]);
                    
                    // Порядок
                    echo html_writer::start_tag('td', ['style' => 'text-align: center; vertical-align: middle;']);
                    echo html_writer::tag('span', $sortorder + 1, ['class' => 'badge', 'style' => 'background-color: #6c757d; color: white;']);
                    echo html_writer::end_tag('td');
                    
                    // Название
                    echo html_writer::start_tag('td');
                    echo htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8');
                    echo html_writer::end_tag('td');
                    
                    // Код
                    echo html_writer::start_tag('td');
                    echo $subjectcode ? htmlspecialchars($subjectcode, ENT_QUOTES, 'UTF-8') : '-';
                    echo html_writer::end_tag('td');
                    
                    // Действия
                    echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
                    echo html_writer::start_div('', ['style' => 'display: flex; gap: 5px; justify-content: center;']);
                    echo html_writer::link('#', '<i class="fas fa-arrow-up"></i>', [
                        'class' => 'btn btn-sm btn-outline-primary move-subject-up',
                        'title' => 'Вверх',
                        'data-relation-id' => $relationid,
                        'style' => 'padding: 4px 8px;'
                    ]);
                    echo html_writer::link('#', '<i class="fas fa-arrow-down"></i>', [
                        'class' => 'btn btn-sm btn-outline-primary move-subject-down',
                        'title' => 'Вниз',
                        'data-relation-id' => $relationid,
                        'style' => 'padding: 4px 8px;'
                    ]);
                    echo html_writer::link('#', '<i class="fas fa-times"></i>', [
                        'class' => 'btn btn-sm btn-outline-danger remove-subject',
                        'title' => 'Удалить',
                        'data-subject-id' => $subject->id,
                        'data-relation-id' => $relationid,
                        'style' => 'padding: 4px 8px;'
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_tag('td');
                    
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
            }
            
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Статус
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Статус', 'visible');
            echo html_writer::select(
                [1 => 'Активный', 0 => 'Скрыт'],
                'visible',
                $program ? (int)$program->visible : 1,
                false,
                ['class' => 'form-control']
            );
            echo html_writer::end_div();
            
            // Кнопки
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'submit',
                'value' => '1'
            ]);
            $submittext = $isedit ? 'Сохранить изменения' : 'Создать программу';
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => $submittext,
                'class' => 'btn btn-primary',
                'style' => 'margin-right: 10px;'
            ]);
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs']),
                'Отмена',
                ['class' => 'btn btn-secondary']
            );
            echo html_writer::end_div();
            
            echo html_writer::end_tag('form');
            
            // Модальное окно для добавления предметов
            echo html_writer::start_div('modal', [
                'id' => 'add-subject-modal',
                'style' => 'display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);'
            ]);
            echo html_writer::start_div('modal-content', [
                'style' => 'background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px;'
            ]);
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
            echo html_writer::tag('h3', 'Добавить предмет', ['style' => 'margin: 0;']);
            echo html_writer::link('#', '<i class="fas fa-times"></i>', [
                'class' => 'close-modal',
                'style' => 'font-size: 28px; font-weight: bold; color: #aaa; text-decoration: none; cursor: pointer;'
            ]);
            echo html_writer::end_div();
            
            // Поиск предметов
            echo html_writer::start_div('form-group');
            echo html_writer::label('Поиск предмета', 'subject-search');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => 'subject-search',
                'class' => 'form-control',
                'placeholder' => 'Введите название или код предмета...',
                'style' => 'margin-bottom: 15px;'
            ]);
            echo html_writer::end_div();
            
            // Список предметов
            echo html_writer::start_div('', [
                'id' => 'subjects-list-modal',
                'style' => 'max-height: 400px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px;'
            ]);
            echo html_writer::div('Загрузка предметов...', 'text-muted', ['style' => 'padding: 20px; text-align: center;']);
            echo html_writer::end_div();
            
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // JavaScript для работы с предметами
            echo html_writer::start_tag('script');
            echo "
            (function() {
                var programId = " . ($isedit && isset($programid) ? (int)$programid : 0) . ";
                var isEdit = " . ($isedit ? 'true' : 'false') . ";
                var nextRelationId = " . ((isset($maxrelationid) ? (int)$maxrelationid : 0) + 1) . ";
                
                // Функция обновления скрытого поля с порядком предметов
                function updateSubjectsOrder() {
                    var tbody = document.getElementById('program-subjects-tbody');
                    if (!tbody) {
                        document.getElementById('subjects-order').value = '';
                        return;
                    }
                    
                    var rows = tbody.querySelectorAll('tr');
                    var order = [];
                    rows.forEach(function(row, index) {
                        var relationId = row.getAttribute('data-relation-id');
                        var subjectId = row.getAttribute('data-subject-id');
                        if (relationId && subjectId) {
                            order.push(relationId + ':' + subjectId);
                        }
                    });
                    document.getElementById('subjects-order').value = order.join(',');
                }
                
                // Функция обновления номеров порядка в таблице
                function updateOrderNumbers() {
                    var tbody = document.getElementById('program-subjects-tbody');
                    if (!tbody) return;
                    
                    var rows = tbody.querySelectorAll('tr');
                    rows.forEach(function(row, index) {
                        var badge = row.querySelector('.badge');
                        if (badge) {
                            badge.textContent = index + 1;
                        }
                        row.setAttribute('data-sortorder', index);
                    });
                    updateSubjectsOrder();
                }
                
                // Функция добавления предмета в таблицу
                function addSubjectToTable(subject) {
                    var tbody = document.getElementById('program-subjects-tbody');
                    var listDiv = document.getElementById('program-subjects-list');
                    
                    // Если таблицы нет, создаем её
                    if (!tbody) {
                        listDiv.innerHTML = '';
                        var table = document.createElement('table');
                        table.className = 'table table-sm';
                        table.style.margin = '0';
                        table.style.background = 'white';
                        
                        var thead = document.createElement('thead');
                        var headerRow = document.createElement('tr');
                        ['Порядок', 'Название', 'Код', 'Действия'].forEach(function(text) {
                            var th = document.createElement('th');
                            th.textContent = text;
                            if (text === 'Порядок' || text === 'Действия') {
                                th.style.textAlign = 'center';
                                th.style.width = text === 'Порядок' ? '80px' : '150px';
                            } else if (text === 'Код') {
                                th.style.width = '150px';
                            }
                            headerRow.appendChild(th);
                        });
                        thead.appendChild(headerRow);
                        table.appendChild(thead);
                        
                        tbody = document.createElement('tbody');
                        tbody.id = 'program-subjects-tbody';
                        table.appendChild(tbody);
                        listDiv.appendChild(table);
                    }
                    
                    // Проверяем, не добавлен ли уже этот предмет
                    var existingRow = tbody.querySelector('tr[data-subject-id=\"' + subject.id + '\"]');
                    if (existingRow) {
                        alert('Этот предмет уже добавлен в программу');
                        return;
                    }
                    
                    // Создаем новую строку
                    var row = document.createElement('tr');
                    var relationId = isEdit && subject.relation_id ? subject.relation_id : ('new_' + (nextRelationId++));
                    row.setAttribute('data-subject-id', subject.id);
                    row.setAttribute('data-relation-id', relationId);
                    row.setAttribute('data-sortorder', tbody.querySelectorAll('tr').length);
                    
                    // Порядок
                    var tdOrder = document.createElement('td');
                    tdOrder.style.textAlign = 'center';
                    tdOrder.style.verticalAlign = 'middle';
                    var badge = document.createElement('span');
                    badge.className = 'badge';
                    badge.style.cssText = 'background-color: #6c757d; color: white;';
                    badge.textContent = tbody.querySelectorAll('tr').length + 1;
                    tdOrder.appendChild(badge);
                    row.appendChild(tdOrder);
                    
                    // Название
                    var tdName = document.createElement('td');
                    tdName.textContent = subject.name;
                    row.appendChild(tdName);
                    
                    // Код
                    var tdCode = document.createElement('td');
                    tdCode.textContent = subject.code || '-';
                    row.appendChild(tdCode);
                    
                    // Действия
                    var tdActions = document.createElement('td');
                    tdActions.style.textAlign = 'center';
                    var actionsDiv = document.createElement('div');
                    actionsDiv.style.cssText = 'display: flex; gap: 5px; justify-content: center;';
                    
                    var btnUp = document.createElement('a');
                    btnUp.href = '#';
                    btnUp.className = 'btn btn-sm btn-outline-primary move-subject-up';
                    btnUp.title = 'Up';
                    btnUp.setAttribute('data-relation-id', relationId);
                    btnUp.style.padding = '4px 8px';
                    btnUp.innerHTML = '<i class=\"fas fa-arrow-up\"></i>';
                    actionsDiv.appendChild(btnUp);
                    
                    var btnDown = document.createElement('a');
                    btnDown.href = '#';
                    btnDown.className = 'btn btn-sm btn-outline-primary move-subject-down';
                    btnDown.title = 'Down';
                    btnDown.setAttribute('data-relation-id', relationId);
                    btnDown.style.padding = '4px 8px';
                    btnDown.innerHTML = '<i class=\"fas fa-arrow-down\"></i>';
                    actionsDiv.appendChild(btnDown);
                    
                    var btnRemove = document.createElement('a');
                    btnRemove.href = '#';
                    btnRemove.className = 'btn btn-sm btn-outline-danger remove-subject';
                    btnRemove.title = 'Remove';
                    btnRemove.setAttribute('data-subject-id', subject.id);
                    btnRemove.setAttribute('data-relation-id', relationId);
                    btnRemove.style.padding = '4px 8px';
                    btnRemove.innerHTML = '<i class=\"fas fa-times\"></i>';
                    actionsDiv.appendChild(btnRemove);
                    
                    tdActions.appendChild(actionsDiv);
                    row.appendChild(tdActions);
                    
                    tbody.appendChild(row);
                    updateOrderNumbers();
                }
                
                // Handler for Add Subject button
                var addSubjectBtn = document.getElementById('add-subject-to-program-btn');
                if (addSubjectBtn) {
                    addSubjectBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('add-subject-modal').style.display = 'block';
                        loadSubjectsList();
                    });
                }
                
                // Закрытие модального окна
                var modal = document.getElementById('add-subject-modal');
                var closeBtn = modal.querySelector('.close-modal');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                window.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
                
                // Загрузка списка предметов
                function loadSubjectsList(search) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    displaySubjectsList(response.subjects || []);
                                } else {
                                    document.getElementById('subjects-list-modal').innerHTML = '<div class=\"text-muted\" style=\"padding: 20px; text-align: center;\">Ошибка: ' + (response.error || 'Неизвестная ошибка') + '</div>';
                                }
                            } catch (e) {
                                document.getElementById('subjects-list-modal').innerHTML = '<div class=\"text-muted\" style=\"padding: 20px; text-align: center;\">Ошибка при обработке ответа</div>';
                            }
                        }
                    };
                    var params = 'action=getsubjects';
                    if (programId > 0) {
                        params += '&programid=' + programId;
                    }
                    if (search) {
                        params += '&search=' + encodeURIComponent(search);
                    }
                    xhr.send(params);
                }
                
                // Отображение списка предметов в модальном окне
                function displaySubjectsList(subjects) {
                    var container = document.getElementById('subjects-list-modal');
                    if (!subjects || subjects.length === 0) {
                        container.innerHTML = '<div class=\"text-muted\" style=\"padding: 20px; text-align: center;\">Предметы не найдены</div>';
                        return;
                    }
                    
                    // Получаем уже добавленные предметы
                    var tbody = document.getElementById('program-subjects-tbody');
                    var addedSubjectIds = [];
                    if (tbody) {
                        var rows = tbody.querySelectorAll('tr');
                        rows.forEach(function(row) {
                            addedSubjectIds.push(parseInt(row.getAttribute('data-subject-id')));
                        });
                    }
                    
                    var html = '';
                    subjects.forEach(function(subject) {
                        var isAdded = addedSubjectIds.indexOf(parseInt(subject.id)) !== -1;
                        html += '<div class=\"subject-item\" style=\"padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; ' + (isAdded ? 'opacity: 0.5;' : '') + '\" data-subject-id=\"' + subject.id + '\">';
                        html += '<strong>' + escapeHtml(subject.name) + '</strong>';
                        if (subject.code) {
                            html += ' <span style=\"color: #666;\">(' + escapeHtml(subject.code) + ')</span>';
                        }
                        if (isAdded) {
                            html += ' <span style=\"color: #28a745;\">(уже добавлен)</span>';
                        }
                        html += '</div>';
                    });
                    container.innerHTML = html;
                    
                    // Обработчики клика на предметы
                    container.querySelectorAll('.subject-item').forEach(function(item) {
                        if (item.style.opacity !== '0.5') {
                            item.addEventListener('click', function() {
                                var subjectId = parseInt(this.getAttribute('data-subject-id'));
                                var subject = subjects.find(function(s) { return parseInt(s.id) === subjectId; });
                                if (subject) {
                                    addSubjectToTable(subject);
                                    modal.style.display = 'none';
                                }
                            });
                        }
                    });
                }
                
                // Поиск предметов
                var searchInput = document.getElementById('subject-search');
                if (searchInput) {
                    var searchTimeout;
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        var search = this.value.trim();
                        searchTimeout = setTimeout(function() {
                            loadSubjectsList(search);
                        }, 300);
                    });
                }
                
                // Обработчики изменения порядка и удаления
                var tbody = document.getElementById('program-subjects-tbody');
                if (tbody) {
                    tbody.addEventListener('click', function(e) {
                        var target = e.target.closest('.move-subject-up, .move-subject-down, .remove-subject');
                        if (!target) return;
                        
                        e.preventDefault();
                        var row = target.closest('tr');
                        if (!row) return;
                        
                        if (target.classList.contains('remove-subject')) {
                            // Удаление предмета
                            row.remove();
                            updateOrderNumbers();
                        } else {
                            // Изменение порядка
                            var isUp = target.classList.contains('move-subject-up');
                            var siblingRow = isUp ? row.previousElementSibling : row.nextElementSibling;
                            if (!siblingRow) return;
                            
                            if (isUp) {
                                tbody.insertBefore(row, siblingRow);
                            } else {
                                tbody.insertBefore(siblingRow, row.nextSibling);
                            }
                            updateOrderNumbers();
                        }
                    });
                }
                
                // Инициализация порядка при загрузке страницы
                updateSubjectsOrder();
                
                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
            })();
            ";
            echo html_writer::end_tag('script');
            
        } else {
            // Список программ
            // Заголовок с кнопками
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
            echo html_writer::start_div('', ['style' => 'display: flex; align-items: center; gap: 10px;']);
            echo html_writer::tag('i', '', ['class' => 'fas fa-clipboard-list', 'style' => 'font-size: 24px;']);
            echo html_writer::tag('h2', 'Программы', ['style' => 'margin: 0; font-size: 24px; font-weight: 600;']);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['style' => 'display: flex; gap: 10px;']);
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'create']),
                '+ Добавить программу',
                [
                    'class' => 'btn btn-primary',
                    'style' => 'background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
                ]
            );
            echo html_writer::link('#', '<i class="fas fa-file-import"></i> Импорт из JSON', [
                'class' => 'btn btn-success',
                'id' => 'import-programs-json-btn',
                'style' => 'background-color: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
            ]);
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Модальное окно для импорта JSON
            echo html_writer::start_div('modal fade', [
                'id' => 'importProgramsJsonModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Импорт программ из JSON', ['class' => 'modal-title']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#importProgramsJsonModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'import' => 'json']),
                'enctype' => 'multipart/form-data'
            ]);
            echo html_writer::start_div('form-group');
            echo html_writer::label('Выберите JSON файл', 'jsonfile');
            echo html_writer::empty_tag('input', [
                'type' => 'file',
                'name' => 'jsonfile',
                'id' => 'jsonfile-programs',
                'class' => 'form-control-file',
                'accept' => '.json,application/json',
                'required' => true
            ]);
            echo html_writer::start_div('form-text text-muted', ['style' => 'margin-top: 5px;']);
            echo 'Формат JSON файла:<br>';
            echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 11px; margin-top: 5px; max-height: 200px; overflow-y: auto;">';
            echo '{<br>';
            echo '  "programs": [<br>';
            echo '    {<br>';
            echo '      "name": "Название программы",<br>';
            echo '      "code": "КОД",<br>';
            echo '      "description": "Описание",<br>';
            echo '      "is_active": true,<br>';
            echo '      "subjects": [<br>';
            echo '        {<br>';
            echo '          "name": "Название предмета",<br>';
            echo '          "code": "КОД",<br>';
            echo '          "order": 0<br>';
            echo '        }<br>';
            echo '      ]<br>';
            echo '    }<br>';
            echo '  ]<br>';
            echo '}</pre>';
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'import_submit',
                'value' => '1'
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => 'Импортировать',
                'class' => 'btn btn-success',
                'style' => 'margin-right: 10px;'
            ]);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#importProgramsJsonModal\').modal(\'hide\');'
            ]);
            echo 'Отмена';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Получение всех программ из таблицы local_deanpromoodle_programs
            $programs = [];
            try {
                $programs = $DB->get_records('local_deanpromoodle_programs', null, 'name ASC');
            } catch (\dml_exception $e) {
                echo html_writer::div('Ошибка при получении программ из БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
                echo html_writer::end_div();
                break;
            } catch (\Exception $e) {
                echo html_writer::div('Ошибка при получении программ из БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
                echo html_writer::end_div();
                break;
            }
            
            // Инициализируем переменные для JavaScript заранее
            $programsjson = '[]';
            $programsoptions = [];
            
            if (empty($programs)) {
                echo html_writer::div('Программы не найдены. Создайте первую программу.', 'alert alert-info');
            } else {
                // Получаем название сайта как учебное заведение
                $sitename = $CFG->fullname ?: 'Московская богословская семинария';
                
                // Подготовка данных для каждой программы
                $programsdata = [];
                foreach ($programs as $program) {
                    // Подсчет связанных предметов
                    try {
                        $subjectscount = $DB->count_records('local_deanpromoodle_program_subjects', ['programid' => $program->id]);
                    } catch (\Exception $e) {
                        $subjectscount = 0;
                    }
                    
                    // Подсчет связанных когорт
                    try {
                        $cohortscount = $DB->count_records('local_deanpromoodle_program_cohorts', ['programid' => $program->id]);
                    } catch (\Exception $e) {
                        $cohortscount = 0;
                    }
                    
                    // Получаем список предметов для отображения
                    try {
                        $subjects = $DB->get_records_sql(
                            "SELECT s.name, s.code
                             FROM {local_deanpromoodle_program_subjects} ps
                             JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
                             WHERE ps.programid = ?
                             ORDER BY ps.sortorder ASC
                             LIMIT 3",
                            [$program->id]
                        );
                    } catch (\Exception $e) {
                        $subjects = [];
                    }
                    $subjectslist = [];
                    foreach ($subjects as $s) {
                        $subjectslist[] = htmlspecialchars($s->name, ENT_QUOTES, 'UTF-8');
                    }
                    if ($subjectscount > 3) {
                        $subjectslist[] = '...';
                    }
                    
                    // Получаем учебное заведение из БД или используем название сайта по умолчанию
                    $institution = !empty($program->institution) ? $program->institution : $sitename;
                    
                    $programsdata[] = (object)[
                        'id' => $program->id,
                        'name' => $program->name,
                        'code' => $program->code ?? '',
                        'categoryname' => $institution,
                        'subjectscount' => $subjectscount,
                        'cohortscount' => $cohortscount,
                        'subjectslist' => implode(', ', $subjectslist),
                        'visible' => $program->visible
                    ];
                }
                
                // Поле поиска
                echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 20px;']);
                echo html_writer::label('Поиск программ:', 'program-search-input');
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'id' => 'program-search-input',
                    'class' => 'form-control',
                    'placeholder' => 'Введите ID, название или код программы для поиска...',
                    'style' => 'max-width: 500px;'
                ]);
                echo html_writer::end_div();
                
                // Стили для таблицы
            echo html_writer::start_tag('style');
            echo "
                .programs-table {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .programs-table table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .programs-table th {
                    background-color: #f8f9fa;
                    padding: 12px 16px;
                    text-align: left;
                    font-weight: 600;
                    color: #495057;
                    border-bottom: 1px solid #dee2e6;
                    font-size: 14px;
                }
                .programs-table td {
                    padding: 16px;
                    border-bottom: 1px solid #f0f0f0;
                    vertical-align: middle;
                }
                .programs-table th:last-child,
                .programs-table td:last-child {
                    min-width: 180px;
                    width: 180px;
                    padding-left: 20px;
                    padding-right: 20px;
                }
                .programs-table th:first-child,
                .programs-table td:first-child {
                    width: 60px;
                    min-width: 60px;
                    max-width: 60px;
                    text-align: center;
                }
                .programs-table th:nth-child(3),
                .programs-table td:nth-child(3) {
                    width: auto;
                    max-width: 160px;
                }
                .programs-table td:nth-child(3) .badge-institution {
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    display: inline-block;
                }
                .programs-table tr:hover {
                    background-color: #f8f9fa;
                }
                .program-id-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background-color: #6c757d;
                    color: white;
                    font-weight: 600;
                    font-size: 14px;
                }
                .course-name-cell {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .course-name-full {
                    font-weight: 500;
                    color: #212529;
                }
                .course-name-short {
                    font-size: 13px;
                    color: #6c757d;
                }
                .badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 500;
                    white-space: nowrap;
                }
                .badge-institution {
                    background-color: #e3f2fd;
                    color: #1976d2;
                }
                .badge-group {
                    background-color: #2196f3;
                    color: white;
                    margin-right: 6px;
                }
                .badge-student {
                    background-color: #424242;
                    color: white;
                }
                .badge-free {
                    background-color: #4caf50;
                    color: white;
                }
                .badge-active {
                    background-color: #4caf50;
                    color: white;
                }
                .badge i.fas,
                .badge i.far,
                .badge i.fab {
                    margin-right: 4px;
                }
                .btn i.fas,
                .btn i.far,
                .btn i.fab {
                    margin-right: 6px;
                }
            ";
            echo html_writer::end_tag('style');
            
                // Отображение таблицы
                echo html_writer::start_div('programs-table');
                echo html_writer::start_tag('table', [
                    'id' => 'programs-table'
                ]);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', '<span class="sortable" data-column="id">ID</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'id'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="name">Название курса</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'name'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="categoryname">Учебное заведение</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'categoryname'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="subjectscount">Связи</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'subjectscount'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="visible">Статус</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'visible'
                ]);
                echo html_writer::tag('th', 'Действия');
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                foreach ($programsdata as $program) {
                    // Безопасное преобразование всех значений
                    $programid = (int)$program->id;
                    $programname = is_string($program->name) ? $program->name : (string)$program->name;
                    $programcode = is_string($program->code) ? $program->code : (string)$program->code;
                    $programcategoryname = is_string($program->categoryname) ? $program->categoryname : (string)$program->categoryname;
                    $programsubjectscount = (int)$program->subjectscount;
                    $programcohortscount = (int)$program->cohortscount;
                    $programsubjectslist = is_string($program->subjectslist) ? $program->subjectslist : '';
                    $programvisible = (bool)$program->visible;
                    
                    // Подготовка текста для поиска
                    $searchText = mb_strtolower($programid . ' ' . $programname . ' ' . ($programcode ?? ''));
                    $searchText = htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8');
                    
                    echo html_writer::start_tag('tr', [
                        'data-id' => $programid,
                        'data-name' => htmlspecialchars(mb_strtolower($programname), ENT_QUOTES, 'UTF-8'),
                        'data-code' => htmlspecialchars(mb_strtolower($programcode ?? ''), ENT_QUOTES, 'UTF-8'),
                        'data-categoryname' => htmlspecialchars(mb_strtolower($institution), ENT_QUOTES, 'UTF-8'),
                        'data-subjectscount' => $programsubjectscount,
                        'data-cohortscount' => $programcohortscount,
                        'data-visible' => $programvisible ? '1' : '0',
                        'data-search-text' => $searchText
                    ]);
                    
                    // ID
                    echo html_writer::start_tag('td');
                    echo htmlspecialchars((string)$programid, ENT_QUOTES, 'UTF-8');
                    echo html_writer::end_tag('td');
                    
                    // Название программы
                    echo html_writer::start_tag('td');
                    echo html_writer::start_div('course-name-cell');
                    echo '<div class="course-name-full">' . htmlspecialchars($programname, ENT_QUOTES, 'UTF-8') . '</div>';
                    if ($programcode) {
                        echo '<div class="course-name-short">' . htmlspecialchars($programcode, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    if ($programsubjectslist) {
                        echo '<div class="course-name-short" style="font-size: 11px; color: #999; margin-top: 4px;">Предметы: ' . $programsubjectslist . '</div>';
                    }
                    echo html_writer::end_div();
                    echo html_writer::end_tag('td');
                    
                    // Учебное заведение
                    echo html_writer::start_tag('td');
                    echo '<span class="badge badge-institution">' . htmlspecialchars($programcategoryname, ENT_QUOTES, 'UTF-8') . '</span>';
                    echo html_writer::end_tag('td');
                    
                    // Связи
                    echo html_writer::start_tag('td');
                    $relations = [];
                    if ($programsubjectscount > 0) {
                        $relations[] = '<span class="badge badge-group"><i class="fas fa-book"></i> ' . $programsubjectscount . ' предмет' . ($programsubjectscount > 1 ? 'ов' : '') . '</span>';
                    }
                    if ($programcohortscount > 0) {
                        $relations[] = '<span class="badge badge-student view-program-cohorts" style="cursor: pointer;" data-program-id="' . $programid . '"><i class="fas fa-users"></i> ' . $programcohortscount . ' группа' . ($programcohortscount > 1 ? '' : 'а') . '</span>';
                    }
                    if (empty($relations)) {
                        echo '-';
                    } else {
                        echo implode(' ', $relations);
                    }
                    echo html_writer::end_tag('td');
                    
                    // Статус
                    echo html_writer::start_tag('td');
                    if ($programvisible) {
                        echo '<span class="badge badge-active"><i class="fas fa-check"></i> Активный</span>';
                    } else {
                        echo '<span class="badge" style="background-color: #9e9e9e; color: white;">Скрыт</span>';
                    }
                    echo html_writer::end_tag('td');
                    
                    // Действия
                    echo html_writer::start_tag('td');
                    echo html_writer::start_div('action-buttons');
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'view', 'programid' => $programid]),
                        '<i class="fas fa-eye"></i>',
                        [
                            'class' => 'action-btn action-btn-view',
                            'title' => 'Просмотр'
                        ]
                    );
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'edit', 'programid' => $programid]),
                        '<i class="fas fa-edit"></i>',
                        [
                            'class' => 'action-btn action-btn-edit',
                            'title' => 'Редактировать'
                        ]
                    );
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'copy', 'programid' => $programid]),
                        '<i class="fas fa-copy"></i>',
                        [
                            'class' => 'action-btn action-btn-copy',
                            'title' => 'Копировать'
                        ]
                    );
                    echo html_writer::link('#', '<i class="fas fa-link"></i>', [
                        'class' => 'action-btn action-btn-link attach-cohort-to-program',
                        'title' => 'Прикрепить группу',
                        'data-program-id' => $programid,
                        'data-program-name' => htmlspecialchars($programname, ENT_QUOTES, 'UTF-8')
                    ]);
                    echo html_writer::link('#', '<i class="fas fa-times"></i>', [
                        'class' => 'action-btn action-btn-delete delete-program',
                        'title' => 'Удалить',
                        'data-program-id' => $programid
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_tag('td');
                    
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                echo html_writer::end_div();
                
                // JavaScript для сортировки и поиска программ
                $PAGE->requires->js_init_code("
                    (function() {
                        var table = document.getElementById('programs-table');
                        if (!table) return;
                        
                        var tbody = table.querySelector('tbody');
                        if (!tbody) return;
                        
                        var searchInput = document.getElementById('program-search-input');
                        var rows = Array.from(tbody.querySelectorAll('tr'));
                        var currentSort = {
                            column: null,
                            direction: 'asc'
                        };
                        
                        // Функция сортировки
                        function sortTable(column) {
                            var isNumeric = ['id', 'subjectscount', 'cohortscount', 'visible'].includes(column);
                            var direction = currentSort.column === column && currentSort.direction === 'asc' ? 'desc' : 'asc';
                            
                            currentSort.column = column;
                            currentSort.direction = direction;
                            
                            rows.sort(function(a, b) {
                                var aVal, bVal;
                                
                                if (isNumeric) {
                                    aVal = parseFloat(a.getAttribute('data-' + column)) || 0;
                                    bVal = parseFloat(b.getAttribute('data-' + column)) || 0;
                                } else {
                                    aVal = (a.getAttribute('data-' + column) || '').toLowerCase();
                                    bVal = (b.getAttribute('data-' + column) || '').toLowerCase();
                                }
                                
                                if (isNumeric) {
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                } else {
                                    if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                                    if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                                    return 0;
                                }
                            });
                            
                            // Обновляем иконки сортировки
                            var headers = table.querySelectorAll('.sortable-header');
                            headers.forEach(function(header) {
                                var icon = header.querySelector('.sort-icon');
                                if (icon) {
                                    if (header.getAttribute('data-column') === column) {
                                        icon.className = 'fas fa-sort-' + (direction === 'asc' ? 'up' : 'down') + ' sort-icon';
                                        icon.style.color = '#007bff';
                                    } else {
                                        icon.className = 'fas fa-sort sort-icon';
                                        icon.style.color = '#999';
                                    }
                                }
                            });
                            
                            // Перерисовываем таблицу
                            rows.forEach(function(row) {
                                tbody.appendChild(row);
                            });
                        }
                        
                        // Функция поиска
                        function filterTable() {
                            var searchText = (searchInput.value || '').toLowerCase().trim();
                            
                            rows.forEach(function(row) {
                                if (!searchText) {
                                    row.style.display = '';
                                    return;
                                }
                                
                                var searchData = row.getAttribute('data-search-text') || '';
                                if (searchData.indexOf(searchText) !== -1) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        }
                        
                        // Обработчики кликов на заголовки
                        var headers = table.querySelectorAll('.sortable-header');
                        headers.forEach(function(header) {
                            header.addEventListener('click', function() {
                                var column = header.getAttribute('data-column');
                                if (column) {
                                    sortTable(column);
                                }
                            });
                        });
                        
                        // Обработчик поиска
                        if (searchInput) {
                            searchInput.addEventListener('input', function() {
                                filterTable();
                            });
                        }
                        
                        // Стили для сортируемых заголовков
                        var style = document.createElement('style');
                        style.textContent = '.sortable-header { cursor: pointer; user-select: none; } .sortable-header:hover { background-color: #e9ecef !important; } .sort-icon { margin-left: 5px; color: #999; font-size: 12px; }';
                        document.head.appendChild(style);
                    })();
                ");
            }
            
            // Модальное окно для прикрепления когорт к программе
            // Обновляем $programsjson и $programsoptions если есть программы
            if (!empty($programs)) {
                $programsjson = json_encode(array_map(function($p) {
                    return ['id' => $p->id, 'name' => $p->name];
                }, $programs));
                $programsoptions = array_map(function($p) { 
                    return htmlspecialchars($p->name, ENT_QUOTES, 'UTF-8'); 
                }, $programs);
            }
            
            // Стили для модального окна
            echo html_writer::start_tag('style');
            echo "
                #attachCohortModal .modal-header {
                    position: relative;
                    padding: 15px 20px;
                    border-bottom: 1px solid #dee2e6;
                }
                #attachCohortModal .modal-header .close {
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
                #attachCohortModal .modal-header .close:hover {
                    opacity: 0.75;
                }
                #attachCohortModal .modal-header .close span {
                    display: block;
                }
            ";
            echo html_writer::end_tag('style');
            
            echo html_writer::start_div('modal fade', [
                'id' => 'attachCohortModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Прикрепить глобальную группу к программе', ['class' => 'modal-title']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'aria-label' => 'Закрыть',
                'onclick' => 'jQuery(\'#attachCohortModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('form-group', ['id' => 'program-select-group']);
            echo html_writer::label('Программа', 'program-select');
            echo html_writer::tag('div', '<strong id="selected-program-name">Выберите программу</strong>', [
                'id' => 'selected-program-display',
                'class' => 'form-control',
                'style' => 'padding: 8px 12px; background-color: #f8f9fa; border: 1px solid #ced4da; border-radius: 4px;'
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'id' => 'program-select',
                'name' => 'program-select',
                'value' => ''
            ]);
            echo html_writer::end_div();
            echo html_writer::start_div('form-group');
            echo html_writer::label('Поиск когорты', 'cohort-search');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => 'cohort-search',
                'class' => 'form-control',
                'placeholder' => 'Введите название когорты...'
            ]);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['id' => 'cohorts-list', 'style' => 'max-height: 400px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background: #f8f9fa;']);
            echo html_writer::div('Загрузка списка когорт...', 'text-muted');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('modal-footer');
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#attachCohortModal\').modal(\'hide\');'
            ]);
            echo 'Закрыть';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Модальное окно для просмотра прикрепленных групп программы
            // Стили для модального окна просмотра групп программы
            echo html_writer::start_tag('style');
            echo "
                #viewProgramCohortsModal .modal-header {
                    position: relative;
                    padding: 15px 20px;
                    border-bottom: 1px solid #dee2e6;
                }
                #viewProgramCohortsModal .modal-header .close {
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
                #viewProgramCohortsModal .modal-header .close:hover {
                    opacity: 0.75;
                }
                #viewProgramCohortsModal .modal-header .close span {
                    display: block;
                }
            ";
            echo html_writer::end_tag('style');
            
            echo html_writer::start_div('modal fade', [
                'id' => 'viewProgramCohortsModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Глобальные группы программы', ['class' => 'modal-title']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'aria-label' => 'Закрыть',
                'onclick' => 'jQuery(\'#viewProgramCohortsModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('', ['id' => 'program-cohorts-list', 'style' => 'max-height: 400px; overflow-y: auto;']);
            echo html_writer::div('Загрузка групп...', 'text-muted');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('modal-footer');
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#viewProgramCohortsModal\').modal(\'hide\');'
            ]);
            echo 'Закрыть';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            $PAGE->requires->js_init_code("
                (function() {
                    var programs = " . $programsjson . ";
                    var currentProgramId = null;
                    
                    // Обработчик открытия модального окна при клике на кнопку в строке программы
                    document.querySelectorAll('.attach-cohort-to-program').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            var programId = this.getAttribute('data-program-id');
                            var programName = this.getAttribute('data-program-name');
                            
                            if (!programId) return;
                            
                            // Устанавливаем выбранную программу
                            currentProgramId = programId;
                            document.getElementById('program-select').value = programId;
                            document.getElementById('selected-program-name').textContent = programName;
                            
                            // Очищаем поиск
                            document.getElementById('cohort-search').value = '';
                            
                            // Загружаем все когорты при открытии модального окна
                            var cohortsList = document.getElementById('cohorts-list');
                            cohortsList.innerHTML = '<div class=\"text-muted\">Загрузка списка когорт...</div>';
                            
                            // Загружаем все когорты
                            loadCohortsList('', programId);
                            
                            // Показываем модальное окно
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery('#attachCohortModal').modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(document.getElementById('attachCohortModal'));
                                modal.show();
                            }
                        });
                    });
                    
                    // Функция загрузки списка когорт
                    function loadCohortsList(search, programId) {
                        var cohortsList = document.getElementById('cohorts-list');
                        if (!cohortsList) return;
                        
                        var url = '/local/deanpromoodle/pages/admin_ajax.php?action=getcohorts&programid=' + programId;
                        if (search && search.trim().length >= 2) {
                            url += '&search=' + encodeURIComponent(search.trim());
                        }
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', url, true);
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success && response.cohorts) {
                                            if (response.cohorts.length > 0) {
                                                var html = '<table class=\"table table-striped table-hover\" style=\"margin: 0; background: white;\"><thead><tr><th style=\"width: 60px;\">ID</th><th>Название</th><th style=\"width: 150px;\">ID Number</th><th style=\"width: 120px;\">Действие</th></tr></thead><tbody>';
                                                response.cohorts.forEach(function(cohort) {
                                                    html += '<tr>';
                                                    html += '<td>' + cohort.id + '</td>';
                                                    html += '<td>' + escapeHtml(cohort.name) + '</td>';
                                                    html += '<td>' + (cohort.idnumber ? escapeHtml(cohort.idnumber) : '-') + '</td>';
                                                    html += '<td><button class=\"btn btn-sm btn-primary attach-cohort-btn\" data-cohort-id=\"' + cohort.id + '\" title=\"Прикрепить группу\" style=\"padding: 4px 8px; font-size: 12px;\"><i class=\"fas fa-link\"></i></button></td>';
                                                    html += '</tr>';
                                                });
                                                html += '</tbody></table>';
                                                cohortsList.innerHTML = html;
                                                
                                                // Обработчики кнопок прикрепления
                                                document.querySelectorAll('.attach-cohort-btn').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        var cohortId = this.getAttribute('data-cohort-id');
                                                        var btn = this;
                                                        btn.disabled = true;
                                                        btn.innerHTML = '<i class=\"fas fa-spinner fa-spin\"></i>';
                                                        btn.title = 'Прикрепление...';
                                                        
                                                        var xhr2 = new XMLHttpRequest();
                                                        xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                        xhr2.onreadystatechange = function() {
                                                            if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                                var response2 = JSON.parse(xhr2.responseText);
                                                                if (response2.success) {
                                                                    alert('Группа успешно прикреплена к программе');
                                                                    location.reload();
                                                                } else {
                                                                    alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                                    btn.disabled = false;
                                                                    btn.innerHTML = '<i class=\"fas fa-link\"></i>';
                                                                    btn.title = 'Прикрепить группу';
                                                                }
                                                            }
                                                        };
                                                        xhr2.send('action=attachcohorttoprogram&programid=' + programId + '&cohortid=' + cohortId);
                                                    });
                                                });
                                            } else {
                                                cohortsList.innerHTML = '<div class=\"alert alert-info\" style=\"margin: 0;\">Когорты не найдены или все уже прикреплены</div>';
                                            }
                                        } else {
                                            cohortsList.innerHTML = '<div class=\"alert alert-danger\" style=\"margin: 0;\">Ошибка: ' + (response.error || 'Неизвестная ошибка') + '</div>';
                                        }
                                    } catch (e) {
                                        console.error('Ошибка парсинга JSON:', e);
                                        console.error('Ответ сервера:', xhr.responseText);
                                        cohortsList.innerHTML = '<div class=\"alert alert-danger\" style=\"margin: 0;\">Ошибка при обработке ответа: ' + e.message + '</div>';
                                    }
                                } else {
                                    cohortsList.innerHTML = '<div class=\"alert alert-danger\" style=\"margin: 0;\">Ошибка загрузки данных (HTTP ' + xhr.status + ')</div>';
                                }
                            }
                        };
                        xhr.onerror = function() {
                            cohortsList.innerHTML = '<div class=\"alert alert-danger\" style=\"margin: 0;\">Ошибка сети при загрузке данных</div>';
                        };
                        xhr.send();
                    }
                    
                    function escapeHtml(text) {
                        var div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }
                    
                    // Поиск когорт
                    var cohortSearchInput = document.getElementById('cohort-search');
                    var cohortsList = document.getElementById('cohorts-list');
                    var cohortSearchTimeout;
                    
                    if (cohortSearchInput) {
                        cohortSearchInput.addEventListener('input', function() {
                            if (!currentProgramId) {
                                cohortsList.innerHTML = '<div class=\"alert alert-warning\" style=\"margin: 0;\">Программа не выбрана</div>';
                                return;
                            }
                            
                            clearTimeout(cohortSearchTimeout);
                            var query = this.value.trim();
                            
                            cohortSearchTimeout = setTimeout(function() {
                                loadCohortsList(query, currentProgramId);
                            }, 300);
                        });
                    }
                    
                    // Обработчик просмотра прикрепленных групп программы
                    document.querySelectorAll('.view-program-cohorts').forEach(function(badge) {
                        badge.addEventListener('click', function(e) {
                            e.preventDefault();
                            var programId = this.getAttribute('data-program-id');
                            if (!programId) return;
                            
                            // Показываем модальное окно
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery('#viewProgramCohortsModal').modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(document.getElementById('viewProgramCohortsModal'));
                                modal.show();
                            }
                            
                            // Загружаем список прикрепленных групп
                            var cohortsList = document.getElementById('program-cohorts-list');
                            cohortsList.innerHTML = '<div class=\"text-muted\">Загрузка групп...</div>';
                            
                            var xhr = new XMLHttpRequest();
                            xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getprogramcohorts&programid=' + programId, true);
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success && response.cohorts) {
                                            if (response.cohorts.length > 0) {
                                                var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>ID Number</th><th>Описание</th><th>Действие</th></tr></thead><tbody>';
                                                response.cohorts.forEach(function(cohort) {
                                                    html += '<tr>';
                                                    html += '<td>' + cohort.id + '</td>';
                                                    html += '<td>' + escapeHtml(cohort.name) + '</td>';
                                                    html += '<td>' + (cohort.idnumber ? escapeHtml(cohort.idnumber) : '-') + '</td>';
                                                    // Описание отображаем как HTML, но ограничиваем длину
                                                    var description = cohort.description || '';
                                                    var descriptionHtml = description;
                                                    if (description.length > 100) {
                                                        descriptionHtml = description.substring(0, 100) + '...';
                                                    }
                                                    html += '<td>' + descriptionHtml + '</td>';
                                                    html += '<td><button class=\"btn btn-sm btn-danger detach-cohort-btn\" data-cohort-id=\"' + cohort.id + '\" data-program-id=\"' + programId + '\"><i class=\"fas fa-times\"></i> Открепить</button></td>';
                                                    html += '</tr>';
                                                });
                                                html += '</tbody></table>';
                                                cohortsList.innerHTML = html;
                                                
                                                // Обработчики кнопок открепления
                                                document.querySelectorAll('.detach-cohort-btn').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        if (!confirm('Вы уверены, что хотите открепить эту группу от программы?')) {
                                                            return;
                                                        }
                                                        var cohortId = this.getAttribute('data-cohort-id');
                                                        var programId = this.getAttribute('data-program-id');
                                                        var btn = this;
                                                        btn.disabled = true;
                                                        
                                                        var xhr2 = new XMLHttpRequest();
                                                        xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                        xhr2.onreadystatechange = function() {
                                                            if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                                var response2 = JSON.parse(xhr2.responseText);
                                                                if (response2.success) {
                                                                    btn.closest('tr').remove();
                                                                    if (document.querySelectorAll('#program-cohorts-list tbody tr').length === 0) {
                                                                        document.getElementById('program-cohorts-list').innerHTML = '<div class=\"alert alert-info\">Группы не прикреплены</div>';
                                                                    }
                                                                    location.reload();
                                                                } else {
                                                                    alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                                    btn.disabled = false;
                                                                }
                                                            }
                                                        };
                                                        xhr2.send('action=detachcohortfromprogram&programid=' + programId + '&cohortid=' + cohortId);
                                                    });
                                                });
                                            } else {
                                                cohortsList.innerHTML = '<div class=\"alert alert-info\">Группы не прикреплены к этой программе</div>';
                                            }
                                        } else {
                                            cohortsList.innerHTML = '<div class=\"alert alert-danger\">Ошибка: ' + (response.error || 'Неизвестная ошибка') + '</div>';
                                        }
                                    } catch (e) {
                                        cohortsList.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа</div>';
                                    }
                                }
                            };
                            xhr.send();
                        });
                    });
                    
                    function escapeHtml(text) {
                        var div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    }
                    
                    // Обработчик удаления программы
                    document.querySelectorAll('.delete-program').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (!confirm('Вы уверены, что хотите удалить эту программу? Все связи с предметами и когортами будут удалены.')) {
                                return;
                            }
                            var programId = this.getAttribute('data-program-id');
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                    }
                                }
                            };
                            xhr.send('action=deleteprogram&programid=' + programId);
                        });
                    });
                    
                    // Обработчик кнопки импорта JSON
                    var importBtn = document.getElementById('import-programs-json-btn');
                    if (importBtn) {
                        importBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery('#importProgramsJsonModal').modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(document.getElementById('importProgramsJsonModal'));
                                modal.show();
                            } else {
                                // Fallback: просто показываем модальное окно через CSS
                                var modal = document.getElementById('importProgramsJsonModal');
                                if (modal) {
                                    modal.style.display = 'block';
                                    modal.classList.add('show');
                                    document.body.classList.add('modal-open');
                                }
                            }
                        });
                    }
                })();
            ");
        }
        
        echo html_writer::end_div();
        break;
    
    case 'subjects':
        // Вкладка "Предметы"
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        
        // Обработка импорта JSON
        $importaction = optional_param('import', '', PARAM_ALPHA);
        if ($importaction == 'json') {
            $importsubmitted = optional_param('import_submit', 0, PARAM_INT);
            if ($importsubmitted) {
                // Проверяем загруженный файл
                $file = $_FILES['jsonfile'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    echo html_writer::div('Ошибка загрузки файла. Убедитесь, что файл выбран и не превышает максимальный размер.', 'alert alert-danger');
                } else {
                    // Проверяем тип файла
                    $filetype = mime_content_type($file['tmp_name']);
                    $fileext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($fileext !== 'json' && strpos($filetype, 'json') === false && strpos($filetype, 'text') === false) {
                        echo html_writer::div('Неверный тип файла. Загрузите файл в формате JSON.', 'alert alert-danger');
                    } else {
                        // Читаем содержимое файла
                        $jsoncontent = file_get_contents($file['tmp_name']);
                        if ($jsoncontent === false) {
                            echo html_writer::div('Ошибка чтения файла.', 'alert alert-danger');
                        } else {
                            // Парсим JSON
                            $jsondata = json_decode($jsoncontent, true);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                echo html_writer::div('Ошибка парсинга JSON: ' . json_last_error_msg(), 'alert alert-danger');
                            } else {
                                // Определяем структуру: если есть ключ 'subjects', используем его, иначе весь JSON как массив
                                if (is_array($jsondata) && isset($jsondata['subjects']) && is_array($jsondata['subjects'])) {
                                    $subjectsdata = $jsondata['subjects'];
                                } elseif (is_array($jsondata)) {
                                    $subjectsdata = $jsondata;
                                } else {
                                    echo html_writer::div('JSON файл должен содержать массив предметов или объект с ключом "subjects".', 'alert alert-danger');
                                    $subjectsdata = [];
                                }
                                
                                if (empty($subjectsdata)) {
                                    echo html_writer::div('Не найдено предметов для импорта.', 'alert alert-warning');
                                } else {
                                    // Импортируем предметы
                                    $imported = 0;
                                    $skipped = 0;
                                    $errors = [];
                                    
                                    $transaction = $DB->start_delegated_transaction();
                                    try {
                                        foreach ($subjectsdata as $index => $subjectdata) {
                                            // Валидация данных
                                            if (empty($subjectdata['name'])) {
                                                $errors[] = 'Предмет #' . ((int)$index + 1) . ': отсутствует название';
                                                $skipped++;
                                                continue;
                                            }
                                            
                                            // Проверяем, существует ли предмет с таким названием или кодом
                                            $existing = null;
                                            $subjectcode = !empty($subjectdata['code']) ? trim($subjectdata['code']) : '';
                                            if ($subjectcode) {
                                                $existing = $DB->get_record('local_deanpromoodle_subjects', ['code' => $subjectcode]);
                                            }
                                            if (!$existing) {
                                                $existing = $DB->get_record('local_deanpromoodle_subjects', ['name' => trim($subjectdata['name'])]);
                                            }
                                            
                                            if ($existing) {
                                                $skipped++;
                                                continue; // Пропускаем существующие предметы
                                            }
                                            
                                            // Маппинг полей из нового формата
                                            // name -> name
                                            // code -> code
                                            // short_description -> shortdescription
                                            // description -> description (может быть null)
                                            // order -> sortorder
                                            // is_active (true/false) -> visible (1/0)
                                            
                                            // Создаем новый предмет
                                            $data = new stdClass();
                                            $data->name = trim($subjectdata['name']);
                                            $data->code = $subjectcode;
                                            
                                            // Маппинг short_description -> shortdescription
                                            if (isset($subjectdata['short_description'])) {
                                                $data->shortdescription = $subjectdata['short_description'] ?: '';
                                            } elseif (isset($subjectdata['shortdescription'])) {
                                                $data->shortdescription = $subjectdata['shortdescription'] ?: '';
                                            } else {
                                                $data->shortdescription = '';
                                            }
                                            
                                            // Маппинг description (может быть null)
                                            if (isset($subjectdata['description'])) {
                                                $data->description = $subjectdata['description'] ?: '';
                                            } else {
                                                $data->description = '';
                                            }
                                            
                                            // Маппинг order -> sortorder
                                            if (isset($subjectdata['order'])) {
                                                $data->sortorder = (int)$subjectdata['order'];
                                            } elseif (isset($subjectdata['sortorder'])) {
                                                $data->sortorder = (int)$subjectdata['sortorder'];
                                            } else {
                                                $data->sortorder = 0;
                                            }
                                            
                                            // Маппинг is_active (true/false) -> visible (1/0)
                                            if (isset($subjectdata['is_active'])) {
                                                $data->visible = $subjectdata['is_active'] ? 1 : 0;
                                            } elseif (isset($subjectdata['visible'])) {
                                                $data->visible = (int)$subjectdata['visible'];
                                            } else {
                                                $data->visible = 1;
                                            }
                                            
                                            $data->timecreated = time();
                                            $data->timemodified = time();
                                            
                                            $DB->insert_record('local_deanpromoodle_subjects', $data);
                                            $imported++;
                                        }
                                        
                                        $transaction->allow_commit();
                                        
                                        // Сообщение об успехе
                                        $message = 'Импорт завершен. Импортировано предметов: ' . $imported;
                                        if ($skipped > 0) {
                                            $message .= ', пропущено (уже существуют): ' . $skipped;
                                        }
                                        if (!empty($errors)) {
                                            $message .= '. Ошибки: ' . implode('; ', array_slice($errors, 0, 5));
                                            if (count($errors) > 5) {
                                                $message .= ' и еще ' . (count($errors) - 5) . ' ошибок';
                                            }
                                        }
                                        echo html_writer::div($message, 'alert alert-success');
                                        
                                        // Редирект на список предметов
                                        redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects']), $message, null, \core\output\notification::NOTIFY_SUCCESS);
                                    } catch (\Exception $e) {
                                        $transaction->rollback($e);
                                        echo html_writer::div('Ошибка при импорте: ' . $e->getMessage(), 'alert alert-danger');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Обработка действий
        if ($action == 'create' || ($action == 'edit' && $subjectid > 0)) {
            // Создание или редактирование предмета
            $subject = null;
            $isedit = ($action == 'edit' && $subjectid > 0);
            
            if ($isedit) {
                $subject = $DB->get_record('local_deanpromoodle_subjects', ['id' => $subjectid]);
                if (!$subject) {
                    echo html_writer::div('Предмет не найден.', 'alert alert-danger');
                    break;
                }
            }
            
            // Обработка отправки формы
            $formsubmitted = optional_param('submit', 0, PARAM_INT);
            if ($formsubmitted) {
                $name = optional_param('name', '', PARAM_TEXT);
                $code = optional_param('code', '', PARAM_TEXT);
                $shortdescription = optional_param('shortdescription', '', PARAM_RAW);
                $description = optional_param('description', '', PARAM_RAW);
                $credits = optional_param('credits', null, PARAM_INT);
                $academichours = optional_param('academic_hours', null, PARAM_INT);
                $independenthours = optional_param('independent_hours', null, PARAM_INT);
                $sortorder = optional_param('sortorder', 0, PARAM_INT);
                $visible = optional_param('visible', 1, PARAM_INT);
                
                if (empty($name)) {
                    echo html_writer::div('Название предмета обязательно для заполнения.', 'alert alert-danger');
                } else {
                    $data = new stdClass();
                    $data->name = $name;
                    $data->code = $code;
                    $data->shortdescription = $shortdescription;
                    $data->description = $description;
                    $data->credits = $credits !== null ? $credits : null;
                    $data->academic_hours = $academichours !== null ? $academichours : null;
                    $data->independent_hours = $independenthours !== null ? $independenthours : null;
                    $data->sortorder = $sortorder;
                    $data->visible = $visible;
                    $data->timemodified = time();
                    
                    if ($isedit) {
                        $data->id = $subjectid;
                        $DB->update_record('local_deanpromoodle_subjects', $data);
                        $subjectid = $data->id;
                    } else {
                        $data->timecreated = time();
                        $subjectid = $DB->insert_record('local_deanpromoodle_subjects', $data);
                    }
                    
                    // Редирект на список предметов
                    redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects']));
                }
            }
            
            // Отображение формы
            $formtitle = $isedit ? 'Редактировать предмет' : 'Создать предмет';
            echo html_writer::tag('h2', $formtitle, ['style' => 'margin-bottom: 20px;']);
            
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', [
                    'tab' => 'subjects',
                    'action' => $action,
                    'subjectid' => $subjectid
                ]),
                'style' => 'max-width: 800px;'
            ]);
            
            // Название предмета *
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Название предмета *', 'name');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'name',
                'id' => 'name',
                'class' => 'form-control',
                'value' => $subject ? htmlspecialchars($subject->name, ENT_QUOTES, 'UTF-8') : '',
                'required' => true
            ]);
            echo html_writer::end_div();
            
            // Код предмета
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Код предмета', 'code');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'code',
                'id' => 'code',
                'class' => 'form-control',
                'value' => $subject ? htmlspecialchars($subject->code ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Краткое описание
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Краткое описание', 'shortdescription');
            echo html_writer::start_tag('textarea', [
                'name' => 'shortdescription',
                'id' => 'shortdescription',
                'class' => 'form-control',
                'rows' => '3'
            ]);
            echo $subject ? htmlspecialchars($subject->shortdescription ?? '', ENT_QUOTES, 'UTF-8') : '';
            echo html_writer::end_tag('textarea');
            echo html_writer::end_div();
            
            // Описание предмета
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Описание предмета', 'description');
            echo html_writer::start_tag('textarea', [
                'name' => 'description',
                'id' => 'description',
                'class' => 'form-control',
                'rows' => '5'
            ]);
            echo $subject ? htmlspecialchars($subject->description ?? '', ENT_QUOTES, 'UTF-8') : '';
            echo html_writer::end_tag('textarea');
            echo html_writer::end_div();
            
            // Количество кредитов
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Количество кредитов по предмету', 'credits');
            echo html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => 'credits',
                'id' => 'credits',
                'class' => 'form-control',
                'value' => $subject && isset($subject->credits) ? (int)$subject->credits : '',
                'min' => 0,
                'step' => 1
            ]);
            echo html_writer::end_div();
            
            // Количество академических часов
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Количество академических часов', 'academic_hours');
            echo html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => 'academic_hours',
                'id' => 'academic_hours',
                'class' => 'form-control',
                'value' => $subject && isset($subject->academic_hours) ? (int)$subject->academic_hours : '',
                'min' => 0,
                'step' => 1
            ]);
            echo html_writer::end_div();
            
            // Количество часов самостоятельной работы
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Количество часов самостоятельной работы', 'independent_hours');
            echo html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => 'independent_hours',
                'id' => 'independent_hours',
                'class' => 'form-control',
                'value' => $subject && isset($subject->independent_hours) ? (int)$subject->independent_hours : '',
                'min' => 0,
                'step' => 1
            ]);
            echo html_writer::end_div();
            
            // Порядок отображения
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Порядок отображения', 'sortorder');
            echo html_writer::empty_tag('input', [
                'type' => 'number',
                'name' => 'sortorder',
                'id' => 'sortorder',
                'class' => 'form-control',
                'value' => $subject ? (int)$subject->sortorder : 0,
                'min' => 0
            ]);
            echo html_writer::end_div();
            
            // Статус
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Статус', 'visible');
            echo html_writer::select(
                [1 => 'Активный', 0 => 'Скрыт'],
                'visible',
                $subject ? (int)$subject->visible : 1,
                false,
                ['class' => 'form-control']
            );
            echo html_writer::end_div();
            
            // Кнопки
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'submit',
                'value' => '1'
            ]);
            $submittext = $isedit ? 'Сохранить изменения' : 'Создать предмет';
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => $submittext,
                'class' => 'btn btn-primary',
                'style' => 'margin-right: 10px;'
            ]);
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects']),
                'Отмена',
                ['class' => 'btn btn-secondary']
            );
            echo html_writer::end_div();
            
            echo html_writer::end_tag('form');
            
        } elseif ($action == 'view' && $subjectid > 0) {
            // Страница предмета - курсы предмета
            $subject = $DB->get_record('local_deanpromoodle_subjects', ['id' => $subjectid]);
            if (!$subject) {
                echo html_writer::div('Предмет не найден.', 'alert alert-danger');
                break;
            }
            
            $subjectname = is_string($subject->name) ? $subject->name : (string)$subject->name;
            
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
            echo html_writer::tag('h2', 'Курсы предмета: ' . htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8'), ['style' => 'margin: 0;']);
            echo html_writer::link('#', '+ Добавить курс', [
                'class' => 'btn btn-primary',
                'id' => 'add-course-btn',
                'data-subject-id' => $subjectid,
                'style' => 'background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
            ]);
            echo html_writer::end_div();
            
            // Получаем курсы предмета
            $subjectcourses = $DB->get_records_sql(
                "SELECT sc.*, c.fullname, c.shortname
                 FROM {local_deanpromoodle_subject_courses} sc
                 JOIN {course} c ON c.id = sc.courseid
                 WHERE sc.subjectid = ?
                 ORDER BY sc.sortorder ASC, c.fullname ASC",
                [$subjectid]
            );
            
            if (empty($subjectcourses)) {
                echo html_writer::div('К этому предмету еще не прикреплены курсы.', 'alert alert-info');
            } else {
                echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Порядок');
                echo html_writer::tag('th', 'ID курса');
                echo html_writer::tag('th', 'Название курса');
                echo html_writer::tag('th', 'Код курса');
                echo html_writer::tag('th', 'Действия');
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                foreach ($subjectcourses as $sc) {
                    echo html_writer::start_tag('tr');
                    echo html_writer::tag('td', (string)$sc->sortorder);
                    echo html_writer::tag('td', (string)$sc->courseid);
                    $coursename = is_string($sc->fullname) ? htmlspecialchars($sc->fullname, ENT_QUOTES, 'UTF-8') : '-';
                    echo html_writer::tag('td', $coursename);
                    $courseshortname = is_string($sc->shortname) ? htmlspecialchars($sc->shortname, ENT_QUOTES, 'UTF-8') : '-';
                    echo html_writer::tag('td', $courseshortname);
                    echo html_writer::start_tag('td');
                    echo html_writer::link('#', '<i class="fas fa-trash"></i> Удалить', [
                        'class' => 'btn btn-sm btn-danger detach-course-btn',
                        'data-subject-id' => $subjectid,
                        'data-course-id' => $sc->courseid,
                        'style' => 'text-decoration: none;'
                    ]);
                    echo html_writer::end_tag('td');
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
            }
            
            // Модальное окно для добавления курса
            echo html_writer::start_div('modal fade', [
                'id' => 'addCourseModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Добавить курс к предмету', ['class' => 'modal-title']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#addCourseModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('form-group');
            echo html_writer::label('Поиск курса', 'course-search');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => 'course-search',
                'class' => 'form-control',
                'placeholder' => 'Введите название или код курса...'
            ]);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['id' => 'courses-list', 'style' => 'max-height: 400px; overflow-y: auto;']);
            echo html_writer::div('Введите текст для поиска курсов...', 'text-muted');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('modal-footer');
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#addCourseModal\').modal(\'hide\');'
            ]);
            echo 'Закрыть';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // JavaScript для модального окна
            $PAGE->requires->js_init_code("
                (function() {
                    var searchInput = document.getElementById('course-search');
                    var coursesList = document.getElementById('courses-list');
                    var searchTimeout;
                    
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        var query = this.value.trim();
                        
                        if (query.length < 2) {
                            coursesList.innerHTML = '<div class=\"text-muted\">Введите минимум 2 символа для поиска...</div>';
                            return;
                        }
                        
                        searchTimeout = setTimeout(function() {
                            var xhr = new XMLHttpRequest();
                            xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getcourses&search=' + encodeURIComponent(query) + '&subjectid=' + " . $subjectid . ", true);
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success && response.courses) {
                                            var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>Код</th><th>Действие</th></tr></thead><tbody>';
                                            response.courses.forEach(function(course) {
                                                html += '<tr><td>' + course.id + '</td><td>' + course.fullname + '</td><td>' + (course.shortname || '-') + '</td><td><button class=\"btn btn-sm btn-primary attach-course-btn\" data-course-id=\"' + course.id + '\">Прикрепить</button></td></tr>';
                                            });
                                            html += '</tbody></table>';
                                            coursesList.innerHTML = html;
                                            
                                            // Обработчики кнопок прикрепления
                                            document.querySelectorAll('.attach-course-btn').forEach(function(btn) {
                                                btn.addEventListener('click', function() {
                                                    var courseId = this.getAttribute('data-course-id');
                                                    var xhr2 = new XMLHttpRequest();
                                                    xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                    xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                    xhr2.onreadystatechange = function() {
                                                        if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                            var response2 = JSON.parse(xhr2.responseText);
                                                            if (response2.success) {
                                                                location.reload();
                                                            } else {
                                                                alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                            }
                                                        }
                                                    };
                                                    xhr2.send('action=attachcoursetosubject&subjectid=' + " . $subjectid . " + '&courseid=' + courseId);
                                                });
                                            });
                                        } else {
                                            coursesList.innerHTML = '<div class=\"alert alert-info\">Курсы не найдены</div>';
                                        }
                                    } catch (e) {
                                        coursesList.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа</div>';
                                    }
                                }
                            };
                            xhr.send();
                        }, 500);
                    });
                    
                    // Открытие модального окна
                    document.getElementById('add-course-btn').addEventListener('click', function(e) {
                        e.preventDefault();
                        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                            jQuery('#addCourseModal').modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var modal = new bootstrap.Modal(document.getElementById('addCourseModal'));
                            modal.show();
                        }
                    });
                    
                    // Обработчик удаления курса
                    document.querySelectorAll('.detach-course-btn').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (!confirm('Вы уверены, что хотите удалить этот курс из предмета?')) {
                                return;
                            }
                            var courseId = this.getAttribute('data-course-id');
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                    }
                                }
                            };
                            xhr.send('action=detachcoursefromsubject&subjectid=' + " . $subjectid . " + '&courseid=' + courseId);
                        });
                    });
                })();
            ");
            
        } else {
            // Список предметов
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
            echo html_writer::start_div('', ['style' => 'display: flex; align-items: center; gap: 10px;']);
            echo html_writer::tag('i', '', ['class' => 'fas fa-book', 'style' => 'font-size: 24px;']);
            echo html_writer::tag('h2', 'Предметы', ['style' => 'margin: 0; font-size: 24px; font-weight: 600;']);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['style' => 'display: flex; gap: 10px;']);
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'action' => 'create']),
                '+ Добавить предмет',
                [
                    'class' => 'btn btn-primary',
                    'style' => 'background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
                ]
            );
            echo html_writer::link('#', '<i class="fas fa-file-import"></i> Импорт из JSON', [
                'class' => 'btn btn-success',
                'id' => 'import-json-btn',
                'style' => 'background-color: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
            ]);
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Модальное окно для импорта JSON
            echo html_writer::start_div('modal fade', [
                'id' => 'importJsonModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Импорт предметов из JSON', ['class' => 'modal-title']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#importJsonModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'import' => 'json']),
                'enctype' => 'multipart/form-data'
            ]);
            echo html_writer::start_div('form-group');
            echo html_writer::label('Выберите JSON файл', 'jsonfile');
            echo html_writer::empty_tag('input', [
                'type' => 'file',
                'name' => 'jsonfile',
                'id' => 'jsonfile',
                'class' => 'form-control-file',
                'accept' => '.json,application/json',
                'required' => true
            ]);
            echo html_writer::start_div('form-text text-muted', ['style' => 'margin-top: 5px;']);
            echo 'Формат JSON файла (поддерживаются оба варианта):<br>';
            echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 11px; margin-top: 5px; max-height: 200px; overflow-y: auto;">';
            echo 'Вариант 1 (объект с ключом "subjects"):<br>';
            echo '{<br>';
            echo '  "subjects": [<br>';
            echo '    {<br>';
            echo '      "name": "Название предмета",<br>';
            echo '      "code": "КОД",<br>';
            echo '      "short_description": "Краткое описание",<br>';
            echo '      "description": "Полное описание",<br>';
            echo '      "order": 0,<br>';
            echo '      "is_active": true<br>';
            echo '    }<br>';
            echo '  ]<br>';
            echo '}<br><br>';
            echo 'Вариант 2 (простой массив):<br>';
            echo '[<br>';
            echo '  {<br>';
            echo '    "name": "Название предмета",<br>';
            echo '    "code": "КОД",<br>';
            echo '    "shortdescription": "Краткое описание",<br>';
            echo '    "description": "Полное описание",<br>';
            echo '    "sortorder": 0,<br>';
            echo '    "visible": 1<br>';
            echo '  }<br>';
            echo ']</pre>';
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'import_submit',
                'value' => '1'
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => 'Импортировать',
                'class' => 'btn btn-success',
                'style' => 'margin-right: 10px;'
            ]);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#importJsonModal\').modal(\'hide\');'
            ]);
            echo 'Отмена';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_tag('form');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // Получаем все предметы
            $subjects = $DB->get_records('local_deanpromoodle_subjects', null, 'sortorder ASC, name ASC');
            
            if (empty($subjects)) {
                echo html_writer::div('Предметы не найдены. Создайте первый предмет.', 'alert alert-info');
            } else {
                // Подготовка данных для таблицы
                $subjectsdata = [];
                foreach ($subjects as $subject) {
                    // Подсчет курсов
                    $coursescount = $DB->count_records('local_deanpromoodle_subject_courses', ['subjectid' => $subject->id]);
                    
                    // Подсчет программ и получение их названий и ID
                    $programscount = $DB->count_records('local_deanpromoodle_program_subjects', ['subjectid' => $subject->id]);
                    
                    // Получаем ID и названия программ для поиска
                    $programsearchitems = [];
                    if ($programscount > 0) {
                        $programs = $DB->get_records_sql(
                            "SELECT p.id, p.name 
                             FROM {local_deanpromoodle_program_subjects} ps
                             JOIN {local_deanpromoodle_programs} p ON p.id = ps.programid
                             WHERE ps.subjectid = ?
                             ORDER BY p.name ASC",
                            [$subject->id]
                        );
                        foreach ($programs as $program) {
                            // Добавляем ID и название программы в нижнем регистре для поиска
                            $programsearchitems[] = (string)$program->id;
                            $programsearchitems[] = mb_strtolower($program->name);
                        }
                    }
                    $programssearchtext = implode(' ', $programsearchitems);
                    
                    $subjectsdata[] = (object)[
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code ?? '',
                        'sortorder' => $subject->sortorder,
                        'coursescount' => $coursescount,
                        'programscount' => $programscount,
                        'visible' => $subject->visible,
                        'programssearchtext' => $programssearchtext
                    ];
                }
                
                // Поля поиска
                echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;']);
                
                // Поиск по предметам
                echo html_writer::start_div('', ['style' => 'flex: 1; min-width: 300px;']);
                echo html_writer::label('Поиск предметов:', 'subject-search-input');
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'id' => 'subject-search-input',
                    'class' => 'form-control',
                    'placeholder' => 'Введите название, код или ID предмета...',
                    'style' => 'max-width: 100%;'
                ]);
                echo html_writer::end_div();
                
                // Поиск по программам
                echo html_writer::start_div('', ['style' => 'flex: 1; min-width: 300px;']);
                echo html_writer::label('Поиск по программам:', 'program-search-input');
                echo html_writer::empty_tag('input', [
                    'type' => 'text',
                    'id' => 'program-search-input',
                    'class' => 'form-control',
                    'placeholder' => 'Введите ID или название программы...',
                    'style' => 'max-width: 100%;'
                ]);
                echo html_writer::end_div();
                
                echo html_writer::end_div();
                
                // Таблица предметов с ID для JavaScript
                echo html_writer::start_tag('table', [
                    'class' => 'table table-striped table-hover',
                    'style' => 'width: 100%;',
                    'id' => 'subjects-table'
                ]);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', '<span class="sortable" data-column="sortorder">Порядок</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'sortorder'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="id">ID</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'id'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="name">Название</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'name'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="code">Код</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'code'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="coursescount">Курсов</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'coursescount'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="programscount">Кол-во программ</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'programscount'
                ]);
                echo html_writer::tag('th', '<span class="sortable" data-column="visible">Статус</span> <i class="fas fa-sort sort-icon"></i>', [
                    'style' => 'cursor: pointer; user-select: none;',
                    'class' => 'sortable-header',
                    'data-column' => 'visible'
                ]);
                echo html_writer::tag('th', 'Действия', ['style' => 'width: 150px;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody', ['id' => 'subjects-table-body']);
                
                foreach ($subjectsdata as $subject) {
                    $subjectname = is_string($subject->name) ? $subject->name : (string)$subject->name;
                    $subjectcode = is_string($subject->code) ? $subject->code : (string)$subject->code;
                    
                    // Добавляем data-атрибуты для поиска и сортировки
                    // Экранируем значения для безопасного использования в HTML
                    $searchText = mb_strtolower($subject->id . ' ' . $subjectname . ' ' . $subjectcode);
                    $searchText = htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8');
                    
                    // Текст для поиска по программам
                    $programsSearchText = htmlspecialchars($subject->programssearchtext ?? '', ENT_QUOTES, 'UTF-8');
                    
                    echo html_writer::start_tag('tr', [
                        'data-sortorder' => $subject->sortorder,
                        'data-id' => $subject->id,
                        'data-name' => htmlspecialchars(mb_strtolower($subjectname), ENT_QUOTES, 'UTF-8'),
                        'data-code' => htmlspecialchars(mb_strtolower($subjectcode), ENT_QUOTES, 'UTF-8'),
                        'data-coursescount' => $subject->coursescount,
                        'data-programscount' => $subject->programscount,
                        'data-visible' => $subject->visible,
                        'data-search-text' => $searchText,
                        'data-programs-search-text' => $programsSearchText
                    ]);
                    echo html_writer::tag('td', (string)$subject->sortorder);
                    echo html_writer::tag('td', (string)$subject->id);
                    echo html_writer::tag('td', htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8'));
                    echo html_writer::tag('td', htmlspecialchars($subjectcode, ENT_QUOTES, 'UTF-8'));
                    echo html_writer::tag('td', (string)$subject->coursescount);
                    echo html_writer::tag('td', (string)$subject->programscount);
                    $status = $subject->visible ? '<span class="badge badge-success">Активный</span>' : '<span class="badge badge-secondary">Скрыт</span>';
                    echo html_writer::tag('td', $status);
                    echo html_writer::start_tag('td');
                    echo html_writer::start_div('action-buttons', ['style' => 'display: flex; gap: 4px;']);
                    // Просмотр
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'action' => 'view', 'subjectid' => $subject->id]),
                        '<i class="fas fa-eye"></i>',
                        ['class' => 'action-btn action-btn-view', 'title' => 'Просмотр']
                    );
                    // Редактирование
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'action' => 'edit', 'subjectid' => $subject->id]),
                        '<i class="fas fa-edit"></i>',
                        ['class' => 'action-btn action-btn-edit', 'title' => 'Редактировать']
                    );
                    // Прикрепить курс
                    echo html_writer::link('#', '<i class="fas fa-book"></i>', [
                        'class' => 'action-btn action-btn-course add-course-to-subject-list-btn',
                        'title' => 'Прикрепить курс',
                        'data-subject-id' => $subject->id,
                        'data-subject-name' => htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8'),
                        'style' => 'background-color: #17a2b8; color: white;'
                    ]);
                    // Прикрепить к программе
                    echo html_writer::link('#', '<i class="fas fa-link"></i>', [
                        'class' => 'action-btn action-btn-link attach-subject-to-program',
                        'title' => 'Прикрепить к программе',
                        'data-subject-id' => $subject->id,
                        'data-subject-name' => htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8')
                    ]);
                    // Удаление
                    echo html_writer::link('#', '<i class="fas fa-times"></i>', [
                        'class' => 'action-btn action-btn-delete delete-subject',
                        'title' => 'Удалить',
                        'data-subject-id' => $subject->id
                    ]);
                    echo html_writer::end_div();
                    echo html_writer::end_tag('td');
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                
                // JavaScript для сортировки и поиска
                $PAGE->requires->js_init_code("
                    (function() {
                        var table = document.getElementById('subjects-table');
                        var tbody = document.getElementById('subjects-table-body');
                        var searchInput = document.getElementById('subject-search-input');
                        var rows = Array.from(tbody.querySelectorAll('tr'));
                        var currentSort = {
                            column: null,
                            direction: 'asc'
                        };
                        
                        // Сохраняем исходные данные строк
                        var originalRows = rows.map(function(row) {
                            return row.cloneNode(true);
                        });
                        
                        // Функция сортировки
                        function sortTable(column) {
                            var isNumeric = ['sortorder', 'id', 'coursescount', 'programscount', 'visible'].includes(column);
                            var direction = currentSort.column === column && currentSort.direction === 'asc' ? 'desc' : 'asc';
                            
                            currentSort.column = column;
                            currentSort.direction = direction;
                            
                            rows.sort(function(a, b) {
                                var aVal, bVal;
                                
                                if (isNumeric) {
                                    aVal = parseFloat(a.getAttribute('data-' + column)) || 0;
                                    bVal = parseFloat(b.getAttribute('data-' + column)) || 0;
                                } else {
                                    aVal = (a.getAttribute('data-' + column) || '').toLowerCase();
                                    bVal = (b.getAttribute('data-' + column) || '').toLowerCase();
                                }
                                
                                if (isNumeric) {
                                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                                } else {
                                    if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                                    if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                                    return 0;
                                }
                            });
                            
                            // Обновляем иконки сортировки
                            var headers = table.querySelectorAll('.sortable-header');
                            headers.forEach(function(header) {
                                var icon = header.querySelector('.sort-icon');
                                if (header.getAttribute('data-column') === column) {
                                    icon.className = 'fas fa-sort-' + (direction === 'asc' ? 'up' : 'down') + ' sort-icon';
                                    icon.style.color = '#007bff';
                                } else {
                                    icon.className = 'fas fa-sort sort-icon';
                                    icon.style.color = '#999';
                                }
                            });
                            
                            // Перерисовываем таблицу
                            rows.forEach(function(row) {
                                tbody.appendChild(row);
                            });
                        }
                        
                        // Обработчики клика на заголовки
                        var headers = table.querySelectorAll('.sortable-header');
                        headers.forEach(function(header) {
                            header.addEventListener('click', function() {
                                var column = this.getAttribute('data-column');
                                sortTable(column);
                            });
                            
                            // Стили для hover
                            header.addEventListener('mouseenter', function() {
                                this.style.backgroundColor = '#f8f9fa';
                            });
                            header.addEventListener('mouseleave', function() {
                                this.style.backgroundColor = '';
                            });
                        });
                        
                        // Функция поиска
                        function filterTable() {
                            var subjectSearchText = (searchInput.value || '').toLowerCase().trim();
                            var programSearchInput = document.getElementById('program-search-input');
                            var programSearchText = (programSearchInput ? programSearchInput.value || '' : '').toLowerCase().trim();
                            
                            rows.forEach(function(row) {
                                var showRow = true;
                                
                                // Фильтр по предметам
                                if (subjectSearchText) {
                                    var searchData = row.getAttribute('data-search-text') || '';
                                    if (searchData.indexOf(subjectSearchText) === -1) {
                                        showRow = false;
                                    }
                                }
                                
                                // Фильтр по программам
                                if (showRow && programSearchText) {
                                    var programsData = row.getAttribute('data-programs-search-text') || '';
                                    if (programsData.indexOf(programSearchText) === -1) {
                                        showRow = false;
                                    }
                                }
                                
                                row.style.display = showRow ? '' : 'none';
                            });
                        }
                        
                        // Обработчик поиска по предметам
                        searchInput.addEventListener('input', function() {
                            filterTable();
                        });
                        
                        // Обработчик поиска по программам
                        var programSearchInput = document.getElementById('program-search-input');
                        if (programSearchInput) {
                            programSearchInput.addEventListener('input', function() {
                                filterTable();
                            });
                        }
                        
                        // CSS для сортировки
                        var style = document.createElement('style');
                        style.textContent = '.sortable-header { position: relative; padding-right: 25px !important; } ' +
                            '.sortable-header .sort-icon { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 12px; color: #999; transition: color 0.2s; } ' +
                            '.sortable-header:hover { background-color: #f8f9fa !important; } ' +
                            '.sortable-header:hover .sort-icon { color: #007bff; }';
                        document.head.appendChild(style);
                    })();
                ");
            }
            
            // Модальное окно для прикрепления предмета к программе
            echo html_writer::start_div('modal fade', [
                'id' => 'attachSubjectToProgramModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Прикрепить предмет к программе', ['class' => 'modal-title', 'id' => 'attachSubjectModalTitle']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#attachSubjectToProgramModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('form-group');
            echo html_writer::label('Поиск программы', 'program-search');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => 'program-search',
                'class' => 'form-control',
                'placeholder' => 'Введите название программы...'
            ]);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['id' => 'programs-list', 'style' => 'max-height: 400px; overflow-y: auto;']);
            echo html_writer::div('Введите текст для поиска программ...', 'text-muted');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('modal-footer');
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#attachSubjectToProgramModal\').modal(\'hide\');'
            ]);
            echo 'Закрыть';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // JavaScript для модального окна прикрепления к программе
            $PAGE->requires->js_init_code("
                (function() {
                    var currentSubjectId = null;
                    var currentSubjectName = null;
                    
                    // Обработчик открытия модального окна
                    document.querySelectorAll('.attach-subject-to-program').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            currentSubjectId = this.getAttribute('data-subject-id');
                            currentSubjectName = this.getAttribute('data-subject-name');
                            document.getElementById('attachSubjectModalTitle').textContent = 'Прикрепить предмет \"' + currentSubjectName + '\" к программе';
                            
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery('#attachSubjectToProgramModal').modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(document.getElementById('attachSubjectToProgramModal'));
                                modal.show();
                            }
                        });
                    });
                    
                    // Поиск программ
                    var programSearchInput = document.getElementById('program-search');
                    var programsList = document.getElementById('programs-list');
                    var programSearchTimeout;
                    
                    if (programSearchInput) {
                        programSearchInput.addEventListener('input', function() {
                            clearTimeout(programSearchTimeout);
                            var query = this.value.trim();
                            
                            if (query.length < 2) {
                                programsList.innerHTML = '<div class=\"text-muted\">Введите минимум 2 символа для поиска...</div>';
                                return;
                            }
                            
                            programSearchTimeout = setTimeout(function() {
                                var xhr = new XMLHttpRequest();
                                xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getprograms&search=' + encodeURIComponent(query), true);
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4 && xhr.status === 200) {
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.success && response.programs) {
                                                var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>Код</th><th>Действие</th></tr></thead><tbody>';
                                                response.programs.forEach(function(program) {
                                                    html += '<tr><td>' + program.id + '</td><td>' + program.name + '</td><td>' + (program.code || '-') + '</td><td><button class=\"btn btn-sm btn-primary attach-subject-btn\" data-program-id=\"' + program.id + '\">Прикрепить</button></td></tr>';
                                                });
                                                html += '</tbody></table>';
                                                programsList.innerHTML = html;
                                                
                                                // Обработчики кнопок прикрепления
                                                document.querySelectorAll('.attach-subject-btn').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        var programId = this.getAttribute('data-program-id');
                                                        var xhr2 = new XMLHttpRequest();
                                                        xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                        xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                        xhr2.onreadystatechange = function() {
                                                            if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                                var response2 = JSON.parse(xhr2.responseText);
                                                                if (response2.success) {
                                                                    alert('Предмет успешно прикреплен к программе');
                                                                    location.reload();
                                                                } else {
                                                                    alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                                }
                                                            }
                                                        };
                                                        xhr2.send('action=attachsubjecttoprogram&subjectid=' + currentSubjectId + '&programid=' + programId);
                                                    });
                                                });
                                            } else {
                                                programsList.innerHTML = '<div class=\"alert alert-info\">Программы не найдены</div>';
                                            }
                                        } catch (e) {
                                            programsList.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа</div>';
                                        }
                                    }
                                };
                                xhr.send();
                            }, 500);
                        });
                    }
                    
                    // Обработчик удаления предмета
                    document.querySelectorAll('.delete-subject').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (!confirm('Вы уверены, что хотите удалить этот предмет? Все связи с курсами и программами будут удалены.')) {
                                return;
                            }
                            var subjectId = this.getAttribute('data-subject-id');
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                    }
                                }
                            };
                            xhr.send('action=deletesubject&subjectid=' + subjectId);
                        });
                    });
                    
                    // Обработчик кнопки импорта JSON
                    var importBtn = document.getElementById('import-json-btn');
                    if (importBtn) {
                        importBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery('#importJsonModal').modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(document.getElementById('importJsonModal'));
                                modal.show();
                            } else {
                                // Fallback: просто показываем модальное окно через CSS
                                var modal = document.getElementById('importJsonModal');
                                if (modal) {
                                    modal.style.display = 'block';
                                    modal.classList.add('show');
                                    document.body.classList.add('modal-open');
                                }
                            }
                        });
                    }
                })();
            ");
            
            // Модальное окно для добавления курса к предмету (на странице списка предметов)
            echo html_writer::start_div('modal fade', [
                'id' => 'addCourseToSubjectListModal',
                'tabindex' => '-1',
                'role' => 'dialog'
            ]);
            echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
            echo html_writer::start_div('modal-content');
            echo html_writer::start_div('modal-header');
            echo html_writer::tag('h5', 'Добавить курс к предмету', ['class' => 'modal-title', 'id' => 'addCourseToSubjectListModalTitle']);
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'close',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#addCourseToSubjectListModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('form-group');
            echo html_writer::label('Поиск курса', 'course-search-list');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'id' => 'course-search-list',
                'class' => 'form-control',
                'placeholder' => 'Введите название или код курса...'
            ]);
            echo html_writer::end_div();
            echo html_writer::start_div('', ['id' => 'courses-list-subjects', 'style' => 'max-height: 400px; overflow-y: auto;']);
            echo html_writer::div('Введите текст для поиска курсов...', 'text-muted');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::start_div('modal-footer');
            echo html_writer::start_tag('button', [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-dismiss' => 'modal',
                'onclick' => 'jQuery(\'#addCourseToSubjectListModal\').modal(\'hide\');'
            ]);
            echo 'Закрыть';
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            echo html_writer::end_div();
            
            // JavaScript для модального окна прикрепления курса к предмету
            $PAGE->requires->js_init_code("
                (function() {
                    var currentSubjectIdForCourse = null;
                    var currentSubjectNameForCourse = null;
                    var searchInputCourse = document.getElementById('course-search-list');
                    var coursesListSubjects = document.getElementById('courses-list-subjects');
                    var searchTimeoutCourse;
                    
                    // Функция для загрузки курсов
                    function loadCoursesForList(query) {
                        if (!currentSubjectIdForCourse) return;
                        
                        var searchQuery = query || '';
                        if (searchQuery.length > 0 && searchQuery.length < 2) {
                            coursesListSubjects.innerHTML = '<div class=\"text-muted\">Введите минимум 2 символа для поиска...</div>';
                            return;
                        }
                        
                        var xhr = new XMLHttpRequest();
                        var url = '/local/deanpromoodle/pages/admin_ajax.php?action=getcourses&subjectid=' + currentSubjectIdForCourse;
                        if (searchQuery.length >= 2) {
                            url += '&search=' + encodeURIComponent(searchQuery);
                        }
                        // Если запрос пустой, не добавляем параметр search - сервер вернет все доступные курсы
                        
                        xhr.open('GET', url, true);
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success && response.courses && response.courses.length > 0) {
                                        var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>Код</th><th>Действие</th></tr></thead><tbody>';
                                        response.courses.forEach(function(course) {
                                            html += '<tr><td>' + course.id + '</td><td>' + course.fullname + '</td><td>' + (course.shortname || '-') + '</td><td><button class=\"btn btn-sm btn-primary attach-course-to-subject-list-btn\" data-course-id=\"' + course.id + '\">Прикрепить</button></td></tr>';
                                        });
                                        html += '</tbody></table>';
                                        coursesListSubjects.innerHTML = html;
                                        
                                        // Обработчики кнопок прикрепления
                                        document.querySelectorAll('.attach-course-to-subject-list-btn').forEach(function(btn) {
                                            btn.addEventListener('click', function() {
                                                var courseId = this.getAttribute('data-course-id');
                                                var xhr2 = new XMLHttpRequest();
                                                xhr2.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                                                xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                xhr2.onreadystatechange = function() {
                                                    if (xhr2.readyState === 4 && xhr2.status === 200) {
                                                        var response2 = JSON.parse(xhr2.responseText);
                                                        if (response2.success) {
                                                            location.reload();
                                                        } else {
                                                            alert('Ошибка: ' + (response2.error || 'Неизвестная ошибка'));
                                                        }
                                                    }
                                                };
                                                xhr2.send('action=attachcoursetosubject&subjectid=' + currentSubjectIdForCourse + '&courseid=' + courseId);
                                            });
                                        });
                                    } else {
                                        coursesListSubjects.innerHTML = '<div class=\"alert alert-info\">' + (searchQuery.length >= 2 ? 'Курсы не найдены' : 'Введите текст для поиска курсов (минимум 2 символа)') + '</div>';
                                    }
                                } catch (e) {
                                    coursesListSubjects.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа: ' + e.message + '</div>';
                                }
                            }
                        };
                        xhr.send();
                    }
                    
                    // Обработчик открытия модального окна
                    document.querySelectorAll('.add-course-to-subject-list-btn').forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            currentSubjectIdForCourse = this.getAttribute('data-subject-id');
                            currentSubjectNameForCourse = this.getAttribute('data-subject-name');
                            
                            if (!currentSubjectIdForCourse) {
                                alert('Ошибка: не указан ID предмета');
                                return;
                            }
                            
                            if (!searchInputCourse || !coursesListSubjects) {
                                alert('Ошибка: элементы модального окна не найдены');
                                return;
                            }
                            
                            document.getElementById('addCourseToSubjectListModalTitle').textContent = 'Добавить курс к предмету: ' + currentSubjectNameForCourse;
                            
                            searchInputCourse.value = '';
                            coursesListSubjects.innerHTML = '<div class=\"text-muted\">Загрузка курсов...</div>';
                            
                            var modalElement = document.getElementById('addCourseToSubjectListModal');
                            if (!modalElement) {
                                alert('Ошибка: модальное окно не найдено');
                                return;
                            }
                            
                            // Функция для загрузки курсов после открытия модального окна
                            var loadCoursesAfterOpen = function() {
                                setTimeout(function() {
                                    loadCoursesForList('');
                                }, 300);
                            };
                            
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery(modalElement).off('shown.bs.modal'); // Удаляем предыдущие обработчики
                                jQuery(modalElement).on('shown.bs.modal', loadCoursesAfterOpen);
                                jQuery(modalElement).modal('show');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var modal = new bootstrap.Modal(modalElement);
                                modalElement.addEventListener('shown.bs.modal', function handler() {
                                    modalElement.removeEventListener('shown.bs.modal', handler);
                                    loadCoursesAfterOpen();
                                }, { once: true });
                                modal.show();
                            } else {
                                // Fallback
                                modalElement.style.display = 'block';
                                modalElement.classList.add('show');
                                document.body.classList.add('modal-open');
                                loadCoursesAfterOpen();
                            }
                        });
                    });
                    
                    // Поиск курсов
                    if (searchInputCourse) {
                        searchInputCourse.addEventListener('input', function() {
                            clearTimeout(searchTimeoutCourse);
                            var query = this.value.trim();
                            
                            if (!currentSubjectIdForCourse) {
                                coursesListSubjects.innerHTML = '<div class=\"alert alert-warning\">Ошибка: не выбран предмет</div>';
                                return;
                            }
                            
                            searchTimeoutCourse = setTimeout(function() {
                                loadCoursesForList(query);
                            }, 500);
                        });
                    } else {
                        console.error('Элемент поиска курсов не найден');
                    }
                    
                    if (!coursesListSubjects) {
                        console.error('Элемент списка курсов не найден');
                    }
                })();
            ");
        }
        
        echo html_writer::end_div();
        break;
    
    case 'institutions':
        // Вкладка "Учебные заведения"
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        
        // Проверяем существование таблицы БД
        $tablesexist = false;
        $errormsg = '';
        try {
            $DB->get_records('local_deanpromoodle_institutions', null, '', '*', 0, 1);
            $tablesexist = true;
        } catch (\dml_exception $e) {
            $errormsg = 'Таблица учебных заведений не найдена. Выполните обновление базы данных.';
        } catch (\Exception $e) {
            $errormsg = 'Ошибка при проверке таблицы: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
        
        if (!$tablesexist) {
            echo html_writer::div($errormsg, 'alert alert-danger');
            echo html_writer::end_div();
            break;
        }
        
        // Получение параметров
        $action = optional_param('action', '', PARAM_ALPHA);
        $institutionid = optional_param('institutionid', 0, PARAM_INT);
        
        // Обработка создания или редактирования учебного заведения
        if ($action == 'create' || ($action == 'edit' && $institutionid > 0)) {
            $institution = null;
            $isedit = ($action == 'edit' && $institutionid > 0);
            
            if ($isedit) {
                try {
                    $institution = $DB->get_record('local_deanpromoodle_institutions', ['id' => $institutionid]);
                    if (!$institution) {
                        echo html_writer::div('Учебное заведение не найдено.', 'alert alert-danger');
                        echo html_writer::end_div();
                        break;
                    }
                } catch (\Exception $e) {
                    echo html_writer::div('Ошибка при получении учебного заведения: ' . $e->getMessage(), 'alert alert-danger');
                    echo html_writer::end_div();
                    break;
                }
            }
            
            // Обработка отправки формы
            $formsubmitted = optional_param('submit', 0, PARAM_INT);
            if ($formsubmitted) {
                $name = optional_param('name', '', PARAM_TEXT);
                $description = optional_param('description', '', PARAM_RAW);
                $address = optional_param('address', '', PARAM_TEXT);
                $phone = optional_param('phone', '', PARAM_TEXT);
                $email = optional_param('email', '', PARAM_TEXT);
                $website = optional_param('website', '', PARAM_TEXT);
                $logo = optional_param('logo', '', PARAM_TEXT);
                $visible = optional_param('visible', 1, PARAM_INT);
                
                if (empty($name)) {
                    echo html_writer::div('Название учебного заведения обязательно для заполнения.', 'alert alert-danger');
                } else {
                    try {
                        $data = new stdClass();
                        $data->name = $name;
                        $data->description = $description;
                        $data->address = $address;
                        $data->phone = $phone;
                        $data->email = $email;
                        $data->website = $website;
                        $data->logo = $logo;
                        $data->visible = $visible;
                        $data->timemodified = time();
                        
                        if ($isedit) {
                            $data->id = $institutionid;
                            $DB->update_record('local_deanpromoodle_institutions', $data);
                            
                            // Редирект на список учебных заведений
                            redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions']), 'Учебное заведение успешно обновлено', null, \core\output\notification::NOTIFY_SUCCESS);
                        } else {
                            $data->timecreated = time();
                            $institutionid = $DB->insert_record('local_deanpromoodle_institutions', $data);
                            
                            // Редирект на список учебных заведений
                            redirect(new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions']), 'Учебное заведение успешно создано', null, \core\output\notification::NOTIFY_SUCCESS);
                        }
                    } catch (\Exception $e) {
                        echo html_writer::div('Ошибка при сохранении: ' . $e->getMessage(), 'alert alert-danger');
                    }
                }
            }
            
            // Отображение формы создания/редактирования
            $formtitle = $isedit ? 'Редактировать учебное заведение' : 'Создать учебное заведение';
            echo html_writer::tag('h2', $formtitle, ['style' => 'margin-bottom: 20px;']);
            
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', [
                    'tab' => 'institutions',
                    'action' => $action,
                    'institutionid' => $institutionid
                ]),
                'style' => 'max-width: 800px;'
            ]);
            
            // Название *
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Название учебного заведения *', 'name');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'name',
                'id' => 'name',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->name, ENT_QUOTES, 'UTF-8') : '',
                'required' => true
            ]);
            echo html_writer::end_div();
            
            // Описание
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Описание', 'description');
            echo html_writer::start_tag('textarea', [
                'name' => 'description',
                'id' => 'description',
                'class' => 'form-control',
                'rows' => '5'
            ]);
            echo $institution ? htmlspecialchars($institution->description ?? '', ENT_QUOTES, 'UTF-8') : '';
            echo html_writer::end_tag('textarea');
            echo html_writer::end_div();
            
            // Адрес
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Адрес', 'address');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'address',
                'id' => 'address',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->address ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Телефон
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Телефон', 'phone');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'phone',
                'id' => 'phone',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->phone ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Email
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Email', 'email');
            echo html_writer::empty_tag('input', [
                'type' => 'email',
                'name' => 'email',
                'id' => 'email',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->email ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Сайт
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Сайт', 'website');
            echo html_writer::empty_tag('input', [
                'type' => 'url',
                'name' => 'website',
                'id' => 'website',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->website ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Логотип
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Путь к логотипу', 'logo');
            echo html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'logo',
                'id' => 'logo',
                'class' => 'form-control',
                'value' => $institution ? htmlspecialchars($institution->logo ?? '', ENT_QUOTES, 'UTF-8') : ''
            ]);
            echo html_writer::end_div();
            
            // Статус
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Статус', 'visible');
            echo html_writer::select(
                [1 => 'Активно', 0 => 'Скрыто'],
                'visible',
                $institution ? (int)$institution->visible : 1,
                false,
                ['class' => 'form-control']
            );
            echo html_writer::end_div();
            
            // Кнопки
            echo html_writer::start_div('form-group');
            echo html_writer::empty_tag('input', [
                'type' => 'hidden',
                'name' => 'submit',
                'value' => '1'
            ]);
            $submittext = $isedit ? 'Сохранить изменения' : 'Создать учебное заведение';
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => $submittext,
                'class' => 'btn btn-primary',
                'style' => 'margin-right: 10px;'
            ]);
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions']),
                'Отмена',
                ['class' => 'btn btn-secondary']
            );
            echo html_writer::end_div();
            
            echo html_writer::end_tag('form');
            
            echo html_writer::end_div();
            break;
        }
        
        // Обработка импорта из JSON
        $importaction = optional_param('import', '', PARAM_ALPHA);
        if ($importaction == 'json') {
            $importsubmit = optional_param('import_submit', 0, PARAM_INT);
            if ($importsubmit && isset($_FILES['jsonfile']) && $_FILES['jsonfile']['error'] == UPLOAD_ERR_OK) {
                $filepath = $_FILES['jsonfile']['tmp_name'];
                $mimetype = mime_content_type($filepath);
                
                if ($mimetype == 'application/json' || pathinfo($_FILES['jsonfile']['name'], PATHINFO_EXTENSION) == 'json') {
                    $jsoncontent = file_get_contents($filepath);
                    $jsondata = json_decode($jsoncontent, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Определяем структуру данных
                        $institutionsdata = [];
                        if (isset($jsondata['institutions']) && is_array($jsondata['institutions'])) {
                            $institutionsdata = $jsondata['institutions'];
                        } elseif (is_array($jsondata) && isset($jsondata[0]['name'])) {
                            $institutionsdata = $jsondata;
                        }
                        
                        if (!empty($institutionsdata)) {
                            $imported = 0;
                            $updated = 0;
                            $skipped = 0;
                            $errors = [];
                            
                            $transaction = $DB->start_delegated_transaction();
                            try {
                                foreach ($institutionsdata as $index => $institutiondata) {
                                    // Валидация данных
                                    if (empty($institutiondata['name'])) {
                                        $errors[] = 'Учебное заведение #' . ((int)$index + 1) . ': отсутствует название';
                                        $skipped++;
                                        continue;
                                    }
                                    
                                    // Проверяем, существует ли учебное заведение с таким названием
                                    $existing = $DB->get_record('local_deanpromoodle_institutions', ['name' => trim($institutiondata['name'])]);
                                    
                                    $institutionid = null;
                                    if ($existing) {
                                        // Обновляем существующее учебное заведение
                                        $institutionid = $existing->id;
                                        $data = new stdClass();
                                        $data->id = $institutionid;
                                        $data->name = trim($institutiondata['name']);
                                        $data->description = isset($institutiondata['description']) ? ($institutiondata['description'] ?: '') : '';
                                        $data->address = isset($institutiondata['address']) ? trim($institutiondata['address']) : '';
                                        $data->phone = isset($institutiondata['phone']) ? trim($institutiondata['phone']) : '';
                                        $data->email = isset($institutiondata['email']) ? trim($institutiondata['email']) : '';
                                        $data->website = isset($institutiondata['website']) ? trim($institutiondata['website']) : '';
                                        $data->logo = isset($institutiondata['logo']) ? trim($institutiondata['logo']) : '';
                                        $data->visible = isset($institutiondata['is_active']) ? ($institutiondata['is_active'] ? 1 : 0) : 1;
                                        $data->timemodified = time();
                                        $DB->update_record('local_deanpromoodle_institutions', $data);
                                        $updated++;
                                    } else {
                                        // Создаем новое учебное заведение
                                        $data = new stdClass();
                                        $data->name = trim($institutiondata['name']);
                                        $data->description = isset($institutiondata['description']) ? ($institutiondata['description'] ?: '') : '';
                                        $data->address = isset($institutiondata['address']) ? trim($institutiondata['address']) : '';
                                        $data->phone = isset($institutiondata['phone']) ? trim($institutiondata['phone']) : '';
                                        $data->email = isset($institutiondata['email']) ? trim($institutiondata['email']) : '';
                                        $data->website = isset($institutiondata['website']) ? trim($institutiondata['website']) : '';
                                        $data->logo = isset($institutiondata['logo']) ? trim($institutiondata['logo']) : '';
                                        $data->visible = isset($institutiondata['is_active']) ? ($institutiondata['is_active'] ? 1 : 0) : 1;
                                        $data->timecreated = time();
                                        $data->timemodified = time();
                                        $institutionid = $DB->insert_record('local_deanpromoodle_institutions', $data);
                                        $imported++;
                                    }
                                }
                                
                                $transaction->allow_commit();
                                
                                $message = 'Импорт завершен. Импортировано: ' . $imported . ', обновлено: ' . $updated;
                                if ($skipped > 0) {
                                    $message .= ', пропущено: ' . $skipped;
                                }
                                if (!empty($errors)) {
                                    $message .= '<br>Ошибки:<br>' . implode('<br>', array_slice($errors, 0, 10));
                                    if (count($errors) > 10) {
                                        $message .= '<br>... и еще ' . (count($errors) - 10) . ' ошибок';
                                    }
                                }
                                echo html_writer::div($message, 'alert alert-success');
                            } catch (\Exception $e) {
                                $transaction->rollback($e);
                                echo html_writer::div('Ошибка при импорте: ' . $e->getMessage(), 'alert alert-danger');
                            }
                        } else {
                            echo html_writer::div('В JSON файле не найдены данные об учебных заведениях.', 'alert alert-warning');
                        }
                    } else {
                        echo html_writer::div('Ошибка при разборе JSON: ' . json_last_error_msg(), 'alert alert-danger');
                    }
                } else {
                    echo html_writer::div('Неверный тип файла. Ожидается JSON файл.', 'alert alert-danger');
                }
            }
        }
        
        // Заголовок с кнопками
        echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
        echo html_writer::start_div('', ['style' => 'display: flex; align-items: center; gap: 10px;']);
        echo html_writer::tag('i', '', ['class' => 'fas fa-university', 'style' => 'font-size: 24px;']);
        echo html_writer::tag('h2', 'Учебные заведения', ['style' => 'margin: 0; font-size: 24px; font-weight: 600;']);
        echo html_writer::end_div();
        echo html_writer::start_div('', ['style' => 'display: flex; gap: 10px;']);
        echo html_writer::link(
            new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions', 'action' => 'create']),
            '+ Добавить учебное заведение',
            [
                'class' => 'btn btn-primary',
                'style' => 'background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
            ]
        );
        echo html_writer::link('#', '<i class="fas fa-file-import"></i> Импорт из JSON', [
            'class' => 'btn btn-success',
            'id' => 'import-institutions-json-btn',
            'style' => 'background-color: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Модальное окно для импорта JSON
        echo html_writer::start_div('modal fade', [
            'id' => 'importInstitutionsJsonModal',
            'tabindex' => '-1',
            'role' => 'dialog'
        ]);
        echo html_writer::start_div('modal-dialog', ['role' => 'document']);
        echo html_writer::start_div('modal-content');
        echo html_writer::start_div('modal-header');
        echo html_writer::tag('h5', 'Импорт учебных заведений из JSON', ['class' => 'modal-title']);
        echo html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'close',
            'data-dismiss' => 'modal',
            'onclick' => 'jQuery(\'#importInstitutionsJsonModal\').modal(\'hide\');'
        ]);
        echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
        echo html_writer::end_tag('button');
        echo html_writer::end_div();
        echo html_writer::start_div('modal-body');
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions', 'import' => 'json']),
            'enctype' => 'multipart/form-data'
        ]);
        echo html_writer::start_div('form-group');
        echo html_writer::label('Выберите JSON файл', 'jsonfile');
        echo html_writer::empty_tag('input', [
            'type' => 'file',
            'name' => 'jsonfile',
            'id' => 'jsonfile-institutions',
            'class' => 'form-control-file',
            'accept' => '.json,application/json',
            'required' => true
        ]);
        echo html_writer::start_div('form-text text-muted', ['style' => 'margin-top: 5px;']);
        echo 'Формат JSON файла:<br>';
        echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 11px; margin-top: 5px; max-height: 200px; overflow-y: auto;">';
        echo '{<br>';
        echo '  "institutions": [<br>';
        echo '    {<br>';
        echo '      "name": "Название учебного заведения",<br>';
        echo '      "description": "Описание",<br>';
        echo '      "address": "Адрес",<br>';
        echo '      "phone": "+7 (495) 123-45-67",<br>';
        echo '      "email": "email@example.com",<br>';
        echo '      "website": "https://example.com",<br>';
        echo '      "logo": "path/to/logo.png",<br>';
        echo '      "is_active": true<br>';
        echo '    }<br>';
        echo '  ]<br>';
        echo '}</pre>';
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::start_div('form-group');
        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'import_submit',
            'value' => '1'
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => 'Импортировать',
            'class' => 'btn btn-success',
            'style' => 'margin-right: 10px;'
        ]);
        echo html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-dismiss' => 'modal',
            'onclick' => 'jQuery(\'#importInstitutionsJsonModal\').modal(\'hide\');'
        ]);
        echo 'Отмена';
        echo html_writer::end_tag('button');
        echo html_writer::end_div();
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
        
        // Получение всех учебных заведений
        $institutions = [];
        try {
            $institutions = $DB->get_records('local_deanpromoodle_institutions', null, 'name ASC');
        } catch (\dml_exception $e) {
            echo html_writer::div('Ошибка при получении учебных заведений из БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
            echo html_writer::end_div();
            break;
        } catch (\Exception $e) {
            echo html_writer::div('Ошибка при получении учебных заведений из БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'alert alert-danger');
            echo html_writer::end_div();
            break;
        }
        
        if (empty($institutions)) {
            echo html_writer::div('Учебные заведения не найдены. Импортируйте данные из JSON файла.', 'alert alert-info');
        } else {
            // Стили для таблицы
            echo html_writer::start_tag('style');
            echo "
                .institutions-table {
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .institutions-table table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .institutions-table th {
                    background-color: #f8f9fa;
                    padding: 12px 16px;
                    text-align: left;
                    font-weight: 600;
                    color: #495057;
                    border-bottom: 1px solid #dee2e6;
                    font-size: 14px;
                }
                .institutions-table td {
                    padding: 16px;
                    border-bottom: 1px solid #f0f0f0;
                    vertical-align: middle;
                }
                .institutions-table tr:hover {
                    background-color: #f8f9fa;
                }
                .institution-logo {
                    width: 50px;
                    height: 50px;
                    object-fit: contain;
                    border-radius: 4px;
                }
            ";
            echo html_writer::end_tag('style');
            
            // Отображение таблицы
            echo html_writer::start_div('institutions-table');
            echo html_writer::start_tag('table');
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'ID', ['style' => 'width: 60px; text-align: center;']);
            echo html_writer::tag('th', 'Логотип', ['style' => 'width: 80px;']);
            echo html_writer::tag('th', 'Название');
            echo html_writer::tag('th', 'Адрес', ['style' => 'width: 200px;']);
            echo html_writer::tag('th', 'Контакты', ['style' => 'width: 250px;']);
            echo html_writer::tag('th', 'Сайт', ['style' => 'width: 150px;']);
            echo html_writer::tag('th', 'Статус', ['style' => 'width: 100px;']);
            echo html_writer::tag('th', 'Действия', ['style' => 'width: 120px; text-align: center;']);
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            foreach ($institutions as $institution) {
                // Подсчет программ для этого учебного заведения
                try {
                    $programscount = $DB->count_records('local_deanpromoodle_programs', ['institution' => $institution->name]);
                } catch (\Exception $e) {
                    $programscount = 0;
                }
                
                echo html_writer::start_tag('tr');
                
                // ID
                echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
                echo htmlspecialchars((string)$institution->id, ENT_QUOTES, 'UTF-8');
                echo html_writer::end_tag('td');
                
                // Логотип
                echo html_writer::start_tag('td');
                if (!empty($institution->logo)) {
                    echo html_writer::empty_tag('img', [
                        'src' => htmlspecialchars($institution->logo, ENT_QUOTES, 'UTF-8'),
                        'alt' => htmlspecialchars($institution->name, ENT_QUOTES, 'UTF-8'),
                        'class' => 'institution-logo'
                    ]);
                } else {
                    echo '-';
                }
                echo html_writer::end_tag('td');
                
                // Название
                echo html_writer::start_tag('td');
                echo html_writer::tag('strong', htmlspecialchars($institution->name, ENT_QUOTES, 'UTF-8'));
                if (!empty($institution->description)) {
                    echo html_writer::tag('div', htmlspecialchars(mb_substr($institution->description, 0, 100), ENT_QUOTES, 'UTF-8') . (mb_strlen($institution->description) > 100 ? '...' : ''), ['style' => 'font-size: 12px; color: #6c757d; margin-top: 4px;']);
                }
                echo html_writer::end_tag('td');
                
                // Адрес
                echo html_writer::start_tag('td');
                echo !empty($institution->address) ? htmlspecialchars($institution->address, ENT_QUOTES, 'UTF-8') : '-';
                echo html_writer::end_tag('td');
                
                // Контакты
                echo html_writer::start_tag('td');
                $contacts = [];
                if (!empty($institution->phone)) {
                    $contacts[] = '<i class="fas fa-phone"></i> ' . htmlspecialchars($institution->phone, ENT_QUOTES, 'UTF-8');
                }
                if (!empty($institution->email)) {
                    $contacts[] = '<i class="fas fa-envelope"></i> ' . htmlspecialchars($institution->email, ENT_QUOTES, 'UTF-8');
                }
                echo !empty($contacts) ? implode('<br>', $contacts) : '-';
                echo html_writer::end_tag('td');
                
                // Сайт
                echo html_writer::start_tag('td');
                if (!empty($institution->website)) {
                    echo html_writer::link(
                        htmlspecialchars($institution->website, ENT_QUOTES, 'UTF-8'),
                        '<i class="fas fa-external-link-alt"></i> Открыть',
                        ['target' => '_blank', 'style' => 'text-decoration: none;']
                    );
                } else {
                    echo '-';
                }
                echo html_writer::end_tag('td');
                
                // Статус
                echo html_writer::start_tag('td');
                if ($institution->visible) {
                    echo '<span class="badge badge-active"><i class="fas fa-check"></i> Активно</span>';
                } else {
                    echo '<span class="badge" style="background-color: #9e9e9e; color: white;">Скрыто</span>';
                }
                echo html_writer::end_tag('td');
                
                // Действия
                echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
                echo html_writer::start_div('action-buttons');
                echo html_writer::link(
                    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'institutions', 'action' => 'edit', 'institutionid' => $institution->id]),
                    '<i class="fas fa-edit"></i>',
                    [
                        'class' => 'action-btn action-btn-edit',
                        'title' => 'Редактировать'
                    ]
                );
                echo html_writer::link('#', '<i class="fas fa-times"></i>', [
                    'class' => 'action-btn action-btn-delete delete-institution',
                    'title' => 'Удалить',
                    'data-institution-id' => $institution->id,
                    'data-institution-name' => htmlspecialchars($institution->name, ENT_QUOTES, 'UTF-8')
                ]);
                echo html_writer::end_div();
                echo html_writer::end_tag('td');
                
                echo html_writer::end_tag('tr');
            }
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            echo html_writer::end_div();
        }
        
        // JavaScript для модального окна и удаления
        echo html_writer::start_tag('script');
        echo "
        document.addEventListener('DOMContentLoaded', function() {
            var importBtn = document.getElementById('import-institutions-json-btn');
            if (importBtn) {
                importBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    jQuery('#importInstitutionsJsonModal').modal('show');
                });
            }
            
            // Обработка удаления учебного заведения
            document.querySelectorAll('.delete-institution').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var institutionId = this.getAttribute('data-institution-id');
                    var institutionName = this.getAttribute('data-institution-name');
                    
                    if (!confirm('Вы уверены, что хотите удалить учебное заведение \"' + institutionName + '\"?')) {
                        return;
                    }
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '/local/deanpromoodle/pages/admin_ajax.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        window.location.reload();
                                    } else {
                                        alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                    }
                                } catch (e) {
                                    alert('Ошибка при обработке ответа сервера');
                                }
                            } else {
                                alert('Ошибка при отправке запроса');
                            }
                        }
                    };
                    xhr.send('action=deleteinstitution&institutionid=' + institutionId);
                });
            });
        });
        ";
        echo html_writer::end_tag('script');
        
        echo html_writer::end_div();
        break;
    
    case 'categories':
        // Вкладка "Категории курсов" с поддержкой вложенных категорий
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Категории курсов', ['style' => 'margin-bottom: 20px;']);
        
        // Добавляем стили для дерева категорий
        echo html_writer::start_tag('style');
        echo "
            .category-row {
                transition: background-color 0.2s;
            }
            .category-row:hover {
                background-color: #f5f5f5;
            }
            .category-toggle {
                user-select: none;
                transition: color 0.2s;
            }
            .category-toggle:hover {
                color: #007bff !important;
            }
            .category-child {
                background-color: #fafafa;
            }
        ";
        echo html_writer::end_tag('style');
        
        // Получение всех категорий курсов
        $allcategories = $DB->get_records('course_categories', null, 'name ASC');
        
        if (empty($allcategories)) {
            echo html_writer::div('Категории курсов не найдены.', 'alert alert-info');
        } else {
            // Функция для получения всех дочерних категорий рекурсивно
            $getchildcategories = function($parentid, $categories) use (&$getchildcategories) {
                $children = [];
                $parentid = is_scalar($parentid) ? (int)$parentid : 0;
                foreach ($categories as $cat) {
                    $catparent = is_scalar($cat->parent) ? (int)$cat->parent : 0;
                    $catid = is_scalar($cat->id) ? (int)$cat->id : 0;
                    if ($catparent == $parentid && $catid > 0) {
                        $children[] = $catid;
                        $subchildren = $getchildcategories($catid, $categories);
                        $children = array_merge($children, $subchildren);
                    }
                }
                return $children;
            };
            
            // Функция для подсчета статистики категории (включая дочерние)
            $calculatecategorystats = function($categoryid, $allcategories) use ($DB, $getchildcategories) {
                // Безопасное преобразование ID категории
                $categoryid = is_scalar($categoryid) ? (int)$categoryid : 0;
                // Получаем все дочерние категории
                $childids = $getchildcategories($categoryid, $allcategories);
                $allcategoryids = array_merge([$categoryid], $childids);
                
                // Подсчет курсов
                $placeholders = implode(',', array_fill(0, count($allcategoryids), '?'));
                $coursescount = $DB->count_records_sql(
                    "SELECT COUNT(*) FROM {course} WHERE category IN ($placeholders) AND id > 1",
                    $allcategoryids
                );
                
                // Получаем все курсы для подсчета студентов и преподавателей
                $allcourses = $DB->get_records_select('course', "category IN ($placeholders) AND id > 1", $allcategoryids, '', 'id');
                $courseids = array_keys($allcourses);
                
                $studentscount = 0;
                $teacherscount = 0;
                
                if (!empty($courseids)) {
                    // Подсчет студентов
                    $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
                    if ($studentroleid) {
                        $courseids_placeholders = implode(',', array_fill(0, count($courseids), '?'));
                        $coursecontextids = $DB->get_fieldset_sql(
                            "SELECT id FROM {context} WHERE instanceid IN ($courseids_placeholders) AND contextlevel = 50",
                            $courseids
                        );
                        
                        if (!empty($coursecontextids)) {
                            $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
                            $studentscount = $DB->count_records_sql(
                                "SELECT COUNT(DISTINCT ra.userid)
                                 FROM {role_assignments} ra
                                 WHERE ra.contextid IN ($contextplaceholders)
                                 AND ra.roleid = ?",
                                array_merge($coursecontextids, [$studentroleid])
                            );
                        }
                    }
                    
                    // Подсчет преподавателей
                    $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
                    if (!empty($teacherroleids)) {
                        if (!empty($coursecontextids)) {
                            $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
                            $roleplaceholders = implode(',', array_fill(0, count($teacherroleids), '?'));
                            $teacherscount = $DB->count_records_sql(
                                "SELECT COUNT(DISTINCT ra.userid)
                                 FROM {role_assignments} ra
                                 WHERE ra.contextid IN ($contextplaceholders)
                                 AND ra.roleid IN ($roleplaceholders)",
                                array_merge($coursecontextids, $teacherroleids)
                            );
                        }
                    }
                }
                
                // Убеждаемся, что все значения - числа
                return [
                    'coursescount' => is_scalar($coursescount) ? (int)$coursescount : 0,
                    'studentscount' => is_scalar($studentscount) ? (int)$studentscount : 0,
                    'teacherscount' => is_scalar($teacherscount) ? (int)$teacherscount : 0
                ];
            };
            
            // Функция для рекурсивного отображения категорий
            $rendercategorytree = function($parentid, $categories, $level = 0, $parentrowid = null) use (&$rendercategorytree, $calculatecategorystats, $allcategories) {
                $children = [];
                
                // Находим дочерние категории
                foreach ($categories as $cat) {
                    if ($cat->parent == $parentid) {
                        $children[] = $cat;
                    }
                }
                
                // Сортируем по имени с безопасным преобразованием
                usort($children, function($a, $b) {
                    $nameA = is_string($a->name) ? $a->name : (is_array($a->name) ? implode(', ', array_filter($a->name, 'is_string')) : (string)$a->name);
                    $nameB = is_string($b->name) ? $b->name : (is_array($b->name) ? implode(', ', array_filter($b->name, 'is_string')) : (string)$b->name);
                    return strcmp($nameA, $nameB);
                });
                
                // Отображаем каждую категорию
                foreach ($children as $category) {
                    // Безопасное преобразование ID категории
                    $catid = is_scalar($category->id) ? (int)$category->id : 0;
                    $stats = $calculatecategorystats($catid, $allcategories);
                    $haschildren = false;
                    
                    // Проверяем, есть ли дочерние категории
                    foreach ($categories as $cat) {
                        $catparent = is_scalar($cat->parent) ? (int)$cat->parent : 0;
                        $checkcatid = is_scalar($category->id) ? (int)$category->id : 0;
                        if ($catparent == $checkcatid) {
                            $haschildren = true;
                            break;
                        }
                    }
                    
                    // Безопасное преобразование ID категории в строку
                    $categoryid = is_scalar($category->id) ? (string)$category->id : (is_array($category->id) ? implode('-', $category->id) : '0');
                    
                    // Безопасное преобразование parentrowid в строку
                    $parentrowidstr = '';
                    if ($parentrowid) {
                        $parentrowidstr = is_scalar($parentrowid) ? (string)$parentrowid : (is_array($parentrowid) ? implode('-', $parentrowid) : '');
                    }
                    
                    // Убеждаемся, что categoryid - строка
                    $categoryid = (string)$categoryid;
                    $rowid = 'category-row-' . $categoryid;
                    
                    // Убеждаемся, что rowid - строка
                    $rowid = (string)$rowid;
                    
                    $rowclass = 'category-row';
                    $rowstyle = '';
                    
                    // Если это дочерняя категория, скрываем по умолчанию
                    if ($level > 0 && $parentrowidstr) {
                        $rowclass .= ' category-child category-child-of-' . $parentrowidstr;
                        $rowstyle = 'display: none;';
                    }
                    
                    $indentstyle = 'padding-left: ' . ($level * 30) . 'px;';
                    
                    echo html_writer::start_tag('tr', [
                        'id' => $rowid,
                        'class' => $rowclass,
                        'data-category-id' => $categoryid,
                        'data-level' => (string)$level,
                        'data-parent-id' => $parentrowidstr,
                        'style' => $rowstyle
                    ]);
                    
                    // Колонка ID - убеждаемся, что это строка
                    echo html_writer::tag('td', (string)$categoryid);
                    
                    // Колонка названия с кнопкой раскрытия/сворачивания
                    $namecell = '';
                    if ($haschildren) {
                        $linkresult = html_writer::link('#', '▶', [
                            'class' => 'category-toggle',
                            'data-category-id' => (string)$categoryid,
                            'data-row-id' => (string)$rowid,
                            'style' => 'text-decoration: none; color: #666; margin-right: 5px; font-size: 12px; display: inline-block; width: 15px;',
                            'title' => 'Раскрыть/свернуть'
                        ]);
                        $namecell .= is_string($linkresult) ? $linkresult : (string)$linkresult;
                    } else {
                        // Используем обычный HTML вместо html_writer::span() для избежания проблем с типами
                        $namecell .= '<span style="display: inline-block; width: 15px;"></span>';
                    }
                    // Безопасное преобразование имени категории в строку
                    $categoryname = 'Без названия';
                    try {
                        if (isset($category->name)) {
                            if (is_string($category->name)) {
                                $categoryname = $category->name;
                            } elseif (is_array($category->name)) {
                                $categoryname = implode(', ', array_filter($category->name, function($v) { return is_string($v) || is_numeric($v); }));
                                if (empty($categoryname)) {
                                    $categoryname = 'Без названия';
                                }
                            } elseif (is_object($category->name)) {
                                // Попробуем использовать __toString если доступен
                                if (method_exists($category->name, '__toString')) {
                                    $categoryname = (string)$category->name;
                                } elseif (method_exists($category->name, 'out')) {
                                    // Для lang_string объектов Moodle
                                    $categoryname = $category->name->out();
                                } else {
                                    $categoryname = 'Без названия';
                                }
                            } elseif (is_scalar($category->name)) {
                                $categoryname = (string)$category->name;
                            }
                        }
                    } catch (Exception $e) {
                        $categoryname = 'Без названия';
                    } catch (Throwable $e) {
                        $categoryname = 'Без названия';
                    }
                    // Финальная проверка - убеждаемся, что это строка
                    if (!is_string($categoryname)) {
                        if (is_array($categoryname)) {
                            $categoryname = json_encode($categoryname, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        } else {
                            $categoryname = 'Без названия';
                        }
                    }
                    // Дополнительная проверка перед htmlspecialchars
                    if (is_string($categoryname)) {
                        $namecell .= htmlspecialchars($categoryname, ENT_QUOTES, 'UTF-8');
                    } else {
                        $namecell .= 'Без названия';
                    }
                    // Убеждаемся, что namecell - строка перед использованием
                    $namecell = is_string($namecell) ? $namecell : (string)$namecell;
                    // Убеждаемся, что indentstyle - строка
                    $indentstyle = is_string($indentstyle) ? $indentstyle : (string)$indentstyle;
                    echo html_writer::tag('td', $namecell, ['style' => $indentstyle]);
                    
                    // Количество курсов - ссылка если > 0
                    $coursescountval = is_scalar($stats['coursescount']) ? (int)$stats['coursescount'] : 0;
                    $coursescell = (string)$coursescountval;
                    if ($coursescountval > 0) {
                        $linkresult = html_writer::link('#', (string)$coursescountval, [
                            'class' => 'category-link',
                            'data-category-id' => (string)$categoryid,
                            'data-type' => 'courses',
                            'data-category-name' => htmlspecialchars($categoryname, ENT_QUOTES, 'UTF-8'),
                            'style' => 'color: #007bff; font-weight: bold; text-decoration: none; cursor: pointer;'
                        ]);
                        $coursescell = is_string($linkresult) ? $linkresult : (string)$linkresult;
                    }
                    // Убеждаемся, что coursescell - строка
                    $coursescell = is_string($coursescell) ? $coursescell : (string)$coursescell;
                    $strongcontent = html_writer::tag('strong', $coursescell);
                    $strongcontent = is_string($strongcontent) ? $strongcontent : (string)$strongcontent;
                    echo html_writer::tag('td', $strongcontent);
                    
                    // Количество студентов - ссылка если > 0
                    $studentscountval = is_scalar($stats['studentscount']) ? (int)$stats['studentscount'] : 0;
                    $studentscell = (string)$studentscountval;
                    if ($studentscountval > 0) {
                        $linkresult = html_writer::link('#', (string)$studentscountval, [
                            'class' => 'category-link',
                            'data-category-id' => (string)$categoryid,
                            'data-type' => 'students',
                            'data-category-name' => htmlspecialchars($categoryname, ENT_QUOTES, 'UTF-8'),
                            'style' => 'color: green; font-weight: bold; text-decoration: none; cursor: pointer;'
                        ]);
                        $studentscell = is_string($linkresult) ? $linkresult : (string)$linkresult;
                    }
                    // Убеждаемся, что studentscell - строка
                    $studentscell = is_string($studentscell) ? $studentscell : (string)$studentscell;
                    $spancontent = html_writer::tag('span', $studentscell);
                    $spancontent = is_string($spancontent) ? $spancontent : (string)$spancontent;
                    echo html_writer::tag('td', $spancontent);
                    
                    // Количество преподавателей - ссылка если > 0
                    $teacherscountval = is_scalar($stats['teacherscount']) ? (int)$stats['teacherscount'] : 0;
                    $teacherscell = (string)$teacherscountval;
                    if ($teacherscountval > 0) {
                        $linkresult = html_writer::link('#', (string)$teacherscountval, [
                            'class' => 'category-link',
                            'data-category-id' => (string)$categoryid,
                            'data-type' => 'teachers',
                            'data-category-name' => htmlspecialchars($categoryname, ENT_QUOTES, 'UTF-8'),
                            'style' => 'color: orange; font-weight: bold; text-decoration: none; cursor: pointer;'
                        ]);
                        $teacherscell = is_string($linkresult) ? $linkresult : (string)$linkresult;
                    }
                    // Убеждаемся, что teacherscell - строка
                    $teacherscell = is_string($teacherscell) ? $teacherscell : (string)$teacherscell;
                    $spancontent2 = html_writer::tag('span', $teacherscell);
                    $spancontent2 = is_string($spancontent2) ? $spancontent2 : (string)$spancontent2;
                    echo html_writer::tag('td', $spancontent2);
                    
                    // Статус - безопасное преобразование visible
                    $isvisible = false;
                    if (is_bool($category->visible)) {
                        $isvisible = $category->visible;
                    } elseif (is_numeric($category->visible)) {
                        $isvisible = (bool)$category->visible;
                    } elseif (is_string($category->visible)) {
                        $isvisible = ($category->visible === '1' || strtolower($category->visible) === 'true');
                    } elseif (is_array($category->visible)) {
                        $isvisible = !empty($category->visible);
                    }
                    $status = $isvisible ? html_writer::tag('span', 'Активна', ['style' => 'color: green;']) : html_writer::tag('span', 'Скрыта', ['style' => 'color: red;']);
                    echo html_writer::tag('td', $status);
                    
                    echo html_writer::end_tag('tr');
                    
                    // Рекурсивно отображаем дочерние категории
                    if ($haschildren) {
                        $rendercategorytree((int)$categoryid, $categories, $level + 1, $rowid);
                    }
                }
            };
            
            // Отображение таблицы
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'ID');
            echo html_writer::tag('th', 'Название категории');
            echo html_writer::tag('th', 'Количество курсов');
            echo html_writer::tag('th', 'Количество студентов');
            echo html_writer::tag('th', 'Количество преподавателей');
            echo html_writer::tag('th', 'Статус');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            // Отображаем дерево категорий, начиная с корневых (parent = 0)
            $rendercategorytree(0, $allcategories);
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        }
        
        // Модальное окно для отображения списка курсов/студентов/преподавателей категории
        echo html_writer::start_div('modal fade', [
            'id' => 'categoryDetailsModal',
            'tabindex' => '-1',
            'role' => 'dialog',
            'aria-labelledby' => 'categoryDetailsModalLabel',
            'aria-hidden' => 'true'
        ]);
        echo html_writer::start_div('modal-dialog modal-lg', ['role' => 'document']);
        echo html_writer::start_div('modal-content');
        echo html_writer::start_div('modal-header');
        echo html_writer::tag('h5', 'Детали категории', ['class' => 'modal-title', 'id' => 'categoryDetailsModalLabel']);
        echo html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'close',
            'data-dismiss' => 'modal',
            'aria-label' => 'Закрыть',
            'onclick' => 'jQuery(\'#categoryDetailsModal\').modal(\'hide\');'
        ]);
        echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
        echo html_writer::end_tag('button');
        echo html_writer::end_div(); // modal-header
        echo html_writer::start_div('modal-body', ['id' => 'modalCategoryDetails']);
        echo html_writer::div('Загрузка...', 'text-center');
        echo html_writer::end_div(); // modal-body
        echo html_writer::start_div('modal-footer');
        echo html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-dismiss' => 'modal',
            'onclick' => 'jQuery(\'#categoryDetailsModal\').modal(\'hide\');'
        ]);
        echo 'Закрыть';
        echo html_writer::end_tag('button');
        echo html_writer::end_div(); // modal-footer
        echo html_writer::end_div(); // modal-content
        echo html_writer::end_div(); // modal-dialog
        echo html_writer::end_div(); // modal
        
        // JavaScript для раскрытия/сворачивания дочерних категорий и загрузки данных
        $PAGE->requires->js_init_code("
            (function() {
                // Функция для раскрытия/сворачивания дочерних категорий
                function toggleCategoryChildren(categoryId, rowId) {
                    var childRows = document.querySelectorAll('.category-child-of-' + rowId);
                    var toggleButton = document.querySelector('.category-toggle[data-category-id=\"' + categoryId + '\"]');
                    
                    if (!toggleButton) return;
                    
                    var isExpanded = toggleButton.getAttribute('data-expanded') === 'true';
                    
                    childRows.forEach(function(row) {
                        if (isExpanded) {
                            // Сворачиваем
                            row.style.display = 'none';
                            // Также сворачиваем все вложенные дочерние категории
                            var nestedCategoryId = row.getAttribute('data-category-id');
                            var nestedToggle = row.querySelector('.category-toggle');
                            if (nestedToggle) {
                                nestedToggle.setAttribute('data-expanded', 'false');
                                nestedToggle.textContent = '▶';
                                var nestedRowId = nestedToggle.getAttribute('data-row-id');
                                if (nestedRowId) {
                                    var nestedChildren = document.querySelectorAll('.category-child-of-' + nestedRowId);
                                    nestedChildren.forEach(function(nestedRow) {
                                        nestedRow.style.display = 'none';
                                    });
                                }
                            }
                        } else {
                            // Раскрываем
                            row.style.display = '';
                        }
                    });
                    
                    // Обновляем состояние кнопки
                    if (isExpanded) {
                        toggleButton.setAttribute('data-expanded', 'false');
                        toggleButton.textContent = '▶';
                    } else {
                        toggleButton.setAttribute('data-expanded', 'true');
                        toggleButton.textContent = '▼';
                    }
                }
                
                // Обработчики кликов на кнопки раскрытия/сворачивания
                var categoryToggles = document.querySelectorAll('.category-toggle');
                categoryToggles.forEach(function(toggle) {
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var categoryId = this.getAttribute('data-category-id');
                        var rowId = this.getAttribute('data-row-id');
                        toggleCategoryChildren(categoryId, rowId);
                    });
                });
                
                // Обработчики для ссылок на цифры (модальное окно)
                var categoryLinks = document.querySelectorAll('.category-link');
                categoryLinks.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var categoryId = this.getAttribute('data-category-id');
                        var dataType = this.getAttribute('data-type');
                        var categoryName = this.getAttribute('data-category-name');
                        var modal = document.getElementById('categoryDetailsModal');
                        var modalTitle = document.getElementById('categoryDetailsModalLabel');
                        var modalBody = document.getElementById('modalCategoryDetails');
                        
                        var typeLabels = {
                            'courses': 'Курсы',
                            'students': 'Студенты',
                            'teachers': 'Преподаватели'
                        };
                        
                        modalTitle.textContent = typeLabels[dataType] + ' категории: ' + categoryName;
                        modalBody.innerHTML = '<div class=\"text-center\">Загрузка...</div>';
                        
                        // AJAX запрос для получения данных
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getcategory' + dataType + '&categoryid=' + categoryId, true);
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            var html = '';
                                            if (dataType === 'courses') {
                                                html = '<table class=\"table table-striped table-hover\"><thead><tr><th>ID</th><th>Название курса</th><th>Краткое название</th><th>Дата начала</th><th>Дата окончания</th></tr></thead><tbody>';
                                                if (response.courses && response.courses.length > 0) {
                                                    response.courses.forEach(function(course) {
                                                        html += '<tr><td>' + course.id + '</td><td>' + course.fullname + '</td><td>' + (course.shortname || '-') + '</td><td>' + (course.startdate || '-') + '</td><td>' + (course.enddate || '-') + '</td></tr>';
                                                    });
                                                } else {
                                                    html += '<tr><td colspan=\"5\" class=\"text-center\">Курсы не найдены</td></tr>';
                                                }
                                                html += '</tbody></table>';
                                            } else if (dataType === 'students') {
                                                html = '<table class=\"table table-striped table-hover\"><thead><tr><th>ID</th><th>ФИО</th><th>Email</th><th>Дата регистрации</th><th>Последний вход</th></tr></thead><tbody>';
                                                if (response.students && response.students.length > 0) {
                                                    response.students.forEach(function(student) {
                                                        html += '<tr><td>' + student.id + '</td><td>' + student.fullname + '</td><td>' + (student.email || '-') + '</td><td>' + (student.timecreated || '-') + '</td><td>' + (student.lastaccess || '-') + '</td></tr>';
                                                    });
                                                } else {
                                                    html += '<tr><td colspan=\"5\" class=\"text-center\">Студенты не найдены</td></tr>';
                                                }
                                                html += '</tbody></table>';
                                            } else if (dataType === 'teachers') {
                                                html = '<table class=\"table table-striped table-hover\"><thead><tr><th>ID</th><th>ФИО</th><th>Email</th><th>Роль</th><th>Дата регистрации</th></tr></thead><tbody>';
                                                if (response.teachers && response.teachers.length > 0) {
                                                    response.teachers.forEach(function(teacher) {
                                                        html += '<tr><td>' + teacher.id + '</td><td>' + teacher.fullname + '</td><td>' + (teacher.email || '-') + '</td><td>' + (teacher.role || '-') + '</td><td>' + (teacher.timecreated || '-') + '</td></tr>';
                                                    });
                                                } else {
                                                    html += '<tr><td colspan=\"5\" class=\"text-center\">Преподаватели не найдены</td></tr>';
                                                }
                                                html += '</tbody></table>';
                                            }
                                            modalBody.innerHTML = html;
                                        } else {
                                            modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка: ' + (response.error || 'Неизвестная ошибка') + '</div>';
                                        }
                                    } catch (e) {
                                        modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа сервера</div>';
                                    }
                                } else {
                                    modalBody.innerHTML = '<div class=\"alert alert-danger\">Ошибка загрузки данных</div>';
                                }
                            }
                        };
                        xhr.send();
                        
                        // Показываем модальное окно (Bootstrap/jQuery)
                        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                            jQuery(modal).modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var bsModal = new bootstrap.Modal(modal);
                            bsModal.show();
                        } else {
                            // Fallback: просто показываем модальное окно через CSS
                            modal.style.display = 'block';
                            modal.classList.add('show');
                            document.body.classList.add('modal-open');
                        }
                    });
                });
                
                // Обработчик закрытия модального окна при клике вне его
                var modalElement = document.getElementById('categoryDetailsModal');
                if (modalElement) {
                    modalElement.addEventListener('click', function(e) {
                        if (e.target === modalElement) {
                            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery(modalElement).modal('hide');
                            } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                var bsModal = bootstrap.Modal.getInstance(modalElement);
                                if (bsModal) {
                                    bsModal.hide();
                                }
                            } else {
                                modalElement.style.display = 'none';
                                modalElement.classList.remove('show');
                                document.body.classList.remove('modal-open');
                            }
                        }
                    });
                }
            })();
        ");
        
        echo html_writer::end_div();
        break;
    
    case 'cohorts':
        // Вкладка "Когорты" - список когорт, студентов и их связь с программами
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Когорты (Глобальные группы)', ['style' => 'margin-bottom: 20px;']);
        
        try {
            // Получаем все когорты
            $cohorts = $DB->get_records('cohort', null, 'name ASC', 'id, name, idnumber, description');
            
            if (empty($cohorts)) {
                echo html_writer::div('Когорты не найдены.', 'alert alert-info');
            } else {
                // Получаем все программы для проверки связи
                $allprograms = $DB->get_records('local_deanpromoodle_programs', null, 'name ASC', 'id, name');
                $programsmap = [];
                foreach ($allprograms as $program) {
                    $programsmap[$program->id] = $program->name;
                }
                
                // Получаем связи когорт с программами
                $cohortprograms = [];
                if (!empty($cohorts)) {
                    $cohortids = array_keys($cohorts);
                    $placeholders = implode(',', array_fill(0, count($cohortids), '?'));
                    $programcohorts = $DB->get_records_sql(
                        "SELECT pc.cohortid, pc.programid, p.name as programname
                         FROM {local_deanpromoodle_program_cohorts} pc
                         JOIN {local_deanpromoodle_programs} p ON p.id = pc.programid
                         WHERE pc.cohortid IN ($placeholders)",
                        $cohortids
                    );
                    
                    foreach ($programcohorts as $pc) {
                        if (!isset($cohortprograms[$pc->cohortid])) {
                            $cohortprograms[$pc->cohortid] = [];
                        }
                        $cohortprograms[$pc->cohortid][] = [
                            'id' => $pc->programid,
                            'name' => $pc->programname
                        ];
                    }
                }
                
                // Стили для таблицы когорт
                echo html_writer::start_tag('style');
                echo "
                    .cohorts-table {
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        overflow: hidden;
                    }
                    .cohorts-table table {
                        margin: 0;
                        width: 100%;
                    }
                    .cohorts-table thead th {
                        background-color: #f8f9fa;
                        padding: 12px 16px;
                        text-align: left;
                        font-weight: 600;
                        color: #495057;
                        border-bottom: 2px solid #dee2e6;
                    }
                    .cohorts-table tbody tr {
                        border-bottom: 1px solid #f0f0f0;
                    }
                    .cohorts-table tbody tr:hover {
                        background-color: #f8f9fa;
                    }
                    .cohorts-table tbody td {
                        padding: 12px 16px;
                        vertical-align: top;
                    }
                    .cohort-students {
                        max-height: 300px;
                        overflow-y: auto;
                    }
                    .cohort-students table {
                        font-size: 0.9em;
                        margin: 0;
                    }
                    .cohort-students table th {
                        background-color: #e9ecef;
                        padding: 8px 12px;
                        font-weight: 600;
                        font-size: 0.85em;
                    }
                    .cohort-students table td {
                        padding: 8px 12px;
                    }
                    .program-badge {
                        display: inline-block;
                        margin: 2px 4px 2px 0;
                        padding: 4px 8px;
                        background-color: #007bff;
                        color: white;
                        border-radius: 4px;
                        font-size: 0.85em;
                    }
                    .no-program {
                        color: #6c757d;
                        font-style: italic;
                    }
                    .cohort-toggle {
                        cursor: pointer;
                        color: #007bff;
                        text-decoration: none;
                    }
                    .cohort-toggle:hover {
                        text-decoration: underline;
                    }
                ";
                echo html_writer::end_tag('style');
                
                echo html_writer::start_div('cohorts-table');
                echo html_writer::start_tag('table', ['class' => 'table']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'ID', ['style' => 'width: 60px;']);
                echo html_writer::tag('th', 'Название');
                echo html_writer::tag('th', 'ID Number', ['style' => 'width: 150px;']);
                echo html_writer::tag('th', 'Описание', ['style' => 'width: 200px;']);
                echo html_writer::tag('th', 'Программы', ['style' => 'width: 200px;']);
                echo html_writer::tag('th', 'Студенты', ['style' => 'width: 100px; text-align: center;']);
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                foreach ($cohorts as $cohort) {
                    $cohortid = (int)$cohort->id;
                    $cohortname = is_string($cohort->name) ? $cohort->name : '';
                    $cohortidnumber = is_string($cohort->idnumber) ? $cohort->idnumber : '';
                    $cohortdescription = is_string($cohort->description) ? $cohort->description : '';
                    
                    // Получаем студентов когорты
                    $cohortmembers = $DB->get_records_sql(
                        "SELECT u.id, u.firstname, u.lastname, u.email, u.timecreated, u.lastaccess
                         FROM {cohort_members} cm
                         JOIN {user} u ON u.id = cm.userid
                         WHERE cm.cohortid = ? AND u.deleted = 0
                         ORDER BY u.lastname, u.firstname",
                        [$cohortid]
                    );
                    
                    // Получаем программы, связанные с этой когортой
                    $linkedprograms = isset($cohortprograms[$cohortid]) ? $cohortprograms[$cohortid] : [];
                    
                    echo html_writer::start_tag('tr', ['data-cohort-id' => $cohortid]);
                    
                    // ID
                    echo html_writer::tag('td', $cohortid);
                    
                    // Название
                    echo html_writer::tag('td', htmlspecialchars($cohortname, ENT_QUOTES, 'UTF-8'));
                    
                    // ID Number
                    echo html_writer::tag('td', $cohortidnumber ? htmlspecialchars($cohortidnumber, ENT_QUOTES, 'UTF-8') : '-');
                    
                    // Описание
                    $descriptiontext = $cohortdescription ? mb_substr(strip_tags($cohortdescription), 0, 100) : '-';
                    if ($cohortdescription && mb_strlen(strip_tags($cohortdescription)) > 100) {
                        $descriptiontext .= '...';
                    }
                    echo html_writer::tag('td', htmlspecialchars($descriptiontext, ENT_QUOTES, 'UTF-8'));
                    
                    // Программы
                    echo html_writer::start_tag('td');
                    if (!empty($linkedprograms)) {
                        foreach ($linkedprograms as $program) {
                            echo html_writer::tag('span', htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8'), [
                                'class' => 'program-badge',
                                'title' => 'Программа: ' . htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8')
                            ]);
                        }
                    } else {
                        echo html_writer::tag('span', 'Не прикреплена', ['class' => 'no-program']);
                    }
                    echo html_writer::end_tag('td');
                    
                    // Студенты
                    echo html_writer::start_tag('td', ['style' => 'text-align: center;']);
                    $studentscount = count($cohortmembers);
                    if ($studentscount > 0) {
                        echo html_writer::link('#', '<i class="fas fa-users"></i> ' . $studentscount, [
                            'class' => 'cohort-toggle',
                            'data-cohort-id' => $cohortid,
                            'title' => 'Показать студентов'
                        ]);
                    } else {
                        echo html_writer::tag('span', '0', ['class' => 'text-muted']);
                    }
                    echo html_writer::end_tag('td');
                    
                    echo html_writer::end_tag('tr');
                    
                    // Скрытая строка со списком студентов
                    echo html_writer::start_tag('tr', [
                        'class' => 'cohort-students-row',
                        'data-cohort-id' => $cohortid,
                        'style' => 'display: none;'
                    ]);
                    echo html_writer::start_tag('td', ['colspan' => '6', 'style' => 'padding: 0;']);
                    echo html_writer::start_div('cohort-students', ['style' => 'padding: 15px; background-color: #f8f9fa;']);
                    
                    if (!empty($cohortmembers)) {
                        echo html_writer::start_tag('table', ['class' => 'table table-sm table-bordered']);
                        echo html_writer::start_tag('thead');
                        echo html_writer::start_tag('tr');
                        echo html_writer::tag('th', 'ID');
                        echo html_writer::tag('th', 'ФИО');
                        echo html_writer::tag('th', 'Email');
                        echo html_writer::tag('th', 'Связан с программой');
                        echo html_writer::tag('th', 'Дата регистрации');
                        echo html_writer::end_tag('tr');
                        echo html_writer::end_tag('thead');
                        echo html_writer::start_tag('tbody');
                        
                        foreach ($cohortmembers as $member) {
                            // Проверяем, связан ли студент с программой через когорту
                            $islinkedtoprogram = !empty($linkedprograms);
                            $programnames = [];
                            if ($islinkedtoprogram) {
                                foreach ($linkedprograms as $program) {
                                    $programnames[] = htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8');
                                }
                            }
                            
                            echo html_writer::start_tag('tr');
                            echo html_writer::tag('td', $member->id);
                            echo html_writer::tag('td', htmlspecialchars(trim($member->firstname . ' ' . $member->lastname), ENT_QUOTES, 'UTF-8'));
                            echo html_writer::tag('td', htmlspecialchars($member->email, ENT_QUOTES, 'UTF-8'));
                            
                            // Связь с программой
                            echo html_writer::start_tag('td');
                            if ($islinkedtoprogram) {
                                foreach ($linkedprograms as $program) {
                                    echo html_writer::tag('span', htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8'), [
                                        'class' => 'program-badge',
                                        'style' => 'font-size: 0.8em;'
                                    ]);
                                }
                            } else {
                                echo html_writer::tag('span', 'Нет', ['class' => 'no-program']);
                            }
                            echo html_writer::end_tag('td');
                            
                            // Дата регистрации
                            $timecreated = $member->timecreated ? date('d.m.Y', $member->timecreated) : '-';
                            echo html_writer::tag('td', $timecreated);
                            
                            echo html_writer::end_tag('tr');
                        }
                        
                        echo html_writer::end_tag('tbody');
                        echo html_writer::end_tag('table');
                    } else {
                        echo html_writer::div('В когорте нет студентов.', 'alert alert-info');
                    }
                    
                    echo html_writer::end_div();
                    echo html_writer::end_tag('td');
                    echo html_writer::end_tag('tr');
                }
                
                echo html_writer::end_tag('tbody');
                echo html_writer::end_tag('table');
                echo html_writer::end_div();
                
                // JavaScript для раскрытия/сворачивания списка студентов
                $PAGE->requires->js_init_code("
                    (function() {
                        document.querySelectorAll('.cohort-toggle').forEach(function(toggle) {
                            toggle.addEventListener('click', function(e) {
                                e.preventDefault();
                                var cohortId = this.getAttribute('data-cohort-id');
                                var row = document.querySelector('tr.cohort-students-row[data-cohort-id=\"' + cohortId + '\"]');
                                if (row) {
                                    if (row.style.display === 'none') {
                                        row.style.display = '';
                                        this.innerHTML = '<i class=\"fas fa-chevron-up\"></i> ' + this.textContent.replace(/[^0-9]/g, '');
                                        this.title = 'Скрыть студентов';
                                    } else {
                                        row.style.display = 'none';
                                        this.innerHTML = '<i class=\"fas fa-users\"></i> ' + this.textContent.replace(/[^0-9]/g, '');
                                        this.title = 'Показать студентов';
                                    }
                                }
                            });
                        });
                    })();
                ");
            }
        } catch (\Exception $e) {
            echo html_writer::div('Ошибка: ' . $e->getMessage(), 'alert alert-danger');
        }
        
        echo html_writer::end_div();
        break;
    
    case 'searchstudent':
        // Вкладка "Поиск студента"
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Поиск студента', ['style' => 'margin-bottom: 20px;']);
        
        // Форма поиска
        echo html_writer::start_tag('form', [
            'id' => 'student-search-form',
            'class' => 'form-horizontal',
            'style' => 'background-color: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;'
        ]);
        
        echo html_writer::start_div('row');
        
        // Поле ID
        echo html_writer::start_div('col-md-3');
        echo html_writer::label('ID студента', 'student-id-search');
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'id' => 'student-id-search',
            'name' => 'studentid',
            'class' => 'form-control',
            'placeholder' => 'ID студента',
            'min' => '1'
        ]);
        echo html_writer::end_div();
        
        // Поле ФИО
        echo html_writer::start_div('col-md-3');
        echo html_writer::label('ФИО', 'student-name-search');
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => 'student-name-search',
            'name' => 'studentname',
            'class' => 'form-control',
            'placeholder' => 'Имя или фамилия'
        ]);
        echo html_writer::end_div();
        
        // Поле Email
        echo html_writer::start_div('col-md-3');
        echo html_writer::label('Email', 'student-email-search');
        echo html_writer::empty_tag('input', [
            'type' => 'email',
            'id' => 'student-email-search',
            'name' => 'studentemail',
            'class' => 'form-control',
            'placeholder' => 'Email студента'
        ]);
        echo html_writer::end_div();
        
        // Поле Группа
        echo html_writer::start_div('col-md-3');
        echo html_writer::label('Группа', 'student-cohort-search');
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'id' => 'student-cohort-search',
            'name' => 'studentcohort',
            'class' => 'form-control',
            'placeholder' => 'Название группы'
        ]);
        echo html_writer::end_div();
        
        echo html_writer::end_div(); // row
        
        echo html_writer::start_div('row', ['style' => 'margin-top: 15px;']);
        echo html_writer::start_div('col-md-12');
        echo html_writer::empty_tag('input', [
            'type' => 'button',
            'id' => 'search-student-btn',
            'value' => 'Найти',
            'class' => 'btn btn-primary',
            'style' => 'margin-right: 10px;'
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'button',
            'id' => 'clear-search-btn',
            'value' => 'Очистить',
            'class' => 'btn btn-secondary'
        ]);
        echo html_writer::end_div();
        echo html_writer::end_div(); // row
        
        echo html_writer::end_tag('form');
        
        // Область результатов поиска
        echo html_writer::start_div('', ['id' => 'student-search-results', 'style' => 'margin-top: 20px;']);
        echo html_writer::div('Введите критерии поиска и нажмите "Найти"', 'text-muted', ['style' => 'text-align: center; padding: 20px;']);
        echo html_writer::end_div();
        
        // JavaScript для поиска студентов
        // Используем тот же подход, что и в других AJAX-запросах
        $ajaxurl = $CFG->wwwroot . '/local/deanpromoodle/pages/admin_ajax.php';
        $studenturl = $CFG->wwwroot . '/local/deanpromoodle/pages/student.php';
        $PAGE->requires->js_init_code("
            (function() {
                var searchTimeout;
                var searchInputs = ['student-id-search', 'student-name-search', 'student-email-search', 'student-cohort-search'];
                var resultsDiv = document.getElementById('student-search-results');
                var searchBtn = document.getElementById('search-student-btn');
                var clearBtn = document.getElementById('clear-search-btn');
                var ajaxUrl = " . json_encode($ajaxurl) . ";
                var studentUrl = " . json_encode($studenturl) . ";
                
                // Функция выполнения поиска
                function performSearch() {
                    var studentid = document.getElementById('student-id-search').value.trim();
                    var studentname = document.getElementById('student-name-search').value.trim();
                    var studentemail = document.getElementById('student-email-search').value.trim();
                    var studentcohort = document.getElementById('student-cohort-search').value.trim();
                    
                    // Если все поля пустые, не выполняем поиск
                    if (!studentid && !studentname && !studentemail && !studentcohort) {
                        resultsDiv.innerHTML = '<div class=\"text-muted\" style=\"text-align: center; padding: 20px;\">Введите критерии поиска и нажмите \"Найти\"</div>';
                        return;
                    }
                    
                    resultsDiv.innerHTML = '<div class=\"text-center\" style=\"padding: 20px;\"><i class=\"fa fa-spinner fa-spin\"></i> Поиск...</div>';
                    
                    var params = [];
                    if (studentid) params.push('studentid=' + encodeURIComponent(studentid));
                    if (studentname) params.push('studentname=' + encodeURIComponent(studentname));
                    if (studentemail) params.push('studentemail=' + encodeURIComponent(studentemail));
                    if (studentcohort) params.push('studentcohort=' + encodeURIComponent(studentcohort));
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', ajaxUrl + '?action=searchstudents&' + params.join('&'), true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            if (xhr.status === 200) {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success && response.students && response.students.length > 0) {
                                        var html = '<table class=\"table table-striped table-hover\"><thead><tr><th>ID</th><th>ФИО</th><th>Email</th><th>Группы</th><th>Действие</th></tr></thead><tbody>';
                                        response.students.forEach(function(student) {
                                            var fullname = (student.firstname || '') + ' ' + (student.lastname || '');
                                            var cohorts = student.cohorts && student.cohorts.length > 0 ? student.cohorts.join(', ') : '-';
                                            html += '<tr>';
                                            html += '<td>' + student.id + '</td>';
                                            html += '<td>' + (fullname.trim() || '-') + '</td>';
                                            html += '<td>' + (student.email || '-') + '</td>';
                                            html += '<td>' + cohorts + '</td>';
                                            html += '<td>';
                                            html += '<a href=\"' + studentUrl + '?studentid=' + student.id + '&tab=courses\" class=\"btn btn-sm btn-primary\" target=\"_blank\" style=\"margin-right: 5px;\"><i class=\"fas fa-graduation-cap\"></i> Мои оценки</a>';
                                            html += '<a href=\"' + studentUrl + '?studentid=' + student.id + '&tab=programs\" class=\"btn btn-sm btn-info\" target=\"_blank\"><i class=\"fas fa-user\"></i> Личная информация</a>';
                                            html += '</td>';
                                            html += '</tr>';
                                        });
                                        html += '</tbody></table>';
                                        html += '<div class=\"text-muted\" style=\"margin-top: 10px;\">Найдено студентов: ' + response.count + '</div>';
                                        resultsDiv.innerHTML = html;
                                    } else {
                                        resultsDiv.innerHTML = '<div class=\"alert alert-info\" style=\"text-align: center;\">Студенты не найдены</div>';
                                    }
                                } catch (e) {
                                    console.error('Ошибка при обработке ответа:', e);
                                    resultsDiv.innerHTML = '<div class=\"alert alert-danger\" style=\"text-align: center;\">Ошибка при обработке ответа: ' + e.message + '</div>';
                                }
                            } else {
                                console.error('Ошибка AJAX:', xhr.status, xhr.statusText);
                                resultsDiv.innerHTML = '<div class=\"alert alert-danger\" style=\"text-align: center;\">Ошибка загрузки данных (HTTP ' + xhr.status + ')</div>';
                            }
                        }
                    };
                    xhr.onerror = function() {
                        console.error('Ошибка сети при выполнении AJAX-запроса');
                        resultsDiv.innerHTML = '<div class=\"alert alert-danger\" style=\"text-align: center;\">Ошибка сети при загрузке данных</div>';
                    };
                    xhr.send();
                }
                
                // Обработчик кнопки поиска
                if (searchBtn) {
                    searchBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        performSearch();
                    });
                }
                
                // Обработчик кнопки очистки
                if (clearBtn) {
                    clearBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        searchInputs.forEach(function(inputId) {
                            var input = document.getElementById(inputId);
                            if (input) input.value = '';
                        });
                        resultsDiv.innerHTML = '<div class=\"text-muted\" style=\"text-align: center; padding: 20px;\">Введите критерии поиска и нажмите \"Найти\"</div>';
                    });
                }
                
                // AJAX-поиск при вводе (debounce 500ms)
                searchInputs.forEach(function(inputId) {
                    var input = document.getElementById(inputId);
                    if (input) {
                        input.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(function() {
                                performSearch();
                            }, 500);
                        });
                    }
                });
            })();
        ");
        
        echo html_writer::end_div();
        break;
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
