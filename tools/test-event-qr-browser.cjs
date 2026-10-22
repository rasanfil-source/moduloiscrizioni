// Verifica browser locale con dati sintetici; non accede al sito WordPress.
const fs=require('fs'), http=require('http'), assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE || 'playwright');
const jsQR=require('../.tmp/event-qr-tests/node_modules/jsqr');
const {PNG}=require('../.tmp/event-qr-tests/node_modules/pngjs');
const asset='wordpress-plugin/modulo-iscrizioni/assets/';
let payload='https://example.invalid/v/evento', logo='', failure=false, requests=0;
const markup='<div data-mi-event-qr="/data"><img data-mi-qr-preview hidden width="240" height="240" style="max-width:100%;height:auto"><p data-mi-qr-status></p><button data-mi-qr-download disabled>Scarica</button></div>';
const server=http.createServer((req,res)=>{
 if(req.url==='/data'){requests++;res.setHeader('Content-Type','application/json');return res.end(JSON.stringify(failure?{success:false,data:'Logo mancante'}:{success:true,data:{url:payload,logo,filename:'iscrizioni-evento-42-qr.png'}}));}
 if(req.url.endsWith('.js')){res.setHeader('Content-Type','text/javascript');return res.end(fs.readFileSync(asset+req.url.slice(1)));}
 res.setHeader('Content-Type','text/html');res.end('<!doctype html><html><body>'+markup+'<script src="/qrcode-generator-2.0.4.js"></script><script src="/event-qr.js"></script></body></html>');
});
function decode(file,expected){const png=PNG.sync.read(fs.readFileSync(file));const code=jsQR(new Uint8ClampedArray(png.data),png.width,png.height);assert.ok(code,'PNG must decode with central logo');assert.equal(code.data,expected);assert.ok(png.width>=1000);return png;}
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
 const page=await browser.newPage({viewport:{width:390,height:844}}), errors=[];page.on('pageerror',e=>errors.push(e.message));
 logo=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=200;c.height=120;const x=c.getContext('2d');x.fillStyle='#f00000';x.fillRect(0,0,200,120);x.fillStyle='#fff';x.font='bold 65px sans-serif';x.fillText('GR',50,82);return c.toDataURL();});
 await page.goto('http://127.0.0.1:'+server.address().port);
 await page.locator('[data-mi-qr-preview]').waitFor({state:'visible'});
 assert.equal(requests,1,'automatic generation');
 async function download(name){const wait=page.waitForEvent('download');await page.locator('[data-mi-qr-download]').first().click();const d=await wait;assert.equal(d.suggestedFilename(),'iscrizioni-evento-42-qr.png');await d.saveAs('.tmp/'+name);return decode('.tmp/'+name,payload);}
 let image=await download('event-qr-short.png');
 assert.ok(image.data.some((v,i)=>i%4===0&&v>200&&image.data[i+1]<20),'logo red pixels preserved');
 payload='https://example.invalid/'+('long-path/'.repeat(20))+'?test=àé&other=1';
 await download('event-qr-long.png');
 logo=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=c.height=90;const x=c.getContext('2d');x.fillStyle='#0000ff';x.fillRect(0,0,90,90);return c.toDataURL();});
 image=await download('event-qr-new-logo.png');
 assert.ok(image.data.some((v,i)=>i%4===2&&v>200&&image.data[i-2]<20),'new logo applied');
 failure=true;await page.locator('[data-mi-qr-download]').click();await page.waitForFunction(()=>document.querySelector('[data-mi-qr-status]').textContent==='Logo mancante');assert.equal(await page.locator('[data-mi-qr-preview]').isVisible(),false);
 failure=false;await download('event-qr-retry.png');
 await page.evaluate(html=>document.body.insertAdjacentHTML('beforeend',html),markup);
 await page.locator('[data-mi-qr-preview]').nth(1).waitFor({state:'visible'});
 assert.deepEqual(errors,[]);await page.screenshot({path:'.tmp/event-qr-mobile.png'});
 console.log('QR browser: automatic PNG, decoded short/long UTF-8 URL, logo update, errors/retry, dynamic panels passed.');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
