let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); const ctx=await b.newContext({viewport:{width:1280,height:900},colorScheme:'dark'}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message)); p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/adminmaster/tickets',{waitUntil:'networkidle'}); console.log(p.url());
  await p.locator('button[wire\\:click^="select"]').first().click(); await p.waitForSelector('naara-composer .cc-box',{timeout:8000});
  await p.fill('naara-composer .cc-text','Hi, this is staff replying through the composer.'); await p.press('naara-composer .cc-text','Enter'); await p.waitForTimeout(3800);
  await p.waitForSelector('text=staff replying through the composer',{timeout:8000}); console.log('staff reply delivered: OK');
  await p.screenshot({path:'out/cc-staff.png'}); await b.close();
})();
