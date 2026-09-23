// Read-only UI verification: current assets, synthetic data, intercepted requests.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const assets=path.resolve('wordpress-plugin/modulo-iscrizioni/assets');
const out=path.resolve('.tmp/portal-visual-regressions');fs.mkdirSync(out,{recursive:true});
const widths=[1280,1024,800,768,760,600,480,390,360,320];
const css=['portal.css','portal-management.css','portal-payments.css'];
const html=(body,scripts=[])=>'<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+css.map(f=>'<link rel="stylesheet" href="/'+f+'">').join('')+'</head><body class="mi-portal-standalone">'+body+scripts.map(f=>'<script src="/'+f+'"></script>').join('')+'</body></html>';
const management=(compact=false)=>`<section class="mi-management${compact?' mi-management--compact':''}" data-mi-management data-event="42" data-order="${compact?'ORD-DEMO':''}" data-endpoint="/ajax" data-nonce="synthetic"><div class="mi-management-event-selectors" ${compact?'hidden':''}><label><select data-period-select aria-label="Eventi attivi o passati"><option value="current">Eventi attivi</option></select></label><label>Evento<select data-event-select><option value="42" data-period="current" selected>Evento dimostrativo</option></select></label></div><div data-event-actions hidden><div class="mi-booking-detail__actions mi-event-toolbar"><button type="button" data-refresh>Aggiorna riepilogo</button><button type="button" data-print>Stampa riepilogo iscritti</button></div><p role="status" data-management-status></p></div><div data-management-content></div></section>`;
const people=[{id:1,number:1,code:'ORD-DEMO',name:'Dell’Aquila Fioravanti Maria Assunta',buyer:'Maria Assunta',email:'maria@example.invalid',phone:'0123456789',status:'CONFIRMED',room:'DM3',fields:{},missing:[],collectible:true,requests:'',options:[{code:'alloggio-doppia',name:'Doppia matrimoniale',category:'alloggio',quantity:1}]}];
const rooms=[{code:'DM3',name:'Doppia matrimoniale',capacity:2,occupied:1,available:1}];
const summary={features:{rooms:true,room_inventory:true,payments:true,deposit:true},items:[{code:'ORD-DEMO',name:people[0].name,status:'CONFIRMED',participants:1,balance:35000,total:48000,paid:13000,collectible:true,active:true,missing:0,unassigned:0}],people,rooms,rooms_version:'synthetic',updated_at:'2026-09-13T12:00:00Z',room_types:{'alloggio-doppia':{name:'Doppia matrimoniale',prefix:'DM',capacity:2}}};
const booking={registration_id:1,buyer:{first_name:'Maria Assunta',last_name:'Dell’Aquila Fioravanti',email:'maria@example.invalid',phone:'0123456789'},event_title:'Evento dimostrativo con pernottamento',order_code:'ORD-DEMO',status:'CONFIRMED',total_cents:48000,paid_cents:13000,balance_cents:35000,version:'synthetic',features:summary.features,fields:[{key:'participant_email',label:'Email personale',type:'email'}],participants:[{...people[0],first_name:'Maria Assunta',last_name:'Dell’Aquila Fioravanti',status:'ACTIVE',fields:{participant_email:'maria@example.invalid'}}],accommodations:rooms,movements:[],option_definitions:[]};
const movements=reference=>[{data:'2026-09-12',tipo:'INCASSO',importo:35000,metodo:'BONIFICO',riferimento:reference,operatore:'WP#14 · Bianchi Elena',nota:''},{data:'2026-09-03',tipo:'INCASSO',importo:13000,metodo:'CONTANTE',riferimento:'',operatore:'WP#14 · Bianchi Elena',nota:'Consegnato a mano dopo la messa, verificato con il referente del gruppo giovani'}];
let paymentTemplate=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-payments.php','utf8');
paymentTemplate=paymentTemplate.slice(paymentTemplate.indexOf('<section class="mi-payments"'),paymentTemplate.lastIndexOf('</section>')+10).replace(/<\?php[\s\S]*?\?>/g,'').replace('data-endpoint=""','data-endpoint="/ajax"');
const share='<button type="button" class="mi-event-link-share" aria-label="Condividi"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.5"></circle><circle cx="6" cy="12" r="2.5"></circle><circle cx="18" cy="19" r="2.5"></circle><path d="M8.2 10.8l7.6-4.5M8.2 13.2l7.6 4.5"></path></svg></button>';
const linkRow=(balance=false)=>`<div class="mi-event-management__registration"><strong>Link per ${balance?'il versamento del saldo':'le iscrizioni'}</strong><div class="mi-output-copy mi-event-registration-link"><input type="url" readonly aria-label="Link ${balance?'saldo':'iscrizioni'}" value="https://eventi.example.invalid/?${balance?'mi_saldo':'mi_iscrizione'}=482"><button type="button" class="mi-secondary">Copia</button>${balance?'':share}<a class="mi-secondary mi-output-link" href="#" target="_blank" rel="noopener">Apri <span aria-hidden="true">↗</span></a></div></div>`;
const result={browser:'',links:[],payments:[],modal:[],controls:[],errors:[]};
const equalPx=(actual,expected)=>assert.ok(Math.abs(actual-expected)<0.1,actual+'px differs from '+expected+'px');
const bounds=e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom,clientWidth:e.clientWidth,scrollWidth:e.scrollWidth};};
(async()=>{
 const browser=await chromium.launch({channel:process.env.MI_BROWSER_CHANNEL||'msedge',headless:true});result.browser=browser.version();
 const page=await browser.newPage({viewport:{width:1280,height:900},locale:'it-IT'});page.setDefaultTimeout(10000);
 page.on('pageerror',e=>result.errors.push(e.message));
 let mode='management',reference='',emptyHistory=false;
 const shot=async name=>page.screenshot({path:path.join(out,name+'.png')});
 const fits=async label=>assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),label+' overflow');
 try{
  await page.route('**/*',async route=>{
   const url=new URL(route.request().url()),file=path.basename(url.pathname);
   if(url.hostname!=='audit.invalid')return route.abort();
   if(url.pathname==='/ajax'){
    const p=Object.fromEntries(new URLSearchParams(route.request().postData()));
    assert.ok(['summary','detail','search'].includes(p.operation),'Unexpected mutation '+p.operation);
    let data;
    if(p.action==='mi_portal_payment')data=p.operation==='search'?{prenotazioni:[{id:1,nome:people[0].name,codice:'ORD-DEMO',evento:booking.event_title}],has_more:false}:{data:'2026-09-13',saldo:{evento:booking.event_title,totale:48000,versato:48000,residuo:0,movimenti:emptyHistory?[]:movements(reference)}};
    else data=p.operation==='summary'?summary:{...booking,order_code:p.order_code};
    return route.fulfill({json:{success:true,data}});
   }
   if(css.includes(file)||['portal.js','portal-management.js','portal-payments.js'].includes(file))return route.fulfill({body:fs.readFileSync(path.join(assets,file)),contentType:file.endsWith('.css')?'text/css':'text/javascript'});
   let body,scripts=[];
   if(mode==='management'){body='<main class="mi-portal">'+management()+'</main>';scripts=['portal-management.js'];}
   if(mode==='modal'){
    const id=url.searchParams.get('mi_portal_booking');
    body='<main class="mi-portal"><h1>Ricerca iscrizioni</h1>'+[1,2,3].map(n=>'<a data-mi-portal-booking-open href="/booking?mi_portal_booking='+n+'">Scheda '+n+'</a>').join(' ')+(id?'<div id="mi-portal-booking-detail">'+management(true).replace('data-order="ORD-DEMO"','data-order="ORD-'+id+'"')+'</div>':'')+'</main>';
    scripts=['portal.js','portal-management.js'];
   }
   if(mode==='payments'){body='<main class="mi-portal">'+paymentTemplate+'</main>';scripts=['portal-payments.js'];}
   if(mode==='links')body='<main class="mi-portal"><h1>Gestisci eventi</h1><div class="mi-event-grid"><div class="mi-event-inline-panel"><section class="mi-event-management"><details open><summary>Dettagli principali</summary>'+linkRow()+linkRow(true)+'</details></section></div></div></main>';
   return route.fulfill({body:html(body,scripts),contentType:'text/html'});
  });

  await page.goto('https://audit.invalid/management');await page.locator('[data-query]').waitFor();
  await page.locator('[data-room-section]>summary').click();await page.locator('[data-room-inventory]>summary').click();
  for(const width of [1280,390,360,320]){
   await page.setViewportSize({width,height:900});await page.locator('[data-query]').fill('Dell');await page.locator('[data-clear-query]').waitFor();
   const room=await page.locator('[data-room-person]').first().evaluate(bounds),clear=await page.locator('[data-clear-query]').evaluate(bounds),inventory=await page.locator('[data-room-inventory] input[type=number]').evaluate(bounds);
   equalPx(room.height,44);equalPx(inventory.height,44);equalPx(clear.width,44);equalPx(clear.height,44);
   await fits('Management '+width);result.controls.push({width,room,clear});
   if(width===360||width===1280){await page.locator('[data-room-person]').first().scrollIntoViewIfNeeded();await shot('camere-'+width);}
  }

  mode='links';await page.goto('https://audit.invalid/links');
  for(const width of [...widths,481]){
   await page.setViewportSize({width,height:844});await fits('Links '+width);
   const sharing=await page.locator('.mi-event-link-share').evaluate(bounds);equalPx(sharing.width,44);equalPx(sharing.height,44);
   for(const row of await page.locator('.mi-event-registration-link').all()){
    const input=await row.locator('input').evaluate(bounds),button=await row.locator('button').first().evaluate(bounds),link=await row.locator('a').evaluate(bounds);
    assert.ok(link.right<=width,'Apri remains visible');
    if(width<=480){assert.ok(button.y>=input.bottom,'Actions on their own row');assert.ok(input.width>=width-100,'Address uses the available row');}
    else assert.ok(Math.abs(button.y-input.y)<1,'Desktop row is unchanged');
   }
   result.links.push({width,input:await page.locator('.mi-event-registration-link input').first().evaluate(bounds)});
   if([360,390,1280].includes(width))await shot('link-'+width);
  }

  mode='payments';
  for(const ref of ['', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789','CRO987654321IT02BONIFICOPARROCCHIASANTAMARIAORDINANTEDELLAQUILA','X'.repeat(120)]){
   reference=ref;await page.goto('https://audit.invalid/payments');await page.locator('input[type=search]').fill('Dell');await page.locator('.mi-payment-choice').click();await page.locator('[data-payment-history] table').waitFor();
   assert.equal(await page.locator('[data-payment-history] tbody tr').count(),2);
   assert.equal(await page.locator('[data-payment-history] tbody tr').first().locator('td').nth(4).textContent(),ref);
   for(const width of widths){
    await page.setViewportSize({width,height:900});await fits('Payments '+width+', reference '+ref.length);
    const scroll=await page.locator('.mi-payment-history-scroll').evaluate(bounds);
    assert.ok(scroll.right<=width,'History region is contained');
    assert.ok((await page.locator('.mi-payment-history-table th').nth(5).evaluate(bounds)).width>=128,'Operator column stays readable instead of wrapping letter by letter');
    if(width<=600)assert.ok(scroll.scrollWidth>scroll.clientWidth,'Wide history scrolls locally');
    result.payments.push({width,referenceLength:ref.length,scroll});
    if(ref.length===63&&[800,1280,360].includes(width)){await page.locator('[data-payment-history]').scrollIntoViewIfNeeded();await shot('pagamenti-'+width);}
   }
  }
  const ledger=page.getByRole('region',{name:'Movimenti registrati',exact:true});
  await ledger.focus();await page.keyboard.press('ArrowRight');await page.waitForFunction(()=>document.querySelector('.mi-payment-history-scroll').scrollLeft>0);
  await page.emulateMedia({media:'print'});assert.equal(await page.locator('.mi-payment-history-table').evaluate(e=>getComputedStyle(e).minWidth),'0px');await page.emulateMedia({media:'screen'});
  emptyHistory=true;await page.goto('https://audit.invalid/payments');await page.locator('input[type=search]').fill('Dell');await page.locator('.mi-payment-choice').click();await page.getByText('Nessun movimento registrato.',{exact:true}).waitFor();assert.equal(await page.locator('.mi-payment-history-scroll').count(),0);

  mode='modal';await page.goto('https://audit.invalid/search');await page.getByRole('link',{name:'Scheda 2',exact:true}).click();await page.locator('[data-person]').waitFor();
  const previous=page.getByRole('button',{name:'Scheda precedente',exact:true}),next=page.getByRole('button',{name:'Scheda successiva',exact:true}),close=page.getByRole('button',{name:'Chiudi la scheda',exact:true}),content=page.locator('.mi-portal-modal__content');
  assert.ok(await close.evaluate(e=>e===document.activeElement));
  for(const width of [1280,600,481,480,390,360,320]){
   await page.setViewportSize({width,height:844});await content.evaluate(e=>e.scrollTop=0);await fits('Modal '+width);
   const inner=await page.locator('.mi-management--compact').evaluate(bounds);result.modal.push({width,content:inner});
   if(width<=480){
    assert.ok(inner.width>=width-33,'Content gets the full width minus 16px margins');
    const panel=await content.evaluate(bounds);
    for(const control of [previous,next,close]){const box=await control.evaluate(bounds);equalPx(box.width,44);equalPx(box.height,44);assert.ok(box.bottom<=panel.y,'Controls stay above content');}
    const y=(await previous.evaluate(bounds)).y;await content.evaluate(e=>e.scrollTop=e.scrollHeight);assert.equal((await previous.evaluate(bounds)).y,y);await content.evaluate(e=>e.scrollTop=0);
   }
   if([360,390,1280].includes(width))await shot('scheda-'+width);
  }
  await page.setViewportSize({width:360,height:480});assert.ok((await content.evaluate(bounds)).height>300);await fits('Short mobile modal');
  await previous.focus();await page.keyboard.press('Shift+Tab');assert.ok(await page.locator('.mi-detail-back [data-back]').last().evaluate(e=>e===document.activeElement));await page.keyboard.press('Tab');assert.ok(await previous.evaluate(e=>e===document.activeElement));
  await next.click();await page.waitForFunction(()=>document.querySelector('[data-booking-overview] [data-open]')?.dataset.open==='ORD-3');assert.ok(await next.isDisabled());
  await previous.click();await page.waitForFunction(()=>document.querySelector('[data-booking-overview] [data-open]')?.dataset.open==='ORD-2');
  await close.focus();await page.keyboard.press('ArrowLeft');await page.waitForFunction(()=>document.querySelector('[data-booking-overview] [data-open]')?.dataset.open==='ORD-1');assert.ok(await previous.isDisabled());
  await page.keyboard.press('Escape');assert.ok(await page.locator('.mi-portal-modal').isHidden());assert.ok(await page.getByRole('link',{name:'Scheda 1',exact:true}).evaluate(e=>e===document.activeElement));
  await page.getByRole('link',{name:'Scheda 2',exact:true}).click();await page.locator('[data-person]').waitFor();
  await page.locator('[data-back]').first().click();await page.locator('[data-room-section]>summary').click();await page.locator('[data-room-person]').first().waitFor();
  assert.ok((await page.locator('[data-room-section]').evaluate(bounds)).width>260,'Room overview also gains content width');await fits('Room overview inside modal');
  await close.click();await page.getByRole('link',{name:'Scheda 3',exact:true}).click();await page.locator('[data-person]').waitFor();await page.locator('[data-person] input').first().fill('Bozza sintetica');
  for(const action of [previous,close]){await action.click();await page.getByRole('dialog').getByRole('button',{name:'Continua a modificare',exact:true}).click();}
  await page.keyboard.press('Escape');assert.ok(await page.locator('.mi-portal-modal').isVisible());assert.equal(await page.locator('[data-person] input').first().inputValue(),'Bozza sintetica');assert.equal(await page.locator('[data-booking-overview] [data-open]').first().getAttribute('data-open'),'ORD-3');
  assert.deepEqual(result.errors,[]);fs.writeFileSync(path.join(out,'misure.json'),JSON.stringify(result,null,2));
  console.log('Browser: link visibili 320–1280px; storico contenuto con riferimenti fino a 120 caratteri, tastiera e stampa; controlli 44px; modale, frecce, focus, Escape e protezione bozze verificati. Screenshot: '+out);
 }catch(error){await shot('failure');throw error;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
