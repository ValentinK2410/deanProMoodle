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
$PAGE->set_title(get_string('teacherpagetitle', 'local_deanpromoodle'));
$PAGE->set_heading(get_string('teacherpagetitle', 'local_deanpromoodle'));
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

// Вывод страницы
echo $OUTPUT->header();
// Заголовок уже выводится через set_heading(), не нужно дублировать

// Вкладки
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
    $assignmentsstr);
$tabs[] = new tabobject('quizzes', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'quizzes', 'courseid' => $courseid]),
    $quizzesstr);
$tabs[] = new tabobject('forums', 
    new moodle_url('/local/deanpromoodle/pages/teacher.php', ['tab' => 'forums', 'courseid' => $courseid]),
    $forumsstr);

echo $OUTPUT->tabtree($tabs, $tab);

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
    echo html_writer::label('Курс: ', 'courseid');
    echo html_writer::select($courseoptions, 'courseid', $courseid, false, ['class' => 'form-control', 'style' => 'display: inline-block; margin-left: 5px;']);
    $filterstr = get_string('filterbycourse', 'local_deanpromoodle');
    if (strpos($filterstr, '[[') !== false) {
        $filterstr = 'Фильтр'; // Резервное значение
    }
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => $filterstr, 'class' => 'btn btn-primary', 'style' => 'margin-left: 10px;']);
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
                    $ungradedassignments[] = (object)[
                        'id' => $submission->id,
                        'assignmentid' => $assignment->id,
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
                $gradeurl = new moodle_url('/mod/assign/view.php', ['id' => $item->assignmentid, 'action' => 'grading', 'userid' => $item->userid]);
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
        // Получение несданных тестов (только экзамены)
        $failedquizzes = [];
        foreach ($teachercourses as $course) {
            $quizzes = get_all_instances_in_course('quiz', $course, false);
            
            foreach ($quizzes as $quiz) {
                // Проверка, является ли это экзаменом (может потребоваться корректировка логики)
                // Пока получаем все несданные попытки
                $attempts = $DB->get_records_sql(
                    "SELECT qa.*, u.firstname, u.lastname, u.email, u.id as userid, q.name as quizname
                     FROM {quiz_attempts} qa
                     JOIN {user} u ON u.id = qa.userid
                     JOIN {quiz} q ON q.id = qa.quiz
                     WHERE qa.quiz = ? AND qa.state = 'finished' AND qa.sumgrades < q.sumgrades
                     ORDER BY qa.timemodified DESC",
                    [$quiz->id]
                );
                
                foreach ($attempts as $attempt) {
                    $failedquizzes[] = (object)[
                        'id' => $attempt->id,
                        'quizid' => $quiz->id,
                        'quizname' => $quiz->name,
                        'courseid' => $course->id,
                        'coursename' => $course->fullname,
                        'userid' => $attempt->userid,
                        'studentname' => fullname($attempt),
                        'email' => $attempt->email,
                        'grade' => $attempt->sumgrades . ' / ' . $quiz->sumgrades,
                        'attempted' => userdate($attempt->timemodified),
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
            echo html_writer::div(get_string('noquizzesfound', 'local_deanpromoodle'), 'alert alert-info');
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
                echo html_writer::start_tag('tr');
                echo html_writer::tag('td', htmlspecialchars($item->coursename));
                echo html_writer::tag('td', htmlspecialchars($item->quizname));
                echo html_writer::tag('td', htmlspecialchars($item->studentname));
                echo html_writer::tag('td', $item->grade);
                echo html_writer::tag('td', $item->attempted);
                $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $item->id]);
                $reviewstr = 'Просмотр';
                $actions = html_writer::link($reviewurl, $reviewstr, ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']);
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
        // Получение сообщений форумов без ответов преподавателя
        $unrepliedposts = [];
        foreach ($teachercourses as $course) {
            $forums = get_all_instances_in_course('forum', $course, false);
            
            foreach ($forums as $forum) {
                $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id);
                $forumcontext = context_module::instance($cm->id);
                
                // Получение ролей преподавателей
                $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
                if (empty($teacherroleids)) {
                    continue;
                }
                
                // Получение ID пользователей с ролями преподавателей в этом контексте курса
                $coursecontext = context_course::instance($course->id);
                $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
                $teacheruserids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT ra.userid
                     FROM {role_assignments} ra
                     WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)",
                    array_merge([$coursecontext->id], $teacherroleids)
                );
                
                // Если нет преподавателей в контексте курса, проверяем системный контекст
                if (empty($teacheruserids)) {
                    $systemcontext = context_system::instance();
                    $teacheruserids = $DB->get_fieldset_sql(
                        "SELECT DISTINCT ra.userid
                         FROM {role_assignments} ra
                         WHERE ra.contextid = ? AND ra.roleid IN ($placeholders)",
                        array_merge([$systemcontext->id], $teacherroleids)
                    );
                }
                
                if (empty($teacheruserids)) {
                    continue;
                }
                
                // Получение ID пользователей с ролью студента в этом контексте курса
                $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
                if (!$studentroleid) {
                    continue;
                }
                
                $studentuserids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT ra.userid
                     FROM {role_assignments} ra
                     WHERE ra.contextid = ? AND ra.roleid = ?",
                    [$coursecontext->id, $studentroleid]
                );
                
                if (empty($studentuserids)) {
                    continue;
                }
                
                $teacherplaceholders = implode(',', array_fill(0, count($teacheruserids), '?'));
                $studentplaceholders = implode(',', array_fill(0, count($studentuserids), '?'));
                
                // Получение сообщений студентов, на которые не ответили преподаватели
                // Проверяем по времени создания: если после сообщения студента нет ответа от преподавателя
                $posts = $DB->get_records_sql(
                    "SELECT p.*, u.firstname, u.lastname, u.email, u.id as userid, d.name as discussionname
                     FROM {forum_posts} p
                     JOIN {user} u ON u.id = p.userid
                     JOIN {forum_discussions} d ON d.id = p.discussion
                     WHERE d.forum = ? 
                     AND p.userid IN ($studentplaceholders)
                     AND NOT EXISTS (
                         SELECT 1 FROM {forum_posts} p2
                         WHERE p2.discussion = p.discussion 
                         AND p2.created > p.created
                         AND p2.userid IN ($teacherplaceholders)
                     )
                     ORDER BY p.created DESC",
                    array_merge([$forum->id], $studentuserids, $teacheruserids)
                );
                
                foreach ($posts as $post) {
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
                        'forumid' => $forum->id,
                        'forumname' => $forum->name,
                        'discussionid' => $post->discussion,
                        'discussionname' => $post->discussionname,
                        'courseid' => $course->id,
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
            }
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

echo $OUTPUT->footer();
