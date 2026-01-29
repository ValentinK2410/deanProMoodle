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
 * Index file for local_deanpromoodle plugin.
 * Prevents direct access to plugin directory.
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file prevents direct access to the plugin directory.
// Users should access specific pages like student.php, teacher.php, or admin.php

require_once(__DIR__ . '/../../../../config.php');
require_login();

// Redirect to site home or show access denied
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// If user is admin, show plugin information
if (has_capability('moodle/site:config', $context)) {
    redirect(new moodle_url('/'));
}

// For other users, redirect to home
redirect(new moodle_url('/'));
