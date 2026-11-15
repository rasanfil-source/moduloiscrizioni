// Esegue i percorsi reali anche rimuovendo requestSubmit dal browser.
const fs=require('fs'),assert=require('node:assert/strict');
const playwright=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets='wordpress-plugin/modulo-iscrizioni/assets/';
const script=name=>fs.readFileSync(assets+(process.env.MI_TEST_MIN?'min/':'')+name,'utf8');
(async()=>{
 const browser=await playwright.chromium.launch({channel:'msedge',headless:true});
 try{
  for(const legacy of [false,true]){
   const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.setContent('<form id="sample"><input name="email" type="email" required><button name="operation" value="save">Salva</button><button name="operation" value="skip" formnovalidate>Ignora</button></form>');
   if(legacy)await page.evaluate(()=>{HTMLFormElement.prototype.requestSubmit=undefined;});
   await page.addScriptTag({content:script('form-submit.js')});
   await page.evaluate(()=>{window.submissions=[];document.addEventListener('submit',e=>{e.preventDefault();submissions.push({value:e.submitter?.value,operation:new FormData(e.target,e.submitter).get('operation')});});MIFormSubmit(document.querySelector('form'));});
   assert.equal(await page.evaluate(()=>submissions.length),0,'Invalid form submitted');
   await page.locator('[name=email]').fill('chosen@example.invalid');
   await page.evaluate(()=>MIFormSubmit(document.querySelector('form'),document.querySelector('[value=save]')));
   assert.deepEqual(await page.evaluate(()=>submissions),[{value:'save',operation:'save'}]);
   await page.locator('[name=email]').fill('');await page.evaluate(()=>MIFormSubmit(document.querySelector('form'),document.querySelector('[value=skip]')));
   assert.equal(await page.evaluate(()=>submissions.at(-1).operation),'skip','formnovalidate lost');
   await page.locator('[name=email]').fill('chosen@example.invalid');await page.evaluate(()=>MIFormSubmit(document.querySelector('form')));
   assert.equal(await page.locator('button').count(),2,'Temporary submitter left behind');
   // Conferma reale del portale: il secondo submit deve raggiungere il gestore una volta.
   await page.setContent('<form data-mi-confirm="Confermi?" id="confirmed"><input name="note" required value="ok"><button name="operation" value="delete">Elimina</button></form>');
   await page.addScriptTag({content:script('portal-management.js')});
   await page.evaluate(()=>{window.confirmed=[];document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();confirmed.push(e.submitter.value);});});
   await page.locator('form > button').click();await page.locator('dialog [value=accept]').click();
   await page.waitForFunction(()=>confirmed.length===1);
   assert.deepEqual(await page.evaluate(()=>confirmed),['delete']);
   // Filtri delle iscrizioni: stesso evento submit, senza navigazione nativa aggiuntiva.
   await page.setContent('<form class="mi-registrations-toolbar"><select data-mi-auto-submit><option value="a">A</option><option value="b">B</option></select></form>');
   await page.evaluate(()=>{window.filtered=0;document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();filtered++;});});
   await page.addScriptTag({content:script('portal.js')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
   await page.locator('select').selectOption('b');assert.equal(await page.evaluate(()=>filtered),1);
   await page.setContent('<section data-mi-pwa-install><form><select name="mi_pwa_group"><option value="a">A</option><option value="b">B</option></select></form></section>');
   await page.evaluate(()=>{window.groups=0;document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();groups++;});});
   await page.addScriptTag({content:script('portal-pwa.js')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
   await page.locator('select').selectOption('b');assert.equal(await page.evaluate(()=>groups),1);
   assert.deepEqual(errors,[]);await page.close();
  }
  console.log('PASS native/fallback: required fields, cancellation/AJAX, submitter, formnovalidate, confirmation and registration filters.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
