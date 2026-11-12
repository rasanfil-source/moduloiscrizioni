import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await Promise.all(['Core.gs','SincronizzazioneManuale.gs'].map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
function environment(){
 let value='Anna Maria ',base={first_name:'Anna'},stored={},locks=0;
 const baseSheet={getLastRow:()=>2,getRange:(row,col)=>({getValues:()=>[['DEMO',1,JSON.stringify(base),JSON.stringify(stored)]],setValue:v=>{if(col===4)stored=JSON.parse(v);else base=JSON.parse(v);}})};
 const sheet={getParent:()=>({getSheetByName:()=>baseSheet}),getProtections:()=>[],getLastRow:()=>2,getLastColumn:()=>3,getRange:(row,col,height,width)=>({getDisplayValues:()=>[['DEMO','1',value]],getDisplayValue:()=>value,setNumberFormat(){return this;},setValue:v=>{value=v;}})};
 const c=vm.createContext({SpreadsheetApp:{flush(){},ProtectionType:{SHEET:'sheet'}},LockService:{getScriptLock:()=>({waitLock(){locks++;},releaseLock(){locks--;}})},apriFoglioEventoFirmato_:()=>({sheet}),mappaColonneEvento_:()=>({_ordine:1,_numero:2,first_name:3}),proteggiProiezione_(){}});
 vm.runInContext(source,c);
 c.convertiRigheInOggetti_=v=>v;
 return {c,sheet,get value(){return value;},set value(v){value=v;},get base(){return base;},get stored(){return stored;},set stored(v){stored=v;},get locks(){return locks;}};
}

test('normalization back to baseline leaves no receipt and old no-op receipts are cleaned safely',()=>{
 const e=environment();e.value='Anna ';
 e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[{...receipt,after:'Anna ',accepted:'Anna',workspace_revision:'8'}]});
 assert.equal(e.value,'Anna');assert.deepEqual(Object.keys(e.stored),[]);
 e.stored={first_name:{before:'Anna',accepted:'Anna',revision:8},phone:{before:'1',accepted:'2',revision:8}};
 e.c.allineaBaseConVista_(e.sheet,{righe:[]});
 assert.equal(e.stored.first_name,undefined);assert.ok(e.stored.phone);
 e.value='Nuova modifica';e.c.allineaBaseConVista_(e.sheet,{righe:[]});
 assert.equal(e.value,'Nuova modifica');assert.equal(e.base.first_name,'Anna');
});
const receipt={order_code:'DEMO',number:1,key:'first_name',before:'Anna',after:'Anna Maria ',accepted:'Anna Maria'};
test('lettura dopo conferma e seconda modifica usano la ricevuta autorevole',()=>{
 const e=environment(),payload={event_id:'42',direct_projection:true};
 e.c.confermaModificheFoglio_({...payload,confirmations:[{...receipt,workspace_revision:'8'}]});
 assert.equal(e.c.leggiModificheEventoMysql_(payload).changes.length,0);
 e.value='Anna Nuova';
 const changes=e.c.leggiModificheEventoMysql_(payload).changes;
 assert.equal(changes[0].before,'Anna Maria');
 e.c.confermaModificheFoglio_({...payload,confirmations:[{...changes[0],accepted:'Anna Nuova',workspace_revision:'9'}]});
 assert.equal(e.c.leggiModificheEventoMysql_(payload).changes.length,0);
 e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,workspace_revision:'9',valori:{first_name:'Anna Nuova'}}]});
 assert.equal(e.base.first_name,'Anna Nuova');
});
test('more than 500 distinct receipts are confirmed and replay preserves subsequent edits',()=>{
 const count=501,values=Array.from({length:count},()=> 'Anna Maria '),stored=Array.from({length:count},()=>({}));
 const baseRows=values.map((value,index)=>['BOOK'+index,1,JSON.stringify({first_name:'Anna'}),'{}']);
 const baseSheet={
  getLastRow:()=>count+1,
  getRange:(row,col)=>({
   getValues:()=>baseRows.map((value,index)=>[...value.slice(0,3),JSON.stringify(stored[index])]),
   setValue:value=>{stored[row-2]=JSON.parse(value);}
  })
 };
 const sheet={
  getParent:()=>({getSheetByName:()=>baseSheet}),getProtections:()=>[],
  getLastRow:()=>count+1,getLastColumn:()=>3,
  getRange:(row,col)=>({
   getDisplayValues:()=>values.map((value,index)=>['BOOK'+index,'1',value]),
   getDisplayValue:()=>values[row-2],
   setNumberFormat(){return this;},
   setValue:value=>{values[row-2]=value;}
  })
 };
 let locks=0;
 const c=vm.createContext({
  SpreadsheetApp:{flush(){},ProtectionType:{SHEET:'sheet'}},
  LockService:{getScriptLock:()=>({waitLock(){locks++;},releaseLock(){locks--;}})},
  apriFoglioEventoFirmato_:()=>({sheet}),
  mappaColonneEvento_:()=>({_ordine:1,_numero:2,first_name:3}),proteggiProiezione_(){}
 });
 vm.runInContext(source,c);
 const confirmations=values.map((value,index)=>({...receipt,order_code:'BOOK'+index,workspace_revision:'8'}));
 const payload={event_id:'42',direct_projection:true,confirmations};
 assert.equal(c.confermaModificheFoglio_(payload).updated,count);
 assert.ok(values.every(value=>value==='Anna Maria'));
 assert.ok(stored.every(value=>value.first_name.revision===8));
 values[count-1]='Modifica successiva';
 assert.equal(c.confermaModificheFoglio_(payload).updated,count-1);
 assert.equal(values[count-1],'Modifica successiva');
 assert.equal(locks,0);
});
test('ricevuta legacy normalizzata aggiorna la base immediatamente',()=>{
 const e=environment();const vista={righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna Maria'}}]};
 e.c.allineaBaseConVista_(e.sheet,vista);assert.equal(e.base.first_name,'Anna');
 assert.equal(e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[receipt]}).updated,1);
 assert.equal(e.value,'Anna Maria');assert.equal(e.base.first_name,'Anna Maria','legacy receipt updates its accepted cell');
 e.c.allineaBaseConVista_(e.sheet,vista);assert.equal(e.base.first_name,'Anna Maria');assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,0);assert.equal(e.locks,0);
});
test('ricevuta tardiva non cancella una nuova modifica e il retry è innocuo',()=>{
 const e=environment();e.value='Nuovo nome';assert.equal(e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[receipt]}).updated,0);assert.equal(e.value,'Nuovo nome');
 e.value=receipt.after;e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[receipt]});assert.equal(e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[receipt]}).updated,0);
 assert.equal(e.locks,0);
});
test('normalizzazione della vista usa lo stesso testo visualizzato nelle celle',()=>{
 const e=environment();e.value='Anna Maria';e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna\nMaria'}}]});assert.equal(e.base.first_name,'Anna Maria');
});

test('replica multilinea senza ricevuta riconosce il valore identico senza perdere modifiche nuove',()=>{
 const e=environment();e.value='Anna\nMaria';
 e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna\nMaria'}}]});
 assert.equal(e.base.first_name,'Anna\nMaria');assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,0);
 e.value='Anna\nNuova';
 e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,valori:{first_name:'Anna\nMaria'}}]});
 assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,1);
});
test('replica successiva riconosce una modifica accettata anche se quella intermedia è stata accorpata',()=>{
 const e=environment();
 e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[{...receipt,workspace_revision:'8'}]});
 e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,workspace_revision:'9',valori:{first_name:'Anna'}}]});
 assert.equal(e.base.first_name,'Anna Maria');
 assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,0,'projection can now replace the acknowledged cell with revision 9');
});
test('una replica obsoleta o incompleta non conferma la ricevuta',()=>{
 for(const revision of ['7',null,undefined]){
  const e=environment();e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[{...receipt,workspace_revision:'8'}]});
  e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,workspace_revision:revision,valori:{first_name:'Anna Maria'}}]});
  assert.equal(e.base.first_name,'Anna');assert.equal(e.c.modificheCorrentiFoglio_(e.sheet).changes.length,1);
 }
});
test('la conferma della replica preserva un ulteriore cambiamento locale',()=>{
 const e=environment();e.c.confermaModificheFoglio_({event_id:'42',direct_projection:true,confirmations:[{...receipt,workspace_revision:'8'}]});
 e.value='Nuova modifica';
 e.c.allineaBaseConVista_(e.sheet,{righe:[{codice_ordine:'DEMO',numero_partecipante:1,workspace_revision:'9',valori:{first_name:'Anna'}}]});
 const changes=e.c.modificheCorrentiFoglio_(e.sheet).changes;
 assert.equal(e.value,'Nuova modifica');assert.equal(changes.length,1);assert.equal(changes[0].before,'Anna Maria');assert.equal(changes[0].after,'Nuova modifica');
});
