import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup({sender='info@parrocchiasanteugenio.it',quota=10,fail=false}={}) {
 const sent=[],properties={MI_EMAIL_SENDER:'info@parrocchiasanteugenio.it',MI_EMAIL_TEST_RECIPIENT:'test@example.invalid'};
 const props={getProperty:key=>properties[key]||null,setProperty:(key,value)=>{properties[key]=value;},deleteProperty:key=>{delete properties[key];},getProperties:()=>({...properties})};
 const ctx={Session:{getEffectiveUser:()=>({getEmail:()=>sender})},PropertiesService:{getScriptProperties:()=>props},LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},MailApp:{getRemainingDailyQuota:()=>quota,sendEmail:o=>{sent.push(o);if(fail)throw Error('uncertain');}},console:{error:()=>{}}};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const payload={delivery_key:'a'.repeat(64),mode:'PROVA',destinatario:'test@example.invalid',oggetto:'[PROVA] Santiago',html:'<p>Conferma</p>',testo:'Conferma'};
 return {send:p=>ctx.inviaEmailConfermaDaWordPress_({...payload,...p}),sent,properties};
}
test('same accepted delivery is never sent twice',()=>{const s=setup();assert.equal(s.send().ok,true);assert.equal(s.send().replayed,true);assert.equal(s.sent.length,1);});
test('wrong deployer cannot impersonate info',()=>{const s=setup({sender:'other@example.invalid'});assert.equal(s.send().error,'EMAIL_SENDER_NOT_AUTHORIZED');assert.equal(s.sent.length,0);});
test('test destination cannot escape configured mailbox',()=>{const s=setup();assert.equal(s.send({destinatario:'other@example.invalid'}).error,'TEST_RECIPIENT_MISMATCH');assert.equal(s.sent.length,0);});
test('uncertain send is not repeated',()=>{const s=setup({fail:true});assert.equal(s.send().error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.send().error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.sent.length,1);});
test('quota failure creates no delivery intent',()=>{const s=setup({quota:0});assert.equal(s.send().error,'EMAIL_QUOTA_EXCEEDED');assert.equal(s.properties['MI_EMAIL_DELIVERY_'+ 'a'.repeat(64)],undefined);});
test('operational mail uses real recipient and institutional reply',()=>{const s=setup();assert.equal(s.send({mode:'OPERATIVO',destinatario:'person@example.invalid'}).ok,true);assert.equal(s.sent[0].to,'person@example.invalid');assert.equal(s.sent[0].replyTo,'info@parrocchiasanteugenio.it');});
test('operational replies go exclusively to signed group contact',()=>{const s=setup();assert.equal(s.send({mode:'OPERATIVO',destinatario:'person@example.invalid',reply_to:'Group@example.invalid'}).ok,true);assert.equal(s.sent[0].replyTo,'group@example.invalid');assert.equal(s.sent[0].cc,undefined);assert.equal(s.sent[0].bcc,undefined);});
test('invalid reply address is rejected before recording or sending',()=>{for(const reply_to of ['a@example.invalid\r\nBcc: b@example.invalid','a@example.invalid,b@example.invalid','invalid']){const s=setup();assert.equal(s.send({mode:'OPERATIVO',reply_to}).error,'INVALID_REPLY_TO');assert.equal(s.sent.length,0);assert.equal(s.properties['MI_EMAIL_DELIVERY_'+ 'a'.repeat(64)],undefined);}});
test('test mode never directs replies to the real group',()=>{const s=setup();assert.equal(s.send({reply_to:'group@example.invalid'}).ok,true);assert.equal(s.sent[0].replyTo,'test@example.invalid');});
