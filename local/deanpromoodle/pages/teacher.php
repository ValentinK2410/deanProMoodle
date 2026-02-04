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
 * Страница преподавателя для плагина local_deanpromoodle.
 * Вкладки: Задания, Тесты, Форумы
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Определение пути к конфигурационному файлу Moodle
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    die('Ошибка: Файл config.php Moodle не найден по адресу: ' . $configpath);
}

require_once($configpath);
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

// Проверка доступа
require_login();

// Проверка установки плагина
if (!file_exists($CFG->dirroot . '/local/deanpromoodle/version.php')) {
    die('Ошибка: Плагин не найден. Пожалуйста, установите плагин через интерфейс администратора Moodle.');
}

// Проверка доступа - сначала проверяем capability, затем роли пользователя
$context = context_system::instance();
$hasaccess = false;

// Проверка capability
if (has_capability('local/deanpromoodle:viewteacher', $context)) {
    $hasaccess = true;
} else {
    // Резервный вариант: проверка ролей преподавателя/редактора/менеджера
    global $USER;
    $roles = get_user_roles($context, $USER->id, false);
    $teacherroles = ['teacher', 'editingteacher', 'manager', 'coursecreator'];
    foreach ($roles as $role) {
        if (in_array($role->shortname, $teacherroles)) {
            $hasaccess = true;
            break;
        }
    }
    
    // Также проверяем системные роли
    if (!$hasaccess) {
        $systemcontext = context_system::instance();
        $systemroles = get_user_roles($systemcontext, $USER->id, false);
        foreach ($systemroles as $role) {
            if (in_array($role->shortname, $teacherroles)) {
                $hasaccess = true;
                break;
            }
        }
    }
    
    // Временно разрешаем доступ всем залогиненным пользователям, если capability не установлен (для тестирования)
    if (!$hasaccess && !isguestuser()) {
        $hasaccess = true; // Временно: разрешаем всем залогиненным пользователям
    }
}

if (!$hasaccess) {
    require_capability('local/deanpromoodle:viewteacher', $context);
}

// Проверка роли пользователя и редирект при необходимости
global $USER;
$isadmin = false;
$isteacher = false;
$isstudent = false;

// Проверяем, является ли пользователь админом
if (has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context)) {
    $isadmin = true;
    // Админ может заходить на teacher.php, но если он пытается зайти как преподаватель, оставляем доступ
    // Админ также может заходить на admin.php
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

// Если пользователь только студент (не преподаватель и не админ), редирект на student.php
if ($isstudent && !$isteacher && !$isadmin) {
    redirect(new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']));
}

// Получение параметров
$tab = optional_param('tab', 'assignments', PARAM_ALPHA); // assignments, quizzes, forums
$courseid = optional_param('courseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$selectedmonth = optional_param('statmonth', date('n'), PARAM_INT); // Месяц для статистики (1-12)
$selectedyear = optional_param('statyear', date('Y'), PARAM_INT); // Год для статистики

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/teacher.php', [
    'tab' => $tab,
    'courseid' => $courseid,
    'page' => $page,
    'perpage' => $perpage,
    'statmonth' => $selectedmonth,
    'statyear' => $selectedyear
]));
$PAGE->set_context(context_system::instance());
// Получение заголовка с проверкой и fallback на русский
$pagetitle = get_string('teacherpagetitle', 'local_deanpromoodle');
if (strpos($pagetitle, '[[') !== false || $pagetitle == 'Teacher Dashboard') {
    $pagetitle = 'Панель преподавателя'; // Fallback на русский
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading(''); // Убираем стандартный заголовок, используем кастомный
$PAGE->set_pagelayout('standard');

// Подключение CSS
$PAGE->requires->css('/local/deanpromoodle/styles.css');

// Получение курсов, где пользователь является преподавателем
global $USER, $DB;
$teachercourses = [];
if ($courseid == 0) {
    // Получаем все курсы, где пользователь является преподавателем
    $courses = enrol_get_my_courses();
    foreach ($courses as $course) {
        if ($course->id > 1) {
            $coursecontext = context_course::instance($course->id);
            if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
                has_capability('moodle/course:manageactivities', $coursecontext)) {
                $teachercourses[$course->id] = $course;
            }
        }
    }
} else {
    $course = get_course($courseid);
    if ($course && $course->id > 1) {
        $coursecontext = context_course::instance($courseid);
        if (has_capability('moodle/course:viewparticipants', $coursecontext) || 
            has_capability('moodle/course:manageactivities', $coursecontext)) {
            $teachercourses[$courseid] = $course;
        }
    }
}

// Подсчет количества для вкладок
// Подсчет неоцененных заданий
$assignmentscount = 0;
foreach ($teachercourses as $course) {
    $assignments = get_all_instances_in_course('assign', $course, false);
    foreach ($assignments as $assignment) {
        $submissionscount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT s.id)
             FROM {assign_submission} s
             WHERE s.assignment = ? AND s.status = 'submitted' 
             AND (s.timemodified > 0)
             AND NOT EXISTS (
                 SELECT 1 FROM {assign_grades} g 
                 WHERE g.assignment = s.assignment 
                 AND g.userid = s.userid
                 AND g.grade IS NOT NULL
                 AND g.grade >= 0
             )",
            [$assignment->id]
        );
        $assignmentscount += $submissionscount;
    }
}

// Подсчет сообщений форумов без ответов преподавателя
$forumscount = 0;
$teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
$studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
if (!empty($teacherroleids) && $studentroleid) {
    $forumids = [];
    $courseforums = [];
    foreach ($teachercourses as $course) {
        $forums = get_all_instances_in_course('forum', $course, false);
        foreach ($forums as $forum) {
            $forumids[] = $forum->id;
            $courseforums[$forum->id] = $course;
        }
    }
    if (!empty($forumids)) {
        $coursecontexts = [];
        $allcourseids = array_unique(array_column($courseforums, 'id'));
        foreach ($allcourseids as $cid) {
            $coursecontexts[$cid] = context_course::instance($cid);
        }
        $systemcontext = context_system::instance();
        $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
        $coursecontextids = array_map(function($ctx) { return $ctx->id; }, $coursecontexts);
        $coursecontextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
        
        $allteacheruserids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
             FROM {role_assignments} ra
             WHERE (ra.contextid IN ($coursecontextplaceholders) OR ra.contextid = ?)
             AND ra.roleid IN ($placeholders)",
            array_merge($coursecontextids, [$systemcontext->id], $teacherroleids)
        );
        
        if (!empty($allteacheruserids)) {
            $allstudentuserids = $DB->get_fieldset_sql(
                "SELECT DISTINCT ra.userid
                 FROM {role_assignments} ra
                 WHERE ra.contextid IN ($coursecontextplaceholders)
                 AND ra.roleid = ?",
                array_merge($coursecontextids, [$studentroleid])
            );
            
            if (!empty($allstudentuserids)) {
                $forumplaceholders = implode(',', array_fill(0, count($forumids), '?'));
                $teacherplaceholders = implode(',', array_fill(0, count($allteacheruserids), '?'));
                $studentplaceholders = implode(',', array_fill(0, count($allstudentuserids), '?'));
                
                $forumscount = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT p.id)
                     FROM {forum_posts} p
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     JOIN {forum} f ON f.id = d.forum
                     LEFT JOIN {forum_posts} p2 ON p2.discussion = p.discussion 
                         AND p2.created > p.created 
                         AND p2.userid IN ($teacherplaceholders)
                     WHERE d.forum IN ($forumplaceholders)
                     AND p.userid IN ($studentplaceholders)
                     AND p2.id IS NULL",
                    array_merge($forumids, $allteacheruserids, $allstudentuserids)
                );
            }
        }
    }
}

// Подсчет проверенных заданий, тестов и форумов за выбранный календарный месяц
// Валидация выбранных месяца и года
$selectedmonth = max(1, min(12, $selectedmonth)); // Ограничиваем 1-12
$selectedyear = max(2000, min(2100, $selectedyear)); // Ограничиваем разумными значениями

$selectedmonthstart = mktime(0, 0, 0, $selectedmonth, 1, $selectedyear); // Первый день выбранного месяца
$selectedmonthend = mktime(23, 59, 59, $selectedmonth, date('t', $selectedmonthstart), $selectedyear); // Последний день выбранного месяца

// Подсчет проверенных заданий за текущий месяц
$gradedassignmentscount = 0;
if (!empty($teachercourses)) {
    $courseids = array_keys($teachercourses);
    $courseids_placeholders = implode(',', array_fill(0, count($courseids), '?'));
    
    // Получаем все задания из курсов преподавателя
    $allassignments = [];
    foreach ($teachercourses as $course) {
        try {
            $assignments = get_all_instances_in_course('assign', $course, false);
            foreach ($assignments as $assignment) {
                $allassignments[] = $assignment->id;
            }
        } catch (\Exception $e) {
            // Пропускаем курс, если не удалось получить задания
        }
    }
    
    if (!empty($allassignments)) {
        $assignmentids_placeholders = implode(',', array_fill(0, count($allassignments), '?'));
        // Подсчитываем оценки, поставленные текущим преподавателем в текущем месяце
        // Проверяем через assign_grades, где timemodified в текущем месяце
        // И проверяем, что это оценка от преподавателя (grader = USER->id или проверяем через контекст)
        $gradedassignmentscount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ag.id)
             FROM {assign_grades} ag
             JOIN {assign} a ON a.id = ag.assignment
             WHERE ag.assignment IN ($assignmentids_placeholders)
             AND ag.timemodified >= ? AND ag.timemodified <= ?
             AND ag.grader = ?",
            array_merge($allassignments, [$selectedmonthstart, $selectedmonthend, $USER->id])
        );
    }
}

// Подсчет ответов на форумы за текущий месяц
$forumrepliescount = 0;
if (!empty($teachercourses)) {
    $allforums = [];
    foreach ($teachercourses as $course) {
        try {
            $forums = get_all_instances_in_course('forum', $course, false);
            foreach ($forums as $forum) {
                $allforums[] = $forum->id;
            }
        } catch (\Exception $e) {
            // Пропускаем курс, если не удалось получить форумы
        }
    }
    
    if (!empty($allforums)) {
        $forumids_placeholders = implode(',', array_fill(0, count($allforums), '?'));
        // Подсчитываем ответы преподавателя на форумах в текущем месяце
        $forumrepliescount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.id)
             FROM {forum_posts} p
             JOIN {forum_discussions} d ON d.id = p.discussion
             WHERE d.forum IN ($forumids_placeholders)
             AND p.userid = ?
             AND p.created >= ? AND p.created <= ?
             AND p.parent > 0", // Только ответы, не начальные сообщения
            array_merge($allforums, [$USER->id, $selectedmonthstart, $selectedmonthend])
        );
    }
}

// Вывод страницы
echo $OUTPUT->header();
// Заголовок уже выводится через set_heading(), не нужно дублировать

// Вкладки с количеством
$tabs = [];
$assignmentsstr = get_string('assignments', 'local_deanpromoodle');
if (strpos($assignmentsstr, '[[') !== false) {
    $assignmentsstr = 'Задания'; // Резервное значение
}
$forumsstr = get_string('forums', 'local_deanpromoodle');
if (strpos($forumsstr, '[[') !== false) {
    $forumsstr = 'Форумы'; // Резервное значение
}
$tabs[] = new tabobject('assignments', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'assignments', 'courseid' => $courseid]),
    $assignmentsstr . ' (' . $assignmentscount . ')');
$tabs[] = new tabobject('forums', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'forums', 'courseid' => $courseid]),
    $forumsstr . ' (' . $forumscount . ')');
$tabs[] = new tabobject('history', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'history', 'courseid' => $courseid]),
    'История (' . $gradedassignmentshistorycount . ')');
$tabs[] = new tabobject('searchstudent', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'searchstudent']),
    'Поиск студента');

// Заголовок с информацией о преподавателе
echo html_writer::start_div('teacher-profile-header-main');
// Фото преподавателя
$userpicture = $OUTPUT->user_picture($USER, ['size' => 120, 'class' => 'userpicture']);
echo html_writer::div($userpicture, 'teacher-profile-photo-main');
// ФИО и email преподавателя
echo html_writer::start_div('teacher-profile-info-main');
$profileurl = new moodle_url('/user/profile.php', ['id' => $USER->id]);
echo html_writer::tag('h1', 
    html_writer::link($profileurl, fullname($USER), [
        'class' => 'teacher-profile-name-link',
        'target' => '_blank'
    ]),
    ['class' => 'teacher-profile-name-main']
);
if (!empty($USER->email)) {
    echo html_writer::div(
        html_writer::link('mailto:' . htmlspecialchars($USER->email, ENT_QUOTES, 'UTF-8'), htmlspecialchars($USER->email, ENT_QUOTES, 'UTF-8')),
        'teacher-profile-email-main'
    );
}
// Бейдж "Преподаватель"
echo html_writer::div('Преподаватель', 'teacher-profile-role-main');
echo html_writer::end_div(); // teacher-profile-info-main
echo html_writer::end_div(); // teacher-profile-header-main

echo $OUTPUT->tabtree($tabs, $tab);

// Блок со статистикой за выбранный месяц (не показываем на вкладке "Поиск студента")
if ($tab != 'searchstudent') {
    $monthnameru = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
    ];
    $monthdisplay = isset($monthnameru[$selectedmonth]) ? $monthnameru[$selectedmonth] . ' ' . $selectedyear : date('F Y', $selectedmonthstart);

    // Форма выбора месяца и года
    echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px; margin-bottom: 20px; padding: 15px;']);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/deanpromoodle/pages/teacher.php'),
        'class' => 'form-inline',
        'style' => 'margin-bottom: 10px;'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);

    echo html_writer::tag('strong', 'Статистика за: ', ['style' => 'margin-right: 10px;']);

    // Выбор месяца
    $monthoptions = [];
    foreach ($monthnameru as $num => $name) {
        $monthoptions[$num] = $name;
    }
    echo html_writer::label('Месяц: ', 'statmonth');
    echo html_writer::select($monthoptions, 'statmonth', $selectedmonth, false, ['class' => 'form-control', 'style' => 'display: inline-block; margin-left: 5px; margin-right: 10px;']);

    // Выбор года
    $yearoptions = [];
    $currentyear = date('Y');
    for ($y = $currentyear - 5; $y <= $currentyear + 1; $y++) {
        $yearoptions[$y] = $y;
    }
    echo html_writer::label('Год: ', 'statyear');
    echo html_writer::select($yearoptions, 'statyear', $selectedyear, false, ['class' => 'form-control', 'style' => 'display: inline-block; margin-left: 5px; margin-right: 10px;']);

    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Показать', 'class' => 'btn btn-primary', 'style' => 'margin-left: 10px;']);
    echo html_writer::end_tag('form');

    // Подсчет не проверенных заданий
    $ungradedassignmentscount = $assignmentscount;

    // Подсчет не отвеченных сообщений (с учетом таблицы local_deanpromoodle_forum_no_reply)
    $unrepliedforumscount = 0;
    if (!empty($teachercourses) && !empty($teacherroleids) && $studentroleid) {
        $forumids = [];
        $courseforums = [];
        foreach ($teachercourses as $course) {
            $forums = get_all_instances_in_course('forum', $course, false);
            foreach ($forums as $forum) {
                $forumids[] = $forum->id;
                $courseforums[$forum->id] = $course;
            }
        }
        if (!empty($forumids)) {
            $coursecontexts = [];
            $allcourseids = array_unique(array_column($courseforums, 'id'));
            foreach ($allcourseids as $cid) {
                $coursecontexts[$cid] = context_course::instance($cid);
            }
            $systemcontext = context_system::instance();
            $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
            $coursecontextids = array_map(function($ctx) { return $ctx->id; }, $coursecontexts);
            $coursecontextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
            
            $allteacheruserids = $DB->get_fieldset_sql(
                "SELECT DISTINCT ra.userid
                 FROM {role_assignments} ra
                 WHERE (ra.contextid IN ($coursecontextplaceholders) OR ra.contextid = ?)
                 AND ra.roleid IN ($placeholders)",
                array_merge($coursecontextids, [$systemcontext->id], $teacherroleids)
            );
            
            $allstudentuserids = $DB->get_fieldset_sql(
                "SELECT DISTINCT ra.userid
                 FROM {role_assignments} ra
                 WHERE ra.contextid IN ($coursecontextplaceholders)
                 AND ra.roleid = ?",
                array_merge($coursecontextids, [$studentroleid])
            );
            
            if (!empty($allteacheruserids) && !empty($allstudentuserids)) {
                $forumplaceholders = implode(',', array_fill(0, count($forumids), '?'));
                $teacherplaceholders = implode(',', array_fill(0, count($allteacheruserids), '?'));
                $studentplaceholders = implode(',', array_fill(0, count($allstudentuserids), '?'));
                
                // Проверяем существование таблицы local_deanpromoodle_forum_no_reply
                $dbman = $DB->get_manager();
                $tableexists = $dbman->table_exists('local_deanpromoodle_forum_no_reply');
                
                $noreplyjoin = '';
                $noreplywhere = '';
                if ($tableexists) {
                    $noreplyjoin = "LEFT JOIN {local_deanpromoodle_forum_no_reply} fnr ON fnr.postid = p.id";
                    $noreplywhere = "AND fnr.id IS NULL";
                }
                
                $unrepliedforumscount = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT p.id)
                     FROM {forum_posts} p
                     JOIN {user} u ON u.id = p.userid
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     JOIN {forum} f ON f.id = d.forum
                     LEFT JOIN {forum_posts} p2 ON p2.discussion = p.discussion 
                         AND p2.created > p.created 
                         AND p2.userid IN ($teacherplaceholders)
                     $noreplyjoin
                     WHERE d.forum IN ($forumplaceholders)
                     AND p.userid IN ($studentplaceholders)
                     AND p2.id IS NULL
                     $noreplywhere",
                    array_merge($forumids, $allteacheruserids, $allstudentuserids)
                );
            }
        }
    }

    // Отображение статистики в новом формате
    echo html_writer::start_div('', ['style' => 'margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);']);
    echo html_writer::start_tag('table', ['class' => 'teacher-statistics-table', 'style' => 'width: 100%; border-collapse: collapse;']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', 'СДЕЛАНО', ['style' => 'background-color: #d4edda; padding: 10px; text-align: center; border: 1px solid #c3e6cb;']);
    echo html_writer::tag('th', 'НЕ СДЕЛАНО', ['style' => 'background-color: #f8d7da; padding: 10px; text-align: center; border: 1px solid #f5c6cb;']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    echo html_writer::start_tag('tr');
    echo html_writer::start_tag('td', ['style' => 'background-color: #d4edda; padding: 10px; border: 1px solid #c3e6cb;']);
    echo html_writer::tag('div', 'Проверено заданий: ' . $gradedassignmentscount, ['style' => 'margin-bottom: 5px;']);
    echo html_writer::tag('div', 'Ответов на сообщения: ' . $forumrepliescount, ['style' => '']);
    echo html_writer::end_tag('td');
    echo html_writer::start_tag('td', ['style' => 'background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;']);
    echo html_writer::tag('div', 'Не проверено заданий: ' . $ungradedassignmentscount, ['style' => 'margin-bottom: 5px;']);
    echo html_writer::tag('div', 'Не отвечено сообщений: ' . $unrepliedforumscount, ['style' => '']);
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Фильтр по курсам
if (count($teachercourses) > 1) {
    echo html_writer::start_div('local-deanpromoodle-teacher-filters', ['style' => 'margin-bottom: 20px; margin-top: 20px;']);
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/deanpromoodle/pages/teacher.php'),
        'class' => 'form-inline'
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    $allcoursesstr = get_string('allcourses', 'local_deanpromoodle');
    if (strpos($allcoursesstr, '[[') !== false) {
        $allcoursesstr = 'Все курсы'; // Резервное значение на русском
    }
    $courseoptions = [0 => $allcoursesstr];
    foreach ($teachercourses as $cid => $c) {
        $courseoptions[$cid] = $c->fullname;
    }
    echo html_writer::label('Поиск: ', 'courseid');
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control', 'style' => 'display: inline-block; margin-left: 5px;']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => 'Поиск', 'class' => 'btn btn-primary', 'style' => 'margin-left: 10px;']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

// Содержимое в зависимости от выбранной вкладки
switch ($tab) {
    case 'assignments':
        // Получение неоцененных заданий
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $ungradedassignments = [];
        foreach ($teachercourses as $course) {
            $coursecontext = context_course::instance($course->id);
            $assignments = get_all_instances_in_course('assign', $course, false);
            
            foreach ($assignments as $assignment) {
                $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                if (!$cm) {
                    continue;
                }
                $assigncontext = context_module::instance($cm->id);
                
                // Используем Moodle API для получения неоцененных заданий
                try {
                    $assignobj = new assign($assigncontext, $cm, $course);
                    
                    // Получаем всех участников курса
                    $participants = $assignobj->list_participants(0, true);
                    
                    foreach ($participants as $participant) {
                        // Проверяем статус отправки
                        $submission = $assignobj->get_user_submission($participant->id, false);
                        
                        if ($submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                            // Проверяем наличие оценки
                            $grade = $assignobj->get_user_grade($participant->id, false);
                            
                            // Если нет оценки или оценка NULL, добавляем в список
                            if (!$grade || $grade->grade === null || $grade->grade < 0) {
                                // Убеждаемся, что объект участника содержит нужные поля для fullname()
                                if (!isset($participant->firstname) || !isset($participant->lastname)) {
                                    // Если полей нет, получаем пользователя из БД
                                    $user = $DB->get_record('user', ['id' => $participant->id], 'id, firstname, lastname, email');
                                    if ($user) {
                                        $participant->firstname = $user->firstname;
                                        $participant->lastname = $user->lastname;
                                        if (!isset($participant->email)) {
                                            $participant->email = $user->email;
                                        }
                                    }
                                }
                                
                                $ungradedassignments[] = (object)[
                                    'id' => $submission->id,
                                    'assignmentid' => $assignment->id,
                                    'cmid' => $cm->id,
                                    'assignmentname' => $assignment->name,
                                    'courseid' => $course->id,
                                    'coursename' => $course->fullname,
                                    'courseshortname' => $course->shortname,
                                    'userid' => $participant->id,
                                    'studentname' => fullname($participant),
                                    'email' => isset($participant->email) ? $participant->email : '',
                                    'submitted' => userdate($submission->timemodified),
                                    'timemodified' => $submission->timemodified
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Если не удалось использовать API, используем прямой SQL-запрос
                    $submissions = $DB->get_records_sql(
                        "SELECT DISTINCT s.*, u.firstname, u.lastname, u.email, u.id as userid
                         FROM {assign_submission} s
                         JOIN {user} u ON u.id = s.userid
                         WHERE s.assignment = ? 
                         AND s.status = 'submitted'
                         AND s.timemodified > 0
                         AND NOT EXISTS (
                             SELECT 1 FROM {assign_grades} g 
                             WHERE g.assignment = s.assignment 
                             AND g.userid = s.userid
                             AND g.grade IS NOT NULL
                             AND g.grade >= 0
                         )
                         ORDER BY s.timemodified DESC",
                        [$assignment->id]
                    );
                    
                    foreach ($submissions as $submission) {
                        $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                        if ($cm) {
                            // Создаем объект пользователя для правильного получения ФИО
                            $user = new stdClass();
                            $user->id = $submission->userid;
                            $user->firstname = $submission->firstname;
                            $user->lastname = $submission->lastname;
                            
                            $ungradedassignments[] = (object)[
                                'id' => $submission->id,
                                'assignmentid' => $assignment->id,
                                'cmid' => $cm->id,
                                'assignmentname' => $assignment->name,
                                'courseid' => $course->id,
                                'coursename' => $course->fullname,
                                'courseshortname' => $course->shortname,
                                'userid' => $submission->userid,
                                'studentname' => fullname($user),
                                'email' => $submission->email,
                                'submitted' => userdate($submission->timemodified),
                                'timemodified' => $submission->timemodified
                            ];
                        }
                    }
                }
            }
        }
        
        // Сортируем по дате отправки (новые первыми)
        usort($ungradedassignments, function($a, $b) {
            return $b->timemodified - $a->timemodified;
        });
        
        // Pagination
        $total = count($ungradedassignments);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($ungradedassignments, $offset, $perpage);
        
        // Display table
        if (empty($paginated)) {
            echo html_writer::div(get_string('noassignmentsfound', 'local_deanpromoodle'), 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            $coursestr = get_string('courses', 'local_deanpromoodle');
            if (strpos($coursestr, '[[') !== false) {
                $coursestr = 'Курс';
            }
            $assignmentstr = get_string('assignments', 'local_deanpromoodle');
            if (strpos($assignmentstr, '[[') !== false) {
                $assignmentstr = 'Задание';
            }
            $actionsstr = get_string('actions', 'local_deanpromoodle');
            if (strpos($actionsstr, '[[') !== false) {
                $actionsstr = 'Действие';
            }
            echo html_writer::tag('th', $coursestr); // Курс
            echo html_writer::tag('th', $assignmentstr); // Задание
            echo html_writer::tag('th', 'ФИО студента');
            $submittedstr = 'Опубликовано';
            echo html_writer::tag('th', $submittedstr);
            echo html_writer::tag('th', $actionsstr); // Действие
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                // Используем краткое название курса
                $coursedisplayname = !empty($item->courseshortname) ? htmlspecialchars($item->courseshortname) : htmlspecialchars($item->coursename);
                echo html_writer::tag('td', $coursedisplayname);
                echo html_writer::tag('td', htmlspecialchars($item->assignmentname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->submitted);
                // Используем прямой переход к странице оценки конкретного студента
                $gradeurl = new moodle_url('/mod/assign/view.php', [
                    'id' => $item->cmid, 
                    'action' => 'grade', 
                    'userid' => $item->userid,
                    'rownum' => 0
                ]);
                $gradestr = 'Оценить';
                $actions = html_writer::link($gradeurl, $gradestr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            // Убеждаемся, что все переменные определены и являются числами
            $totalcount = isset($total) ? (is_array($total) ? count($total) : (int)$total) : 0;
            $pagenum = isset($page) ? (is_array($page) ? 0 : (int)$page) : 0;
            $totalpagesnum = isset($totalpages) ? (is_array($totalpages) ? 0 : (int)$totalpages) : 0;
            
            // Убеждаемся, что параметры для URL являются правильных типов
            $tabparam = is_array($tab) ? 'forums' : (string)$tab;
            $courseidparam = is_array($courseid) ? 0 : (int)$courseid;
            $perpageparam = is_array($perpage) ? 25 : (int)$perpage;
            
            if ($totalpagesnum > 1) {
                echo '<div class="pagination-wrapper">';
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tabparam, 'courseid' => $courseidparam, 'perpage' => $perpageparam]);
                
                // Кнопка "Назад"
                if ($pagenum > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $pagenum - 1);
                    echo '<a href="' . $prevurl . '" class="pagination-btn prev-btn"><span class="arrow-icon">◀</span> Назад</a>';
                } else {
                    echo '<span class="pagination-btn prev-btn disabled"><span class="arrow-icon">◀</span> Назад</span>';
                }
                
                // Информация о страницах
                $pagenumval = (int)$pagenum + 1;
                $totalpagesnumval = (int)$totalpagesnum;
                $totalcountval = (int)$totalcount;
                
                echo '<div class="pagination-info">';
                echo 'Страница <span class="current-page">' . $pagenumval . '</span> ';
                echo '<span class="total-pages">из ' . $totalpagesnumval . '</span>';
                echo '<span class="items-count">| ' . $totalcountval . ' записей</span>';
                echo '</div>';
                
                // Кнопка "Вперед"
                if ($pagenum < $totalpagesnum - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $pagenum + 1);
                    echo '<a href="' . $nexturl . '" class="pagination-btn next-btn">Вперёд <span class="arrow-icon">▶</span></a>';
                } else {
                    echo '<span class="pagination-btn next-btn disabled">Вперёд <span class="arrow-icon">▶</span></span>';
                }
                
                echo '</div>';
            }
        }
        break;
        
    case 'forums':
        // Оптимизированное получение сообщений форумов без ответов преподавателя
        $unrepliedposts = [];
        
        // Получаем роли один раз перед циклом
        $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        
        if (empty($teacherroleids) || !$studentroleid) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
            break;
        }
        
        // Получаем все ID форумов из всех курсов одним запросом
        $forumids = [];
        $courseforums = []; // Маппинг forum_id => course_id
        foreach ($teachercourses as $course) {
            $forums = get_all_instances_in_course('forum', $course, false);
            foreach ($forums as $forum) {
                $forumids[] = $forum->id;
                $courseforums[$forum->id] = $course;
            }
        }
        
        if (empty($forumids)) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
            break;
        }
        
        // Получаем все контексты курсов один раз
        $coursecontexts = [];
        $allcourseids = array_unique(array_column($courseforums, 'id'));
        foreach ($allcourseids as $cid) {
            $coursecontexts[$cid] = context_course::instance($cid);
        }
        
        // Получаем всех преподавателей для всех курсов одним запросом
        $systemcontext = context_system::instance();
        $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
        $coursecontextids = array_map(function($ctx) { return $ctx->id; }, $coursecontexts);
        $coursecontextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
        
        $allteacheruserids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
             FROM {role_assignments} ra
             WHERE (ra.contextid IN ($coursecontextplaceholders) OR ra.contextid = ?)
             AND ra.roleid IN ($placeholders)",
            array_merge($coursecontextids, [$systemcontext->id], $teacherroleids)
        );
        
        if (empty($allteacheruserids)) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
            break;
        }
        
        // Получаем всех студентов для всех курсов одним запросом
        $allstudentuserids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
             FROM {role_assignments} ra
             WHERE ra.contextid IN ($coursecontextplaceholders)
             AND ra.roleid = ?",
            array_merge($coursecontextids, [$studentroleid])
        );
        
        if (empty($allstudentuserids)) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
            break;
        }
        
        // Оптимизированный запрос: получаем все сообщения одним запросом
        $forumplaceholders = implode(',', array_fill(0, count($forumids), '?'));
        $teacherplaceholders = implode(',', array_fill(0, count($allteacheruserids), '?'));
        $studentplaceholders = implode(',', array_fill(0, count($allstudentuserids), '?'));
        
        // Проверяем существование таблицы local_deanpromoodle_forum_no_reply
        $dbman = $DB->get_manager();
        $tableexists = $dbman->table_exists('local_deanpromoodle_forum_no_reply');
        
        // Используем LEFT JOIN вместо NOT EXISTS для лучшей производительности
        // Исключаем сообщения, которые помечены как "не требует ответа"
        $noreplyjoin = '';
        $noreplywhere = '';
        if ($tableexists) {
            $noreplyjoin = "LEFT JOIN {local_deanpromoodle_forum_no_reply} fnr ON fnr.postid = p.id";
            $noreplywhere = "AND fnr.id IS NULL";
        }
        
        $posts = $DB->get_records_sql(
            "SELECT p.id, p.discussion, p.userid, p.subject, p.message, p.created,
                    u.firstname, u.lastname, u.email,
                    d.name as discussionname, d.forum,
                    f.name as forumname, f.course as courseid
             FROM {forum_posts} p
             JOIN {user} u ON u.id = p.userid
             JOIN {forum_discussions} d ON d.id = p.discussion
             JOIN {forum} f ON f.id = d.forum
             LEFT JOIN {forum_posts} p2 ON p2.discussion = p.discussion 
                 AND p2.created > p.created 
                 AND p2.userid IN ($teacherplaceholders)
             $noreplyjoin
             WHERE d.forum IN ($forumplaceholders)
             AND p.userid IN ($studentplaceholders)
             AND p2.id IS NULL
             $noreplywhere
             ORDER BY p.created DESC
             LIMIT 1000",
            array_merge($forumids, $allteacheruserids, $allstudentuserids)
        );
        
        // Обрабатываем результаты
        foreach ($posts as $post) {
            $course = $courseforums[$post->forum];
            
            // Обрезаем текст сообщения для отображения (только первые 10 слов)
            $fullmessage = strip_tags($post->message);
            $words = preg_split('/\s+/', trim($fullmessage));
            $wordlimit = 10;
            
            if (count($words) > $wordlimit) {
                $message = implode(' ', array_slice($words, 0, $wordlimit)) . '...';
            } else {
                $message = $fullmessage;
            }
            
            // Убеждаемся, что все значения являются строками/числами, а не массивами
            $studentname = fullname($post);
            if (is_array($studentname)) {
                $studentname = isset($post->firstname) && isset($post->lastname) 
                    ? trim($post->firstname . ' ' . $post->lastname) 
                    : '';
            }
            
            $unrepliedposts[] = (object)[
                'id' => isset($post->id) ? (int)$post->id : 0,
                'forumid' => isset($post->forum) ? (int)$post->forum : 0,
                'forumname' => isset($post->forumname) && !is_array($post->forumname) ? (string)$post->forumname : '',
                'discussionid' => isset($post->discussion) ? (int)$post->discussion : 0,
                'discussionname' => isset($post->discussionname) && !is_array($post->discussionname) ? (string)$post->discussionname : '',
                'courseid' => isset($post->courseid) ? (int)$post->courseid : 0,
                'coursename' => isset($course->fullname) && !is_array($course->fullname) ? (string)$course->fullname : '',
                'courseshortname' => isset($course->shortname) && !is_array($course->shortname) ? (string)$course->shortname : '',
                'userid' => isset($post->userid) ? (int)$post->userid : 0,
                'studentname' => (string)$studentname,
                'email' => isset($post->email) && !is_array($post->email) ? (string)$post->email : '',
                'subject' => isset($post->subject) && !is_array($post->subject) ? (string)$post->subject : '',
                'message' => (string)$message,
                'fullmessage' => (string)$fullmessage,
                'posted' => isset($post->created) ? userdate($post->created) : '',
                'created' => isset($post->created) ? (int)$post->created : 0
            ];
        }
        
        // Pagination
        $total = count($unrepliedposts);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($unrepliedposts, $offset, $perpage);
        
        // Отображение таблицы
        if (empty($paginated)) {
            echo html_writer::div(get_string('noforumspostsfound', 'local_deanpromoodle'), 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            // Получение и проверка языковых строк с fallback на русский
            $coursestr = get_string('courses', 'local_deanpromoodle');
            if (strpos($coursestr, '[[') !== false) {
                $coursestr = 'Курс';
            }
            $forumsstr = get_string('forums', 'local_deanpromoodle');
            if (strpos($forumsstr, '[[') !== false) {
                $forumsstr = 'Форум';
            }
            $fullnamestr = get_string('fullname', 'local_deanpromoodle');
            if (strpos($fullnamestr, '[[') !== false) {
                $fullnamestr = 'Студент';
            }
            $actionsstr = get_string('actions', 'local_deanpromoodle');
            if (strpos($actionsstr, '[[') !== false) {
                $actionsstr = 'Действия';
            }
            echo html_writer::tag('th', $coursestr); // Курс
            echo html_writer::tag('th', $forumsstr); // Форум
            echo html_writer::tag('th', 'ФИО студента');
            $subjectstr = 'Тема';
            echo html_writer::tag('th', $subjectstr);
            $messagestr = 'Сообщение студента';
            echo html_writer::tag('th', $messagestr);
            $postedstr = 'Опубликовано';
            echo html_writer::tag('th', $postedstr);
            echo html_writer::tag('th', $actionsstr); // Действия
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                // Проверяем и приводим ID к правильному типу
                $postid = isset($item->id) && !is_array($item->id) ? (int)$item->id : 0;
                $discussionid = isset($item->discussionid) && !is_array($item->discussionid) ? (int)$item->discussionid : 0;
                
                echo html_writer::start_tag('tr', ['id' => 'forum-post-' . $postid]);
                // Используем краткое название курса с проверкой типов
                $courseshortname = isset($item->courseshortname) && !is_array($item->courseshortname) ? (string)$item->courseshortname : '';
                $coursename = isset($item->coursename) && !is_array($item->coursename) ? (string)$item->coursename : '';
                $coursedisplayname = !empty($courseshortname) ? htmlspecialchars($courseshortname) : htmlspecialchars($coursename);
                echo html_writer::tag('td', $coursedisplayname);
                
                $forumname = isset($item->forumname) && !is_array($item->forumname) ? (string)$item->forumname : '';
                echo html_writer::tag('td', htmlspecialchars($forumname));
                
                $studentname = isset($item->studentname) && !is_array($item->studentname) ? (string)$item->studentname : '';
                echo html_writer::tag('td', htmlspecialchars($studentname));
                
                $subject = isset($item->subject) && !is_array($item->subject) ? (string)$item->subject : '';
                echo html_writer::tag('td', htmlspecialchars($subject));
                
                $message = isset($item->message) && !is_array($item->message) ? (string)$item->message : '';
                echo html_writer::tag('td', htmlspecialchars($message), ['style' => 'max-width: 300px; word-wrap: break-word;']);
                
                $posted = isset($item->posted) && !is_array($item->posted) ? (string)$item->posted : '';
                echo html_writer::tag('td', $posted);
                // Создаем URL с якорем для перехода к конкретному сообщению
                // Используем discuss.php с параметром reply для открытия формы ответа и якорем для прокрутки
                $posturl = new moodle_url('/mod/forum/discuss.php', [
                    'd' => $discussionid,
                    'reply' => $postid // ID сообщения, на которое отвечаем
                ]);
                $posturl->set_anchor('p' . $postid); // Якорь для прокрутки к сообщению
                $replystr = 'Ответить';
                $noreplystr = 'Не требует ответа';
                $actions = html_writer::link($posturl, $replystr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank', 'style' => 'margin-right: 8px; margin-top: 5px;']);
                $actions .= html_writer::link('#', $noreplystr, [
                    'class' => 'btn btn-sm btn-secondary forum-no-reply-btn',
                    'data-postid' => $postid,
                    'onclick' => 'return false;',
                    'style' => 'margin-top: 5px;'
                ]);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            // Убеждаемся, что все переменные определены и являются числами
            $totalcount = isset($total) ? (is_array($total) ? count($total) : (int)$total) : 0;
            $pagenum = isset($page) ? (is_array($page) ? 0 : (int)$page) : 0;
            $totalpagesnum = isset($totalpages) ? (is_array($totalpages) ? 0 : (int)$totalpages) : 0;
            
            // Убеждаемся, что параметры для URL являются правильных типов
            $tabparam = is_array($tab) ? 'forums' : (string)$tab;
            $courseidparam = is_array($courseid) ? 0 : (int)$courseid;
            $perpageparam = is_array($perpage) ? 25 : (int)$perpage;
            
            if ($totalpagesnum > 1) {
                echo '<div class="pagination-wrapper">';
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tabparam, 'courseid' => $courseidparam, 'perpage' => $perpageparam]);
                
                // Кнопка "Назад"
                if ($pagenum > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $pagenum - 1);
                    echo '<a href="' . $prevurl . '" class="pagination-btn prev-btn"><span class="arrow-icon">◀</span> Назад</a>';
                } else {
                    echo '<span class="pagination-btn prev-btn disabled"><span class="arrow-icon">◀</span> Назад</span>';
                }
                
                // Информация о страницах
                $pagenumval = (int)$pagenum + 1;
                $totalpagesnumval = (int)$totalpagesnum;
                $totalcountval = (int)$totalcount;
                
                echo '<div class="pagination-info">';
                echo 'Страница <span class="current-page">' . $pagenumval . '</span> ';
                echo '<span class="total-pages">из ' . $totalpagesnumval . '</span>';
                echo '<span class="items-count">| ' . $totalcountval . ' записей</span>';
                echo '</div>';
                
                // Кнопка "Вперед"
                if ($pagenum < $totalpagesnum - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $pagenum + 1);
                    echo '<a href="' . $nexturl . '" class="pagination-btn next-btn">Вперёд <span class="arrow-icon">▶</span></a>';
                } else {
                    echo '<span class="pagination-btn next-btn disabled">Вперёд <span class="arrow-icon">▶</span></span>';
                }
                
                echo '</div>';
            }
        }
        break;
    
    case 'history':
        // Получение проверенных заданий (за все время)
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $gradedassignments = [];
        
        if (!empty($teachercourses)) {
            $allassignments = [];
            foreach ($teachercourses as $course) {
                try {
                    $assignments = get_all_instances_in_course('assign', $course, false);
                    foreach ($assignments as $assignment) {
                        $allassignments[] = ['assignment' => $assignment, 'course' => $course];
                    }
                } catch (\Exception $e) {
                    // Пропускаем курс, если не удалось получить задания
                }
            }
            
            if (!empty($allassignments)) {
                foreach ($allassignments as $item) {
                    $assignment = $item['assignment'];
                    $course = $item['course'];
                    
                    // Получаем все проверенные задания текущим преподавателем
                    $grades = $DB->get_records_sql(
                        "SELECT ag.*, u.firstname, u.lastname, u.email, u.id as userid, a.name as assignmentname
                         FROM {assign_grades} ag
                         JOIN {assign} a ON a.id = ag.assignment
                         JOIN {user} u ON u.id = ag.userid
                         WHERE ag.assignment = ?
                         AND ag.grader = ?
                         AND ag.grade IS NOT NULL
                         AND ag.grade >= 0
                         ORDER BY ag.timemodified DESC",
                        [$assignment->id, $USER->id]
                    );
                    
                    foreach ($grades as $grade) {
                        $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                        if ($cm) {
                            $gradedassignments[] = (object)[
                                'id' => $grade->id,
                                'assignmentid' => $assignment->id,
                                'cmid' => $cm->id,
                                'assignmentname' => $grade->assignmentname,
                                'courseid' => $course->id,
                                'coursename' => $course->fullname,
                                'courseshortname' => $course->shortname,
                                'userid' => $grade->userid,
                                'studentname' => fullname($grade),
                                'email' => $grade->email,
                                'grade' => $grade->grade,
                                'graded' => userdate($grade->timemodified),
                                'timemodified' => $grade->timemodified
                            ];
                        }
                    }
                }
            }
        }
        
        // Сортируем по дате проверки (новые первыми)
        usort($gradedassignments, function($a, $b) {
            return $b->timemodified - $a->timemodified;
        });
        
        // Pagination
        $total = count($gradedassignments);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($gradedassignments, $offset, $perpage);
        
        // Display table
        if (empty($paginated)) {
            echo html_writer::div('Проверенные задания не найдены', 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', 'Курс');
            echo html_writer::tag('th', 'Задание');
            echo html_writer::tag('th', 'ФИО студента');
            echo html_writer::tag('th', 'Дата проверки');
            echo html_writer::tag('th', 'Оценка');
            echo html_writer::tag('th', 'Действие');
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                // Используем краткое название курса
                $coursedisplayname = !empty($item->courseshortname) ? htmlspecialchars($item->courseshortname) : htmlspecialchars($item->coursename);
                echo html_writer::tag('td', $coursedisplayname);
                echo html_writer::tag('td', htmlspecialchars($item->assignmentname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->graded);
                echo html_writer::tag('td', htmlspecialchars($item->grade));
                // Ссылка на задание для просмотра
                $viewurl = new moodle_url('/mod/assign/view.php', [
                    'id' => $item->cmid, 
                    'action' => 'grade', 
                    'userid' => $item->userid,
                    'rownum' => 0
                ]);
                $viewstr = 'Просмотр';
                $actions = html_writer::link($viewurl, $viewstr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            // Убеждаемся, что все переменные определены и являются числами
            $totalcount = isset($total) ? (is_array($total) ? count($total) : (int)$total) : 0;
            $pagenum = isset($page) ? (is_array($page) ? 0 : (int)$page) : 0;
            $totalpagesnum = isset($totalpages) ? (is_array($totalpages) ? 0 : (int)$totalpages) : 0;
            
            // Убеждаемся, что параметры для URL являются правильных типов
            $tabparam = is_array($tab) ? 'forums' : (string)$tab;
            $courseidparam = is_array($courseid) ? 0 : (int)$courseid;
            $perpageparam = is_array($perpage) ? 25 : (int)$perpage;
            
            if ($totalpagesnum > 1) {
                echo '<div class="pagination-wrapper">';
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tabparam, 'courseid' => $courseidparam, 'perpage' => $perpageparam]);
                
                // Кнопка "Назад"
                if ($pagenum > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $pagenum - 1);
                    echo '<a href="' . $prevurl . '" class="pagination-btn prev-btn"><span class="arrow-icon">◀</span> Назад</a>';
                } else {
                    echo '<span class="pagination-btn prev-btn disabled"><span class="arrow-icon">◀</span> Назад</span>';
                }
                
                // Информация о страницах
                $pagenumval = (int)$pagenum + 1;
                $totalpagesnumval = (int)$totalpagesnum;
                $totalcountval = (int)$totalcount;
                
                echo '<div class="pagination-info">';
                echo 'Страница <span class="current-page">' . $pagenumval . '</span> ';
                echo '<span class="total-pages">из ' . $totalpagesnumval . '</span>';
                echo '<span class="items-count">| ' . $totalcountval . ' записей</span>';
                echo '</div>';
                
                // Кнопка "Вперед"
                if ($pagenum < $totalpagesnum - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $pagenum + 1);
                    echo '<a href="' . $nexturl . '" class="pagination-btn next-btn">Вперёд <span class="arrow-icon">▶</span></a>';
                } else {
                    echo '<span class="pagination-btn next-btn disabled">Вперёд <span class="arrow-icon">▶</span></span>';
                }
                
                echo '</div>';
            }
        }
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
        global $CFG;
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
                var ajaxUrl = " . json_encode($ajaxurl) . ";
                
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
        
        // JavaScript для обработки кнопки "Не требует ответа"
        global $CFG;
        $ajaxurl = $CFG->wwwroot . '/local/deanpromoodle/pages/admin_ajax.php';
        $PAGE->requires->js_init_code("
            (function() {
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('forum-no-reply-btn')) {
                        e.preventDefault();
                        var postid = e.target.getAttribute('data-postid');
                        var row = e.target.closest('tr');
                        
                        if (!postid) {
                            alert('Ошибка: не указан ID сообщения');
                            return;
                        }
                        
                        // Отправляем AJAX-запрос
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', '" . $ajaxurl . "?action=markforumpostnoreply&postid=' + postid, true);
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            // Скрываем строку таблицы
                                            if (row) {
                                                row.style.display = 'none';
                                            }
                                        } else {
                                            alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                                        }
                                    } catch (e) {
                                        alert('Ошибка при обработке ответа сервера');
                                    }
                                } else {
                                    alert('Ошибка сети: ' + xhr.status);
                                }
                            }
                        };
                        xhr.send();
                    }
                });
            })();
        ");
        break;
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
