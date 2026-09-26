import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup({sender='info@parrocchiasanteugenio.it',quota=10,fail=false,aliases=[],aliasFailure=false}={}) {
 const sent=[],properties={MI_EMAIL_SENDER:'info@parrocchiasanteugenio.it',MI_EMAIL_TEST_RECIPIENT:'test@example.invalid'};
 const props={getProperty:key=>properties[key]||null,setProperty:(key,value)=>{properties[key]=value;},deleteProperty:key=>{delete properties[key];},getProperties:()=>({...properties})};
 const ctx={Session:{getEffectiveUser:()=>({getEmail:()=>sender})},PropertiesService:{getScriptProperties:()=>props},LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},MailApp:{getRemainingDailyQuota:()=>quota,sendEmail:o=>{sent.push(o);if(fail)throw Error('uncertain');}},console:{error:()=>{}}};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 ctx.GmailApp={getAliases:()=>{if(aliasFailure)throw Error('authorization');return aliases;},sendEmail:(to,subject,body,options)=>{sent.push({...options,to,subject,body,gmail:true});if(fail)throw Error('uncertain');}};
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

test('signed display name reaches MailApp in both modes',()=>{
 for(const mode of ['PROVA','OPERATIVO']) { const s=setup();assert.equal(s.send({mode,nome_mittente:'Gruppo Giovani — Sant’Eugenio'}).ok,true);assert.equal(s.sent[0].name,'Gruppo Giovani — Sant’Eugenio'); }
});

test('authorized alias supplies actual From, display name and independent replies',()=>{
 for(const mode of ['OPERATIVO','PROVA']) {
  const s=setup({aliases:['Sender@example.invalid']});
  const result=s.send({mode,indirizzo_mittente:'sender@example.invalid',nome_mittente:'Gruppo',reply_to:'contact@example.invalid'});
  assert.equal(result.ok,true);assert.equal(result.sender,'sender@example.invalid');
  assert.equal(s.sent[0].gmail,true);assert.equal(s.sent[0].from,'Sender@example.invalid');assert.equal(s.sent[0].name,'Gruppo');
  assert.equal(s.sent[0].replyTo,mode==='PROVA'?'test@example.invalid':'contact@example.invalid');
 }
});
test('unauthorized alias and missing Gmail authorization fail without fallback or intent',()=>{
 for(const aliasFailure of [false,true]) {
  const s=setup({aliasFailure});assert.equal(s.send({indirizzo_mittente:'sender@example.invalid'}).error,aliasFailure?'EMAIL_ALIAS_CHECK_FAILED':'EMAIL_ALIAS_NOT_AUTHORIZED');
  assert.equal(s.sent.length,0);assert.equal(s.properties['MI_EMAIL_DELIVERY_'+'a'.repeat(64)],undefined);
 }
});
test('invalid From is rejected before sending',()=>{
 for(const indirizzo_mittente of ['invalid','a@example.invalid,b@example.invalid','a@example.invalid\r\nBcc: b@example.invalid']) {
  const s=setup();assert.equal(s.send({indirizzo_mittente}).error,'INVALID_SENDER_EMAIL');assert.equal(s.sent.length,0);
 }
});
test('alias sends retain accepted and uncertain replay protection',()=>{
 for(const fail of [false,true]) {
  const s=setup({aliases:['sender@example.invalid'],fail}),p={indirizzo_mittente:'sender@example.invalid'};
  const first=s.send(p),second=s.send(p);assert.equal(s.sent.length,1);
  if(fail){assert.equal(first.error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(second.error,'EMAIL_DELIVERY_UNCERTAIN');}
  else {assert.equal(first.ok,true);assert.equal(second.replayed,true);}
 }
});
test('parish and legacy payloads never depend on Gmail aliases',()=>{
 const s=setup({aliasFailure:true});assert.equal(s.send({indirizzo_mittente:'info@parrocchiasanteugenio.it'}).ok,true);assert.equal(s.sent[0].gmail,undefined);
});
test('old WordPress payload keeps the default display name',()=>{const s=setup();s.send();assert.equal(s.sent[0].name,'Parrocchia Sant’Eugenio');});
test('invalid display name is rejected before sending or recording intent',()=>{
 for(const nome_mittente of ['Gruppo\r\nBcc: x@example.invalid','a'.repeat(121),'Gruppo\0']) { const s=setup();assert.equal(s.send({nome_mittente}).error,'INVALID_SENDER_NAME');assert.equal(s.sent.length,0);assert.equal(s.properties['MI_EMAIL_DELIVERY_'+ 'a'.repeat(64)],undefined); }
});
