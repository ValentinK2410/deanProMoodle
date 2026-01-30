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

// Получение параметров
$tab = optional_param('tab', 'assignments', PARAM_ALPHA); // assignments, quizzes, forums
$courseid = optional_param('courseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/teacher.php', [
    'tab' => $tab,
    'courseid' => $courseid,
    'page' => $page,
    'perpage' => $perpage
]));
$PAGE->set_context(context_system::instance());
// Получение заголовка с проверкой и fallback на русский
$pagetitle = get_string('teacherpagetitle', 'local_deanpromoodle');
if (strpos($pagetitle, '[[') !== false || $pagetitle == 'Teacher Dashboard') {
    $pagetitle = 'Панель преподавателя'; // Fallback на русский
}
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
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
                 WHERE g.assignment = s.assignment AND g.userid = s.userid
             )",
            [$assignment->id]
        );
        $assignmentscount += $submissionscount;
    }
}

// Подсчет несданных экзаменов
$quizzescount = 0;
foreach ($teachercourses as $course) {
    $quizzes = get_all_instances_in_course('quiz', $course, false);
    foreach ($quizzes as $quiz) {
        $quizname = mb_strtolower($quiz->name);
        if (strpos($quizname, 'экзамен') === false) {
            continue;
        }
        $attemptscount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT qa.id)
             FROM {quiz_attempts} qa
             LEFT JOIN {quiz_grades} qg ON qg.quiz = qa.quiz AND qg.userid = qa.userid
             WHERE qa.quiz = ? 
             AND qa.state = 'finished'
             AND NOT (qa.sumgrades > 0 AND qa.timemodified > 0)
             AND qg.grade IS NULL",
            [$quiz->id]
        );
        $quizzescount += $attemptscount;
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

// Подсчет проверенных заданий, тестов и форумов за текущий календарный месяц
$currentmonthstart = mktime(0, 0, 0, date('n'), 1, date('Y')); // Первый день текущего месяца
$currentmonthend = mktime(23, 59, 59, date('n'), date('t'), date('Y')); // Последний день текущего месяца

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
            array_merge($allassignments, [$currentmonthstart, $currentmonthend, $USER->id])
        );
    }
}

// Подсчет проверенных тестов за текущий месяц
$gradedquizzescount = 0;
if (!empty($teachercourses)) {
    $allquizzes = [];
    foreach ($teachercourses as $course) {
        try {
            $quizzes = get_all_instances_in_course('quiz', $course, false);
            foreach ($quizzes as $quiz) {
                $allquizzes[] = $quiz->id;
            }
        } catch (\Exception $e) {
            // Пропускаем курс, если не удалось получить тесты
        }
    }
    
    if (!empty($allquizzes)) {
        $quizids_placeholders = implode(',', array_fill(0, count($allquizzes), '?'));
        // Подсчитываем оценки тестов, где timemodified в текущем месяце
        // Для quiz_grades нет поля grader, но можно проверить через попытки и оценки
        // Проверяем, что есть попытка и оценка в текущем месяце
        $gradedquizzescount = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT qg.id)
             FROM {quiz_grades} qg
             JOIN {quiz_attempts} qa ON qa.quiz = qg.quiz AND qa.userid = qg.userid
             WHERE qg.quiz IN ($quizids_placeholders)
             AND qg.timemodified >= ? AND qg.timemodified <= ?
             AND qa.state = 'finished'",
            array_merge($allquizzes, [$currentmonthstart, $currentmonthend])
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
            array_merge($allforums, [$USER->id, $currentmonthstart, $currentmonthend])
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
$quizzesstr = get_string('quizzes', 'local_deanpromoodle');
if (strpos($quizzesstr, '[[') !== false) {
    $quizzesstr = 'Тесты'; // Резервное значение
}
$forumsstr = get_string('forums', 'local_deanpromoodle');
if (strpos($forumsstr, '[[') !== false) {
    $forumsstr = 'Форумы'; // Резервное значение
}
$tabs[] = new tabobject('assignments', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'assignments', 'courseid' => $courseid]),
    $assignmentsstr . ' (' . $assignmentscount . ')');
$tabs[] = new tabobject('quizzes', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'quizzes', 'courseid' => $courseid]),
    $quizzesstr . ' (' . $quizzescount . ')');
$tabs[] = new tabobject('forums', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'forums', 'courseid' => $courseid]),
    $forumsstr . ' (' . $forumscount . ')');

echo $OUTPUT->tabtree($tabs, $tab);

// Блок со статистикой за текущий месяц
$monthname = strftime('%B %Y', $currentmonthstart);
$monthnameru = [
    'January' => 'Январь', 'February' => 'Февраль', 'March' => 'Март', 'April' => 'Апрель',
    'May' => 'Май', 'June' => 'Июнь', 'July' => 'Июль', 'August' => 'Август',
    'September' => 'Сентябрь', 'October' => 'Октябрь', 'November' => 'Ноябрь', 'December' => 'Декабрь'
];
$monthnameru_key = date('F', $currentmonthstart);
$monthdisplay = isset($monthnameru[$monthnameru_key]) ? $monthnameru[$monthnameru_key] . ' ' . date('Y', $currentmonthstart) : $monthname;

echo html_writer::start_div('alert alert-info', ['style' => 'margin-top: 20px; margin-bottom: 20px; padding: 15px;']);
echo html_writer::tag('strong', 'Статистика за ' . $monthdisplay . ': ', ['style' => 'margin-right: 15px;']);
echo html_writer::tag('span', 'Проверено заданий: ' . $gradedassignmentscount, ['style' => 'margin-right: 20px; color: #28a745; font-weight: 500;']);
echo html_writer::tag('span', 'Проверено тестов: ' . $gradedquizzescount, ['style' => 'margin-right: 20px; color: #007bff; font-weight: 500;']);
echo html_writer::tag('span', 'Ответов на форумах: ' . $forumrepliescount, ['style' => 'color: #17a2b8; font-weight: 500;']);
echo html_writer::end_div();

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
        $ungradedassignments = [];
        foreach ($teachercourses as $course) {
            $coursecontext = context_course::instance($course->id);
            $assignments = get_all_instances_in_course('assign', $course, false);
            
            foreach ($assignments as $assignment) {
                $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                $assigncontext = context_module::instance($cm->id);
                
                // Get submissions that need grading
                $submissions = $DB->get_records_sql(
                    "SELECT s.*, u.firstname, u.lastname, u.email, u.id as userid
                     FROM {assign_submission} s
                     JOIN {user} u ON u.id = s.userid
                     WHERE s.assignment = ? AND s.status = 'submitted' 
                     AND (s.timemodified > 0)
                     AND NOT EXISTS (
                         SELECT 1 FROM {assign_grades} g 
                         WHERE g.assignment = s.assignment AND g.userid = s.userid
                     )
                     ORDER BY s.timemodified DESC",
                    [$assignment->id]
                );
                
                foreach ($submissions as $submission) {
                    // Получаем ID модуля курса (cmid) для правильной ссылки
                    $cm = get_coursemodule_from_instance('assign', $assignment->id, $course->id);
                    $ungradedassignments[] = (object)[
                        'id' => $submission->id,
                        'assignmentid' => $assignment->id,
                        'cmid' => $cm->id, // ID модуля курса для ссылки
                        'assignmentname' => $assignment->name,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $submission->userid,
                        'studentname' => fullname($submission),
                        'email' => $submission->email,
                        'submitted' => userdate($submission->timemodified),
                        'timemodified' => $submission->timemodified
                    ];
                }
            }
        }
        
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
            $fullnamestr = get_string('fullname', 'local_deanpromoodle');
            if (strpos($fullnamestr, '[[') !== false) {
                $fullnamestr = 'Студент';
            }
            $actionsstr = get_string('actions', 'local_deanpromoodle');
            if (strpos($actionsstr, '[[') !== false) {
                $actionsstr = 'Действия';
            }
            echo html_writer::tag('th', $coursestr); // Курс
            echo html_writer::tag('th', $assignmentstr); // Задание
            echo html_writer::tag('th', $fullnamestr); // Студент
            $submittedstr = 'Отправлено';
            echo html_writer::tag('th', $submittedstr);
            echo html_writer::tag('th', $actionsstr); // Действия
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->assignmentname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->submitted);
                // Используем cmid (ID модуля курса) вместо assignmentid
                $gradeurl = new moodle_url('/mod/assign/view.php', ['id' => $item->cmid, 'action' => 'grading', 'userid' => $item->userid]);
                $gradestr = 'Оценить';
                $actions = html_writer::link($gradeurl, $gradestr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« Previous', ['class' => 'btn btn-sm']);
                }
                echo html_writer::span("Page " . ($page + 1) . " of " . $totalpages . " ($total items)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, 'Next »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
            }
        }
        break;
        
    case 'quizzes':
        // Получение только несданных экзаменов
        // Показываем только тесты с названием "Экзамен" и только если они не сданы
        // Условие: если есть оценка И дата оценки - не показывать такой тест
        $failedquizzes = [];
        foreach ($teachercourses as $course) {
            $quizzes = get_all_instances_in_course('quiz', $course, false);
            
            foreach ($quizzes as $quiz) {
                // Фильтруем только экзамены по названию (содержит "Экзамен" или "экзамен")
                $quizname = mb_strtolower($quiz->name);
                if (strpos($quizname, 'экзамен') === false) {
                    continue; // Пропускаем тесты, которые не являются экзаменами
                }
                
                // Получаем попытки экзаменов
                // Исключаем тесты, у которых есть оценка И дата завершения
                // Условие: НЕ показывать если (sumgrades > 0 AND timemodified > 0)
                $attempts = $DB->get_records_sql(
                    "SELECT qa.*, u.firstname, u.lastname, u.email, u.id as userid, q.name as quizname, q.sumgrades as maxgrade
                     FROM {quiz_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     JOIN {quiz} q ON q.id = qa.quiz
                     LEFT JOIN {quiz_grades} qg ON qg.quiz = qa.quiz AND qg.userid = qa.userid
                     WHERE qa.quiz = ? 
                     AND qa.state = 'finished'
                     AND NOT (qa.sumgrades > 0 AND qa.timemodified > 0)
                     AND qg.grade IS NULL
                     ORDER BY qa.timemodified DESC",
                    [$quiz->id]
                );
                
                foreach ($attempts as $attempt) {
                    // Дополнительная проверка: если есть оценка И дата - пропускаем (на всякий случай)
                    if ($attempt->sumgrades > 0 && $attempt->timemodified > 0) {
                        continue;
                    }
                    
                    $failedquizzes[] = (object)[
                        'id' => $attempt->id,
                        'quizid' => $quiz->id,
                        'quizname' => $quiz->name,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $attempt->userid,
                        'studentname' => fullname($attempt),
                        'email' => $attempt->email,
                        'grade' => ($attempt->sumgrades ?? 0) . ' / ' . $quiz->sumgrades,
                        'attempted' => $attempt->timemodified > 0 ? userdate($attempt->timemodified) : 'Не завершено',
                        'timemodified' => $attempt->timemodified
                    ];
                }
            }
        }
        
        // Пагинация
        $total = count($failedquizzes);
        $totalpages = $total > 0 ? ceil($total / $perpage) : 0;
        $offset = $page * $perpage;
        $paginated = array_slice($failedquizzes, $offset, $perpage);
        
        // Отображение таблицы
        if (empty($paginated)) {
            $noquizzesfoundstr = get_string('noquizzesfound', 'local_deanpromoodle');
            if (strpos($noquizzesfoundstr, '[[') !== false) {
                $noquizzesfoundstr = 'Несданные экзамены не найдены'; // Fallback на русский
            }
            echo html_writer::div($noquizzesfoundstr, 'alert alert-info');
        } else {
            echo html_writer::start_tag('table', ['class' => 'table table-striped table-hover', 'style' => 'width: 100%;']);
            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            $coursestr = get_string('courses', 'local_deanpromoodle');
            if (strpos($coursestr, '[[') !== false) {
                $coursestr = 'Курс';
            }
            $quizzesstr = get_string('quizzes', 'local_deanpromoodle');
            if (strpos($quizzesstr, '[[') !== false) {
                $quizzesstr = 'Тест';
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
            echo html_writer::tag('th', $quizzesstr); // Тест
            echo html_writer::tag('th', $fullnamestr); // Студент
            $gradestr = 'Оценка';
            echo html_writer::tag('th', $gradestr);
            $attemptedstr = 'Попытка';
            echo html_writer::tag('th', $attemptedstr);
            echo html_writer::tag('th', $actionsstr); // Действия
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($paginated as $item) {
                // Все записи в этой таблице - несданные экзамены, выделяем красным
                echo html_writer::start_tag('tr', ['style' => 'background-color: #ffebee; color: #c62828;']);
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->quizname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', html_writer::tag('strong', $item->grade));
                echo html_writer::tag('td', $item->attempted);
                $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $item->id]);
                $reviewstr = 'Просмотр';
                $actions = html_writer::link($reviewurl, $reviewstr, ['class' => 'btn btn-sm btn-danger', 'target' => '_blank']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Пагинация
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                $prevstr = get_string('previous', 'local_deanpromoodle');
                $nextstr = get_string('next', 'local_deanpromoodle');
                $pagestr = get_string('page', 'local_deanpromoodle');
                $ofstr = get_string('of', 'local_deanpromoodle');
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« ' . $prevstr, ['class' => 'btn btn-sm']);
                }
                echo html_writer::span($pagestr . " " . ($page + 1) . " " . $ofstr . " " . $totalpages . " ($total)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, $nextstr . ' »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
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
        
        // Используем LEFT JOIN вместо NOT EXISTS для лучшей производительности
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
             WHERE d.forum IN ($forumplaceholders)
             AND p.userid IN ($studentplaceholders)
             AND p2.id IS NULL
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
            
            $unrepliedposts[] = (object)[
                'id' => $post->id,
                'forumid' => $post->forum,
                'forumname' => $post->forumname,
                'discussionid' => $post->discussion,
                'discussionname' => $post->discussionname,
                'courseid' => $post->courseid,
                'coursename' => $course->fullname,
                'userid' => $post->userid,
                'studentname' => fullname($post),
                'email' => $post->email,
                'subject' => $post->subject,
                'message' => $message,
                'fullmessage' => $fullmessage,
                'posted' => userdate($post->created),
                'created' => $post->created
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
            $discussionstr = 'Обсуждение';
            echo html_writer::tag('th', $discussionstr);
            echo html_writer::tag('th', $fullnamestr); // Студент
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
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->forumname));
                echo html_writer::tag('td', htmlspecialchars($item->discussionname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', htmlspecialchars($item->subject));
                echo html_writer::tag('td', htmlspecialchars($item->message), ['style' => 'max-width: 300px; word-wrap: break-word;']);
                echo html_writer::tag('td', $item->posted);
                // Создаем URL с якорем для перехода к конкретному сообщению
                // Используем discuss.php с параметром reply для открытия формы ответа и якорем для прокрутки
                $posturl = new moodle_url('/mod/forum/discuss.php', [
                    'd' => $item->discussionid,
                    'reply' => $item->id // ID сообщения, на которое отвечаем
                ]);
                $posturl->set_anchor('p' . $item->id); // Якорь для прокрутки к сообщению
                $replystr = 'Ответить';
                $actions = html_writer::link($posturl, $replystr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
                echo html_writer::tag('td', $actions);
                echo html_writer::end_tag('tr');
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
            
            // Pagination
            if ($totalpages > 1) {
                echo html_writer::start_div('pagination-wrapper', ['style' => 'margin-top: 20px; text-align: center;']);
                $baseurl = new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => $tab, 'courseid' => $courseid, 'perpage' => $perpage]);
                if ($page > 0) {
                    $prevurl = clone $baseurl;
                    $prevurl->param('page', $page - 1);
                    echo html_writer::link($prevurl, '« Previous', ['class' => 'btn btn-sm']);
                }
                echo html_writer::span("Page " . ($page + 1) . " of " . $totalpages . " ($total items)", ['style' => 'margin: 0 15px;']);
                if ($page < $totalpages - 1) {
                    $nexturl = clone $baseurl;
                    $nexturl->param('page', $page + 1);
                    echo html_writer::link($nexturl, 'Next »', ['class' => 'btn btn-sm']);
                }
                echo html_writer::end_div();
            }
        }
        break;
}

// Информация об авторе в футере
echo html_writer::start_div('local-deanpromoodle-author-footer', ['style' => 'margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 0.9em;']);
echo html_writer::tag('p', 'Автор: ' . html_writer::link('https://github.com/ValentinK2410', 'ValentinK2410', ['target' => '_blank', 'style' => 'color: #007bff; text-decoration: none;']));
echo html_writer::end_div();

echo $OUTPUT->footer();
