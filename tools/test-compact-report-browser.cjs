const fs=require('node:fs'),http=require('node:http'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets',process.env.MI_TEST_MIN?'min':'');
const out=path.resolve('.tmp/compact-report-67'+(process.env.MI_TEST_MIN?'-min':''));fs.mkdirSync(out,{recursive:true});
const people=Array.from({length:207},(_,index)=>({id:index+1,code:'TEST-'+index,number:1,last_name:index===0||index===206?'Rossi':index===1?'De Santis della Rovere e delle Colline':index===2?'D’Amico':index===3?'=Formula':'Cognome '+String(index).padStart(3,'0'),first_name:index===0?'Ada':index===206?'Luca':'Persona',name:'',buyer:'Referente',status:'CONFIRMED',fields:{},options:[],missing:[],room:'DS1',email:'demo@example.invalid',phone:'00000',created_at:'2026-11-15',registration_time:'2026-11-15 11:22:33'}));
people.forEach(person=>person.name=person.last_name+' '+person.first_name);
let fingerprintMismatch=false;const calls=[];
const markup='<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/portal-management.css"><main class="mi-portal" data-mi-portal-scope="reserved"><section class="mi-management" data-mi-management data-event="42" data-endpoint="/ajax" data-nonce="test"><select data-event-select><option value="42">Giornata dimostrativa &amp; incontro</option></select><button data-refresh>Aggiorna riepilogo</button><button data-print>Stampa</button><p data-management-status></p><div data-management-content></div></section></main><script src="/portal-management.js"></script></html>';
const server=http.createServer(async(req,res)=>{
 if(new URL(req.url,'http://localhost').pathname==='/')return res.end(markup);
 if(req.url==='/ajax'){
  let body='';for await(const chunk of req)body+=chunk;const params=new URLSearchParams(body),op=params.get('operation');let data;
  if(op==='summary')data={server_paging:true,features:{rooms:true,payments:false,registration_timestamps:true},items:[],people:[],updated_at:'2026-11-15T12:00:00Z'};
  else if(op==='list_page'){
   const context=JSON.parse(params.get('context')),offset=Number(params.get('offset')),limit=Number(params.get('limit'));calls.push({offset,limit});
   const rows=people.filter(person=>person.name.toLowerCase().includes((context.query||'').toLowerCase()));
   data={rows:rows.slice(offset,offset+limit),total:rows.length,fingerprint:fingerprintMismatch&&offset>0?'changed':'v1'};
  }else throw Error('Unexpected operation '+op);
  res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({success:true,data}));
 }
 const file=path.join(assets,path.basename(req.url));if(!fs.existsSync(file)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript; charset=utf-8':'text/css');res.end(fs.readFileSync(file));
});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:900},acceptDownloads:true}),errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('[data-list] tbody tr').first().waitFor();
  await page.locator('[data-export-settings] summary').click();const compact=page.getByLabel('Elenco compatto',{exact:true});await compact.check();
  assert.equal(await page.locator('[data-export-column]:checked').count(),1);
  await page.locator('[data-export-column="email"]').check();assert.equal(await compact.isChecked(),false);await compact.check();
  await page.reload();await page.locator('[data-list] tbody tr').first().waitFor();assert.equal(await compact.isChecked(),true);assert.equal(await page.locator('[data-export-column]:checked').count(),1);
  async function download(){const pending=page.waitForEvent('download');await page.locator('[data-export]').click();const result=await pending;assert.equal(result.suggestedFilename(),'evento-42-elenco-compatto.xlsx');await result.saveAs(path.join(out,'elenco-compatto.xlsx'));return fs.readFileSync(await result.path()).toString('utf8');}
  const xml=await download();assert.match(xml,/Rossi Ada/);assert.match(xml,/Rossi Luca/);assert.match(xml,/De Santis della Rovere e delle Colline/);assert.doesNotMatch(xml,/demo@example|DS1|Iscrizione|11:22:33/);assert.equal((xml.match(/<row /g)||[]).length,43);assert.ok(calls.some(call=>call.offset===200));
  await page.evaluate(()=>{window.print=()=>{window.printedGrid=document.querySelector('[data-print-list]').outerHTML;};});
  await page.locator('[data-print]').click();const host=page.locator('[data-print-list]');await host.waitFor({state:'attached'});
  assert.equal(await host.locator('td').count(),210);assert.equal(await host.locator('th').textContent(),'Giornata dimostrativa & incontro');assert.match(await host.textContent(),/Rossi Ada.*Rossi Luca/);
  await page.emulateMedia({media:'print'});assert.equal(await host.locator('table').evaluate(el=>getComputedStyle(el).tableLayout),'fixed');
  assert.equal(await host.locator('td').nth(0).evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(255, 255, 255)');assert.equal(await host.locator('td').nth(1).evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(242, 242, 242)');assert.equal(await host.locator('td').nth(5).evaluate(el=>getComputedStyle(el).backgroundColor),'rgb(242, 242, 242)');
  await page.screenshot({path:path.join(out,'stampa.png'),fullPage:true});await page.pdf({path:path.join(out,'elenco-compatto.pdf'),preferCSSPageSize:true,printBackground:true});
  await page.evaluate(()=>window.dispatchEvent(new Event('afterprint')));assert.equal(await host.count(),0);await page.emulateMedia({media:'screen'});
  assert.equal(await page.locator('[data-list] tbody tr').count(),30);
  await page.locator('[data-query]').fill('Rossi');await page.locator('[data-run-query]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-list] tbody tr').length===2);
  const filtered=await download();assert.equal((filtered.match(/<row /g)||[]).length,2);assert.match(filtered,/Rossi Ada/);assert.doesNotMatch(filtered,/D’Amico/);
  await page.locator('[data-query]').fill('inesistente');await page.locator('[data-run-query]').click();await page.getByText('Nessun risultato. Modifica la ricerca o i filtri.').waitFor();await page.locator('[data-export]').click();await page.getByText('Nessuna persona da esportare con i filtri attuali.').waitFor();
  await page.locator('[data-query]').fill('');await page.locator('[data-run-query]').click();await page.locator('[data-list] tbody tr').first().waitFor();fingerprintMismatch=true;await page.locator('[data-export]').click();await page.getByText(/Esportazione non completata.*cambiati durante/).waitFor();
  await page.setViewportSize({width:390,height:844});await page.locator('[data-export-settings] summary').click();assert.equal(await compact.isVisible(),true);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  fingerprintMismatch=false;await page.setViewportSize({width:1280,height:900});await page.locator('[data-export-column="name"]').check();assert.equal(await compact.isChecked(),false);
  const normalPending=page.waitForEvent('download');await page.locator('[data-export]').click();const normal=await normalPending;assert.equal(normal.suggestedFilename(),'evento-42-partecipanti.xlsx');const normalXml=fs.readFileSync(await normal.path()).toString('utf8');assert.equal((normalXml.match(/<row /g)||[]).length,208);assert.match(normalXml,/Iscrizione \(data e ora\)/);assert.match(normalXml,/11:22:33/);assert.doesNotMatch(normalXml,/<mergeCell/);
  await page.locator('[data-print]').click();await host.waitFor({state:'attached'});assert.equal(await host.locator('tbody tr').count(),207);assert.deepEqual(await host.locator('th').allTextContents(),['N.','Stanza','Partecipante']);await page.evaluate(()=>window.dispatchEvent(new Event('afterprint')));
  assert.deepEqual(errors,[]);console.log('PASS compact report: exclusivity, persistence, 207 people across pages, duplicate surnames, print + Excel, filters, empty selection, fingerprint rejection, mobile.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
