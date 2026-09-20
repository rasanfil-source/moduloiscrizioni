import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await Promise.all(['Core.gs','Segreteria.gs','SincronizzazioneManuale.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
function context(){const c=vm.createContext({});vm.runInContext(source,c);return c;}
test('profilo corrente prevale su snapshot vecchi e parole nelle opzioni, anche senza iscritti',()=>{
 const c=context();
 const orders=[{snapshot_json:JSON.stringify({event:{operational_profile:'VIAGGIO_COMPLESSO'}})}];
 const people=[{opzioni_json:'[{"name":"Cena e pullman"}]',dati_aggiuntivi_json:'{}'}];
 assert.equal(c.determinaProfiloVistaOperativa_(orders,people,'QUOTA_UNICA').id,'QUOTA_UNICA');
 assert.equal(c.determinaProfiloVistaOperativa_([],[],'VIAGGIO_COMPLESSO').id,'VIAGGIO_COMPLESSO');
 assert.equal(c.determinaProfiloVistaOperativa_(orders,people).id,'VIAGGIO_COMPLESSO');
 assert.equal(c.determinaProfiloVistaOperativa_([],people).id,'SERVIZI_MULTIPLI');
});
test('apertura aggiorna il profilo prima della proiezione e non scrive un evento diverso',()=>{
 const c=context();let width=11,profile='',projected=false,locked=false;
 const sheet={getMaxColumns:()=>width,insertColumnsAfter:(last,n)=>{assert.equal(last,11);width+=n;},getRange:(row,col)=>({setValue(value){assert.equal(col,12);if(row===3)profile=value;else assert.equal(row,1);}})};
 Object.assign(c,{MI_SHEETS:{EVENTS:'events',REGISTRATIONS:'orders',REPLICA_REVISIONS:'revisions'},LockService:{getScriptLock:()=>({waitLock(){locked=true;},releaseLock(){locked=false;}})},SpreadsheetApp:{flush(){}},ottieniSchedaObbligatoria_:key=>key==='events'?sheet:key,sincronizzaCamereMysql_(){},aggiornaFoglioOperativoEventoConLock_(){assert.equal(profile,'QUOTA_UNICA');assert.equal(locked,true);projected=true;return {ok:true,esito:{},url_foglio:'synthetic'};}});
 c.convertiRigheInOggetti_=s=>s===sheet?[{id_evento:'99',_row:2},{id_evento:'42',_row:3}]:s==='revisions'?[{id_evento:'42',revisione_camere:'0'}]:[];
 assert.equal(c.preparaAperturaFoglio_({event_id:'42',registrations:[],rooms:[],workspace_event_revision:'0',operational_profile:'QUOTA_UNICA'}).ready,true);
 assert.equal(projected,true);assert.equal(width,12);assert.equal(locked,false);
 assert.throws(()=>c.aggiornaProfiloEventoMysql_('42','AUTOMATICO'),/INVALID_OPERATIONAL_PROFILE/);
 assert.throws(()=>c.aggiornaProfiloEventoMysql_('43','MINIMO'),/EVENT_NOT_FOUND/);
});
