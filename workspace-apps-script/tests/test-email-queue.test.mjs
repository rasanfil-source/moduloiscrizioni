import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../src/Email.gs', import.meta.url), 'utf8');
function setup({count=3, quota=100, busy=false, failure='', elapsedPerSend=0}={}) {
  const rows=Array.from({length:count}, (_,i)=>({_row:i+2, stato:'PREVIEW', contenuto_json:'{}', codice_ordine:'ORDER'+i, id_messaggio:'msg'+i}));
  const sent=[], logs=[], alerts=[];
  let locked=false, now=0, flushes=0;
  const ctx={Date:{now:()=>now}, Number, JSON,
    ottieniConfigurazione_:()=> 'TEST',
    PropertiesService:{getScriptProperties:()=>({getProperty:()=> 'private@example.invalid'})},
    LockService:{getScriptLock:()=>({tryLock:()=>{locked=!busy;return locked;},releaseLock:()=>{locked=false;}})},
    MI_SHEETS:{EMAIL_OUTBOX:'outbox'},
    ottieniSchedaObbligatoria_:()=>({getRange:r=>({setValue:v=>{assert.equal(locked,true);if(failure==='state' && v==='TEST_INVIATA')throw Error('write');rows[r-2].stato=v;}})}),
    creaIndiceIntestazioni_:()=>({stato:5}), convertiRigheInOggetti_:()=>rows.map(r=>({...r})),
    Session:{getActiveUser:()=>({getEmail:()=> 'actor@example.invalid'})}, normalizzaTesto_:v=>String(v||''),
    MailApp:{getRemainingDailyQuota:()=>quota-sent.length,sendEmail:m=>{assert.equal(locked,true);assert.ok(flushes>sent.length);assert.equal(rows[sent.length].stato,'TEST_IN_CORSO');sent.push(m);now+=elapsedPerSend;if(failure==='send')throw Error('ambiguous');}},
    aggiungiControllo_:(...args)=>{if(failure==='log')throw Error('audit');logs.push(args);},
    SpreadsheetApp:{flush:()=>{flushes++;if(failure==='flush')throw Error('flush');},getUi:()=>({alert:m=>{assert.equal(locked,false);alerts.push(m);}})}
  };
  vm.createContext(ctx);vm.runInContext(source,ctx);
  return {run:()=>ctx.inviaCodaEmailDiTest(),rows,sent,logs,alerts,locked:()=>locked};
}
test('bounded queue resumes and sends exclusively to private recipient',()=>{
  const s=setup({count:30});s.run();assert.equal(s.sent.length,25);assert.equal(s.rows[25].stato,'PREVIEW');s.run();assert.equal(s.sent.length,30);s.run();assert.equal(s.sent.length,30);assert.equal(s.logs.length,30);assert.ok(s.sent.every(m=>m.to==='private@example.invalid'));
});
test('quota exhaustion leaves unsent rows available',()=>{const s=setup({quota:1});s.run();assert.equal(s.sent.length,1);assert.equal(s.rows[1].stato,'PREVIEW');s.run();assert.equal(s.sent.length,1);});
test('busy lock cannot touch queue or release another invocation lock',()=>{const s=setup({busy:true});assert.throws(s.run,/occupato/);assert.equal(s.sent.length,0);assert.ok(s.rows.every(r=>r.stato==='PREVIEW'));});
test('ambiguous send and post-send write failure cannot cause automatic replay',()=>{
  for(const failure of ['send','state']) {const s=setup({count:1,failure});assert.throws(s.run,/TEST_IN_CORSO/);assert.equal(s.locked(),false);assert.equal(s.rows[0].stato,'TEST_IN_CORSO');s.run();assert.equal(s.sent.length,1);assert.match(s.alerts[0],/verifica la consegna/);}
});
test('failed intent flush prevents sending',()=>{const s=setup({failure:'flush'});assert.throws(s.run);assert.equal(s.sent.length,0);assert.equal(s.locked(),false);});
test('audit failure preserves completed delivery',()=>{const s=setup({count:1,failure:'log'});assert.throws(s.run,/audit/);assert.equal(s.rows[0].stato,'TEST_INVIATA');s.run();assert.equal(s.sent.length,1);});
test('elapsed time stops the next send with pending rows intact',()=>{const s=setup({elapsedPerSend:240000});s.run();assert.equal(s.sent.length,1);assert.equal(s.rows[1].stato,'PREVIEW');});
test('invalid JSON does not mark or send the message',()=>{const s=setup();s.rows[0].contenuto_json='{';assert.throws(s.run);assert.equal(s.sent.length,0);assert.equal(s.rows[0].stato,'PREVIEW');assert.equal(s.locked(),false);});
