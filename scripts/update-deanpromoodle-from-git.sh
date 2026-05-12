#!/usr/bin/env bash
#
# Обновление плагина local_deanpromoodle на сервере из Git.
# Подходит для shared-хостинга: clone с pack.threads=1, затем rsync в каталог Moodle.
#
# Использование:
#   1. Скопируйте скрипт на сервер (или клонируйте репозиторий и запускайте оттуда).
#   2. Отредактируйте переменные в блоке «НАСТРОЙКИ» ниже.
#   3. chmod +x scripts/update-deanpromoodle-from-git.sh
#   4. Запуск из корня репозитория или с полным путём к скрипту.
#   При каждом обновлении в $DEANPRO_SCRIPTS_DIR кладётся
#   patch-moodle-environment-mysql80-temporary.sh (по умолчанию ~/class.mbs.ru/deanpromoodle-scripts).
#
set -eu

# --- НАСТРОЙКИ (измените под свой сервер) ---
REPO_URL="${REPO_URL:-https://github.com/ValentinK2410/deanProMoodle.git}"
BRANCH="${BRANCH:-main}"

# Корень установки Moodle (каталог, внутри которого есть local/)
MOODLE_ROOT="${MOODLE_ROOT:-$HOME/class.mbs.ru/public_html}"

# Куда ставится плагин (обычно не менять)
PLUGIN_REL="local/deanpromoodle"
PLUGIN_DST="${PLUGIN_DST:-$MOODLE_ROOT/$PLUGIN_REL}"

# Временный каталог для clone (не в /tmp на слабом хостинге — лучше $HOME/tmp)
WORKDIR="${WORKDIR:-$HOME/tmp/deanpromoodle-git-update}"

# Очистка файлового кеша Moodle (необязательно): CLEAR_MOODLE_CACHE=1 ./script.sh
MOODLEDATA_CACHE="${MOODLEDATA_CACHE:-$HOME/class.mbs.ru/moodledata/cache}"
CLEAR_MOODLE_CACHE="${CLEAR_MOODLE_CACHE:-0}"

# Куда складывать сервисные скрипты репозитория (рядом с сайтом, не в public_html).
# Патч MySQL окружения: MOODLE_ROOT=… bash …/patch-moodle-environment-mysql80-temporary.sh
DEANPRO_SCRIPTS_DIR="${DEANPRO_SCRIPTS_DIR:-$(dirname "$MOODLE_ROOT")/deanpromoodle-scripts}"
# -------------------------------------------

stamp() { date '+%Y-%m-%d %H:%M:%S'; }

echo "$(stamp) Обновление плагина из Git"
echo "  REPO:    $REPO_URL"
echo "  BRANCH:  $BRANCH"
echo "  WORKDIR: $WORKDIR"
echo "  DEST:    $PLUGIN_DST"
echo "  SCRIPTS: $DEANPRO_SCRIPTS_DIR"

if [[ ! -d "$(dirname "$PLUGIN_DST")" ]]; then
  echo "$(stamp) Ошибка: нет родительского каталога для плагина: $(dirname "$PLUGIN_DST")"
  exit 1
fi

rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"
cd "$WORKDIR"

export GIT_OPTIONAL_LOCKS=0
echo "$(stamp) git clone (--depth 1) …"
git -c pack.threads=1 \
  -c pack.deltaCacheSize=128m \
  clone --depth 1 --single-branch -b "$BRANCH" "$REPO_URL" repo

SRC="$WORKDIR/repo/$PLUGIN_REL"
if [[ ! -f "$SRC/version.php" ]]; then
  echo "$(stamp) Ошибка: после clone нет файла $SRC/version.php"
  exit 1
fi

mkdir -p "$PLUGIN_DST"
echo "$(stamp) rsync → $PLUGIN_DST"
rsync -a --delete "$SRC/" "$PLUGIN_DST/"

if [[ -f "$PLUGIN_DST/version.php" ]]; then
  echo "$(stamp) OK: $PLUGIN_DST/version.php на месте"
else
  echo "$(stamp) Ошибка: version.php не найден в месте установки"
  exit 1
fi

PATCH_SCRIPT_SRC="$WORKDIR/repo/scripts/patch-moodle-environment-mysql80-temporary.sh"
if [[ -f "$PATCH_SCRIPT_SRC" ]]; then
  mkdir -p "$DEANPRO_SCRIPTS_DIR"
  cp -f "$PATCH_SCRIPT_SRC" "$DEANPRO_SCRIPTS_DIR/"
  chmod 0755 "$DEANPRO_SCRIPTS_DIR/patch-moodle-environment-mysql80-temporary.sh"
  echo "$(stamp) Скрипт патча окружения: $DEANPRO_SCRIPTS_DIR/patch-moodle-environment-mysql80-temporary.sh"
  echo "           Запуск: MOODLE_ROOT=\"$MOODLE_ROOT\" bash \"$DEANPRO_SCRIPTS_DIR/patch-moodle-environment-mysql80-temporary.sh\""
else
  echo "$(stamp) Предупреждение: в репозитории нет scripts/patch-moodle-environment-mysql80-temporary.sh"
fi

if [[ "${CLEAR_MOODLE_CACHE:-0}" == "1" ]]; then
  if [[ -d "$MOODLEDATA_CACHE" ]]; then
    echo "$(stamp) Очистка кеша: $MOODLEDATA_CACHE"
    rm -rf "${MOODLEDATA_CACHE:?}/"*
  else
    echo "$(stamp) Предупреждение: каталог кеша не найден: $MOODLEDATA_CACHE"
  fi
fi

rm -rf "$WORKDIR"
echo "$(stamp) Готово. В админке Moodle: «Уведомления» (при необходимости обновление БД) и «Очистить кэш»."
