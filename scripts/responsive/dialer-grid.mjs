// Dialer keypad regression (Brand-Growth 1.1): opened from a saved contact at 360x740, the keypad must stay a 3x4 grid and nothing may overflow,
// whatever the number length. Usage: node scripts/responsive/dialer-grid.mjs http://127.0.0.1:8099 email password
// Needs a signed-in user, the dialer feature on (Twilio configured) and contacts whose numbers are 7, 13 and 15 digits long.
import fs from 'node:fs';
import path from 'node:path';

// Playwright is resolved like scripts/responsive/audit.mjs: PLAYWRIGHT_MODULE, ./node_modules, then /opt/node-tools (the cloud image).
let pw = null;
for (const p of [process.env.PLAYWRIGHT_MODULE, 'playwright', path.resolve('node_modules/playwright'), '/opt/node-tools/node_modules/playwright'].filter(Boolean)) {
  try { pw = await import(p.startsWith('/') ? p + '/index.mjs' : p); break; } catch { /* try the next */ }
}
if (!pw) { console.error('Playwright not found. Set PLAYWRIGHT_MODULE or npm i -D playwright.'); process.exit(2); }
const chromium = pw.chromium;

const [base = 'http://127.0.0.1:8099', email = 'plain@example.test', pass = 'Passw0rd!x'] = process.argv.slice(2);
const numbers = ['+2348012', '+2348012345678', '+442071838750123'];
const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM || (fs.existsSync('/opt/pw-browsers/chromium') ? '/opt/pw-browsers/chromium' : undefined) });
let failed = 0;
for (const n of numbers) {
  const page = await (await browser.newContext({ viewport: { width: 360, height: 740 }, hasTouch: true })).newPage();
  await page.goto(base + '/login');
  await page.fill('input[name=email]', email); await page.fill('input[name=password]', pass);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  await page.goto(`${base}/numbers/dialer?to=${encodeURIComponent(n)}`);
  await page.waitForSelector('.ns-dl__pad', { timeout: 30000 });
  await page.waitForTimeout(1500);
  const m = await page.evaluate(() => {
    const keys = [...document.querySelectorAll('.ns-dl__key')].map((k) => { const r = k.getBoundingClientRect(); return { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height) }; });
    const vw = document.documentElement.clientWidth;
    return { keys, hscroll: document.documentElement.scrollWidth > vw, inside: keys.every((k) => k.x >= 0 && k.x + k.w <= vw), square: keys.every((k) => Math.abs(k.w - k.h) <= 1), cols: new Set(keys.map((k) => k.x)).size, rows: new Set(keys.map((k) => k.y)).size };
  });
  const ok = m.keys.length === 12 && m.cols === 3 && m.rows === 4 && m.inside && m.square && !m.hscroll;
  console.log(ok ? 'PASS' : 'FAIL', n, JSON.stringify({ cols: m.cols, rows: m.rows, inside: m.inside, square: m.square, hscroll: m.hscroll }));
  if (!ok) failed++;
}
await browser.close();
process.exit(failed ? 1 : 0);
