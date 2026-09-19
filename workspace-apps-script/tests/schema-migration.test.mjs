import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await Promise.all(['Config.gs','Setup.gs'].map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
for (const [name,missing,last] of [['Iscrizioni',1,'replica_completa_revision'],['Eventi',1,'profilo_operativo'],['Eventi',2,'profilo_operativo']]) test(`migrazione ${name} da ${missing} colonne precedenti conserva i dati`,()=>{
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
