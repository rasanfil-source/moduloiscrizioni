// Runs the real management UI against synthetic responses. No live records.
const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
let opens=0,mode='error';const calls=[];
const markup=`<!doctype html><html><meta charset="utf-8"><section data-mi-management data-event="42" data-endpoint="/ajax" data-nonce="test"><select data-event-select><option value="42">Prova</option></select><button data-refresh>Aggiorna</button><button data-print>Stampa</button><button data-sheet-sync>Sincronizza</button><a data-open-sheet hidden><span>Apri</span></a><p data-management-status></p><div data-management-content></div></section><script src="/portal-management.js"></script></html>`;
const server=http.createServer(async(req,res)=>{
 if(req.url==='/portal-management.js'){res.setHeader('Content-Type','text/javascript');return res.end(fs.readFileSync(path.resolve('wordpress-plugin/modulo-iscrizioni/assets/portal-management.js')));}
 if(req.url!=='/ajax')return res.end(markup);
 let body='';for await(const chunk of req)body+=chunk;const p=new URLSearchParams(body);calls.push(Object.fromEntries(p));res.setHeader('Content-Type','application/json');
 if(p.get('operation')==='open_sheet'){
  opens++;if(mode==='error')return res.end(JSON.stringify({success:false,data:{message:'Modifiche pendenti: sincronizza prima.'}}));
  return res.end(JSON.stringify({success:true,data:opens===2?{ready:false,token:'test-token',retry_after:1,message:'Google occupato, attesa…'}:{ready:true,url:'https://docs.google.com/spreadsheets/d/synthetic/edit'}}));
 }
 res.end(JSON.stringify({success:true,data:{sheet_url:'/?mi_open_sheet=42',features:{},people:[],items:[],rooms:[],updated_at:'2026-09-20T12:00:00Z'}}));
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://docs.google.com/**',route=>route.fulfill({contentType:'text/html',body:'Foglio aggiornato'}));
 const home='http://127.0.0.1:'+server.address().port+'/';await page.goto(home);
 await page.getByText('Apri',{exact:true}).click();await page.getByText(/Apertura non completata/).waitFor();assert.equal(page.url(),home);
 assert.equal(await page.locator('[data-open-sheet]').getAttribute('aria-disabled'),null);
 mode='ready';await page.getByText('Apri',{exact:true}).click();await page.getByText('Google occupato, attesa…',{exact:true}).waitFor();
 assert.equal(page.url(),home);assert.equal(await page.locator('[data-open-sheet]').getAttribute('aria-disabled'),'true');
 await page.locator('[data-open-sheet]').dispatchEvent('click');assert.equal(opens,2);
 await page.waitForURL('https://docs.google.com/spreadsheets/d/synthetic/edit');
 assert.deepEqual(calls.filter(x=>x.operation==='open_sheet').map(x=>x.token),['','','test-token']);assert.deepEqual(errors,[]);
 console.log('PASS browser: Apri inline, error recovery, busy retry, duplicate clicks blocked, verified same-tab navigation.');
 }finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
