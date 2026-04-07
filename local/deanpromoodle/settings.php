<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Настройки плагина (фильтр абитуриентов с портала МБС).
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($h = $ADMIN->locate('localplugins')) {
    $settings = new admin_settingpage('local_deanpromoodle', get_string('pluginname', 'local_deanpromoodle'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configselect(
        'local_deanpromoodle/applicants_filter_mode',
        get_string('applicants_filter_mode', 'local_deanpromoodle'),
        get_string('applicants_filter_mode_desc', 'local_deanpromoodle'),
        'mbs_only',
        [
            'mbs_only' => get_string('applicants_filter_mbs_only', 'local_deanpromoodle'),
            'all_registrations' => get_string('applicants_filter_all_reg', 'local_deanpromoodle'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_deanpromoodle/applicants_source_hosts',
        get_string('applicants_source_hosts', 'local_deanpromoodle'),
        get_string('applicants_source_hosts_desc', 'local_deanpromoodle'),
        'mbs.russianseminary.org',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_deanpromoodle/applicants_email_domain',
        get_string('applicants_email_domain', 'local_deanpromoodle'),
        get_string('applicants_email_domain_desc', 'local_deanpromoodle'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_deanpromoodle/applicants_profile_field',
        get_string('applicants_profile_field', 'local_deanpromoodle'),
        get_string('applicants_profile_field_desc', 'local_deanpromoodle'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_deanpromoodle/applicants_auth_plugins',
        get_string('applicants_auth_plugins', 'local_deanpromoodle'),
        get_string('applicants_auth_plugins_desc', 'local_deanpromoodle'),
        '',
        PARAM_TEXT
    ));
}
