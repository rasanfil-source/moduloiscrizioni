const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const file=name=>path.join(assets,process.env.MI_TEST_MIN?'min':'',name);
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  for(const width of [390,1280]){
   const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
   let failAvailability=false,availabilityClosed=false;await page.route('https://travel.invalid/**',route=>failAvailability?route.fulfill({status:503,json:{message:'Disponibilità non disponibile.'}}):route.fulfill({json:{accepting_new_requests:!availabilityClosed,rides:[{id:15,origin:'Roma, Piazza Esempio',departure:'10/12/2026 08:00',seats:2,price_cents:1200},{id:16,origin:'<img src=x onerror=alert(1)>',departure:'10/12/2026 09:00',seats:1,price_cents:0}]}}));
   await page.goto('https://travel.invalid/');await page.setContent('<!doctype html><html><meta name="viewport" content="width=device-width"><body><form><div id="row"></div><button id="submit">Invia</button></form></body></html>');
   await page.addStyleTag({path:file('public.css')});await page.addScriptTag({path:file('travel.js')});
   await page.evaluate(()=>{globalThis.config={endpoint:'https://travel.invalid/events/42/registrations',event:{travel:{enabled:true,price_mode:'FIXED',price_cents:1200,instructions:'Viaggio di prova'}}};document.querySelector('#row').append(MITravel.render(config,{},0));});
   const field=key=>page.locator(`[data-travel="${key}"]`);
   assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Missing mode accepted by browser');
   await field('mode').selectOption('DRIVER');await field('seats').selectOption('0');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true,'Zero seats should not require route');
   await field('seats').selectOption('2');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Offered seats without details accepted');
   await field('origin').fill('Roma, Piazza Esempio');assert.equal(await field('departure').inputValue(),'','Departure defaults to the event start when blank');assert.equal(await field('price').inputValue(),'12');assert.equal(await field('price').getAttribute('readonly'),'');
   assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true);
   let captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.seats,'2');assert.equal(captured.mode,'DRIVER');
   assert.equal(await field('mode').locator('option[value=PUBLIC]').count(),0,'Redundant public transit choice remains');failAvailability=true;await field('mode').selectOption('NEED');await page.getByRole('button',{name:'Riprova'}).waitFor({state:'visible'});assert.equal(await page.locator('[data-travel="ride_offer"]').count(),0);failAvailability=false;await page.getByRole('button',{name:'Riprova'}).click();await page.waitForFunction(()=>document.querySelectorAll('[data-travel="ride_offer"]').length===2);assert.equal(await page.getByRole('button',{name:'Riprova'}).isHidden(),true);
   assert.equal(await page.locator('#row img').count(),0,'Availability data interpreted as HTML');
   await field('fallback').selectOption('REQUIRED');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),false,'Neither ride nor waitlist consent accepted');
   await field('ride_offer').first().check();assert.equal(await field('fallback').isHidden(),false,'Fallback choice hidden after selecting all required seats');assert.equal(await field('waitlist').isHidden(),true);captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.assignments[0].driver_id,15);assert.equal(captured.assignments[0].price_cents,1200);
   await field('ride_offer').first().uncheck();assert.equal(await field('fallback').isHidden(),false);assert.equal(await field('wanted_origin').count(),0,'Requested departure location should not complicate registration');assert.equal(await field('waitlist').evaluate(el=>getComputedStyle(el.closest('label')).display),'flex','Waitlist checkbox and text should share one line');await field('waitlist').check();assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true);captured=await page.locator('#row').evaluate(row=>MITravel.capture(row));assert.equal(captured.waitlist,true);assert.equal(captured.origin,'');
   // Re-render as the main form does when moving between steps.
   await page.evaluate(previous=>{document.querySelector('#row').replaceChildren(MITravel.render(config,previous,0));},captured);
   await page.waitForFunction(()=>document.querySelectorAll('[data-travel="ride_offer"]').length===2);assert.equal(await field('waitlist').isChecked(),true);assert.equal(await field('wanted_origin').count(),0);
   availabilityClosed=true;await field('mode').selectOption('OWN');await field('mode').selectOption('NEED');await page.waitForFunction(()=>document.querySelector('[data-travel="mode"] option[value="NEED"]').disabled);assert.equal(await field('mode').inputValue(),'','Closed request mode was silently kept');assert.equal(await field('closed_notice').isHidden(),false,'Closed-window explanation hidden after mode reset');assert.match(await field('closed_notice').innerText(),/non puoi inviare richieste/);assert.equal(await page.locator('[data-travel="ride_offer"]').count(),0,'Closed travel window still shows available offers');
   await field('mode').selectOption('DRIVER');assert.equal(await field('seats').locator('option[value="1"]').isDisabled(),true,'Driver can offer a new seat inside the final 24 hours');assert.equal(await field('seats').inputValue(),'0','Previously entered seats were not reset after offers closed');
   await field('mode').selectOption('OWN');assert.equal(await page.locator('form').evaluate(f=>f.checkValidity()),true,'Hidden travel inputs block autonomous travel');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Mobile overflow');assert.deepEqual(errors,[]);
   await page.close();
  }
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));let submitted;
  await page.route('https://travel.invalid/**',route=>{
   if(route.request().method()==='POST'){submitted=route.request().postDataJSON();return route.fulfill({json:{status:'TRAVEL_PENDING',order_code:'SYN1',economic_summary:{total_cents:2000,initial_due_cents:0}}});}
   return route.fulfill({json:{rides:[{id:15,origin:'Roma A',departure:'10/12/2026 08:00',seats:1,price_cents:1200},{id:16,origin:'Roma B',departure:'10/12/2026 08:10',seats:1,price_cents:1200},{id:17,origin:'Roma C',departure:'10/12/2026 08:20',seats:1,price_cents:1200}]}});
  });
  await page.goto('https://travel.invalid/');
  const config={endpoint:'https://travel.invalid/events/42/registrations',homeUrl:'https://travel.invalid/',event:{id:42,identifier_display:'NONE',pricing_mode:'FIXED',fixed_price_cents:2000,economic_mode:'FULL_PAYMENT',payment_methods:['CASH'],ticket_types:[{code:'standard',name:'Partecipazione',price_cents:2000}],options:[],participant_fields:[],travel:{enabled:true,price_mode:'FIXED',price_cents:1200}}};
  await page.setContent(`<!doctype html><html><body><section class="mi-registration"><form class="mi-registration__form"><section data-mi-step="1"><h2>Iscrizioni</h2><input data-mi-ticket="standard" value="1" max="20"></section><section data-mi-step="2" hidden><h2>Contatti</h2><input name="buyerFirstName" required><input name="buyerLastName" required><input type="email" name="buyerEmail"><input type="tel" name="buyerPhone" required><div data-mi-participants></div></section><section data-mi-step="3" hidden><h2>Conferma</h2><input type="checkbox" name="privacyAccepted" required><input name="website"></section><div data-mi-economic-summary><span data-mi-total></span><p data-mi-initial-row><span data-mi-initial-label></span><span data-mi-initial></span></p><p data-mi-balance-row><span data-mi-balance></span></p><p data-mi-payment-methods-row><span data-mi-payment-methods></span></p><p data-mi-economic-note></p></div><p data-mi-error hidden></p><div class="mi-registration__action-bar"><span data-mi-sticky-summary></span><button type="button" data-mi-back>Indietro</button><button type="button" data-mi-next>Continua</button><button type="submit" class="mi-registration__submit">Invia iscrizione</button></div></form><div data-mi-success hidden></div></section></body></html>`);
  await page.locator('.mi-registration').evaluate((el,config)=>el.dataset.miConfig=JSON.stringify(config),config);
  for(const name of ['core.js','travel.js','public.js'])await page.addScriptTag({path:file(name)});
  await page.locator('[data-mi-ticket]').fill('3');
  await page.locator('[data-mi-next]').click();await page.locator('[name="buyerFirstName"]').fill('Ada');await page.locator('[name="buyerLastName"]').fill('Esempio');await page.locator('[name="buyerPhone"]').fill('+390000000000');
  for(let index=1;index<3;index++){await page.locator('[data-mi-first-name]').nth(index).fill('Persona '+index);await page.locator('[data-mi-last-name]').nth(index).fill('Esempio');}
  assert.equal(await page.locator('[data-mi-travel]').count(),1);
   await page.locator('[data-travel="mode"]').selectOption('NEED');await page.waitForFunction(()=>document.querySelectorAll('[data-travel="ride_offer"]').length===3);await page.locator('[data-travel="ride_offer"]').evaluateAll(items=>items.slice(0,3).forEach(input=>input.checked=true));await page.locator('[data-travel="ride_offer"]').first().dispatchEvent('change');assert.equal(await page.locator('[data-travel="fallback"]').isHidden(),false);assert.equal(await page.locator('[data-travel="waitlist"]').isHidden(),true);assert.equal(await page.locator('[data-travel="wanted_origin"]').count(),0);
  assert.equal(await page.locator('[name="buyerEmail"]').evaluate(el=>el.required),true);await page.locator('[name="buyerEmail"]').fill('ada@example.invalid');
  await page.locator('[data-mi-next]').click();assert.match(await page.locator('[data-mi-confirmation-summary]').innerText(),/Nessun pagamento/);assert.equal(await page.locator('.mi-registration__payment-instructions-note').isHidden(),true);
  await page.locator('[name="privacyAccepted"]').check();await page.locator('.mi-registration__submit').click();await page.locator('[data-mi-success]').waitFor({state:'visible'});
  assert.equal(submitted.participants.length,3);assert.equal(submitted.travel_booking.mode,'NEED');
  assert.deepEqual(submitted.participants.map(person=>person.travel.driver_id),[15,16,17]);assert.equal(submitted.travel_booking.assignments.length,3);assert.deepEqual(errors,[]);await page.close();
  console.log('PASS travel browser: desktop/mobile, conditional fields, zero seats, fixed contribution, safe availability, waitlist consent, retained selections and full form submission/payment suppression'+(process.env.MI_TEST_MIN?' (minified)':''));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
