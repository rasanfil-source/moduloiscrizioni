// Read-only presentation regression: production assets, synthetic responses, no external services.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const chromium=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright')[process.env.MI_BROWSER_ENGINE||'chromium'];
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets'),out=path.resolve('.tmp/portal-work-layout');fs.mkdirSync(out,{recursive:true});
const css=['portal.css','portal-management.css','portal-payments.css'];
const title='Percorso parrocchiale dimostrativo con un titolo molto lungo';
const shell=body=>`<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}</style>${css.map(f=>'<link rel="stylesheet" href="/'+f+'">').join('')}<main class="mi-portal"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Area riservata</span><h1>Segreteria eventi</h1></div><a class="mi-portal-logout" href="/logout">Esci</a></header><nav class="mi-portal-switcher" aria-label="Segreteria eventi">${['Iscrizioni','Eventi','Crea evento','Pagamenti','Comunicazioni','Gruppi','Operatori'].map((n,i)=>'<a '+(i===0?'class="is-active" aria-current="page"':'')+' href="/tab/'+i+'">'+n+'</a>').join('')}</nav>${body}</main><script src="/portal.js"></script><script src="/portal-management.js"></script></html>`;
const management=`<section class="mi-management" data-mi-management data-event="42" data-endpoint="/ajax" data-nonce="test" data-order=""><h2>Gestione iscrizioni</h2><div class="mi-management-event-selectors"><label><select data-period-select aria-label="Eventi attivi o passati"><option value="current">Eventi attivi</option><option value="past">Eventi passati</option></select></label><label>Evento<select data-event-select><option value="">Tutti gli eventi</option><option value="42" data-period="current" selected>${title}</option></select></label></div><div data-event-actions><div class="mi-event-toolbar"><button data-refresh aria-label="Aggiorna riepilogo"><span class="mi-refresh-icon">↻</span><span class="mi-refresh-label">Aggiorna riepilogo</span></button><button data-print>Stampa</button><button data-sheet-sync hidden>Sincronizza</button><a class="mi-sheet-button" data-open-sheet hidden><svg width="22" height="22" aria-hidden="true"></svg><span>Apri</span><span aria-hidden="true">↗</span></a></div><p data-management-status role="status"></p></div><div data-management-content></div></section>`;
const people=Array.from({length:12},(_,i)=>({id:i+1,number:i+1,code:'ORD-DEMO',name:i?'Persona '+String(i).padStart(2,'0'):'Alessandra Maria Della Valle Lunghissima',buyer:'Referente prova',email:'persona'+i+'@example.invalid',phone:'0312345678',status:'CONFIRMED',room:'DS1',fields:{custom_test:'Risposta sintetica'},missing:[],collectible:true,unassigned:false,requests:'',options:[],paid:0,balance:1200}));
let full=false;
const summary=()=>({sheet_url:'https://layout.invalid/?mi_open_sheet=42',field_labels:{custom_test:'Richiesta alimentare'},features:{rooms:full,payments:full,deposit:false},items:[{code:'ORD-DEMO',name:'Referente prova',status:'CONFIRMED',participants:12,balance:14400,paid:0,total:14400,missing:full?1:0,unassigned:0,active:true,collectible:full}],people:people.map((p,i)=>({...p,missing:full&&i===0?['Cellulare']:[]})),rooms:[],fields:[{key:'custom_test',label:'Richiesta alimentare',type:'text'}],attendance_availability:{available:full},registration_url:'https://example.invalid/iscrizione',updated_at:'2026-09-24T08:00:00Z'});
function sheetXml(buffer){for(let offset=0;buffer.readUInt32LE(offset)===0x04034b50;){const size=buffer.readUInt32LE(offset+18),nameLength=buffer.readUInt16LE(offset+26),extra=buffer.readUInt16LE(offset+28),start=offset+30+nameLength+extra;const name=buffer.subarray(offset+30,offset+30+nameLength).toString();if(name==='xl/worksheets/sheet1.xml')return buffer.subarray(start,start+size).toString();offset=start+size;}throw Error('Missing worksheet');}
(async()=>{const browser=await chromium.launch({...(process.env.MI_BROWSER_ENGINE?{}:{channel:process.env.MI_BROWSER_CHANNEL||'msedge'}),headless:true});const page=await browser.newPage({viewport:{width:1280,height:900},acceptDownloads:true});page.setDefaultTimeout(12000);const errors=[];page.on('pageerror',e=>{if(e.message!=='ResizeObserver loop completed with undelivered notifications.')errors.push(e.message);});let body=management;
try{
await page.route('**/*',async route=>{const url=new URL(route.request().url()),file=path.basename(url.pathname);if(url.hostname!=='layout.invalid')return route.abort();if(url.pathname==='/ajax'){const operation=new URLSearchParams(route.request().postData()).get('operation');assert.ok(['summary','open_sheet','sheet_changes'].includes(operation),'Unexpected operation '+operation);return route.fulfill({json:{success:true,data:operation==='summary'?summary():{url:''}}});}if(css.includes(file)||['portal.js','portal-management.js'].includes(file))return route.fulfill({body:fs.readFileSync(path.join(assets,file)),contentType:file.endsWith('.css')?'text/css; charset=utf-8':'text/javascript; charset=utf-8'});return route.fulfill({body:shell(body),contentType:'text/html'});});
await page.goto('https://layout.invalid/');await page.locator('.mi-participant-row').first().waitFor();
assert.equal(await page.locator('[data-query]').evaluate(input=>{input.setAttribute('aria-invalid','true');const color=getComputedStyle(input).borderTopColor;input.removeAttribute('aria-invalid');return color;}),'rgb(180, 35, 24)');
const columns=await page.locator('[data-export-column]').evaluateAll(nodes=>nodes.map(n=>n.dataset.exportColumn));assert.ok(columns.length>=5);
const fits=async()=>{await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));const overflow=await page.evaluate(()=>({url:location.pathname,width:innerWidth,total:document.documentElement.scrollWidth,scrollX,body:document.body.getBoundingClientRect().toJSON(),nodes:[...document.querySelectorAll('main *')].filter(e=>{const r=e.getBoundingClientRect();return r.width&&(r.right+scrollX>innerWidth+1||e.scrollWidth>e.clientWidth+1);}).slice(0,8).map(e=>({tag:e.tagName,cls:e.className,rect:e.getBoundingClientRect().toJSON(),scroll:e.scrollWidth,client:e.clientWidth}))}));assert.ok(overflow.total<=overflow.width,JSON.stringify(overflow));};
await page.screenshot({path:path.join(out,'desktop.png'),fullPage:true});
for(const width of [760,600,390,360,320]){
 await page.setViewportSize({width,height:844});await fits();
 const cards=page.locator('.mi-summary-card');
 assert.equal(await page.locator('details[data-summary-details]').count(),0);
 assert.equal(await cards.first().isVisible(),true);
 assert.equal(await page.locator('.mi-summary-card--missing').isVisible(),true);
 const first=await cards.first().boundingBox(),missing=await page.locator('.mi-summary-card--missing').boundingBox();
 assert.equal(first.y,missing.y);assert.ok(missing.x>first.x);assert.ok(first.height<120&&missing.height<120);
 const printBox=await page.locator('[data-print]').boundingBox(),exportBox=await page.locator('[data-export]').boundingBox();
 assert.equal(printBox.y,exportBox.y);assert.ok(printBox.height<=64&&exportBox.height<=64);assert.ok(Math.abs(printBox.width-exportBox.width)<1);
 const refreshBox=await page.locator('[data-refresh]').boundingBox(),sheetBox=await page.locator('[data-open-sheet]').boundingBox();
 assert.ok(sheetBox,'Open sheet visible');assert.equal(refreshBox.y,sheetBox.y);assert.ok(refreshBox.width>100&&sheetBox.width>100);
 assert.equal(await page.locator('.mi-portal-switcher').isVisible(),false);
 await page.locator('.mi-portal-menu-toggle').click();assert.equal(await page.locator('.mi-portal-switcher a').count(),8);
 assert.equal(await page.getByRole('link',{name:'Operatori',exact:true}).isVisible(),true);
 await page.locator('.mi-portal-switcher a').first().focus();await page.keyboard.press('Escape');assert.equal(await page.locator('.mi-portal-switcher').isVisible(),false);
 const toggle=page.locator('[data-toggle-filters]'), find=page.locator('[data-run-query]');
 if(await toggle.getAttribute('aria-expanded')==='true')await toggle.click();
 const before=await toggle.boundingBox();assert.equal(before.y,(await find.boundingBox()).y);
 await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');
 assert.equal((await toggle.boundingBox()).y,before.y);
 assert.ok((await page.locator('.mi-advanced-filters .mi-participant-filters').boundingBox()).y>=before.y+before.height);
 await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'false');
 const scroll=page.locator('.mi-participant-scroll');
 assert.equal(await scroll.evaluate(e=>{e.scrollTop=100;return e.scrollTop>0&&e.clientHeight<=innerHeight*.56;}),true);
 await scroll.evaluate(e=>e.scrollTop=0);
 assert.equal(await page.locator('.mi-management-event-context').evaluate(e=>e.open),false);
 assert.equal(await page.locator('.mi-management-event-context>summary').innerText(),title);
 assert.equal(await page.evaluate(()=>Boolean(document.querySelector('[data-new-registration]').compareDocumentPosition(document.querySelector('.mi-management-summary-cards'))&Node.DOCUMENT_POSITION_FOLLOWING)),true);
 for(const cell of await page.locator('.mi-participant-contacts-cell').all())assert.equal(await cell.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;}),true);
 for(const icon of await page.locator('.mi-contact-icon').all())assert.deepEqual(await icon.evaluate(e=>{const r=e.getBoundingClientRect();return [r.width,r.height];}),[44,44]);
 assert.equal(await page.locator('[data-query]').isVisible(),true);assert.ok((await page.locator('[data-query]').boundingBox()).y<650,'Search should be near the top');
 assert.deepEqual(await page.locator('[data-export-column]').evaluateAll(nodes=>nodes.map(n=>n.dataset.exportColumn)),columns);
 if(width===390||width===320)await page.screenshot({path:path.join(out,'mobile-'+width+'.png'),fullPage:true});
}
// Column selection survives resize/refresh and drives both print and the actual XLSX download.
await page.locator('[data-export-settings]>summary').click();
for(const checkbox of await page.locator('[data-export-column]').all())await checkbox.uncheck();
await page.locator('[data-export-column="name"]').check();await page.locator('[data-export-column="phone"]').check();
await page.evaluate(()=>{window.print=()=>{window.printed=document.querySelector('[data-print-list]').innerHTML;window.dispatchEvent(new Event('afterprint'));};});
await page.locator('[data-print]').click();await page.waitForFunction(()=>window.printed);
const print=await page.evaluate(()=>window.printed);assert.match(print,/Cellulare|Telefono/);assert.doesNotMatch(print,/<th>Email<\/th>/);
const downloadPromise=page.waitForEvent('download');await page.locator('[data-export]').click();const download=await downloadPromise;const xml=sheetXml(fs.readFileSync(await download.path()));assert.match(xml,/Cellulare|Telefono/);assert.doesNotMatch(xml,/>Email</);assert.match(xml,/0312345678/);
await page.locator('[data-refresh]').click();await page.locator('.mi-participant-row').first().waitFor();
assert.deepEqual(await page.locator('[data-export-column]:checked').evaluateAll(nodes=>nodes.map(n=>n.dataset.exportColumn)).then(a=>a.sort()),['name','phone']);
await page.locator('[data-query]').fill('Lunghissima');await page.waitForFunction(()=>document.querySelectorAll('.mi-participant-row').length===1);await page.locator('[data-clear-query]').click();await page.waitForFunction(()=>document.querySelectorAll('.mi-participant-row').length===12);
full=true;await page.reload();await page.locator('.mi-attendance-cell input').first().waitFor();await fits();
for(const selector of ['.mi-participant-room-code','.mi-participant-status','.mi-attendance-cell','.mi-participant-missing'])assert.ok(await page.locator('tbody '+selector).first().isVisible(),selector);
assert.equal(await page.locator('.mi-summary-card--missing>strong').innerText(),'1');
assert.equal(await page.locator('.mi-summary-card--missing').isVisible(),true);
await page.screenshot({path:path.join(out,'mobile-conditional-columns.png'),fullPage:true});
await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(out,'mobile-first-screen.png')});
await page.locator('[data-export-settings]>summary').click();await page.locator('[data-participant-reports]').screenshot({path:path.join(out,'report-columns-mobile.png')});
assert.equal(await page.locator('tbody td.mi-participant-missing:visible').count(),1);
await page.setViewportSize({width:1280,height:900});await page.waitForFunction(()=>document.querySelector('.mi-portal-header .mi-portal-logout'));assert.equal(await page.locator('.mi-portal-switcher').isVisible(),true);assert.equal(await page.locator('.mi-portal-header .mi-portal-logout').count(),1);assert.equal(await page.locator('.mi-management-summary-cards').isVisible(),true);await fits();
// Shared tab components keep their form controls across desktop/mobile layouts.
const tabs={
 groups:'<section class="mi-groups"><header class="mi-groups__heading"><h2>Gruppi</h2><p>Gestione dei gruppi</p></header><details class="mi-group-card" open><summary>Gruppo dimostrativo</summary><form><div class="mi-group-form-grid"><label>Nome<input name="name" value="Gruppo dimostrativo"></label><label>Email<input name="email" value="gruppo@example.invalid"></label><label>Colore<input name="color" type="color" value="#123456"></label></div><button class="mi-primary">Salva gruppo</button></form></details></section>',
 operators:'<section class="mi-operators"><header class="mi-operators__heading"><h2>Operatori</h2></header><details class="mi-operator-card" open><summary><span>Operatore dimostrativo<small>operatore@example.invalid</small></span></summary><form><div class="mi-operator-grid"><label>Nome<input name="name" value="Operatore dimostrativo"></label><label>Ruolo<select name="role"><option>Gestore gruppo</option><option>Gestore evento</option></select></label></div><fieldset><legend>Gruppi assegnati</legend><label><input type="checkbox" name="scope" checked>Gruppo dimostrativo</label></fieldset><button class="mi-primary">Salva operatore</button></form></details></section>',
 communications:'<section class="mi-portal-communications"><h2>Comunicazioni</h2><form><label>Evento<select name="event"><option>'+title+'</option></select></label><label>Oggetto<input name="subject"></label><label>Messaggio<textarea name="message"></textarea></label><button class="mi-primary">Invia comunicazione</button></form></section>',
 events:'<section><h2>Gestisci eventi</h2><div class="mi-event-grid"><a class="mi-event-card" href="#"><span class="mi-event-card__date"><small>OTT</small><strong>12</strong></span><span class="mi-event-card__content"><span class="mi-event-card__identity"><strong>'+title+'</strong><small>Gruppo dimostrativo</small></span></span></a></div><section class="mi-event-management"><details open><summary>Dettagli principali</summary><button class="mi-secondary">Modifica evento</button></details></section></section>'
};
let payment=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-payments.php','utf8');tabs.payments=payment.slice(payment.indexOf('<section class="mi-payments"'),payment.lastIndexOf('</section>')+10).replace(/<\?php[\s\S]*?\?>/g,'');
tabs.create=execFileSync(process.env.MI_PHP_BINARY||'php',['wordpress-plugin/tests/event-wizard.php','0','--html'],{encoding:'utf8'});
for(const [name,markup]of Object.entries(tabs)){body=markup;await page.goto('https://layout.invalid/'+name);const fields=await page.locator('input,select,textarea').evaluateAll(nodes=>nodes.map(n=>n.name));for(const width of [1440,1024,768,390,375,320]){await page.setViewportSize({width,height:844});await fits();assert.deepEqual(await page.locator('input,select,textarea').evaluateAll(nodes=>nodes.map(n=>n.name)),fields);for(const control of await page.locator('button,a').all()){if(!await control.isVisible())continue;const box=await control.boundingBox();if(box)assert.ok(box.x>=-1&&box.x+box.width<=width+1,name+' comando tagliato a '+width+'px');}if([1440,768,390,320].includes(width))await page.screenshot({path:path.join(out,name+'-'+width+'.png'),fullPage:true});}}
assert.deepEqual(errors,[]);console.log('PASS: navigation, DOM order, 320–1280px, contacts, conditional columns, search, report choices, print/XLSX and all tab layouts.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
