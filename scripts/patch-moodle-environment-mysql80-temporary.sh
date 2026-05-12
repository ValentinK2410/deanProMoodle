#!/usr/bin/env bash
#
# Временное ослабление проверки окружения Moodle 5.x: заменить в matrix для
# Moodle 5.0 требование MySQL 8.4 на 8.0, если на сервере стоит 8.0.x
# (официально не поддерживается; правка затрётся обновлением ядра).
#
# Применить:
#   MOODLE_ROOT=/path/to/moodle bash scripts/patch-moodle-environment-mysql80-temporary.sh
#
# Откатить только этот патч в файле:
#   MOODLE_ROOT=/path/to/moodle bash scripts/patch-moodle-environment-mysql80-temporary.sh --revert
#
# Если на сервере ещё нет этого файла (каталог deanpromoodle-scripts не создавали):
#   обновите плагин через scripts/update-deanpromoodle-from-git.sh из ветки main, либо один раз:
#   mkdir -p ~/class.mbs.ru/deanpromoodle-scripts
#   curl -fsSL -o ~/class.mbs.ru/deanpromoodle-scripts/patch-moodle-environment-mysql80-temporary.sh \\
#     'https://raw.githubusercontent.com/ValentinK2410/deanProMoodle/main/scripts/patch-moodle-environment-mysql80-temporary.sh'
#   chmod +x ~/class.mbs.ru/deanpromoodle-scripts/patch-moodle-environment-mysql80-temporary.sh
#
set -eu

MOODLE_ROOT="${MOODLE_ROOT:?Задайте MOODLE_ROOT — корень установки Moodle (каталог с admin/environment.xml)}"
ENV_XML="${ENV_XML:-$MOODLE_ROOT/admin/environment.xml}"
ACTION="${1:-apply}"

if [[ ! -f "$ENV_XML" ]]; then
	echo "Не найден файл: $ENV_XML" >&2
	exit 1
fi

bak="${ENV_XML}.bak.mysql80patch.$(date +%Y%m%d%H%M%S)"
cp -a "$ENV_XML" "$bak"
echo "Резервная копия: $bak"

python3 - "$ENV_XML" "$ACTION" <<'PY'
import re
import sys

path, action = sys.argv[1], sys.argv[2]

text = open(path, encoding="utf-8").read()
m = re.search(r'(<MOODLE version="5\.0"[^>]*>.*?</MOODLE>)', text, re.DOTALL)
if not m:
	sys.exit("В environment.xml не найден блок <MOODLE version=\"5.0\" ...>")

block = m.group(1)
if action == "apply":
	new_block, n = re.subn(
		r'(<VENDOR name="mysql" version=")8\.4(" />)',
		r"\g<1>8.0\g<2>",
		block,
		count=1,
	)
	if n != 1:
		sys.exit(
			"Не удалось применить патч: в блоке 5.0 нет ровно одного "
			'<VENDOR name="mysql" version="8.4" />. Проверьте версию ядра Moodle.'
		)
elif action == "--revert":
	new_block, n = re.subn(
		r'(<VENDOR name="mysql" version=")8\.0(" />)',
		r"\g<1>8.4\g<2>",
		block,
		count=1,
	)
	if n != 1:
		sys.exit(
			"Не удалось откатить: в блоке 5.0 нет ровно одного "
			'<VENDOR name="mysql" version="8.0" />.'
		)
else:
	sys.exit("Неизвестное действие. Используйте без аргументов (apply) или --revert")

out = text[: m.start()] + new_block + text[m.end() :]
open(path, "w", encoding="utf-8").write(out)
print("OK:", path, action if action == "--revert" else "apply")
PY

echo "Готово. Очистите кеш Moodle (админка → разработка) или moodledata/cache при необходимости."
