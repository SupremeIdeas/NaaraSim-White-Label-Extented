// End-to-end on /support: text send (with undo window), evidence file, voice note (fake mic), validation failure restores the message.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME,args:['--use-fake-device-for-media-stream','--use-fake-ui-for-media-stream']});
  const ctx=await b.newContext({viewport:{width:430,height:900},permissions:['microphone']}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message)); p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+'/support',{waitUntil:'networkidle'}); await p.waitForSelector('naara-composer .cc-box');
  const bubbles=()=>p.locator('main [wire\\:id] >> text=/Flow test|\\[Voice note\\]|Attached/').count();
  // 1) text + undo
  await p.fill('naara-composer .cc-text','Flow test one'); await p.press('naara-composer .cc-text','Enter');
  console.log('undo ring shown:',await p.locator('.cc-send.cc-undo').count());
  await p.click('.cc-send.cc-undo'); console.log('after undo, text back:',JSON.stringify(await p.inputValue('naara-composer .cc-text')));
  // 2) real send
  await p.press('naara-composer .cc-text','Enter'); await p.waitForTimeout(3600);
  await p.waitForSelector('text=Flow test one',{timeout:8000}); console.log('text message delivered via Livewire: OK');
  console.log('composer cleared:',JSON.stringify(await p.inputValue('naara-composer .cc-text')));
  // 3) evidence image
  await p.click('[data-r="attachBtn"]');
  const [fc]=await Promise.all([p.waitForEvent('filechooser'),p.click('.cc-sheet-item[data-kind="media"]')]);
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==','base64');
  await fc.setFiles({name:'shot.png',mimeType:'image/png',buffer:png});
  await p.waitForSelector('.cc-att img'); await p.fill('naara-composer .cc-text','Flow test evidence'); await p.press('naara-composer .cc-text','Enter'); await p.waitForTimeout(3600);
  await p.waitForSelector('text=Flow test evidence',{timeout:8000}); console.log('evidence message delivered: OK');
  // 4) wrong type is refused client-side
  await p.click('[data-r="attachBtn"]');
  const [fc2]=await Promise.all([p.waitForEvent('filechooser'),p.click('.cc-sheet-item[data-kind="document"]')]);
  await fc2.setFiles({name:'bad.exe',mimeType:'application/x-msdownload',buffer:Buffer.from('MZ')});
  await p.waitForTimeout(400); console.log('status:',await p.locator('.cc-status').innerText().catch(()=>'(none)'));
  // 5) voice note with fake mic
  await p.click('[data-r="micBtn"]'); await p.waitForSelector('[data-r="pPrimer"]:not([hidden])'); console.log('primer text:',(await p.locator('.cc-primer-txt b').innerText()));
  await p.click('[data-r="primerYes"]'); await p.waitForSelector('.cc-voice:not([hidden])'); await p.waitForTimeout(1800);
  console.log('timer:',await p.locator('[data-r="vTimer"]').innerText());
  await p.click('[data-r="vPause"]'); await p.waitForTimeout(400); console.log('preview play visible:',await p.locator('[data-r="vPlay"]').isVisible());
  await p.screenshot({path:'out/cc-voice-paused.png'});
  await p.click('[data-r="vPause"]'); await p.waitForTimeout(600);
  await p.click('[data-r="vSend"]'); await p.waitForTimeout(4500);
  console.log('voice bubble delivered:',await p.locator('text=/Voice note/').count()>0);
  await p.screenshot({path:'out/cc-after-voice.png'});
  await b.close();
})();
