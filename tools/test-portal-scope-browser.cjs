// Compare the shared base styles with the reserved restyling on synthetic controls.
const fs=require('fs'),assert=require('node:assert/strict');
const chromium=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright')[process.env.MI_BROWSER_ENGINE||'chromium'];
const css=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal.css','utf8');
const managementCss=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal-management.css','utf8');
const base=css.slice(0,css.indexOf('/* Rifinitura visiva:'));
const content='<h2>Accesso</h2><input placeholder="Email"><button class="mi-primary">Accedi</button><a class="mi-secondary">Indietro</a><article class="mi-event-card"><div class="mi-event-card__date">25 settembre</div><strong>Evento</strong><small>Informazioni</small></article>';
const luminance=hex=>{const rgb=hex.match(/[0-9a-f]{2}/gi).map(v=>parseInt(v,16)/255).map(v=>v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4);return rgb[0]*0.2126+rgb[1]*0.7152+rgb[2]*0.0722;};
for(const [fg,bg,min] of [['1B2B52','FFFFFF',4.5],['83682F','F5F3EE',4.5],['5A657A','F5F3EE',4.5],['287A5B','EAF5F0',4.5],['8A5A00','FFF4D6',4.5],['7C8496','F5F3EE',3]]){const a=luminance(fg),b=luminance(bg);assert.ok((Math.max(a,b)+0.05)/(Math.min(a,b)+0.05)>=min,'Contrast '+fg+'/'+bg);}
const properties=['color','backgroundColor','borderTopColor','borderRadius','fontSize','lineHeight','paddingTop','paddingLeft','boxShadow','outlineColor'];
(async()=>{const browser=await chromium.launch({...(process.env.MI_BROWSER_ENGINE?{}:{channel:'msedge'}),headless:true});try{
 const page=await browser.newPage();
 async function snapshot(style,scope){await page.setContent('<main class="mi-portal" '+(scope?'data-mi-portal-scope="reserved"':'')+'>'+content+'</main>');await page.addStyleTag({content:style});return page.locator('main *').evaluateAll((nodes,props)=>nodes.map(node=>Object.fromEntries(props.map(p=>[p,getComputedStyle(node)[p]]))),properties);}
 for(const width of [320,375,390,768,1024,1440]){
  await page.setViewportSize({width,height:900});
  const before=await snapshot(base,false),excluded=await snapshot(css,false);
  assert.deepEqual(excluded,before,'Shared controls outside reserved scope at '+width);
  const reserved=await snapshot(css,true);
  assert.deepEqual(reserved.slice(-4),before.slice(-4),'Closed event card at '+width);
 }
 await page.setContent('<dialog data-mi-portal-scope="reserved"><button>Chiudi</button></dialog>');await page.addStyleTag({content:css});
 assert.equal(await page.locator('dialog').evaluate(e=>getComputedStyle(e).getPropertyValue('--portal-primary').trim()),'#1B2B52');
 assert.equal(await page.locator('dialog').evaluate(e=>getComputedStyle(e).getPropertyValue('--field-border').trim()),'#7C8496');

 await page.setContent('<main class="mi-portal" data-mi-portal-scope="reserved"><input><button class="mi-primary" disabled>Salva</button><span class="mi-status-pill is-neutral">Annullata</span><span class="mi-status-pill is-info">Gratuito</span><nav class="mi-portal-switcher"><a class="is-active">Eventi</a></nav></main>');
 await page.addStyleTag({content:css});await page.addStyleTag({content:managementCss});
 assert.equal(await page.locator('input').evaluate(e=>getComputedStyle(e).borderTopColor),'rgb(124, 132, 150)');
 assert.equal(await page.locator('button').evaluate(e=>getComputedStyle(e).color),'rgb(107, 115, 133)');
 assert.equal(await page.locator('.is-neutral').evaluate(e=>getComputedStyle(e).color),'rgb(67, 76, 94)');
 assert.equal(await page.locator('.is-info').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(233, 237, 246)');
 assert.equal(await page.locator('a').evaluate(e=>getComputedStyle(e).color),'rgb(27, 43, 82)');
 await page.locator('input').focus();assert.equal(await page.locator('input').evaluate(e=>getComputedStyle(e).outlineWidth),'3px');
 const labels=JSON.parse(require('child_process').execFileSync(process.env.MI_PHP_BINARY||'php',['wordpress-plugin/tests/portal-navigation-labels.php'],{encoding:'utf8'}));
 for(const label of [...labels,'Comunicazioni']){
  for(const width of [320,390,768,1440]){
   await page.goto('about:blank');await page.setViewportSize({width,height:800});
   await page.setContent('<main class="mi-portal" data-mi-portal-scope="reserved"><header class="mi-portal-header"><h1>Segreteria eventi</h1><a class="mi-portal-logout" href="/logout">Esci</a></header><nav class="mi-portal-switcher" aria-label="Segreteria eventi"><a href="/iscrizioni">Iscrizioni</a><a class="is-active" aria-current="page" href="/eventi">'+label+'</a><a href="/pagamenti">Pagamenti</a></nav></main>');
   await page.addStyleTag({content:css});await page.addScriptTag({content:fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal.js','utf8')});await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
   if(width<=760){await page.locator('.mi-portal-menu-toggle').click();assert.deepEqual(await page.locator('nav a').allTextContents(),['Iscrizioni',label,'Pagamenti','Esci']);await page.locator('nav a').first().focus();await page.keyboard.press('Escape');assert.equal(await page.locator('.mi-portal-menu-toggle').getAttribute('aria-expanded'),'false');assert.equal(await page.locator('.mi-portal-menu-toggle').evaluate(e=>e===document.activeElement),true);}
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Menu overflow '+label+' '+width);
  }
 }
 const management=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal-management.js','utf8');
 assert.doesNotMatch(management,/window\.confirm/);
 const presenter=management.match(/const presentDialog=([\s\S]*?);\r?\n    async function ask/)[1],draft=management.match(/const resolveDraft=([\s\S]*?);\r?\n/)[1];await page.addStyleTag({content:managementCss});
 for(const expected of ['save','discard','stay']){
  const result=await page.evaluate(async({presenter,draft,expected})=>{const native=HTMLDialogElement.prototype.showModal;try{HTMLDialogElement.prototype.showModal=undefined;eval('const requestId=()=>"test";const presentDialog='+presenter+';window.__draftResult=('+draft+')();');const dialog=document.querySelector('[data-mi-fallback-dialog]');if(!dialog)throw Error('Missing fallback dialog');const style={dialog:getComputedStyle(dialog).zIndex,backdrop:getComputedStyle(document.querySelector('.mi-management-confirm-backdrop')).zIndex};dialog.querySelector('[value="'+expected+'"]').click();return {value:await window.__draftResult,style};}finally{HTMLDialogElement.prototype.showModal=native;}},{presenter,draft,expected});assert.equal(result.value,expected);assert.deepEqual(result.style,{dialog:'600',backdrop:'500'});
 }
 const escaped=await page.evaluate(async({presenter,draft})=>{const native=HTMLDialogElement.prototype.showModal;try{HTMLDialogElement.prototype.showModal=undefined;eval('const requestId=()=>"test";const presentDialog='+presenter+';window.__draftResult=('+draft+')();');const dialog=document.querySelector('[data-mi-fallback-dialog]');dialog.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));return await window.__draftResult;}finally{HTMLDialogElement.prototype.showModal=native;}},{presenter,draft});assert.equal(escaped,'stay');
 const submitted=await page.evaluate(async({presenter,draft})=>{const native=HTMLDialogElement.prototype.showModal;try{HTMLDialogElement.prototype.showModal=undefined;eval('const requestId=()=>"test";const presentDialog='+presenter+';window.__draftResult=('+draft+')();');const dialog=document.querySelector('[data-mi-fallback-dialog]'),button=dialog.querySelector('[value="save"]');dialog.querySelector('form').requestSubmit(button);return await window.__draftResult;}finally{HTMLDialogElement.prototype.showModal=native;}},{presenter,draft});assert.equal(submitted,'save');
 console.log('Role labels, mobile links, Escape/focus and all three draft fallback choices passed.');
 console.log('Scope: excluded shared controls and closed event card unchanged at six widths; body dialog tokens available. Synthetic CSS checks, not full page baselines.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
