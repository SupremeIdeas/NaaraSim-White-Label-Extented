/*
 * Review moments, browser side. Asks "how did it go?" only at a calm moment: when the person has been idle for a while (default 15 minutes) or comes back to the app
 * after being away that long. The SERVER decides whether there is anything to ask about (a completed purchase or use, cool-down, admin switches); this file only finds a
 * quiet moment, never interrupts a call, a checkout, a login or another dialog, and shows the sheet.
 */
const box = document.querySelector('[data-review-moment]');
if (box) {
  const IDLE_MS = Math.max(1, Number(box.dataset.idle || 15)) * 60000;
  const SKIP = JSON.parse(box.dataset.skip || '[]');
  const SEEN = 'naara-rm-seen';
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const api = async (path, method = 'POST', body) => {
    const res = await fetch('/reviews' + path, { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: body ? JSON.stringify(body) : undefined });
    let data = {}; try { data = await res.json(); } catch (e) { /* empty */ }
    return { ok: res.ok, data };
  };
  let asked = false; let current = null; let rated = false; let stars = 0; let idleTimer = null; let hiddenAt = 0; let tries = 0;
  const $ = (s) => box.querySelector(s);

  const busy = () => {
    if (!box.hidden) { return true; }
    if (SKIP.some((p) => location.pathname.startsWith(p))) { return true; }
    if (document.querySelector('#n2n-call:not([hidden]), [data-call-review]:not([hidden])')) { return true; }
    if ([...document.querySelectorAll('[aria-modal="true"]')].some((d) => d !== box && !d.hidden && d.offsetParent !== null)) { return true; }
    const a = document.activeElement; if (a && /input|textarea|select/i.test(a.tagName) && a.value) { return true; }   // typing something
    return false;
  };

  window.NaaraReviewMoments = { busy: () => busy(), ask: () => { asked = false; return maybeAsk(); }, state: () => ({ asked, tries, open: !box.hidden, idleMs: IDLE_MS }) };   // read by tests/visual/review-moments-smoke.mjs

  async function maybeAsk() {
    if (asked || document.hidden) { return; }
    if (busy()) { if (tries++ < 4) { setTimeout(maybeAsk, 60000); } return; }
    const r = await api('/next', 'GET');
    if (!r.ok || !r.data.show) { asked = true; return; }   // nothing to ask: stay quiet for this visit
    asked = true; current = r.data; rated = false; stars = 0;
    $('[data-rm-title]').textContent = current.title; $('[data-rm-sub]').textContent = current.sub;
    box.querySelectorAll('[data-star]').forEach((b) => { b.classList.remove('is-on'); b.setAttribute('aria-checked', 'false'); });
    $('[data-rm-submit]').disabled = true;
    $('[data-rm-step="rate"]').hidden = false; $('[data-rm-step="thanks"]').hidden = true;
    box.hidden = false; box.querySelector('[data-star]').focus();
  }

  const close = () => { if (box.hidden) { return; } box.hidden = true; if (current && !rated) { api(`/${current.id}/dismiss`); } };
  box.querySelectorAll('[data-rm-close]').forEach((b) => b.addEventListener('click', close));
  $('[data-rm-scrim]').addEventListener('click', close);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); } });
  const starBtns = [...box.querySelectorAll('[data-star]')];
  const pick = (n) => { stars = n; starBtns.forEach((b) => { b.classList.toggle('is-on', Number(b.dataset.star) <= n); b.setAttribute('aria-checked', Number(b.dataset.star) === n ? 'true' : 'false'); }); $('[data-rm-submit]').disabled = false; };
  starBtns.forEach((b, i) => { b.addEventListener('click', () => pick(Number(b.dataset.star))); b.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight' && starBtns[i + 1]) { starBtns[i + 1].focus(); pick(i + 2); } if (e.key === 'ArrowLeft' && starBtns[i - 1]) { starBtns[i - 1].focus(); pick(i); } }); });
  $('[data-rm-submit]').addEventListener('click', async (e) => {
    if (!stars || !current) { return; }
    e.currentTarget.disabled = true;
    const r = await api(`/${current.id}/rate`, 'POST', { stars });
    if (!r.ok) { close(); return; }
    rated = true;
    $('[data-rm-link]').href = r.data.review_url;
    const sup = $('[data-rm-support]'); sup.hidden = !r.data.support_url; if (r.data.support_url) { sup.href = r.data.support_url; }
    $('[data-rm-step="rate"]').hidden = true; $('[data-rm-step="thanks"]').hidden = false;
  });
  $('[data-rm-link]').addEventListener('click', () => { if (current) { api(`/${current.id}/opened`); } });

  // Quiet moments: idle for a while, or back after being away.
  const rearm = () => { clearTimeout(idleTimer); idleTimer = setTimeout(() => { asked = false; tries = 0; maybeAsk(); }, IDLE_MS); try { localStorage.setItem(SEEN, String(Date.now())); } catch (e) { /* private mode */ } };
  let lastStamp = 0;
  ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach((ev) => window.addEventListener(ev, () => { if (Date.now() - lastStamp > 5000) { lastStamp = Date.now(); rearm(); } }, { passive: true }));
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) { hiddenAt = Date.now(); return; }
    if (hiddenAt && Date.now() - hiddenAt >= IDLE_MS) { asked = false; tries = 0; setTimeout(maybeAsk, 2500); }
    rearm();
  });
  let away = 0; try { away = Date.now() - Number(localStorage.getItem(SEEN) || 0); } catch (e) { /* ok */ }
  rearm();
  if (away >= IDLE_MS) { setTimeout(maybeAsk, 4000); }   // coming back to Naara after a while
}
