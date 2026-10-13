// Review moments smoke test: the "how did it go?" sheet after a real success, at a calm moment.
// Setup (scratch DB): review moments ON with a Trustpilot profile, idle_minutes=1, a delivered eSIM order for ada@n2n.test (no earlier ask). Run: BASE=http://127.0.0.1:8099 node tests/visual/review-moments-smoke.mjs
import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
const { chromium } = createRequire(execSync('npm root -g').toString().trim() + '/')('playwright');
const BASE = process.env.BASE || 'http://127.0.0.1:8099';
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium', args: ['--no-sandbox'] });
let fails = 0; const ok = (n, c, extra = '') => { console.log((c ? 'PASS ' : 'FAIL ') + n + (extra ? '  ' + extra : '')); if (!c) fails++; };
async function login(seenMinutesAgo) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true });
  if (seenMinutesAgo !== null) { await ctx.addInitScript((ms) => { try { localStorage.setItem('naara-rm-seen', String(Date.now() - ms)); } catch (e) {} }, seenMinutesAgo * 60000); }
  const page = await ctx.newPage(); page.on('pageerror', (e) => console.log('pageerror', String(e).slice(0, 200)));
  await page.goto(BASE + '/login'); await page.locator('#naara-cookie button:has-text("Reject all")').first().click({ timeout: 9000 }).catch(() => {});
  await page.fill('input[name=email]', 'ada@n2n.test'); await page.fill('input[name=password]', 'password');
  for (let i = 0; i < 3 && new URL(page.url()).pathname.startsWith('/login'); i++) { await page.click('button[type=submit]', { force: true }); await page.waitForTimeout(3000); }
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 });
  return { ctx, page };
}
// 1) Coming back after being away for 20 minutes -> asked within a few seconds, with the eSIM wording, then rate -> Trustpilot for everyone.
let s = await login(20);
await s.page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' });
await s.page.waitForSelector('[data-review-moment]:not([hidden])', { timeout: 15000 }).then(() => ok('returning after a while opens the sheet', true), () => ok('returning after a while opens the sheet', false));
ok('the wording is about the eSIM', (await s.page.textContent('[data-rm-title]')).includes('eSIM'));
ok('Submit is disabled until a star is chosen', await s.page.locator('[data-rm-submit]').isDisabled());
await s.page.screenshot({ path: 'test-results/review-moment-rate.png' });
await s.page.click('[data-star="5"]'); await s.page.click('[data-rm-submit]');
await s.page.waitForSelector('[data-rm-step="thanks"]:not([hidden])', { timeout: 8000 });
ok('a 5-star answer is invited to Trustpilot', (await s.page.getAttribute('[data-rm-link]', 'href')).includes('trustpilot.com/evaluate/'));
ok('a 5-star answer has no support link', await s.page.locator('[data-rm-support]').isHidden());
await s.page.screenshot({ path: 'test-results/review-moment-thanks.png' });
await s.page.click('[data-rm-step="thanks"] [data-rm-close]');
// 2) Asked once: a fresh visit after another long absence does not ask again (cool-down and one-time moment).
await s.page.evaluate(() => localStorage.setItem('naara-rm-seen', String(Date.now() - 30 * 60000)));
await s.page.goto(BASE + '/dashboard', { waitUntil: 'networkidle' }); await s.page.waitForTimeout(7000);
ok('it is not asked again', await s.page.locator('[data-review-moment]').isHidden());
await s.ctx.close();
// 3) Never on a payment/checkout style page.
s = await login(20);
await s.page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' }); await s.page.waitForTimeout(6000);
ok('it stays quiet on sign-in style pages', await s.page.locator('[data-review-moment]').count() === 0 || await s.page.locator('[data-review-moment]').isHidden());
await s.ctx.close(); await browser.close(); process.exit(fails ? 1 : 0);
