const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve('wordpress-plugin/modulo-iscrizioni');
const model=(id=1)=>({success:true,row:id,persona:{nome:id===1?'Marco':'Maria',cognome:'Rossi',email:'demo@example.invalid',alloggio:'S'+id,siglaAlloggio:'S'+id,locked:[{name:'Alloggio: Singola',price:50000}],services:[{code:'bus',name:'Roma → Fiumicino',price:1000,selected:false,group:'',direction:'All’andata'}],fixed:50000,paid:0,deposit:15000,managed:true,shared:false,token:'opaque',version:'v1'}});
let saves=0;
const config={eventTitle:'Prova saldo',endpoint:'/api',nonce:'test',contact:'demo@example.invalid',iban:'ITTEST',holder:'Prova',cardUrl:'https://example.invalid/pay',methods:['BANK_TRANSFER','CARD']};
let html=fs.readFileSync(path.join(root,'templates/public-balance.php'),'utf8').replace(/<\?php.*?\?>/gs,'Prova');
html=`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public-balance.css"><body>${html}<script>window.MIBalance=${JSON.stringify(config)};</script><script src="/public-balance.js"></script></body></html>`;
const server=http.createServer(async(req,res)=>{
 if(req.url==='/')return res.end(html);
 if(req.url==='/api'){
  let raw='';for await(const c of req)raw+=c;const d=JSON.parse(raw);let result={success:true};
  if(d.action==='lookupByCognome')result=d.cognome==='coppia'?{...model(1),partner:model(2)}:{success:false,error:'duplicate',candidates:[{nome:'Marco',row:1},{nome:'Maria',row:2}]};
  if(d.action==='lookupPersona')result=model(Number(d.candidate));
  if(d.action==='preview'||d.action==='salvaTransfer'){
   const total=d.persone.reduce((sum,p)=>sum+50000+(p.services.bus?1000:0),0),dep=15000*d.persone.length;
   result={success:true,total,paid:0,deposit:dep,depositPaid:0,depositDue:dep,saldoDue:total-dep,balance:total,people:d.persone.map(p=>({row:p.row,name:p.row===1?'Rossi Marco':'Rossi Maria',lines:[{name:'Singola',price:50000},...(p.services.bus?[{name:'Roma → Fiumicino',price:1000}]:[])]})),fingerprint:'quote',causale:'Saldo Prova Rossi',versions:{1:'v2',2:'v2'},emailQueued:true};
   if(d.action==='salvaTransfer')saves++;
  }
  res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(result));
 }
 const file=path.join(root,'assets',path.basename(req.url));if(fs.existsSync(file)){res.setHeader('Content-Type',file.endsWith('.js')?'application/javascript':'text/css');return res.end(fs.readFileSync(file));}res.statusCode=404;res.end();
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 for(const width of [390,1280]){
  const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);await page.waitForFunction(()=>!document.querySelector('.btn-cerca').disabled);
  await page.locator('.person-cognome').fill('Rossi');await page.locator('.btn-cerca').click();await page.getByRole('button',{name:'Sono Maria',exact:true}).waitFor();
  await page.getByRole('button',{name:'Sono Maria',exact:true}).click();await page.locator('.person-card.locked').waitFor();
  assert.equal(await page.locator('.person-nome').inputValue(),'Maria');assert.match(await page.locator('#caparraDaVersare').innerText(),/150/);assert.match(await page.locator('#saldoDaVersare').innerText(),/500/);
  await page.locator('[data-service="bus"]').check();assert.match(await page.locator('#saldoDaVersare').innerText(),/510/);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'No horizontal overflow');
  await page.screenshot({path:'.tmp/public-balance-'+width+'.png',fullPage:true});
  await page.locator(width<900?'#calcBtn':'#calcBtnDesktop').click();await page.locator('#confirmModal.active').waitFor();assert.match(await page.locator('#confirmBody').innerText(),/Caparra ancora da versare/);
  await page.locator('#confirmCancelBtn').click();assert.equal(await page.locator('#confirmModal').evaluate(el=>el.classList.contains('active')),false);
  await page.locator(width<900?'#calcBtn':'#calcBtnDesktop').click();await page.locator('#confirmOkBtn').click();await page.locator('#ibanPopup.active').waitFor();
  assert.match(await page.locator('#globalStatus').innerText(),/riepilogo in invio/);await page.locator('#ibanCloseBtn').click();await page.locator('#payBtn').click();await page.locator('#payPopup.active').waitFor();assert.match(await page.locator('#popupImporto').innerText(),/510/);await page.keyboard.press('Escape');
  await page.locator('[data-service="bus"]').uncheck();assert.match(await page.locator('#saldoDaVersare').innerText(),/500/);assert.equal(await page.locator('#actionSection').isVisible(),false);
  await page.locator('.btn-change').click();await page.locator('.person-cognome').fill('Rossi');await page.locator('.person-nome').fill('Marco');await page.locator('.btn-cerca').click();await page.locator('.person-card.locked').waitFor();assert.equal(await page.locator('.person-nome').inputValue(),'Marco');
  await page.locator('.btn-change').click();await page.locator('.person-cognome').fill('Coppia');await page.locator('.btn-cerca').click();await page.waitForFunction(()=>document.querySelectorAll('.person-card.locked').length===2);assert.match(await page.locator('#saldoDaVersare').innerText(),/1.?000/);
  await page.locator('.btn-change').first().click();assert.equal(await page.locator('.person-card').count(),1);
  assert.deepEqual(errors,[]);await page.close();
 }
 assert.equal(saves,2);console.log('Saldo pubblico desktop/mobile: omonimi, caparra non versata, servizi, annulla/conferma, email, carta, modifica e layout verificati.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
