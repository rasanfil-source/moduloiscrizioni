const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const out=path.resolve('.tmp/payment-report');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
const page=await browser.newPage({viewport:{width:1280,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.route('**/*',route=>{const url=new URL(route.request().url());if(url.pathname==='/portal.css'||url.pathname==='/portal.js')return route.fulfill({body:fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets'+url.pathname),contentType:url.pathname.endsWith('css')?'text/css':'text/javascript'});
const html=execFileSync(process.env.MI_PHP_BINARY||'.tmp/php-runtime/php.exe',['wordpress-plugin/tests/portal-payment-report.php','--html',url.search.slice(1)],{encoding:'utf8'});
return route.fulfill({body:'<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/portal.css"><style>body{margin:0}</style>'+html+'<script src="/portal.js"></script></html>',contentType:'text/html'});});
await page.goto('https://report.invalid/?mi_portal=1&mi_portal_view=payment-report');
assert.equal(await page.locator('.mi-payment-report th').count(),9);
assert.equal(await page.locator('.mi-portal-switcher a.is-active').innerText(),'Pagamenti');
for(const width of [1280,390,320]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Document overflow '+width);assert.ok(await page.locator('.mi-payment-report__table').evaluate(e=>e.clientWidth<e.scrollWidth));await page.screenshot({path:path.join(out,'report-'+width+'.png'),fullPage:true});}
await page.locator('[name=payment_source]').selectOption('CASH');await page.locator('[name=transaction_kind]').selectOption('REFUND');await page.locator('[name=payment_from]').fill('2026-09-05');await page.getByRole('button',{name:'Applica filtri'}).click();
await page.waitForURL('**payment_source=CASH**');assert.equal(await page.locator('[name=payment_from]').inputValue(),'2026-09-05');
const csv=new URL(await page.getByRole('link',{name:'Esporta CSV'}).getAttribute('href'));assert.equal(csv.pathname,'/wp-admin/admin-post.php');assert.equal(csv.searchParams.get('payment_source'),'CASH');assert.equal(csv.searchParams.get('transaction_kind'),'REFUND');assert.equal(csv.searchParams.get('payment_from'),'2026-09-05');
await page.locator('.page-numbers').click();await page.waitForURL('**paged=2**');assert.equal(new URL(page.url()).searchParams.get('mi_portal_view'),'payment-report');assert.equal(await page.locator('[name=payment_source]').inputValue(),'CASH');
assert.deepEqual(errors,[]);console.log('PASS: real portal route, all columns, desktop/mobile overflow, local table scroll, combined filters, CSV and pagination.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
