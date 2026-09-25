// Synthetic PHP-rendered wizard, exercised in a local browser only.
const fs=require('fs'),http=require('http'),assert=require('node:assert/strict'),{execFileSync}=require('child_process');
const chromium=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright')[process.env.MI_BROWSER_ENGINE||'chromium'];
const php=process.env.MI_PHP_BINARY||'php';
const server=http.createServer((req,res)=>{
 if(req.url==='/portal.js'){res.setHeader('Content-Type','text/javascript');return res.end(fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal.js'));}
 const id=new URL(req.url,'http://localhost').searchParams.get('mi_copy_from')||'0';
 const html=execFileSync(php,['wordpress-plugin/tests/event-wizard.php',id,'--html'],{encoding:'utf8'});
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><style>.mi-wizard-step{display:none}.mi-wizard-step.is-active{display:block}[hidden]{display:none!important}</style>'+html+'<script src="/portal.js"></script>');
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({...(process.env.MI_BROWSER_ENGINE?{}:{channel:'msedge'}),headless:true});try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);
 await page.locator('[name=title]').fill('Nuovo ciclo');await page.locator('[data-mi-copy-event]').selectOption('42');await page.waitForURL('**mi_copy_from=42');
 assert.equal(await page.locator('[name=title]').inputValue(),'Nuovo ciclo');assert.equal(await page.locator('[name=capacity]').inputValue(),'77');assert.equal(await page.locator('[name=max_per_order]').inputValue(),'8');
 assert.equal(await page.locator('[name="custom_question_label[]"]').first().inputValue(),'Allergie');assert.equal(await page.locator('[name=closes_at]').inputValue(),'');
 for(let i=0;i<3;i++)await page.locator('[data-mi-next]').click();
 await page.locator('[name=booking_limit_mode][value=ONE]').check();
 assert.deepEqual(errors,[]);assert.notEqual(await page.locator('[data-mi-participant-scope]').getAttribute('disabled'),null);
 await page.locator('[name=booking_limit_mode][value=MULTIPLE]').check();assert.equal(await page.locator('[data-mi-participant-scope]').getAttribute('disabled'),null);
 await page.locator('[name=closes_at]').fill('01/11/2027 12:00');await page.locator('[name=starts_at]').fill('02/11/2027 12:00');await page.locator('[data-mi-next]').click();
 assert.equal(await page.locator('[data-mi-participant-scope]').isVisible(),true);
 await page.locator('[data-mi-back]').click();await page.locator('[name=booking_limit_mode][value=ONE]').check();await page.locator('[data-mi-next]').click();
 assert.equal(await page.locator('[data-mi-participant-scope]').isVisible(),false);assert.deepEqual(errors,[]);
 console.log('Wizard browser: model reload, title retained, fields copied and single/multiple conditional question passed.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
