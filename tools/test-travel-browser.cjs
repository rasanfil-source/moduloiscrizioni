const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const file=name=>path.join(assets,process.env.MI_TEST_MIN?'min':'',name);
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  for(const width of [390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.route('https://travel.invalid/**',route=>route.fulfill({json:{rides:[{id:15,origin:'Roma, Piazza Esempio',departure:'10/12/2026 08:00',seats:2,price_cents:1200},{id:16,origin:'<img src=x onerror=alert(1)>',departure:'10/12/2026 09:00',seats:1,price_cents:0}]}}));
   await page.goto('https://travel.invalid/');await page.setContent('<!doctype html><html><meta name="viewport" content="width=device-width"><body><form><div id="row"></div><button id="submit">Invia</button></form></body></html>');
   await page.addStyleTag({path:file('public.css')});await page.addScriptTag({path:file('travel.js')});
   await page.evaluate(()=>{globalThis.config={endpoint:'https://travel.invalid/events/42/registrations',event:{travel:{enabled:true,price_mode:'FIXED',price_cents:1200,instructions:'Viaggio di prova'}}};document.querySelector('#row').append(MITravel.render(config,{},0));});
   const field=key=>page.locator(`[data-travel="${key}"]`);
   assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Missing mode accepted by browser');
   await field('mode').selectOption('DRIVER');await field('seats').selectOption('0');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true,'Zero seats should not require route');
   await field('seats').selectOption('2');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Offered seats without details accepted');
   await field('origin').fill('Roma, Piazza Esempio');await field('departure').fill('10/12/2026 08:00');assert.equal(await field('price').inputValue(),'12');assert.equal(await field('price').getAttribute('readonly'),'');
   assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true);
   let captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.seats,'2');assert.equal(captured.mode,'DRIVER');
   await field('mode').selectOption('NEED');await page.waitForFunction(()=>document.querySelector('[data-travel="driver_id"]').options.length===3);
   assert.equal(await page.locator('#row img').count(),0,'Availability data interpreted as HTML');
   await field('fallback').selectOption('REQUIRED');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Neither ride nor waitlist consent accepted');
   await field('driver_id').selectOption('15');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true);captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.driver_id,15);assert.equal(captured.price_cents,1200);assert.equal(captured.fallback,'REQUIRED');
   await field('driver_id').selectOption('0');await field('waitlist').check();await field('wanted_origin').fill('Roma');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true);captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.waitlist,true);assert.equal(captured.origin,'Roma');
   // Re-render as the main form does when moving between steps.
   await page.evaluate(previous=>{document.querySelector('#row').replaceChildren(MITravel.render(config,previous,0));},captured);
   await page.waitForFunction(()=>document.querySelector('[data-travel="driver_id"]').options.length===3);assert.equal(await field('waitlist').isChecked(),true);assert.equal(await field('wanted_origin').inputValue(),'Roma');
   await field('mode').selectOption('PUBLIC');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true,'Hidden travel inputs block public transport');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Mobile overflow');assert.deepEqual(errors,[]);
   await page.close();
  }
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));let submitted;
  await page.route('https://travel.invalid/**',route=>{
   if(route.request().method()==='POST'){submitted=route.request().postDataJSON();return route.fulfill({json:{status:'TRAVEL_PENDING',order_code:'SYN1',economic_summary:{total_cents:2000,initial_due_cents:0}}});}
   return route.fulfill({json:{rides:[]}});
  });
  await page.goto('https://travel.invalid/');
  const config={endpoint:'https://travel.invalid/events/42/registrations',homeUrl:'https://travel.invalid/',event:{id:42,identifier_display:'NONE',pricing_mode:'FIXED',fixed_price_cents:2000,economic_mode:'FULL_PAYMENT',payment_methods:['CASH'],ticket_types:[{code:'standard',name:'Partecipazione',price_cents:2000}],options:[],participant_fields:[],travel:{enabled:true,price_mode:'FIXED',price_cents:1200}}};
  await page.setContent(`<!doctype html><html><body><section class="mi-registration"><form class="mi-registration__form"><section data-mi-step="1"><h2>Iscrizioni</h2><input data-mi-ticket="standard" value="1" max="20"></section><section data-mi-step="2" hidden><h2>Contatti</h2><input name="buyerFirstName" required><input name="buyerLastName" required><input type="email" name="buyerEmail"><input type="tel" name="buyerPhone" required><div data-mi-participants></div></section><section data-mi-step="3" hidden><h2>Conferma</h2><input type="checkbox" name="privacyAccepted" required><input name="website"></section><div data-mi-economic-summary><span data-mi-total></span><p data-mi-initial-row><span data-mi-initial-label></span><span data-mi-initial></span></p><p data-mi-balance-row><span data-mi-balance></span></p><p data-mi-payment-methods-row><span data-mi-payment-methods></span></p><p data-mi-economic-note></p></div><p data-mi-error hidden></p><div class="mi-registration__action-bar"><span data-mi-sticky-summary></span><button type="button" data-mi-back>Indietro</button><button type="button" data-mi-next>Continua</button><button type="submit" class="mi-registration__submit">Invia iscrizione</button></div></form><div data-mi-success hidden></div></section></body></html>`);
  await page.locator('.mi-registration').evaluate((el,config)=>el.dataset.miConfig=JSON.stringify(config),config);
  for(const name of ['core.js','travel.js','public.js'])await page.addScriptTag({path:file(name)});
  await page.locator('[data-mi-next]').click();await page.locator('[name="buyerFirstName"]').fill('Ada');await page.locator('[name="buyerLastName"]').fill('Esempio');await page.locator('[name="buyerPhone"]').fill('+390000000000');
  await page.locator('[data-travel="mode"]').selectOption('NEED');await page.locator('[data-travel="fallback"]').selectOption('REQUIRED');await page.locator('[data-travel="waitlist"]').check();await page.locator('[data-travel="wanted_origin"]').fill('Roma');
  assert.equal(await page.locator('[name="buyerEmail"]').evaluate(el=>el.required),true);await page.locator('[name="buyerEmail"]').fill('ada@example.invalid');
  await page.locator('[data-mi-next]').click();assert.match(await page.locator('[data-mi-confirmation-summary]').innerText(),/Nessun pagamento/);assert.equal(await page.locator('.mi-registration__payment-instructions-note').isHidden(),true);
  await page.locator('[name="privacyAccepted"]').check();await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-success]').waitFor({state:'visible'});
  assert.equal(submitted.participants[0].travel.waitlist,true);assert.equal(submitted.participants[0].travel.fallback,'REQUIRED');assert.equal(submitted.participants[0].travel.origin,'Roma');assert.match(await page.locator('[data-mi-success]').innerText(),/subordinata al passaggio/);assert.deepEqual(errors,[]);await page.close();
  console.log('PASS travel browser: desktop/mobile, conditional fields, zero seats, fixed contribution, safe availability, waitlist consent, retained selections and full form submission/payment suppression'+(process.env.MI_TEST_MIN?' (minified)':''));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
