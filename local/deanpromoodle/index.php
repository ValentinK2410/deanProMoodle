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
 * @author     ValentinK2410 <https://github.com/ValentinK2410>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This file prevents direct access to the plugin directory.
// Users should access specific pages like student.php, teacher.php, or admin.php

// Define path to Moodle config
$configpath = __DIR__ . '/../../config.php';
if (!file_exists($configpath)) {
    http_response_code(403);
    die('Access denied. Please access specific pages: student.php, teacher.php, or admin.php');
}

require_once($configpath);
require_login();

// Redirect to site home
redirect(new moodle_url('/'));
