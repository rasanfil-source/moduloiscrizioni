// Reserved shell: actual navigation markup, computed accents and a single plus.
const fs=require('fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const root='wordpress-plugin/modulo-iscrizioni/';
const php=fs.readFileSync(root+'includes/class-mi-portal.php','utf8');
const label=php.match(/>\+ Crea evento<\/a>/)[0].slice(1,-4);
const css=fs.readFileSync(root+'assets/portal.css','utf8');
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage();fs.mkdirSync('.tmp/portal-gold',{recursive:true});
 for(const width of [320,390,768,1024,1440]){
  await page.setViewportSize({width,height:900});
  await page.setContent('<main class="mi-portal" data-mi-portal-scope="reserved"><header class="mi-portal-header"><div><span class="mi-portal-eyebrow">Portale riservato</span><h1>Segreteria eventi</h1></div></header><nav class="mi-portal-switcher"><a href="?mi_portal_view=management" class="is-active" aria-current="page">Iscrizioni</a><a href="?mi_portal_view=manage">Eventi</a><a href="?mi_portal_view=create">'+label+'</a><a href="?mi_portal_view=payments">Pagamenti</a><a href="?mi_portal_view=communications">Comunicazioni</a><a href="?mi_portal_view=groups">Gruppi</a><a href="?mi_portal_view=operators">Operatori</a></nav><h2>Iscrizioni</h2></main>');
  await page.locator('nav').evaluate(e=>e.setAttribute('aria-label','Segreteria eventi'));
  await page.addStyleTag({content:css});
  await page.addScriptTag({content:fs.readFileSync(root+'assets/portal.js','utf8')});
  await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
  assert.equal(await page.locator('main').evaluate(e=>getComputedStyle(e).backgroundColor),'rgb(245, 243, 238)');
  assert.equal(await page.locator('header').evaluate(e=>getComputedStyle(e).borderBottomColor),'rgb(176, 141, 63)');
  if(width<=760){
   assert.equal(await page.locator('.mi-portal-menu-toggle').evaluate(e=>getComputedStyle(e).borderBottomColor),'rgb(176, 141, 63)');
   await page.screenshot({path:'.tmp/portal-gold/closed-'+width+'.png'});
   await page.locator('.mi-portal-menu-toggle').click();
  }
  const create=page.locator('a[href*="view=create"]');
  assert.equal(await create.textContent(),'+ Crea evento');
  assert.equal(await create.evaluate(e=>getComputedStyle(e,'::before').display),'none');
  assert.match(await page.locator('a.is-active').evaluate(e=>getComputedStyle(e).boxShadow),/rgb\(176, 141, 63\)/);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await page.screenshot({path:'.tmp/portal-gold/menu-'+width+'.png'});
 }
 console.log('Gold accents, ivory background, single plus and responsive shell passed at five widths.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
