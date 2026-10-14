#!/usr/bin/env python3
"""Report Tailwind direction utilities in the views that are not written for RTL.

Two things are looked for in the Blade, Vue and JavaScript sources of both panels:

  * one-sided physical utilities (`pl-`, `pr-`, `ml-`, `mr-`, `left-`, `right-`,
    `border-l`, `border-r`, `rounded-l`, `rounded-r`, `text-left`, `text-right`,
    `float-left`, `float-right`) that are neither a logical utility, nor paired
    with an `rtl:` counterpart, nor part of a `translate-x` centring trick;
  * arrow and sort icons whose direction does not come from an `rtl:` variant.

Run it from the repository root:

    python3 scripts/rtl-audit.py

It prints nothing and exits 0 when every view is written for both directions.
The rules it enforces are described in docs/afghanistan-store.md.
"""
from __future__ import annotations

import collections
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

VIEWS = [
    'packages/Webkul/Shop/src/Resources/views',
    'packages/Webkul/Admin/src/Resources/views',
    'packages/Webkul/Installer/src/Resources/views',
    'packages/Webkul/Shop/src/Resources/assets',
    'packages/Webkul/Admin/src/Resources/assets',
]

# physical base class -> the logical utility Tailwind substitutes for it
LOGICAL = {
    'pl': 'ps', 'pr': 'pe', 'ml': 'ms', 'mr': 'me',
    'text-left': 'text-start', 'text-right': 'text-end',
    'border-l': 'border-s', 'border-r': 'border-e',
    'rounded-l': 'rounded-s', 'rounded-r': 'rounded-e',
    'float-left': 'float-start', 'float-right': 'float-end',
    'left': 'start', 'right': 'end',
}

PHYSICAL = '|'.join(sorted(LOGICAL, key=len, reverse=True))

VARIANTS = (r'(?:(?:sm|md|lg|xl|2xl|max-sm|max-md|max-lg|max-xl|max-2xl|hover|focus|'
            r'focus-within|focus-visible|active|visited|first|last|odd|even|disabled|checked|'
            r'required|valid|invalid|placeholder-shown|read-only|group-hover|group-focus|'
            r'peer-hover|peer-focus|peer-checked|dark|print|motion-safe|motion-reduce|'
            r'aria-[a-z-]+):)*')
SIZE = r'(?:-(?:\d+(?:\.\d+)?(?:/\d+)?|\[[^\]]+\]|[a-z][\w-]*))?'

TOKEN = re.compile(rf'^(?P<neg>-)?(?P<prefix>{VARIANTS})(?P<base>{PHYSICAL})(?P<size>{SIZE})$')
CLASS_ATTRIBUTE = re.compile(r'''(?::class|class)\s*=\s*(?:"([^"]*)"|'([^']*)'|`([^`]*)`)''', re.S)

# icons whose meaning depends on the writing direction
DIRECTIONAL_ICONS = {
    'icon-arrow-left': 'icon-arrow-right',
    'icon-arrow-right': 'icon-arrow-left',
    'icon-arrow-left-stylish': 'icon-arrow-right-stylish',
    'icon-arrow-right-stylish': 'icon-arrow-left-stylish',
    'icon-sort-left': 'icon-sort-right',
    'icon-sort-right': 'icon-sort-left',
    'icon-left-arrow': 'icon-right-arrow',
    'icon-right-arrow': 'icon-left-arrow',
}

# `left-1/2 -translate-x-1/2` centres an element and must stay physical
SKEW = re.compile(r'-?(?:translate|skew)-x')


def class_values(path: Path):
    text = path.read_text(encoding='utf-8', errors='replace')
    for match in CLASS_ATTRIBUTE.finditer(text):
        value = next((g for g in match.groups() if g is not None), '')
        if value:
            yield text[:match.start()].count('\n') + 1, value


def sources():
    for target in VIEWS:
        base = ROOT / target
        if not base.is_dir():
            continue
        for pattern in ('*.blade.php', '*.vue', '*.js', '*.ts'):
            for path in base.rglob(pattern):
                parts = set(path.parts)
                if 'node_modules' in parts or 'build' in parts or path.name == 'example.blade.php':
                    continue
                yield path


def main() -> int:
    findings: list[tuple[str, int, str]] = []
    counted = collections.Counter()

    for path in sources():
        relative = str(path.relative_to(ROOT))
        for line, value in class_values(path):
            classes = value.split()
            variants = set(classes)
            skewing = any(SKEW.search(c) for c in classes)

            for token in classes:
                match = TOKEN.match(token)
                if match and not match.group('prefix'):
                    base, size = match.group('base'), match.group('size') or ''
                    if base in ('left', 'right') and skewing:
                        continue  # centring, direction independent
                    if f'rtl:{token.lstrip("-")}' in variants or f'rtl:-{token.lstrip("-")}' in variants:
                        continue
                    logical = (match.group('neg') or '') + LOGICAL[base] + size
                    findings.append((relative, line, f'{token} - use {logical}, or add rtl:{logical}'))
                    counted[base] += 1

                mirror = DIRECTIONAL_ICONS.get(token.split(':')[-1])
                if mirror and token.startswith(('icon-',)) and token in classes:
                    if f'rtl:{mirror}' not in classes:
                        findings.append((relative, line, f'{token} - add rtl:{mirror}'))

    for relative, line, message in findings[:60]:
        print(f'{relative}:{line}  {message}')

    print()
    print(f'{len(findings)} direction findings in {len({f[0] for f in findings})} files')
    if counted:
        print('by utility: ' + ', '.join(f'{k} x{v}' for k, v in counted.most_common()))

    return 1 if findings else 0


if __name__ == '__main__':
    sys.exit(main())
