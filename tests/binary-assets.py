"""Verify Git preserves WebP payloads byte for byte; no database or object writes."""
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parent.parent
assets = sorted((root / 'static').rglob('*.webp'))
if not assets:
    raise SystemExit('No WebP assets found for binary regression checks.')

for asset in assets:
    relative = asset.relative_to(root).as_posix()
    payload = asset.read_bytes()
    git = ['git', '-c', f'safe.directory={root}', 'hash-object']
    # Exercise the actual clean filter used by git add, without writing to the index.
    filtered = subprocess.check_output(git + [f'--path={relative}', '--stdin'], input=payload, cwd=root)
    raw = subprocess.check_output(git + ['--no-filters', '--stdin'], input=payload, cwd=root)
    if filtered != raw:
        raise SystemExit(f'Git modifies binary asset bytes: {relative}')

print(f'PASS: {len(assets)} WebP binary Git filter checks')
