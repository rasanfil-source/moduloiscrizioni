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
if(p.get('operation')==='accommodation_preview'){
const payload=JSON.parse(p.get('data'));requests.push(Object.fromEntries(p));data={version:'preview-v1',people:payload.people.map(x=>({name:'Persona',code:x.code,before_type:'Doppia letti separati',after_type:'Tripla',before_room:'DS3',after_room:'T1'})),orders:payload.people.map(x=>({code:x.code,before_total:10000,after_total:8000,delta:-2000,paid:10000,due:0,refund:2000}))};
}else if(p.get('operation')==='change_accommodation'){
requests.push(Object.fromEntries(p));assert.equal(p.get('preview_version'),'preview-v1');if(!seen.has(p.get('request_id'))){seen.add(p.get('request_id'));for(const x of JSON.parse(p.get('data')).people){const person=people.find(y=>y.code===x.code);person.room='T1';person.options=[{code:'alloggio-tripla',quantity:1}];}}
if(lost){lost=false;res.statusCode=500;return res.end(JSON.stringify({success:false,data:{message:'Risposta interrotta'}}));}data={saved:true,message:'Cambio salvato'};
}else if(p.get('operation')==='room_assign'){
requests.push(Object.fromEntries(p));if(!seen.has(p.get('request_id'))){seen.add(p.get('request_id'));for(const change of JSON.parse(p.get('data'))){people.find(x=>x.code===change.order_code).room=change.after;if(change.after&&!rooms.some(r=>r.code===change.after))rooms.push({code:change.after,name:change.after,capacity:types[change.type].capacity});}for(const r of rooms){r.occupied=people.filter(x=>x.room===r.code).length;r.available=r.capacity-r.occupied;}}
if(lost){lost=false;res.statusCode=500;return res.end(JSON.stringify({success:false,data:{message:'Risposta interrotta simulata'}}));}data={saved:true,message:'Assegnazioni salvate'};
}else data={features:{rooms:true,payments:false,deposit:false},room_types:types,rooms,rooms_version:'v1',people,items};res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data}));}
const file=path.join(assets,path.basename(req.url));if(!fs.existsSync(file)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(file));});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
const page=await browser.newPage({viewport:{width:1280,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);
const planner=page.locator('[data-room-planner]'),selector=planner.locator('[data-room-type]');await selector.waitFor();

const panel=page.locator('[data-accommodation-change]');await panel.locator('summary').click();
await panel.locator('[name=person][value="1"]').check();await panel.locator('[name=person][value="2"]').check();await panel.locator('[name=type]').selectOption('alloggio-tripla');await panel.locator('[name=reason]').fill('Cambio concordato');
await panel.getByRole('button',{name:'Calcola anteprima'}).click();await panel.locator('[data-confirm-accommodation]').waitFor();assert.equal(seen.size,0);assert.equal(await panel.locator('[data-accommodation-preview] tbody tr').count(),2);assert.match(await panel.locator('[data-accommodation-preview]').innerText(),/Da restituire/);
await panel.locator('[name=reason]').fill('Motivo corretto');assert.equal(await panel.locator('[data-confirm-accommodation]').count(),0);
await panel.getByRole('button',{name:'Calcola anteprima'}).click();await panel.locator('[data-confirm-accommodation]').waitFor();
await page.screenshot({path:'.tmp/accommodation-preview.png',fullPage:true});await panel.locator('[data-confirm-accommodation]').click();await page.getByRole('button',{name:'Riprova lo stesso salvataggio'}).click();await page.getByText('Cambio salvato',{exact:true}).waitFor();
const saves=requests.filter(x=>x.operation==='change_accommodation');assert.equal(saves.length,2);assert.equal(saves[0].request_id,saves[1].request_id);assert.equal(saves[0].data,saves[1].data);assert.equal(seen.size,1);assert.equal(people[0].room,'T1');assert.equal(people[1].room,'T1');assert.deepEqual(errors,[]);
await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
console.log('Cambio unico UI: selezione multipla, anteprima senza scritture, invalidazione bozza, conferma e retry identico verificati.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
