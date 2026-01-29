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

// Check access
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

// Получение параметров
$action = optional_param('action', '', PARAM_ALPHA);
$teacherid = optional_param('teacherid', 0, PARAM_INT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

header('Content-Type: application/json');

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
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action or parameters']);
}
