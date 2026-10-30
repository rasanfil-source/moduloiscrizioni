// Interfaccia PHP reale con dati sintetici, nessuna connessione al sito operativo.
const fs=require('fs'),http=require('http'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const php=process.env.MI_PHP_BINARY||'php';
const assetRoot=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const server=http.createServer((req,res)=>{
 const pathname=new URL(req.url,'http://localhost').pathname;
 if(['/portal.js','/portal.css','/public.css'].includes(pathname)){res.setHeader('Content-Type',pathname.endsWith('.js')?'text/javascript':'text/css');return res.end(fs.readFileSync(path.join(assetRoot,pathname)));}
 if(pathname==='/fixture-image.svg'){res.setHeader('Content-Type','image/svg+xml');return res.end('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="360"><rect width="800" height="360" fill="#e8edf8"/><circle cx="400" cy="180" r="90" fill="#bccbeb"/><text x="400" y="190" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#17224a">Gruppo 12</text></svg>');}
 const waiting=pathname==='/waiting';
 const html=execFileSync(php,['-n','-d','extension_dir='+path.resolve(path.dirname(php),'ext'),'-d','extension=mbstring','wordpress-plugin/tests/group-links.php',waiting?'--waiting':'--html'],{encoding:'utf8'});
 res.setHeader('Content-Type','text/html; charset=utf-8');res.end('<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/'+(waiting?'public':'portal')+'.css"><style>body{margin:0;background:#f5f7fa}.mi-portal{max-width:1080px;margin:auto;padding:24px}*{box-sizing:border-box}</style></head><body>'+html+(waiting?'':'<script src="/portal.js"></script>')+'</body></html>');
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:1000}}),errors=[];
  page.setDefaultTimeout(10000);
  page.on('pageerror',e=>errors.push(e.message));
  const url='http://127.0.0.1:'+server.address().port;
  await page.goto(url);
  const create=page.locator('#create-group'),edit=page.locator('#edit-group');
  await create.locator('summary').first().click();
  await create.getByRole('button',{name:'Aggiungi link permanente'}).click();
  await create.getByLabel('Categoria',{exact:true}).fill('Catechesi');
  await create.getByLabel('Indirizzo permanente').fill('12cesteroma');
  assert.equal(await create.locator('[data-mi-link-slug]').textContent(),'12cesteroma');
  await create.getByRole('button',{name:'Aggiungi link permanente'}).click();
  const second=create.locator('[data-mi-link-row]').nth(1);
  await second.getByLabel('Categoria',{exact:true}).fill('Gite');
  await second.getByLabel('Indirizzo permanente').fill('12cesteeventi');
  assert.equal(await create.evaluate(form=>new FormData(form).get('group_links[1][slug]')),'12cesteeventi');
  await second.getByRole('button',{name:'Rimuovi questo nuovo link'}).click();
  assert.equal(await create.locator('[data-mi-link-row]').count(),1);
  await create.getByRole('button',{name:'Aggiungi link permanente'}).click();
  assert.equal(await create.locator('[data-mi-link-row]').last().locator('input[type=file]').getAttribute('name'),'group_link_image_2');
  await create.locator('[data-mi-link-row]').last().getByRole('button',{name:'Rimuovi questo nuovo link'}).click();
  await edit.locator('summary').first().click();
  assert.equal(await edit.locator('[data-mi-link-row]').count(),2);
  assert.equal(await edit.locator('input[readonly]').count(),2);
  await edit.locator('[data-mi-link-row]').first().getByText('Pagina di attesa senza eventi',{exact:true}).click();
  await edit.locator('[data-mi-link-row]').first().locator('textarea').fill('Ci vediamo al prossimo incontro.');
  const group=page.locator('#event-form [name=activity_id]'),category=page.locator('#event-form [name=group_link]');
  assert.equal(await category.inputValue(),'12cesteroma');
  await group.selectOption('13');assert.equal(await category.inputValue(),'');
  assert.deepEqual(await category.locator('option[value="12cesteroma"]').evaluate(option=>({disabled:option.disabled,hidden:option.hidden})),{disabled:true,hidden:true});
  await category.selectOption('altragita');await group.selectOption('12');assert.equal(await category.inputValue(),'');
  await category.selectOption('12cesteeventi');
  fs.mkdirSync('.tmp/group-links-265',{recursive:true});
  await edit.screenshot({path:'.tmp/group-links-265/desktop.png'});
  await page.setViewportSize({width:390,height:844});
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Overflow mobile editor');
  await edit.screenshot({path:'.tmp/group-links-265/mobile.png'});
  await page.goto(url+'/waiting');
  await page.getByRole('heading',{name:'Ci ritroviamo presto'}).waitFor();
  assert.equal(await page.locator('img').evaluate(img=>img.complete&&img.naturalWidth>0),true);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'Overflow mobile attesa');
  await page.screenshot({path:'.tmp/group-links-265/waiting-mobile.png',fullPage:true});
  assert.deepEqual(errors,[]);
  console.log('PASS browser: creazione/gestione link multipli, campi immagine e testo, categorie filtrate per gruppo, desktop e mobile, pagina di attesa.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
