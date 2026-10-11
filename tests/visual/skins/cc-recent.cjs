// Recent photos: real thumbnails of what this member attached on this device, scoped per account; Browse tile; Clear.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const fs=require('fs'); const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); const ctx=await b.newContext({viewport:{width:430,height:900}}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message));
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/support',{waitUntil:'networkidle'}); await p.waitForSelector('naara-composer .cc-box');
  const open=async()=>{await p.click('[data-r="attachBtn"]'); await p.waitForTimeout(500)};
  await open(); console.log('empty: thumbs',await p.locator('.cc-rthumb:not(.cc-rbrowse)').count(),'| hint:',await p.locator('.cc-rempty').innerText().catch(()=>'-')); await p.click('[data-r="attachBtn"]');
  for (const n of ['sunset','ocean']) {
    await open(); const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.click('.cc-sheet-item[data-kind="media"]')]); await fc.setFiles(`/tmp/${n}.jpg`);
    await p.waitForSelector('.cc-att img'); await p.waitForTimeout(600); await p.click('.cc-rm');
  }
  await open(); console.log('after 2 attaches: thumbs',await p.locator('.cc-rthumb:not(.cc-rbrowse)').count()); await p.screenshot({path:'out/cc-recent.png'});
  await p.locator('.cc-rthumb:not(.cc-rbrowse)').first().click(); await p.waitForTimeout(600); console.log('tap recent -> attached:',await p.locator('.cc-att img').count());
  await p.reload({waitUntil:'networkidle'}); await p.waitForSelector('naara-composer .cc-box'); await open(); console.log('after reload: thumbs',await p.locator('.cc-rthumb:not(.cc-rbrowse)').count());
  await p.click('.cc-link'); await p.waitForTimeout(400); console.log('after Clear: thumbs',await p.locator('.cc-rthumb:not(.cc-rbrowse)').count());
  await b.close();
})();
