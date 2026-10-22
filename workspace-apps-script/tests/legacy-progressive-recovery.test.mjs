import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source=fs.readFileSync(new URL('../src/SincronizzazioneManuale.gs',import.meta.url),'utf8');

function environment({numbers=[1,2,3],name='Ada'}={}) {
  const rows=[
    ['Partecipante','Cognome','Nome','Prenotazione'],
    ...numbers.map((number,index)=>[number,'Rossi',index===0?name:'Nome '+(index+1),'ORD-'+(index+1)])
  ];
  const baseRows=numbers.map((_,index)=>['ORD-'+(index+1),1,JSON.stringify({last_name:'Rossi',first_name:index===0?'Ada':'Nome '+(index+1)})]);
  const baseSheet={
    getLastRow:()=>baseRows.length+1,
    getRange:(row,column,height,width)=>({getValues:()=>baseRows.slice(row-2,row-2+height).map(value=>value.slice(column-1,column-1+width))})
  };
  const writes=[];
  const range=(row,column,height,width)=>({
    getDisplayValues:()=>rows.slice(row-1,row-1+height).map(value=>value.slice(column-1,column-1+width).map(String)),
    setValues:values=>{writes.push({row,column,values});values.forEach((value,index)=>{rows[row-1+index][column-1]=value[0];});}
  });
  const protection={getDescription:()=> 'MI_PROIEZIONE',getUnprotectedRanges:()=>['editable']};
  const sheet={
    getParent:()=>({getSheetByName:name=>name==='_MI_BASE'?baseSheet:null}),
    getLastRow:()=>rows.length,
    getLastColumn:()=>rows[0].length,
    getRange:range,
    getProtections:()=>[protection]
  };
  const protections=[];
  const context=vm.createContext({
    SpreadsheetApp:{ProtectionType:{SHEET:'SHEET'},flush(){}},
    mappaColonneEvento_:()=>({_numero:1,last_name:2,first_name:3,_ordine:4}),
    proteggiProiezione_:(target,editable)=>protections.push(editable||[])
  });
  vm.runInContext(source,context);
  return {context,sheet,rows,writes,protections};
}

test('recupera il progressivo scritto nella vecchia colonna identita',()=>{
  const env=environment();
  assert.equal(env.context.riparaProgressivoSuIdentitaLegacy_(env.sheet),true);
  assert.deepEqual(env.rows.slice(1).map(row=>row[0]),[1,1,1]);
  assert.equal(env.writes.length,1);
  assert.deepEqual(env.protections,[[],['editable']]);
});

test('non modifica il foglio se un altro dato diverge dalla base',()=>{
  const env=environment({name:'Modificato'});
  assert.equal(env.context.riparaProgressivoSuIdentitaLegacy_(env.sheet),false);
  assert.equal(env.writes.length,0);
});

test('non modifica numerazioni che non sono il progressivo completo 1..N',()=>{
  const env=environment({numbers:[1,4,3]});
  assert.equal(env.context.riparaProgressivoSuIdentitaLegacy_(env.sheet),false);
  assert.equal(env.writes.length,0);
});
