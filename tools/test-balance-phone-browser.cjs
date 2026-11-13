const fs=require('fs'),path=require('path'),http=require('http'),cp=require('child_process'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const folder=path.resolve('output/telefono-saldo-3.27.62');fs.mkdirSync(folder,{recursive:true});const state=path.join(folder,'stato-sintetico.json'),root=path.resolve('wordpress-plugin/modulo-iscrizioni/assets'),php=path.resolve('.tmp/php-runtime/php.exe');
function call(args,input){const r=cp.spawnSync(php,['-n','-d','extension_dir='+path.resolve('.tmp/php-runtime/ext'),'-d','extension=mbstring','wordpress-plugin/tests/balance-phone.php',...args],{input:input&&JSON.stringify(input),encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);return r.stdout;}
const api=data=>JSON.parse(call(['--api',state],data));
const server=http.createServer(async(req,res)=>{try{const url=new URL(req.url,'http://localhost');
 if(req.method==='POST'){let raw='';for await(const c of req)raw+=c;res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(api(JSON.parse(raw))));}
 if(url.pathname==='/'){const html=call(['--render',url.searchParams.get('off')?'off':'on']).replaceAll('https://example.invalid','');return res.end(html);}
 const name=path.basename(url.pathname);if(!/^[a-z-]+\.(js|css)$/.test(name)){res.statusCode=404;return res.end();}const file=path.join(root,process.env.MI_TEST_MIN?'min':'',name);res.setHeader('Content-Type',name.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(file));
}catch(e){res.statusCode=500;res.end(JSON.stringify({success:false,error:e.message}));}});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 for(const width of [390,1280]){
  api({action:'reset'});const page=await browser.newPage({viewport:{width,height:950}}),errors=[],requests=[];page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>requests.push(r.url()));
  await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('.person-cognome').fill('decclesia');await page.locator('.btn-cerca').click();
  await page.locator('.balance-phone-dialog[open]').waitFor();assert.match(await page.locator('[data-phone-mask]').innerText(),/39338711.*••••/);assert.equal(await page.locator('.person-card.locked').count(),0);assert.equal(await page.locator('[data-service]').count(),0);
  assert.equal(await page.locator('.balance-phone-dialog').evaluate(e=>e.scrollWidth<=e.clientWidth),true);
  await page.locator('[name=digits]').fill('0000');await page.getByRole('button',{name:'Prosegui',exact:true}).click();await page.getByText('Le cifre non corrispondono. Controlla il numero indicato e riprova.',{exact:true}).waitFor();
  assert.doesNotMatch(await page.locator('.balance-phone-dialog').innerText(),/tentativi|rimasti|disponibili/i);
  await page.screenshot({path:path.join(folder,`verifica-${width}${process.env.MI_TEST_MIN?'-min':''}.png`),fullPage:true});
  await page.locator('[name=digits]').fill('1234');await page.getByRole('button',{name:'Prosegui',exact:true}).click();await page.locator('.person-card.locked').waitFor();assert.equal(await page.locator('.balance-phone-dialog').count(),0);assert.equal(await page.locator('[data-service]').count(),4);
  await page.locator('[data-service="pullman-roma-fco"]').check();await page.waitForFunction(()=>document.querySelector('#saldoDaVersare').textContent.includes('410'));
  await page.locator('#emailInput').fill('scelto@example.invalid');await page.locator(width<900?'#calcBtn':'#calcBtnDesktop').click();await page.locator('#confirmModal.active').waitFor();await page.locator('#confirmOkBtn').click();await page.locator('#ibanPopup.active').waitFor();assert.equal(api({action:'inspect'}).total,56000);
  // Cinque errori, poi anche una risposta corretta rimane sospesa; un nuovo caricamento non azzera il limite.
  api({action:'reset'});await page.reload();await page.locator('.person-cognome').fill('decclesia');await page.locator('.btn-cerca').click();await page.locator('.balance-phone-dialog[open]').waitFor();
  for(let i=0;i<5;i++){await page.locator('[name=digits]').fill('0000');await page.getByRole('button',{name:'Prosegui',exact:true}).click();await page.waitForFunction(()=>!document.querySelector('.balance-phone-dialog [type=submit]').disabled);}
  assert.match(await page.locator('[data-phone-error]').innerText(),/Attendi 5 minuti/);await page.reload();await page.locator('.person-cognome').fill('decclesia');await page.locator('.btn-cerca').click();await page.locator('.balance-phone-dialog[open]').waitFor();await page.locator('[name=digits]').fill('1234');await page.getByRole('button',{name:'Prosegui',exact:true}).click();await page.getByText('Attendi 5 minuti prima di riprovare.',{exact:true}).waitFor();
  // Evento senza verifica: nessun download dei file opzionali e nessun popup.
  api({action:'reset',disabled:true});requests.length=0;await page.goto('http://127.0.0.1:'+server.address().port+'/?off=1');await page.locator('.person-cognome').fill('decclesia');await page.locator('.btn-cerca').click();await page.locator('.person-card.locked').waitFor();assert.equal(requests.some(r=>r.includes('/balance-phone.')),false);assert.equal(await page.locator('.balance-phone-dialog').count(),0);
  assert.deepEqual(errors,[]);await page.close();
 }
 console.log('PASS telefono browser desktop/mobile: maschera, errore senza contatori, verifica, scelta pullman e conferma saldo, pausa persistente al reload, zero asset opzionali negli eventi disattivati'+(process.env.MI_TEST_MIN?' (minificati)':''));
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
