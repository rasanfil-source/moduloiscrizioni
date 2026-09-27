import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {fixture,view} from './helpers/projection-sheet.mjs';

test('explicit contact deletion stays empty; an absent personal contact still inherits the buyer',()=>{
 const c=vm.createContext({});vm.runInContext(fs.readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),c);
 const buyer={telefono_referente:'+393331234567',email_referente:'buyer@example.invalid'};
 for(const [key,alias,value]of [['phone','participant_phone',buyer.telefono_referente],['email','participant_email',buyer.email_referente]]){
  assert.equal(c.valoreCampoElenco_(key,{},buyer,{}, {},[]),value);
  assert.equal(c.valoreCampoElenco_(key,{},buyer,{}, {[alias]:''},[]),'');
  assert.equal(c.valoreCampoElenco_(key,{},buyer,{}, {[alias]:'personale'},[]),'personale');
 }
});

test('payment sheet uses signed refunds and reversals, including a refund already carrying a minus sign',()=>{
 const f=fixture(),c=f.context();
 vm.runInContext(fs.readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),c);
 const projection={event:{id_evento:'42',schema_vista_json:'{"pricing":"CALCULATED"}'},payments:[
  {id_pagamento:'p1',codice_ordine:'ORD-1',tipo_movimento:'INCASSO',importo_centesimi:10000},
  {id_pagamento:'p2',codice_ordine:'ORD-1',tipo_movimento:'RIMBORSO',importo_centesimi:3000},
  {id_pagamento:'p3',codice_ordine:'ORD-1',tipo_movimento:'STORNO',importo_centesimi:-1000}
 ]};
 c.aggiornaPagamentiDaProiezione_(f.book,projection,true);
 const rows=f.book.getSheetByName('Pagamenti').cells.slice(1);
 assert.deepEqual(Array.from(rows,r=>r[4]),[100,-30,-10]);
 assert.equal(rows.reduce((sum,r)=>sum+r[4],0),60);
});

function recordFormats(f){
 const formats=[];
 Object.getPrototypeOf(f.sheet.getRange(1,1)).setNumberFormat=function(format){formats.push({sheet:this.sheet.name,row:this.row,col:this.col,height:this.height,width:this.width,format});return this;};
 return formats;
}
function moneyFormat(f,formats){
 const col=f.sheet.cells[0].indexOf('Residuo')+1;
 return formats.filter(r=>r.sheet==='Dati operativi'&&r.row<=2&&r.row+r.height>2&&r.col<=col&&r.col+r.width>col).at(-1)?.format;
}
test('monetary formats survive initial writes, updates and unchanged legacy sheet upgrades',()=>{
 const f=fixture(),c=f.context(),formats=recordFormats(f),v=view(1);
 v.righe[0].valori.balance=12.5;c.scriviProiezioneEvento_(f.sheet,v);
 assert.equal(moneyFormat(f,formats),'#,##0.00');
 formats.length=0;v.righe[0].valori.balance=7.25;c.scriviProiezioneEvento_(f.sheet,v);
 assert.equal(moneyFormat(f,formats),'#,##0.00');
 formats.length=0;f.calls.length=0;f.properties.delete('MI_MONEY_FORMAT_synthetic-book');
 c.scriviProiezioneEvento_(f.sheet,v);
 assert.equal(moneyFormat(f,formats),'#,##0.00');
 assert.equal(f.calls.filter(call=>call.sheet==='Dati operativi'&&call.kind==='setValues').length,0);
 formats.length=0;c.scriviProiezioneEvento_(f.sheet,v);assert.equal(formats.length,0);
});

test('recovering an interrupted row write restores the monetary format before clearing its journal',()=>{
 const f=fixture(),c=f.context(),formats=recordFormats(f),v=view(1);c.scriviProiezioneEvento_(f.sheet,v);
 formats.length=0;v.righe[0].valori.balance=21.5;
 f.state.fail=call=>call.sheet==='Dati operativi'&&call.kind==='setValues';
 assert.throws(()=>c.scriviProiezioneEvento_(f.sheet,v),/Injected interruption/);
 f.context().scriviProiezioneEvento_(f.sheet,v);
 assert.equal(moneyFormat(f,formats),'#,##0.00');assert.equal(f.properties.has('MI_WRITE_synthetic-book'),false);
});
