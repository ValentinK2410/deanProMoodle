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
 * Upgrade script for local_deanpromoodle plugin.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for adding institution field to programs table.
 *
 * @param int $oldversion The old version number
 * @return bool True on success
 */
function xmldb_local_deanpromoodle_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();
    
    // Добавляем поле institution в таблицу local_deanpromoodle_programs
    if ($oldversion < 2026013001) {
        $table = new xmldb_table('local_deanpromoodle_programs');
        $field = new xmldb_field('institution', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2026013001, 'local', 'deanpromoodle');
    }
    
    return true;
}
