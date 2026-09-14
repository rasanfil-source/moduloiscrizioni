import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup({sender='info@parrocchiasanteugenio.it',quota=10,fail=false}={}) {
 const rows=[['delivery_key','stato','data_utc']],sent=[];
 const sheet={getLastRow:()=>rows.length,appendRow:r=>rows.push(r),getRange:(r,c,n)=>({getValues:()=>rows.slice(r-1,r-1+n).map(x=>x.slice(c-1,c+1)),setValue:v=>{rows[r-1][c-1]=v;}})};
 const ctx={Session:{getEffectiveUser:()=>({getEmail:()=>sender})},PropertiesService:{getScriptProperties:()=>({getProperty:key=> key === 'MI_EMAIL_SENDER' ? 'info@parrocchiasanteugenio.it' : 'test@example.org'})},LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},ottieniFoglioDiLavoroAssociato_:()=>({getSheetByName:()=>sheet}),SpreadsheetApp:{flush:()=>{}},MailApp:{getRemainingDailyQuota:()=>quota,sendEmail:o=>{sent.push(o);if(fail)throw Error('uncertain');}},console:{error:()=>{}}};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const payload={delivery_key:'a'.repeat(64),mode:'PROVA',destinatario:'test@example.org',oggetto:'[PROVA] Santiago',html:'<p>Conferma</p>',testo:'Conferma'};
 return {send:p=>ctx.inviaEmailConfermaDaWordPress_({...payload,...p}),sent,rows};
}
test('same accepted delivery is never sent twice',()=>{const s=setup();assert.equal(s.send().ok,true);assert.equal(s.send().replayed,true);assert.equal(s.sent.length,1);});
test('wrong deployer cannot impersonate info',()=>{const s=setup({sender:'other@example.org'});assert.equal(s.send().error,'EMAIL_SENDER_NOT_AUTHORIZED');assert.equal(s.sent.length,0);});
test('test destination cannot escape configured mailbox',()=>{const s=setup();assert.equal(s.send({destinatario:'other@example.org'}).error,'TEST_RECIPIENT_MISMATCH');assert.equal(s.sent.length,0);});
test('uncertain send is not repeated',()=>{const s=setup({fail:true});assert.equal(s.send().error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.send().error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.sent.length,1);});
test('quota failure creates no delivery intent',()=>{const s=setup({quota:0});assert.equal(s.send().error,'EMAIL_QUOTA_EXCEEDED');assert.equal(s.rows.length,1);});
test('operational mail uses real recipient and institutional reply',()=>{const s=setup();assert.equal(s.send({mode:'OPERATIVO',destinatario:'person@example.org'}).ok,true);assert.equal(s.sent[0].to,'person@example.org');assert.equal(s.sent[0].replyTo,'info@parrocchiasanteugenio.it');});
