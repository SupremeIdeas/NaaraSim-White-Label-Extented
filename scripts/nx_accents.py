#!/usr/bin/env python3
"""Generates resources/css/nx-accents.css from config/appearance.php (accent presets). Run after editing the accents."""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
cfg = json.loads(subprocess.check_output(['php', '-r', 'echo json_encode(include "config/appearance.php");'], cwd=ROOT))
keymap = {'teal': 'teal', 'cta_a': 'cta-a', 'cta_b': 'cta-b', 'teal_ink': 'teal-ink', 'acc_a': 'acc-a', 'acc_b': 'acc-b', 'acc_c': 'acc-c'}
decl = lambda d: ';'.join(f'--nx-{keymap[k]}:{v}' for k, v in d.items())

out = [
    '/* GENERATED from config/appearance.php by scripts/nx_accents.py. Do not edit by hand.',
    ' Accents recolour ONLY the interactive tokens; non-default accents also drive the existing `primary` utilities so un-migrated',
    " views follow the member's accent. They never touch gold (money), status colours, the logo or flags. */",
]
for key, a in cfg['accents'].items():
    sel = f'[data-nx-accent={key}]'
    d, light = a['dark'], a['light']
    out.append(f'{sel}{{{decl(d)}}}')
    out.append(f'html:not(.dark){sel},html:not(.dark) {sel}{{{decl(light)}}}')
    if key != 'teal':
        out.append(f"html{sel}{{--brand-primary:{d['cta_b']};--brand-primary-dark:{d['acc_c']}}}")
        out.append(f"html:not(.dark){sel}{{--brand-primary:{light.get('cta_b', d['cta_b'])};--brand-primary-dark:{d['acc_c']}}}")
(ROOT / 'resources/css/nx-accents.css').write_text('\n'.join(out) + '\n')
print('wrote resources/css/nx-accents.css')
