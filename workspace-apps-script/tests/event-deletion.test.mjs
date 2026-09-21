import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';

const source=fs.readFileSync(new URL('../src/EliminazioneEvento.gs',import.meta.url),'utf8');
function setup(){
 const props={},files=new Map(),accessed=[];let locked=false,busy=false,deny=false;
 const file=id=>{if(deny)throw Error('Access denied');if(!files.has(id))files.set(id,{trashed:false});const state=files.get(id);accessed.push(id);return {isTrashed:()=>state.trashed,setTrashed:value=>{state.trashed=value;}};};
 const c=vm.createContext({
  PropertiesService:{getScriptProperties:()=>({getProperty:key=>props[key]||null,setProperty:(key,value)=>{props[key]=value;},deleteProperty:key=>{delete props[key];}})},
  LockService:{getScriptLock:()=>({tryLock:()=>{if(busy)return false;locked=true;return true;},releaseLock:()=>{locked=false;}})},
  DriveApp:{getFileById:file},
  ottieniFoglioDiLavoroAssociato_:()=>{throw Error('CENTRAL_WORKBOOK_ACCESSED');},
  ottieniSchedaObbligatoria_:()=>{throw Error('CENTRAL_WORKBOOK_ACCESSED');},
 });
 vm.runInContext(source,c);
 const payload={id_evento:'42',request_id:'12345678-1234-4234-8234-123456789abc',mode:'trash',id_foglio:'sheet-event-identity-00042',direct_projection:true};
 return {c,payload,props,files,accessed,locked:()=>locked,busy:value=>{busy=value;},deny:value=>{deny=value;}};
}

test('direct deletion trashes only the event sheet and is idempotent without DB_MODULI',()=>{
 const x=setup();x.props.MI_DIRECT_SHEET_42=x.payload.id_foglio;x.props['MI_DIRECT_VIEW_'+x.payload.id_foglio]='fingerprint';
 const first=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(first.complete,true);assert.equal(first.removed,0);assert.equal(x.files.get(x.payload.id_foglio).trashed,true);
 assert.equal(x.props.MI_DIRECT_SHEET_42,undefined);assert.equal(x.props['MI_DIRECT_VIEW_'+x.payload.id_foglio],undefined);
 assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).complete,true);assert.equal(x.accessed.length,1);assert.equal(x.locked(),false);
});

test('keep detaches the direct association without trashing the document',()=>{
 const x=setup();x.payload.mode='keep';x.props.MI_DIRECT_SHEET_42=x.payload.id_foglio;
 const result=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(result.complete,true);assert.equal(x.files.has(x.payload.id_foglio),true);assert.equal(x.files.get(x.payload.id_foglio).trashed,false);
});

test('identity mismatch, changed retries and missing direct mode fail closed',()=>{
 const x=setup();x.props.MI_DIRECT_SHEET_42='different-sheet-identity-00042';
 assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).error,'EVENT_SHEET_MISMATCH');assert.equal(x.c.eventoInEliminazione_('42'),false);
 delete x.props.MI_DIRECT_SHEET_42;assert.equal(x.c.eliminaDatiEventoDaWordPress_({...x.payload,direct_projection:false}).error,'USE_DIRECT_PROJECTION');
 x.c.eliminaDatiEventoDaWordPress_(x.payload);assert.equal(x.c.eliminaDatiEventoDaWordPress_({...x.payload,mode:'keep'}).error,'DELETION_CONFLICT');
});

test('Drive failure retains the tombstone job and a retry completes',()=>{
 const x=setup();x.deny(true);assert.throws(()=>x.c.eliminaDatiEventoDaWordPress_(x.payload),/Access denied/);assert.equal(x.locked(),false);
 x.deny(false);assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).complete,true);
});

test('a busy direct deletion does not create a tombstone',()=>{
 const x=setup();x.busy(true);assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).error,'EVENT_BUSY');assert.equal(x.c.eventoInEliminazione_('42'),false);
});
