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
 *
 * Не существует значения $plugin->version или $plugin->requires для этого плагина, которое «отключило бы»
 * эту страницу проверки ядра: это не параметр локального плагина.
 * Если сервер MySQL/MariaDB нельзя обновить до требуемой для Moodle 5 версии — обычно ставят ветку Moodle,
 * официально совместимую с вашей СУБД (часто это Moodle 4.x при MySQL 8.0.x), а не понижают версию плагина:
 * номер версии локального плагина не смягчает требование ядра к серверу БД.
 * Иначе: привести СУБД к версии из отчёта проверки окружения.
 *
 * Если тормозит только обновление ЭТОГО плагина (проверка ядра уже зелёная): сверьте
 * значение в {config_plugins} для plugin=local_deanpromoodle с $plugin->version здесь или выполните
 * один шаг апгрейда локального плагина в администрировании.
 */

$plugin->component = 'local_deanpromoodle';
// Примечание для хостинга: $plugin->version задан как 2025091200 (вручную). Шаги миграции перечислены в db/upgrade.php (до savepoint 2026051101).
// Если в {config_plugins} уже записано большее число версии этого плагина — Moodle может сообщить о понижении версии (тогда нужно согласовать файл и БД).
// Логические метки только в комментариях: 2026051102 — скачать identitydocs; 2026051103 — selected в списке пола.
// Ранее: лента абитуриентов, регистрация студента и т.д.
$plugin->version = 2025091200;
$plugin->requires = 2022041900; // Moodle 4.0+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0.0-alpha';
