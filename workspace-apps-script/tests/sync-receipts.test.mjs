import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await Promise.all(['Core.gs','SincronizzazioneManuale.gs'].map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
function environment(){
 let value='Anna Maria ',base={first_name:'Anna'},locks=0;
 const baseSheet={getLastRow:()=>2,getRange:()=>({getValues:()=>[['DEMO',1,JSON.stringify(base)]],setValue:v=>{base=JSON.parse(v);}})};
 const sheet={getParent:()=>({getSheetByName:()=>baseSheet}),getProtections:()=>[],getLastRow:()=>2,getLastColumn:()=>3,getRange:(row,col,height,width)=>({getDisplayValues:()=>[['DEMO','1',value]],getDisplayValue:()=>value,setNumberFormat(){return this;},setValue:v=>{value=v;}})};
 const c=vm.createContext({SpreadsheetApp:{openById:()=>({getSheetByName:()=>sheet}),flush(){},ProtectionType:{SHEET:'sheet'}},LockService:{getScriptLock:()=>({waitLock(){locks++;},releaseLock(){locks--;}})},MI_SHEETS:{EVENT_WORKSPACES:'links'},ottieniSchedaObbligatoria_:()=>[{id_evento:'42',id_foglio:'test'}],convertiRigheInOggetti_:v=>v,mappaColonneEvento_:()=>({_ordine:1,_numero:2,first_name:3}),proteggiProiezione_(){}});
 vm.runInContext(source,c);
 c.convertiRigheInOggetti_=v=>v;
 return {c,sheet,get value(){return value;},set value(v){value=v;},get base(){return base;},get locks(){return locks;}};
}
const receipt={order_code:'DEMO',number:1,key:'first_name',before:'Anna',after:'Anna Maria ',accepted:'Anna Maria'};
test('ricevuta normalizzata sblocca la proiezione solo dopo la replica canonica',()=>{
 const e=environment();const vista={righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna Maria'}}]};
 e.c.allineaBaseConVista_(e.sheet,vista);assert.equal(e.base.first_name,'Anna');
 assert.equal(e.c.confermaModificheFoglio_({event_id:'42',confirmations:[receipt]}).updated,1);
 assert.equal(e.value,'Anna Maria');assert.equal(e.base.first_name,'Anna','receipt must not invent a completed replica');
 e.c.allineaBaseConVista_(e.sheet,vista);assert.equal(e.base.first_name,'Anna Maria');assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,0);assert.equal(e.locks,0);
});
test('ricevuta tardiva non cancella una nuova modifica e il retry è innocuo',()=>{
 const e=environment();e.value='Nuovo nome';assert.equal(e.c.confermaModificheFoglio_({event_id:'42',confirmations:[receipt]}).updated,0);assert.equal(e.value,'Nuovo nome');
 e.value=receipt.after;e.c.confermaModificheFoglio_({event_id:'42',confirmations:[receipt]});assert.equal(e.c.confermaModificheFoglio_({event_id:'42',confirmations:[receipt]}).updated,0);
 assert.equal(e.locks,0);
});
test('normalizzazione della vista usa lo stesso testo visualizzato nelle celle',()=>{
 const e=environment();e.value='Anna Maria';e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna\nMaria'}}]});assert.equal(e.base.first_name,'Anna Maria');
});
