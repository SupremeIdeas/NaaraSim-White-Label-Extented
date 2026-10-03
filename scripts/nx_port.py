#!/usr/bin/env python3
"""
Mechanical port of the skin wireframes (docs/appearance/wireframes/*.html) to production CSS.

The wireframes are the visual contract, so nothing is re-typed by hand: every rule is lifted from the HTML and only
RENAMED (classes -> ns-*, variables -> --nx-*, [data-theme] -> html.dark, [data-skin] scoped selectors -> compiled
selectors that do not need native @scope). Run:

    python3 scripts/nx_port.py shared            # engine components   -> resources/css/nx-components.css
    python3 scripts/nx_port.py skin <key> [...]  # skin stylesheets    -> resources/css/nx-skins/<key>.css

Re-running is idempotent. See docs/appearance/README.md for the naming map.
"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
WF = ROOT / 'docs/appearance/wireframes'
V9 = WF / 'naara-dashboard-system-v9-surface-core13.html'
V15 = WF / 'naara-dashboard-system-v15-new22-skins-lab.html'

# wireframe class -> production class. Order matters only for readability; matching is whole-token.
CLASS_MAP = {
    'card': 'ns-card', 'verify': 'ns-card--primary', 'tile': 'ns-tile', 'pill': 'ns-pill', 'btn': 'ns-btn', 'cta': 'ns-cta',
    'chips': 'ns-chips', 'strip': 'ns-strip', 'banner': 'ns-banner', 'field': 'ns-field', 'seg': 'ns-seg', 'op': 'ns-row',
    'mr': 'ns-list__row', 'list': 'ns-list', 'promo': 'ns-promo', 'note': 'ns-note', 'foot': 'ns-pricebar', 'sheet': 'ns-sheet',
    'scrim': 'ns-scrim', 'handle': 'ns-handle', 'sh': 'ns-sheet__head', 'deco': 'ns-deco', 'search': 'ns-search', 'spec': 'ns-spec',
    'h1': 'ns-h1', 'sub': 'ns-sub', 'grid': 'ns-grid', 'full': 'ns-full', 'step': 'ns-step', 'lbl': 'ns-lbl', 'nrow': 'ns-nrow',
    'len': 'ns-len', 'chk': 'ns-chk', 'live': 'ns-live', 'skc': 'ns-skin-card', 'swb': 'ns-swatch', 'sw': 'ns-swatches',
    'mini': 'ns-mini', 'prev': 'ns-preview', 'cust': 'ns-custom', 'dial': 'ns-dial', 'empty': 'ns-empty', 'tg': 'ns-toggle',
    'ck': 'ns-check', 'stack': 'ns-stack', 'back': 'ns-back', 'hero': 'ns-hero', 'thumb': 'ns-thumb', 'pr2': 'ns-price',
    'av': 'ns-avatar', 'keys': 'ns-keypad', 'tl': 'ns-timeline', 'prog': 'ns-progress', 'chipsel': 'ns-chips-filter',
    'gate': 'ns-gate', 'grp': 'ns-group', 'ring': 'ns-ring', 'fl': 'ns-flag', 'sm': 'ns-small', 'rw': 'ns-row-inline',
    'top': 'ns-card__top', 'pr': 'ns-row__price', 'chev': 'ns-chevron', 'st': 'ns-list__star', 'hd': 'ns-skin-card__head',
    'x': 'ns-sheet__close', 'bk': 'ns-sheet__back', 'amt': 'ns-amount', 'stat': 'ns-stat', 'r': 'ns-price__right',
    'row2': 'ns-card__row', 'app': 'ns-app', 'stage': 'ns-app__body', 'wrapseg': 'ns-seg--wrap', 'js-skinseg': 'ns-seg--wrap', 'sect': 'ns-section', 'stats': 'ns-stats', 'pt': 'ns-card__title', 'row': 'ns-pricebar--row', 'tx': 'ns-card__text', 't': 'ns-text',
    # states / modifiers
    'on': 'is-on', 'sel': 'is-selected', 'g': 'ns-list--grid', 'pin': 'ns-list__row--pin', 'dash': 'ns-note--dash', 'warn': 'ns-note--warn', 'out': 'ns-pill--out',
    'best': 'ns-pill--best', 'rec': 'ns-pill--rec', 'auto': 'ns-row--auto', 'no': 'ns-row--no', 'short': 'ns-sheet--short',
    'done': 'is-done', 'lnk': 'ns-note--link', 'sm2': 'ns-small', 'i': 'ns-i',
    # card tone modifiers
    'rent': 'ns-card--rent', 'line': 'ns-card--line', 'net': 'ns-card--net', 'fwd': 'ns-card--fwd', 'ppl': 'ns-card--ppl', 'cyan': 'ns-card--cyan',
}

# Wireframe-only chrome (phone frame, review bar, header, bottom bar, toast, demo selects). Production owns those.
DROP_SELECTORS = [
    r'^\*$', r'^html', r'^body', r'^\[data-theme=light\] body', r'^button', r'^:focus-visible', r'^\.hide$', r'^svg\.i$',
    r'^\.bar', r'^\.app', r'^\.hdr', r'^\.logo', r'^\.mark', r'^\.hb', r'^\.tog', r'^\.stage', r'^\.body', r'^\.nav', r'^\.toast',
    r'^\.js-who', r'^\.bld', r'^:root$', r'^\[data-theme=light\]$', r'^\[data-theme=light\]\[data-accent', r'^\[data-accent=',
    r'^\[data-round', r'^\[data-ts', r'^\[data-depth', r'^\[data-font', r'^\[data-dens', r'^\[data-motion',
    r'^\[data-theme=light\]\[data-depth', r'^\.stage',
]

VAR_RENAME = {'canvas2': 'canvas-2', 'gold2': 'gold-2', 'glass': 'frost', 'ga': 'frost-a', 'surface2': 'surface-2', 'surface3': 'surface-3', 'text2': 'text-2', 'text3': 'text-3'}


def read_css(path):
    s = path.read_text()
    a = s.index('<style>') + 7
    return s[a:s.index('</style>')]


def split_top(css):
    """Top-level (selector, body) items; at-rules keep their full block as body."""
    items, i, n = [], 0, len(css)
    while i < n:
        if css[i].isspace():
            i += 1
            continue
        if css.startswith('/*', i):
            i = css.index('*/', i) + 2
            continue
        j = i
        while css[j] != '{':
            j += 1
        sel = css[i:j].strip()
        d, k = 0, j
        while True:
            if css[k] == '{':
                d += 1
            elif css[k] == '}':
                d -= 1
                if d == 0:
                    break
            k += 1
        items.append((sel, css[j + 1:k]))
        i = k + 1
    return items


def split_commas(sel):
    out, d, cur = [], 0, ''
    for ch in sel:
        if ch in '([':
            d += 1
        elif ch in ')]':
            d -= 1
        if ch == ',' and d == 0:
            out.append(cur.strip())
            cur = ''
        else:
            cur += ch
    if cur.strip():
        out.append(cur.strip())
    return out


def rename_classes(sel):
    # contextual gold: a gold CTA vs a gold pill
    sel = sel.replace('.cta.gold', '.cta.ns-cta--gold').replace('.pill.gold', '.pill.ns-pill--gold').replace(':not(.gold)', ':not(.ns-pill--gold)')
    def sub(m):
        name = m.group(1)
        return '.' + CLASS_MAP.get(name, name)
    return re.sub(r'\.([A-Za-z][\w-]*)', sub, sel)


def rename_attrs(sel):
    sel = re.sub(r'\[data-(round|dens|ts|depth|font|motion|accent|skin|country|tod)=', r'[data-nx-\1=', sel)
    sel = sel.replace('[data-mode=page]', '.ns-flow--page')
    return sel


def theme(sel):
    sel = sel.replace('[data-theme=light]', ':where(html):not(.dark)').replace('[data-theme=dark]', ':where(html).dark')
    return sel


KEYFRAMES = {'nxp': 'nx-pulse', 'nxspin': 'nx-spin', 'nxtide': 'nx-tide', 'nxwave': 'nx-wave'}


# Contrast contract (Prompt 20 §42): white label text on the CTA / selected-segment gradient must pass 4.5:1 on every pixel it
# sits over, not only on the dark end. The wireframe fades cta-a -> cta-b over the whole height; here the fade finishes in the
# top 30% so the text sits entirely on cta-b. Recorded in docs/appearance/CONTRAST-DEVIATIONS.md.
CONTRAST_PATCHES = {
    'linear-gradient(180deg,rgb(var(--nx-cta-a)),rgb(var(--nx-cta-b)))': 'linear-gradient(180deg,rgb(var(--nx-cta-a)),rgb(var(--nx-cta-b)) 30%)',
}


def finalize(css):
    """Whole-file patches that need the selector. A segmented button is only ~38px tall, so a 30% fade would still run under its
    label: it finishes in 8px instead."""
    return css.replace('.ns-seg button.is-on{background:linear-gradient(180deg,rgb(var(--nx-cta-a)),rgb(var(--nx-cta-b)) 30%)',
                       '.ns-seg button.is-on{background:linear-gradient(180deg,rgb(var(--nx-cta-a)),rgb(var(--nx-cta-b)) 8px)')


def fix_names(text):
    for old, new in KEYFRAMES.items():
        text = re.sub(r'(?<![\w-])' + old + r'(?![\w-])', new, text)
    for old, new in CONTRAST_PATCHES.items():
        text = text.replace(old, new)
    return text


def rename_vars(text):
    def sub(m):
        name = m.group(1)
        return '--nx-' + VAR_RENAME.get(name, name)
    # leave data: URIs alone
    parts = re.split(r'(url\((?:"[^"]*"|\'[^\']*\'|[^)]*)\))', text)
    for idx in range(0, len(parts), 2):
        parts[idx] = re.sub(r'--(?!nx-)([a-z][a-z0-9-]*)', sub, parts[idx])
    return ''.join(parts)


def should_drop(sel):
    s = sel.strip()
    return any(re.match(p, s) for p in DROP_SELECTORS)


def port_selector(sel):
    return rename_classes(theme(rename_attrs(sel)))


def port_block(sel, body):
    if sel.startswith('@'):
        if sel.startswith('@keyframes') or sel.startswith('@property'):
            return fix_names(sel.replace('--ang', '--nx-ang')) + '{' + fix_names(rename_vars(body)) + '}'
        inner = ''.join(port_block(s, b) + '\n' for s, b in split_top(body) if not should_drop(s))
        return (sel + '{\n' + inner + '}') if inner.strip() else ''
    sels = [port_selector(s) for s in split_commas(sel)]
    return ', '.join(sels) + '{' + fix_names(rename_vars(body)) + '}'


def shared(src, drop_skins=True):
    out = []
    for sel, body in split_top(read_css(src)):
        if sel.startswith('@scope'):
            continue
        if 'data-skin=' in sel or 'data-skin=' in body:
            continue
        if should_drop(sel):
            continue
        blk = port_block(sel, body)
        if blk:
            out.append(blk)
    return '\n'.join(out) + '\n'


# ---------------------------------------------------------------------------------------------------------------------
# Skins. Each wireframe skin is an `@scope ([data-skin=X]) to ([data-skin=X] [data-skin]) { :scope ... }` block. Native @scope
# is not used (older Android WebViews ignore the whole rule), so every rule is compiled to plain selectors:
#   descendant rule:  html[data-nx-skin=X] REST:not([data-nx-preview] *) ,  [data-nx-preview=X] REST
#     (the :not() stops the PAGE skin leaking into a nested picker preview, which carries its own data-nx-preview)
#   root rule:        html[data-nx-skin=X]COMPOUND ,  [data-nx-preview=X]COMPOUND
#   light-only rule:  prefixed with html:not(.dark)
# ---------------------------------------------------------------------------------------------------------------------
PSEUDO_EL = re.compile(r'((?:::?(?:before|after|first-line|first-letter|placeholder|selection|-webkit-[\w-]+))+)$')


def split_compound(rest):
    """Split the text after :scope into (leading compound, descendant tail)."""
    d = 0
    for i, ch in enumerate(rest):
        if ch in '([':
            d += 1
        elif ch in ')]':
            d -= 1
        elif d == 0 and ch in ' >+~':
            return rest[:i], rest[i:]
    return rest, ''


def add_not_preview(tail):
    """Append :not([data-nx-preview] *) to the LAST compound of a tail, before any pseudo-element."""
    m = PSEUDO_EL.search(tail)
    if m:
        return tail[:m.start()] + ':where(:not([data-nx-preview] *))' + m.group(1)
    return tail + ':where(:not([data-nx-preview] *))'


def compile_scoped(sel, skin):
    light = False
    m = re.match(r'^:where\(\[data-theme=light\],\[data-theme=light\] \*\)(.*)$', sel)
    if m:
        light, sel = True, m.group(1)
    rest = sel[len(':scope'):] if sel.startswith(':scope') else ' ' + sel
    comp, tail = split_compound(rest)
    comp, tail = port_selector(comp), port_selector(tail)
    if light:
        a = 'html:where(:not(.dark))[data-nx-skin=%s]%s' % (skin, comp)
        b = ':where(html:not(.dark)) [data-nx-preview=%s]%s' % (skin, comp)
    else:
        a = 'html[data-nx-skin=%s]%s' % (skin, comp)
        b = '[data-nx-preview=%s]%s' % (skin, comp)
    if tail.strip():
        a += add_not_preview(tail)
        b += tail
    return a + ',\n' + b


def port_scoped_block(sel, body, skin):
    if sel.startswith('@'):
        inner = '\n'.join(filter(None, (port_scoped_block(s, b, skin) for s, b in split_top(body))))
        return (sel + '{\n' + inner + '\n}') if inner.strip() else ''
    if any(re.search(r'\.hdr(?![\w-])', c) for c in split_commas(sel)):
        return ''   # the wireframe's own header is not ours: the production header stays untouched
    sels = [compile_scoped(c, skin) for c in split_commas(sel)]
    return ',\n'.join(sels) + '{' + fix_names(rename_vars(body)) + '}'


def port_toplevel(sel, body):
    """A V9 wireframe rule written against the page (`[data-theme=dark][data-skin=X] .card`). The page skin gets it (not leaking
    into nested previews); the picker preview of X gets the same rule so a preview looks like the applied skin."""
    if sel.startswith('@'):
        return port_block(sel, body)
    out = []
    for c in split_commas(sel):
        p = port_selector(c)
        m = re.search(r'\[data-nx-skin=(\w+)\]', p)
        if not m:
            out.append(p)
            continue
        head, rest = p[:m.end()], p[m.end():]
        _, tail = split_compound(rest)
        out.append(head + (rest[:len(rest) - len(tail)]) + (add_not_preview(tail) if tail.strip() else ''))
        pv = re.sub(r'(?:html)?\[data-nx-skin=(\w+)\]', r' [data-nx-preview=\1]', p, count=1).strip()
        out.append(pv)
    return ',\n'.join(dict.fromkeys(out)) + '{' + fix_names(rename_vars(body)) + '}'


def skin_of(text):
    m = re.search(r'data-skin=(\w+)', text)
    return m.group(1) if m else None


APP_SURFACE = re.compile(r'^(?P<head>.*?) \.ns-app(?::where\(:not\(\[data-nx-preview\] \*\)\))?$')


def split_decls(body):
    """Split a declaration block on top-level `;` (data: URIs and functions may contain their own)."""
    out, d, cur = [], 0, ''
    for ch in body:
        if ch == '(':
            d += 1
        elif ch == ')':
            d -= 1
        if ch == ';' and d == 0:
            out.append(cur)
            cur = ''
        else:
            cur += ch
    if cur.strip():
        out.append(cur)
    return [x.strip() for x in out if x.strip()]


def page_companion(blk):
    """Skins repaint `.ns-app`, a panel that only spans the content. The skin must own the whole page, end to end, so every
    skin rule that paints the app surface also paints the page (<body>) with the same background, pinned to the viewport. The
    platform's dashboard wallpaper is switched off under a skin in nx-tokens.css."""
    extra = []
    for sel, body in split_top(blk):
        if sel.startswith('@'):
            continue
        heads = []
        for c in split_commas(sel):
            m = APP_SURFACE.match(c.strip())
            if m and 'data-nx-preview' not in m.group('head'):
                heads.append(m.group('head') + ' body[class]')
        decls = [x for x in split_decls(body) if x.startswith('background')]
        if heads and decls:
            extra.append(',\n'.join(heads) + '{' + ';'.join(decls) + ';background-attachment:fixed}')
    return blk + ('\n' + '\n'.join(extra) if extra else '')


def skin_rules(sources):
    """{skin: css} lifted in wireframe order. Rules for the same skin from every source are merged; identical rules are kept once."""
    out = {}
    for src in sources:
        for sel, body in split_top(read_css(src)):
            if sel.startswith('@scope'):
                key = skin_of(sel)
                blk = '\n'.join(filter(None, (port_scoped_block(s, b, key) for s, b in split_top(body))))
            elif 'data-skin=' in sel or 'data-skin=' in body:
                key = skin_of(sel) or skin_of(body)
                blk = port_toplevel(sel, body)
            else:
                continue
            if not blk.strip():
                continue
            blk = page_companion(blk)
            rules = out.setdefault(key, [])
            if blk not in rules:
                rules.append(blk)
    return {k: '\n'.join(v) + '\n' for k, v in out.items()}


if __name__ == '__main__':
    mode = sys.argv[1] if len(sys.argv) > 1 else 'shared'
    if mode == 'shared':
        css = '/* GENERATED by scripts/nx_port.py from the V9 wireframe. Do not edit by hand: change the script or the wireframe. */\n' + finalize(shared(V9))
        (ROOT / 'resources/css/nx-components.css').write_text(css)
        print('wrote resources/css/nx-components.css', len(css), 'bytes')
    elif mode == 'icons':
        wf = V9.read_text()
        syms = re.findall(r'<symbol id="([a-z0-9-]+)"([^>]*)>(.*?)</symbol>', wf, re.S)
        out = ["{{-- GENERATED by scripts/nx_port.py icons from the V9 wireframe sprite (the visual contract). Do not edit by hand. Skin icons are namespaced nx-* so the platform's own sprite is untouched. --}}",
               '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">']
        out += [f'<symbol id="nx-{i}"{a}>{body}</symbol>' for i, a, body in syms]
        out.append('</svg>')
        (ROOT / 'resources/views/partials/nx-icon-sprite.blade.php').write_text('\n'.join(out) + '\n')
        print('wrote nx-icon-sprite with', len(syms), 'icons')
    elif mode == 'skins':
        sk = skin_rules([V9, V15])
        outdir = ROOT / 'resources/css/nx-skins'
        outdir.mkdir(exist_ok=True)
        header = '/* GENERATED by scripts/nx_port.py from the wireframes. Do not edit by hand: change the script or the wireframe. */\n'
        for key, css in sk.items():
            (outdir / f'{key}.css').write_text(header + finalize(css))
        order = ['surface'] + [k for k in sk if k != 'surface']
        (ROOT / 'resources/css/nx-skins.css').write_text(header + ''.join(f"@import './nx-skins/{k}.css';\n" for k in order) + "@import './nx-skins/_contrast-fixes.css';\n")
        print('wrote', len(sk), 'skins:', ' '.join(order))
    else:
        print('usage: nx_port.py shared|skins|icons', file=sys.stderr)
        sys.exit(2)
