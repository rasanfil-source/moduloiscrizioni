import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import test from 'node:test';
const c=vm.createContext({});vm.runInContext(await readFile(new URL('../src/SincronizzazioneManuale.gs',import.meta.url),'utf8'),c);
const base={'["MI-1",1]':{first_name:'Anna',room:'A',balance:'100'},'["MI-1",2]':{first_name:'Maria',room:'B',balance:'100'}};
const rows=()=>[{order:'MI-1',number:2,values:{first_name:'Maria',room:'B',balance:'100'}},{order:'MI-1',number:1,values:{first_name:'Anna',room:'A',balance:'100'}}];
test('ordinamento righe non genera cambiamenti, due camere scambiate restano due modifiche',()=>{
 assert.equal(c.confrontaModificheFoglio_(base,rows()).changes.length,0);
 const changed=rows();changed[0].values.room='A';changed[1].values.room='B';
 const r=c.confrontaModificheFoglio_(base,changed);assert.equal(r.changes.length,2);assert.equal(r.errors.length,0);assert.equal(r.changes[0].number,2);
});
test('svuotare una cella è una modifica; cancellare una riga o alterare importi viene segnalato',()=>{
 const changed=rows();changed[0].values.room='';changed[1].values.balance='0';
 const r=c.confrontaModificheFoglio_(base,changed);assert.equal(r.changes[0].after,'');assert.equal(r.errors.length,1);
 assert.equal(c.confrontaModificheFoglio_(base,changed.slice(0,1)).errors.length,1);
 assert.equal(c.confrontaModificheFoglio_(base,[...rows(),rows()[0]]).errors.length,1);
});

test('una replica conferma soltanto celle coincidenti e conserva una modifica successiva',()=>{
 const stored=[['MI-1',1,JSON.stringify({first_name:'Anna',room:'A'})]];
 const baseSheet={getLastRow:()=>2,getRange:(row,col)=>({getValues:()=>stored,setValue:value=>{stored[row-2][col-1]=value;}})};
 const sheet={getParent:()=>({getSheetByName:()=>baseSheet})};
 const original=c.modificheCorrentiFoglio_;
 c.modificheCorrentiFoglio_=()=>({changes:[{order_code:'MI-1',number:1,key:'first_name',before:'Anna',after:'Anna Maria'},{order_code:'MI-1',number:1,key:'room',before:'A',after:'C'}],errors:[]});
 try{
  c.allineaBaseConVista_(sheet,{righe:[{codice_ordine:'MI-1',numero_partecipante:1,valori:{first_name:'Anna Maria',room:'B'}}]});
  assert.deepEqual(JSON.parse(stored[0][2]),{first_name:'Anna Maria',room:'A'});
 }finally{c.modificheCorrentiFoglio_=original;}
});
