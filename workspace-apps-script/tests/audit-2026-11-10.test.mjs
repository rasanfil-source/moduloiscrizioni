import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {fixture} from './helpers/projection-sheet.mjs';
import {projectionContext,projectionFixture} from './helpers/projection-performance.mjs';

test('current question labels win while deleted questions keep historical labels and answers',()=>{
 const {context:c}=projectionContext(),p=projectionFixture(1);
 const schema=JSON.parse(p.event.schema_vista_json);schema.fields[0].label='Etichetta corrente';p.event.schema_vista_json=JSON.stringify(schema);
 p.registrations[0].snapshot_json=JSON.stringify({event:{participant_fields:[{key:'custom_test',label:'Vecchia etichetta'},{key:'custom_old',label:'Domanda rimossa'}]}});
 p.participants[0].dati_aggiuntivi_json='{"custom_test":"Risposta attuale","custom_old":"Risposta storica"}';
 const v=c.generaVistaDaProiezioneDiretta_(p);
 assert.equal(v.colonne.find(f=>f.key==='custom_test').label,'Etichetta corrente');
 assert.equal(v.colonne.find(f=>f.key==='custom_old').label,'Domanda rimossa');
 assert.equal(v.righe[0].valori.custom_test,'Risposta attuale');assert.equal(v.righe[0].valori.custom_old,'Risposta storica');
});

test('order amounts prefer an active person; personal amounts and all-cancelled history stay intact',()=>{
 const {context:c}=projectionContext(),p=projectionFixture(1),schema=JSON.parse(p.event.schema_vista_json);
 schema.registration_timestamps=true;p.event.schema_vista_json=JSON.stringify(schema);
 p.participants[0].stato_partecipante='CANCELLED';
 p.participants.push({...p.participants[0],numero_partecipante:2,stato_partecipante:'ACTIVE'});
 p.registrations[0].versato_centesimi=500;p.payments=[{codice_ordine:'ORD-0',importo_centesimi:500,fonte_pagamento:'CONTANTE',tipo_movimento:'INCASSO'}];
 let v=c.generaVistaDaProiezioneDiretta_(p);
 assert.equal(v.righe[0].valori.paid_cash,'');assert.equal(v.righe[1].valori.paid_cash,5);
 assert.deepEqual(Array.from(v.righe,r=>r.valori.total),[12,12]);
 p.participants.forEach(person=>{person.totale_centesimi='';person.versato_centesimi='';person.saldo_centesimi='';});
 v=c.generaVistaDaProiezioneDiretta_(p);assert.equal(v.righe[0].valori.total,'');assert.equal(v.righe[1].valori.total,12);
 p.participants[1].stato_partecipante='CANCELLED';
 v=c.generaVistaDaProiezioneDiretta_(p);assert.equal(v.righe[0].valori.paid_cash,5);assert.equal(v.righe[1].valori.paid_cash,'');
});

test('payment retries protect before writing, restore data visibility, and sort across orders without mutating input',()=>{
 const f=fixture(),c=f.context();let protectedSheet=false;
 vm.runInContext(fs.readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),c);
 c.proteggiProiezione_=()=>{protectedSheet=true;};
 f.sheet.hidden=true;const management=f.book.insertSheet('Gestione evento');
 management.hideSheet=()=>{assert.equal(f.sheet.hidden,false);management.hidden=true;};
 const payments=f.book.insertSheet('Pagamenti'),range=payments.getRange.bind(payments);
 payments.getRange=(...args)=>{const r=range(...args),write=r.setValues;r.setValues=function(values){assert.equal(protectedSheet,true);return write.call(this,values);};return r;};
 const p={event:{id_evento:'42',schema_vista_json:'{"pricing":"FIXED"}'},payments:[
  {id_pagamento:'later',codice_ordine:'A',data_effettiva:'2026-11-10',importo_centesimi:500},
  {id_pagamento:'earlier',codice_ordine:'B',data_effettiva:'2026-11-01',importo_centesimi:200}
 ]};
 const before=JSON.stringify(p);c.aggiornaPagamentiDaProiezione_(f.book,p,true);
 assert.equal(protectedSheet,true);assert.equal(management.hidden,true);
 assert.deepEqual(payments.cells.slice(1).map(r=>r[0]),['earlier','later']);assert.equal(JSON.stringify(p),before);
 protectedSheet=false;c.aggiornaPagamentiDaProiezione_(f.book,p,true);assert.equal(protectedSheet,true);
});

test('batch verification preserves unavailable states without declaring the file missing',()=>{
 const c=vm.createContext({normalizzaTesto_:v=>String(v||'')});vm.runInContext(fs.readFileSync(new URL('../src/FogliOperativi.gs',import.meta.url),'utf8'),c);
 c.verificaFoglioEventoDaWordPress_=p=>p.id_evento==='42'?{ok:false,error:'SHEET_UNAVAILABLE',id_evento:'42'}:{ok:true,esiste:false,id_evento:p.id_evento};
 const result=c.verificaFogliEventoDaWordPress_({direct_projection:true,fogli:[{id_evento:'42'},{id_evento:'43'}]});
 assert.equal(result.stati.length,2);assert.equal(result.stati[0].ok,false);assert.equal(Object.hasOwn(result.stati[0],'esiste'),false);
 assert.equal(result.stati[1].esiste,false);
});
