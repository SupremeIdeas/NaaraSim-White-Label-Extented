// endpoint mode: multipart POST + Idempotency-Key, offline queue, retry, 500 backoff, 422 fatal -> message returned; two composers on one page.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); const ctx=await b.newContext({viewport:{width:900,height:1000}}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message));
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','supremeideasz@gmail.com');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/adminmaster/ui-kit',{waitUntil:'networkidle'}); await p.waitForSelector('naara-composer .cc-box');
  const calls=[]; let mode='ok';
  await p.route('**/__cc_test',async r=>{const req=r.request(); calls.push({key:req.headers()['idempotency-key'],ct:(req.headers()['content-type']||'').split(';')[0],body:req.postData()?.length||0}); 
    if(mode==='ok') r.fulfill({status:200,body:'{}'}); else if(mode==='500') r.fulfill({status:500,body:'x'}); else r.fulfill({status:422,body:'x'});});
  await p.evaluate(()=>{const el=document.createElement('naara-composer');el.id='second';el.setAttribute('data-config',JSON.stringify({endpoint:'/__cc_test',convoId:'http-test',undoMs:200,features:['emoji','attach']}));document.querySelector('section').append(el)});
  await p.waitForSelector('#second .cc-box');
  const S=p.locator('#second'), F=p.locator('naara-composer:not(#second)');
  // isolation: type in second, first untouched
  await S.locator('.cc-text').fill('second only'); console.log('first untouched:',JSON.stringify(await F.locator('.cc-text').inputValue()));
  await S.locator('.cc-text').press('Enter'); await p.waitForTimeout(900);
  console.log('online send -> calls:',calls.length,calls[0]&&calls[0].ct,'idempotency key set:',!!(calls[0]&&calls[0].key)); console.log('status:',await S.locator('.cc-status').innerText());
  // offline queue
  await ctx.setOffline(true); await S.locator('.cc-text').fill('queued offline'); await S.locator('.cc-text').press('Enter'); await p.waitForTimeout(900);
  console.log('offline status:',await S.locator('.cc-status').innerText()); const before=calls.length;
  await ctx.setOffline(false); await p.evaluate(()=>window.dispatchEvent(new Event('online'))); await p.waitForTimeout(1800);
  console.log('after reconnect calls +',calls.length-before,'| status:',await S.locator('.cc-status').innerText());
  // reload survives? (queued then reload)
  await ctx.setOffline(true); await S.locator('.cc-text').fill('survives reload'); await S.locator('.cc-text').press('Enter'); await p.waitForTimeout(700);
  await ctx.setOffline(false);
  // 500 -> backoff retry
  mode='500'; await S.locator('.cc-text').fill('server error'); await S.locator('.cc-text').press('Enter'); await p.waitForTimeout(1500);
  console.log('500 status:',await S.locator('.cc-status').innerText());
  // 422 -> fatal: returned to the composer
  mode='422'; await S.locator('.cc-text').fill('rejected'); await S.locator('.cc-text').press('Enter'); await p.waitForTimeout(3500);
  console.log('422 status:',await S.locator('.cc-status').innerText(),'| text restored:',JSON.stringify(await S.locator('.cc-text').inputValue()));
  await p.screenshot({path:'out/cc-http.png'});
  await b.close();
})();
