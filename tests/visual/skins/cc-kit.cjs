// Composer UI-kit demo (all features): GIF maker, schedule, poll, contact, hints, snippets, 2 composers? , dark mode, desktop.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); const mode=process.env.MODE||'dark', w=+(process.env.W||1100);
  const ctx=await b.newContext({viewport:{width:w,height:1000},colorScheme:mode,permissions:['geolocation'],geolocation:{latitude:6.45,longitude:3.39}}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message)); p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/adminmaster/ui-kit',{waitUntil:'networkidle'}); await p.waitForSelector('naara-composer .cc-box'); await p.locator('naara-composer').scrollIntoViewIfNeeded();
  const C=p.locator('naara-composer'); const shot=async n=>{await p.waitForTimeout(250);await C.locator('xpath=ancestor::section').screenshot({path:`out/ck-${n}-${mode}-${w}.png`})};
  // hints + snippets
  await C.locator('.cc-text').fill('youre welcome see http://bit.ly/abc'); await shot('hints');
  await C.locator('.cc-text').fill('/thanks'); await C.locator('.cc-text').press('Space'); console.log('snippet:',await C.locator('.cc-text').inputValue());
  await C.locator('.cc-text').fill('');
  // poll
  await C.locator('[data-r="attachBtn"]').click(); await C.locator('.cc-sheet-item[data-kind="poll"]').click(); await C.locator('[data-r="pollQuestion"]').fill('Lunch?');
  const opts=C.locator('.cc-poll-opt'); await opts.nth(0).fill('Rice'); await opts.nth(1).fill('Beans'); await shot('poll'); await C.locator('[data-r="pollCreate"]').click();
  // contact
  await C.locator('[data-r="attachBtn"]').click(); await C.locator('.cc-sheet-item[data-kind="contact"]').click(); await C.locator('[data-r="contactName"]').fill('Ada'); await C.locator('[data-r="contactPhone"]').fill('+2348012345678'); await C.locator('[data-r="contactAdd"]').click();
  // location (one-time)
  await C.locator('[data-r="attachBtn"]').click(); await C.locator('.cc-sheet-item[data-kind="location"]').click(); await C.locator('[data-r="pDialog"] .cc-chip:has-text("One-time pin")').click(); await p.waitForTimeout(600);
  console.log('attachments:',await C.locator('.cc-att').count());
  // schedule
  await C.locator('[data-r="schedBtn"]').click(); const t=new Date(Date.now()+3600e3); const pad=n=>String(n).padStart(2,'0');
  await C.locator('[data-r="schedInput"]').fill(`${t.getFullYear()}-${pad(t.getMonth()+1)}-${pad(t.getDate())}T${pad(t.getHours())}:${pad(t.getMinutes())}`); await C.locator('[data-r="schedSet"]').click();
  console.log('sched active:',await C.locator('[data-r="schedBtn"].cc-active').count()); await shot('attachments');
  // GIF maker from images
  await C.locator('[data-r="gifBtn"]').click(); await shot('gif-empty');
  const mk=(c)=>{ const buf=require('child_process').execSync(`python3 -c "from PIL import Image;import sys,io;im=Image.new('RGB',(40,30),${c});b=io.BytesIO();im.save(b,'PNG');sys.stdout.buffer.write(b.getvalue())"`); return buf};
  const [fc]=await Promise.all([p.waitForEvent('filechooser'),C.locator('[data-r="gImg"]').click()]);
  await fc.setFiles([{name:'a.png',mimeType:'image/png',buffer:mk('(220,40,40)')},{name:'b.png',mimeType:'image/png',buffer:mk('(40,160,60)')},{name:'c.png',mimeType:'image/png',buffer:mk('(40,60,220)')}]);
  await C.locator('[data-r="pGifMaker"] input').first().fill('rgb dance'); await shot('gif-maker'); await C.locator('[data-r="pGifMaker"] .cc-primary').click();
  await p.waitForSelector('.cc-gcell img',{timeout:8000}); await shot('gif-saved'); console.log('saved gifs:',await C.locator('.cc-gcell').count());
  // send saved GIF (one tap) -> undo ring then payload
  await C.locator('.cc-gcell').first().click(); await p.waitForTimeout(3600); console.log('payload:',(await p.locator('pre').innerText()).replace(/\s+/g,' '));
  await b.close();
})();
