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
 * AJAX endpoint for admin page.
 * Returns teacher courses in JSON format.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define path to Moodle config
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

require_once($configpath);
require_login();

// Получаем параметры ДО проверки доступа
$action = optional_param('action', '', PARAM_ALPHA);
$programid = optional_param('programid', 0, PARAM_INT);

// Для действия getprogramsubjectsforstudent разрешаем доступ студентам
if ($action == 'getprogramsubjectsforstudent') {
    // Проверяем, что пользователь имеет доступ к студенческой странице
    $context = context_system::instance();
    $hasaccess = false;
    
    if (has_capability('local/deanpromoodle:viewstudent', $context)) {
        $hasaccess = true;
    } else {
        global $USER;
        $roles = get_user_roles($context, $USER->id, false);
        foreach ($roles as $role) {
            if ($role->shortname == 'student') {
                $hasaccess = true;
                break;
            }
        }
        
        if (!$hasaccess && !isguestuser()) {
            $hasaccess = true; // Временно разрешаем всем залогиненным пользователям
        }
    }
    
    if (!$hasaccess) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Для этого действия не нужна дальнейшая проверка доступа
    header('Content-Type: application/json');
} else {
    // Для остальных действий проверяем админский доступ
    $context = context_system::instance();
    $hasaccess = false;
    
    if (has_capability('local/deanpromoodle:viewadmin', $context)) {
        $hasaccess = true;
    } else {
        if (has_capability('moodle/site:config', $context)) {
            $hasaccess = true;
        } else {
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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    header('Content-Type: application/json');
} else {
    // Для остальных действий проверяем админский доступ
    $context = context_system::instance();
    $hasaccess = false;
    
    if (has_capability('local/deanpromoodle:viewadmin', $context)) {
        $hasaccess = true;
    } else {
        if (has_capability('moodle/site:config', $context)) {
            $hasaccess = true;
        } else {
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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // Получение остальных параметров для админских действий
    $teacherid = optional_param('teacherid', 0, PARAM_INT);
    $categoryid = optional_param('categoryid', 0, PARAM_INT);
    // Параметры для предметов и программ
    $subjectid = optional_param('subjectid', 0, PARAM_INT);
    $courseid = optional_param('courseid', 0, PARAM_INT);
    $cohortid = optional_param('cohortid', 0, PARAM_INT);
    $search = optional_param('search', '', PARAM_TEXT);
    
    header('Content-Type: application/json');
}

if ($action == 'getteachercourses' && $teacherid > 0) {
    global $DB;
    
    // Получение ID ролей преподавателей
    $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
    
    if (empty($teacherroleids)) {
        echo json_encode(['success' => false, 'error' => 'Teacher roles not found']);
        exit;
    }
    
    // Получаем все контексты курсов, где преподаватель имеет роль
    $placeholders = implode(',', array_fill(0, count($teacherroleids), '?'));
    
    $coursecontextids = $DB->get_fieldset_sql(
        "SELECT DISTINCT ra.contextid
         FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ctx.contextlevel = 50
         AND ra.userid = ?
         AND ra.roleid IN ($placeholders)",
        array_merge([$teacherid], $teacherroleids)
    );
    
    $courses = [];
    if (!empty($coursecontextids)) {
        $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
        $courses = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.fullname, c.shortname, c.startdate, c.enddate,
                    cat.name as categoryname
             FROM {course} c
             JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
             LEFT JOIN {course_categories} cat ON cat.id = c.category
             WHERE ctx.id IN ($contextplaceholders)
             AND c.id > 1
             ORDER BY c.fullname",
            $coursecontextids
        );
    }
    
    // Форматируем данные для JSON
    $formattedcourses = [];
    foreach ($courses as $course) {
        $formattedcourses[] = [
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname,
            'categoryname' => $course->categoryname ?: '-',
            'startdate' => $course->startdate > 0 ? userdate($course->startdate) : '-',
            'enddate' => $course->enddate > 0 ? userdate($course->enddate) : '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $formattedcourses,
        'count' => count($formattedcourses)
    ]);
} elseif ($action == 'getcategorycourses' && $categoryid > 0) {
    // Получение курсов категории (включая дочерние категории)
    global $DB;
    
    // Функция для получения всех дочерних категорий рекурсивно
    $getchildcategories = function($parentid, $allcategories) use (&$getchildcategories) {
        $children = [];
        foreach ($allcategories as $cat) {
            if ($cat->parent == $parentid) {
                $children[] = $cat->id;
                $subchildren = $getchildcategories($cat->id, $allcategories);
                $children = array_merge($children, $subchildren);
            }
        }
        return $children;
    };
    
    // Получаем все категории
    $allcategories = $DB->get_records('course_categories');
    
    // Получаем все дочерние категории
    $childids = $getchildcategories($categoryid, $allcategories);
    $allcategoryids = array_merge([$categoryid], $childids);
    
    // Получаем курсы из всех категорий
    $placeholders = implode(',', array_fill(0, count($allcategoryids), '?'));
    $courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
         FROM {course} c
         WHERE c.category IN ($placeholders)
         AND c.id > 1
         ORDER BY c.fullname",
        $allcategoryids
    );
    
    $formattedcourses = [];
    foreach ($courses as $course) {
        $formattedcourses[] = [
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname ?: '-',
            'startdate' => $course->startdate > 0 ? userdate($course->startdate) : '-',
            'enddate' => $course->enddate > 0 ? userdate($course->enddate) : '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $formattedcourses,
        'count' => count($formattedcourses)
    ]);
} elseif ($action == 'getcategorystudents' && $categoryid > 0) {
    // Получение студентов категории (включая дочерние категории)
    global $DB;
    
    // Функция для получения всех дочерних категорий рекурсивно
    $getchildcategories = function($parentid, $allcategories) use (&$getchildcategories) {
        $children = [];
        foreach ($allcategories as $cat) {
            if ($cat->parent == $parentid) {
                $children[] = $cat->id;
                $subchildren = $getchildcategories($cat->id, $allcategories);
                $children = array_merge($children, $subchildren);
            }
        }
        return $children;
    };
    
    // Получаем все категории
    $allcategories = $DB->get_records('course_categories');
    
    // Получаем все дочерние категории
    $childids = $getchildcategories($categoryid, $allcategories);
    $allcategoryids = array_merge([$categoryid], $childids);
    
    // Получаем все курсы из всех категорий
    $placeholders = implode(',', array_fill(0, count($allcategoryids), '?'));
    $categorycourses = $DB->get_records_select('course', "category IN ($placeholders) AND id > 1", $allcategoryids, '', 'id');
    $courseids = array_keys($categorycourses);
    
    $students = [];
    if (!empty($courseids)) {
        // Получаем контексты курсов
        $courseids_placeholders = implode(',', array_fill(0, count($courseids), '?'));
        $coursecontextids = $DB->get_fieldset_sql(
            "SELECT id FROM {context} WHERE instanceid IN ($courseids_placeholders) AND contextlevel = 50",
            $courseids
        );
        
        if (!empty($coursecontextids)) {
            // Получаем ID роли студента
            $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
            
            if ($studentroleid) {
                $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
                $studentids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT ra.userid
                     FROM {role_assignments} ra
                     WHERE ra.contextid IN ($contextplaceholders)
                     AND ra.roleid = ?",
                    array_merge($coursecontextids, [$studentroleid])
                );
                
                if (!empty($studentids)) {
                    $studentids_placeholders = implode(',', array_fill(0, count($studentids), '?'));
                    $students = $DB->get_records_sql(
                        "SELECT u.id, u.firstname, u.lastname, u.email, u.timecreated, u.lastaccess
                         FROM {user} u
                         WHERE u.id IN ($studentids_placeholders)
                         AND u.deleted = 0
                         ORDER BY u.lastname, u.firstname",
                        $studentids
                    );
                }
            }
        }
    }
    
    $formattedstudents = [];
    foreach ($students as $student) {
        $formattedstudents[] = [
            'id' => $student->id,
            'fullname' => fullname($student),
            'email' => $student->email ?: '-',
            'timecreated' => $student->timecreated > 0 ? userdate($student->timecreated) : '-',
            'lastaccess' => $student->lastaccess > 0 ? userdate($student->lastaccess) : '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'students' => $formattedstudents,
        'count' => count($formattedstudents)
    ]);
} elseif ($action == 'getcategoryteachers' && $categoryid > 0) {
    // Получение преподавателей категории (включая дочерние категории)
    global $DB;
    
    // Функция для получения всех дочерних категорий рекурсивно
    $getchildcategories = function($parentid, $allcategories) use (&$getchildcategories) {
        $children = [];
        foreach ($allcategories as $cat) {
            if ($cat->parent == $parentid) {
                $children[] = $cat->id;
                $subchildren = $getchildcategories($cat->id, $allcategories);
                $children = array_merge($children, $subchildren);
            }
        }
        return $children;
    };
    
    // Получаем все категории
    $allcategories = $DB->get_records('course_categories');
    
    // Получаем все дочерние категории
    $childids = $getchildcategories($categoryid, $allcategories);
    $allcategoryids = array_merge([$categoryid], $childids);
    
    // Получаем все курсы из всех категорий
    $placeholders = implode(',', array_fill(0, count($allcategoryids), '?'));
    $categorycourses = $DB->get_records_select('course', "category IN ($placeholders) AND id > 1", $allcategoryids, '', 'id');
    $courseids = array_keys($categorycourses);
    
    $teachers = [];
    if (!empty($courseids)) {
        // Получаем контексты курсов
        $courseids_placeholders = implode(',', array_fill(0, count($courseids), '?'));
        $coursecontextids = $DB->get_fieldset_sql(
            "SELECT id FROM {context} WHERE instanceid IN ($courseids_placeholders) AND contextlevel = 50",
            $courseids
        );
        
        if (!empty($coursecontextids)) {
            // Получаем ID ролей преподавателей
            $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'manager')");
            
            if (!empty($teacherroleids)) {
                $contextplaceholders = implode(',', array_fill(0, count($coursecontextids), '?'));
                $roleplaceholders = implode(',', array_fill(0, count($teacherroleids), '?'));
                
                // Получаем преподавателей с их ролями
                $teacherdata = $DB->get_records_sql(
                    "SELECT DISTINCT ra.userid, r.shortname as roleshortname, r.name as rolename
                     FROM {role_assignments} ra
                     JOIN {role} r ON r.id = ra.roleid
                     WHERE ra.contextid IN ($contextplaceholders)
                     AND ra.roleid IN ($roleplaceholders)",
                    array_merge($coursecontextids, $teacherroleids)
                );
                
                // Группируем по пользователям и собираем роли
                $teacherusers = [];
                foreach ($teacherdata as $td) {
                    if (!isset($teacherusers[$td->userid])) {
                        $teacherusers[$td->userid] = [
                            'userid' => $td->userid,
                            'roles' => []
                        ];
                    }
                    $teacherusers[$td->userid]['roles'][] = $td->roleshortname;
                }
                
                // Получаем данные пользователей
                if (!empty($teacherusers)) {
                    $teacherids = array_keys($teacherusers);
                    $teacherids_placeholders = implode(',', array_fill(0, count($teacherids), '?'));
                    $userdata = $DB->get_records_sql(
                        "SELECT u.id, u.firstname, u.lastname, u.email, u.timecreated
                         FROM {user} u
                         WHERE u.id IN ($teacherids_placeholders)
                         AND u.deleted = 0",
                        $teacherids
                    );
                    
                    foreach ($userdata as $user) {
                        $roles = $teacherusers[$user->id]['roles'];
                        $rolenames = [];
                        $rolenamesmap = [
                            'teacher' => 'Преподаватель',
                            'editingteacher' => 'Редактирующий преподаватель',
                            'manager' => 'Менеджер'
                        ];
                        foreach ($roles as $r) {
                            $rolenames[] = isset($rolenamesmap[$r]) ? $rolenamesmap[$r] : $r;
                        }
                        
                        $teachers[] = [
                            'id' => $user->id,
                            'fullname' => fullname($user),
                            'email' => $user->email ?: '-',
                            'role' => implode(', ', $rolenames),
                            'timecreated' => $user->timecreated > 0 ? userdate($user->timecreated) : '-'
                        ];
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers,
        'count' => count($teachers)
    ]);
} elseif ($action == 'getcourses') {
    // Получение списка курсов с фильтрацией для модального окна
    global $DB;
    
    $courses = [];
    if (!empty($search) && strlen($search) >= 2) {
        $searchpattern = '%' . $DB->sql_like_escape($search) . '%';
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname
             FROM {course} c
             WHERE c.id > 1
             AND (c.fullname LIKE ? OR c.shortname LIKE ?)
             ORDER BY c.fullname ASC
             LIMIT 50",
            [$searchpattern, $searchpattern]
        );
    }
    
    // Исключаем курсы, уже прикрепленные к предмету, если указан subjectid
    if ($subjectid > 0 && !empty($courses)) {
        $attachedcourseids = $DB->get_fieldset_select(
            'local_deanpromoodle_subject_courses',
            'courseid',
            'subjectid = ?',
            [$subjectid]
        );
        if (!empty($attachedcourseids)) {
            $courses = array_filter($courses, function($course) use ($attachedcourseids) {
                return !in_array($course->id, $attachedcourseids);
            });
        }
    }
    
    $formattedcourses = [];
    foreach ($courses as $course) {
        $formattedcourses[] = [
            'id' => $course->id,
            'fullname' => $course->fullname,
            'shortname' => $course->shortname ?: '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $formattedcourses
    ]);
} elseif ($action == 'attachcoursetosubject' && $subjectid > 0 && $courseid > 0) {
    // Прикрепление курса к предмету
    global $DB;
    
    // Проверяем существование предмета и курса
    $subject = $DB->get_record('local_deanpromoodle_subjects', ['id' => $subjectid]);
    $course = $DB->get_record('course', ['id' => $courseid]);
    
    if (!$subject) {
        echo json_encode(['success' => false, 'error' => 'Предмет не найден']);
        exit;
    }
    
    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'Курс не найден']);
        exit;
    }
    
    // Проверяем, не прикреплен ли уже курс
    $existing = $DB->get_record('local_deanpromoodle_subject_courses', [
        'subjectid' => $subjectid,
        'courseid' => $courseid
    ]);
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Курс уже прикреплен к этому предмету']);
        exit;
    }
    
    // Получаем максимальный порядок для этого предмета
    $maxsortorder = $DB->get_field_sql(
        "SELECT MAX(sortorder) FROM {local_deanpromoodle_subject_courses} WHERE subjectid = ?",
        [$subjectid]
    );
    $newsortorder = ($maxsortorder !== false) ? $maxsortorder + 1 : 0;
    
    // Добавляем связь
    $data = new stdClass();
    $data->subjectid = $subjectid;
    $data->courseid = $courseid;
    $data->sortorder = $newsortorder;
    $data->timecreated = time();
    $data->timemodified = time();
    
    $id = $DB->insert_record('local_deanpromoodle_subject_courses', $data);
    
    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении']);
    }
} elseif ($action == 'detachcoursefromsubject' && $subjectid > 0 && $courseid > 0) {
    // Открепление курса от предмета
    global $DB;
    
    $deleted = $DB->delete_records('local_deanpromoodle_subject_courses', [
        'subjectid' => $subjectid,
        'courseid' => $courseid
    ]);
    
    if ($deleted) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Связь не найдена']);
    }
} elseif ($action == 'getcohorts') {
    // Получение списка когорт (глобальных групп) с фильтрацией
    global $DB;
    
    $cohorts = [];
    if (!empty($search) && strlen($search) >= 2) {
        // Поиск по названию или ID number
        $searchpattern = '%' . $DB->sql_like_escape($search) . '%';
        $cohorts = $DB->get_records_sql(
            "SELECT c.id, c.name, c.idnumber, c.description
             FROM {cohort} c
             WHERE c.name LIKE ? OR c.idnumber LIKE ?
             ORDER BY c.name ASC
             LIMIT 50",
            [$searchpattern, $searchpattern]
        );
    } else {
        // Если поиск пустой, возвращаем все когорты (ограничение 100)
        $cohorts = $DB->get_records_sql(
            "SELECT c.id, c.name, c.idnumber, c.description
             FROM {cohort} c
             ORDER BY c.name ASC
             LIMIT 100",
            []
        );
    }
    
    // Исключаем когорты, уже прикрепленные к программе, если указан programid
    if ($programid > 0 && !empty($cohorts)) {
        $attachedcohortids = $DB->get_fieldset_select(
            'local_deanpromoodle_program_cohorts',
            'cohortid',
            'programid = ?',
            [$programid]
        );
        if (!empty($attachedcohortids)) {
            $cohorts = array_filter($cohorts, function($cohort) use ($attachedcohortids) {
                return !in_array($cohort->id, $attachedcohortids);
            });
        }
    }
    
    $formattedcohorts = [];
    foreach ($cohorts as $cohort) {
        $formattedcohorts[] = [
            'id' => $cohort->id,
            'name' => $cohort->name,
            'idnumber' => $cohort->idnumber ?: '-',
            'description' => $cohort->description ?: '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cohorts' => $formattedcohorts
    ]);
} elseif ($action == 'attachcohorttoprogram' && $programid > 0 && $cohortid > 0) {
    // Прикрепление когорты к программе
    global $DB;
    
    // Проверяем существование программы и когорты
    $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
    $cohort = $DB->get_record('cohort', ['id' => $cohortid]);
    
    if (!$program) {
        echo json_encode(['success' => false, 'error' => 'Программа не найдена']);
        exit;
    }
    
    if (!$cohort) {
        echo json_encode(['success' => false, 'error' => 'Когорта не найдена']);
        exit;
    }
    
    // Проверяем, не прикреплена ли уже когорта
    $existing = $DB->get_record('local_deanpromoodle_program_cohorts', [
        'programid' => $programid,
        'cohortid' => $cohortid
    ]);
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Когорта уже прикреплена к этой программе']);
        exit;
    }
    
    // Добавляем связь
    $data = new stdClass();
    $data->programid = $programid;
    $data->cohortid = $cohortid;
    $data->timecreated = time();
    $data->timemodified = time();
    
    $id = $DB->insert_record('local_deanpromoodle_program_cohorts', $data);
    
    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении']);
    }
} elseif ($action == 'getprogramcohorts' && $programid > 0) {
    // Получение списка прикрепленных когорт к программе
    global $DB;
    
    $cohorts = $DB->get_records_sql(
        "SELECT c.id, c.name, c.idnumber, c.description
         FROM {cohort} c
         JOIN {local_deanpromoodle_program_cohorts} pc ON pc.cohortid = c.id
         WHERE pc.programid = ?
         ORDER BY c.name ASC",
        [$programid]
    );
    
    $formattedcohorts = [];
    foreach ($cohorts as $cohort) {
        $formattedcohorts[] = [
            'id' => $cohort->id,
            'name' => $cohort->name,
            'idnumber' => $cohort->idnumber ?: '-',
            'description' => $cohort->description ?: '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'cohorts' => $formattedcohorts
    ]);
} elseif ($action == 'detachcohortfromprogram' && $programid > 0 && $cohortid > 0) {
    // Открепление когорты от программы
    global $DB;
    
    $deleted = $DB->delete_records('local_deanpromoodle_program_cohorts', [
        'programid' => $programid,
        'cohortid' => $cohortid
    ]);
    
    if ($deleted) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Связь не найдена']);
    }
} elseif ($action == 'getsubjects') {
    // Получение списка предметов для выбора
    global $DB;
    
    $subjects = [];
    if (!empty($search) && strlen($search) >= 2) {
        $searchpattern = '%' . $DB->sql_like_escape($search) . '%';
        $subjects = $DB->get_records_sql(
            "SELECT s.id, s.name, s.code, s.shortdescription
             FROM {local_deanpromoodle_subjects} s
             WHERE s.visible = 1
             AND (s.name LIKE ? OR s.code LIKE ?)
             ORDER BY s.sortorder ASC, s.name ASC
             LIMIT 50",
            [$searchpattern, $searchpattern]
        );
    } else {
        // Если поиск не указан, возвращаем все видимые предметы
        $subjects = $DB->get_records_select(
            'local_deanpromoodle_subjects',
            'visible = 1',
            null,
            'sortorder ASC, name ASC',
            'id, name, code, shortdescription',
            0,
            50
        );
    }
    
    // Исключаем предметы, уже прикрепленные к программе, если указан programid
    // Примечание: при редактировании программы мы не исключаем уже добавленные предметы,
    // так как пользователь может видеть их в модальном окне (они будут помечены как "уже добавлен")
    // Это позволяет видеть полный список предметов для выбора
    
    $formattedsubjects = [];
    foreach ($subjects as $subject) {
        $formattedsubjects[] = [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code ?: '-',
            'shortdescription' => $subject->shortdescription ?: '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'subjects' => $formattedsubjects
    ]);
} elseif ($action == 'getprograms') {
    // Получение списка программ с фильтрацией
    global $DB;
    
    $programs = [];
    if (!empty($search) && strlen($search) >= 2) {
        $searchpattern = '%' . $DB->sql_like_escape($search) . '%';
        $programs = $DB->get_records_sql(
            "SELECT p.id, p.name, p.code, p.description
             FROM {local_deanpromoodle_programs} p
             WHERE p.visible = 1
             AND (p.name LIKE ? OR p.code LIKE ?)
             ORDER BY p.name ASC
             LIMIT 50",
            [$searchpattern, $searchpattern]
        );
    } else {
        // Если поиск не указан, возвращаем все видимые программы
        $programs = $DB->get_records_select(
            'local_deanpromoodle_programs',
            'visible = 1',
            null,
            'name ASC',
            'id, name, code, description',
            0,
            50
        );
    }
    
    $formattedprograms = [];
    foreach ($programs as $program) {
        $formattedprograms[] = [
            'id' => $program->id,
            'name' => $program->name,
            'code' => $program->code ?: '-',
            'description' => $program->description ?: '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'programs' => $formattedprograms
    ]);
} elseif ($action == 'attachsubjecttoprogram' && $subjectid > 0 && $programid > 0) {
    // Прикрепление предмета к программе
    global $DB;
    
    // Проверяем существование предмета и программы
    $subject = $DB->get_record('local_deanpromoodle_subjects', ['id' => $subjectid]);
    $program = $DB->get_record('local_deanpromoodle_programs', ['id' => $programid]);
    
    if (!$subject) {
        echo json_encode(['success' => false, 'error' => 'Предмет не найден']);
        exit;
    }
    
    if (!$program) {
        echo json_encode(['success' => false, 'error' => 'Программа не найдена']);
        exit;
    }
    
    // Проверяем, не прикреплен ли уже предмет
    $existing = $DB->get_record('local_deanpromoodle_program_subjects', [
        'programid' => $programid,
        'subjectid' => $subjectid
    ]);
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'Предмет уже прикреплен к этой программе']);
        exit;
    }
    
    // Получаем максимальный порядок для этой программы
    $maxsortorder = $DB->get_field_sql(
        "SELECT MAX(sortorder) FROM {local_deanpromoodle_program_subjects} WHERE programid = ?",
        [$programid]
    );
    $newsortorder = ($maxsortorder !== false) ? $maxsortorder + 1 : 0;
    
    // Добавляем связь
    $data = new stdClass();
    $data->programid = $programid;
    $data->subjectid = $subjectid;
    $data->sortorder = $newsortorder;
    $data->timecreated = time();
    $data->timemodified = time();
    
    $id = $DB->insert_record('local_deanpromoodle_program_subjects', $data);
    
    if ($id) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении']);
    }
} elseif ($action == 'detachsubjectfromprogram' && $programid > 0 && $subjectid > 0) {
    // Открепление предмета от программы
    global $DB;
    
    $deleted = $DB->delete_records('local_deanpromoodle_program_subjects', [
        'programid' => $programid,
        'subjectid' => $subjectid
    ]);
    
    if ($deleted) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Связь не найдена']);
    }
} elseif ($action == 'deletesubject' && $subjectid > 0) {
    // Удаление предмета и всех его связей
    global $DB;
    
    $transaction = $DB->start_delegated_transaction();
    
    try {
        // Удаляем связи с курсами
        $DB->delete_records('local_deanpromoodle_subject_courses', ['subjectid' => $subjectid]);
        
        // Удаляем связи с программами
        $DB->delete_records('local_deanpromoodle_program_subjects', ['subjectid' => $subjectid]);
        
        // Удаляем сам предмет
        $DB->delete_records('local_deanpromoodle_subjects', ['id' => $subjectid]);
        
        $transaction->allow_commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $transaction->rollback($e);
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()]);
    }
} elseif ($action == 'deleteprogram' && $programid > 0) {
    // Удаление программы и всех её связей
    global $DB;
    
    $transaction = $DB->start_delegated_transaction();
    
    try {
        // Удаляем связи с предметами
        $DB->delete_records('local_deanpromoodle_program_subjects', ['programid' => $programid]);
        
        // Удаляем связи с когортами
        $DB->delete_records('local_deanpromoodle_program_cohorts', ['programid' => $programid]);
        
        // Удаляем саму программу
        $DB->delete_records('local_deanpromoodle_programs', ['id' => $programid]);
        
        $transaction->allow_commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $transaction->rollback($e);
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()]);
    }
} elseif ($action == 'changesubjectorder') {
    // Изменение порядка предметов в программе
    global $DB;
    
    $relationid = optional_param('relation_id', 0, PARAM_INT);
    $siblingrelationid = optional_param('sibling_relation_id', 0, PARAM_INT);
    $direction = optional_param('direction', '', PARAM_ALPHA); // 'up' или 'down'
    
    if ($relationid <= 0 || $siblingrelationid <= 0 || !in_array($direction, ['up', 'down'])) {
        echo json_encode(['success' => false, 'error' => 'Неверные параметры']);
        exit;
    }
    
    // Получаем обе записи
    $relation = $DB->get_record('local_deanpromoodle_program_subjects', ['id' => $relationid]);
    $siblingrelation = $DB->get_record('local_deanpromoodle_program_subjects', ['id' => $siblingrelationid]);
    
    if (!$relation || !$siblingrelation) {
        echo json_encode(['success' => false, 'error' => 'Связь не найдена']);
        exit;
    }
    
    // Проверяем, что обе связи относятся к одной программе
    if ($relation->programid != $siblingrelation->programid) {
        echo json_encode(['success' => false, 'error' => 'Предметы относятся к разным программам']);
        exit;
    }
    
    $transaction = $DB->start_delegated_transaction();
    try {
        // Меняем порядок местами
        $temporder = $relation->sortorder;
        $relation->sortorder = $siblingrelation->sortorder;
        $siblingrelation->sortorder = $temporder;
        
        $relation->timemodified = time();
        $siblingrelation->timemodified = time();
        
        $DB->update_record('local_deanpromoodle_program_subjects', $relation);
        $DB->update_record('local_deanpromoodle_program_subjects', $siblingrelation);
        
        $transaction->allow_commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $transaction->rollback($e);
        echo json_encode(['success' => false, 'error' => 'Ошибка при изменении порядка: ' . $e->getMessage()]);
    }
} elseif ($action == 'deleteinstitution' && $institutionid > 0) {
    // Удаление учебного заведения
    global $DB;
    
    if ($institutionid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Неверный ID учебного заведения']);
        exit;
    }
    
    try {
        $institution = $DB->get_record('local_deanpromoodle_institutions', ['id' => $institutionid]);
        if (!$institution) {
            echo json_encode(['success' => false, 'error' => 'Учебное заведение не найдено']);
            exit;
        }
        
        // Удаляем учебное заведение
        $deleted = $DB->delete_records('local_deanpromoodle_institutions', ['id' => $institutionid]);
        
        if ($deleted) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка при удалении']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
    }
} elseif ($action == 'getprogramsubjectsforstudent' && $programid > 0) {
    // Получение предметов программы для студента с проверкой статуса прохождения
    global $DB, $USER;
    
    require_once($CFG->libdir . '/enrollib.php');
    
    // Получаем предметы программы
    $subjects = $DB->get_records_sql(
        "SELECT s.id, s.name, s.code, s.shortdescription, ps.sortorder
         FROM {local_deanpromoodle_program_subjects} ps
         JOIN {local_deanpromoodle_subjects} s ON s.id = ps.subjectid
         WHERE ps.programid = ?
         ORDER BY ps.sortorder ASC",
        [$programid]
    );
    
    // Получаем все курсы студента
    $mycourses = enrol_get_my_courses(['id']);
    $mycourseids = array_keys($mycourses);
    
    $formattedsubjects = [];
    foreach ($subjects as $subject) {
        // Получаем курсы предмета
        $subjectcourses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname
             FROM {local_deanpromoodle_subject_courses} sc
             JOIN {course} c ON c.id = sc.courseid
             WHERE sc.subjectid = ?
             ORDER BY sc.sortorder ASC, c.fullname ASC",
            [$subject->id]
        );
        
        $courses = [];
        $subjectstarted = false;
        
        foreach ($subjectcourses as $course) {
            $isenrolled = in_array($course->id, $mycourseids);
            if ($isenrolled) {
                $subjectstarted = true; // Если хотя бы один курс начат, предмет считается начатым
            }
            
            $courses[] = [
                'id' => $course->id,
                'name' => $course->fullname,
                'shortname' => $course->shortname,
                'enrolled' => $isenrolled
            ];
        }
        
        $formattedsubjects[] = [
            'id' => $subject->id,
            'name' => $subject->name,
            'code' => $subject->code ?: '',
            'shortdescription' => $subject->shortdescription ?: '',
            'started' => $subjectstarted,
            'courses' => $courses
        ];
    }
    
    echo json_encode([
        'success' => true,
        'subjects' => $formattedsubjects
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action or parameters']);
}
