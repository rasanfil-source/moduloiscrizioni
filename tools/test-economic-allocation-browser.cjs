const fs=require('fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets='wordpress-plugin/modulo-iscrizioni/assets/';
const template=`<main class="mi-portal"><section class="mi-management" data-mi-management data-endpoint="/ajax" data-nonce="test" data-event="0" data-order=""><h2>Gestione iscrizioni</h2><div class="mi-management-event-selectors"><select data-period-select><option value="current">Eventi attivi</option><option value="past">Eventi passati</option></select><select data-event-select><option value="">Tutti gli eventi</option><option value="42" data-period="current">Pellegrinaggio ad Assisi</option></select></div><div data-event-actions><button data-refresh>Aggiorna riepilogo</button><button data-print>Stampa</button><a data-open-sheet hidden></a><p data-management-status role="status"></p></div><div data-management-content></div></section></main>`;
let removed=false,saves=0,requests=[];
const options=()=>removed?[]:[{code:'bus-andata',name:'Pullman di andata',quantity:1,unit_price_cents:2500,category:'trasporti'}];
const detail=()=>({registration_id:1,event_id:42,event_title:'Pellegrinaggio ad Assisi',order_code:'FAMILY',status:'CONFIRMED',version:removed?'v2':'v1',total_cents:removed?60000:62500,paid_cents:42500,balance_cents:removed?17500:20000,is_free_event:false,buyer:{email:'',phone:''},fields:[],features:{rooms:true},option_scope:'ALL',can_change_options:true,can_adjust_due:true,order_options:[],option_definitions:[{code:'common',name:'Servizio comune',scope:'ORDER',price_cents:2000,max_quantity:1},{code:'bus-andata',name:'Pullman di andata',scope:'TICKET',category:'trasporti',price_cents:2500,max_quantity:1}],participants:[{id:1,number:1,first_name:'Raimondo',last_name:'Sanfilippo',status:'ACTIVE',room:'DS1',fields:{},options:[]},{id:2,number:2,first_name:'Gustavo',last_name:'Lora',status:'ACTIVE',room:'DS1',fields:{},options:options()}],individual:{ready:true,quotes_known:true,payments_known:true,people:[{id:1,total:30000,paid:10000,balance:20000,credit:0},{id:2,total:removed?30000:32500,paid:32500,balance:0,credit:removed?2500:0}]}});
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage({viewport:{width:1024,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://management-demo.invalid/**',async route=>{if(!route.request().url().endsWith('/ajax'))return route.fulfill({body:'<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width">'+template,contentType:'text/html'});
  const p=Object.fromEntries(new URLSearchParams(route.request().postData()));requests.push(p);let data;
  if(p.operation==='all_people')data={items:[{first_name:'Gustavo',last_name:'Lora',event_title:'Pellegrinaggio ad Assisi',event_id:42,order_code:'FAMILY',number:2,status:'ACTIVE',booking_status:'CONFIRMED',created_at:'2026-09-14 10:00:00'}],more:false};
  else if(p.operation==='detail')data=detail();
  else if(p.operation==='options_preview'&&JSON.parse(p.data).participant_id===0){const c=JSON.parse(p.data);data={delta:2000,before_total:null,after_total:null,credit:0,allocations:c.allocations||{'1':1000,'2':1000}};}
  else if(p.operation==='change_options'&&JSON.parse(p.data).participant_id===0){assert.deepEqual(JSON.parse(p.data).allocations,{'1':500,'2':1500});data={saved:true};}
  else if(p.operation==='adjust_due'){assert.equal(JSON.parse(p.data).participant_id,2);data={saved:true};}
  else if(p.operation==='options_preview'){assert.equal(JSON.parse(p.data).participant_id,2);data={before_total:32500,after_total:30000,delta:-2500,credit:2500};}
  else if(p.operation==='change_options'){assert.equal(JSON.parse(p.data).options['bus-andata'],0);assert.equal(JSON.parse(p.data).participant_id,2);removed=true;saves++;data={saved:true,message:'Servizi e importi aggiornati.'};}
  else throw Error('Unexpected operation '+p.operation);
  return route.fulfill({json:{success:true,data}});
 });
 await page.goto('https://management-demo.invalid/');for(const css of ['portal.css','portal-management.css'])await page.addStyleTag({content:fs.readFileSync(assets+css,'utf8')});await page.addScriptTag({content:fs.readFileSync(assets+'portal-management.js','utf8')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
 await page.locator('[data-all-query]').fill('Gustavo');await page.locator('[data-open]').click();await page.locator('form[data-person="2"]').waitFor();
 assert.match(await page.locator('[aria-label="Importi personali"]').innerText(),/325,00/);assert.doesNotMatch(await page.locator('[aria-label="Importi personali"]').innerText(),/625,00/);
 await page.locator('[data-booking-overview] summary').click();assert.match(await page.locator('[data-booking-overview]').innerText(),/Raimondo/);assert.match(await page.locator('[data-booking-overview]').innerText(),/625,00/);assert.match(await page.locator('[data-booking-overview]').innerText(),/Pullman di andata/);
 await page.locator('[name="option:common"]').fill('1');
 await page.locator('[data-change-options="0"] button').click();
 await page.locator('[data-share="1"]').waitFor();assert.equal(await page.locator('[data-share="1"]').inputValue(),'10.00');
 await page.locator('[data-share="1"]').fill('5.00');await page.locator('[data-share="2"]').fill('15.00');
 await page.locator('[data-change-options="0"] button').click();await page.locator('dialog[open]').waitFor();assert.match(await page.locator('dialog').innerText(),/Ripartizione/);await page.locator('dialog [value=accept]').click();
 await page.locator('[data-change-options="0"] [data-common-shares]').waitFor({state:'detached'});
 await page.getByText('Rettifica il dovuto della prenotazione',{exact:true}).click();
 await page.locator('[data-adjust-due] [name=participant_id]').selectOption('2');await page.locator('[data-adjust-due] [name=total]').fill('600');await page.locator('[data-adjust-due] [name=reason]').fill('Sconto concordato');await page.locator('[data-adjust-due] button').click();await page.locator('dialog[open]').waitFor();await page.locator('dialog [value=accept]').click();
 await page.waitForTimeout(300);assert.ok(requests.some(p=>p.operation==='adjust_due'));
 for(const width of [1024,390,320]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'overflow '+width);}
 assert.deepEqual(errors,[]);console.log('PASS browser ripartizioni e rettifiche: ricerca unica, quota personale, prenotazione completa, anteprima credito, salvataggio sulla persona corretta, ritorno ai risultati, 320–1024px.');
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
