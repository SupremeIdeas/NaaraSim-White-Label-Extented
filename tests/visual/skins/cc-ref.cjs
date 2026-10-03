let chromium;try{({chromium}=require('playwright'))}catch(e){({chromium}=require(process.env.PW_MODULE||'/opt/node22/lib/node_modules/playwright'))}
const CHROME=process.env.CHROME||'/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const REF='/root/.claude/uploads/9b2fea12-c1c3-5181-b130-be5b73d77434/27e976fd-chat-composer-pro_to_replace_Naara_generic_chat_composer_in_support_center_and_other_areas_in_app.html';
(async()=>{
  const b=await chromium.launch({executablePath:CHROME});
  for (const mode of ['light','dark']) {
    const p=await (await b.newContext({viewport:{width:430,height:760},colorScheme:mode})).newPage();
    await p.goto('file://'+REF); await p.fill('#textarea','Hello there'); await p.waitForTimeout(200); await p.screenshot({path:`out/ref-typed-${mode}.png`});
    await p.click('#attachBtn'); await p.waitForTimeout(300); await p.screenshot({path:`out/ref-attach-${mode}.png`});
    await p.click('#attachBtn'); await p.click('#emojiBtn'); await p.waitForTimeout(300); await p.screenshot({path:`out/ref-emoji-${mode}.png`});
  }
  await b.close();
})();
