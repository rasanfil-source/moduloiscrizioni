import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
const source=fs.readFileSync(new URL('../src/EliminazioneEvento.gs',import.meta.url),'utf8');
function setup(count=2){
 const names=['REGISTRATIONS','PARTICIPANTS','PAYMENTS','EMAIL_OUTBOX','SECRETARY_OPERATIONS','OPERATIONAL_STATE','OPERATIONAL_LIST','OPERATIONAL_VIEWS','ACCOMMODATIONS','REPLICA_REVISIONS','REPORT_TEMPLATES','EVENT_WORKSPACES','EVENTS','AUDIT_LOG'];
 const MI_SHEETS=Object.fromEntries(names.map(n=>[n,n]));const tables=Object.fromEntries(names.map(n=>[n,[]]));
 tables.REGISTRATIONS=[{id_evento:'42',codice_ordine:'A'},{id_evento:'43',codice_ordine:'B'}];
 for(const n of names.slice(1,7))tables[n]=Array.from({length:count},()=>({codice_ordine:'A'})).concat({codice_ordine:'B'});
 for(const n of names.slice(7,-1))tables[n]=[{id_evento:'42'},{id_evento:'43'}];
 tables.EVENT_WORKSPACES=[{id_evento:'42',id_foglio:'file42'},{id_evento:'43',id_foglio:'file43'}];
 const props={};let denied=false,trashed=false,locked=false;const calls=[];
 const sheet=n=>({name:n,getName:()=>n,clearContents(){assert.equal(locked,true);tables[n]=[];},deleteRows(row,count){assert.equal(locked,true);calls.push({name:n,row,count});tables[n].splice(row-2,count);}});
 const c=vm.createContext({MI_SHEETS,PropertiesService:{getScriptProperties:()=>({getProperty:k=>props[k]||null,setProperty:(k,v)=>{props[k]=v;},deleteProperty:k=>{delete props[k];}})},LockService:{getScriptLock:()=>({tryLock:()=>{locked=true;return true;},releaseLock:()=>{locked=false;}})},DriveApp:{getFileById:id=>{assert.equal(id,'file42');if(denied)throw Error('Access denied');return {isTrashed:()=>trashed,setTrashed:v=>{trashed=v;}};}},ottieniSchedaObbligatoria_:sheet,ottieniFoglioDiLavoroAssociato_:()=>({getSheetByName:sheet,getSheets:()=>names.map(sheet)}),convertiRigheInOggetti_:s=>tables[s.name].map((r,i)=>({...r,_row:i+2}))});
 vm.runInContext(source,c);
 const payload={id_evento:'42',request_id:'12345678-1234-4234-8234-123456789abc',mode:'trash',order_codes:['A']};
 return {c,tables,payload,calls,props,deny:v=>{denied=v;},trashed:()=>trashed,locked:()=>locked};
}
test('bounded deletion resumes, preserves sibling and rejects changed retry',()=>{
 const x=setup(110);let r=x.c.eliminaDatiEventoDaWordPress_(x.payload);assert.equal(r.complete,false);assert.equal(x.c.eventoInEliminazione_('42'),true);assert.equal(x.trashed(),true);
 for(let i=0;i<15&&!r.complete;i++)r=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(r.complete,true);for(const rows of Object.values(x.tables))assert.ok(rows.every(r=>r.id_evento!=='42'&&r.codice_ordine!=='A'));
 assert.equal(x.tables.REGISTRATIONS[0].codice_ordine,'B');assert.equal(x.tables.PARTICIPANTS[0].codice_ordine,'B');
 assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).complete,true);
 assert.equal(x.c.eliminaDatiEventoDaWordPress_({...x.payload,mode:'keep'}).error,'DELETION_CONFLICT');
});
test('Drive failure preserves data and file reference; retry completes',()=>{
 const x=setup();x.deny(true);assert.throws(()=>x.c.eliminaDatiEventoDaWordPress_(x.payload),/Access denied/);assert.equal(x.tables.REGISTRATIONS.length,2);assert.equal(x.tables.EVENT_WORKSPACES.length,2);assert.equal(x.locked(),false);
 x.deny(false);assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).complete,true);
});
test('keep leaves the document untouched and detaches all event data',()=>{const x=setup();x.payload.mode='keep';const r=x.c.eliminaDatiEventoDaWordPress_(x.payload);assert.equal(r.complete,true);assert.equal(x.trashed(),false);assert.equal(x.tables.EVENT_WORKSPACES.length,1);assert.match(r.sheet_url,/file42/);});
test('shared sheet is rejected before a tombstone or deletion',()=>{const x=setup();x.tables.EVENT_WORKSPACES[1].id_foglio='file42';assert.equal(x.c.eliminaDatiEventoDaWordPress_(x.payload).error,'SHARED_EVENT_SHEET');assert.equal(x.c.eventoInEliminazione_('42'),false);assert.equal(x.trashed(),false);});

test('printable report without order-code headers is cleared for its owner',()=>{
 const x=setup();x.props.MI_OPERATIONAL_LIST_EVENT='42';
 x.tables.OPERATIONAL_LIST=[{title:'Elenco operativo'},{Nome:'Persona privata'}];
 x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.deepEqual(x.tables.OPERATIONAL_LIST,[]);
 assert.equal(x.props.MI_OPERATIONAL_LIST_EVENT,undefined);
});
test('printable report belonging to another event survives deletion',()=>{
 const x=setup();x.props.MI_OPERATIONAL_LIST_EVENT='43';
 x.tables.OPERATIONAL_LIST=[{Nome:'Altro evento'}];
 x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.deepEqual(x.tables.OPERATIONAL_LIST,[{Nome:'Altro evento'}]);
 assert.equal(x.props.MI_OPERATIONAL_LIST_EVENT,'43');
});
test('slow reads still permit progress before yielding',()=>{
 const x=setup();let clock=0;x.c.Date={now:()=>{clock+=10000;return clock;}};
 let r=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(r.complete,false);assert.ok(r.removed>0);
 for(let i=0;i<50&&!r.complete;i++)r=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(r.complete,true);assert.equal(x.tables.REGISTRATIONS.length,1);
});

test('one contiguous batch removes 100 rows with one Google call',()=>{
 const x=setup(250),r=x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.equal(r.removed,100);assert.equal(r.complete,false);assert.equal(x.calls.length,1);assert.equal(x.calls[0].count,100);
 assert.equal(x.tables.REGISTRATIONS.length,2);
});

test('interleaved rows keep other events and delete ranges from bottom up',()=>{
 const x=setup(0);x.tables.PARTICIPANTS=[{codice_ordine:'A'},{codice_ordine:'A'},{codice_ordine:'B'},{codice_ordine:'A'},{codice_ordine:'A'},{codice_ordine:'B'}];
 x.c.eliminaDatiEventoDaWordPress_(x.payload);
 assert.deepEqual(x.tables.PARTICIPANTS.map(r=>r.codice_ordine),['B','B']);
 assert.deepEqual(x.calls.filter(c=>c.name==='PARTICIPANTS').map(c=>[c.row,c.count]),[[5,2],[2,2]]);
});
