# Dean Pro Moodle Plugin

Moodle local plugin для системы управления образованием (education-management-system).

## Описание

Плагин предоставляет отдельные страницы для различных ролей пользователей:
- Страница для студентов
- Страница для преподавателей
- Страница для администраторов

## Установка

1. Скопируйте каталог `deanpromoodle` в директорию `/local/` вашего Moodle проекта:
   ```
   cp -r deanpromoodle /path/to/moodle/local/
   ```

2. Войдите в Moodle как администратор

3. Перейдите в раздел "Уведомления" (Notifications) - Moodle автоматически обнаружит новый плагин

4. Нажмите "Upgrade Moodle database now" для установки плагина

5. После установки плагин будет доступен по пути:
   - `yourmoodle.com/local/deanpromoodle/pages/student.php`
   - `yourmoodle.com/local/deanpromoodle/pages/teacher.php`
   - `yourmoodle.com/local/deanpromoodle/pages/admin.php`

## Структура плагина

```
local/deanpromoodle/
├── version.php                    # Метаданные плагина
├── access.php                     # Определение capabilities
├── lib.php                        # Функции навигации
├── lang/
│   └── en/
│       └── local_deanpromoodle.php  # Языковые строки
├── pages/
│   ├── student.php                # Страница студента
│   ├── teacher.php                # Страница преподавателя
│   └── admin.php                  # Страница администратора
└── README.md                      # Этот файл
```

## Права доступа (Capabilities)

Плагин определяет следующие capabilities:

- `local/deanpromoodle:viewstudent` - доступ к странице студента (назначается роли Student)
- `local/deanpromoodle:viewteacher` - доступ к странице преподавателя (назначается ролям Teacher, Editing teacher, Manager)
- `local/deanpromoodle:viewadmin` - доступ к странице администратора (назначается роли Manager)

## Навигация

После установки плагина пункты меню автоматически добавляются в основную навигацию Moodle для пользователей с соответствующими правами доступа.

## Требования

- Moodle 4.0 или выше
- PHP 7.4 или выше

## Разработка

Плагин создан как базовая структура для дальнейшего расширения функционала. Страницы содержат базовый контент-заглушку, который можно расширить по мере необходимости.

## Лицензия

GPL v3 или выше
