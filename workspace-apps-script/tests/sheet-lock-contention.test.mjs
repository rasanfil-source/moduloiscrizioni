import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await Promise.all(['SincronizzazioneManuale.gs','FogliOperativi.gs','PagamentiEvento.gs','InterfacciaMovimentiEvento.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
test('busy opening returns promptly without accessing sheets or releasing a foreign lock',()=>{
 const c=vm.createContext({LockService:{getScriptLock:()=>({tryLock(ms){assert.equal(ms,1000);return false;},releaseLock(){assert.fail('Foreign lock released');}})}});vm.runInContext(source,c);
 assert.equal(c.preparaAperturaFoglio_({event_id:'42',registrations:[]}).busy,true);
});
test('background synchronization gives priority to opening and keeps the deferred event cursor',()=>{
 const props=new Map([['MI_INTERACTIVE_OPEN_UNTIL',String(Date.now()+90000)]]);
 const c=vm.createContext({PropertiesService:{getScriptProperties:()=>({getProperty:k=>props.get(k),setProperty:(k,v)=>props.set(k,v)})}});vm.runInContext(source,c);
 assert.equal(c.sincronizzaFogliEventi().length,0);
 props.clear();Object.assign(c,{MI_SHEETS:{EVENT_WORKSPACES:'links'},ottieniSchedaObbligatoria_:()=>{},convertiRigheInOggetti_:()=>[{id_evento:'42',id_foglio:'a'},{id_evento:'43',id_foglio:'b'}],aggiornaFoglioOperativoEvento:form=>{assert.equal(form.background,true);return {busy:true};}});
 assert.equal(c.sincronizzaFogliEventi().length,0);assert.equal(props.get('MI_EVENT_SYNC_CURSOR'),'0');
});
test('free projection hides synchronization tab, paid projection restores it',()=>{
 let hidden=false,prepared=0,shown=false;
 const c=vm.createContext({ScriptApp:{getProjectTriggers:()=>[]},preparaPagamentiEvento_:()=>{},preparaAccessoGestioneEvento_:()=>prepared++});vm.runInContext(source,c);
 c.preparaPagamentiEvento_=()=>{};
 const sheet={getSheetByName:name=>name==='Gestione evento'?{hideSheet(){hidden=true;},showSheet(){hidden=false;}}:name==='Dati operativi'?{showSheet(){shown=true;}}:null};
 c.configuraSchedeEconomicheEvento_(sheet,'42',true);assert.equal(hidden,true);assert.equal(shown,true);assert.equal(prepared,0);
 c.configuraSchedeEconomicheEvento_(sheet,'42',false);assert.equal(hidden,false);assert.equal(prepared,1);
});
