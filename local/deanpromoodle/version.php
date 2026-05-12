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
 * Version details for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/*
 * Внимание администраторам Moodle 5+: страница «Информация о текущем выпуске» / проверка окружения
 * с ошибкой по строке database (например: требуется MySQL/MariaDB 8.4, установлено 8.0.x)
 * задаётся требованиями ЯДРА Moodle и сервера СУБД, а не номером $plugin->version ниже.
 * Изменение версии этого плагина не снимает красную проверку по БД: обновите СУБД до требуемой
 * указанием в проверке, либо используйте ветку Moodle, совместимую с вашей версией MySQL/MariaDB.
 *
 * Если сайт уже прошёл проверку, а тормозит только обновление ЭТОГО плагина: убедитесь, что
 * в таблице {config_plugins} для plugin=local_deanpromoodle версия совпадает с $plugin->version здесь,
 * либо дайте установщику один раз выполнить upgrade локального плагина (без смешения с требованиями ядра).
 */

$plugin->component = 'local_deanpromoodle';
// 2026051101 — последний upgrade savepoint в db/upgrade.php; доп. поля student_info, слоты identitydocs.
// 2026051102 — актуальный $plugin->version ниже; кнопка «Скачать» для файлов из identitydocs.
// Ранее: лента абитуриентов, регистрация студента и т.д.
// 2026051103 — только в комментарии: выпадающий список «Пол», корректный атрибут selected у option (не «Ж» по ошибке).
$plugin->version = 2026051102;
$plugin->requires = 2022041900; // Moodle 4.0+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0.0-alpha';
