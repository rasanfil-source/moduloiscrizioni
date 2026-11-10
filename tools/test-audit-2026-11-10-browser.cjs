// PHP e browser locali con dati sintetici; nessun servizio remoto.
const fs=require('node:fs'),http=require('node:http'),assert=require('node:assert/strict'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const php=process.env.MI_PHP_BINARY||'php',assets='wordpress-plugin/modulo-iscrizioni/assets/';
const phpArgs=process.env.MI_PHP_EXTENSION_DIR?['-n','-d','extension_dir='+process.env.MI_PHP_EXTENSION_DIR,'-d','extension=mbstring']:[];
const server=http.createServer((req,res)=>{
 const file=req.url.slice(1);
 if(['portal.js','portal.css','portal-management.css'].includes(file)){res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');return res.end(fs.readFileSync(assets+(process.env.MI_TEST_MIN?'min/':'')+file));}
 const html=execFileSync(php,[...phpArgs,'wordpress-plugin/tests/event-wizard.php','42','--html'],{encoding:'utf8'});
 res.setHeader('Content-Type','text/html; charset=utf-8');
 res.end('<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/portal.css"><link rel="stylesheet" href="/portal-management.css"><style>body{margin:0;font:16px/1.5 system-ui}*{box-sizing:border-box}.mi-portal{max-width:960px;margin:auto;padding:20px}[hidden]{display:none!important}</style><main class="mi-portal" data-mi-portal-scope="reserved">'+html+'</main><script src="/portal.js"></script></html>');
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  for(const width of [390,1280]){
   const page=await browser.newPage({viewport:{width,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto('http://127.0.0.1:'+server.address().port);
   await page.locator('[name=title]').fill('Verifica date evento');
   for(let i=0;i<3;i++)await page.locator('[data-mi-next]').click();
   await page.locator('[data-mi-transport-none]').check();await page.locator('[data-mi-next]').click();
   await page.locator('[name=closes_at]').fill('01/11/2027 12:00');await page.locator('[name=starts_at]').fill('02/11/2027 12:00');
   const end=page.locator('[name=ends_at]');assert.equal(await end.evaluate(el=>el.required),false);
   await end.fill('01/11/2027 12:00');assert.equal(await end.evaluate(el=>el.checkValidity()),false);
   await page.locator('[data-mi-next]').click();assert.match(await page.locator('.mi-wizard-step.is-active h2').innerText(),/Date e posti/);
   await end.fill('04/11/2027 18:00');assert.equal(await end.evaluate(el=>el.checkValidity()),true);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   fs.mkdirSync('.tmp/audit-2026-11-10-browser',{recursive:true});await page.screenshot({path:'.tmp/audit-2026-11-10-browser/date-'+width+(process.env.MI_TEST_MIN?'-min':'')+'.png'});
   await page.locator('[data-mi-next]').click();assert.doesNotMatch(await page.locator('.mi-wizard-step.is-active h2').innerText(),/Date e posti/);
   await page.locator('[data-mi-back]').click();await end.fill('');assert.equal(await end.evaluate(el=>el.checkValidity()),true);
   await page.evaluate(()=>{const host=document.createElement('div');host.className='mi-management';host.innerHTML='<label>Nota<textarea></textarea></label>';document.body.append(host);});
   assert.equal(await page.locator('.mi-management textarea').evaluate(el=>getComputedStyle(el).resize),'none');assert.deepEqual(errors,[]);
   await page.close();
  }
  console.log('PASS: conclusione facoltativa, formato date, ordine temporale, ripiego vuoto, nessun overflow a 390/1280 px e textarea resize:none.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
