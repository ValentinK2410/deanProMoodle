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

// ОТЛАДКА - ДО ПОДКЛЮЧЕНИЯ MOODLE - удалить после исправления ошибки
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Собственный обработчик ошибок для отображения до Moodle
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<pre style='background:red;color:white;padding:20px;'>";
    echo "PHP Error [$errno]: $errstr\n";
    echo "File: $errfile\n";
    echo "Line: $errline\n";
    echo "</pre>";
    return false;
});

set_exception_handler(function($e) {
    echo "<pre style='background:red;color:white;padding:20px;'>";
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    echo "</pre>";
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<pre style='background:darkred;color:white;padding:20px;'>";
        echo "Fatal Error: " . $error['message'] . "\n";
        echo "File: " . $error['file'] . "\n";
        echo "Line: " . $error['line'] . "\n";
        echo "</pre>";
    }
});

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
$tabs[] = new tabobject('categories', 
    new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'categories']),
    'Категории курсов');

echo $OUTPUT->tabtree($tabs, $tab);

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
        
        // Обработка действий
        if ($action == 'create' || ($action == 'edit' && $programid > 0)) {
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
                $visible = optional_param('visible', 1, PARAM_INT);
                $selectedsubjects = optional_param_array('subjects', [], PARAM_INT);
                
                if (empty($name)) {
                    echo html_writer::div('Название программы обязательно для заполнения.', 'alert alert-danger');
                } else {
                    $transaction = $DB->start_delegated_transaction();
                    try {
                        $data = new stdClass();
                        $data->name = $name;
                        $data->code = $code;
                        $data->description = $description;
                        $data->visible = $visible;
                        $data->timemodified = time();
                        
                        if ($isedit) {
                            $data->id = $programid;
                            $DB->update_record('local_deanpromoodle_programs', $data);
                            $programid = $data->id;
                            
                            // Удаляем старые связи с предметами
                            $DB->delete_records('local_deanpromoodle_program_subjects', ['programid' => $programid]);
                        } else {
                            $data->timecreated = time();
                            $programid = $DB->insert_record('local_deanpromoodle_programs', $data);
                        }
                        
                        // Добавляем связи с предметами
                        if (!empty($selectedsubjects)) {
                            $sortorder = 0;
                            foreach ($selectedsubjects as $subjectid) {
                                if ($subjectid > 0) {
                                    $psdata = new stdClass();
                                    $psdata->programid = $programid;
                                    $psdata->subjectid = $subjectid;
                                    $psdata->sortorder = $sortorder++;
                                    $psdata->timecreated = time();
                                    $psdata->timemodified = time();
                                    $DB->insert_record('local_deanpromoodle_program_subjects', $psdata);
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
            
            // Получаем все предметы для выбора
            try {
                $allsubjects = $DB->get_records('local_deanpromoodle_subjects', ['visible' => 1], 'sortorder ASC, name ASC');
            } catch (\Exception $e) {
                $allsubjects = [];
            }
            $selectedsubjectids = [];
            if ($isedit && $program) {
                try {
                    $selectedsubjectids = $DB->get_fieldset_select(
                        'local_deanpromoodle_program_subjects',
                        'subjectid',
                        'programid = ?',
                        [$programid]
                    );
                } catch (\Exception $e) {
                    $selectedsubjectids = [];
                }
            }
            
            // Отображение формы
            $formtitle = $isedit ? 'Редактировать программу' : 'Создать программу';
            echo html_writer::tag('h2', $formtitle, ['style' => 'margin-bottom: 20px;']);
            
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/local/deanpromoodle/pages/admin.php', [
                    'tab' => 'programs',
                    'action' => $action,
                    'programid' => $programid
                ]),
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
            
            // Выбор предметов
            echo html_writer::start_div('form-group', ['style' => 'margin-bottom: 15px;']);
            echo html_writer::label('Предметы программы', 'subjects');
            echo html_writer::start_div('', ['style' => 'max-height: 300px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px;']);
            if (empty($allsubjects)) {
                echo html_writer::div('Предметы не найдены. Сначала создайте предметы на вкладке "Предметы".', 'text-muted');
            } else {
                foreach ($allsubjects as $subject) {
                    $checked = in_array($subject->id, $selectedsubjectids) ? 'checked' : '';
                    echo html_writer::start_div('form-check', ['style' => 'margin-bottom: 8px;']);
                    echo html_writer::empty_tag('input', [
                        'type' => 'checkbox',
                        'name' => 'subjects[]',
                        'id' => 'subject-' . $subject->id,
                        'value' => $subject->id,
                        'class' => 'form-check-input',
                        'checked' => $checked
                    ]);
                    echo html_writer::label(
                        htmlspecialchars($subject->name, ENT_QUOTES, 'UTF-8') . 
                        ($subject->code ? ' (' . htmlspecialchars($subject->code, ENT_QUOTES, 'UTF-8') . ')' : ''),
                        'subject-' . $subject->id,
                        ['class' => 'form-check-label']
                    );
                    echo html_writer::end_div();
                }
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
            
        } else {
            // Список программ
            // Заголовок с кнопками
            echo html_writer::start_div('', ['style' => 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;']);
            echo html_writer::start_div('', ['style' => 'display: flex; align-items: center; gap: 10px;']);
            echo html_writer::tag('span', '📋', ['style' => 'font-size: 24px;']);
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
            echo html_writer::link('#', '🔗 Прикрепить глобальную группу', [
                'class' => 'btn btn-secondary',
                'id' => 'attach-cohort-btn',
                'style' => 'background-color: #6c757d; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
            ]);
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
                    
                    $programsdata[] = (object)[
                        'id' => $program->id,
                        'name' => $program->name,
                        'code' => $program->code ?? '',
                        'categoryname' => $sitename,
                        'subjectscount' => $subjectscount,
                        'cohortscount' => $cohortscount,
                        'subjectslist' => implode(', ', $subjectslist),
                        'visible' => $program->visible
                    ];
                }
            }
            
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
                .action-buttons {
                    display: flex;
                    gap: 4px;
                }
                .action-btn {
                    width: 32px;
                    height: 32px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                    transition: opacity 0.2s;
                }
                .action-btn:hover {
                    opacity: 0.8;
                }
                .action-btn-view {
                    background-color: #2196f3;
                    color: white;
                }
                .action-btn-edit {
                    background-color: #ffc107;
                    color: white;
                }
                .action-btn-copy {
                    background-color: #9e9e9e;
                    color: white;
                }
                .action-btn-delete {
                    background-color: #f44336;
                    color: white;
                }
            ";
            echo html_writer::end_tag('style');
            
                // Отображение таблицы
                echo html_writer::start_div('programs-table');
                echo html_writer::start_tag('table');
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'ID');
                echo html_writer::tag('th', 'Название курса');
                echo html_writer::tag('th', 'Учебное заведение');
                echo html_writer::tag('th', 'Связи');
                echo html_writer::tag('th', 'Тип оплаты');
                echo html_writer::tag('th', 'Цена');
                echo html_writer::tag('th', 'Статус');
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
                    
                    echo html_writer::start_tag('tr');
                    
                    // ID
                    echo html_writer::start_tag('td');
                    echo '<span class="program-id-badge">' . htmlspecialchars((string)$programid, ENT_QUOTES, 'UTF-8') . '</span>';
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
                        $relations[] = '<span class="badge badge-group">📚 ' . $programsubjectscount . ' предмет' . ($programsubjectscount > 1 ? 'ов' : '') . '</span>';
                    }
                    if ($programcohortscount > 0) {
                        $relations[] = '<span class="badge badge-student">👥 ' . $programcohortscount . ' группа' . ($programcohortscount > 1 ? '' : 'а') . '</span>';
                    }
                    if (empty($relations)) {
                        echo '-';
                    } else {
                        echo implode(' ', $relations);
                    }
                    echo html_writer::end_tag('td');
                    
                    // Тип оплаты
                    echo html_writer::start_tag('td');
                    echo '<span class="badge badge-free">🎁 Бесплатный</span>';
                    echo html_writer::end_tag('td');
                    
                    // Цена
                    echo html_writer::start_tag('td');
                    echo '-';
                    echo html_writer::end_tag('td');
                    
                    // Статус
                    echo html_writer::start_tag('td');
                    if ($programvisible) {
                        echo '<span class="badge badge-active">✓ Активный</span>';
                    } else {
                        echo '<span class="badge" style="background-color: #9e9e9e; color: white;">Скрыт</span>';
                    }
                    echo html_writer::end_tag('td');
                    
                    // Действия
                    echo html_writer::start_tag('td');
                    echo html_writer::start_div('action-buttons');
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'programs', 'action' => 'edit', 'programid' => $programid]),
                        '✏',
                        [
                            'class' => 'action-btn action-btn-edit',
                            'title' => 'Редактировать'
                        ]
                    );
                    echo html_writer::link('#', '🗑', [
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
                'onclick' => 'jQuery(\'#attachCohortModal\').modal(\'hide\');'
            ]);
            echo html_writer::tag('span', '×', ['aria-hidden' => 'true']);
            echo html_writer::end_tag('button');
            echo html_writer::end_div();
            echo html_writer::start_div('modal-body');
            echo html_writer::start_div('form-group');
            echo html_writer::label('Выберите программу', 'program-select');
            echo html_writer::select(
                $programsoptions,
                'program-select',
                '',
                ['' => 'Выберите программу...'],
                ['id' => 'program-select', 'class' => 'form-control']
            );
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
            echo html_writer::start_div('', ['id' => 'cohorts-list', 'style' => 'max-height: 400px; overflow-y: auto;']);
            echo html_writer::div('Выберите программу и введите текст для поиска когорт...', 'text-muted');
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
            $PAGE->requires->js_init_code("
                (function() {
                    var programs = " . $programsjson . ";
                    var currentProgramId = null;
                    
                    // Обработчик открытия модального окна
                    document.getElementById('attach-cohort-btn').addEventListener('click', function(e) {
                        e.preventDefault();
                        if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                            jQuery('#attachCohortModal').modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var modal = new bootstrap.Modal(document.getElementById('attachCohortModal'));
                            modal.show();
                        }
                    });
                    
                    // Обработчик выбора программы
                    document.getElementById('program-select').addEventListener('change', function() {
                        currentProgramId = this.value;
                        document.getElementById('cohort-search').value = '';
                        document.getElementById('cohorts-list').innerHTML = '<div class=\"text-muted\">Введите текст для поиска когорт...</div>';
                    });
                    
                    // Поиск когорт
                    var cohortSearchInput = document.getElementById('cohort-search');
                    var cohortsList = document.getElementById('cohorts-list');
                    var cohortSearchTimeout;
                    
                    if (cohortSearchInput) {
                        cohortSearchInput.addEventListener('input', function() {
                            if (!currentProgramId) {
                                cohortsList.innerHTML = '<div class=\"alert alert-warning\">Сначала выберите программу</div>';
                                return;
                            }
                            
                            clearTimeout(cohortSearchTimeout);
                            var query = this.value.trim();
                            
                            if (query.length < 2) {
                                cohortsList.innerHTML = '<div class=\"text-muted\">Введите минимум 2 символа для поиска...</div>';
                                return;
                            }
                            
                            cohortSearchTimeout = setTimeout(function() {
                                var xhr = new XMLHttpRequest();
                                xhr.open('GET', '/local/deanpromoodle/pages/admin_ajax.php?action=getcohorts&search=' + encodeURIComponent(query) + '&programid=' + currentProgramId, true);
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4 && xhr.status === 200) {
                                        try {
                                            var response = JSON.parse(xhr.responseText);
                                            if (response.success && response.cohorts) {
                                                var html = '<table class=\"table table-striped\"><thead><tr><th>ID</th><th>Название</th><th>ID Number</th><th>Действие</th></tr></thead><tbody>';
                                                response.cohorts.forEach(function(cohort) {
                                                    html += '<tr><td>' + cohort.id + '</td><td>' + cohort.name + '</td><td>' + (cohort.idnumber || '-') + '</td><td><button class=\"btn btn-sm btn-primary attach-cohort-btn\" data-cohort-id=\"' + cohort.id + '\">Прикрепить группу</button></td></tr>';
                                                });
                                                html += '</tbody></table>';
                                                cohortsList.innerHTML = html;
                                                
                                                // Обработчики кнопок прикрепления
                                                document.querySelectorAll('.attach-cohort-btn').forEach(function(btn) {
                                                    btn.addEventListener('click', function() {
                                                        var cohortId = this.getAttribute('data-cohort-id');
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
                                                                }
                                                            }
                                                        };
                                                        xhr2.send('action=attachcohorttoprogram&programid=' + currentProgramId + '&cohortid=' + cohortId);
                                                    });
                                                });
                                            } else {
                                                cohortsList.innerHTML = '<div class=\"alert alert-info\">Когорты не найдены</div>';
                                            }
                                        } catch (e) {
                                            cohortsList.innerHTML = '<div class=\"alert alert-danger\">Ошибка при обработке ответа</div>';
                                        }
                                    }
                                };
                                xhr.send();
                            }, 500);
                        });
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
                })();
            ");
        }
        
        echo html_writer::end_div();
        break;
    
    case 'subjects':
        // Вкладка "Предметы"
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        
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
                    echo html_writer::link('#', '🗑 Удалить', [
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
            echo html_writer::tag('span', '📚', ['style' => 'font-size: 24px;']);
            echo html_writer::tag('h2', 'Предметы', ['style' => 'margin: 0; font-size: 24px; font-weight: 600;']);
            echo html_writer::end_div();
            echo html_writer::link(
                new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'action' => 'create']),
                '+ Добавить предмет',
                [
                    'class' => 'btn btn-primary',
                    'style' => 'background-color: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500;'
                ]
            );
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
                    
                    // Подсчет программ
                    $programscount = $DB->count_records('local_deanpromoodle_program_subjects', ['subjectid' => $subject->id]);
                    
                    $subjectsdata[] = (object)[
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code ?? '',
                        'sortorder' => $subject->sortorder,
                        'coursescount' => $coursescount,
                        'programscount' => $programscount,
                        'visible' => $subject->visible
                    ];
                }
                
                // Таблица предметов
                echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
                echo html_writer::start_tag('thead');
                echo html_writer::start_tag('tr');
                echo html_writer::tag('th', 'Порядок');
                echo html_writer::tag('th', 'ID');
                echo html_writer::tag('th', 'Название');
                echo html_writer::tag('th', 'Код');
                echo html_writer::tag('th', 'Курсов');
                echo html_writer::tag('th', 'Программ');
                echo html_writer::tag('th', 'Статус');
                echo html_writer::tag('th', 'Действия');
                echo html_writer::end_tag('tr');
                echo html_writer::end_tag('thead');
                echo html_writer::start_tag('tbody');
                
                foreach ($subjectsdata as $subject) {
                    $subjectname = is_string($subject->name) ? $subject->name : (string)$subject->name;
                    $subjectcode = is_string($subject->code) ? $subject->code : (string)$subject->code;
                    
                    echo html_writer::start_tag('tr');
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
                        '👁',
                        ['class' => 'action-btn action-btn-view', 'title' => 'Просмотр']
                    );
                    // Редактирование
                    echo html_writer::link(
                        new moodle_url('/local/deanpromoodle/pages/admin.php', ['tab' => 'subjects', 'action' => 'edit', 'subjectid' => $subject->id]),
                        '✏',
                        ['class' => 'action-btn action-btn-edit', 'title' => 'Редактировать']
                    );
                    // Прикрепить к программе
                    echo html_writer::link('#', '🔗', [
                        'class' => 'action-btn action-btn-link attach-subject-to-program',
                        'title' => 'Прикрепить к программе',
                        'data-subject-id' => $subject->id,
                        'data-subject-name' => htmlspecialchars($subjectname, ENT_QUOTES, 'UTF-8')
                    ]);
                    // Удаление
                    echo html_writer::link('#', '🗑', [
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
                })();
            ");
        }
        
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
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
