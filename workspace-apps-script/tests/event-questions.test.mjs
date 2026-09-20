import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source = (await Promise.all(['DomandeEvento.gs','InterfacciaMovimentiEvento.gs','PagamentiEvento.gs','Segreteria.gs','FogliOperativi.gs','Core.gs','SincronizzazioneManuale.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
function context(rows={}) { const c=vm.createContext({MI_SHEETS:{EVENTS:'events',REGISTRATIONS:'orders',PAYMENTS:'payments'},ottieniSchedaObbligatoria_:s=>s});vm.runInContext(source,c);c.convertiRigheInOggetti_=s=>rows[s]||[];return c; }
test('required and optional questions are included, labels preserved, no cross-event columns',()=>{
 const c=context(),columns=[{key:'first_name',label:'Nome'}];
 c.aggiungiColonneDomande_(columns,{domande_json:JSON.stringify([{key:'custom_food',label:'Cosa porti?',required:false},{key:'custom_time',label:'Orario',required:true}])},[],[{dati_aggiuntivi_json:'{"custom_food":"Pane"}'}]);
 assert.deepEqual(columns.map(c=>c.key),['first_name','custom_food','custom_time']);
 assert.equal(columns[1].label,'Cosa porti?');
 c.aggiungiColonneDomande_(columns,{domande_json:'[]'},[],[]);assert.equal(columns.length,3);
});
test('legacy snapshots preserve original question labels',()=>{
 const c=context(),columns=[];
 c.aggiungiColonneDomande_(columns,{},[{snapshot_json:JSON.stringify({event:{participant_fields:[{key:'custom_test',label:'Domanda originale'}]}})}],[]);
 assert.equal(columns[0].label,'Domanda originale');
});

test('initial and saved event sheets include every question and preserve blank optional answers',()=>{
 const fields=[{key:'custom_required',label:'Domanda obbligatoria',required:true},{key:'custom_optional',label:'Domanda facoltativa',required:false},{key:'custom_empty',label:'Sempre vuota',required:false}];
 const rows={events:[{id_evento:'1',titolo:'Evento',domande_json:JSON.stringify(fields)}],orders:[{id_evento:'1',codice_ordine:'A',stato:'CONFIRMED'},{id_evento:'2',codice_ordine:'B',stato:'CONFIRMED'}],people:[{codice_ordine:'A',numero_partecipante:1,dati_aggiuntivi_json:'{"custom_required":"Sì","custom_optional":"Pane"}'},{codice_ordine:'A',numero_partecipante:2,dati_aggiuntivi_json:'{"custom_required":"No","custom_optional":""}'},{codice_ordine:'B',numero_partecipante:1,dati_aggiuntivi_json:'{"custom_other_event":"Altro"}'}],views:[{id_evento:'1',campi_json:'["first_name"]'}]};
 const c=context(rows); Object.assign(c.MI_SHEETS,{PARTICIPANTS:'people',OPERATIONAL_VIEWS:'views',OPERATIONAL_STATE:'states'});
 c.aggiungiColonneServizi_=()=>{};
 const initial=c.generaVistaOperativaIniziale_('1','Evento','MINIMO');
 assert.deepEqual(Array.from(initial.colonne.slice(-3),f=>f.label),fields.map(f=>f.label));
 const view=c.generaVistaOperativaEvento_('1');
 assert.equal(view.righe.length,2);
 assert.deepEqual(Array.from(view.colonne,c=>c.key),['first_name',...fields.map(f=>f.key)]);
 assert.equal(view.righe[0].valori.custom_required,'Sì');
 assert.equal(view.righe[0].valori.custom_optional,'Pane');
 assert.equal(view.righe[1].valori.custom_optional,'');
 assert.equal(view.righe[0].valori.custom_empty,'');
 assert.equal(view.righe[1].valori.custom_empty,'');
});
test('free events do not create payments tab; historical payments remain visible',()=>{
 const rows={events:[{id_evento:'1',modalita_prezzo:'ZERO'}],orders:[{id_evento:'1',codice_ordine:'A'}],payments:[]};const c=context(rows);
 let hidden=false;const sheet={hideSheet(){hidden=true;}};
 const book={getSheetByName:()=>null,insertSheet:()=>{throw Error('Unexpected creation');}};
 assert.equal(c.preparaPagamentiEvento_(book,'1'),null);
 book.getSheetByName=()=>sheet;book.getSheets=()=>[{getName:()=> 'Dati operativi',isSheetHidden:()=>false}];
 c.preparaPagamentiEvento_(book,'1');assert.equal(hidden,true);
 rows.payments.push({codice_ordine:'A'});assert.equal(c.eventoPrevedeMovimenti_('1'),true);
 rows.payments=[];rows.events[0].modalita_prezzo='FIXED';assert.equal(c.eventoPrevedeMovimenti_('1'),true);
});
