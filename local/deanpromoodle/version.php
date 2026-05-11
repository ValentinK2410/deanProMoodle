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

$plugin->component = 'local_deanpromoodle';
// 2026051101 — и последний upgrade savepoint в db/upgrade.php, и актуальный $plugin->version ниже.
// доп. поля в local_deanpromoodle_student_info (семья, образование/работа, церковь, адрес по частям),
// два новых файловых слота в identitydocs (документ об образовании, рекомендация церкви).
// Ранее: лента абитуриентов, регистрация студента и т.д.
// Значения ниже только в комментариях (код уже в дереве): 2026051102 — кнопка «Скачать» для identitydocs.
// 2026051103 — выпадающий список «Пол»: не передавать пустой selected у option (ошибочно показывался «Ж»).
$plugin->version = 2026051101;
$plugin->requires = 2022041900; // Moodle 4.0+
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v1.0.0-alpha';
