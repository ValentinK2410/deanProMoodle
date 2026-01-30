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
        // Проверяем, не добавлена ли уже кнопка
        if (document.getElementById('lk-button-deanpromoodle')) {
            return;
        }
        
        function addLKButton() {
            // Проверяем еще раз перед добавлением
            if (document.getElementById('lk-button-deanpromoodle')) {
                return;
            }
            
            // Ищем существующие кнопки для копирования стилей
            var existingButton = null;
            var allButtons = document.querySelectorAll('a, button');
            for (var b = 0; b < allButtons.length; b++) {
                var btnText = (allButtons[b].textContent || allButtons[b].innerText || '').trim();
                // Ищем именно кнопки 'Сайт семинарии' или 'Деканат' для точного совпадения
                if (btnText.indexOf('Сайт семинарии') !== -1 || btnText.indexOf('Деканат') !== -1) {
                    existingButton = allButtons[b];
                    break;
                }
            }
            
            // Копируем все важные стили с существующей кнопки
            var buttonStyles = '';
            if (existingButton && window.getComputedStyle) {
                var computed = window.getComputedStyle(existingButton);
                // Копируем все размерные стили
                var paddingTop = computed.paddingTop || '6px';
                var paddingRight = computed.paddingRight || '12px';
                var paddingBottom = computed.paddingBottom || '6px';
                var paddingLeft = computed.paddingLeft || '12px';
                var height = computed.height || 'auto';
                var lineHeight = computed.lineHeight || '1.4';
                var fontSize = computed.fontSize || '14px';
                var boxSizing = computed.boxSizing || 'border-box';
                
                buttonStyles = 'padding: ' + paddingTop + ' ' + paddingRight + ' ' + paddingBottom + ' ' + paddingLeft + '; ' +
                              'height: ' + height + '; ' +
                              'line-height: ' + lineHeight + '; ' +
                              'font-size: ' + fontSize + '; ' +
                              'box-sizing: ' + boxSizing + '; ' +
                              'vertical-align: middle; ' +
                              'display: inline-block;';
            } else {
                // Стандартные стили, если не удалось найти существующую кнопку
                buttonStyles = 'padding: 6px 12px; height: 32px; line-height: 20px; font-size: 14px; box-sizing: border-box; vertical-align: middle; display: inline-block;';
            }
            
            var lkButton = document.createElement('a');
            lkButton.id = 'lk-button-deanpromoodle';
            lkButton.href = '" . $lkurlstring . "';
            lkButton.className = 'btn btn-primary';
            lkButton.style.cssText = 'margin-left: 10px; margin-right: 10px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; border: none; ' + buttonStyles;
            lkButton.textContent = 'ЛК';
            lkButton.title = 'Личный кабинет';
            
            var teacherButton = null;
            " . ($isadmin ? "
            teacherButton = document.createElement('a');
            teacherButton.href = '" . $teacherurlstring . "';
            teacherButton.className = 'btn btn-secondary';
            teacherButton.style.cssText = 'margin-left: 5px; margin-right: 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; border: none; ' + buttonStyles;
            teacherButton.textContent = 'Преподаватель';
            teacherButton.title = 'Панель преподавателя';
            " : "") . "
            
            // Стратегия 1: Ищем по тексту существующих кнопок ('Сайт семинарии', 'Деканат')
            var found = false;
            var allLinks = document.querySelectorAll('a, button');
            for (var i = 0; i < allLinks.length; i++) {
                var text = (allLinks[i].textContent || allLinks[i].innerText || '').trim();
                if (text.indexOf('Сайт семинарии') !== -1 || text.indexOf('Деканат') !== -1) {
                    var parent = allLinks[i].parentElement;
                    // Ищем контейнер с кнопками
                    while (parent && parent !== document.body) {
                        if (parent.classList && (parent.classList.contains('navbar-nav') || parent.classList.contains('nav') || parent.tagName === 'NAV' || parent.tagName === 'HEADER')) {
                            if (parent.tagName === 'UL' || parent.classList.contains('navbar-nav')) {
                                var li1 = document.createElement('li');
                                li1.className = 'nav-item';
                                li1.appendChild(lkButton);
                                parent.appendChild(li1);
                                if (teacherButton) {
                                    var li2 = document.createElement('li');
                                    li2.className = 'nav-item';
                                    li2.appendChild(teacherButton);
                                    parent.appendChild(li2);
                                }
                            } else {
                                parent.appendChild(lkButton);
                                if (teacherButton) parent.appendChild(teacherButton);
                            }
                            found = true;
                            break;
                        }
                        parent = parent.parentElement;
                    }
                    if (found) break;
                    
                    // Если не нашли контейнер, добавляем рядом с найденной кнопкой
                    if (!found) {
                        var nextSibling = allLinks[i].nextSibling;
                        if (nextSibling) {
                            allLinks[i].parentElement.insertBefore(lkButton, nextSibling);
                            if (teacherButton) allLinks[i].parentElement.insertBefore(teacherButton, nextSibling);
                        } else {
                            allLinks[i].parentElement.appendChild(lkButton);
                            if (teacherButton) allLinks[i].parentElement.appendChild(teacherButton);
                        }
                        found = true;
                        break;
                    }
                }
            }
            
            // Стратегия 2: Ищем область пользователя или навигации
            if (!found) {
                var selectors = [
                    '.usermenu',
                    '.user-info',
                    '[data-region=\"usermenu\"]',
                    '.navbar-nav',
                    '.header-actions',
                    '.custom-nav-buttons',
                    'header nav',
                    '.navbar .container'
                ];
                
                for (var s = 0; s < selectors.length; s++) {
                    var element = document.querySelector(selectors[s]);
                    if (element) {
                        if (element.tagName === 'UL' || element.classList.contains('navbar-nav')) {
                            var li1 = document.createElement('li');
                            li1.className = 'nav-item';
                            li1.appendChild(lkButton);
                            element.appendChild(li1);
                            if (teacherButton) {
                                var li2 = document.createElement('li');
                                li2.className = 'nav-item';
                                li2.appendChild(teacherButton);
                                element.appendChild(li2);
                            }
                        } else {
                            element.appendChild(lkButton);
                            if (teacherButton) element.appendChild(teacherButton);
                        }
                        found = true;
                        break;
                    }
                }
            }
            
            // Стратегия 3: Добавляем в фиксированное положение в правом верхнем углу
            if (!found) {
                var container = document.createElement('div');
                container.id = 'lk-button-container-deanpromoodle';
                container.style.cssText = 'position: fixed; top: 70px; right: 20px; z-index: 9999; background: rgba(255,255,255,0.95); padding: 10px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);';
                container.appendChild(lkButton);
                if (teacherButton) {
                    container.appendChild(document.createTextNode(' '));
                    container.appendChild(teacherButton);
                }
                document.body.appendChild(container);
            }
        }
        
        // Пытаемся добавить сразу
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addLKButton);
        } else {
            addLKButton();
        }
        
        // Также пытаемся добавить после небольшой задержки на случай динамической загрузки
        setTimeout(addLKButton, 1000);
        setTimeout(addLKButton, 2000);
    })();
    ";
    
    $PAGE->requires->js_init_code($js);
}
