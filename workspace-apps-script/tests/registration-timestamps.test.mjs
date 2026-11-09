import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await Promise.all(['Core.gs','Segreteria.gs','DomandeEvento.gs','FogliOperativi.gs','SincronizzazioneManuale.gs','ProiezioneDiretta.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
function fixture(enabled=true){
 const schema={fields:[],options:[],pricing:'ZERO',registration_timestamps:enabled};
 const event={id_evento:'42',titolo:'Evento sintetico',profilo_operativo:'MINIMO',schema_vista_json:JSON.stringify(schema)};
 const orders=[{id_evento:'42',codice_ordine:'ORD-1',stato:'CONFIRMED'},{id_evento:'42',codice_ordine:'ORD-2',stato:'CANCELLED'},{id_evento:'42',codice_ordine:'ORD-3',stato:'EXPIRED'}];
 const person=(order,n,status,cancel)=>({codice_ordine:order,numero_partecipante:n,nome:'Persona '+n,cognome:'Prova',stato_partecipante:status,dati_aggiuntivi_json:JSON.stringify({registration_time:'2026-11-09 11:20:30',cancellation_time:cancel})});
 const people=[person('ORD-1',1,'ACTIVE',''),person('ORD-1',2,'CANCELLED','2026-11-09 12:30:40'),person('ORD-2',1,'ACTIVE','2026-11-09 13:30:40'),person('ORD-3',1,'EXPIRED','')];
 const rows={events:[event],orders,people,payments:[],views:[],states:[]};
 const c=vm.createContext({MI_SHEETS:{EVENTS:'events',REGISTRATIONS:'orders',PARTICIPANTS:'people',PAYMENTS:'payments',OPERATIONAL_VIEWS:'views',OPERATIONAL_STATE:'states'},ottieniSchedaObbligatoria_:s=>s});vm.runInContext(source,c);c.convertiRigheInOggetti_=s=>rows[s]||[];
 return {c,rows,event,projection:{event,registrations:orders,participants:people,payments:[]}};
}
test('new event sheets keep cancelled people, distinguish status, and append automatic timestamps',()=>{
 const {c,projection}=fixture();
 for(const view of [c.generaVistaDaProiezioneDiretta_(projection),c.generaVistaOperativaEvento_('42')]){
  assert.equal(view.righe.length,3);assert.deepEqual(Array.from(view.colonne.slice(-2),c=>c.key),['registration_time','cancellation_time']);
  assert.equal(view.righe[0].valori.registration_time,'2026-11-09 11:20:30');assert.equal(view.righe[0].valori.cancellation_time,'');
  assert.equal(view.righe[1].valori.status,'Annullata');assert.equal(view.righe[1].valori.cancellation_time,'2026-11-09 12:30:40');assert.equal(view.righe[2].valori.status,'Annullata');
 }
 assert.deepEqual(Array.from(c.generaVistaOperativaIniziale_('42','Evento','MINIMO').colonne.slice(-2),c=>c.key),['registration_time','cancellation_time']);
});
test('existing events keep active-only rows and current columns',()=>{
 const {c,projection}=fixture(false);
 for(const view of [c.generaVistaDaProiezioneDiretta_(projection),c.generaVistaOperativaEvento_('42')]){
  assert.equal(view.righe.length,1);assert.ok(!view.colonne.some(c=>['registration_time','cancellation_time'].includes(c.key)));
 }
});
test('dates precede optional code even in saved/custom layouts, without duplicate columns',()=>{
 const {c,rows}=fixture();rows.views=[{id_evento:'42',campi_json:'["order_code","last_name","registration_time"]'}];
 const keys=Array.from(c.generaVistaOperativaEvento_('42').colonne,c=>c.key);
 assert.deepEqual(keys.slice(-3),['registration_time','cancellation_time','order_code']);assert.equal(keys.filter(k=>k==='registration_time').length,1);
});
test('registration/cancellation times cannot be imported as manual sheet edits',()=>{
 const {c}=fixture();
 for(const key of ['registration_time','cancellation_time']){
  assert.equal(c.campoModificabileFoglio_(key),false);assert.equal(c.campoLocaleFoglio_(key),false);
 }
 assert.equal(c.campoModificabileFoglio_('first_name'),true);
});
