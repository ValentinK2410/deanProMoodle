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
 * @param bool $visibleonly если false — для админ-ленты показывать и программы с visible = 0
 * @return array cohortid => "Программа1; Программа2"
 */
function local_deanpromoodle_feed_program_labels_for_cohorts(array $cohortids, $visibleonly = true) {
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
    $vissql = $visibleonly ? 'AND p.visible = 1' : '';
    $rows = $DB->get_records_sql(
        "SELECT pc.cohortid, p.name
           FROM {local_deanpromoodle_program_cohorts} pc
           JOIN {local_deanpromoodle_programs} p ON p.id = pc.programid
          WHERE pc.cohortid $insql $vissql
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
 * @param bool $visibleonly см. {@see local_deanpromoodle_feed_program_labels_for_cohorts()}
 * @return string
 */
function local_deanpromoodle_feed_user_program_string($userid, $visibleonly = true) {
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
    $labels = local_deanpromoodle_feed_program_labels_for_cohorts($ids, $visibleonly);
    $parts = [];
    foreach ($ids as $cid) {
        if (!empty($labels[$cid])) {
            $parts[] = $labels[$cid];
        }
    }
    return implode(' | ', array_unique($parts));
}

/**
 * Один «главный» ярлык для ленты: первая часть при нескольких значениях (когорты, программы).
 *
 * @param string $text
 * @return string
 */
function local_deanpromoodle_feed_primary_label($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    foreach ([' | ', '; ', ' · '] as $sep) {
        $pos = strpos($text, $sep);
        if ($pos !== false) {
            return trim(substr($text, 0, $pos));
        }
    }
    return $text;
}

/**
 * Один курс для ленты абитуриентов: последнее активное зачисление студента (по timestart/timecreated).
 *
 * @param array $userids
 * @return array userid => ['course' => string, 'coursedates' => string]
 */
function local_deanpromoodle_feed_user_course_display_batch(array $userids) {
    global $DB;

    $out = [];
    if (empty($userids)) {
        return $out;
    }
    $userids = array_unique(array_map('intval', array_filter($userids)));
    if (empty($userids)) {
        return $out;
    }

    $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);
    foreach ($userids as $uid) {
        $out[$uid] = ['course' => '', 'coursedates' => ''];
    }
    if (!$studentroleid) {
        return $out;
    }

    list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $params['srid'] = $studentroleid;
    $params['ctxcourse'] = CONTEXT_COURSE;

    $sql = "SELECT ue.userid, c.id AS courseid, c.fullname, c.shortname, c.startdate, c.enddate,
                   COALESCE(NULLIF(ue.timestart, 0), ue.timecreated) AS sortenrol
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
              JOIN {course} c ON c.id = e.courseid AND c.id > 1
              JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :ctxcourse
              JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.contextid = ctx.id AND ra.roleid = :srid
             WHERE ue.userid $insql AND ue.status = 0
          ORDER BY ue.userid ASC, sortenrol DESC, c.id ASC";

    $rows = $DB->get_recordset_sql($sql, $params);
    $bestbyuser = [];
    foreach ($rows as $r) {
        $uid = (int) $r->userid;
        $sort = (int) $r->sortenrol;
        if (!isset($bestbyuser[$uid]) || $sort > (int) $bestbyuser[$uid]->sortenrol) {
            $bestbyuser[$uid] = $r;
        }
    }
    $rows->close();

    foreach ($userids as $uid) {
        if (empty($bestbyuser[$uid])) {
            continue;
        }
        $r = $bestbyuser[$uid];
        $start = !empty($r->startdate) && (int) $r->startdate > 0
            ? userdate((int) $r->startdate, get_string('strftimedate', 'langconfig')) : '—';
        $end = !empty($r->enddate) && (int) $r->enddate > 0
            ? userdate((int) $r->enddate, get_string('strftimedate', 'langconfig')) : '—';
        $out[$uid] = [
            'course' => format_string($r->fullname) . ' (' . format_string($r->shortname) . ')',
            'coursedates' => $start . ' — ' . $end,
        ];
    }

    return $out;
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
 * Нормализовать подвкладку ленты абитуриентов.
 *
 * @param string $kind registration|course|cohort
 * @return string
 */
function local_deanpromoodle_activity_feed_normalize_kind($kind) {
    $allowed = ['registration', 'course', 'cohort'];
    return in_array($kind, $allowed, true) ? $kind : 'registration';
}

/**
 * student_info для набора пользователей (лента абитуриентов).
 *
 * @param array $userids
 * @return array userid => record
 */
function local_deanpromoodle_feed_student_info_by_userids(array $userids) {
    global $DB;

    $out = [];
    $userids = array_unique(array_map('intval', array_filter($userids)));
    if (empty($userids) || !$DB->get_manager()->table_exists('local_deanpromoodle_student_info')) {
        return $out;
    }
    list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
    $sirecs = $DB->get_records_sql(
        "SELECT * FROM {local_deanpromoodle_student_info} WHERE userid $insql",
        $params
    );
    foreach ($sirecs as $s) {
        $out[(int) $s->userid] = $s;
    }
    return $out;
}

/**
 * Собрать элементы ленты: активные (не скрытые) или только скрытые.
 * Активная лента — по подвкладке feedkind (v2026052201): новые аккаунты, запись на курс, глобальные группы.
 *
 * @param string $view active|hidden
 * @param string $kind registration|course|cohort — только для active
 * @return array массив объектов с полями для таблицы
 */
function local_deanpromoodle_get_admin_activity_feed($view, $kind = 'registration') {
    global $DB;

    $view = ($view === 'hidden') ? 'hidden' : 'active';
    $kind = local_deanpromoodle_activity_feed_normalize_kind($kind);
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
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], IGNORE_MISSING);

    if ($kind === 'registration' && $roleid) {
        // Новые аккаунты: только user.timecreated за последние 90 дней (без массового назначения роли).
        $users = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.timecreated
               FROM {user} u
               JOIN {role_assignments} ra ON ra.userid = u.id AND ra.roleid = :roleid
              WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 1
                AND u.timecreated >= :since
           ORDER BY u.timecreated DESC",
            ['roleid' => $roleid, 'since' => $since],
            0,
            500
        );
        $uids = array_map(static function($u) {
            return (int) $u->id;
        }, array_values($users));
        $cohortstr = local_deanpromoodle_feed_user_cohort_strings($uids);
        $coursebatch = local_deanpromoodle_feed_user_course_display_batch($uids);
        $sibyuser = local_deanpromoodle_feed_student_info_by_userids($uids);
        foreach ($users as $u) {
            if (!local_deanpromoodle_user_is_mbs_portal_applicant($u->id)) {
                continue;
            }
            $key = 'na_' . $u->id;
            if (isset($dismissset[$key])) {
                continue;
            }
            $programs = local_deanpromoodle_feed_primary_label(local_deanpromoodle_feed_user_program_string($u->id, false));
            $cohorts = isset($cohortstr[$u->id]) ? $cohortstr[$u->id] : '';
            $sirow = array_key_exists($u->id, $sibyuser) ? $sibyuser[$u->id] : false;
            $formcomplete = local_deanpromoodle_applicant_additional_form_complete($u->id, $sirow, $u);
            $cdata = isset($coursebatch[$u->id]) ? $coursebatch[$u->id] : ['course' => '', 'coursedates' => ''];
            $coursecell = $cdata['course'];
            $datescell = $cdata['coursedates'];
            if ($coursecell === '' && $sirow && trim((string) ($sirow->intended_course ?? '')) !== '') {
                $coursecell = format_string(trim($sirow->intended_course));
            }
            $items[] = (object) [
                'itemkey' => $key,
                'type' => 'registration',
                'typelabel' => get_string('feedtype_registration', 'local_deanpromoodle'),
                'sorttime' => (int) $u->timecreated,
                'userid' => $u->id,
                'studentname' => fullname($u),
                'email' => $u->email,
                'cohorts' => $cohorts ?: '—',
                'programs' => $programs ?: '—',
                'course' => $coursecell !== '' ? $coursecell : '—',
                'coursedates' => $datescell !== '' ? $datescell : '—',
                'form_complete' => $formcomplete,
            ];
        }
    } else if ($kind === 'course' && $roleid) {
        // Индивидуальная запись на курс (без синхронизации из глобальной группы).
        $enrols = $DB->get_records_sql(
            "SELECT ue.id AS ueid, ue.userid, ue.timecreated, ue.timestart,
                    c.fullname AS coursename, c.shortname, c.startdate, c.enddate,
                    u.firstname, u.lastname, u.email
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid > 1 AND e.enrol <> 'cohort'
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.status = 0
                AND u.deleted = 0
                AND (ue.timecreated >= :since1 OR COALESCE(ue.timestart, 0) >= :since2)
                AND EXISTS (
                    SELECT 1 FROM {role_assignments} ra
                     WHERE ra.userid = ue.userid AND ra.roleid = :roleid
                )
           ORDER BY COALESCE(ue.timestart, ue.timecreated) DESC",
            [
                'since1' => $since,
                'since2' => $since,
                'roleid' => $roleid,
            ],
            0,
            400
        );
        $euids = [];
        foreach ($enrols as $e) {
            $euids[(int) $e->userid] = true;
        }
        $ecohortstr = local_deanpromoodle_feed_user_cohort_strings(array_keys($euids));
        $esibyuser = local_deanpromoodle_feed_student_info_by_userids(array_keys($euids));
        foreach ($enrols as $e) {
            if (!local_deanpromoodle_user_is_mbs_portal_applicant($e->userid)) {
                continue;
            }
            $key = 'ce_' . $e->ueid;
            if (isset($dismissset[$key])) {
                continue;
            }
            $sortt = !empty($e->timestart) ? (int) $e->timestart : (int) $e->timecreated;
            $start = $e->startdate > 0 ? userdate($e->startdate, get_string('strftimedate', 'langconfig')) : '—';
            $end = $e->enddate > 0 ? userdate($e->enddate, get_string('strftimedate', 'langconfig')) : '—';
            $uobj = (object) ['firstname' => $e->firstname, 'lastname' => $e->lastname, 'email' => $e->email];
            $programs = local_deanpromoodle_feed_primary_label(local_deanpromoodle_feed_user_program_string($e->userid, false));
            $cohorts = isset($ecohortstr[$e->userid]) ? $ecohortstr[$e->userid] : '';
            $sirow = array_key_exists($e->userid, $esibyuser) ? $esibyuser[$e->userid] : false;
            $formcomplete = local_deanpromoodle_applicant_additional_form_complete($e->userid, $sirow, $uobj);
            $items[] = (object) [
                'itemkey' => $key,
                'type' => 'course',
                'typelabel' => get_string('feedtype_course', 'local_deanpromoodle'),
                'sorttime' => $sortt,
                'userid' => $e->userid,
                'studentname' => fullname($uobj),
                'email' => $e->email,
                'cohorts' => $cohorts ?: '—',
                'programs' => $programs ?: '—',
                'course' => format_string($e->coursename) . ' (' . format_string($e->shortname) . ')',
                'coursedates' => $start . ' — ' . $end,
                'form_complete' => $formcomplete,
            ];
        }
    } else if ($kind === 'cohort' && $roleid) {
        // Зачисление в глобальные группы (когорты).
        $cms = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.userid, cm.cohortid, cm.timeadded,
                    ch.name AS cohortname,
                    u.firstname, u.lastname, u.email
               FROM {cohort_members} cm
               JOIN {cohort} ch ON ch.id = cm.cohortid
               JOIN {user} u ON u.id = cm.userid
              WHERE cm.timeadded >= :since
                AND u.deleted = 0
                AND EXISTS (
                    SELECT 1 FROM {role_assignments} ra
                     WHERE ra.userid = cm.userid AND ra.roleid = :roleid
                )
           ORDER BY cm.timeadded DESC",
            ['since' => $since, 'roleid' => $roleid],
            0,
            400
        );
        $cohortids = [];
        foreach ($cms as $cm) {
            $cohortids[(int) $cm->cohortid] = true;
        }
        $proglab = local_deanpromoodle_feed_program_labels_for_cohorts(array_keys($cohortids), false);
        $cmuids = [];
        foreach ($cms as $cm) {
            $cmuids[(int) $cm->userid] = true;
        }
        $cmcohortstr = local_deanpromoodle_feed_user_cohort_strings(array_keys($cmuids));
        $cmsibyuser = local_deanpromoodle_feed_student_info_by_userids(array_keys($cmuids));
        foreach ($cms as $cm) {
            $key = 'cm_' . $cm->cmid;
            if (isset($dismissset[$key])) {
                continue;
            }
            $prog = isset($proglab[$cm->cohortid]) ? local_deanpromoodle_feed_primary_label($proglab[$cm->cohortid]) : '—';
            $allcohorts = isset($cmcohortstr[$cm->userid]) ? $cmcohortstr[$cm->userid] : format_string($cm->cohortname);
            $uobj = (object) ['firstname' => $cm->firstname, 'lastname' => $cm->lastname, 'email' => $cm->email];
            $sirow = array_key_exists($cm->userid, $cmsibyuser) ? $cmsibyuser[$cm->userid] : false;
            $formcomplete = local_deanpromoodle_applicant_additional_form_complete($cm->userid, $sirow, $uobj);
            $items[] = (object) [
                'itemkey' => $key,
                'type' => 'cohort',
                'typelabel' => get_string('feedtype_cohort', 'local_deanpromoodle'),
                'sorttime' => (int) $cm->timeadded,
                'userid' => $cm->userid,
                'studentname' => fullname($uobj),
                'email' => $cm->email,
                'cohorts' => $allcohorts,
                'programs' => $prog,
                'course' => '—',
                'coursedates' => userdate($cm->timeadded, get_string('strftimedatetime', 'langconfig')),
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
        $programs = local_deanpromoodle_feed_primary_label(local_deanpromoodle_feed_user_program_string($id, false));
        $formcomplete = local_deanpromoodle_applicant_additional_form_complete($id);
        $cdata = local_deanpromoodle_feed_user_course_display_batch([$id]);
        $cd = isset($cdata[$id]) ? $cdata[$id] : ['course' => '', 'coursedates' => ''];
        $coursecell = $cd['course'];
        $datescell = $cd['coursedates'];
        if ($coursecell === '') {
            if ($DB->get_manager()->table_exists('local_deanpromoodle_student_info')) {
                $si = $DB->get_record('local_deanpromoodle_student_info', ['userid' => $id]);
                if ($si && trim((string) ($si->intended_course ?? '')) !== '') {
                    $coursecell = format_string(trim($si->intended_course));
                }
            }
        }
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
            'course' => $coursecell !== '' ? $coursecell : '—',
            'coursedates' => $datescell !== '' ? $datescell : '—',
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
        $programs = local_deanpromoodle_feed_primary_label(local_deanpromoodle_feed_user_program_string($e->userid, false));
        $uobj = (object)['firstname' => $e->firstname, 'lastname' => $e->lastname, 'email' => $e->email];
        $formcomplete = local_deanpromoodle_applicant_additional_form_complete($e->userid, false, $uobj);
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
            'form_complete' => $formcomplete,
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
        $proglab = local_deanpromoodle_feed_program_labels_for_cohorts([$cm->cohortid], false);
        $prog = isset($proglab[$cm->cohortid]) ? local_deanpromoodle_feed_primary_label($proglab[$cm->cohortid]) : '—';
        $allcohorts = local_deanpromoodle_feed_user_cohort_strings([$cm->userid]);
        $cohorts = !empty($allcohorts[$cm->userid]) ? $allcohorts[$cm->userid] : format_string($cm->cohortname);
        $uobj = (object)['firstname' => $cm->firstname, 'lastname' => $cm->lastname, 'email' => $cm->email];
        $formcomplete = local_deanpromoodle_applicant_additional_form_complete($cm->userid, false, $uobj);
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
            'form_complete' => $formcomplete,
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
 * Определить MIME для загрузки скана: finfo часто даёт application/octet-stream для PDF/JPEG.
 *
 * @param string $pathname путь к временному файлу
 * @param string $originalname оригинальное имя из браузера
 * @return string распознанный тип из image/jpeg | image/png | application/pdf или пустая строка
 */
function local_deanpromoodle_identity_upload_resolve_mimetype($pathname, $originalname) {
    $allowed = ['image/jpeg' => true, 'image/png' => true, 'application/pdf' => true];
    $mimetype = '';
    if (is_readable($pathname)) {
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mimetype = $fi->file($pathname) ?: '';
        } else if (function_exists('mime_content_type')) {
            $mimetype = mime_content_type($pathname) ?: '';
        }
    }
    $mimetype = strtolower(trim((string) $mimetype));
    if (isset($allowed[$mimetype])) {
        return $mimetype;
    }

    $ext = strtolower((string) pathinfo($originalname, PATHINFO_EXTENSION));
    $exttomime = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];
    if ($ext !== '' && isset($exttomime[$ext])) {
        return $exttomime[$ext];
    }

    $fh = @fopen($pathname, 'rb');
    if ($fh) {
        $head = fread($fh, 8);
        fclose($fh);
        if ($head !== false && $head !== '') {
            if (strncmp($head, '%PDF-', 5) === 0) {
                return 'application/pdf';
            }
            if (isset($head[1]) && $head[0] === "\xff" && $head[1] === "\xd8") {
                return 'image/jpeg';
            }
            if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
                return 'image/png';
            }
            if (strlen($head) >= 4 && substr($head, 0, 4) === "\x89PNG") {
                return 'image/png';
            }
        }
    }

    return '';
}

/**
 * Слоты прикладываемых файлов в filearea identitydocs (паспорт, документ об образовании, рекомендация церкви).
 *
 * @return array slot => ключ строки имени слота из local_deanpromoodle
 */
function local_deanpromoodle_student_document_slot_labels(): array {
    return [
        'passport_scan1' => 'identitydoc_passport_main',
        'passport_scan2' => 'identitydoc_passport_reg',
        'education_document' => 'additionaldoc_education',
        'church_recommendation' => 'additionaldoc_church_rec',
    ];
}

/**
 * @return string[]
 */
function local_deanpromoodle_student_document_slots(): array {
    return array_keys(local_deanpromoodle_student_document_slot_labels());
}

/**
 * Сохраняет загруженные файлы документов (несколько независимых слотов) в filearea пользователя identitydocs.
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
    $slots = local_deanpromoodle_student_document_slots();

    foreach ($slots as $slot) {
        if (empty($files[$slot]) || !isset($files[$slot]['error']) || $files[$slot]['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($files[$slot]['error'] !== UPLOAD_ERR_OK) {
            $errcode = (int) $files[$slot]['error'];
            if ($errcode === UPLOAD_ERR_INI_SIZE || $errcode === UPLOAD_ERR_FORM_SIZE) {
                return 'Файл слишком большой для настроек PHP/сервера. Разрешено до 5 МБ на файл; при необходимости уменьшите файл или сообщите администратору (upload_max_filesize / post_max_size).';
            }
            if ($errcode === UPLOAD_ERR_PARTIAL) {
                return 'Загрузка прервалась. Попробуйте отправить форму ещё раз.';
            }
            if ($errcode === UPLOAD_ERR_NO_TMP_DIR) {
                return 'На сервере не настроен каталог временных загрузок (upload_tmp_dir).';
            }
            if ($errcode === UPLOAD_ERR_CANT_WRITE || $errcode === UPLOAD_ERR_EXTENSION) {
                return 'Не удалось записать файл на сервер. Обратитесь к администратору.';
            }
            return 'Ошибка загрузки файла (код ' . $errcode . '): ' . $slot;
        }
        if ($files[$slot]['size'] > $maxbytes) {
            return 'Файл слишком большой (максимум 5 МБ): ' . $slot;
        }
        $tmp = $files[$slot]['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return 'Некорректная загрузка файла. Попробуйте другой браузер или обратитесь к администратору (проверка is_uploaded_file).';
        }
        $mimetype = local_deanpromoodle_identity_upload_resolve_mimetype($tmp, $files[$slot]['name'] ?? '');
        if (!in_array($mimetype, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            return 'Разрешены только JPG, PNG и PDF с корректным содержимым. Если файл PDF, сохраните его заново или смените формат.';
        }
        $filename = clean_filename($files[$slot]['name']);
        if ($filename === '') {
            return 'Пустое имя файла';
        }
        $filepath = '/' . $slot . '/';
        // В Moodle 4.x delete_area_files() не принимает путь к подпапке: 5-й аргумент игнорируется,
        // и удаляется вся filearea identitydocs — отсюда пропадал первый скан при загрузке второго.
        local_deanpromoodle_delete_identity_scan($userid, $slot);
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
        try {
            $fs->create_file_from_pathname($record, $tmp);
        } catch (\Throwable $e) {
            debugging('local_deanpromoodle_save_identity_scans create_file: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 'Не удалось записать файл в хранилище Moodle. Обратитесь к администратору сайта.';
        }
        $stored = $fs->get_file($context->id, 'local_deanpromoodle', 'identitydocs', 0, $filepath, $filename);
        if (!$stored || $stored->is_directory()) {
            return 'Файл не найден сразу после сохранения — возможна ошибка дискового хранилища.';
        }
    }

    return null;
}

/**
 * Удалить скан в слоте.
 *
 * @param int $userid
 * @param string $slot один из ключей {@see local_deanpromoodle_student_document_slots()}
 */
function local_deanpromoodle_delete_identity_scan($userid, $slot) {
    if (!in_array($slot, local_deanpromoodle_student_document_slots(), true)) {
        return;
    }
    $targetpath = '/' . $slot . '/';
    try {
        $context = context_user::instance((int) $userid);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'local_deanpromoodle',
            'identitydocs',
            0,
            'filepath, filename',
            false
        );
        foreach ($files as $f) {
            if ($f->get_filepath() === $targetpath) {
                $f->delete();
            }
        }
    } catch (\Throwable $e) {
        debugging('local_deanpromoodle_delete_identity_scan: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

/**
 * Получить файлы сканов для отображения.
 *
 * @param int $userid
 * @return array ключ slot => stored_file|null
 */
function local_deanpromoodle_get_identity_doc_files($userid) {
    $out = array_fill_keys(local_deanpromoodle_student_document_slots(), null);
    try {
        $context = context_user::instance((int) $userid);
        $fs = get_file_storage();
        // В file_storage::get_area_files пятый аргумент — строка SORT (ORDER BY), не filepath.
        // Раньше сюда передавали '/' . $slot . '/', из‑за чего записи из БД не находились и сканы не отображались.
        $files = $fs->get_area_files(
            $context->id,
            'local_deanpromoodle',
            'identitydocs',
            0,
            'filepath, filename',
            false
        );
        foreach ($files as $f) {
            if ($f->is_directory()) {
                continue;
            }
            $path = $f->get_filepath();
            foreach (array_keys($out) as $slot) {
                if ($path === '/' . $slot . '/') {
                    if ($out[$slot] === null || $f->get_timemodified() > $out[$slot]->get_timemodified()) {
                        $out[$slot] = $f;
                    }
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
 * URL отдачи файла из identitydocs (pluginfile). При $forcedownload браузер предложит сохранить файл.
 *
 * @param stored_file $file
 * @param bool $forcedownload
 * @return moodle_url
 */
function local_deanpromoodle_make_identity_doc_pluginfile_url(stored_file $file, $forcedownload = false) {
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'local_deanpromoodle',
        'identitydocs',
        0,
        $file->get_filepath(),
        $file->get_filename(),
        (bool) $forcedownload
    );
}

/**
 * Ссылка «Скачать» для сохранённого скана без открытия в браузере.
 *
 * @param stored_file $file
 * @return string HTML (одна ссылка)
 */
function local_deanpromoodle_render_identity_download_link(stored_file $file) {
    return html_writer::link(
        local_deanpromoodle_make_identity_doc_pluginfile_url($file, true),
        get_string('identitydoc_download', 'local_deanpromoodle'),
        [
            'class' => 'btn btn-sm btn-outline-secondary local-deanpromoodle-identity-download',
        ]
    );
}

/**
 * Обёртка для превью: под ней кнопка скачивания.
 *
 * @param stored_file $file
 * @param string $previewinner HTML блока превью
 * @return string
 */
function local_deanpromoodle_wrap_identity_preview_with_download(stored_file $file, $previewinner) {
    $actions = html_writer::start_div('', [
        'style' => 'margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;',
    ]);
    $actions .= local_deanpromoodle_render_identity_download_link($file);
    $actions .= html_writer::end_div();
    return $previewinner . $actions;
}

/**
 * Встроенный просмотр скана (изображение или PDF в iframe) и ссылка на скачивание.
 *
 * @param stored_file $file
 * @return string HTML
 */
function local_deanpromoodle_render_identity_preview($file) {
    $url = local_deanpromoodle_make_identity_doc_pluginfile_url($file, false);
    $mime = $file->get_mimetype();

    $inner = '';
    if (strpos($mime, 'image/') === 0) {
        $inner = html_writer::empty_tag('img', [
            'src' => $url->out(false),
            'alt' => '',
            'style' => 'max-width:100%;max-height:480px;border:1px solid #dee2e6;border-radius:6px;',
        ]);
        return local_deanpromoodle_wrap_identity_preview_with_download($file, $inner);
    }
    if ($mime === 'application/pdf') {
        $u = htmlspecialchars($url->out(false), ENT_QUOTES, 'UTF-8');
        $inner = '<iframe src="' . $u . '" class="local-deanpromoodle-doc-iframe" '
            . 'style="width:100%;min-height:520px;border:1px solid #dee2e6;border-radius:6px;" title="PDF"></iframe>';
        return local_deanpromoodle_wrap_identity_preview_with_download($file, $inner);
    }

    $open = html_writer::link(
        $url,
        get_string('identitydoc_openfile', 'local_deanpromoodle'),
        ['target' => '_blank', 'rel' => 'noopener']
    );
    $actions = html_writer::start_div('', [
        'style' => 'margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;',
    ]);
    $actions .= $open;
    $actions .= local_deanpromoodle_render_identity_download_link($file);
    $actions .= html_writer::end_div();
    return $actions;
}
