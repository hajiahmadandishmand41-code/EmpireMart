#!/usr/bin/env python3
"""Check that every direction class the views use made it into the built stylesheets.

A class that never reached the built CSS is a class the browser ignores: a padding
on the wrong side, an arrow that does not mirror, a drawer that slides the wrong
way.  This compares the class lists in the Blade, Vue and JavaScript sources of each
panel with the stylesheets under `public/themes/*/default/build/assets`, so a view
that was changed without rebuilding is caught before it is deployed.

Run it from the repository root:

    python3 scripts/check-built-css.py

Exit status is 1 when a class is missing.  Two kinds of token are skipped because
they cannot be matched reliably: arbitrary values that contain a comma, and tokens
built at run time (anything holding `{`, `}` or `$`).
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

PANELS = {
    'Shop': {
        'css': 'public/themes/shop/default/build/assets',
        'sources': [
            'packages/Webkul/Shop/src/Resources/views',
            'packages/Webkul/Shop/src/Resources/assets',
        ],
    },
    'Admin': {
        'css': 'public/themes/admin/default/build/assets',
        'sources': [
            'packages/Webkul/Admin/src/Resources/views',
            'packages/Webkul/Admin/src/Resources/assets',
        ],
    },
    'Installer': {
        'css': 'public/themes/admin/default/build/assets',
        'sources': [
            'packages/Webkul/Installer/src/Resources/views',
        ],
    },
}

VARIANTS = (r'(?:(?:sm|md|lg|xl|2xl|max-sm|max-md|max-lg|max-xl|max-2xl|hover|focus|'
            r'focus-within|focus-visible|active|visited|first|last|odd|even|only|disabled|'
            r'checked|required|valid|invalid|placeholder-shown|read-only|group-hover|'
            r'group-focus|peer-hover|peer-focus|peer-checked|dark|print|motion-safe|'
            r'motion-reduce|after|before|aria-[a-z-]+|group-\[[^\]]*\]/[\w-]+):)*')

LOGICAL = (r'(?:ps|pe|ms|me|start|end|text-start|text-end'
           r'|border-s|border-e|rounded-s|rounded-e|float-start|float-end)')

PHYSICAL = (r'(?:pl|pr|ml|mr|left|right|text-left|text-right'
            r'|border-l|border-r|rounded-l|rounded-r|float-left|float-right)')

DIRECTIONAL = (r'(?:icon-(?:arrow|sort|caret|angle)-(?:left|right)(?:-stylish)?'
               r'|icon-(?:left|right)-arrow|space-x-reverse|translate-x(?:-[\w.\[\]%/-]+)?'
               r'|rotate-\[[^\]]*\]|shadow-\[[^\]]*\])')

SIZE = r'(?:-(?:\d+(?:\.\d+)?(?:/\d+)?|\[[^\]]*\]|[a-z][\w-]*))?'
TOKEN = re.compile(rf'^(?P<neg>-)?(?P<prefix>{VARIANTS})(?P<base>{LOGICAL}|{PHYSICAL}|{DIRECTIONAL})(?P<size>{SIZE})$')
DIRECTION_VARIANT = re.compile(r'rtl:|ltr:')

CLASS_ATTRIBUTE = re.compile(r'''(?::class|class)\s*=\s*(?:"([^"]*)"|'([^']*)'|`([^`]*)`)''', re.S)
IDENTIFIER = re.compile(r'''\.((?:\\.|[A-Za-z0-9_\-/.%#()\[\]'"+@!])+)''')
UNESCAPE = re.compile(r'\\(.)')


def is_direction_class(token: str) -> bool:
    """A class the reading direction can change, or that follows it."""
    if any(c in token for c in (',', '{', '}', '$')):
        return False

    match = TOKEN.match(token)
    if not match:
        return False

    # a bare physical utility (`pl-3`) is not a direction class: it is what the audit
    # script in scripts/rtl-audit.py asks to be replaced by a logical one
    if not DIRECTION_VARIANT.search(token):
        base = match.group('base')
        return bool(re.match(LOGICAL + r'$', base)) or bool(re.match(DIRECTIONAL + r'$', base))

    return True


def built_classes(directory: Path) -> set[str]:
    found: set[str] = set()
    for stylesheet in sorted(directory.glob('*.css')):
        text = stylesheet.read_text(encoding='utf-8', errors='replace')
        for match in IDENTIFIER.finditer(text):
            found.add(UNESCAPE.sub(r'\1', match.group(1)))
    return found


def used_classes(source: Path) -> set[str]:
    used: set[str] = set()
    for pattern in ('*.blade.php', '*.vue', '*.js', '*.ts'):
        for path in source.rglob(pattern):
            parts = set(path.parts)
            if 'node_modules' in parts or 'build' in parts:
                continue
            text = path.read_text(encoding='utf-8', errors='replace')
            for match in CLASS_ATTRIBUTE.finditer(text):
                value = next((group for group in match.groups() if group is not None), '')
                for token in value.split():
                    if is_direction_class(token):
                        used.add(token)
    return used


def main() -> int:
    failures = 0
    for panel, config in PANELS.items():
        directory = ROOT / config['css']
        if not directory.is_dir():
            print(f'{panel}: no built stylesheets under {config["css"]} - run npm run build first')
            failures += 1
            continue

        available = built_classes(directory)

        used: set[str] = set()
        for source in config['sources']:
            path = ROOT / source
            if path.is_dir():
                used |= used_classes(path)

        missing = sorted(token for token in used if token not in available)
        print(f'{panel}: {len(used)} direction classes used, {len(used) - len(missing)} in the built CSS')
        for token in missing:
            print(f'    MISSING: {token}')
        failures += len(missing)

    return 1 if failures else 0


if __name__ == '__main__':
    sys.exit(main())
