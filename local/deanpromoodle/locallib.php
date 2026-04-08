<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Вспомогательные функции плагина (лента событий для администратора).
 *
 * @package    local_deanpromoodle
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Период (дней), за который подтягиваются события в активной ленте.
 *
 * @return int timestamp «с какой даты»
 */
function local_deanpromoodle_activity_feed_since() {
    return time() - 90 * DAYSECS;
}

/**
 * Проверка, что пользователь имеет роль student где-либо.
 *
 * @param int $userid
 * @return bool
 */
function local_deanpromoodle_user_is_student($userid) {
    global $DB;
    static $cache = [];
    if (isset($cache[$userid])) {
        return $cache[$userid];
    }
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
    if (!$roleid) {
        $cache[$userid] = false;
        return false;
    }
    $cache[$userid] = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $roleid]);
    return $cache[$userid];
}

/**
 * Когорты пользователей одним запросом: userid => строка имён через запятую.
 *
 * @param array $userids
 * @return array
 */
function local_deanpromoodle_feed_user_cohort_strings(array $userids) {
    global $DB;
    if (empty($userids)) {
        return [];
    }
    $userids = array_map('intval', $userids);
    $userids = array_filter($userids);
    if (empty($userids)) {
        return [];
    }
    list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $rows = $DB->get_records_sql(
        "SELECT cm.userid, c.name
           FROM {cohort_members} cm
           JOIN {cohort} c ON c.id = cm.cohortid
          WHERE cm.userid $insql
       ORDER BY c.name ASC",
        $params
    );
    $out = [];
    foreach ($rows as $r) {
        if (!isset($out[$r->userid])) {
            $out[$r->userid] = [];
        }
        $out[$r->userid][] = $r->name;
    }
    foreach ($out as $uid => $names) {
        $out[$uid] = implode(', ', array_unique($names));
    }
    return $out;
}

/**
 * Названия программ по id когорт.
 *
 * @param array $cohortids
 * @return array cohortid => "Программа1; Программа2"
 */
function local_deanpromoodle_feed_program_labels_for_cohorts(array $cohortids) {
    global $DB;
    if (empty($cohortids)) {
        return [];
    }
    $cohortids = array_map('intval', $cohortids);
    $cohortids = array_unique(array_filter($cohortids));
    if (empty($cohortids)) {
        return [];
    }
    if (!$DB->get_manager()->table_exists('local_deanpromoodle_program_cohorts')) {
        return [];
    }
    list($insql, $params) = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
    $rows = $DB->get_records_sql(
        "SELECT pc.cohortid, p.name
           FROM {local_deanpromoodle_program_cohorts} pc
           JOIN {local_deanpromoodle_programs} p ON p.id = pc.programid
          WHERE pc.cohortid $insql AND p.visible = 1
       ORDER BY p.name ASC",
        $params
    );
    $out = [];
    foreach ($rows as $r) {
        if (!isset($out[$r->cohortid])) {
            $out[$r->cohortid] = [];
        }
        $out[$r->cohortid][] = $r->name;
    }
    foreach ($out as $cid => $names) {
        $out[$cid] = implode('; ', array_unique($names));
    }
    return $out;
}

/**
 * Программы для пользователя по его когортам (объединённая строка).
 *
 * @param int $userid
 * @return string
 */
function local_deanpromoodle_feed_user_program_string($userid) {
    global $DB;
    $cohorts = $DB->get_records_sql(
        "SELECT cm.cohortid
           FROM {cohort_members} cm
          WHERE cm.userid = ?",
        [$userid]
    );
    if (empty($cohorts)) {
        return '';
    }
    $ids = array_keys($cohorts);
    $labels = local_deanpromoodle_feed_program_labels_for_cohorts($ids);
    $parts = [];
    foreach ($ids as $cid) {
        if (!empty($labels[$cid])) {
            $parts[] = $labels[$cid];
        }
    }
    return implode(' | ', array_unique($parts));
}

/**
 * Режим фильтра абитуриентов: только портал МБС или все регистрации со ролью student.
 *
 * @return string mbs_only|all_registrations
 */
function local_deanpromoodle_applicants_filter_mode() {
    $mode = get_config('local_deanpromoodle', 'applicants_filter_mode');
    if ($mode === false || $mode === '') {
        return 'mbs_only';
    }
    return $mode === 'all_registrations' ? 'all_registrations' : 'mbs_only';
}

/**
 * Пользователь считается пришедшим с портала МБС (настраивается в админке плагина).
 *
 * @param int $userid
 * @return bool
 */
function local_deanpromoodle_user_is_mbs_portal_applicant($userid) {
    global $DB;

    if (local_deanpromoodle_applicants_filter_mode() === 'all_registrations') {
        return true;
    }

    $userid = (int) $userid;
    // Полная запись: в части установок Moodle нет колонки user.url (сайт вынесен в поля профиля).
    $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$u) {
        return false;
    }

    $hostsstr = get_config('local_deanpromoodle', 'applicants_source_hosts');
    if ($hostsstr === false || trim((string) $hostsstr) === '') {
        $hostsstr = 'mbs.russianseminary.org';
    }
    $hosts = array_filter(array_map('trim', explode(',', $hostsstr)));

    $urltext = '';
    if (isset($u->url) && (string) $u->url !== '') {
        $urltext = (string) $u->url;
    }
    if ($urltext === '' && $DB->get_manager()->table_exists('user_info_field')) {
        $fid = $DB->get_field('user_info_field', 'id', ['shortname' => 'url']);
        if ($fid) {
            $ud = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fid]);
            if ($ud && $ud->data !== '') {
                $urltext = $ud->data;
            }
        }
    }
    foreach ($hosts as $h) {
        if ($h !== '' && $urltext !== '' && stripos($urltext, $h) !== false) {
            return true;
        }
    }

    // Некоторые интеграции кладут ссылку на портал в idnumber вместо «Веб-страница».
    $idnum = trim((string) ($u->idnumber ?? ''));
    if ($idnum !== '') {
        foreach ($hosts as $h) {
            if ($h !== '' && stripos($idnum, $h) !== false) {
                return true;
            }
        }
    }

    $fieldshort = trim((string) get_config('local_deanpromoodle', 'applicants_profile_field'));
    if ($fieldshort !== '') {
        $field = $DB->get_record('user_info_field', ['shortname' => $fieldshort]);
        if ($field) {
            $d = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $field->id]);
            if ($d && $d->data !== '') {
                foreach ($hosts as $h) {
                    if ($h !== '' && stripos($d->data, $h) !== false) {
                        return true;
                    }
                }
            }
        }
    }

    $domains = trim((string) get_config('local_deanpromoodle', 'applicants_email_domain'));
    if ($domains !== '') {
        $parts = array_filter(array_map('trim', explode(',', $domains)));
        $em = strtolower(trim((string) $u->email));
        foreach ($parts as $dom) {
            $dom = strtolower($dom);
            if ($dom !== '' && substr($em, -strlen('@' . $dom)) === '@' . $dom) {
                return true;
            }
        }
    }

    $auths = trim((string) get_config('local_deanpromoodle', 'applicants_auth_plugins'));
    if ($auths !== '') {
        $allowed = array_map('trim', explode(',', $auths));
        if (in_array($u->auth, $allowed, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Обязательные поля «Дополнительные данные» для колонки «Форма» (11 полей).
 *
 * @param int $userid
 * @param stdClass|false|null $sipreloaded null — загрузить из БД; false — строки student_info нет; иначе запись
 * @param stdClass|null $upreloaded запись user (firstname, lastname, email) или null
 * @return bool
 */
function local_deanpromoodle_applicant_additional_form_complete($userid, $sipreloaded = null, stdClass $upreloaded = null) {
    global $DB;

    if (!$DB->get_manager()->table_exists('local_deanpromoodle_student_info')) {
        return false;
    }

    $userid = (int) $userid;
    if ($sipreloaded === null) {
        $si = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $userid]);
    } else if ($sipreloaded === false) {
        $si = null;
    } else {
        $si = $sipreloaded;
    }
    $u = $upreloaded ?? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$u) {
        return false;
    }

    $lastname = ($si && trim((string) ($si->lastname ?? '')) !== '')
        ? trim($si->lastname) : trim((string) $u->lastname);
    $firstname = ($si && trim((string) ($si->firstname ?? '')) !== '')
        ? trim($si->firstname) : trim((string) $u->firstname);
    $middlename = ($si && trim((string) ($si->middlename ?? '')) !== '') ? trim($si->middlename) : '';
    $birthdate = ($si && !empty($si->birthdate) && (int) $si->birthdate > 0) ? (int) $si->birthdate : 0;
    $mobile = ($si && trim((string) ($si->mobile ?? '')) !== '') ? trim($si->mobile) : '';
    $email = '';
    if ($si && trim((string) ($si->email ?? '')) !== '') {
        $email = trim($si->email);
    } else if (trim((string) $u->email) !== '') {
        $email = trim($u->email);
    }
    $idtype = ($si && trim((string) ($si->id_type ?? '')) !== '') ? trim($si->id_type) : '';
    $pnum = ($si && trim((string) ($si->passport_number ?? '')) !== '') ? trim($si->passport_number) : '';
    $pby = ($si && trim((string) ($si->passport_issued_by ?? '')) !== '') ? trim($si->passport_issued_by) : '';
    $pdate = ($si && !empty($si->passport_issue_date) && (int) $si->passport_issue_date > 0)
        ? (int) $si->passport_issue_date : 0;
    $pdiv = ($si && trim((string) ($si->passport_division_code ?? '')) !== '') ? trim($si->passport_division_code) : '';

    return $lastname !== '' && $firstname !== '' && $middlename !== ''
        && $birthdate > 0 && $mobile !== '' && $email !== ''
        && $idtype !== '' && $pnum !== '' && $pby !== '' && $pdate > 0 && $pdiv !== '';
}

/**
 * Собрать элементы ленты: активные (не скрытые) или только скрытые.
 * Активная лента: только регистрации; при mbs_only — фильтр по порталу МБС.
 *
 * @param string $view active|hidden
 * @return array массив объектов с полями для таблицы
 */
function local_deanpromoodle_get_admin_activity_feed($view) {
    global $DB;

    $view = ($view === 'hidden') ? 'hidden' : 'active';
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_deanpromoodle_admin_feed_dismissed')) {
        return [];
    }

    if ($view === 'hidden') {
        $dismissed = $DB->get_records('local_deanpromoodle_admin_feed_dismissed', null, 'timecreated DESC');
        $items = [];
        foreach ($dismissed as $d) {
            $row = local_deanpromoodle_feed_resolve_item($d->itemkey);
            if ($row) {
                $row->hiddenat = $d->timecreated;
                $row->hiddenby = $d->hiddenby;
                $hu = $DB->get_record('user', ['id' => $d->hiddenby, 'deleted' => 0], '*', IGNORE_MISSING);
                $row->hiddenbyname = $hu ? fullname($hu) : (string) $d->hiddenby;
                $items[] = $row;
            }
        }
        return $items;
    }

    $since = local_deanpromoodle_activity_feed_since();
    $dismisskeys = $DB->get_fieldset_select('local_deanpromoodle_admin_feed_dismissed', 'itemkey', '1=1');
    $dismissset = array_flip($dismisskeys);

    $items = [];

    // Регистрации студентов (вкладка «Абитуриенты»: только портал МБС при режиме mbs_only).
    // За «последние 90 дней» считаем и создание аккаунта, и назначение роли student (зачисление на программу/курс).
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
    if ($roleid) {
        $users = $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, u.timecreated,
                    (SELECT MAX(rax.timemodified) FROM {role_assignments} rax
                       WHERE rax.userid = u.id AND rax.roleid = :roleid2) AS studentrolelastmod
               FROM {user} u
               JOIN {role_assignments} ra ON ra.userid = u.id AND ra.roleid = :roleid1
              WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 1
                AND (
                      u.timecreated >= :since1
                   OR EXISTS (
                        SELECT 1 FROM {role_assignments} ra3
                         WHERE ra3.userid = u.id AND ra3.roleid = :roleid3 AND ra3.timemodified >= :since2
                      )
                    )
           ORDER BY u.timecreated DESC",
            [
                'roleid1' => $roleid,
                'roleid2' => $roleid,
                'roleid3' => $roleid,
                'since1' => $since,
                'since2' => $since,
            ],
            0,
            500
        );
        $users = array_values($users);
        foreach ($users as $u) {
            $lastmod = isset($u->studentrolelastmod) ? (int) $u->studentrolelastmod : 0;
            $u->sorttime = max((int) $u->timecreated, $lastmod);
        }
        usort($users, function($a, $b) {
            return ($b->sorttime ?? 0) <=> ($a->sorttime ?? 0);
        });
        $uids = array_map(static function($u) {
            return (int) $u->id;
        }, $users);
        $cohortstr = local_deanpromoodle_feed_user_cohort_strings($uids);
        $sibyuser = [];
        if ($dbman->table_exists('local_deanpromoodle_student_info') && count($uids) > 0) {
            list($insql, $psi) = $DB->get_in_or_equal($uids, SQL_PARAMS_NAMED);
            $sirecs = $DB->get_records_sql(
                "SELECT * FROM {local_deanpromoodle_student_info} WHERE userid $insql",
                $psi
            );
            foreach ($sirecs as $s) {
                $sibyuser[$s->userid] = $s;
            }
        }
        foreach ($users as $u) {
            if (!local_deanpromoodle_user_is_mbs_portal_applicant($u->id)) {
                continue;
            }
            $key = 'na_' . $u->id;
            if (isset($dismissset[$key])) {
                continue;
            }
            $programs = local_deanpromoodle_feed_user_program_string($u->id);
            $cohorts = isset($cohortstr[$u->id]) ? $cohortstr[$u->id] : '';
            $sirow = array_key_exists($u->id, $sibyuser) ? $sibyuser[$u->id] : false;
            $formcomplete = local_deanpromoodle_applicant_additional_form_complete($u->id, $sirow, $u);
            $items[] = (object) [
                'itemkey' => $key,
                'type' => 'registration',
                'typelabel' => get_string('feedtype_registration', 'local_deanpromoodle'),
                'sorttime' => isset($u->sorttime) ? (int) $u->sorttime : (int) $u->timecreated,
                'userid' => $u->id,
                'studentname' => fullname($u),
                'email' => $u->email,
                'cohorts' => $cohorts ?: '—',
                'programs' => $programs ?: '—',
                'course' => '—',
                'coursedates' => '—',
                'form_complete' => $formcomplete,
            ];
        }
    }

    usort($items, function($a, $b) {
        return $b->sorttime <=> $a->sorttime;
    });

    return $items;
}

/**
 * Восстановить одну строку ленты по ключу (для вкладки «Скрытые»).
 *
 * @param string $itemkey
 * @return stdClass|null
 */
function local_deanpromoodle_feed_resolve_item($itemkey) {
    global $DB;
    if (!preg_match('/^(na|ce|cm)_(\d+)$/', $itemkey, $m)) {
        return null;
    }
    $kind = $m[1];
    $id = (int)$m[2];

    if ($kind === 'na') {
        $u = $DB->get_record('user', ['id' => $id, 'deleted' => 0]);
        if (!$u || !local_deanpromoodle_user_is_student($id)) {
            return null;
        }
        if (!local_deanpromoodle_user_is_mbs_portal_applicant($id)) {
            return null;
        }
        $cohortstr = local_deanpromoodle_feed_user_cohort_strings([$id]);
        $programs = local_deanpromoodle_feed_user_program_string($id);
        $formcomplete = local_deanpromoodle_applicant_additional_form_complete($id);
        return (object) [
            'itemkey' => $itemkey,
            'type' => 'registration',
            'typelabel' => get_string('feedtype_registration', 'local_deanpromoodle'),
            'sorttime' => $u->timecreated,
            'userid' => $u->id,
            'studentname' => fullname($u),
            'email' => $u->email,
            'cohorts' => !empty($cohortstr[$id]) ? $cohortstr[$id] : '—',
            'programs' => $programs ?: '—',
            'course' => '—',
            'coursedates' => '—',
            'form_complete' => $formcomplete,
        ];
    }

    if ($kind === 'ce') {
        $e = $DB->get_record_sql(
            "SELECT ue.id AS ueid, ue.userid, ue.timecreated, ue.timestart,
                    c.fullname AS coursename, c.shortname, c.startdate, c.enddate,
                    u.firstname, u.lastname, u.email
               FROM {user_enrolments} ue
               JOIN {enrol} en ON en.id = ue.enrolid
               JOIN {course} c ON c.id = en.courseid
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.id = ? AND ue.status = 0",
            [$id]
        );
        if (!$e) {
            return null;
        }
        $sortt = !empty($e->timestart) ? $e->timestart : $e->timecreated;
        $start = $e->startdate > 0 ? userdate($e->startdate, get_string('strftimedate', 'langconfig')) : '—';
        $end = $e->enddate > 0 ? userdate($e->enddate, get_string('strftimedate', 'langconfig')) : '—';
        $cohortstr = local_deanpromoodle_feed_user_cohort_strings([$e->userid]);
        $programs = local_deanpromoodle_feed_user_program_string($e->userid);
        $uobj = (object)['firstname' => $e->firstname, 'lastname' => $e->lastname, 'email' => $e->email];
        return (object)[
            'itemkey' => $itemkey,
            'type' => 'course',
            'typelabel' => get_string('feedtype_course', 'local_deanpromoodle'),
            'sorttime' => $sortt,
            'userid' => $e->userid,
            'studentname' => fullname($uobj),
            'email' => $e->email,
            'cohorts' => !empty($cohortstr[$e->userid]) ? $cohortstr[$e->userid] : '—',
            'programs' => $programs ?: '—',
            'course' => format_string($e->coursename) . ' (' . format_string($e->shortname) . ')',
            'coursedates' => $start . ' — ' . $end,
        ];
    }

    if ($kind === 'cm') {
        $cm = $DB->get_record_sql(
            "SELECT cm.id AS cmid, cm.userid, cm.cohortid, cm.timeadded,
                    ch.name AS cohortname,
                    u.firstname, u.lastname, u.email
               FROM {cohort_members} cm
               JOIN {cohort} ch ON ch.id = cm.cohortid
               JOIN {user} u ON u.id = cm.userid
              WHERE cm.id = ?",
            [$id]
        );
        if (!$cm) {
            return null;
        }
        $proglab = local_deanpromoodle_feed_program_labels_for_cohorts([$cm->cohortid]);
        $prog = isset($proglab[$cm->cohortid]) ? $proglab[$cm->cohortid] : '—';
        $allcohorts = local_deanpromoodle_feed_user_cohort_strings([$cm->userid]);
        $cohorts = !empty($allcohorts[$cm->userid]) ? $allcohorts[$cm->userid] : format_string($cm->cohortname);
        $uobj = (object)['firstname' => $cm->firstname, 'lastname' => $cm->lastname, 'email' => $cm->email];
        return (object)[
            'itemkey' => $itemkey,
            'type' => 'cohort',
            'typelabel' => get_string('feedtype_cohort', 'local_deanpromoodle'),
            'sorttime' => $cm->timeadded,
            'userid' => $cm->userid,
            'studentname' => fullname($uobj),
            'email' => $cm->email,
            'cohorts' => $cohorts,
            'programs' => $prog,
            'course' => '—',
            'coursedates' => userdate($cm->timeadded, get_string('strftimedatetime', 'langconfig')),
        ];
    }

    return null;
}

/**
 * Доступ к сканам документов: владелец или админ/преподаватель (как на странице студента).
 *
 * @param int $targetuserid
 * @return bool
 */
function local_deanpromoodle_can_view_user_identity_docs($targetuserid) {
    global $USER, $DB;

    $targetuserid = (int) $targetuserid;
    if ((int) $USER->id === $targetuserid) {
        return true;
    }

    $ctx = context_system::instance();
    if (has_capability('moodle/site:config', $ctx) || has_capability('local/deanpromoodle:viewadmin', $ctx)) {
        return true;
    }

    $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'coursecreator')");
    if (empty($teacherroleids)) {
        return false;
    }
    list($insql, $params) = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED);
    return $DB->record_exists_sql(
        "SELECT 1 FROM {role_assignments} ra
          JOIN {context} ctx ON ctx.id = ra.contextid
         WHERE ra.userid = :me AND ra.roleid $insql
           AND (ctx.contextlevel = :clevel OR ctx.id = :sysid)",
        array_merge(['me' => $USER->id, 'clevel' => CONTEXT_COURSE, 'sysid' => $ctx->id], $params)
    );
}

/**
 * Сохраняет загруженные сканы (до 2 слотов) в filearea пользователя.
 *
 * @param int $userid Владелец файлов
 * @param array $files массив $_FILES
 * @return string|null сообщение об ошибке или null
 */
function local_deanpromoodle_save_identity_scans($userid, array $files) {
    global $USER, $DB;

    $userid = (int) $userid;
    if ($userid !== (int) $USER->id && !has_capability('moodle/site:config', context_system::instance())
            && !has_capability('local/deanpromoodle:viewadmin', context_system::instance())) {
        $teacherroleids = $DB->get_fieldset_select('role', 'id', "shortname IN ('teacher', 'editingteacher', 'coursecreator')");
        if (!empty($teacherroleids)) {
            list($insql, $p) = $DB->get_in_or_equal($teacherroleids, SQL_PARAMS_NAMED);
            $ok = $DB->record_exists_sql(
                "SELECT 1 FROM {role_assignments} ra WHERE ra.userid = :me AND ra.roleid $insql",
                array_merge(['me' => $USER->id], $p)
            );
            if (!$ok) {
                return 'Нет права загружать файлы за этого пользователя';
            }
        } else {
            return 'Нет права загружать файлы за этого пользователя';
        }
    }

    $context = context_user::instance($userid);
    $fs = get_file_storage();
    $maxbytes = 5 * 1024 * 1024;
    $allowedmimes = ['image/jpeg', 'image/png', 'application/pdf'];
    $slots = ['passport_scan1', 'passport_scan2'];

    foreach ($slots as $slot) {
        if (empty($files[$slot]) || !isset($files[$slot]['error']) || $files[$slot]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files[$slot]['error'] !== UPLOAD_ERR_OK) {
            return 'Ошибка загрузки файла: ' . $slot;
        }
        if ($files[$slot]['size'] > $maxbytes) {
            return 'Файл слишком большой (максимум 5 МБ): ' . $slot;
        }
        $tmp = $files[$slot]['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return 'Некорректная загрузка файла';
        }
        $mimetype = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mimetype = $fi->file($tmp);
        }
        if ($mimetype === '' && function_exists('mime_content_type')) {
            $mimetype = mime_content_type($tmp);
        }
        if (!in_array($mimetype, $allowedmimes, true)) {
            return 'Допустимы только JPG, PNG или PDF';
        }
        $filename = clean_filename($files[$slot]['name']);
        if ($filename === '') {
            return 'Пустое имя файла';
        }
        $filepath = '/' . $slot . '/';
        $fs->delete_area_files($context->id, 'local_deanpromoodle', 'identitydocs', 0, $filepath);
        $record = (object) [
            'contextid' => $context->id,
            'component' => 'local_deanpromoodle',
            'filearea' => 'identitydocs',
            'itemid' => 0,
            'filepath' => $filepath,
            'filename' => $filename,
            'userid' => (int) $USER->id,
            'author' => fullname($USER),
            'license' => 'allrightsreserved',
            'timemodified' => time(),
        ];
        $fs->create_file_from_pathname($record, $tmp);
    }

    return null;
}

/**
 * Удалить скан в слоте.
 *
 * @param int $userid
 * @param string $slot passport_scan1|passport_scan2
 */
function local_deanpromoodle_delete_identity_scan($userid, $slot) {
    if (!in_array($slot, ['passport_scan1', 'passport_scan2'], true)) {
        return;
    }
    $context = context_user::instance((int) $userid);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'local_deanpromoodle', 'identitydocs', 0, '/' . $slot . '/');
}

/**
 * Получить файлы сканов для отображения.
 *
 * @param int $userid
 * @return array ключ slot => stored_file|null
 */
function local_deanpromoodle_get_identity_doc_files($userid) {
    $out = ['passport_scan1' => null, 'passport_scan2' => null];
    try {
        $context = context_user::instance((int) $userid);
        $fs = get_file_storage();
        foreach (array_keys($out) as $slot) {
            $files = $fs->get_area_files($context->id, 'local_deanpromoodle', 'identitydocs', 0, '/' . $slot . '/', false);
            foreach ($files as $f) {
                if (!$f->is_directory()) {
                    $out[$slot] = $f;
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
        debugging('local_deanpromoodle_get_identity_doc_files: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    return $out;
}

/**
 * Встроенный просмотр скана (изображение или PDF в iframe).
 *
 * @param stored_file $file
 * @return string HTML
 */
function local_deanpromoodle_render_identity_preview($file) {
    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'local_deanpromoodle',
        'identitydocs',
        0,
        $file->get_filepath(),
        $file->get_filename(),
        false
    );
    $mime = $file->get_mimetype();
    if (strpos($mime, 'image/') === 0) {
        return html_writer::empty_tag('img', [
            'src' => $url->out(false),
            'alt' => '',
            'style' => 'max-width:100%;max-height:480px;border:1px solid #dee2e6;border-radius:6px;',
        ]);
    }
    if ($mime === 'application/pdf') {
        $u = htmlspecialchars($url->out(false), ENT_QUOTES, 'UTF-8');
        return '<iframe src="' . $u . '" class="local-deanpromoodle-doc-iframe" style="width:100%;min-height:520px;border:1px solid #dee2e6;border-radius:6px;" title="PDF"></iframe>';
    }
    return html_writer::link($url, get_string('identitydoc_openfile', 'local_deanpromoodle'), ['target' => '_blank', 'rel' => 'noopener']);
}
