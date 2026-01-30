#!/bin/bash
# Скрипт для восстановления удаленных файлов плагина deanpromoodle из Git

# Переходим в каталог local
cd /var/www/www-root/data/www/class.russianseminary.org/local

# Проверяем, существует ли каталог deanpromoodle
if [ -d "deanpromoodle" ]; then
    echo "Каталог deanpromoodle уже существует. Удаляем старую версию..."
    rm -rf deanpromoodle
fi

# Клонируем репозиторий во временный каталог
echo "Клонируем репозиторий из Git..."
git clone https://github.com/ValentinK2410/deanProMoodle.git /tmp/deanpromoodle-restore

# Перемещаем каталог плагина в нужное место
echo "Перемещаем плагин в нужное место..."
mv /tmp/deanpromoodle-restore/local/deanpromoodle .

# Устанавливаем правильные права доступа
echo "Устанавливаем права доступа..."
chown -R www-data:www-data deanpromoodle
chmod -R 755 deanpromoodle

# Очищаем временный каталог
echo "Очищаем временные файлы..."
rm -rf /tmp/deanpromoodle-restore

# Очищаем кеш Moodle
echo "Очищаем кеш Moodle..."
rm -rf /var/www/www-root/data/www/class.russianseminary.org/moodledata/cache/*

echo "Восстановление завершено!"
echo "Плагин восстановлен из Git репозитория."
echo "Не забудьте обновить базу данных Moodle через админ-панель (Настройки сайта → Уведомления → Обновить)."
