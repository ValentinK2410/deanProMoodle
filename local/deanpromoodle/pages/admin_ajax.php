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
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action or teacher ID']);
}
