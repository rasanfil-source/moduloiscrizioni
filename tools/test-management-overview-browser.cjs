// Serializzatori JavaScript e PHP applicativi, dati sintetici, nessuna scrittura WordPress o Google.
const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('child_process');
const chromium=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright')[process.env.MI_BROWSER_ENGINE||'chromium'];
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const people=Array.from({length:65},(_,i)=>({id:i+1,number:i+1,code:'ORDER',name:'Persona '+(i+1),status:'CONFIRMED',room:'',options:[{code:'meal',name:'Pranzo',quantity:2},{code:'alloggio-doppia-separati',name:'Doppia',quantity:1}],attendance:{state:'UNRECORDED'},fields:{},missing:[],phone:'123',email:'test@example.invalid'}));
const model={people,items:[{code:'ORDER',name:'Referente',status:'CONFIRMED',participants:65,active:true,collectible:true,balance:1000,paid:2000,missing:0,unassigned:65,requests:'Richiesta privata',order_options:[]},{code:'OFFER',name:'Persona in attesa',status:'WAITLIST_OFFERED',participants:1,active:true,balance:0,paid:0,missing:0,unassigned:0,offer_expires_at:'2026-10-10 10:00:00'}],features:{rooms:true,payments:true,deposit:false,room_inventory:true},rooms:[],room_types:{'alloggio-doppia-separati':{name:'Doppia separati',prefix:'DS',capacity:2}},option_definitions:[{code:'meal',name:'Pranzo',category:'pranzo'}],field_labels:{},updated_at:'2026-09-28T12:00:00Z'};
const php=input=>JSON.parse(execFileSync(path.resolve('.tmp/php-runtime/php.exe'),['-d','extension_dir='+path.resolve('.tmp/php-runtime/ext'),'-d','extension=mbstring','wordpress-plugin/tests/management-list.php','--json'],{input:JSON.stringify({...input,summary:model}),encoding:'utf8'}));
const markup=`<!doctype html><html><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/portal-management.css"><main class="mi-portal"><section data-mi-management class="mi-management" data-event="42" data-endpoint="/ajax" data-nonce="test"><select data-event-select><option value="42">Evento</option></select><button data-refresh>Aggiorna</button><button data-print>Stampa</button><p data-management-status></p><div data-management-content></div></section></main><script src="/portal-management.js"></script></html>`;
const calls=[];let failAttendance=true,heldRooms=null,releaseRooms=null;
model.items.push({code:'WAIT',name:'Gruppo in attesa',status:'WAITLISTED',participants:3,active:true,balance:0,paid:0,missing:0,unassigned:0});
const server=http.createServer(async(req,res)=>{try{
 if(new URL(req.url,'http://localhost').pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(markup);}
 if(req.url==='/ajax'){
  let body='';for await(const chunk of req)body+=chunk;const params=Object.fromEntries(new URLSearchParams(body));calls.push(params);let data;
  if(params.operation==='summary'){data=php({operation:'overview'});assert.ok(!('people' in data)&&!('items' in data));data.attendance_availability={enabled:true,available:true};if(Object.hasOwn(model,'travel_overview')&&!data.metrics.missing)data.travel_overview=model.travel_overview;}
  else if(params.operation==='summary_panel'){
   if(params.panel==='attendance'&&failAttendance){failAttendance=false;return res.end(JSON.stringify({success:false,data:{message:'Errore sintetico'}}));}
   if(params.panel==='rooms'&&heldRooms)await heldRooms;
   data=php({operation:'panel',panel:params.panel});
  }else if(params.operation==='list_page')data=php({context:JSON.parse(params.context),offset:Number(params.offset),limit:Number(params.limit)});
  else throw Error('Unexpected operation '+params.operation);
  res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data}));
 }
 const file=path.join(assets,path.basename(req.url));if(!fs.existsSync(file)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(file));
 }catch(e){res.statusCode=500;res.end(JSON.stringify({success:false,data:{message:e.message}}));}});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({...(!process.env.MI_BROWSER_ENGINE?{channel:'msedge'}:{}),headless:true});try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('[data-more]').waitFor();
 assert.match(await page.locator('[data-management-content]').innerText(),/In attesa: 3 · Proposte: 1/);
 assert.equal(calls.filter(c=>c.operation==='summary_panel').length,0);
 assert.equal(await page.locator('[data-list] tbody tr').count(),30);
 assert.equal(await page.locator('.mi-summary-card--missing').count(),1);
 model.travel_overview={waiting:3,available:4,assigned:2,reserved:1};
 let beforeCalls=calls.length;await page.locator('[data-refresh]').click();await page.locator('.mi-summary-card--travel').waitFor();
 await page.locator('[data-more]').waitFor();assert.equal(calls.length-beforeCalls,2,'Only existing summary and list requests');
 assert.equal(await page.locator('.mi-summary-card--missing').count(),0);
 assert.match(await page.locator('.mi-summary-card--travel').innerText(),/3 in attesa[\s\S]*Posti disponibili: 4[\s\S]*Posti assegnati: 2[\s\S]*Riservati in attesa di risposta: 1/);
 for(const width of [390,1280]){await page.setViewportSize({width,height:1000});await page.screenshot({path:'output/passaggi-riepilogo-'+width+'.png',fullPage:true});assert.equal(await page.locator('.mi-summary-card--travel').evaluate(el=>el.scrollWidth<=el.clientWidth),true);}
 model.items[0].missing=1;await page.locator('[data-refresh]').click();await page.locator('.mi-summary-card--missing').waitFor();assert.equal(await page.locator('.mi-summary-card--travel').count(),0);
 model.items[0].missing=0;model.travel_overview=null;await page.locator('[data-refresh]').click();await page.getByText('Riepilogo non disponibile. Aggiorna per riprovare.',{exact:true}).waitFor();
 delete model.travel_overview;await page.locator('[data-refresh]').click();await page.locator('.mi-summary-card--missing').waitFor();await page.locator('[data-more]').waitFor();
 assert.equal(await page.locator('[data-list] tbody .mi-participant-room-code').first().innerText(),'DS-');
 assert.equal(await page.locator('[data-room-planner]').count(),0);assert.equal(await page.locator('[data-attendance-person]').count(),0);
 await page.locator('[data-service-summary]>summary').click();assert.match(await page.locator('[data-service-summary]').innerText(),/Pranzo: 65 persone/);
 await page.locator('[data-room-section]>summary').click();await page.locator('[data-room-planner]').waitFor();
 assert.equal(calls.filter(c=>c.panel==='rooms').length,1);
 await page.locator('[data-room-section]>summary').click();await page.locator('[data-room-section]>summary').click();
 assert.equal(calls.filter(c=>c.panel==='rooms').length,1);
 await page.locator('[data-attendance-panel]>summary').click();await page.locator('[data-attendance-panel]').getByRole('button',{name:'Riprova'}).click();
 await page.locator('[data-attendance-person]').first().waitFor();assert.equal(await page.locator('[data-attendance-person]').count(),65);assert.equal(calls.filter(c=>c.panel==='attendance').length,2);
 await page.locator('[data-offer-panel]>summary').click();await page.locator('[data-offer-panel] [data-open="OFFER"]').waitFor();assert.equal(calls.filter(c=>c.panel==='offers').length,1);
 await page.locator('[data-refresh]').click();await page.locator('[data-more]').waitFor();
 heldRooms=new Promise(r=>releaseRooms=r);await page.locator('[data-room-section]>summary').click();
 await page.waitForFunction(()=>document.querySelector('[data-room-section]').textContent.includes('Caricamento'));
 await page.locator('[data-refresh]').click();await page.locator('[data-more]').waitFor();releaseRooms();heldRooms=null;
 await page.waitForResponse(async r=>{try{return (await r.json()).data?.rooms_version!==undefined;}catch{return false;}});
 assert.equal(await page.locator('[data-room-planner]').count(),0,'Stale panel must not change refreshed summary');
 model.items=model.items.filter(item=>item.status!=='WAITLIST_OFFERED');await page.locator('[data-refresh]').click();
 await page.getByText('In attesa: 3 · Proposte: 0',{exact:true}).waitFor();
 model.people=[];model.items=[];model.features.rooms=false;await page.locator('[data-refresh]').click();await page.getByText('Nessun risultato. Modifica la ricerca o i filtri.',{exact:true}).waitFor();
 assert.equal(await page.locator('[data-attendance-panel]').count(),0);assert.equal(await page.locator('[data-offer-panel]').count(),0);
 assert.deepEqual(errors,[]);console.log('Overview: 30 initial rows, aggregate services, panels on demand, retry, no repeated loading, stale response ignored.');
 }finally{if(releaseRooms)releaseRooms();await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
