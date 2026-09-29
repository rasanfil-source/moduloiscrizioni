const fs=require('node:fs'),assert=require('node:assert/strict');
const {chromium}=require(process.env.MI_PLAYWRIGHT_MODULE||'playwright');
const script=fs.readFileSync('wordpress-plugin/modulo-iscrizioni/assets/portal-management.js','utf8');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const php=process.env.MI_PHP_BINARY||(process.platform==='win32'?path.resolve('.tmp/php-runtime/php.exe'):'php');
const phpArgs=process.platform==='win32'?['-d','extension_dir='+path.join(path.dirname(php),'ext'),'-d','extension=mbstring']:[];
const fixture=JSON.parse(execFileSync(php,[...phpArgs,'wordpress-plugin/tests/management-deposit-totals.php','--json'],{encoding:'utf8'}));
const overview=fixture.overview,listPage=fixture.page;
const option={code:'meal',name:'Pranzo',scope:'TICKET',price_cents:1000,max_quantity:1};
function booking(status,options){return {
  registration_id:1,event_id:42,event_title:'Evento sintetico',order_code:'TEST',status,version:'synthetic-v1',
  total_cents:10000,paid_cents:3000,balance_cents:7000,is_free_event:false,can_change_options:true,can_adjust_due:true,
  buyer:{email:'',phone:''},fields:[],features:{rooms:false},option_definitions:options,order_options:[],
  participants:[{id:1,number:1,first_name:'Ada',last_name:'Esempio',status:status==='CANCELLED'?'CANCELLED':'ACTIVE',room:'',fields:{},options:[]}],
  individual:{ready:true,quotes_known:true,payments_known:true,people:[{id:1,total:10000,paid:3000,balance:7000,credit:0}]}
};}
function markup(detail){return '<!doctype html><meta charset="utf-8"><main class="mi-portal"><section data-mi-management class="mi-management" data-endpoint="/ajax" data-nonce="synthetic" data-event="42" data-order="'+(detail?'TEST':'')+'"><select data-event-select><option value="42">Evento sintetico</option></select><button data-refresh>Aggiorna</button><button data-print>Stampa</button><p data-management-status></p><div data-management-content></div></section></main>';}
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});
  try{
    for(const test of [
      {name:'adjustment_missing_without_services',booking:booking('CONFIRMED',[]),expected:1},
      {name:'adjustment_missing_after_cancellation',booking:booking('CANCELLED',[option]),expected:1},
      {name:'adjustment_expired',booking:booking('EXPIRED',[option]),expected:1},
      {name:'adjustment_denied',booking:{...booking('CONFIRMED',[option]),can_adjust_due:false},expected:0},
      {name:'control_active_booking_with_services',booking:booking('CONFIRMED',[option]),expected:1},
      {name:'partial_deposit_counter',booking:null}
    ]){
      const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.route('https://audit.example.invalid/**',async route=>{
        if(!route.request().url().endsWith('/ajax'))return route.fulfill({contentType:'text/html',body:markup(!!test.booking)});
        const params=Object.fromEntries(new URLSearchParams(route.request().postData()));
        const data=params.operation==='detail'?test.booking:params.operation==='summary'?overview:params.operation==='list_page'?listPage:null;
        if(!data)throw Error('Unexpected operation '+params.operation);
        await route.fulfill({json:{success:true,data}});
      });
      await page.goto('https://audit.example.invalid/');
      await page.addScriptTag({content:script});
      await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
      if(test.booking){
        await page.locator('form[data-person]').waitFor();
        const count=await page.locator('[data-adjust-due]').count();
        assert.equal(count,test.expected);
        console.log(JSON.stringify({case:test.name,can_adjust_due:test.booking.can_adjust_due,status:test.booking.status,option_definitions:test.booking.option_definitions.length,adjustment_forms:count}));
      }else{
        await page.locator('.mi-management-summary-cards').waitFor();
        const text=await page.locator('.mi-management-summary-cards').innerText();
        assert.match(text,/Caparra versata: 1/);
        assert.equal(overview.payment_counts.deposit_covered,1);
        console.log(JSON.stringify({case:test.name,actual_deposits_covered:overview.payment_counts.deposit_covered,displayed_counter:1,card_text:text}));
      }
      assert.deepEqual(errors,[]);
      await page.close();
    }
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
