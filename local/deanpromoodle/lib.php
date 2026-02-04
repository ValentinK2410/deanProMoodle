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
 * Adds LK button to Moodle header based on user role
 * Called via before_footer hook
 */
function local_deanpromoodle_before_footer() {
    global $PAGE, $USER, $OUTPUT;
    
    // Check if user is logged in
    if (!isloggedin() || isguestuser()) {
        return;
    }
    
    // Determine user role and URL for LK button
    $lkurl = null;
    $isadmin = false;
    $isteacher = false;
    $isstudent = false;
    
    $context = context_system::instance();
    
    // Check if user is admin
    if (has_capability('moodle/site:config', $context) || has_capability('local/deanpromoodle:viewadmin', $context)) {
        $isadmin = true;
        $lkurl = new moodle_url('/local/deanpromoodle/pages/admin.php');
    } else {
        global $DB;
        
        // Check if user is teacher - только роль teacher с id=3 в любом курсе
        $teacherroleid = 3; // ID роли teacher
        $isteacher = false;
        
        // Проверяем роль teacher через прямой SQL запрос к role_assignments
        // Сначала проверяем в системном контексте
        $systemcontextid = $context->id;
        $hasrole = $DB->record_exists('role_assignments', [
            'userid' => $USER->id,
            'roleid' => $teacherroleid,
            'contextid' => $systemcontextid
        ]);
        
        if ($hasrole) {
            $isteacher = true;
        } else {
            // Если не найдено в системном контексте, проверяем во ВСЕХ контекстах курсов
            // Используем прямой SQL запрос для поиска роли teacher в любом контексте курса
            // НЕ добавляем LIMIT, так как Moodle автоматически добавляет его в record_exists_sql
            $hasrole = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments} ra
                 JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = ? 
                 AND ra.roleid = ? 
                 AND ctx.contextlevel = 50",
                [$USER->id, $teacherroleid]
            );
            
            if ($hasrole) {
                $isteacher = true;
            }
        }
        
        // Check if user is student (check in any course context)
        $studentroles = ['student'];
        $roles = get_user_roles($context, $USER->id, false);
        foreach ($roles as $role) {
            if (in_array($role->shortname, $studentroles)) {
                $isstudent = true;
                break;
            }
        }
        // If not found in system context, check if user is enrolled in any course as student
        if (!$isstudent) {
            $courses = enrol_get_all_users_courses($USER->id, true);
            foreach ($courses as $course) {
                $coursecontext = context_course::instance($course->id);
                $courseroles = get_user_roles($coursecontext, $USER->id, false);
                foreach ($courseroles as $role) {
                    if (in_array($role->shortname, $studentroles)) {
                        $isstudent = true;
                        break 2;
                    }
                }
            }
        }
        // If still not found, assume any authenticated user without teacher/admin role is a student
        if (!$isstudent && !$isteacher && !$isadmin) {
            $isstudent = true;
        }
        
        // Determine URL based on role
        // Кнопка "Деканат" (ЛК) показывается только студентам и админам
        // Преподаватели, которые не являются студентами, не видят кнопку "Деканат"
        if ($isadmin) {
            // Админ всегда видит кнопку "Деканат" (ведет на админскую страницу)
            $lkurl = new moodle_url('/local/deanpromoodle/pages/admin.php');
        } elseif ($isstudent && !$isteacher) {
            // Студент (не преподаватель) видит кнопку "Деканат" (ведет на страницу студента)
            $lkurl = new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']);
        } elseif ($isstudent && $isteacher) {
            // Если пользователь и студент и преподаватель, показываем кнопку "Деканат" (ведет на страницу студента)
            $lkurl = new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']);
        }
        // Если пользователь только преподаватель (не студент и не админ) - кнопка "Деканат" не показывается
    }
    
    // Если URL все еще не определен, но пользователь является студентом, устанавливаем URL студента
    if (!$lkurl && $isstudent) {
        $lkurl = new moodle_url('/local/deanpromoodle/pages/student.php', ['tab' => 'courses']);
    }
    
    // Проверяем еще раз роль teacher, если она не была определена ранее
    // Это нужно для случая, когда проверка выполнилась до определения $isteacher
    if (!$isteacher && !$isadmin) {
        global $DB;
        $teacherroleid = 3; // ID роли teacher
        
        // Проверяем во ВСЕХ контекстах курсов через прямой SQL запрос
        // НЕ добавляем LIMIT, так как Moodle автоматически добавляет его в record_exists_sql
        $hasrole = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra
             JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.userid = ? 
             AND ra.roleid = ? 
             AND ctx.contextlevel = 50",
            [$USER->id, $teacherroleid]
        );
        
        if ($hasrole) {
            $isteacher = true;
        }
    }
    
    // Определяем URL для кнопки "Преподаватель" (показывается независимо от кнопки "Деканат")
    $teacherurlstring = '';
    // Кнопка "Преподаватель" показывается для админов и преподавателей
    if ($isadmin || $isteacher) {
        $teacherurl = new moodle_url('/local/deanpromoodle/pages/teacher.php');
        $teacherurlstring = $teacherurl->out(false);
    }
    
    // Если нет ни кнопки "Деканат", ни кнопки "Преподаватель", не добавляем ничего
    if (!$lkurl && !$teacherurlstring) {
        return;
    }
    
    // Add JavaScript to insert buttons into header
    $lkurlstring = $lkurl ? $lkurl->out(false) : '';
    
    // Get button texts from language files
    $lkButtonTextRaw = get_string('lkbutton', 'local_deanpromoodle');
    $lkButtonTitleRaw = get_string('lkbuttontitle', 'local_deanpromoodle');
    $teacherButtonTextRaw = get_string('teacherbutton', 'local_deanpromoodle');
    $teacherButtonTitleRaw = get_string('teacherbuttontitle', 'local_deanpromoodle');
    
    // Encode all values to JSON before using in JavaScript string
    $lkButtonTextJson = json_encode($lkButtonTextRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $lkButtonTitleJson = json_encode($lkButtonTitleRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $teacherButtonTextJson = json_encode($teacherButtonTextRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $teacherButtonTitleJson = json_encode($teacherButtonTitleRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $lkUrlJson = json_encode($lkurlstring, JSON_UNESCAPED_SLASHES);
    $teacherUrlJson = json_encode($teacherurlstring, JSON_UNESCAPED_SLASHES);
    
    $js = "
    (function() {
        // Check if buttons already added
        if (document.getElementById('lk-button-deanpromoodle') || document.getElementById('teacher-button-deanpromoodle') || document.getElementById('telegram-button-deanpromoodle')) {
            return;
        }
        
        function addLKButton() {
            // Check again before adding
            if (document.getElementById('lk-button-deanpromoodle') || document.getElementById('teacher-button-deanpromoodle') || document.getElementById('telegram-button-deanpromoodle')) {
                return;
            }
            
            // Find moodle-sso-buttons-container or create our own container
            var ssoContainer = document.querySelector('.moodle-sso-buttons-container');
            var ourContainer = document.querySelector('.dean-pro-moodle');
            
            // If our container already exists, use it
            if (ourContainer) {
                ssoContainer = ourContainer;
            } else if (!ssoContainer) {
                // Create our own independent container
                ourContainer = document.createElement('div');
                ourContainer.className = 'dean-pro-moodle';
                ourContainer.style.cssText = 'display: inline-flex; gap: 10px; align-items: center; margin-left: 5px; margin-right: 10px;';
                
                // Find insertion point (same logic as moodle-sso-buttons.php)
                var topBar = document.querySelector('.top-bar, .header-top, .top-header, .navbar-top');
                var userMenu = document.querySelector('.usermenu, .user-menu, .dropdown-toggle');
                var navBar = document.querySelector('.navbar-nav, nav.navbar, .navbar');
                var insertionPoint = null;
                
                // Try to find insertion point
                if (topBar) {
                    var iconsContainer = topBar.querySelector('.d-flex, .ml-auto, .navbar-nav');
                    insertionPoint = iconsContainer || topBar;
                } else if (userMenu && userMenu.parentElement) {
                    insertionPoint = userMenu.parentElement;
                } else if (navBar) {
                    insertionPoint = navBar;
                } else {
                    insertionPoint = document.querySelector('header') || document.body;
                }
                
                // Insert our container
                if (insertionPoint) {
                    if (userMenu && userMenu.parentElement === insertionPoint) {
                        insertionPoint.insertBefore(ourContainer, userMenu);
                    } else if (insertionPoint.firstChild) {
                        insertionPoint.insertBefore(ourContainer, insertionPoint.firstChild);
                    } else {
                        insertionPoint.appendChild(ourContainer);
                    }
                }
                
                ssoContainer = ourContainer;
            }
            
            if (!ssoContainer) {
                return;
            }
            
            " . (!empty($lkurlstring) ? "
            // Create LK (Деканат) button
            var lkButton = document.createElement('a');
            lkButton.id = 'lk-button-deanpromoodle';
            lkButton.href = " . $lkUrlJson . ";
            lkButton.className = 'deanpromoodle-button deanpromoodle-button-lk';
            lkButton.style.cssText = 'display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; cursor: pointer; border: medium; white-space: nowrap; color: white; margin-left: 10px; margin-right: 10px; background-color: rgb(0, 123, 255);';
            lkButton.textContent = " . $lkButtonTextJson . ";
            lkButton.title = " . $lkButtonTitleJson . ";
            
            // Add LK button to container
            ssoContainer.appendChild(lkButton);
            " : "") . "
            
            " . (!empty($teacherurlstring) ? "
            // Create Teacher button for admins and teachers
            var teacherButton = document.createElement('a');
            teacherButton.id = 'teacher-button-deanpromoodle';
            teacherButton.href = " . $teacherUrlJson . ";
            teacherButton.className = 'deanpromoodle-button deanpromoodle-button-teacher';
            teacherButton.style.cssText = 'display: inline-block; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; cursor: pointer; border: medium; white-space: nowrap; color: white; margin-left: 5px; margin-right: 10px; background-color: rgb(108, 117, 125);';
            teacherButton.textContent = " . $teacherButtonTextJson . ";
            teacherButton.title = " . $teacherButtonTitleJson . ";
            
            // Add Teacher button to container
            ssoContainer.appendChild(teacherButton);
            " : "") . "
            
            // Create Telegram button (always visible)
            var telegramButton = document.createElement('a');
            telegramButton.id = 'telegram-button-deanpromoodle';
            telegramButton.href = 'https://t.me/+I9rjz2wfEpI4NjIy';
            telegramButton.target = '_blank';
            telegramButton.className = 'deanpromoodle-button deanpromoodle-button-telegram';
            telegramButton.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; transition: 0.3s; cursor: pointer; border: medium; white-space: nowrap; color: white; margin-left: 5px; margin-right: 10px; background-color: rgb(37, 150, 190);';
            telegramButton.title = 'Техподдержка в Telegram';
            
            // Add Telegram icon (SVG)
            var telegramIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            telegramIcon.setAttribute('width', '18');
            telegramIcon.setAttribute('height', '18');
            telegramIcon.setAttribute('viewBox', '0 0 24 24');
            telegramIcon.setAttribute('fill', 'currentColor');
            telegramIcon.style.cssText = 'margin-right: 6px;';
            var telegramPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            telegramPath.setAttribute('d', 'M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.174 1.857-.923 6.654-1.304 8.84-.152.856-.432 1.142-.709 1.17-.598.055-1.052-.395-1.631-.774-.906-.68-1.42-1.103-2.302-1.767-1.016-.83-.357-1.287.221-2.031.152-.193 2.776-2.555 2.828-2.771.006-.027.012-.128-.047-.18-.059-.052-.144-.034-.207-.02-.089.018-1.5.955-4.234 2.803-.401.27-.764.401-1.089.401-.357-.006-1.043-.201-1.552-.367-.625-.204-1.121-.312-1.078-.658.021-.173.321-.348.884-.533 3.47-1.523 5.79-2.527 6.96-3.03 3.33-1.425 4.014-1.673 4.463-1.683.099-.002.321.024.465.146.118.099.151.232.167.326.016.094.036.309.02.477z');
            telegramIcon.appendChild(telegramPath);
            telegramButton.appendChild(telegramIcon);
            
            // Add text
            var telegramText = document.createTextNode('Техподдержка');
            telegramButton.appendChild(telegramText);
            
            // Add Telegram button to container
            ssoContainer.appendChild(telegramButton);
        }
        
        // Try to add button with multiple attempts
        function tryAddButton(attempt) {
            attempt = attempt || 0;
            if (attempt > 5) return; // Maximum 5 attempts
            
            // If buttons already added, do nothing
            if (document.getElementById('lk-button-deanpromoodle') || document.getElementById('teacher-button-deanpromoodle') || document.getElementById('telegram-button-deanpromoodle')) {
                return;
            }
            
            // Check if moodle-sso-buttons-container or our container exists
            var ssoContainer = document.querySelector('.moodle-sso-buttons-container');
            var ourContainer = document.querySelector('.dean-pro-moodle');
            
            // If neither container found and we haven't tried enough times, wait and retry
            if (!ssoContainer && !ourContainer && attempt < 3) {
                setTimeout(function() { tryAddButton(attempt + 1); }, 500);
                return;
            }
            
            // If at least one container found or we can create our own, add buttons
            if (ssoContainer || ourContainer || attempt >= 2) {
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
