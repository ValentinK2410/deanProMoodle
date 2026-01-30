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
            teacherButton.id = 'teacher-button-deanpromoodle';
            teacherButton.href = '" . $teacherurlstring . "';
            teacherButton.className = 'btn btn-secondary';
            teacherButton.style.cssText = 'margin-left: 5px; margin-right: 10px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; border: none; ' + buttonStyles;
            teacherButton.textContent = 'Преподаватель';
            teacherButton.title = 'Панель преподавателя';
            " : "") . "
            
            // Стратегия 1: Ищем контейнер moodle-sso-buttons-container и добавляем кнопки сразу после него
            var found = false;
            var ssoContainer = document.querySelector('.moodle-sso-buttons-container');
            if (ssoContainer) {
                // Копируем стили с кнопок внутри контейнера
                var ssoButtons = ssoContainer.querySelectorAll('a.sso-button');
                if (ssoButtons.length > 0) {
                    var firstButton = ssoButtons[0];
                    var computed = window.getComputedStyle(firstButton);
                    // Применяем стили к нашим кнопкам
                    lkButton.style.paddingTop = computed.paddingTop;
                    lkButton.style.paddingRight = computed.paddingRight;
                    lkButton.style.paddingBottom = computed.paddingBottom;
                    lkButton.style.paddingLeft = computed.paddingLeft;
                    lkButton.style.height = computed.height;
                    lkButton.style.minHeight = computed.minHeight;
                    lkButton.style.maxHeight = computed.maxHeight;
                    lkButton.style.lineHeight = computed.lineHeight;
                    lkButton.style.fontSize = computed.fontSize;
                    lkButton.style.boxSizing = computed.boxSizing;
                    lkButton.style.display = computed.display;
                    lkButton.style.verticalAlign = computed.verticalAlign;
                    lkButton.style.marginLeft = '10px';
                    lkButton.style.marginRight = '10px';
                    
                    if (teacherButton) {
                        teacherButton.style.paddingTop = computed.paddingTop;
                        teacherButton.style.paddingRight = computed.paddingRight;
                        teacherButton.style.paddingBottom = computed.paddingBottom;
                        teacherButton.style.paddingLeft = computed.paddingLeft;
                        teacherButton.style.height = computed.height;
                        teacherButton.style.minHeight = computed.minHeight;
                        teacherButton.style.maxHeight = computed.maxHeight;
                        teacherButton.style.lineHeight = computed.lineHeight;
                        teacherButton.style.fontSize = computed.fontSize;
                        teacherButton.style.boxSizing = computed.boxSizing;
                        teacherButton.style.display = computed.display;
                        teacherButton.style.verticalAlign = computed.verticalAlign;
                        teacherButton.style.marginLeft = '5px';
                        teacherButton.style.marginRight = '10px';
                    }
                }
                
                // Добавляем кнопки сразу после контейнера
                if (ssoContainer.nextSibling) {
                    ssoContainer.parentElement.insertBefore(lkButton, ssoContainer.nextSibling);
                    if (teacherButton) {
                        ssoContainer.parentElement.insertBefore(teacherButton, ssoContainer.nextSibling);
                    }
                } else {
                    ssoContainer.parentElement.appendChild(lkButton);
                    if (teacherButton) {
                        ssoContainer.parentElement.appendChild(teacherButton);
                    }
                }
                found = true;
            }
            
            // Стратегия 2: Если контейнер не найден, ищем по тексту существующих кнопок
            if (!found) {
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
        
        // Функция для получения точных стилей существующей кнопки
        function getButtonStyles() {
            var existingButton = null;
            var allButtons = document.querySelectorAll('a, button');
            for (var b = 0; b < allButtons.length; b++) {
                var btnText = (allButtons[b].textContent || allButtons[b].innerText || '').trim();
                // Ищем именно кнопки 'Сайт семинарии' или 'Деканат'
                if (btnText === 'Сайт семинарии' || btnText === 'Деканат') {
                    existingButton = allButtons[b];
                    break;
                }
            }
            
            if (existingButton && window.getComputedStyle) {
                var computed = window.getComputedStyle(existingButton);
                return {
                    paddingTop: computed.paddingTop,
                    paddingRight: computed.paddingRight,
                    paddingBottom: computed.paddingBottom,
                    paddingLeft: computed.paddingLeft,
                    height: computed.height,
                    minHeight: computed.minHeight,
                    maxHeight: computed.maxHeight,
                    lineHeight: computed.lineHeight,
                    fontSize: computed.fontSize,
                    boxSizing: computed.boxSizing,
                    display: computed.display,
                    verticalAlign: 'middle'
                };
            }
            return null;
        }
        
        // Пытаемся добавить кнопку с несколькими попытками
        function tryAddButton(attempt) {
            attempt = attempt || 0;
            if (attempt > 5) return; // Максимум 5 попыток
            
            var styles = getButtonStyles();
            if (!styles && attempt < 3) {
                // Если стили не найдены, ждем и пробуем снова
                setTimeout(function() { tryAddButton(attempt + 1); }, 500);
                return;
            }
            
            // Если кнопка уже добавлена, обновляем ее стили
            var existingLK = document.getElementById('lk-button-deanpromoodle');
            if (existingLK && styles) {
                existingLK.style.paddingTop = styles.paddingTop;
                existingLK.style.paddingRight = styles.paddingRight;
                existingLK.style.paddingBottom = styles.paddingBottom;
                existingLK.style.paddingLeft = styles.paddingLeft;
                existingLK.style.height = styles.height;
                existingLK.style.minHeight = styles.minHeight;
                existingLK.style.maxHeight = styles.maxHeight;
                existingLK.style.lineHeight = styles.lineHeight;
                existingLK.style.fontSize = styles.fontSize;
                existingLK.style.boxSizing = styles.boxSizing;
                existingLK.style.display = styles.display;
                existingLK.style.verticalAlign = styles.verticalAlign;
                
                if (teacherButton && document.getElementById('teacher-button-deanpromoodle')) {
                    var existingTeacher = document.getElementById('teacher-button-deanpromoodle');
                    existingTeacher.style.paddingTop = styles.paddingTop;
                    existingTeacher.style.paddingRight = styles.paddingRight;
                    existingTeacher.style.paddingBottom = styles.paddingBottom;
                    existingTeacher.style.paddingLeft = styles.paddingLeft;
                    existingTeacher.style.height = styles.height;
                    existingTeacher.style.minHeight = styles.minHeight;
                    existingTeacher.style.maxHeight = styles.maxHeight;
                    existingTeacher.style.lineHeight = styles.lineHeight;
                    existingTeacher.style.fontSize = styles.fontSize;
                    existingTeacher.style.boxSizing = styles.boxSizing;
                    existingTeacher.style.display = styles.display;
                    existingTeacher.style.verticalAlign = styles.verticalAlign;
                }
                return;
            }
            
            // Если кнопка еще не добавлена, добавляем ее
            if (!existingLK) {
                addLKButton();
                // После добавления обновляем стили
                setTimeout(function() {
                    var btn = document.getElementById('lk-button-deanpromoodle');
                    if (btn && styles) {
                        btn.style.paddingTop = styles.paddingTop;
                        btn.style.paddingRight = styles.paddingRight;
                        btn.style.paddingBottom = styles.paddingBottom;
                        btn.style.paddingLeft = styles.paddingLeft;
                        btn.style.height = styles.height;
                        btn.style.minHeight = styles.minHeight;
                        btn.style.maxHeight = styles.maxHeight;
                        btn.style.lineHeight = styles.lineHeight;
                        btn.style.fontSize = styles.fontSize;
                        btn.style.boxSizing = styles.boxSizing;
                        btn.style.display = styles.display;
                        btn.style.verticalAlign = styles.verticalAlign;
                    }
                    if (teacherButton) {
                        var tbtn = document.getElementById('teacher-button-deanpromoodle');
                        if (tbtn && styles) {
                            tbtn.style.paddingTop = styles.paddingTop;
                            tbtn.style.paddingRight = styles.paddingRight;
                            tbtn.style.paddingBottom = styles.paddingBottom;
                            tbtn.style.paddingLeft = styles.paddingLeft;
                            tbtn.style.height = styles.height;
                            tbtn.style.minHeight = styles.minHeight;
                            tbtn.style.maxHeight = styles.maxHeight;
                            tbtn.style.lineHeight = styles.lineHeight;
                            tbtn.style.fontSize = styles.fontSize;
                            tbtn.style.boxSizing = styles.boxSizing;
                            tbtn.style.display = styles.display;
                            tbtn.style.verticalAlign = styles.verticalAlign;
                        }
                    }
                }, 100);
            }
        }
        
        // Пытаемся добавить сразу
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() { tryAddButton(0); }, 100);
            });
        } else {
            setTimeout(function() { tryAddButton(0); }, 100);
        }
        
        // Также пытаемся добавить после задержек на случай динамической загрузки
        setTimeout(function() { tryAddButton(0); }, 500);
        setTimeout(function() { tryAddButton(0); }, 1000);
        setTimeout(function() { tryAddButton(0); }, 2000);
    })();
    ";
    
    $PAGE->requires->js_init_code($js);
}
