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
 * Library functions for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation to add plugin pages.
 *
 * @param global_navigation $navigation The navigation object
 */
function local_deanpromoodle_extend_navigation(global_navigation $navigation) {
    global $PAGE, $USER;

    // Check if user is logged in
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Get the main navigation node
    $mainnode = $navigation->find('home', global_navigation::TYPE_ROOTNODE);
    
    if (!$mainnode) {
        return;
    }

    // Add Student Page if user has capability
    if (has_capability('local/deanpromoodle:viewstudent', context_system::instance())) {
        $studenturl = new moodle_url('/local/deanpromoodle/pages/student.php');
        $mainnode->add(
            get_string('studentpage', 'local_deanpromoodle'),
            $studenturl,
            navigation_node::TYPE_CUSTOM,
            null,
            'deanpromoodle_student',
            new pix_icon('i/user', '')
        );
    }

    // Add Teacher Page if user has capability
    if (has_capability('local/deanpromoodle:viewteacher', context_system::instance())) {
        $teacherurl = new moodle_url('/local/deanpromoodle/pages/teacher.php');
        $mainnode->add(
            get_string('teacherpage', 'local_deanpromoodle'),
            $teacherurl,
            navigation_node::TYPE_CUSTOM,
            null,
            'deanpromoodle_teacher',
            new pix_icon('i/user', '')
        );
    }

    // Add Admin Page if user has capability
    if (has_capability('local/deanpromoodle:viewadmin', context_system::instance())) {
        $adminurl = new moodle_url('/local/deanpromoodle/pages/admin.php');
        $mainnode->add(
            get_string('adminpage', 'local_deanpromoodle'),
            $adminurl,
            navigation_node::TYPE_CUSTOM,
            null,
            'deanpromoodle_admin',
            new pix_icon('i/settings', '')
        );
    }
}

/**
 * Get URL for a specific page.
 *
 * @param string $pagename Name of the page (student, teacher, admin)
 * @return moodle_url|null URL object or null if page doesn't exist
 */
function local_deanpromoodle_get_url($pagename) {
    $allowedpages = ['student', 'teacher', 'admin'];
    
    if (!in_array($pagename, $allowedpages)) {
        return null;
    }

    return new moodle_url('/local/deanpromoodle/pages/' . $pagename . '.php');
}

/**
 * Check if user has access to a specific page.
 *
 * @param string $pagename Name of the page (student, teacher, admin)
 * @return bool True if user has access, false otherwise
 */
function local_deanpromoodle_has_access($pagename) {
    $capabilitymap = [
        'student' => 'local/deanpromoodle:viewstudent',
        'teacher' => 'local/deanpromoodle:viewteacher',
        'admin' => 'local/deanpromoodle:viewadmin',
    ];

    if (!isset($capabilitymap[$pagename])) {
        return false;
    }

    return has_capability($capabilitymap[$pagename], context_system::instance());
}

/**
 * Добавляет кнопку "ЛК" в хедер Moodle в зависимости от роли пользователя
 * Вызывается через хук before_footer
 */
function local_deanpromoodle_before_footer() {
    global $PAGE, $USER, $OUTPUT;
    
    // Проверяем, что пользователь залогинен
    if (!isloggedin() || isguestuser()) {
        return;
    }
    
    // Определяем роль пользователя и URL для кнопки "ЛК"
    $lkurl = null;
    $isadmin = false;
    $isteacher = false;
    $isstudent = false;
    
    $context = context_system::instance();
    
    // Проверяем, является ли пользователь админом
    if (has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context)) {
        $isadmin = true;
        $lkurl = new moodle_url('/local/deanpromoodle/pages/admin.php');
    } else {
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
        
        // Определяем URL в зависимости от роли
        if ($isteacher && !$isadmin) {
            $lkurl = new moodle_url('/local/deanpromoodle/pages/teacher.php');
        } elseif ($isstudent && !$isteacher && !$isadmin) {
            $lkurl = new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']);
        }
    }
    
    // Если URL не определен, не добавляем кнопку
    if (!$lkurl) {
        return;
    }
    
    // Добавляем JavaScript для вставки кнопки "ЛК" в хедер
    $lkurlstring = $lkurl->out(false);
    $teacherurlstring = '';
    if ($isadmin) {
        $teacherurl = new moodle_url('/local/deanpromoodle/pages/teacher.php');
        $teacherurlstring = $teacherurl->out(false);
    }
    
    // Prepare button texts
    $lkButtonTextRaw = 'ЛК';
    $lkButtonTitleRaw = 'Личный кабинет';
    $teacherButtonTextRaw = 'Преподаватель';
    $teacherButtonTitleRaw = 'Панель преподавателя';
    
    $js = "
    (function() {
        // Check if button already added
        if (document.getElementById('lk-button-deanpromoodle')) {
            return;
        }
        
        function addLKButton() {
            // Check again before adding
            if (document.getElementById('lk-button-deanpromoodle')) {
                return;
            }
            
            // Find moodle-sso-buttons-container
            var ssoContainer = document.querySelector('.moodle-sso-buttons-container');
            if (!ssoContainer) {
                return;
            }
            
            // Create LK button
            var lkButton = document.createElement('a');
            lkButton.id = 'lk-button-deanpromoodle';
            lkButton.href = " . json_encode($lkurlstring, JSON_UNESCAPED_SLASHES) . ";
            lkButton.className = 'deanpromoodle-button deanpromoodle-button-lk';
            lkButton.style.cssText = 'display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; cursor: pointer; border: medium; white-space: nowrap; color: white; margin-left: 10px; margin-right: 10px; background-color: rgb(0, 123, 255);';
            lkButton.textContent = " . json_encode($lkButtonTextRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";
            lkButton.title = " . json_encode($lkButtonTitleRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";
            
            // Add LK button to container
            ssoContainer.appendChild(lkButton);
            
            " . ($isadmin ? "
            // Create Teacher button for admins
            var teacherButton = document.createElement('a');
            teacherButton.id = 'teacher-button-deanpromoodle';
            teacherButton.href = " . json_encode($teacherurlstring, JSON_UNESCAPED_SLASHES) . ";
            teacherButton.className = 'deanpromoodle-button deanpromoodle-button-teacher';
            teacherButton.style.cssText = 'display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; cursor: pointer; border: medium; white-space: nowrap; color: white; margin-left: 5px; margin-right: 10px; background-color: rgb(108, 117, 125);';
            teacherButton.textContent = " . json_encode($teacherButtonTextRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";
            teacherButton.title = " . json_encode($teacherButtonTitleRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";
            
            // Add Teacher button to container
            ssoContainer.appendChild(teacherButton);
            " : "") . "
        }
        
        // Try to add button with multiple attempts
        function tryAddButton(attempt) {
            attempt = attempt || 0;
            if (attempt > 5) return; // Maximum 5 attempts
            
            // If button already added, do nothing
            if (document.getElementById('lk-button-deanpromoodle')) {
                return;
            }
            
            // Check if moodle-sso-buttons-container exists
            var ssoContainer = document.querySelector('.moodle-sso-buttons-container');
            if (!ssoContainer && attempt < 3) {
                // If container not found, wait and try again
                setTimeout(function() { tryAddButton(attempt + 1); }, 500);
                return;
            }
            
            // If container found, add buttons
            if (ssoContainer) {
                addLKButton();
            }
        }
        
        // Try to add immediately
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() { tryAddButton(0); }, 100);
            });
        } else {
            setTimeout(function() { tryAddButton(0); }, 100);
        }
        
        // Also try to add after delays for dynamic loading
        setTimeout(function() { tryAddButton(0); }, 500);
        setTimeout(function() { tryAddButton(0); }, 1000);
        setTimeout(function() { tryAddButton(0); }, 2000);
    })();
    ";
    
    $PAGE->requires->js_init_code($js);
}
