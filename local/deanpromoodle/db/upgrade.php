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
    
    // Добавляем поле credits в таблицу local_deanpromoodle_subjects
    if ($oldversion < 2026013005) {
        $table = new xmldb_table('local_deanpromoodle_subjects');
        $field = new xmldb_field('credits', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2026013005, 'local', 'deanpromoodle');
    }
    
    // Создаем таблицу личной информации студентов
    if ($oldversion < 2026013006) {
        $table = new xmldb_table('local_deanpromoodle_student_info');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('firstname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('middlename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('enrollment_year', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
            $table->add_field('gender', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('birthdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('snils', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('mobile', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('citizenship', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('birthplace', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('id_type', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('passport_number', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('passport_issued_by', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('passport_issue_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('passport_division_code', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('postal_index', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('country', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('region', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('street', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('house_apartment', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('previous_institution', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('previous_institution_year', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
            $table->add_field('cohort', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('userid_unique', XMLDB_KEY_UNIQUE, ['userid']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2026013006, 'local', 'deanpromoodle');
    }
    
    // Создание таблицы local_deanpromoodle_forum_no_reply
    if ($oldversion < 2026013007) {
        $table = new xmldb_table('local_deanpromoodle_forum_no_reply');
        
        // Добавляем таблицу, если её нет
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('postid_fk', XMLDB_KEY_FOREIGN, ['postid'], 'forum_posts', ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('postuser', XMLDB_KEY_UNIQUE, ['postid', 'userid']);
            
            // Не добавляем отдельные индексы, так как внешние ключи уже автоматически создают индексы
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2026013007, 'local', 'deanpromoodle');
    }
    
    // Добавляем поля academic_hours и independent_hours в таблицу local_deanpromoodle_subjects
    if ($oldversion < 2026013008) {
        $table = new xmldb_table('local_deanpromoodle_subjects');
        
        // Поле academic_hours (количество академических часов)
        $field = new xmldb_field('academic_hours', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'credits');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Поле independent_hours (количество часов самостоятельной работы)
        $field = new xmldb_field('independent_hours', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'academic_hours');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        upgrade_plugin_savepoint(true, 2026013008, 'local', 'deanpromoodle');
    }
    
    // Создаем таблицу заметок по студентам
    if ($oldversion < 2026020901) {
        $table = new xmldb_table('local_deanpromoodle_student_notes');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('note', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
            $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            
            // Индексы для studentid и createdby создаются автоматически при создании внешних ключей
            // Добавляем только индекс для timecreated
            $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2026020901, 'local', 'deanpromoodle');
    }

    // Добавление таблицы внешних зачетов студентов
    if ($oldversion < 2026021001) {
        $table = new xmldb_table('local_deanpromoodle_student_external_credits');
        
        if (!$dbman->table_exists($table)) {
            // Создаем поля таблицы
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('subjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('grade', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'Оценка: 3, 4, 5 или процент');
            $table->add_field('grade_percent', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, null, 'Оценка в процентах (0-100)');
            $table->add_field('institution_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'Название учебного заведения');
            $table->add_field('institution_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'ID учебного заведения');
            $table->add_field('credited_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'Дата зачета (timestamp)');
            $table->add_field('document_number', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'Номер документа о зачете');
            $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'Примечания');
            $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'Кто добавил запись');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            // Добавляем ключи
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('studentid', XMLDB_KEY_FOREIGN, ['studentid'], 'user', ['id']);
            $table->add_key('subjectid', XMLDB_KEY_FOREIGN, ['subjectid'], 'local_deanpromoodle_subjects', ['id']);
            $table->add_key('createdby', XMLDB_KEY_FOREIGN, ['createdby'], 'user', ['id']);
            $table->add_key('institution_id', XMLDB_KEY_FOREIGN, ['institution_id'], 'local_deanpromoodle_institutions', ['id']);
            $table->add_key('student_subject', XMLDB_KEY_UNIQUE, ['studentid', 'subjectid'], null, null, 'Один студент может иметь только один внешний зачет по предмету');
            
            // Добавляем индексы
            $table->add_index('credited_date', XMLDB_INDEX_NOTUNIQUE, ['credited_date']);
            $table->add_index('institution_name', XMLDB_INDEX_NOTUNIQUE, ['institution_name']);
            
            $dbman->create_table($table);
        }
        
        upgrade_plugin_savepoint(true, 2026021001, 'local', 'deanpromoodle');
    }

    return true;
}
