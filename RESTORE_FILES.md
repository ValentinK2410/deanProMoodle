# Инструкция по восстановлению удаленных файлов плагина deanpromoodle

## Способ 1: Восстановление из Git репозитория (РЕКОМЕНДУЕТСЯ)

Если файлы были закоммичены в Git, выполните на сервере Ubuntu:

```bash
# 1. Перейдите в каталог local
cd /var/www/www-root/data/www/class.russianseminary.org/local

# 2. Клонируйте репозиторий во временный каталог
git clone https://github.com/ValentinK2410/deanProMoodle.git /tmp/deanpromoodle-restore

# 3. Переместите плагин в нужное место
mv /tmp/deanpromoodle-restore/local/deanpromoodle .

# 4. Установите права доступа
sudo chown -R www-data:www-data deanpromoodle
sudo chmod -R 755 deanpromoodle

# 5. Очистите временные файлы
rm -rf /tmp/deanpromoodle-restore

# 6. Очистите кеш Moodle
sudo rm -rf /var/www/www-root/data/www/class.russianseminary.org/moodledata/cache/*
```

## Способ 2: Проверка истории команд (если удаление было недавно)

Если файлы были удалены недавно, можно проверить историю команд:

```bash
# Просмотр истории команд bash
history | grep -i "rm\|delete\|remove"

# Или просмотр истории zsh
history | grep -i "rm\|delete\|remove"
```

## Способ 3: Восстановление из корзины/trash

Если файлы были удалены через файловый менеджер:

```bash
# Проверка корзины пользователя
ls -la ~/.local/share/Trash/files/

# Восстановление из корзины (если файлы там есть)
mv ~/.local/share/Trash/files/deanpromoodle /var/www/www-root/data/www/class.russianseminary.org/local/
```

## Способ 4: Восстановление из резервных копий

Если у вас есть резервные копии:

```bash
# Если есть резервная копия в другом месте
cp -r /path/to/backup/deanpromoodle /var/www/www-root/data/www/class.russianseminary.org/local/

# Установите права доступа
sudo chown -R www-data:www-data /var/www/www-root/data/www/class.russianseminary.org/local/deanpromoodle
sudo chmod -R 755 /var/www/www-root/data/www/class.russianseminary.org/local/deanpromoodle
```

## Способ 5: Восстановление через Git (если каталог был Git репозиторием)

Если каталог `/var/www/www-root/data/www/class.russianseminary.org/local/deanpromoodle` был Git репозиторием:

```bash
cd /var/www/www-root/data/www/class.russianseminary.org/local

# Попытка восстановить через git
git clone https://github.com/ValentinK2410/deanProMoodle.git temp-repo
mv temp-repo/local/deanpromoodle .
rm -rf temp-repo
```

## После восстановления файлов:

1. **Обновите базу данных Moodle:**
   - Войдите в Moodle как администратор
   - Перейдите в: Настройки сайта → Уведомления
   - Нажмите "Обновить базу данных Moodle" (Upgrade Moodle database now)

2. **Проверьте права доступа:**
   ```bash
   sudo chown -R www-data:www-data /var/www/www-root/data/www/class.russianseminary.org/local/deanpromoodle
   sudo chmod -R 755 /var/www/www-root/data/www/class.russianseminary.org/local/deanpromoodle
   ```

3. **Очистите кеш Moodle:**
   ```bash
   sudo rm -rf /var/www/www-root/data/www/class.russianseminary.org/moodledata/cache/*
   ```

## Если ничего не помогло:

Если файлы не удается восстановить, можно скопировать их с локальной машины (если у вас есть актуальная версия):

```bash
# На локальной машине (Mac/Windows):
# Создайте архив плагина
cd /Users/valentink2410/PhpstormProjects/deanProMoodle
tar -czf deanpromoodle-backup.tar.gz local/deanpromoodle

# Затем на сервере Ubuntu:
# Загрузите архив на сервер (через scp, sftp или другой способ)
# Распакуйте:
cd /var/www/www-root/data/www/class.russianseminary.org/local
tar -xzf deanpromoodle-backup.tar.gz
mv local/deanpromoodle .
rm -rf local
rm deanpromoodle-backup.tar.gz

# Установите права доступа
sudo chown -R www-data:www-data deanpromoodle
sudo chmod -R 755 deanpromoodle
```
