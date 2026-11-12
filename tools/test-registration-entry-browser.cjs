const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets',process.env.MI_TEST_MIN?'min':'');
const php=process.env.MI_PHP_BINARY||path.resolve('.tmp/php-runtime/php.exe');
const markup=execFileSync(php,['wordpress-plugin/tests/public-travel-preview.php'],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  for(const width of [390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}}),errors=[],submitted=[];
   page.on('pageerror',error=>errors.push(error.message));
   await page.route('https://entry.invalid/**',route=>{
    if(route.request().method()==='POST'){
     submitted.push(route.request().postDataJSON());
     return route.fulfill({json:{status:'CONFIRMED',order_code:'TEST',economic_summary:{total_cents:0}}});
    }
    return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="it"><meta name="viewport" content="width=device-width"><body>'+markup+'</body></html>'});
   });
   await page.goto('https://entry.invalid/');
   await page.locator('.mi-registration').evaluate(root=>{
    const config=JSON.parse(root.dataset.miConfig);
    config.preview=false;config.endpoint='https://entry.invalid/register';config.homeUrl='https://entry.invalid/';
    Object.assign(config.event,{travel:{enabled:false},identifier_display:'NONE',pricing_mode:'ZERO',economic_mode:'REGISTRATION_ONLY',special_requests_enabled:true,participant_fields:[
     {key:'email',label:'Email',type:'email'}, {key:'phone',label:'Cellulare',type:'tel'},
     {key:'custom_tail',label:'Nota breve',type:'text'}
    ]});
    root.dataset.miConfig=JSON.stringify(config);
   });
   await page.addStyleTag({path:path.join(assets,'public.css')});
   for(const file of ['core.js','public.js'])await page.addScriptTag({path:path.join(assets,file)});
   const contact=name=>page.locator(`[name="buyer${name}"]`);
   const first=()=>page.locator('.mi-registration__participant').first();
   const participant=key=>first().locator(key==='firstName'?'[data-mi-first-name]':key==='lastName'?'[data-mi-last-name]':`[data-mi-participant-field="${key}"]`);
   const focused=async locator=>assert.ok(await locator.evaluate(input=>input===document.activeElement),'Focus inatteso');
   await page.locator('[data-mi-next]').click();
   await contact('FirstName').press('Enter');await focused(contact('FirstName'));
   assert.equal(await contact('FirstName').getAttribute('aria-invalid'),'true');
   await contact('FirstName').fill('Ada');await contact('FirstName').press('Enter');await focused(contact('LastName'));
   await contact('LastName').fill('De Luca');await contact('LastName').press('Enter');await focused(contact('Email'));
   await contact('Email').fill('ada@');await contact('Email').press('Enter');await focused(contact('Email'));
   await contact('Email').fill('');await contact('Email').pressSequentially('ada@example.invalid');
   assert.equal(await participant('email').inputValue(),'ada@example.invalid','Copia email interrotta alla prima lettera');
   await contact('Email').press('Enter');await focused(contact('Phone'));
   await contact('Phone').fill('123');await contact('Phone').press('Enter');await focused(contact('Phone'));
   await contact('Phone').fill('');await contact('Phone').pressSequentially('3331234567');
   assert.equal(await participant('phone').inputValue(),'3331234567','Copia telefono interrotta alla prima cifra');
   await contact('Phone').press('Enter');await focused(participant('firstName'));
   assert.equal(await participant('phone').inputValue(),'+39 333 1234567');
   assert.equal(await participant('phone').evaluate(input=>input.checkValidity()),true,'Validità del telefono non aggiornata');
   assert.equal(await contact('FirstName').getAttribute('enterkeyhint'),'next');
   assert.equal(await participant('firstName').getAttribute('enterkeyhint'),'next');
   await participant('firstName').press('Enter');await focused(participant('lastName'));
   await participant('lastName').evaluate(input=>{
    const held=new KeyboardEvent('keydown',{key:'Enter',repeat:true,bubbles:true,cancelable:true});input.dispatchEvent(held);
    const ime=new KeyboardEvent('keydown',{key:'Enter',isComposing:true,bubbles:true,cancelable:true});input.dispatchEvent(ime);
   });
   await focused(participant('lastName'));
   await participant('lastName').evaluate(input=>{
    for(const kind of ['hidden','disabled','readonly']){const extra=document.createElement('input');extra.dataset.entryTest=kind;if(kind==='hidden')extra.hidden=true;if(kind==='disabled')extra.disabled=true;if(kind==='readonly')extra.readOnly=true;input.after(extra);}
   });
   await participant('lastName').press('Enter');await focused(participant('email'));
   await page.locator('[data-entry-test]').evaluateAll(inputs=>inputs.forEach(input=>input.remove()));
   // Browser/autofill updates without input/change must be picked up on focus.
   await contact('Email').evaluate(input=>input.value='nuova@example.invalid');
   await participant('phone').focus();await participant('email').focus();
   assert.equal(await participant('email').inputValue(),'nuova@example.invalid');
   await participant('firstName').fill('Elena');await participant('email').fill('elena@example.invalid');
   await contact('FirstName').fill('Ada Maria');await contact('Email').fill('referente@example.invalid');
   assert.equal(await participant('firstName').inputValue(),'Elena');assert.equal(await participant('email').inputValue(),'elena@example.invalid');
   // An intentional empty value is also preserved.
   await participant('lastName').fill('');await contact('LastName').fill('Rossi');await participant('lastName').focus();
   assert.equal(await participant('lastName').inputValue(),'');await participant('lastName').fill('Bianchi');
   await page.locator('[data-mi-back]').click();await page.locator('[data-mi-ticket]').fill('2');await page.locator('[data-mi-next]').click();
   assert.equal(await participant('firstName').inputValue(),'Elena');assert.equal(await participant('lastName').inputValue(),'Bianchi');
   assert.equal(await participant('email').inputValue(),'elena@example.invalid');
   const second=page.locator('.mi-registration__participant').nth(1);
   assert.equal(await second.locator('[data-mi-first-name]').inputValue(),'');assert.equal(await second.locator('[data-mi-participant-field="email"]').inputValue(),'');
   await second.locator('[data-mi-first-name]').fill('Luca');await second.locator('[data-mi-last-name]').fill('Verdi');
   await contact('Phone').fill('3397654321');await contact('Phone').press('Tab');
   assert.equal(await participant('phone').inputValue(),'+39 339 7654321','Copia automatica persa dopo ricostruzione');
   assert.equal(await second.locator('[data-mi-participant-field="phone"]').inputValue(),'');
   await second.locator('[data-mi-participant-field="custom_tail"]').press('Enter');
   await focused(page.locator('[data-mi-next]'));assert.equal(submitted.length,0);
   await contact('Phone').evaluate(input=>input.value='3312223333');
   await page.locator('[data-mi-next]').press('Enter');await page.locator('[data-mi-confirmation-summary]').waitFor();
   assert.equal(await participant('phone').inputValue(),'+39 331 2223333','Autocompletamento non letto prima della validazione');
   const notes=page.locator('[name=specialRequests]');await notes.fill('Prima riga');await notes.press('Enter');
   assert.equal(await notes.inputValue(),'Prima riga\n');await focused(notes);assert.equal(submitted.length,0);
   await page.locator('[name=privacyAccepted]').check();
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Overflow');
   await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-success]').waitFor({state:'visible'});
   assert.equal(submitted.length,1);assert.equal(submitted[0].buyer.first_name,'Ada Maria');
   assert.equal(submitted[0].participants[0].first_name,'Elena');assert.equal(submitted[0].participants[0].fields.email,'elena@example.invalid');
   assert.equal(submitted[0].participants[0].fields.phone,'+39 331 2223333');
   assert.equal(submitted[0].participants[1].first_name,'Luca');assert.deepEqual(errors,[]);
   await page.close();
  }
  console.log('PASS: Invio/Avanti, validazione, IME, note multilinea, copia completa/focus, modifiche manuali, cambio quantità e payload; mobile/desktop'+(process.env.MI_TEST_MIN?' (minificato)':''));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
