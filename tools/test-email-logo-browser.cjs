const fs=require('fs'),http=require('http'),assert=require('node:assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const html=execFileSync(process.env.MI_PHP_BINARY||'php',['wordpress-plugin/tests/event-wizard.php','42','--html'],{encoding:'utf8'});
const server=http.createServer((req,res)=>{
 if(req.url==='/portal.css'){res.setHeader('Content-Type','text/css');return res.end(fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal.css'));}
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/portal.css"><main class="mi-portal">'+html+'</main>');
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage();await page.goto('http://127.0.0.1:'+server.address().port);
 const select=page.locator('[name=event_email_logo_mode]');
 assert.deepEqual(await select.locator('option').evaluateAll(opts=>opts.map(o=>o.value)),['INHERIT','SHOW','HIDE']);
 await select.evaluate(el=>{el.closest('.mi-wizard-step').classList.add('is-active');el.closest('details').open=true;});
 assert.equal(await select.inputValue(),'INHERIT');
 for(const choice of ['HIDE','SHOW','INHERIT']){await select.selectOption(choice);assert.equal(await select.inputValue(),choice);}
 for(const width of [390,1280]){
  await page.setViewportSize({width,height:900});await select.scrollIntoViewIfNeeded();
  assert.ok(await select.evaluate(el=>el.getBoundingClientRect().right<=innerWidth),'Logo selector exceeds viewport');
  await page.screenshot({path:'.tmp/email-logo-'+width+'.png'});
 }
 console.log('PASS browser: rendered three-state logo selector, selection and mobile/desktop layout.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
