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

// Получение параметров
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$period = optional_param('period', 'month', PARAM_ALPHA); // day, week, month, year
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/admin.php', [
    'teacherid' => $teacherid,
    'period' => $period,
    'datefrom' => $datefrom,
    'dateto' => $dateto
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

// Получение списка преподавателей
$teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
$teachers = [];
if (!empty($teacherroleids)) {
    $systemcontext = context_system::instance();
    $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
    $teacheruserids = $DB->get_fieldset_sql(
        "SELECT DISTINCT ra.userid
         FROM {role_assignments} ra
         WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)",
        array_merge([$systemcontext->id], $teacherroleids)
    );
    
    if (!empty($teacheruserids)) {
        $userplaceholders = implode(',', array_fill(0, count($teacheruserids), '?'));
        $teachers = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
             FROM {user} u
             WHERE u.id IN ($userplaceholders)
             AND u.deleted = 0
             ORDER BY u.lastname, u.firstname",
            $teacheruserids
        );
    }
}

// Форма выбора преподавателя и периода
echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
echo html_writer::tag('h2', 'История преподавателя', ['style' => 'margin-bottom: 20px;']);

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/deanpromoodle/pages/admin.php'),
    'class' => 'form-inline',
    'style' => 'background-color: #f5f5f5; padding: 20px; border-radius: 5px; margin-bottom: 20px;'
]);

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
if ($teacherid > 0 || $teacherid == 0) {
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
    $quizzeshistory = $DB->get_records_sql(
        "SELECT qg.id, qg.userid as grader, qg.timemodified, qg.grade,
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
    
    // Получение ответов на форумах
    $forumshistory = $DB->get_records_sql(
        "SELECT p.id, p.userid as grader, p.created as timemodified,
                f.name as forumname, c.fullname as coursename, c.id as courseid,
                d.name as discussionname, p.subject
         FROM {forum_posts} p
         JOIN {forum_discussions} d ON d.id = p.discussion
         JOIN {forum} f ON f.id = d.forum
         JOIN {course} c ON c.id = f.course
         WHERE p.created >= ? AND p.created <= ?
         " . ($teacherid > 0 ? "AND p.userid = $teacherid" : "") . "
         AND p.userid IN (SELECT DISTINCT ra.userid FROM {role_assignments} ra 
                          JOIN {role} r ON r.id = ra.roleid 
                          WHERE r.shortname IN ('teacher', 'editingteacher', 'manager'))
         ORDER BY p.created DESC",
        [$startdate, $enddate]
    );
    
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
}

echo html_writer::end_div();

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
