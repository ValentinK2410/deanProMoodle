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
    
    $js = "
    (function() {
        // Ждем загрузки DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addLKButton);
        } else {
            addLKButton();
        }
        
        function addLKButton() {
            // Ищем место для вставки кнопки - обычно это область с кнопками навигации
            // Пытаемся найти существующие кнопки навигации (например, 'Сайт семинарии', 'Деканат')
            var headerNav = document.querySelector('.navbar-nav, .usermenu, .header-actions, .custom-nav-buttons');
            
            // Если не найдено, ищем по тексту существующих кнопок
            if (!headerNav) {
                var buttons = document.querySelectorAll('a, button');
                for (var i = 0; i < buttons.length; i++) {
                    var text = buttons[i].textContent || buttons[i].innerText;
                    if (text.indexOf('Сайт семинарии') !== -1 || text.indexOf('Деканат') !== -1) {
                        headerNav = buttons[i].parentElement;
                        break;
                    }
                }
            }
            
            // Если все еще не найдено, ищем область пользователя
            if (!headerNav) {
                headerNav = document.querySelector('.usermenu, .user-info, [data-region=\"usermenu\"]');
            }
            
            // Если нашли место, добавляем кнопку
            if (headerNav) {
                // Проверяем, не добавлена ли уже кнопка
                if (document.getElementById('lk-button-deanpromoodle')) {
                    return;
                }
                
                var lkButton = document.createElement('a');
                lkButton.id = 'lk-button-deanpromoodle';
                lkButton.href = '" . $lkurlstring . "';
                lkButton.className = 'btn btn-primary';
                lkButton.style.cssText = 'margin-left: 10px; margin-right: 10px; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;';
                lkButton.textContent = 'ЛК';
                lkButton.title = 'Личный кабинет';
                
                // Если админ, добавляем также ссылку на teacher.php
                " . ($isadmin ? "
                var teacherButton = document.createElement('a');
                teacherButton.href = '" . $teacherurlstring . "';
                teacherButton.className = 'btn btn-secondary';
                teacherButton.style.cssText = 'margin-left: 5px; margin-right: 10px; padding: 8px 16px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;';
                teacherButton.textContent = 'Преподаватель';
                teacherButton.title = 'Панель преподавателя';
                
                // Вставляем обе кнопки
                if (headerNav.tagName === 'UL' || headerNav.classList.contains('navbar-nav')) {
                    var li1 = document.createElement('li');
                    li1.className = 'nav-item';
                    li1.appendChild(lkButton);
                    var li2 = document.createElement('li');
                    li2.className = 'nav-item';
                    li2.appendChild(teacherButton);
                    headerNav.appendChild(li1);
                    headerNav.appendChild(li2);
                } else {
                    headerNav.appendChild(lkButton);
                    headerNav.appendChild(teacherButton);
                }
                " : "
                // Вставляем кнопку ЛК
                if (headerNav.tagName === 'UL' || headerNav.classList.contains('navbar-nav')) {
                    var li = document.createElement('li');
                    li.className = 'nav-item';
                    li.appendChild(lkButton);
                    headerNav.appendChild(li);
                } else {
                    headerNav.appendChild(lkButton);
                }
                ") . "
            } else {
                // Если не нашли подходящее место, пытаемся добавить в начало body или в существующий контейнер
                setTimeout(function() {
                    var body = document.body;
                    if (body) {
                        var container = document.createElement('div');
                        container.id = 'lk-button-container-deanpromoodle';
                        container.style.cssText = 'position: fixed; top: 10px; right: 10px; z-index: 9999;';
                        container.appendChild(lkButton);
                        " . ($isadmin ? "
                        var teacherButton = document.createElement('a');
                        teacherButton.href = '" . $teacherurlstring . "';
                        teacherButton.className = 'btn btn-secondary';
                        teacherButton.style.cssText = 'margin-left: 5px; padding: 8px 16px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500;';
                        teacherButton.textContent = 'Преподаватель';
                        teacherButton.title = 'Панель преподавателя';
                        container.appendChild(teacherButton);
                        " : "") . "
                        body.appendChild(container);
                    }
                }, 500);
            }
        }
    })();
    ";
    
    $PAGE->requires->js_init_code($js);
}
