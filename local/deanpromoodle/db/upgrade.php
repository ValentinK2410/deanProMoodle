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
    
    // Создаем таблицу учебных заведений
    if ($oldversion < 2026013002) {
        $table = new xmldb_table('local_deanpromoodle_institutions');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('address', XMLDB_TYPE_CHAR, '500', null, null, null, null);
            $table->add_field('phone', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('website', XMLDB_TYPE_CHAR, '500', null, null, null, null);
            $table->add_field('logo', XMLDB_TYPE_CHAR, '500', null, null, null, null);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('visible', XMLDB_INDEX_NOTUNIQUE, ['visible']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2026013002, 'local', 'deanpromoodle');
    }
    
    // Добавляем поля price и is_paid в таблицу local_deanpromoodle_programs
    if ($oldversion < 2026013003) {
        $table = new xmldb_table('local_deanpromoodle_programs');
        
        // Поле is_paid (тип оплаты: 0 - бесплатный, 1 - платный)
        $field = new xmldb_field('is_paid', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'institution');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Поле price (цена)
        $field = new xmldb_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'is_paid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2026013003, 'local', 'deanpromoodle');
    }
    
    return true;
}
