import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup({failAt='',archiveFailure=false}={}){
 const sent=[],properties={MI_EMAIL_SENDER:'sender@example.invalid',MI_EMAIL_TEST_RECIPIENT:'test@example.invalid'},rows=[];
 const calls={identity:0,aliases:0,locks:0,created:0};
 const props={getProperty:k=>properties[k]||null,getProperties:()=>({...properties}),deleteProperty:k=>{delete properties[k]},setProperty:(k,v)=>{if(failAt&&k.startsWith('MI_EMAIL_DELIVERY_')&&v.startsWith(failAt))throw Error('storage');properties[k]=v}};
 const sheet={setName(){},getLastRow:()=>rows.length,getRange:(row,col,n=1)=>({setValues(values){if(archiveFailure&&values.length>1)throw Error('archive');values.forEach((v,i)=>rows[row-1+i]=v)},getValue:()=>rows[row-1]?.[col-1],createTextFinder:key=>({matchEntireCell(){return this},findAll:()=>rows.slice(row-1,row-1+n).flatMap((r,i)=>r[0]===key?[{getRow:()=>row+i}]:[])})})};
 const book={getId:()=> 'synthetic-ledger',getSheets:()=>[sheet],getSheetByName:()=>sheet};
 const ctx={console:{error(){}},Session:{getEffectiveUser:()=>({getEmail:()=>{calls.identity++;return 'sender@example.invalid'}})},PropertiesService:{getScriptProperties:()=>props},LockService:{getScriptLock:()=>({tryLock:()=>{calls.locks++;return true},releaseLock(){}})},MailApp:{getRemainingDailyQuota:()=>1500,sendEmail:p=>sent.push(p)},GmailApp:{getAliases:()=>{calls.aliases++;return ['alias@example.invalid']},sendEmail:(to,subject,body,p)=>sent.push({...p,to})},SpreadsheetApp:{create:()=>{calls.created++;return book},openById:()=>book,flush(){}},normalizzaTesto_:(s,n)=>String(s||'').slice(0,n)};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 const message=(n=1,extra={})=>({delivery_key:n.toString(16).padStart(64,'0'),mode:'OPERATIVO',destinatario:'person@example.invalid',reply_to:'group@example.invalid',oggetto:'Audit',testo:'Testo',html:'<p>Testo</p>',...extra});
 const fill=()=>{for(let i=1;i<=1700;i++)properties['MI_EMAIL_DELIVERY_'+message(i).delivery_key]='ACCEPTED|'+Date.now()};
 return {ctx,sent,properties,rows,calls,message,fill};
}
test('storage failure before send is retryable and consumes no delivery',()=>{
 const s=setup({failAt:'SENDING'}),m=s.message();const r=s.ctx.inviaEmailLottoDaWordPress_({messages:[m]});
 assert.equal(r.results[m.delivery_key].error,'EMAIL_STORAGE_UNAVAILABLE');assert.equal(s.sent.length,0);
});
test('storage failure after send remains uncertain and cannot be retried blindly',()=>{
 const s=setup({failAt:'ACCEPTED'}),m=s.message();assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(m).error,'EMAIL_DELIVERY_UNCERTAIN');
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(m).error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.sent.length,1);
});
test('large receipt store archives before deletion and accepted deliveries still replay',()=>{
 const s=setup();s.fill();assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message(5000)).ok,true);
 assert.equal(s.calls.created,1);assert.ok(s.rows.length>=1701);assert.equal(s.properties['MI_EMAIL_DELIVERY_'+s.message().delivery_key],undefined);
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message()).replayed,true);assert.equal(s.sent.length,1);
});
test('archive failure preserves receipts and never starts a delivery',()=>{
 const s=setup({archiveFailure:true});s.fill();assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message(5000)).error,'EMAIL_STORAGE_UNAVAILABLE');
 assert.ok(s.properties['MI_EMAIL_DELIVERY_'+s.message().delivery_key]);assert.equal(s.sent.length,0);
});
test('uncertain archived intent stays blocked',()=>{
 const s=setup();s.fill();s.properties['MI_EMAIL_DELIVERY_'+s.message().delivery_key]='SENDING|'+Date.now();
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message()).error,'EMAIL_DELIVERY_UNCERTAIN');assert.equal(s.sent.length,0);
});
test('batch caches identity and authorized aliases while retaining per-message lock',()=>{
 const s=setup(),messages=Array.from({length:10},(_,i)=>s.message(i+1,{indirizzo_mittente:'alias@example.invalid'}));
 assert.equal(Object.values(s.ctx.inviaEmailLottoDaWordPress_({messages}).results).every(v=>v.ok),true);
 assert.equal(s.calls.identity,1);assert.equal(s.calls.aliases,1);assert.equal(s.calls.locks,10);
});
test('recipient validation rejects separators and excessive lengths before intent',()=>{
 for(const destinatario of ['a,b@example.invalid','a;<b>@example.invalid','a'.repeat(255)+'@example.invalid']){
  const s=setup();assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message(1,{destinatario})).error,'INVALID_EMAIL_PAYLOAD');assert.equal(s.sent.length,0);
 }
});
test('settings test validates the same sender alias as actual transport',()=>{
 const s=setup(),m=s.message(1,{destinatario:'test@example.invalid',indirizzo_mittente:'unauthorized@example.invalid'});
 assert.equal(s.ctx.inviaEmailProvaDaWordPress_(m).error,'EMAIL_ALIAS_NOT_AUTHORIZED');assert.equal(s.sent.length,0);
});
