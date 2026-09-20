const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const people=Array.from({length:65},(_,i)=>({id:i+1,number:i+1,code:'ORD-1',name:i===1?'Bruno Secondo':'Persona '+i,buyer:'Ada Referente',email:'referente@example.invalid',phone:'01234',status:'CONFIRMED',room:'',fields:{},missing:[],collectible:true,unassigned:false,requests:''}));
let rooms=[],roomAttempts=[],features={rooms:false,payments:false,deposit:false};
people[1].requests='Richiesta sintetica';people[1].options=[{code:'bus',name:'Pullman',quantity:1}];
const items=[{code:'ORD-1',name:'Ada Referente',status:'CONFIRMED',participants:65,balance:65000,collectible:true,paid:1000,total:66000,missing:0,unassigned:0,active:true}];
const booking={registration_id:1,buyer:{first_name:'Ada',last_name:'Referente',email:'referente@example.invalid'},event_title:'Prova',order_code:'ORD-1',total_cents:66000,paid_cents:1000,balance_cents:65000,version:'v1',fields:[],movements:[],accommodations:[],participants:people.map(p=>({...p,first_name:p.name,last_name:'Prova',options:[]}))};
const markup=`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/portal-management.css"><main class="mi-portal"><section data-mi-management class="mi-management" data-event="42" data-order="" data-endpoint="/ajax" data-nonce="test"><details data-annual-report><summary>Rapporto annuale</summary><select data-annual-group><option value="5">Gruppo prova</option></select><input data-annual-year type="number" value="2026"><input data-annual-minimum type="number" value="2"><button data-load-annual>Genera rapporto annuale</button><p data-annual-status></p><div data-annual-results></div></details><select data-event-select><option value="42">Prova</option></select><div data-event-actions><button data-refresh>Aggiorna riepilogo</button><button data-print>Stampa vista</button><button data-sheet-sync>Sincronizza foglio Google</button><a data-open-sheet hidden>Apri foglio Google</a><p role="status" data-management-status></p></div><div data-management-content></div></section></main><script src="/portal-management.js"></script></html>`;
const server=http.createServer(async(req,res)=>{if(req.url==='/')return res.end(markup);if(req.url==='/ajax'){let body='';for await(const chunk of req)body+=chunk;const p=new URLSearchParams(body);if(p.get('operation')==='annual_report'){assert.equal(p.get('group_id'),'5');res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data:{year:2026,minimum:2,unrecorded:4,items:[{names:['Persona prova'],count:2,events:['Uno','Due'],records:[{code:'ORD-1',name:'Persona prova',id:1}]}]}}));}if(p.get('operation')==='event_room_save'){const data=JSON.parse(p.get('data'));roomAttempts.push(Object.fromEntries(p));rooms=[{...data,occupied:0,available:data.capacity}];res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data:{saved:true,message:'Camera salvata'}}));}res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data:p.get('operation')==='summary'?{features,items,people,rooms,rooms_version:'rooms-v1',updated_at:'2026-09-09T12:00:00Z'}:booking}));}const file=path.join(assets,path.basename(req.url));if(!fs.existsSync(file)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(file));});

// Complete synthetic event: long names, services, payments, rooms and attendance.
features={rooms:true,payments:true,deposit:true,room_inventory:true};
people[0].name='Maria Alessandra De Santis della Rovere';people[0].missing=['Cellulare','Comune di residenza'];people[0].unassigned=true;
people.forEach((p,i)=>{p.options=[{code:'alloggio-doppia-separati',name:'Doppia con letti separati',quantity:1},{code:'bus',name:'Pullman — partenza dalla piazza della stazione',category:'pullman',quantity:1}];p.attendance='UNRECORDED';p.room=i>1?'DS'+Math.ceil(i/2):'';});
items[0].missing=2;items[0].unassigned=2;items[0].deposit_plan=true;items[0].deposit_due=20000;items[0].deposit_missing=19000;
rooms=[{code:'DS1',name:'Camera doppia con accesso facilitato',capacity:2,occupied:2,available:0}];
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  let fail=false,hold=null;
  await page.route('**/ajax',async route=>{
   const op=new URLSearchParams(route.request().postData()).get('operation');
   if(op==='summary'&&hold)await hold;
   if(op==='summary'&&fail)return route.fulfill({json:{success:false,data:{message:'Servizio non disponibile. Riprova.'}}});
   if(op==='attendance_bulk')return route.fulfill({json:{success:true,data:{saved:true,message:'Presenze salvate'}}});
   const response=await route.fetch(),json=await response.json();
   if(op==='summary')Object.assign(json.data,{room_types:{'alloggio-doppia-separati':{name:'Doppia con letti separati',prefix:'DS',capacity:2}},annual_report_group:{id:5,name:'Gruppo prova'}});
   await route.fulfill({response,json});
  });
  await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('[data-more]').waitFor();
  assert.equal(await page.locator('.mi-summary-card').count(),4);
  assert.match(await page.locator('.mi-row-attention').first().innerText(),/Cellulare/);
  await page.screenshot({path:'.tmp/evento-completo-desktop.png'});
  await page.locator('[data-room-section]>summary').click();
  await page.getByLabel('Tipo di sistemazione',{exact:true}).selectOption('alloggio-doppia-separati');
  await page.locator('[data-room-section]').screenshot({path:'.tmp/evento-camere-desktop.png'});
  await page.locator('[data-attendance-panel]>summary').click();
  await page.locator('[data-attendance-panel]').scrollIntoViewIfNeeded();
  await page.screenshot({path:'.tmp/evento-presenze-desktop.png'});
  await page.locator('[data-attendance-person]').first().check();
  await page.locator('[data-attendance-bulk] button[type=submit]').click();
  await page.locator('[data-management-status].mi-management-status--success').waitFor();
  await page.locator('[data-query]').fill('Nessun risultato sintetico');
  await page.locator('.mi-list-empty').waitFor();
  await page.locator('[data-participant-filters]').scrollIntoViewIfNeeded();
  await page.screenshot({path:'.tmp/evento-nessun-risultato.png'});
  await page.locator('[data-clear-query]').click();await page.locator('[data-more]').waitFor();
  let release;hold=new Promise(r=>release=r);await page.locator('[data-refresh]').click();
  await page.locator('[data-management-status].mi-management-status--busy').waitFor();
  release();hold=null;await page.locator('[data-more]').waitFor();
  fail=true;await page.locator('[data-refresh]').click();await page.locator('[data-management-status].mi-management-status--error').waitFor();
  assert.equal(await page.locator('[data-management-status].mi-management-status--busy').count(),0);
  await page.screenshot({path:'.tmp/evento-errore.png'});
  fail=false;await page.locator('[data-refresh]').click();await page.locator('[data-more]').waitFor();
  for(const width of [390,320]){
   await page.setViewportSize({width,height:844});await page.reload();await page.locator('[data-more]').waitFor();
   assert.equal(await page.locator('.mi-management-summary-cards').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length),2);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.screenshot({path:'.tmp/evento-completo-mobile-'+width+'.png'});
   await page.locator('[data-room-section]>summary').click();await page.getByLabel('Tipo di sistemazione',{exact:true}).selectOption('alloggio-doppia-separati');
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.locator('[data-room-section]').screenshot({path:'.tmp/evento-camere-mobile-'+width+'.png'});
   await page.locator('[data-attendance-panel]>summary').click();
   await page.locator('[data-attendance-panel]').scrollIntoViewIfNeeded();
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.locator('[data-participant-reports]').screenshot({path:'.tmp/evento-report-mobile-'+width+'.png'});
  }
  assert.deepEqual(errors,[]);console.log('Evento completo: camere, presenze, report, ricerca vuota, caricamento, errore, recupero e mobile 320/390px verificati.');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
