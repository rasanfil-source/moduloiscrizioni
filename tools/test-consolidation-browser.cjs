const fs=require('fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage({viewport:{width:1280,height:900}});
 await page.setContent('<html lang="it"><meta name="viewport" content="width=device-width"><main class="mi-portal"><header class="mi-portal-header"><h1>Segreteria eventi</h1></header><div class="mi-registrations-toolbar">Ricerca in tutti gli eventi</div><section class="mi-management"><h2>Gestione iscrizioni</h2><button id="secondary">Aggiorna riepilogo</button> <button type="submit" id="primary">Salva iscritto</button> <button class="mi-danger" id="danger">Annulla partecipazione</button><p><span class="mi-status-pill is-green">Confermata</span> <span class="mi-status-pill mi-status-pill--confirmed">Confermata</span> <span class="mi-status-pill mi-status-pill--offered">Posto proposto</span></p><a class="mi-contact-icon" href="mailto:test@example.invalid" aria-label="Scrivi email"><svg viewBox="0 0 24 24"><path d="M3.5 5.5h17v13h-17zM4 6l8 6 8-6"/></svg></a></section><section class="mi-extra-service"><label>Pullman<select><option>Partenza dalla stazione</option></select></label></section><section class="mi-payments"><div class="mi-payment-summary"><h3>Situazione economica</h3><label>Importo<input name="importo" class="mi-amount-highlight" value="150,00"></label></div></section></main></html>');
 for(const file of ['portal.css','portal-management.css','portal-payments.css'])await page.addStyleTag({content:fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/'+file,'utf8')});
 const css=(selector,key)=>page.locator(selector).evaluate((e,k)=>getComputedStyle(e)[k],key);
 assert.equal(await css('h1','fontSize'),'24px');
 assert.equal(await css('.mi-registrations-toolbar','backdropFilter'),'none');
 assert.equal(await css('.mi-amount-highlight','animationName'),'none');
 assert.equal(await css('.is-green','backgroundColor'),await css('.mi-status-pill--confirmed','backgroundColor'));
 assert.equal(await css('.mi-contact-icon svg','display'),'block');
 assert.equal(await page.locator('.mi-contact-icon').evaluate(e=>getComputedStyle(e,'::before').content),'none');
 for(const [id,color]of [['secondary','rgb(23, 34, 74)'],['primary','rgb(255, 255, 255)'],['danger','rgb(159, 25, 48)']]){await page.locator('#'+id).hover();assert.equal(await css('#'+id,'color'),color);}
 assert.equal(await css('.mi-extra-service','borderRadius'),'14px');
 await page.screenshot({path:'.tmp/consolidamento-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await page.screenshot({path:'.tmp/consolidamento-mobile.png',fullPage:true});
 console.log('Consolidamento: titolo, pulsanti e hover, badge, SVG, toolbar, servizi e importo verificati.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
