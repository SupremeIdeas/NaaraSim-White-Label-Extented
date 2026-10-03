// Chat Composer Pro on /support: MODE=light|dark W=430. Screenshots: empty, typed, emoji panel, attach sheet.
let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome', BASE='http://127.0.0.1:8099';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME}); const mode=process.env.MODE||'light', w=+(process.env.W||430);
  const ctx=await b.newContext({viewport:{width:w,height:900},colorScheme:mode,permissions:[]}); const p=await ctx.newPage();
  p.on('pageerror',e=>console.log('PAGEERROR',e.message)); p.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text())});
  await p.goto(BASE+'/login',{waitUntil:'networkidle'});await p.fill('input[name="email"]','nx-diff@naara.test');await p.fill('input[name="password"]','password');
  await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type="submit"]')]);
  await p.goto(BASE+(process.env.URL||'/support'),{waitUntil:'networkidle'}); console.log(p.url());
  await p.waitForSelector('naara-composer .cc-box',{timeout:8000});
  const shot=async n=>{await p.waitForTimeout(250);await p.screenshot({path:`out/cc-${n}-${mode}-${w}.png`})};
  await shot('empty');
  await p.fill('naara-composer .cc-text','Hello, my eSIM will not activate.\nCan you help?'); await shot('typed');
  await p.click('[data-r="emojiBtn"]'); await p.waitForSelector('.cc-egrid button'); await shot('emoji'); await p.click('.cc-egrid button >> nth=2'); 
  console.log('text after emoji:',JSON.stringify(await p.inputValue('naara-composer .cc-text')));
  await p.click('[data-r="emojiBtn"]'); await p.click('[data-r="attachBtn"]'); await shot('attach');
  await b.close();
})();
