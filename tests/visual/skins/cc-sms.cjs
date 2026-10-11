let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME,args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream']}); const mode=process.env.MODE||'light';
  const ctx=await b.newContext({viewport:{width:430,height:900},colorScheme:mode,permissions:['microphone']}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message)); p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/numbers/lines',{waitUntil:'networkidle'});
  console.log(p.url(), await p.evaluate(()=>typeof window.Livewire)); await p.evaluate(()=>window.Livewire.dispatch('open-send-message',{to:'+12025550199',name:'Ada'}));
  await p.waitForSelector('naara-composer .cc-box',{timeout:8000});
  await p.fill('naara-composer .cc-text','Hello from the composer'); await p.waitForTimeout(1200);
  console.log('server body synced:',await p.evaluate(()=>{const w=document.querySelector('[wire\\:id] naara-composer')?.closest('[wire\\:id]'); return window.Livewire.find(w.getAttribute('wire:id')).body}));
  console.log('quote shown:',await p.locator('text=/Cost to send/').count());
  // photo
  await p.click('[data-r="attachBtn"]'); const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.click('.cc-sheet-item[data-kind="media"]')]);
  await fc.setFiles({name:'pic.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==','base64')});
  await p.waitForTimeout(1500);
  console.log('server attachment set:',await p.evaluate(()=>{const w=document.querySelector('naara-composer').closest('[wire\\:id]'); return !!window.Livewire.find(w.getAttribute('wire:id')).attachment}));
  await p.screenshot({path:`out/cc-sms-${mode}.png`});
  console.log('send button text:',(await p.locator('button:has-text("Send with photo")').count())?'Send with photo':'(other)');
  await b.close();
})();
