<?php
/**
 * Временная тестовая страница для диагностики проблем с добавлением внешних зачетов
 * 
 * @package    local_deanpromoodle
 * @copyright  2026
 * @author     ValentinK2410
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

// Определяем путь к конфигурации Moodle
$configpath = __DIR__ . '/../../../config.php';
if (!file_exists($configpath)) {
    die('Error: Moodle config.php not found at: ' . $configpath);
}

require_once($configpath);
require_login();

// Проверяем права администратора
$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    die('Доступ запрещен. Только администраторы могут использовать эту страницу.');
}

// Включаем режим отладки для детального вывода ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

global $DB, $USER;

// Получаем параметры
$studentid = optional_param('studentid', 0, PARAM_INT);
$action = optional_param('action', 'test', PARAM_ALPHA);

// Настройка страницы
$PAGE->set_url(new moodle_url('/local/deanpromoodle/pages/test_external_credits.php', [
    'studentid' => $studentid,
    'action' => $action
]));
$PAGE->set_context($context);
$PAGE->set_title('Тест добавления внешних зачетов');
$PAGE->set_heading('Тест добавления внешних зачетов');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo html_writer::tag('h1', 'Тестовая страница для диагностики внешних зачетов');
echo html_writer::tag('p', 'Эта страница предназначена для диагностики проблем с добавлением внешних зачетов.');

// Проверяем существование таблицы
echo html_writer::tag('h2', '1. Проверка существования таблицы');
if ($DB->get_manager()->table_exists('local_deanpromoodle_student_external_credits')) {
    echo html_writer::div('✓ Таблица local_deanpromoodle_student_external_credits существует', 'alert alert-success');
    
    // Показываем структуру таблицы
    $table = new xmldb_table('local_deanpromoodle_student_external_credits');
    $dbman = $DB->get_manager();
    if ($dbman->table_exists($table)) {
        echo html_writer::tag('h3', 'Структура таблицы:');
        echo html_writer::start_tag('pre');
        echo htmlspecialchars(print_r($DB->get_columns('local_deanpromoodle_student_external_credits'), true), ENT_QUOTES, 'UTF-8');
        echo html_writer::end_tag('pre');
    }
} else {
    echo html_writer::div('✗ Таблица local_deanpromoodle_student_external_credits НЕ существует!', 'alert alert-danger');
    echo $OUTPUT->footer();
    die();
}

// Проверяем студента
if ($studentid > 0) {
    echo html_writer::tag('h2', '2. Проверка студента');
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0]);
    if ($student) {
        echo html_writer::div('✓ Студент найден: ' . fullname($student) . ' (ID: ' . $student->id . ')', 'alert alert-success');
    } else {
        echo html_writer::div('✗ Студент с ID ' . $studentid . ' не найден!', 'alert alert-danger');
        echo $OUTPUT->footer();
        die();
    }
} else {
    echo html_writer::div('⚠ Параметр studentid не указан. Используйте: ?studentid=54', 'alert alert-warning');
}

// Форма для тестирования
if ($action == 'test' && $studentid > 0) {
    echo html_writer::tag('h2', '3. Форма для тестирования');
    
    // Получаем список предметов
    $subjects = $DB->get_records('local_deanpromoodle_subjects', ['visible' => 1], 'name ASC');
    
    echo html_writer::start_tag('form', [
        'method' => 'POST',
        'action' => new moodle_url('/local/deanpromoodle/pages/test_external_credits.php', [
            'studentid' => $studentid,
            'action' => 'insert'
        ])
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    
    echo html_writer::start_tag('table', ['class' => 'table']);
    
    // Предмет
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Предмет:', 'subjectid'));
    echo html_writer::start_tag('td');
    echo html_writer::start_tag('select', ['name' => 'subjectid', 'id' => 'subjectid', 'required' => true]);
    echo html_writer::tag('option', 'Выберите предмет', ['value' => '']);
    foreach ($subjects as $subject) {
        echo html_writer::tag('option', $subject->name, ['value' => $subject->id]);
    }
    echo html_writer::end_tag('select');
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    
    // Название учебного заведения
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Название учебного заведения:', 'institution_name'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'institution_name',
        'id' => 'institution_name',
        'required' => true,
        'value' => 'Тестовое учебное заведение'
    ]));
    echo html_writer::end_tag('tr');
    
    // Оценка
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Оценка:', 'grade'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'grade',
        'id' => 'grade',
        'value' => '5'
    ]));
    echo html_writer::end_tag('tr');
    
    // Оценка в процентах
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Оценка в процентах:', 'grade_percent'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => 'grade_percent',
        'id' => 'grade_percent',
        'step' => '0.01',
        'min' => '0',
        'max' => '100',
        'value' => '95'
    ]));
    echo html_writer::end_tag('tr');
    
    // Дата зачета
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Дата зачета:', 'credited_date'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'date',
        'name' => 'credited_date',
        'id' => 'credited_date',
        'value' => date('Y-m-d')
    ]));
    echo html_writer::end_tag('tr');
    
    // Номер документа
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Номер документа:', 'document_number'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'document_number',
        'id' => 'document_number',
        'value' => 'TEST-' . time()
    ]));
    echo html_writer::end_tag('tr');
    
    // Примечания
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::label('Примечания:', 'notes'));
    echo html_writer::tag('td', html_writer::tag('textarea', 'Тестовая запись', [
        'name' => 'notes',
        'id' => 'notes',
        'rows' => '3',
        'cols' => '50'
    ]));
    echo html_writer::end_tag('tr');
    
    echo html_writer::end_tag('table');
    
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => 'Добавить тестовый внешний зачет',
        'class' => 'btn btn-primary'
    ]);
    
    echo html_writer::end_tag('form');
}

// Обработка вставки
if ($action == 'insert' && $studentid > 0) {
    require_sesskey();
    
    echo html_writer::tag('h2', '4. Попытка добавления записи');
    
    // Получаем данные формы
    $subjectid = optional_param('subjectid', 0, PARAM_INT);
    $grade = optional_param('grade', '', PARAM_TEXT);
    $grade_percent = optional_param('grade_percent', null, PARAM_FLOAT);
    $institution_name = optional_param('institution_name', '', PARAM_TEXT);
    $credited_date_str = optional_param('credited_date', '', PARAM_TEXT);
    $document_number = optional_param('document_number', '', PARAM_TEXT);
    $notes = optional_param('notes', '', PARAM_TEXT);
    
    // Преобразуем дату
    $credited_date = 0;
    if (!empty($credited_date_str)) {
        $credited_date = strtotime($credited_date_str . ' 00:00:00');
        if ($credited_date === false) {
            $credited_date = time();
        }
    } else {
        $credited_date = time();
    }
    
    // Проверяем студента
    $student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0]);
    if (!$student) {
        echo html_writer::div('Ошибка: студент не найден', 'alert alert-danger');
        echo $OUTPUT->footer();
        die();
    }
    
    // Проверяем предмет
    $subject = $DB->get_record('local_deanpromoodle_subjects', ['id' => $subjectid]);
    if (!$subject) {
        echo html_writer::div('Ошибка: предмет не найден', 'alert alert-danger');
        echo $OUTPUT->footer();
        die();
    }
    
    // Формируем запись
    $record = new stdClass();
    $record->studentid = (int)$studentid;
    $record->subjectid = (int)$subjectid;
    $record->grade = (!empty($grade) && is_string($grade)) ? trim($grade) : null;
    $record->grade_percent = ($grade_percent !== null && $grade_percent !== '') ? (float)$grade_percent : null;
    $record->institution_name = (!empty($institution_name) && is_string($institution_name)) ? trim($institution_name) : null;
    // institution_id не устанавливаем, если не указан
    $record->credited_date = ($credited_date > 0) ? (int)$credited_date : time();
    $record->document_number = (!empty($document_number) && is_string($document_number)) ? trim($document_number) : null;
    $record->notes = (!empty($notes) && is_string($notes)) ? trim($notes) : null;
    $record->createdby = (int)$USER->id;
    $record->timecreated = time();
    $record->timemodified = time();
    
    // Выводим данные записи
    echo html_writer::tag('h3', 'Данные записи перед вставкой:');
    echo html_writer::start_tag('pre', ['style' => 'background: #f5f5f5; padding: 10px; border: 1px solid #ddd;']);
    echo htmlspecialchars(print_r($record, true), ENT_QUOTES, 'UTF-8');
    echo html_writer::end_tag('pre');
    
    // Проверяем обязательные поля
    echo html_writer::tag('h3', 'Проверка обязательных полей:');
    $errors = [];
    if (empty($record->studentid)) {
        $errors[] = 'studentid пустой';
    }
    if (empty($record->subjectid)) {
        $errors[] = 'subjectid пустой';
    }
    if (empty($record->createdby)) {
        $errors[] = 'createdby пустой';
    }
    if (empty($record->institution_name)) {
        $errors[] = 'institution_name пустой';
    }
    if (empty($record->timecreated)) {
        $errors[] = 'timecreated пустой';
    }
    if (empty($record->timemodified)) {
        $errors[] = 'timemodified пустой';
    }
    
    if (!empty($errors)) {
        echo html_writer::div('Ошибки валидации: ' . implode(', ', $errors), 'alert alert-danger');
    } else {
        echo html_writer::div('✓ Все обязательные поля заполнены', 'alert alert-success');
    }
    
    // Проверяем существование записи
    echo html_writer::tag('h3', 'Проверка на дубликаты:');
    $existing = $DB->get_record('local_deanpromoodle_student_external_credits', [
        'studentid' => $record->studentid,
        'subjectid' => $record->subjectid
    ]);
    if ($existing) {
        echo html_writer::div('⚠ Внешний зачет по этому предмету уже существует для данного студента (ID: ' . $existing->id . ')', 'alert alert-warning');
    } else {
        echo html_writer::div('✓ Дубликатов не найдено', 'alert alert-success');
    }
    
    // Пытаемся вставить запись
    echo html_writer::tag('h3', 'Попытка вставки записи:');
    try {
        $insertid = $DB->insert_record('local_deanpromoodle_student_external_credits', $record);
        
        if ($insertid) {
            echo html_writer::div('✓ Запись успешно добавлена! ID новой записи: ' . $insertid, 'alert alert-success');
            
            // Показываем добавленную запись
            $inserted = $DB->get_record('local_deanpromoodle_student_external_credits', ['id' => $insertid]);
            echo html_writer::tag('h4', 'Добавленная запись:');
            echo html_writer::start_tag('pre', ['style' => 'background: #e8f5e9; padding: 10px; border: 1px solid #4caf50;']);
            echo htmlspecialchars(print_r($inserted, true), ENT_QUOTES, 'UTF-8');
            echo html_writer::end_tag('pre');
        } else {
            echo html_writer::div('✗ insert_record вернул false. Запись не была добавлена.', 'alert alert-danger');
        }
    } catch (\dml_exception $e) {
        echo html_writer::div('✗ Исключение dml_exception при вставке', 'alert alert-danger');
        
        echo html_writer::tag('h4', 'Детали исключения:');
        echo html_writer::start_tag('div', ['style' => 'background: #ffebee; padding: 15px; border: 1px solid #f44336; margin: 10px 0;']);
        
        echo html_writer::tag('p', '<strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Код ошибки:</strong> ' . htmlspecialchars($e->getCode(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Файл:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Строка:</strong> ' . htmlspecialchars($e->getLine(), ENT_QUOTES, 'UTF-8'));
        
        if (method_exists($e, 'debuginfo')) {
            $debuginfo = $e->debuginfo();
            if (!empty($debuginfo)) {
                echo html_writer::tag('p', '<strong>Debug info:</strong>');
                echo html_writer::start_tag('pre', ['style' => 'background: white; padding: 10px; overflow: auto;']);
                echo htmlspecialchars(print_r($debuginfo, true), ENT_QUOTES, 'UTF-8');
                echo html_writer::end_tag('pre');
            }
        }
        
        echo html_writer::tag('p', '<strong>Trace:</strong>');
        echo html_writer::start_tag('pre', ['style' => 'background: white; padding: 10px; overflow: auto; max-height: 300px;']);
        echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo html_writer::end_tag('pre');
        
        echo html_writer::end_tag('div');
    } catch (\Exception $e) {
        echo html_writer::div('✗ Общее исключение при вставке', 'alert alert-danger');
        
        echo html_writer::tag('h4', 'Детали исключения:');
        echo html_writer::start_tag('div', ['style' => 'background: #ffebee; padding: 15px; border: 1px solid #f44336; margin: 10px 0;']);
        
        echo html_writer::tag('p', '<strong>Тип:</strong> ' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Код ошибки:</strong> ' . htmlspecialchars($e->getCode(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Файл:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'));
        echo html_writer::tag('p', '<strong>Строка:</strong> ' . htmlspecialchars($e->getLine(), ENT_QUOTES, 'UTF-8'));
        
        echo html_writer::tag('p', '<strong>Trace:</strong>');
        echo html_writer::start_tag('pre', ['style' => 'background: white; padding: 10px; overflow: auto; max-height: 300px;']);
        echo htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo html_writer::end_tag('pre');
        
        echo html_writer::end_tag('div');
    }
}

echo html_writer::tag('hr', '');
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/local/deanpromoodle/pages/test_external_credits.php', ['studentid' => $studentid]),
    '← Вернуться к форме'
));

echo $OUTPUT->footer();
