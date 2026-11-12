const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets',process.env.MI_TEST_MIN?'min':'');
const markup=execFileSync(path.resolve('.tmp/php-runtime/php.exe'),['wordpress-plugin/tests/public-travel-preview.php'],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  async function setup(width,options={}){
   const page=await browser.newPage({viewport:{width,height:900}}),submitted=[],errors=[];
   page.on('pageerror',error=>errors.push(error.message));
   await page.route('https://waitlist.invalid/**',route=>{
    if(route.request().method()==='POST'){
     submitted.push({body:route.request().postDataJSON(),key:route.request().headers()['x-idempotency-key']});
     if(options.stale&&submitted.length===1)return route.fulfill({status:409,json:{code:'mi_waitlist_confirmation_required',message:'Disponibilità cambiata. Nessuna iscrizione registrata.',data:{availability:{remaining:1,waitlist_remaining:5,ticket_types:{standard:{remaining:null}}},waitlist_enabled:true}}});
     return route.fulfill({json:{status:submitted.at(-1).body.waitlist_accepted?'WAITLISTED':'CONFIRMED',order_code:'TEST',economic_summary:{total_cents:0}}});
    }
    return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="it"><meta name="viewport" content="width=device-width"><body>'+markup+'</body></html>'});
   });
   await page.goto('https://waitlist.invalid/');
   await page.locator('.mi-registration').evaluate((root,options)=>{
    const config=JSON.parse(root.dataset.miConfig);config.preview=false;config.endpoint='https://waitlist.invalid/register';config.homeUrl='https://waitlist.invalid/';
    Object.assign(config.event,{travel:{enabled:false},identifier_display:'NONE',pricing_mode:'ZERO',economic_mode:'REGISTRATION_ONLY',participant_fields:[],waitlist_enabled:!options.disabled,waitlist_capacity:5,
     availability:{remaining:options.stale?4:(options.remaining??1),waitlist_remaining:options.queue??5,ticket_types:{standard:{remaining:options.typeLimit??null}}}});
    root.dataset.miConfig=JSON.stringify(config);
   },options);
   await page.addStyleTag({path:path.join(assets,'public.css')});
   for(const file of ['core.js','public.js'])await page.addScriptTag({path:path.join(assets,file)});
   return {page,submitted,errors};
  }
  async function fillContact(page){
   for(const [name,value] of Object.entries({FirstName:'Ada',LastName:'Rossi',Email:'ada@example.invalid',Phone:'3331234567'}))await page.locator(`[name="buyer${name}"]`).fill(value);
   const participants=page.locator('.mi-registration__participant');
   if(await participants.count()>1){await participants.nth(1).locator('[data-mi-first-name]').fill('Luca');await participants.nth(1).locator('[data-mi-last-name]').fill('Verdi');}
   await page.locator('[data-mi-next]').click();await page.locator('[name=privacyAccepted]').check();
  }
  for(const width of [320,390,1280]){
   const {page,submitted,errors}=await setup(width);
   await page.locator('[data-mi-ticket]').fill('2');
   const notice=page.locator('[data-mi-capacity-choice]'),choice=page.locator('[data-mi-waitlist-choice]');
   await notice.waitFor({state:'visible'});assert.match(await notice.innerText(),/disponibile solo 1 posto/);
   await page.locator('[data-mi-next]').click();assert.equal(await page.locator('[data-mi-step="1"]').isVisible(),true);
   assert.equal(await choice.getAttribute('aria-invalid'),'true');assert.equal(submitted.length,0);
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow scelta capienza');
   await choice.check();await page.locator('[data-mi-ticket]').fill('3');assert.equal(await choice.isChecked(),false,'Scelta da ripetere al cambio del gruppo');
   await page.locator('[data-mi-reduce-selection]').click();assert.equal(await page.locator('[data-mi-ticket]').inputValue(),'1');assert.equal(await notice.isVisible(),false);
   await page.locator('[data-mi-next]').click();await fillContact(page);
   assert.equal(await page.locator('.mi-registration__submit').innerText(),'Invia iscrizione');
   await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-success]').waitFor({state:'visible'});
   assert.equal(submitted[0].body.waitlist_accepted,false);assert.equal(submitted[0].body.participants.length,1);assert.deepEqual(errors,[]);await page.close();
  }
  for(const stale of [false,true]){
   const {page,submitted,errors}=await setup(390,{stale});
   await page.locator('[data-mi-ticket]').fill('2');if(!stale)await page.locator('[data-mi-waitlist-choice]').check();
   await page.locator('[data-mi-next]').click();
   if(!stale)assert.equal(await page.locator('[name=buyerEmail]').getAttribute('required'),'');
   await fillContact(page);
   if(stale){
    await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-capacity-choice]').waitFor({state:'visible'});
    assert.equal(submitted.length,1);assert.equal(submitted[0].body.waitlist_accepted,false);
    assert.equal(await page.locator('[data-mi-waitlist-choice]').isChecked(),false);
    await page.locator('[data-mi-waitlist-choice]').check();await page.locator('[data-mi-next]').click();
    assert.equal(await page.locator('.mi-registration__participant').nth(1).locator('[data-mi-first-name]').inputValue(),'Luca','Dati conservati dopo cambio capienza');
    await page.locator('[data-mi-next]').click();
   }
   assert.match(await page.locator('[data-mi-confirmation-summary]').innerText(),/Nessun posto è ancora confermato/);
   assert.equal(await page.locator('.mi-registration__submit').innerText(),'Invia richiesta di lista d’attesa');
   assert.equal(await page.locator('.mi-registration__payment-instructions-note').isVisible(),false);
   await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-success]').waitFor({state:'visible'});
   assert.equal(submitted.at(-1).body.waitlist_accepted,true);assert.equal(submitted.at(-1).body.participants.length,2);
   if(stale)assert.equal(submitted[0].key,submitted[1].key,'Retry con stessa chiave di idempotenza');
   assert.deepEqual(errors,[]);await page.close();
  }
  for(const options of [{queue:1},{disabled:true},{remaining:5,typeLimit:1},{remaining:0}]){
   const {page,submitted,errors}=await setup(390,options);await page.locator('[data-mi-ticket]').fill('2');
   await page.locator('[data-mi-capacity-choice]').waitFor({state:'visible'});
   const blocked=options.queue===1||options.disabled;
   assert.equal(await page.locator('[data-mi-waitlist-choice]').isVisible(),!blocked);
   if(blocked){await page.locator('[data-mi-next]').click();assert.equal(await page.locator('[data-mi-step="1"]').isVisible(),true);}
   if(options.typeLimit)assert.match(await page.locator('[data-mi-capacity-choice]').innerText(),/disponibile solo 1 posto/);
   if(options.remaining===0)assert.equal(await page.locator('[data-mi-reduce-selection]').isVisible(),false);
   assert.equal(submitted.length,0);assert.deepEqual(errors,[]);await page.close();
  }
  console.log('PASS: capienza mobile/desktop, riduzione, accettazione esplicita, email obbligatoria, riepilogo, cambio capienza senza perdita dati, limiti tipo/coda'+(process.env.MI_TEST_MIN?' (minificato)':''));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
