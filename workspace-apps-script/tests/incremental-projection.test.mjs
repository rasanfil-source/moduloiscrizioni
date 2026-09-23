import test from 'node:test';
import assert from 'node:assert/strict';
import {fixture,view} from './helpers/projection-sheet.mjs';

test('one changed person writes one operational row and one baseline row out of 1000',()=>{
  const f=fixture(),c=f.context(),v=view(1000);c.scriviProiezioneEvento_(f.sheet,v);f.calls.length=0;
  v.righe[450].valori.first_name='Nome corretto';
  const result=c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(result.aggiornate,1);assert.equal(result.struttura,false);
  assert.equal(f.calls.filter(x=>x.sheet==='Dati operativi'&&x.kind==='setValues').reduce((n,x)=>n+x.count,0),1);
  assert.equal(f.calls.filter(x=>x.sheet==='_MI_BASE'&&x.kind==='setValues').reduce((n,x)=>n+x.count,0),1);
  assert.equal(f.calls.some(x=>x.sheet==='Dati operativi'&&x.kind==='clear'),false);
  assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
  f.calls.length=0;c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(f.calls.length,0);assert.ok(f.sheet.editable.length);
});
test('adjacent updates are batched; manual edits block all projection changes',()=>{
  const f=fixture(),c=f.context(),v=view();c.scriviProiezioneEvento_(f.sheet,v);f.calls.length=0;
  v.righe[0].valori.balance=0;v.righe[1].valori.balance=50;c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(f.calls.filter(x=>x.sheet==='Dati operativi'&&x.kind==='setValues').length,1);
  f.sheet.cells[1][1]='Modifica manuale';f.calls.length=0;v.righe[2].valori.balance=20;
  const result=c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(result.manuali,1);assert.equal(f.calls.length,0);assert.equal(f.sheet.cells[3][2],100);
});
test('append, removal, empty event and manual row order keep identities and baseline coherent',()=>{
  const f=fixture(),c=f.context(),v=view();c.scriviProiezioneEvento_(f.sheet,v);
  [f.sheet.cells[1],f.sheet.cells[3]]=[f.sheet.cells[3],f.sheet.cells[1]];
  const next=view(4);c.scriviProiezioneEvento_(f.sheet,next);
  assert.deepEqual(f.sheet.cells.slice(1,5).map(r=>r[4]),['ORD2','ORD1','ORD0','ORD3']);
  next.righe=next.righe.filter(r=>r.codice_ordine!=='ORD1');c.scriviProiezioneEvento_(f.sheet,next);
  assert.equal(f.sheet.getLastRow(),4);assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
  c.scriviProiezioneEvento_(f.sheet,view(0));assert.equal(f.sheet.getLastRow(),1);assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
});
test('the visible number is local, stays editable and appends from the preceding row',()=>{
  const f=fixture(),c=f.context(),v=view();
  v.colonne.unshift({key:'participant_number',label:'N.'});
  v.righe.forEach((row,index)=>row.valori.participant_number=index+1);
  c.scriviProiezioneEvento_(f.sheet,v);
  assert.ok(f.sheet.editable.some(range=>range.col===1));
  f.sheet.cells[1][0]=7;f.sheet.cells[2][0]=8;f.sheet.cells[3][0]=9;
  const pending=c.modificheCorrentiFoglio_(f.sheet);
  assert.equal(pending.initialized,true);assert.equal(pending.changes.length,0);assert.equal(pending.errors.length,0);
  const next=view(4);next.colonne.unshift({key:'participant_number',label:'N.'});
  next.righe.forEach((row,index)=>row.valori.participant_number=index+1);
  c.scriviProiezioneEvento_(f.sheet,next);
  assert.deepEqual(f.sheet.cells.slice(1,5).map(row=>row[0]),[7,8,9,10]);
  assert.deepEqual(f.sheet.cells.slice(1,5).map(row=>row[4]),['ORD0','ORD1','ORD2','ORD3']);
});
for(const failure of ['clear','data','base'])test('a new execution resumes an interrupted '+failure+' write without false manual conflicts',()=>{
  const f=fixture(),v=view();let c=f.context();c.scriviProiezioneEvento_(f.sheet,v);
  if(failure==='clear'){v.colonne.push({key:'phone',label:'Telefono'});v.righe.forEach(r=>r.valori.phone='000');}
  else v.righe[1].valori.first_name='Corretto';
  f.state.fail=x=>failure==='clear'?x.sheet==='Dati operativi'&&x.kind==='clear':x.kind==='setValues'&&x.sheet===(failure==='data'?'Dati operativi':'_MI_BASE')&&x.row>1;
  assert.throws(()=>c.scriviProiezioneEvento_(f.sheet,v),/Injected interruption/);
  assert.equal(f.sheet.editable.length,0);assert.equal(f.properties.get('MI_WRITE_synthetic-book'),'READY');
  c=f.context();const result=c.scriviProiezioneEvento_(f.sheet,v);
  assert.equal(result.conflitti,0);assert.equal(result.manuali,0);assert.equal(c.modificheCorrentiFoglio_(f.sheet).errors.length,0);
  assert.equal(c.modificheCorrentiFoglio_(f.sheet).changes.length,0);assert.ok(f.sheet.editable.length);
  assert.equal(f.properties.has('MI_WRITE_synthetic-book'),false);
});
test('generation rejects late delivery, mismatched retry and unversioned downgrade',()=>{
  const f=fixture(),c=f.context(),request={projection_generation:'3',projection_hash:'new'};
  c.verificaGenerazioneProiezione_('42',request);
  assert.throws(()=>c.verificaGenerazioneProiezione_('42',{projection_generation:'2',projection_hash:'old'}),/STALE_EVENT_PROJECTION/);
  assert.throws(()=>c.verificaGenerazioneProiezione_('42',{...request,projection_hash:'different'}),/STALE_EVENT_PROJECTION/);
  assert.throws(()=>c.verificaGenerazioneProiezione_('42',{}),/PROJECTION_GENERATION_REQUIRED/);
  c.verificaGenerazioneProiezione_('42',request);
  c.verificaGenerazioneProiezione_('42',{projection_generation:'4',projection_hash:'next'});
});
