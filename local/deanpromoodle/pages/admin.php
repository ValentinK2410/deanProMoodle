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
$tab = optional_param('tab', 'history', PARAM_ALPHA); // history, teachers, students
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$period = optional_param('period', 'month', PARAM_ALPHA); // day, week, month, year
$datefrom = optional_param('datefrom', '', PARAM_TEXT);
$dateto = optional_param('dateto', '', PARAM_TEXT);
$studentperiod = optional_param('studentperiod', 'month', PARAM_ALPHA);
$studentdatefrom = optional_param('studentdatefrom', '', PARAM_TEXT);
$studentdateto = optional_param('studentdateto', '', PARAM_TEXT);

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
        // Вкладка "Программы" - категории курсов (программы обучения)
        echo html_writer::start_div('local-deanpromoodle-admin-content', ['style' => 'margin-bottom: 30px;']);
        echo html_writer::tag('h2', 'Программы обучения', ['style' => 'margin-bottom: 20px;']);
        
        // Получение всех категорий курсов
        $categories = $DB->get_records('course_categories', null, 'name ASC');
        
        if (empty($categories)) {
            echo html_writer::div('Категории курсов не найдены.', 'alert alert-info');
        } else {
            // Подготовка данных для каждой категории
            $programsdata = [];
            foreach ($categories as $category) {
                // Количество курсов в категории (исключаем системный курс с id=1)
                $coursescount = $DB->count_records_sql(
                    "SELECT COUNT(*) FROM {course} WHERE category = ? AND id > 1",
                    [$category->id]
                );
                
                // Получаем курсы категории для подсчета студентов и преподавателей
                $categorycourses = $DB->get_records('course', ['category' => $category->id], '', 'id');
                $courseids = array_keys($categorycourses);
                
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
                
                // Безопасное преобразование имени категории в строку для programs
                $safename = '';
                if (is_string($category->name)) {
                    $safename = $category->name;
                } elseif (is_array($category->name)) {
                    $safename = implode(', ', array_filter($category->name, 'is_string'));
                } elseif (is_object($category->name) && method_exists($category->name, '__toString')) {
                    $safename = (string)$category->name;
                } elseif (isset($category->name)) {
                    $safename = (string)$category->name;
                } else {
                    $safename = 'Без названия';
                }
                
                $programsdata[] = (object)[
                    'id' => $category->id,
                    'name' => $safename,
                    'description' => $category->description,
                    'coursescount' => $coursescount,
                    'studentscount' => $studentscount,
                    'teacherscount' => $teacherscount,
                    'visible' => $category->visible
                ];
            }
            
            // Отображение таблицы
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'ID');
            echo html_writer::tag('th', 'Название программы');
            echo html_writer::tag('th', 'Количество курсов');
            echo html_writer::tag('th', 'Количество студентов');
            echo html_writer::tag('th', 'Количество преподавателей');
            echo html_writer::tag('th', 'Статус');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            
            foreach ($programsdata as $program) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', $program->id);
                // Безопасное преобразование имени программы в строку
                $programname = '';
                if (is_string($program->name)) {
                    $programname = $program->name;
                } elseif (is_array($program->name)) {
                    $programname = implode(', ', array_filter($program->name, 'is_string'));
                } elseif (is_object($program->name) && method_exists($program->name, '__toString')) {
                    $programname = (string)$program->name;
                } elseif (isset($program->name)) {
                    $programname = (string)$program->name;
                } else {
                    $programname = 'Без названия';
                }
                echo html_writer::tag('td', htmlspecialchars($programname, ENT_QUOTES, 'UTF-8'));
                echo html_writer::tag('td', html_writer::tag('strong', $program->coursescount, ['style' => 'color: #007bff;']));
                echo html_writer::tag('td', html_writer::tag('span', $program->studentscount, ['style' => 'color: green; font-weight: bold;']));
                echo html_writer::tag('td', html_writer::tag('span', $program->teacherscount, ['style' => 'color: orange; font-weight: bold;']));
                $status = $program->visible ? html_writer::tag('span', 'Активна', ['style' => 'color: green;']) : html_writer::tag('span', 'Скрыта', ['style' => 'color: red;']);
                echo html_writer::tag('td', $status);
                echo html_writer::end_tag('tr');
            }
            
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
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
                        $spanresult = html_writer::span('', ['style' => 'display: inline-block; width: 15px;']);
                        $namecell .= is_string($spanresult) ? $spanresult : (string)$spanresult;
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
                    }
                    // Финальная проверка - убеждаемся, что это строка
                    if (!is_string($categoryname)) {
                        $categoryname = 'Без названия';
                    }
                    $namecell .= htmlspecialchars($categoryname, ENT_QUOTES, 'UTF-8');
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
