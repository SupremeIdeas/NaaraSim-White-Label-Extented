// "Diagnosis / Architecture / Promise" homepage bento block — scroll reveal
// + mouse-tracking glow + the click-to-open detail modal ported from the
// owner-supplied reference (Phase D restore: the modal had been dropped, which
// left every card a dead click).
function mountBentoModal(root) {
    const modal = root.querySelector('[data-bento-modal]');
    const dataEl = root.querySelector('[data-bento-modal-data]');
    if (!modal || !dataEl) return () => {};
    let data = {};
    try { data = JSON.parse(dataEl.textContent); } catch (e) { return () => {}; }

    // The "there is more inside" eyebrow: a small corner-brackets expand mark on every card that opens a detail modal (the same cue Stripe uses), so a card
    // never looks like a dead tile. It is decorative: the card itself is the button and is announced as one that opens a dialog.
    root.querySelectorAll('.nx-bento-cell').forEach((cell) => {
        const d = data[cell.dataset.cell];
        if (!d || !(d.headline || d.body) || cell.querySelector('.nx-bento-expand')) return;
        cell.setAttribute('aria-haspopup', 'dialog');
        const mark = document.createElement('span');
        mark.className = 'nx-bento-expand';
        mark.setAttribute('aria-hidden', 'true');
        mark.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
        cell.appendChild(mark);
    });

    const q = (sel) => modal.querySelector(sel);
    const themeBtn = q('[data-bento-theme]');
    let last = null, open = false, dark = null;
    const KEY = 'naara-modal-darkmode';
    const siteDark = () => document.documentElement.classList.contains('dark');
    const applyDark = (v) => { dark = v; modal.classList.toggle('is-dark', v); themeBtn.setAttribute('aria-pressed', String(v)); };

    const openFor = (cell) => {
        const d = data[cell.dataset.cell];
        if (!d) return;
        last = cell;
        q('[data-m-eyebrow]').textContent = d.eyebrow || '';
        q('[data-m-headline]').textContent = d.headline || '';
        q('[data-m-body]').innerHTML = d.body || '';
        const quote = q('[data-m-quote]');
        quote.textContent = d.quote ? '\u201C' + d.quote + '\u201D' : '';
        quote.hidden = !d.quote;
        const list = q('[data-m-bullets]');
        list.innerHTML = '';
        (d.bullets || []).forEach((b) => { const li = document.createElement('li'); li.innerHTML = b; list.appendChild(li); });
        const art = cell.querySelector('.nx-bento-art');
        q('[data-m-art]').innerHTML = art ? art.innerHTML : '';
        let pref = null;
        try { pref = localStorage.getItem(KEY); } catch (e) { /* storage blocked */ }
        applyDark(pref === null ? siteDark() : pref === 'true');
        document.body.style.overflow = 'hidden';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        open = true;
        setTimeout(() => q('[data-bento-close]').focus(), 60);
    };
    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        open = false;
        document.body.style.overflow = '';
        last && last.focus({ preventScroll: true });
    };

    const cells = root.querySelectorAll('.nx-bento-cell[data-cell]');
    const onCell = (e) => openFor(e.currentTarget);
    const onCellKey = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openFor(e.currentTarget); } };
    cells.forEach((c) => { c.addEventListener('click', onCell); c.addEventListener('keydown', onCellKey); });
    const onOverlay = (e) => { if (e.target === modal) close(); };
    const onTheme = () => { applyDark(!dark); try { localStorage.setItem(KEY, String(dark)); } catch (e) { /* ignore */ } };
    const onKey = (e) => {
        if (!open) return;
        if (e.key === 'Escape') { close(); return; }
        if (e.key === 'Tab') {
            const f = modal.querySelectorAll('button');
            const first = f[0], end = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); end.focus(); }
            else if (!e.shiftKey && document.activeElement === end) { e.preventDefault(); first.focus(); }
        }
    };
    modal.addEventListener('click', onOverlay);
    q('[data-bento-close]').addEventListener('click', close);
    themeBtn.addEventListener('click', onTheme);
    document.addEventListener('keydown', onKey);

    return () => {
        document.removeEventListener('keydown', onKey);
        cells.forEach((c) => { c.removeEventListener('click', onCell); c.removeEventListener('keydown', onCellKey); });
        document.body.style.overflow = '';
    };
}

export function mountBentoDiagnosis(root) {
    const destroyModal = mountBentoModal(root);
    const revealables = root.querySelectorAll('.nx-bento-intro, .nx-bento-cell, .nx-bento-formula-line');
    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealables.forEach((el) => io.observe(el));

    // Safety net (owner report: whole bento sections stayed blank after a fast scroll): IntersectionObserver only reports a
    // CHANGE, so an element that is flung past between two frames is never reported and would stay invisible forever. On every
    // scroll frame, reveal anything that is now in or above the viewport.
    let ticking = false;
    const sweep = () => {
        ticking = false;
        const vh = window.innerHeight || 800;
        revealables.forEach((el) => {
            if (el.classList.contains('visible')) return;
            if (el.getBoundingClientRect().top < vh * 0.98) { el.classList.add('visible'); io.unobserve(el); }
        });
    };
    const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(sweep); } };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    sweep();

    if (!window.matchMedia('(hover: hover)').matches) {
        return () => { io.disconnect(); destroyModal(); window.removeEventListener('scroll', onScroll); window.removeEventListener('resize', onScroll); };
    }

    const cells = root.querySelectorAll('.nx-bento-cell');
    const onMove = (e) => {
        const rect = e.currentTarget.getBoundingClientRect();
        e.currentTarget.style.setProperty('--mx', `${e.clientX - rect.left}px`);
        e.currentTarget.style.setProperty('--my', `${e.clientY - rect.top}px`);
    };
    cells.forEach((cell) => cell.addEventListener('mousemove', onMove));

    return () => {
        io.disconnect();
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
        destroyModal();
        cells.forEach((cell) => cell.removeEventListener('mousemove', onMove));
    };
}
