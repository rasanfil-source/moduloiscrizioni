const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const html=execFileSync(path.resolve('.tmp/php-runtime/php.exe'),['wordpress-plugin/tests/sheet-open-render.php'],{encoding:'utf8'});
let stage=0,requests=[];
const server=http.createServer(async(req,res)=>{
 if(req.url!=='/ajax'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(html);}
 let body='';for await(const part of req)body+=part;const p=new URLSearchParams(body);requests.push(Object.fromEntries(p));res.setHeader('Content-Type','application/json');
 if(stage===0){stage++;return res.end(JSON.stringify({success:true,data:{ready:false,token:'synthetic-token'}}));}
 if(stage===1){stage++;res.statusCode=400;return res.end(JSON.stringify({success:false,data:{message:'Conferma prima le modifiche pendenti.'}}));}
 res.end(JSON.stringify({success:true,data:{ready:true,url:'https://docs.google.com/spreadsheets/d/synthetic/edit'}}));
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://docs.google.com/**',route=>route.fulfill({contentType:'text/html',body:'<h1>Foglio verificato</h1>'}));
 await page.goto('http://127.0.0.1:'+server.address().port);await page.getByRole('button',{name:'Riprova',exact:true}).waitFor();
 assert.match(await page.locator('#status').innerText(),/modifiche pendenti/);assert.match(page.url(),/^http:\/\/127/);
 assert.equal(requests[0].token,'');assert.equal(requests[1].token,'synthetic-token');assert.equal(requests[0].event_id,'42');
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.getByRole('button',{name:'Riprova',exact:true}).click();await page.waitForURL('https://docs.google.com/spreadsheets/d/synthetic/edit');
 assert.equal(requests[2].token,'');assert.deepEqual(errors,[]);
 const source=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-management.php','utf8');assert.match(source,/<span>Apri<\/span>/);assert.doesNotMatch(source,/<span>Aggiorna e apri/);
 console.log('PASS browser: aggiornamento obbligatorio, attesa, errore senza apertura, retry, apertura verificata e layout mobile.');
 }finally{await browser.close();server.close();}})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
