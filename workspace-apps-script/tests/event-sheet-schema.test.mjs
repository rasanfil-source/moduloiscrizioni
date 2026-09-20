import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await Promise.all(['Core.gs','Segreteria.gs','DomandeEvento.gs','FogliOperativi.gs','SincronizzazioneManuale.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
function fixture(schema) {
 const event={id_evento:'42',titolo:'12 ceste',profilo_operativo:'VIAGGIO_COMPLESSO',schema_vista_json:JSON.stringify(schema)};
 const rows={events:[event],orders:[],people:[],payments:[],views:[{id_evento:'42',campi_json:'["document_number","insurance","total"]'}],states:[]};
 const c=vm.createContext({MI_SHEETS:{EVENTS:'events',REGISTRATIONS:'orders',PARTICIPANTS:'people',PAYMENTS:'payments',OPERATIONAL_VIEWS:'views',OPERATIONAL_STATE:'states'},ottieniSchedaObbligatoria_:s=>s});vm.runInContext(source,c);c.convertiRigheInOggetti_=s=>rows[s]||[];
 return {c,rows,event};
}
const free={fields:[{key:'birth_date',label:'Data di nascita'},{key:'custom_food',label:'Cosa porti?'}],options:[],room:false,pricing:'ZERO'};
test('free event with birth date does not inherit travel columns, even empty or with a saved view',()=>{
 const {c}=fixture(free);
 for(const view of [c.generaVistaOperativaIniziale_('42','12 ceste','VIAGGIO_COMPLESSO'),c.generaVistaOperativaEvento_('42')]) {
  assert.deepEqual(Array.from(view.colonne,x=>x.key),['last_name','first_name','phone','birth_date','custom_food']);
  assert.equal(view.sola_lettura,true);
 }
});
test('only configured documents, services and rooms appear; questions stay present when unanswered',()=>{
 const {c}=fixture({...free,fields:[{key:'document_expiry',label:'Scadenza documento'}],options:[{code:'bus',name:'Pullman',scope:'TICKET'}],room:true,pricing:'FIXED'});
 const keys=Array.from(c.generaVistaOperativaEvento_('42').colonne,x=>x.key);
 for(const key of ['document_expiry','room','option_bus','total','paid','balance']) assert.ok(keys.includes(key));
 for(const key of ['nationality','lunch','insurance','transport','document_number']) assert.ok(!keys.includes(key));
});
test('free event preserves historical economics and attendance without importing unrelated events',()=>{
 const {c,rows}=fixture(free);
 rows.orders.push({id_evento:'42',codice_ordine:'A',stato:'CONFIRMED'});
 rows.people.push({codice_ordine:'A',numero_partecipante:1,dati_aggiuntivi_json:'{"attendance":"PRESENT"}'});
 rows.payments.push({codice_ordine:'OTHER',importo_centesimi:100});
 let view=c.generaVistaOperativaEvento_('42');assert.ok(!view.colonne.some(x=>x.key==='paid'));assert.equal(view.righe[0].valori.attendance,'Presente');
 rows.payments.push({codice_ordine:'A',importo_centesimi:100});
 view=c.generaVistaOperativaEvento_('42');assert.ok(view.colonne.some(x=>x.key==='paid'));assert.equal(view.sola_lettura,false);
});
test('schema update targets the event and updates price mode before rendering',()=>{
 const {c}=fixture(free);let width=12;const writes=[];
 const sheet={getMaxColumns:()=>width,insertColumnsAfter:(n,count)=>width+=count,getRange:(r,col)=>({setValue:v=>writes.push([r,col,v])})};
 c.ottieniSchedaObbligatoria_=()=>sheet;c.convertiRigheInOggetti_=()=>[{id_evento:'7',_row:2},{id_evento:'42',_row:3}];
 c.aggiornaSchemaEventoMysql_('42',free);assert.equal(width,13);assert.deepEqual(writes,[[1,13,'schema_vista_json'],[3,13,JSON.stringify(free)],[3,8,'ZERO']]);
 assert.throws(()=>c.aggiornaSchemaEventoMysql_('42',{}),/INVALID_EVENT_SCHEMA/);
});
