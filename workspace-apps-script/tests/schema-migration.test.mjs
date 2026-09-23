import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await Promise.all(['Config.gs','Setup.gs'].map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
test('migrazione partecipanti legacy mantiene nomi e stato con la larghezza corrente',()=>{
 const c=vm.createContext({});vm.runInContext(source,c);
 const headers=Array.from(vm.runInContext('MI_HEADERS.Partecipanti',c));
 let rows=[['codice_ordine','numero_partecipante','nome','cognome','dati_aggiuntivi_json'],['TEST',1,'Nome','Cognome','{}']];
 const sheet={getName:()=> 'Partecipanti',getMaxColumns:()=>30,getLastRow:()=>2,getMaxRows:()=>10,setFrozenRows(){},setHiddenGridlines(){},autoResizeColumns(){},getColumnWidth:()=>110,setColumnWidth(){},getRange:(row,col,height,width)=>{
  const range={getDisplayValues:()=>[Array.from({length:width},(_,i)=>rows[row-1]?.[i]??'')],getValues:()=>rows.slice(row-1,row-1+height).map(r=>r.slice(col-1,col-1+width)),clearContent:()=>range,setValues:values=>{assert.ok(values.every(r=>r.length===width));values.forEach((r,i)=>rows[row-1+i]=Array.from(r));return range;}};
  for(const name of ['setBackground','setFontColor','setFontWeight','setWrap','setVerticalAlignment'])range[name]=()=>range;
  return range;
 }};
 c.inizializzaScheda_(sheet,headers);
 assert.equal(rows[1].length,headers.length);assert.equal(rows[1][4],'Nome');assert.equal(rows[1][8],'ACTIVE');assert.equal(rows[1][14],'');
});
for (const [name,missing,last] of [['Iscrizioni',1,'replica_completa_revision'],['Eventi',1,'schema_vista_json'],['Eventi',2,'schema_vista_json'],['Eventi',3,'schema_vista_json']]) test(`migrazione ${name} da ${missing} colonne precedenti conserva i dati`,()=>{
 const c=vm.createContext({});vm.runInContext(source,c);const headers=vm.runInContext('MI_HEADERS.'+name,c);
 let width=headers.length-missing;const rows=[Array.from(headers).slice(0,-missing),['SYNTHETIC-ORDER']];
 const sheet={getName:()=> name,getMaxColumns:()=>width,insertColumnsAfter:(last,count)=>{assert.equal(last,width);width+=count;},getLastRow:()=>2,getMaxRows:()=>10,setFrozenRows(){},setHiddenGridlines(){},autoResizeColumns(){},getColumnWidth:()=>110,setColumnWidth(){},getRange:(row,col,height,size)=>{
  assert.ok(col+size-1<=width,'range exceeded existing grid');
  const range={getDisplayValues:()=>[Array.from({length:size},(_,i)=>rows[0][i]??'')],setValues:values=>{rows[row-1]=Array.from(values[0]);return range;}};
  for(const name of ['setBackground','setFontColor','setFontWeight','setWrap','setVerticalAlignment'])range[name]=()=>range;
  return range;
 }};
 c.inizializzaScheda_(sheet,headers);assert.equal(width,headers.length);assert.equal(rows[0].at(-1),last);assert.deepEqual(rows[1],['SYNTHETIC-ORDER']);
});
