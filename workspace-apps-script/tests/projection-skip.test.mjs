import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=await readFile(new URL('../src/FogliOperativi.gs',import.meta.url),'utf8');
test('unchanged event skips writes, changes and failed writes are retried',()=>{
  const properties=new Map(); let writes=0,fail=false;
  const vista={righe:[{valori:{first_name:'Mario'}}]};
  const rows={links:[{id_evento:'1',id_foglio:'sheet'}],orders:[{id_evento:'1',codice_ordine:'order'}],payments:[]};
  const c=vm.createContext({
    PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k),setProperty:(k,v)=>properties.set(k,v)})},
    MI_SHEETS:{EVENT_WORKSPACES:'links',REGISTRATIONS:'orders',PAYMENTS:'payments'},
    ottieniSchedaObbligatoria_:k=>rows[k],convertiRigheInOggetti_:r=>r,normalizzaTesto_:String,
    SpreadsheetApp:{openById:()=>({getSheetByName:()=>({}),getUrl:()=>''})},
    generaVistaOperativaEvento_:()=>vista,versioneGestione_:JSON.stringify,
    scriviProiezioneEvento_:()=>{writes++;return {manuali:1};},
    configuraSchedeEconomicheEvento_:()=>{},aggiornaProiezionePagamentiEventoConLock_:()=>{if(fail)throw Error('write failed');},
    aggiungiControllo_:()=>{},Session:{getActiveUser:()=>({getEmail:()=>''})}
  });
  vm.runInContext(source,c);
  const refresh=()=>c.aggiornaFoglioOperativoEventoConLock_({id_evento:'1',soloModificati:true});
  refresh(); assert.equal(writes,1);
  assert.equal(refresh().invariato,true); assert.equal(writes,1);
  rows.payments.push({codice_ordine:'order',importo_centesimi:100});
  fail=true; assert.throws(refresh,/write failed/);
  fail=false; refresh(); assert.equal(writes,3);
  vista.righe[0].valori.first_name='Paolo'; refresh();assert.equal(writes,4);
  c.aggiornaFoglioOperativoEventoConLock_({id_evento:'1'});assert.equal(writes,5);
});
