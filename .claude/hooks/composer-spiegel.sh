#!/bin/bash
# Erzeugt composer.local.json/.lock für Container, deren Netzrichtlinie api.github.com sperrt
# (Cloud-Sitzung, gemessen 01.10.2026: CONNECT 403, codeload.github.com ebenso). Die Dist-Archive
# aller Pakete aus composer.lock werden dann vom Packagist-Spiegel mirrors.cloud.tencent.com geladen,
# der jedes Archiv unter {vendor}/{paket}/{version}/{vendor}-{paket}-{version}.zip vorhält (geprüft
# für laravel/framework, phpstan/phpstan, symfony/finder). Versionen und Commit-Referenzen bleiben
# exakt die der composer.lock; nur die URL wechselt. Aufruf:
#   bash .claude/hooks/composer-spiegel.sh && COMPOSER=composer.local.json composer install --prefer-dist
# Die beiden Dateien sind in .gitignore und gehören nie ins Repository.
set -euo pipefail
cd "$(dirname "$0")/../.."
SPIEGEL="${NP_COMPOSER_SPIEGEL:-https://mirrors.cloud.tencent.com/repository/composer}"
cp composer.json composer.local.json
SPIEGEL="$SPIEGEL" python3 - <<'PY'
import json, os
spiegel = os.environ['SPIEGEL'].rstrip('/')
lock = json.load(open('composer.lock'))
n = 0
for p in lock['packages'] + lock['packages-dev']:
    dist = p.get('dist')
    if not dist or 'github.com' not in dist.get('url', ''):
        continue
    vendor, name = p['name'].split('/')
    dist['url'] = f"{spiegel}/{vendor}/{name}/{p['version']}/{vendor}-{name}-{p['version']}.{dist.get('type', 'zip')}"
    n += 1
json.dump(lock, open('composer.local.lock', 'w'), indent=4, ensure_ascii=False)
open('composer.local.lock', 'a').write('\n')
print(f"composer.local.lock: {n} Dist-URLs auf {spiegel} umgeschrieben")
PY
