import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup(quota=1500){
 const properties={MI_EMAIL_SENDER:'sender@example.invalid',MI_EMAIL_TEST_RECIPIENT:'test@example.invalid'},sent=[],ctx={
  Session:{getEffectiveUser:()=>({getEmail:()=>properties.MI_EMAIL_SENDER})},
  PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties[k]||null,getProperties:()=>({...properties}),setProperty:(k,v)=>{properties[k]=v;},deleteProperty:k=>{delete properties[k];}})},
  LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock(){}})},
  MailApp:{getRemainingDailyQuota:()=>quota,sendEmail:p=>{sent.push(p);quota--;}},
  console:{error(){}},Date
 };
 vm.createContext(ctx);vm.runInContext(source,ctx);
 return {ctx,sent,properties,batch:messages=>ctx.inviaEmailLottoDaWordPress_({messages}),quota:v=>{quota=v;}};
}
const message=i=>({delivery_key:i.toString(16).padStart(64,'0'),mode:'OPERATIVO',destinatario:'person'+i+'@example.invalid',oggetto:'Evento',testo:'Ciao Persona '+i,html:'<p>Ciao Persona '+i+'</p>',reply_to:'group@example.invalid'});
test('198 individualized deliveries use 20 batches and survive replay without duplicates',()=>{
 const s=setup();let batches=0;
 for(let i=1;i<=198;i+=10){const messages=Array.from({length:Math.min(10,199-i)},(_,n)=>message(i+n)),r=s.batch(messages);batches++;
  assert.equal(r.ok,true);assert.equal(Object.values(r.results).every(x=>x.ok),true);
  s.batch(messages); // response was lost: the same batch is retried
 }
 assert.equal(batches,20);assert.equal(s.sent.length,198);
 assert.equal(new Set(s.sent.map(p=>p.to)).size,198);
 for(const p of s.sent){assert.equal(p.cc,undefined);assert.equal(p.bcc,undefined);assert.match(p.htmlBody,/Ciao Persona/);}
});
test('quota is exhausted partway without recording intent for remaining messages',()=>{
 const s=setup(3),messages=Array.from({length:10},(_,i)=>message(i+1));
 const first=s.batch(messages);assert.equal(s.sent.length,3);assert.equal(first.quota_remaining,0);
 for(const p of messages.slice(3)){assert.equal(first.results[p.delivery_key].error,'EMAIL_QUOTA_EXCEEDED');assert.equal(s.properties['MI_EMAIL_DELIVERY_'+p.delivery_key],undefined);}
 s.quota(100);s.batch(messages);assert.equal(s.sent.length,10);
});
test('busy worker preserves all delivery keys and test destinations remain isolated',()=>{
 const s=setup();s.ctx.LockService.getScriptLock=()=>({tryLock:()=>false,releaseLock(){}});
 const r=s.batch([message(1),message(2)]);assert.equal(s.sent.length,0);
 assert.equal(r.results[message(2).delivery_key].error,'EMAIL_BUSY');
 s.ctx.LockService.getScriptLock=()=>({tryLock:()=>true,releaseLock(){}});
 const p={...message(3),mode:'PROVA_PUNTUALE',test_only:true,destinatario:'chosen@example.invalid'};
 s.batch([p]);assert.equal(s.sent[0].to,'chosen@example.invalid');
 const invalid=s.batch([{...message(4),mode:'PROVA'}]);assert.equal(invalid.results[message(4).delivery_key].error,'TEST_RECIPIENT_MISMATCH');
});
test('uncertain result cannot resend that recipient but does not discard other recipients',()=>{
 const s=setup();const send=s.ctx.MailApp.sendEmail;
 s.ctx.MailApp.sendEmail=p=>{send(p);if(p.to==='person2@example.invalid')throw Error('connection lost');};
 const messages=[message(1),message(2),message(3)];
 const first=s.batch(messages);assert.equal(first.results[message(2).delivery_key].error,'EMAIL_DELIVERY_UNCERTAIN');
 s.batch(messages);assert.equal(s.sent.length,3);
});
test('expired batch budget returns remaining messages as deferred',()=>{
 const s=setup();let clock=0;s.ctx.Date={now:()=>clock};
 const send=s.ctx.MailApp.sendEmail;s.ctx.MailApp.sendEmail=p=>{send(p);clock+=21000;};
 const r=s.batch([message(1),message(2)]);assert.equal(s.sent.length,1);assert.equal(r.results[message(2).delivery_key].error,'EMAIL_DEFERRED');
});
test('oversized and duplicate-key batches are rejected before any delivery',()=>{
 const s=setup();for(const messages of [[],Array.from({length:11},(_,i)=>message(i)),[message(1),message(1)]])assert.equal(s.batch(messages).error,'INVALID_EMAIL_PAYLOAD');
 assert.equal(s.sent.length,0);
});
