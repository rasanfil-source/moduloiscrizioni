import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await Promise.all(['PagamentiEvento.gs','FinestraPagamenti.gs','AccessoGestione.gs'].map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
test('la proiezione sostituisce bozze locali, filtra evento e ordina incassi e rimborsi',()=>{
 const writes=[],state={cleared:false,locked:false,protected:false};
 const sheet={clear(){state.cleared=true;},showColumns(){},getMaxColumns:()=>9,getMaxRows:()=>100,setFrozenRows(){},getRange(row){return {clearDataValidations(){state.validationsCleared=true;},setValues(v){assert.equal(state.validationsCleared,true);writes.push({row,values:JSON.parse(JSON.stringify(v))});return this;},setFontWeight(){},setNumberFormat(){}};}};
 const ctx=vm.createContext({Date,MI_SHEETS:{REGISTRATIONS:'orders',PAYMENTS:'payments'},LockService:{getScriptLock:()=>({waitLock(){state.locked=true;},releaseLock(){state.locked=false;}})},ottieniSchedaObbligatoria_:n=>n,convertiRigheInOggetti_:n=>n==='orders'?[{id_evento:42,codice_ordine:'A'},{id_evento:43,codice_ordine:'B'}]:[{id_pagamento:'r',codice_ordine:'A',data_effettiva:'2026-09-09',tipo_movimento:'RIMBORSO',importo_centesimi:500},{id_pagamento:'i',codice_ordine:'A',data_effettiva:'2026-09-08',tipo_movimento:'INCASSO',importo_centesimi:2000},{id_pagamento:'other',codice_ordine:'B'}],neutralizzaFormula_:v=>v});
 vm.runInContext(source,ctx);ctx.proteggiProiezione_=()=>{state.protected=true;};
 ctx.aggiornaProiezionePagamentiEvento_({getSheetByName:()=>sheet},42);
 assert.equal(state.cleared,true);assert.equal(state.locked,false);assert.equal(state.protected,true);
 assert.deepEqual(writes[1].values.map(r=>[r[0],r[4]]),[['i',20],['r',-5]]);
 assert.equal(writes[0].values[0].includes('Convalida'),false);
});
test('i tre accessi condividono il portale e mantengono il contesto evento/prenotazione',()=>{
 const ctx=vm.createContext({PropertiesService:{getScriptProperties:()=>({getProperty:()=> 'https://example.invalid/wp-json/modulo-iscrizioni/v1/workspace/commands'})}});vm.runInContext(source,ctx);
 const url=new URL(ctx.urlGestioneWeb_('management','42','ORD A'));
 assert.equal(url.pathname,'/');assert.equal(url.searchParams.get('mi_portal'),'1');assert.equal(url.searchParams.get('mi_portal_event'),'42');assert.equal(url.searchParams.get('mi_order'),'ORD A');
});
