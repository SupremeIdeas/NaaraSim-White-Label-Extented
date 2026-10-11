#!/usr/bin/env python3
"""Rewrite legacy Tailwind palette / brand colour utilities in a Blade view into skin-token utilities (nx:coverage contract).
Usage: scripts/nx_tokenize.py file.blade.php [...]   (edits in place, prints any dark: variant it could not drop)

Light utilities map onto --nx-* tokens (which already flip with light/dark and with the skin); per-element dark: colour variants are
dropped because the token covers both modes. Black/white scrims and on-fill text are left as they are (allowed by the gate)."""
import re, sys

def T(tok, a=None):
    return f"rgb(var(--nx-{tok})/{a})" if a is not None else f"rgb(var(--nx-{tok}))"

def alpha(o):
    return None if o is None else str(round(int(o) / 100, 3)).rstrip('0').rstrip('.') if o else None

TEXT = {'slate-900': 'text', 'slate-800': 'text', 'slate-700': 'text', 'slate-200': 'text', 'slate-100': 'text',
        'slate-600': 'text-2', 'slate-500': 'text-2', 'slate-400': 'text-2', 'slate-300': 'text-3',
        'primary': 'teal-ink', 'primary-dark': 'teal-ink', 'teal-300': 'teal-ink', 'teal-600': 'teal-ink', 'teal-700': 'teal-ink',
        'accent': 'gold-ink', 'accent-dark': 'gold-ink', 'navy': 'text'}
for s in ('red-600', 'red-700', 'red-500', 'red-300', 'red-400'): TEXT[s] = 'bad'
for s in ('green-600', 'green-700', 'green-500', 'green-300', 'green-400', 'emerald-700', 'emerald-400', 'emerald-600'): TEXT[s] = 'ok'
for s in ('amber-600', 'amber-700', 'amber-800', 'amber-400', 'amber-300', 'amber-200', 'orange-600'): TEXT[s] = 'warn'

BG = {'slate-700': ('__scrim__', None), 'slate-800': ('__scrim__', None), 'slate-900': ('__scrim__', None), 'slate-50': ('surface-2', None), 'slate-100': ('surface-3', None), 'slate-200': ('surface-3', None),
      'slate-300': ('line-strong', None), 'slate-400': ('line-strong', None),
      'primary': ('cta-b', None), 'primary-dark': ('cta-a', None), 'accent': ('gold', None), 'accent-dark': ('gold-2', None),
      'red-50': ('bad', .1), 'red-100': ('bad', .14), 'amber-50': ('warn', .12), 'amber-100': ('warn', .16),
      'green-50': ('ok', .12), 'green-100': ('ok', .16), 'emerald-50': ('ok', .12), 'green-500': ('ok', None), 'emerald-500': ('ok', None),
      'red-500': ('bad', None), 'amber-500': ('warn', None)}
BORDER = {'slate-50': 'line', 'slate-100': 'line', 'slate-200': 'line', 'slate-300': 'line-strong', 'slate-400': 'line-strong',
          'primary': 'teal', 'accent': 'gold', 'red-300': 'bad', 'red-200': 'bad', 'amber-300': 'warn', 'amber-200': 'warn', 'green-300': 'ok', 'green-200': 'ok'}
GRAD = {'primary': 'cta-b', 'primary-dark': 'cta-a', 'accent': 'gold', 'accent-dark': 'gold-2'}

UTIL = re.compile(r'^(?P<pre>(?:[a-z0-9\-\[\]=&>_]+:)*)(?P<neg>-?)(?P<u>bg|text|border|ring|from|via|to|shadow|fill|stroke|divide|outline|decoration|placeholder)-(?P<c>[a-z]+(?:-\d{2,3}|-dark)?|\[(?:#|var\(|rgb)[^\s\]]*\](?:\])?)(?:/(?P<o>\d{1,3}))?(?P<imp>!?)$')

def convert(token):
    m = UTIL.match(token)
    if not m:
        return token, False
    pre, u, c, o = m['pre'], m['u'], m['c'], m['o']
    dark = 'dark:' in pre
    colour_util = True
    if c in ('white', 'black') and dark and u in ('bg', 'text', 'border', 'ring', 'fill', 'stroke', 'divide', 'shadow'):
        return '', True            # dark-only white/black: the token flips with the mode
    if c == 'white' and u == 'bg' and not o:
        return f"{pre}bg-[{T('surface')}]", True   # a plain white card must follow the skin surface
    if c in ('white', 'black', 'transparent', 'current', 'inherit') or (u == 'text' and c in ('xs', 'sm', 'base', 'lg', 'center', 'left', 'right')):
        return token, False
    isarb = c.startswith('[')
    mapped = None
    if isarb:
        if not dark:
            return token, False          # a light-mode arbitrary value is left as written
        return '', True
    elif u in ('text', 'placeholder', 'fill', 'stroke', 'decoration'):
        t = TEXT.get(c)
        if t:
            mapped = f"{u if u != 'placeholder' else 'placeholder'}-[{T(t, alpha(o)) if o else T(t)}]"
            if u == 'placeholder': mapped = f"placeholder-[{T(t)}]"
    elif u == 'bg':
        b = BG.get(c)
        if b and b[0] == '__scrim__':
            mapped = f"bg-black/{o or '80'}"
        elif b:
            tok, a = b
            if o: a = int(o) / 100
            mapped = f"bg-[{T(tok, round(a, 3)) if a else T(tok)}]"
    elif u in ('border', 'divide', 'outline'):
        t = BORDER.get(c)
        if t:
            mapped = f"{u}-[{T(t, round(int(o) / 100, 3)) if o else T(t)}]"
    elif u == 'ring':
        t = BORDER.get(c) or ('teal' if c.startswith('primary') else None)
        if t:
            mapped = f"ring-[{T(t, round(int(o) / 100, 3)) if o else T(t)}]"
    elif u == 'shadow':
        t = {'primary': 'teal', 'accent': 'gold'}.get(c)
        if t:
            mapped = f"shadow-[{T(t, round(int(o) / 100, 3)) if o else T(t)}]"
    elif u in ('from', 'via', 'to'):
        t = GRAD.get(c)
        if c == 'navy':
            mapped = f"{u}-black/60"
        elif t:
            mapped = f"{u}-[{T(t, round(int(o) / 100, 3)) if o else T(t)}]"
    if mapped is None and dark and re.match(r'^(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|primary|accent|navy)', c):
        return '', True
    if mapped is None:
        return token, False
    if dark:
        return '', True            # token already flips with the mode
    return f"{pre}{mapped}", True

def _tok(t):
    new, changed = convert(t)
    return new if changed else t

def process(text):
    leftovers = set()
    def fix_attr(m):
        classes = m.group(2).split(' ')
        out = []
        for tok in classes:
            if not tok:
                out.append(tok); continue
            new, changed = convert(tok)
            if changed and new == '':
                continue
            out.append(new)
        return m.group(1) + re.sub(r' {2,}', ' ', ' '.join(out)).strip() + m.group(3)
    # class="..." and :class="'...'" / x-bind strings: operate on any quoted run that looks like a class list
    def run(m):
        body = m.group(0)
        if not re.search(r'(?:bg|text|border|ring|from|via|to|shadow|fill|stroke|divide)-', body):
            return body
        out = re.sub(r'(?<![\w-])[^\s]+', lambda t: _tok(t.group(0)), body)
        lead = out[:len(out) - len(out.lstrip())]
        return lead + re.sub(r' {2,}', ' ', out.strip()) + (' ' if body.endswith(' ') and not out.rstrip().endswith('?') and False else '')
    text = re.sub(r'(?:(?<=["\'])|(?<=\}\}))[^"\'<>{}]+(?=["\']|\{\{)', run, text)
    for m in re.finditer(r'\bdark:[\w\[\]/.#:\-]+', text):
        leftovers.add(m.group(0))
    return text, leftovers

if __name__ == '__main__':
    for p in sys.argv[1:]:
        s = open(p).read()
        n, left = process(s)
        open(p, 'w').write(n)
        print(p, 'leftover dark variants:', sorted(left))
