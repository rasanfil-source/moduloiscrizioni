import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {fixture,view} from './helpers/projection-sheet.mjs';

function formattedFixture(){
  const f=fixture(),prototype=Object.getPrototypeOf(f.sheet.getRange(1,1));
  prototype.setNumberFormat=function(format){
    this.sheet.formats??=new Map();
    for(let i=0;i<this.height;i++)for(let j=0;j<this.width;j++)this.sheet.formats.set(`${this.row+i}:${this.col+j}`,format);
    return this;
  };
  prototype.getDisplayValues=function(){return this.getValues().map((row,i)=>row.map((value,j)=>{
    const format=this.sheet.formats?.get(`${this.row+i}:${this.col+j}`);
    return typeof value==='number'&&format==='#,##0.00'?value.toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2}):String(value);
  }));};
  prototype.getDisplayValue=function(){return this.getDisplayValues()[0][0];};
  return f;
}

test('money display and baseline stay equal after initial write and later updates',()=>{
  const f=formattedFixture(),c=f.context(),v=view(1);
  const keys=['total','paid','balance','paid_cash','paid_transfer','paid_card'];
  v.colonne=[{key:'first_name',label:'Nome'},...keys.map(key=>({key,label:key}))];
  for(const amount of [12.5,1234.5,0,-12.5]){
    keys.forEach(key=>v.righe[0].valori[key]=amount);
    v.righe[0].valori.first_name='Aggiornato '+amount;
    const result=c.scriviProiezioneEvento_(f.sheet,v);
    assert.equal(result.conflitti,0);
    assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
    assert.equal(c.modificheCorrentiFoglio_(f.sheet).changes.length,0);
    const base=JSON.parse(f.book.getSheetByName('_MI_BASE').getRange(2,3).getValue());
    const columns=c.mappaColonneEvento_(f.sheet);
    for(const key of keys)assert.equal(base[key],f.sheet.getRange(2,columns[key]).getDisplayValue());
    assert.equal(f.sheet.getRange(2,columns.first_name).getValue(),'Aggiornato '+amount);
  }
});

test('legacy money baseline recovers while manual names and changed amounts remain protected',()=>{
  const f=formattedFixture(),c=f.context(),v=view(1);v.righe[0].valori.balance=12.5;
  c.scriviProiezioneEvento_(f.sheet,v);
  const base=f.book.getSheetByName('_MI_BASE'),old=JSON.parse(base.getRange(2,3).getValue()),cols=c.mappaColonneEvento_(f.sheet);
  old.balance='12.5';base.getRange(2,3).setValue(JSON.stringify(old));f.properties.set('MI_MONEY_FORMAT_synthetic-book','1');
  f.sheet.getRange(2,cols.first_name).setValue('Correzione manuale');
  let pending=c.modificheCorrentiFoglio_(f.sheet);
  assert.equal(pending.errors.length,0);assert.equal(pending.changes.length,1);
  assert.equal(c.scriviProiezioneEvento_(f.sheet,v).manuali,1);
  assert.equal(f.sheet.getRange(2,cols.first_name).getValue(),'Correzione manuale');
  f.sheet.getRange(2,cols.first_name).setValue(v.righe[0].valori.first_name);
  f.sheet.getRange(2,cols.balance).setValue(13.5);
  assert.deepEqual(Array.from(c.modificheCorrentiFoglio_(f.sheet).errors),['Colonna non modificabile: balance']);
  f.sheet.getRange(2,cols.balance).setValue(12.5);
  assert.equal(c.scriviProiezioneEvento_(f.sheet,v).conflitti,0);
  assert.equal(JSON.parse(base.getRange(2,3).getValue()).balance,'12,50');
  assert.equal(f.properties.get('MI_MONEY_FORMAT_synthetic-book'),'2');
});

test('old COMMITTED journal preserves later manual edits and upgrades baseline only after review',()=>{
  const f=formattedFixture(),c=f.context(),v=view(1);v.righe[0].valori.balance=12.5;
  c.scriviProiezioneEvento_(f.sheet,v);
  const columns=c.mappaColonneEvento_(f.sheet),old=f.sheet.getRange(2,1,1,f.sheet.getLastColumn()).getValues();
  const plan=c.pianoProiezioneIncrementale_(v,columns,old,f.sheet.getRange(1,1,1,f.sheet.getLastColumn()).getValues()[0]);
  c.preparaScritturaProiezione_(f.sheet,plan);
  f.sheet.getRange(2,columns.balance).setNumberFormat('@');
  c.aggiornaBaseIncrementale_(f.sheet,plan.columns,1);
  f.properties.set('MI_WRITE_synthetic-book','COMMITTED');
  f.properties.set('MI_MONEY_FORMAT_synthetic-book','1');
  f.sheet.getRange(2,columns.first_name).setValue('Correzione dopo il commit');
  f.context().riprendiScritturaProiezione_(f.sheet);
  assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
  assert.equal(c.modificheCorrentiFoglio_(f.sheet).changes.length,1);
  assert.equal(f.properties.has('MI_WRITE_synthetic-book'),false);assert.ok(f.sheet.editable.length);
  f.sheet.getRange(2,columns.first_name).setValue(v.righe[0].valori.first_name);
  c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(JSON.parse(f.book.getSheetByName('_MI_BASE').getRange(2,3).getValue()).balance,'12,50');
});

function driveFixture(){
  const properties=new Map(),books=new Map(),state={creates:0,driveFailure:false,openFailure:false,trashed:false};
  const makeBook=()=>{
    const id=`synthetic-sheet-id-${String(++state.creates).padStart(6,'0')}`,metadata=[];
    const sheet={getDeveloperMetadata:()=>metadata,setName(){},addDeveloperMetadata(key,value){metadata.push({getKey:()=>key,getValue:()=>value});}};
    const book={getId:()=>id,getSheetByName:()=>sheet,getSheets:()=>[sheet]};books.set(id,book);return book;
  };
  const c=vm.createContext({
    PropertiesService:{getScriptProperties:()=>({getProperty:key=>properties.get(key),setProperty:(key,value)=>properties.set(key,value),deleteProperty:key=>properties.delete(key)})},
    SpreadsheetApp:{create:makeBook,openById:id=>{if(state.openFailure)throw Error('Sheets temporarily unavailable');return books.get(id);}},
    DriveApp:{getFileById(){if(state.driveFailure)throw Error('Drive temporarily unavailable');return {isTrashed:()=>state.trashed};}},
    spostaFoglioAccantoAlDatabase_(){}
  });
  vm.runInContext(fs.readFileSync(new URL('../src/ProiezioneDiretta.gs',import.meta.url),'utf8'),c);
  return {c,state,properties};
}
for(const failure of ['driveFailure','openFailure'])test('registered sheet survives '+failure+' and successful retry reuses it',()=>{
  const {c,state,properties}=driveFixture(),payload={event_id:'42',sheet_id:''};
  const first=c.apriFoglioEventoFirmato_(payload,true,'Synthetic');state[failure]=true;
  assert.throws(()=>c.apriFoglioEventoFirmato_(payload,true,'Synthetic'),/temporarily unavailable/);
  assert.equal(state.creates,1);assert.equal(properties.get('MI_DIRECT_SHEET_42'),first.book.getId());
  state[failure]=false;assert.equal(c.apriFoglioEventoFirmato_(payload,true,'Synthetic').book.getId(),first.book.getId());
});
test('only a verified trashed registry file may be recreated without an explicit WordPress ID',()=>{
  const {c,state}=driveFixture(),payload={event_id:'42',sheet_id:''};
  const first=c.apriFoglioEventoFirmato_(payload,true,'Synthetic');state.trashed=true;
  assert.throws(()=>c.apriFoglioEventoFirmato_({...payload,sheet_id:first.book.getId()},true,'Synthetic'),/EVENT_SHEET_MISSING/);
  assert.equal(state.creates,1);
  assert.notEqual(c.apriFoglioEventoFirmato_(payload,true,'Synthetic').book.getId(),first.book.getId());
  assert.equal(state.creates,2);
});
