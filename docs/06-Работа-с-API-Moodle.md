# Этап 6: Работа с API Moodle

## Введение

Moodle предоставляет множество API для работы с курсами, пользователями, ролями, оценками и другими сущностями. Правильное использование этих API обеспечивает совместимость с системой и упрощает разработку.

## Работа с пользователями

### Получение информации о пользователе

```php
global $USER, $DB;

// Текущий пользователь
$currentuser = $USER;

// Получение пользователя по ID
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);

// Получение полного имени пользователя
$fullname = fullname($user);

// Получение имени пользователя
$firstname = $user->firstname;
$lastname = $user->lastname;
```

### Получение ролей пользователя

```php
global $USER;

$context = context_system::instance();

// Получение всех ролей пользователя в системе
$roles = get_user_roles($context, $USER->id, false);

// Проверка конкретной роли
foreach ($roles as $role) {
    if ($role->shortname == 'student') {
        // Пользователь является студентом
    }
    if ($role->shortname == 'teacher') {
        // Пользователь является преподавателем
    }
}
```

### Проверка роли в курсе

```php
global $USER, $DB;

$courseid = 1;
$coursecontext = context_course::instance($courseid);

// Получение ролей пользователя в курсе
$roles = get_user_roles($coursecontext, $USER->id, false);

// Проверка наличия роли teacher (id = 3)
$isteacher = false;
foreach ($roles as $role) {
    if ($role->roleid == 3) {
        $isteacher = true;
        break;
    }
}
```

### Поиск пользователей

```php
global $DB;

// Поиск по имени
$users = $DB->get_records_sql(
    "SELECT * FROM {user} 
     WHERE deleted = 0 
     AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)
     LIMIT 50",
    ["%{$query}%", "%{$query}%", "%{$query}%"]
);

// Поиск студентов
$students = $DB->get_records_sql(
    "SELECT DISTINCT u.* 
     FROM {user} u
     INNER JOIN {role_assignments} ra ON ra.userid = u.id
     INNER JOIN {role} r ON r.id = ra.roleid
     WHERE u.deleted = 0 
     AND r.shortname = 'student'
     AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)
     LIMIT 50",
    ["%{$query}%", "%{$query}%", "%{$query}%"]
);
```

## Работа с курсами

### Получение курсов пользователя

```php
global $USER;

// Получение всех курсов, в которых зарегистрирован пользователь
$courses = enrol_get_all_users_courses($USER->id);

// Получение курсов с дополнительной информацией
$courses = enrol_get_all_users_courses($USER->id, true, ['id', 'fullname', 'shortname']);
```

### Получение информации о курсе

```php
global $DB;

$courseid = 1;

// Получение курса
$course = $DB->get_record('course', ['id' => $courseid]);

// Получение краткого названия курса
$shortname = $course->shortname;

// Получение полного названия курса
$fullname = $course->fullname;
```

### Получение модулей курса

```php
global $DB;

$courseid = 1;

// Получение всех экземпляров модулей типа "assign" (задания)
$assignments = get_all_instances_in_course('assign', $courseid, false);

// Получение всех экземпляров модулей типа "quiz" (тесты)
$quizzes = get_all_instances_in_course('quiz', $courseid, false);

// Получение всех экземпляров модулей типа "forum" (форумы)
$forums = get_all_instances_in_course('forum', $courseid, false);
```

### Проверка видимости модуля

```php
global $DB;

$courseid = 1;
$moduleid = 1;

// Получение course module
$cm = get_coursemodule_from_instance('assign', $moduleid, $courseid);

// Проверка видимости
if ($cm->visible && $cm->visibleoncoursepage) {
    // Модуль видим для студентов
}
```

## Работа с заданиями (Assignments)

### Получение заданий курса

```php
global $DB, $USER;

$courseid = 1;

// Получение всех заданий курса
$assignments = get_all_instances_in_course('assign', $courseid, false);

foreach ($assignments as $assignment) {
    // Получение course module для проверки видимости
    $cm = get_coursemodule_from_instance('assign', $assignment->id, $courseid);
    
    if (!$cm->visible || !$cm->visibleoncoursepage) {
        continue; // Пропускаем скрытые модули
    }
    
    // Получение информации о статусе выполнения
    $submission = $DB->get_record('assign_submission', [
        'assignment' => $assignment->id,
        'userid' => $USER->id,
        'status' => 'submitted'
    ]);
    
    // Получение оценки
    $grade = $DB->get_record('assign_grades', [
        'assignment' => $assignment->id,
        'userid' => $USER->id
    ]);
}
```

### Проверка статуса задания

```php
function get_assignment_status($assignmentid, $userid) {
    global $DB;
    
    // Проверка наличия отправки
    $submission = $DB->get_record('assign_submission', [
        'assignment' => $assignmentid,
        'userid' => $userid
    ]);
    
    // Проверка наличия оценки
    $grade = $DB->get_record('assign_grades', [
        'assignment' => $assignmentid,
        'userid' => $userid
    ]);
    
    if ($grade && $grade->grade !== null) {
        return 'graded'; // Оценено
    } else if ($submission && $submission->status == 'submitted') {
        return 'submitted'; // Отправлено, но не оценено
    } else {
        return 'not_submitted'; // Не отправлено
    }
}
```

## Работа с тестами (Quizzes)

### Получение тестов курса

```php
global $DB, $USER;

$courseid = 1;

// Получение всех тестов курса
$quizzes = get_all_instances_in_course('quiz', $courseid, false);

foreach ($quizzes as $quiz) {
    // Получение course module
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
    
    if (!$cm->visible || !$cm->visibleoncoursepage) {
        continue;
    }
    
    // Получение попыток пользователя
    $attempts = $DB->get_records('quiz_attempts', [
        'quiz' => $quiz->id,
        'userid' => $USER->id
    ]);
    
    // Получение последней попытки
    $lastattempt = $DB->get_record('quiz_attempts', [
        'quiz' => $quiz->id,
        'userid' => $USER->id
    ], '*', IGNORE_MULTIPLE);
}
```

### Проверка статуса теста

```php
function get_quiz_status($quizid, $userid) {
    global $DB;
    
    // Проверка наличия попытки
    $attempt = $DB->get_record('quiz_attempts', [
        'quiz' => $quizid,
        'userid' => $userid
    ], '*', IGNORE_MULTIPLE);
    
    if ($attempt) {
        // Проверка оценки через Gradebook API
        $gradeitem = grade_item::fetch([
            'courseid' => $attempt->course,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid
        ]);
        
        if ($gradeitem) {
            $grade = grade_grade::fetch([
                'itemid' => $gradeitem->id,
                'userid' => $userid
            ]);
            
            if ($grade && $grade->finalgrade !== null) {
                return 'completed'; // Завершен с оценкой
            }
        }
        
        // Проверка оценки из quiz_grades
        $quizgrade = $DB->get_record('quiz_grades', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        
        if ($quizgrade) {
            return 'completed'; // Завершен
        }
        
        return 'attempted'; // Есть попытка, но нет оценки
    }
    
    return 'not_attempted'; // Нет попыток
}
```

## Работа с оценками (Grades)

### Получение итоговой оценки курса

```php
function get_course_grade($courseid, $userid) {
    global $DB;
    
    // Получение grade item для курса
    $gradeitem = grade_item::fetch([
        'courseid' => $courseid,
        'itemtype' => 'course'
    ]);
    
    if (!$gradeitem) {
        return null;
    }
    
    // Получение оценки пользователя
    $grade = grade_grade::fetch([
        'itemid' => $gradeitem->id,
        'userid' => $userid
    ]);
    
    if ($grade && $grade->finalgrade !== null) {
        // Вычисление процента
        $percent = ($grade->finalgrade / $gradeitem->grademax) * 100;
        
        return [
            'grade' => round($grade->finalgrade, 2),
            'max' => $gradeitem->grademax,
            'min' => $gradeitem->grademin,
            'percent' => round($percent, 2)
        ];
    }
    
    return null;
}
```

### Получение оценки за модуль

```php
function get_module_grade($courseid, $moduletype, $moduleid, $userid) {
    // Получение grade item для модуля
    $gradeitem = grade_item::fetch([
        'courseid' => $courseid,
        'itemtype' => 'mod',
        'itemmodule' => $moduletype,
        'iteminstance' => $moduleid
    ]);
    
    if (!$gradeitem) {
        return null;
    }
    
    // Получение оценки пользователя
    $grade = grade_grade::fetch([
        'itemid' => $gradeitem->id,
        'userid' => $userid
    ]);
    
    if ($grade && $grade->finalgrade !== null) {
        return [
            'grade' => round($grade->finalgrade, 2),
            'max' => $gradeitem->grademax,
            'min' => $gradeitem->grademin
        ];
    }
    
    return null;
}
```

## Работа с форумами

### Получение сообщений форума

```php
global $DB, $USER;

$courseid = 1;

// Получение всех форумов курса
$forums = get_all_instances_in_course('forum', $courseid, false);

foreach ($forums as $forum) {
    // Получение обсуждений форума
    $discussions = $DB->get_records('forum_discussions', [
        'forum' => $forum->id
    ]);
    
    foreach ($discussions as $discussion) {
        // Получение сообщений обсуждения
        $posts = $DB->get_records('forum_posts', [
            'discussion' => $discussion->id
        ], 'created ASC');
    }
}
```

### Фильтрация сообщений студентов без ответов преподавателей

```php
function get_student_posts_without_teacher_reply($courseid) {
    global $DB, $USER;
    
    // Получение ID форумов курса
    $forums = get_all_instances_in_course('forum', $courseid, false);
    $forumids = array_column($forums, 'id');
    
    if (empty($forumids)) {
        return [];
    }
    
    $forumplaceholders = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'forum');
    
    // Получение ID студентов
    $students = $DB->get_records_sql(
        "SELECT DISTINCT u.id 
         FROM {user} u
         INNER JOIN {role_assignments} ra ON ra.userid = u.id
         INNER JOIN {role} r ON r.id = ra.roleid
         WHERE r.shortname = 'student'"
    );
    $studentids = array_column($students, 'id');
    $studentplaceholders = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'student');
    
    // Исключение сообщений от преподавателей
    $excludeteachers = "AND NOT EXISTS (
        SELECT 1 FROM {role_assignments} ra3
        JOIN {context} ctx3 ON ctx3.id = ra3.contextid
        WHERE ra3.userid = p.userid
        AND ra3.roleid = 3
        AND (ctx3.contextlevel = 50 OR ctx3.id = ?)
    )";
    
    // Исключение сообщений, на которые ответил преподаватель
    $noanswerfromteacher = "AND NOT EXISTS (
        SELECT 1 FROM {forum_posts} p2
        JOIN {role_assignments} ra2 ON ra2.userid = p2.userid
        JOIN {context} ctx2 ON ctx2.id = ra2.contextid
        WHERE p2.discussion = p.discussion
        AND p2.created > p.created
        AND ra2.roleid = 3
        AND (ctx2.contextlevel = 50 OR ctx2.id = ?)
    )";
    
    // SQL запрос
    $sql = "SELECT p.id, p.discussion, p.userid, p.subject, p.message, p.created, d.name as discussionname
            FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            WHERE d.forum {$forumplaceholders[0]}
            AND p.userid {$studentplaceholders[0]}
            {$excludeteachers}
            {$noanswerfromteacher}
            ORDER BY p.created DESC
            LIMIT 1000";
    
    $params = array_merge(
        $forumplaceholders[1],
        $studentplaceholders[1],
        [$courseid, $courseid]
    );
    
    return $DB->get_records_sql($sql, $params);
}
```

## Работа с когортами (Cohorts)

### Получение когорт пользователя

```php
global $DB, $USER;

// Получение всех когорт пользователя
$cohorts = $DB->get_records_sql(
    "SELECT c.* 
     FROM {cohort} c
     INNER JOIN {cohort_members} cm ON cm.cohortid = c.id
     WHERE cm.userid = ?",
    [$USER->id]
);

// Получение названий когорт
$cohortnames = [];
foreach ($cohorts as $cohort) {
    $cohortnames[] = $cohort->name;
}
```

## Безопасность и производительность

### Использование параметров в запросах

```php
// ✅ Правильно - использование параметров
$users = $DB->get_records_sql(
    "SELECT * FROM {user} WHERE firstname LIKE ?",
    ["%{$query}%"]
);

// ❌ Неправильно - прямая подстановка
$users = $DB->get_records_sql(
    "SELECT * FROM {user} WHERE firstname LIKE '%{$query}%'"
);
```

### Использование get_in_or_equal для массивов

```php
// ✅ Правильно
$ids = [1, 2, 3, 4, 5];
list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'param');
$records = $DB->get_records_sql("SELECT * FROM {table} WHERE id {$insql}", $inparams);

// ❌ Неправильно
$ids = [1, 2, 3, 4, 5];
$idsstring = implode(',', $ids);
$records = $DB->get_records_sql("SELECT * FROM {table} WHERE id IN ({$idsstring})");
```

### Кэширование результатов

```php
global $DB;

// Использование кэша для часто запрашиваемых данных
$cache = cache::make('local_deanpromoodle', 'courses');
$cachekey = 'user_courses_' . $USER->id;

$courses = $cache->get($cachekey);
if ($courses === false) {
    $courses = enrol_get_all_users_courses($USER->id);
    $cache->set($cachekey, $courses);
}
```

## Следующие шаги

После изучения работы с API Moodle переходите к:
- [Этап 7: Стилизация и UI](07-Стилизация-и-UI.md) — улучшение внешнего вида
- [Этап 8: Тестирование и отладка](08-Тестирование-и-отладка.md) — тестирование плагина

## Полезные ссылки

- [Moodle Database API](https://docs.moodle.org/dev/Data_manipulation_API)
- [Moodle Gradebook API](https://docs.moodle.org/dev/Gradebook_API)
- [Moodle Course API](https://docs.moodle.org/dev/Course_API)
- [Moodle User API](https://docs.moodle.org/dev/User_API)
