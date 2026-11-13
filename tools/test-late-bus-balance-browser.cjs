// Browser reale + calcolo/salvataggio PHP applicativi; solo archivio locale sintetico.
const fs=require('fs'),http=require('http'),path=require('path'),cp=require('child_process'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve('wordpress-plugin/modulo-iscrizioni'),folder=path.resolve('output/collaudo-pullman-successivo');fs.mkdirSync(folder,{recursive:true});
const state=path.join(folder,'stato-sintetico.json'),php=path.resolve('.tmp/php-runtime/php.exe');
const wizard=cp.spawnSync(php,['-n','-d','extension_dir='+path.resolve('.tmp/php-runtime/ext'),'-d','extension=mbstring','wordpress-plugin/tests/event-late-bus-save.php','--catalog'],{encoding:'utf8'});assert.equal(wizard.status,0,wizard.stderr+wizard.stdout);const catalog=JSON.parse(wizard.stdout);
function api(data){const r=cp.spawnSync(php,['-n','-d','extension_dir='+path.resolve('.tmp/php-runtime/ext'),'-d','extension=mbstring','wordpress-plugin/tests/public-balance-late-bus.php','--api',state],{input:JSON.stringify(data),encoding:'utf8'});assert.equal(r.status,0,r.stderr+r.stdout);return JSON.parse(r.stdout);}
const config={eventTitle:'Cammino — collaudo',endpoint:'/api',nonce:'test',contact:'segreteria@example.invalid',iban:'ITTEST',holder:'Prova',cardUrl:'https://example.invalid/pay',methods:['BANK_TRANSFER','CARD']};
const template=fs.readFileSync(path.join(root,'templates/public-balance.php'),'utf8').replace(/<\?php.*?\?>/gs,'Cammino');
const html=`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public-balance.css"><body>${template}<script>window.MIBalance=${JSON.stringify(config)}</script><script src="/public-balance.js"></script></body></html>`;
const server=http.createServer(async(req,res)=>{try{
 if(req.url==='/')return res.end(html);
 if(req.url==='/api'){let raw='';for await(const c of req)raw+=c;res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(api(JSON.parse(raw))));}
 const file=path.join(root,'assets',path.basename(req.url));if(fs.existsSync(file)){res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':'text/css');return res.end(fs.readFileSync(file));}res.statusCode=404;res.end();
}catch(e){res.statusCode=500;res.end(JSON.stringify({success:false,error:e.message}));}});
async function amount(page,id,value){await page.waitForFunction(({id,value})=>document.getElementById(id).textContent.replace(/[^0-9,]/g,'')===value,{id,value});}
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});const results=[];try{
 for(const width of [390,1280]){
  api({action:'reset'});const page=await browser.newPage({viewport:{width,height:950}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function load(){await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('.person-cognome').fill('decclesia');await page.locator('.btn-cerca').click();await page.locator('.person-card.locked').waitFor();}
  await load();assert.equal(await page.locator('[data-service]').count(),0,'Il pullman deve essere assente prima della definizione');await amount(page,'saldoDaVersare','400,00');
  await page.screenshot({path:path.join(folder,`iniziale-senza-pullman-${width}.png`),fullPage:true});
  api({action:'add_routes',catalog});await load();assert.equal(await page.locator('[data-service]').count(),4,'Nuove tratte non disponibili al vecchio iscritto');
  await page.locator('[data-service="pullman-roma-fco"]').check();await page.locator('[data-service="pullman-porto-tui"]').check();await amount(page,'saldoDaVersare','425,00');await amount(page,'caparraDaVersare','0,00');
  await page.locator('#emailInput').fill('scelto@example.invalid');assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:path.join(folder,`saldo-con-tratte-${width}.png`),fullPage:true});
  await page.locator(width<900?'#calcBtn':'#calcBtnDesktop').click();await page.locator('#confirmModal.active').waitFor();assert.match(await page.locator('#confirmBody').innerText(),/425/);
  await page.locator('#confirmCancelBtn').click();assert.equal(api({action:'inspect'}).registration.total_cents,55000,'Annulla ha modificato il dovuto');
  await page.locator(width<900?'#calcBtn':'#calcBtnDesktop').click();await page.locator('#confirmOkBtn').click();await page.locator('#ibanPopup.active').waitFor();
  let saved=api({action:'inspect'});assert.equal(saved.registration.total_cents,57500);assert.equal(saved.registration.initial_due_cents,15000);assert.equal(saved.payments.length,1);assert.equal(saved.outbox.length,1);
  assert.equal(saved.outbox[0][1],'scelto@example.invalid');assert.match(JSON.parse(saved.outbox[0][3]).email_preview.testo,/425,00/);assert.equal(saved.registration.workspace_status,'PENDING');
  await page.locator('#ibanCloseBtn').click();await page.locator('#payBtn').click();await page.locator('#payPopup.active').waitFor();assert.match(await page.locator('#popupImporto').innerText(),/425/);await page.keyboard.press('Escape');
  // Riapertura: selezioni persistenti e costi conteggiati una sola volta.
  await load();await amount(page,'saldoDaVersare','425,00');assert.equal(await page.locator('[data-service="pullman-roma-fco"]').isChecked(),true);
  await page.locator('[data-service="pullman-roma-fco"]').uncheck();await page.locator('[data-service="pullman-porto-tui"]').uncheck();await amount(page,'saldoDaVersare','400,00');
  assert.equal(api({action:'inspect'}).registration.total_cents,57500,'Una scelta non confermata ha già scritto il totale');
  assert.deepEqual(errors,[]);results.push({width,initial:400,afterBus:425,depositPaid:150,selectedRoutes:2,status:'PASS'});await page.close();
 }
 fs.writeFileSync(path.join(folder,'risultati-browser.json'),JSON.stringify(results,null,2));console.log('PASS browser desktop/mobile con PHP reale: pullman inizialmente assente, quattro tratte aggiunte dopo, saldo 400 → 425 €, annulla, conferma, email archiviata, pagamento, riapertura e persistenza.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
