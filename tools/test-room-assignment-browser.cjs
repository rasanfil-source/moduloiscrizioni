// Local synthetic data only. Real frontend with an idempotent mock transport.
const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const types={'alloggio-doppia-separati':{name:'Doppia letti separati',prefix:'DS',capacity:2},'alloggio-tripla':{name:'Tripla',prefix:'T',capacity:3},'alloggio-multipla':{name:'Multipla',prefix:'M',capacity:1,individual:true}};
const people=['alloggio-doppia-separati','alloggio-doppia-separati','alloggio-tripla','alloggio-multipla','alloggio-multipla'].map((type,i)=>({id:i+1,number:1,code:'ORD-'+(i+1),name:'Persona '+(i+1),status:'CONFIRMED',room:'',options:[{code:type,quantity:1}],fields:{},missing:[],buyer:'Referente',email:'',phone:''}));
const items=people.map(p=>({code:p.code,name:p.name,status:p.status,participants:1,balance:0,paid:0,active:true,missing:0,unassigned:1}));
let rooms=[],requests=[],lost=true,seen=new Set();
const markup=`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/portal-management.css"><main class="mi-portal"><section class="mi-management" data-mi-management data-event="42" data-endpoint="/ajax"><select data-event-select><option value="42">Evento prova</option></select><div data-event-actions><button data-refresh>Aggiorna riepilogo</button><button data-print>Stampa</button><button data-sheet-sync>Sincronizza</button><p role="status" data-management-status></p></div><div data-management-content></div></section></main><script src="/portal-management.js"></script></html>`;
const server=http.createServer(async(req,res)=>{if(req.url==='/')return res.end(markup);if(req.url==='/ajax'){let body='';for await(const chunk of req)body+=chunk;const p=new URLSearchParams(body);let data;
if(p.get('operation')==='room_assign'){
requests.push(Object.fromEntries(p));if(!seen.has(p.get('request_id'))){seen.add(p.get('request_id'));for(const change of JSON.parse(p.get('data'))){people.find(x=>x.code===change.order_code).room=change.after;if(change.after&&!rooms.some(r=>r.code===change.after))rooms.push({code:change.after,name:change.after,capacity:types[change.type].capacity});}for(const r of rooms){r.occupied=people.filter(x=>x.room===r.code).length;r.available=r.capacity-r.occupied;}}
if(lost){lost=false;res.statusCode=500;return res.end(JSON.stringify({success:false,data:{message:'Risposta interrotta simulata'}}));}data={saved:true,message:'Assegnazioni salvate'};
}else data={features:{rooms:true,payments:false,deposit:false},room_types:types,rooms,rooms_version:'v1',people,items};res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data}));}
const file=path.join(assets,path.basename(req.url));if(!fs.existsSync(file)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(file));});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
const page=await browser.newPage({viewport:{width:1280,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);
const planner=page.locator('[data-room-planner]'),selector=planner.locator('[data-room-type]');await selector.waitFor();
assert.equal(await page.evaluate(()=>document.querySelector('[data-new-registration]').nextElementSibling.hasAttribute('data-room-planner')),true);
assert.equal(await selector.locator('option').count(),4);await selector.selectOption('alloggio-doppia-separati');assert.equal(await planner.locator('[data-room-person]').count(),2);
const assignmentFilter=planner.locator('[data-room-assignment-filter]');assert.equal(await assignmentFilter.inputValue(),'unassigned');
assert.deepEqual(await planner.locator('.mi-room-assignment-table thead th').allTextContents(),['Persona iscritta','Codice','Numero']);
await assignmentFilter.selectOption('assigned');assert.equal(await planner.locator('[data-room-person]').count(),0);await assignmentFilter.selectOption('all');
await planner.locator('[data-room-person="1"]').fill('3');await selector.selectOption('alloggio-tripla');assert.equal(await selector.inputValue(),'alloggio-doppia-separati');
await assignmentFilter.selectOption('unassigned');assert.equal(await assignmentFilter.inputValue(),'all');assert.equal(await planner.locator('[data-room-person="1"]').inputValue(),'3');
await planner.locator('[data-room-person="2"]').fill('3');await planner.getByRole('button',{name:'Salva assegnazioni',exact:true}).click();await page.getByRole('button',{name:'Riprova lo stesso salvataggio'}).click();await planner.getByText('DS3 — 2/2 posti',{exact:true}).waitFor();
assert.equal(requests.length,2);assert.equal(requests[0].request_id,requests[1].request_id);assert.equal(requests[0].data,requests[1].data);assert.equal(seen.size,1);
await assignmentFilter.selectOption('unassigned');assert.equal(await planner.locator('[data-room-person]').count(),0);await assignmentFilter.selectOption('assigned');assert.equal(await planner.locator('[data-room-person]').count(),2);await assignmentFilter.selectOption('all');
await selector.selectOption('alloggio-tripla');assert.equal(await planner.locator('[data-room-person]').count(),1);
await selector.selectOption('alloggio-multipla');assert.equal(await planner.locator('[data-room-person]').count(),2);assert.equal(await planner.locator('[data-next-room]').count(),0);await planner.locator('[data-room-person="4"]').fill('1');await planner.locator('[data-room-person="5"]').fill('2');
await planner.getByRole('button',{name:'Salva assegnazioni',exact:true}).click();await planner.getByText('M2 — codice individuale',{exact:true}).waitFor();assert.equal(await planner.locator('[data-room-person]').count(),2);assert.doesNotMatch(await planner.innerText(),/1\/1 posti/);
await page.screenshot({path:'.tmp/room-planner-desktop.png',fullPage:true});await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await page.screenshot({path:'.tmp/room-planner-mobile.png',fullPage:true});assert.deepEqual(errors,[]);
console.log('Camere UI: tipo principale, iscrizioni distinte, DS condivisa, M individuali, protezione bozza, retry e mobile verificati.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
