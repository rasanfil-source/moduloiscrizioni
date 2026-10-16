import test from 'node:test';import assert from 'node:assert/strict';import vm from 'node:vm';import fs from 'node:fs';
const source=fs.readFileSync(new URL('../src/AccessoGestione.gs',import.meta.url),'utf8');
function setup(){let dialogs=0,uiCalls=0,unavailable=false;
 const props={MI_WORDPRESS_COMMAND_URL:'https://example.invalid/wp-json/modulo-iscrizioni/v1/workspace/commands',MI_DIRECT_SHEET_42:'sheet42'};
 const c=vm.createContext({PropertiesService:{getScriptProperties:()=>({getProperty:k=>props[k],getProperties:()=>props})},MI_SHEETS:{REGISTRATIONS:'orders'},ottieniSchedaObbligatoria_:s=>s,convertiRigheInOggetti_:()=>[{codice_ordine:'ORDER',id_evento:42}],SpreadsheetApp:{getActiveSpreadsheet:()=>({getId:()=> 'sheet42'}),getUi:()=>{uiCalls++;if(unavailable)throw Error('No UI');return {showModalDialog:()=>dialogs++};}},HtmlService:{createHtmlOutput:()=>({setWidth(){return this;},setHeight(){return this;}})}});vm.runInContext(source,c);
 return {c,get dialogs(){return dialogs;},get uiCalls(){return uiCalls;},noUi(){unavailable=true;}};
}
test('sheet navigation retains selected event and order and resolves order-only entry',()=>{
 const f=setup();let url=new URL(f.c.apriGestioneWeb('management','42','ORDER').url);
 assert.equal(url.searchParams.get('mi_portal_event'),'42');assert.equal(url.searchParams.get('mi_order'),'ORDER');assert.equal(f.dialogs,1);
 url=new URL(f.c.apriGestioneWeb('management','','ORDER').url);assert.equal(url.searchParams.get('mi_portal_event'),'42');
 assert.throws(()=>f.c.apriGestioneWeb('management','','UNKNOWN'),/non trovata/);
 url=new URL(f.c.apriGestioneWeb().url);assert.equal(url.searchParams.get('mi_portal_event'),'42');
});
test('client navigation never opens spreadsheet UI and missing UI returns a usable link',()=>{
 const f=setup();f.c.apriGestioneWeb({vista:'payments',evento:'42',ordine:'ORDER',client:true});assert.equal(f.uiCalls,0);
 f.noUi();assert.ok(f.c.apriGestioneWeb('management','42','ORDER').url);assert.equal(f.dialogs,0);
});
test('HTML client preserves context and supplies a clickable fallback without popup timing',async()=>{
 const html=fs.readFileSync(new URL('../src/Segreteria.html',import.meta.url),'utf8');
 for(const match of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(match[1].replace(/<\?!=[\s\S]*?\?>/g,'false'));
 const helper=html.match(/async function apriPortale[^\n]+/)[0];
 for(const web of [true,false]){
  let sent,link;
  const c=vm.createContext({booking:{event_id:'42',order_code:'ORDER'},IS_WEB_APP:web,value:()=>'',call:async(name,args)=>{sent=args;return {url:'https://example.invalid/manage'};},document:{createElement:()=>({}),getElementById:()=>({appendChild:a=>link=a})},message(){},showError:e=>{throw e;}});
  vm.runInContext(helper,c);await c.apriPortale('payments');assert.equal(sent.evento,'42');assert.equal(sent.ordine,'ORDER');assert.equal(sent.client,web);assert.equal(link.rel,'noopener noreferrer');
  await c.apriPortale('management','OTHER');assert.equal(sent.evento,'');assert.equal(sent.ordine,'OTHER');
 }
});
