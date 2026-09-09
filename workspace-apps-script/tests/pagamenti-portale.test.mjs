import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await Promise.all(['Config.gs', 'Core.gs', 'Payments.gs', 'PagamentiPortale.gs'].map(n => readFile(new URL('../src/' + n, import.meta.url), 'utf8')))).join('\n');
function env() {
  const payments = [], orders = [{codice_ordine:'ORD-DEMO',id_evento:'42',stato:'CONFIRMED',totale_centesimi:10000}];
  const headers = ['id_pagamento','codice_ordine','tipo_movimento','tipo_rata','data_effettiva','importo_centesimi','valuta','fonte_pagamento','riferimento_esterno','etichetta_operatore','canale_registrazione','id_inserimento_origine','data_creazione','nota_amministrativa'];
  let uuid = 0;
  const context = vm.createContext({Date, console, Utilities:{getUuid:()=>`id-${++uuid}`,parseDate:s=>new Date(s+'T00:00:00Z'),formatDate:d=>d.toISOString().slice(0,10)},LockService:{getScriptLock:()=>({waitLock(){},releaseLock(){}})}});
  vm.runInContext(source,context);
  context.ottieniFoglioDiLavoroAssociato_=()=>({getSpreadsheetTimeZone:()=> 'UTC'});
  context.ottieniSchedaObbligatoria_=name=>name==='Iscrizioni'?{rows:orders}:{rows:payments,appendRow:r=>payments.push(Object.fromEntries(headers.map((h,i)=>[h,r[i]])))};
  context.convertiRigheInOggetti_=s=>s.rows;
  context.aggiungiControllo_=()=>{};
  const payload={order_code:'ORD-DEMO',event_id:'42',request_id:'wp_7_12345678-1234-4234-8234-123456789abc',operator_label:'WP#7 · Operatore demo',data:'2026-09-08',tipo:'INCASSO',rata:'CAPARRA',importo:'20,00',metodo:'CONTANTE',riferimento:'',nota:''};
  return {context,payments,orders,payload,save:overrides=>context.registraPagamentoPortale_({...payload,...overrides})};
}
test('il portale registra nel centrale e il retry non duplica dopo errore della proiezione',()=>{
  const e=env(); assert.equal(e.save().saved,true); assert.equal(e.save().saved,true);
  assert.equal(e.payments.length,1); assert.equal(e.payments[0].canale_registrazione,'WORDPRESS_PORTAL');
  assert.equal(e.payments[0].etichetta_operatore,'WP#7 · Operatore demo');
});
test('lo stesso identificativo con importo diverso viene rifiutato',()=>{
  const e=env(); e.save(); assert.equal(e.save({importo:'30'}).saved,false); assert.equal(e.payments.length,1);
});
test('evento alterato e identità operatore mancante non scrivono',()=>{
  const e=env(); assert.throws(()=>e.save({event_id:'43'})); assert.throws(()=>e.save({operator_label:''})); assert.equal(e.payments.length,0);
});
test('saldo residuo e rimborsi sono convalidati contro il registro aggiornato',()=>{
  const e=env(); assert.equal(e.save({importo:'101'}).saved,false);
  assert.equal(e.save({tipo:'RIMBORSO',importo:'1'}).saved,false);
  e.save(); assert.equal(e.save({request_id:e.payload.request_id.replace('abc','abd'),importo:'81'}).saved,false);
  assert.equal(e.payments.length,1);
});
test('date impossibili non registrano un movimento',()=>{
  const e=env(); assert.equal(e.save({data:'2026-02-30'}).saved,false); assert.equal(e.payments.length,0);
});
test('eventi gratuiti e prenotazioni annullate non accettano incassi',()=>{
  const e=env(); e.orders[0].totale_centesimi=0; assert.equal(e.save().saved,false);
  e.orders[0].totale_centesimi=10000; e.orders[0].stato='CANCELLED'; assert.equal(e.save().saved,false);
});
