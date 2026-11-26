#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 - "${1:-}" <<'PY'
import json
import pathlib
import re
import sys
import zipfile

root = pathlib.Path.cwd()
dest = root / 'theme/aimarket/inc/bundled'
check = sys.argv[1] == '--check'
if sys.argv[1] not in ('', '--check'):
    raise SystemExit('usage: bin/build-bundled.sh [--check]')
versions = {}
errors = []
for name in ('core', 'accounts', 'reseller'):
    plugin = root / 'plugins' / f'aimarket-{name}'
    version = re.search(r'^\s*\* Version:\s*(\S+)', (plugin / f'aimarket-{name}.php').read_text(), re.M).group(1)
    versions[name] = version
    files = {p.relative_to(plugin.parent).as_posix(): p for p in sorted(plugin.rglob('*')) if p.is_file() and not any(part.startswith('.') or part in ('vendor', 'node_modules') for part in p.relative_to(plugin).parts)}
    archive = dest / f'aimarket-{name}.zip'
    if check:
        with zipfile.ZipFile(archive) as z:
            actual = {n for n in z.namelist() if not n.endswith('/')}
            if actual != set(files) or any(z.read(n) != p.read_bytes() for n, p in files.items() if n in actual):
                errors.append(archive.name)
    else:
        with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
            for n, p in files.items():
                z.writestr(zipfile.ZipInfo(n, (1980, 1, 1, 0, 0, 0)), p.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
manifest = dest / 'versions.json'
if check:
    if json.loads(manifest.read_text()) != versions:
        errors.append('versions.json')
    if errors:
        raise SystemExit('Mismatch: ' + ', '.join(errors))
    print('All bundled archives and versions match plugin sources.')
else:
    manifest.write_text(json.dumps(versions, indent=1) + '\n')
PY
