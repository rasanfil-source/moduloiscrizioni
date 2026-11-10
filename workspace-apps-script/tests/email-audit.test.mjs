import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../src/Email.gs',import.meta.url),'utf8');
function setup({failAt='',archiveFailure=false}={}){
 const sent=[],properties={MI_EMAIL_SENDER:'sender@example.invalid',MI_EMAIL_TEST_RECIPIENT:'test@example.invalid'},rows=[];
 const calls={identity:0,aliases:0,locks:0,created:0,gridRows:1000,retention:0,deletions:[]};
 const deadlines={};
 const props={getProperty:k=>properties[k]||null,getProperties:()=>({...properties}),deleteProperty:k=>{delete properties[k]},setProperty:(k,v)=>{if(failAt&&k.startsWith('MI_EMAIL_DELIVERY_')&&v.startsWith(failAt))throw Error('storage');properties[k]=v}};
 const sheet={setName(){},getLastRow:()=>rows.length,getMaxRows:()=>calls.gridRows,insertRowsAfter:(after,n)=>{assert.equal(after,calls.gridRows);calls.gridRows+=n;},deleteRows:(start,n)=>{assert.ok(start>1&&start+n-1<=calls.gridRows);rows.splice(start-1,n);calls.gridRows-=n;calls.deletions.push([start,n]);},getRange:(row,col,n=1)=>{assert.ok(row+n-1<=calls.gridRows,'Range beyond grid');return {getValues:()=>rows.slice(row-1,row-1+n).map(r=>r.slice(col-1,col+1)),setValues(values){if(archiveFailure&&values.length>1)throw Error('archive');values.forEach((v,i)=>rows[row-1+i]=v)},getValue:()=>rows[row-1]?.[col-1],createTextFinder:key=>({matchEntireCell(){return this},findAll:()=>rows.slice(row-1,row-1+n).flatMap((r,i)=>r[0]===key?[{getRow:()=>row+i}]:[])})};}};
 const book={getId:()=> 'synthetic-ledger',getSheets:()=>[sheet],getSheetByName:()=>sheet};
 const ctx={console:{error(){}},Session:{getEffectiveUser:()=>({getEmail:()=>{calls.identity++;return 'sender@example.invalid'}})},PropertiesService:{getScriptProperties:()=>props},LockService:{getScriptLock:()=>({tryLock:()=>{calls.locks++;return true},releaseLock(){}})},MailApp:{getRemainingDailyQuota:()=>1500,sendEmail:p=>sent.push(p)},GmailApp:{getAliases:()=>{calls.aliases++;return ['alias@example.invalid']},sendEmail:(to,subject,body,p)=>sent.push({...p,to})},SpreadsheetApp:{create:()=>{calls.created++;return book},openById:()=>book,flush(){}},normalizzaTesto_:(s,n)=>String(s||'').slice(0,n)};
 vm.createContext(ctx);vm.runInContext(source,ctx);
 ctx.inviaComandoWordPress_=(action,payload)=>{assert.equal(action,'GET_EMAIL_RETENTION');calls.retention++;return {ok:true,deadlines:Object.fromEntries(payload.event_ids.filter(id=>deadlines[id]).map(id=>[id,deadlines[id]]))};};
 const message=(n=1,extra={})=>({delivery_key:n.toString(16).padStart(64,'0'),mode:'OPERATIVO',destinatario:'person@example.invalid',reply_to:'group@example.invalid',oggetto:'Audit',testo:'Testo',html:'<p>Testo</p>',...extra});
 const fill=()=>{for(let i=1;i<=1700;i++)properties['MI_EMAIL_DELIVERY_'+message(i).delivery_key]='ACCEPTED|'+Date.now()};
 return {ctx,sent,properties,rows,calls,message,fill,deadlines,props,sheet};
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
 assert.equal(s.calls.gridRows,1701);
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message()).replayed,true);assert.equal(s.sent.length,1);
});

test('receipts expire after the current event deadline, preserving future events, uncertainty, unknown dates and legacy receipts',()=>{
 const s=setup(),now=Date.now(),old=now-200*86400000,accepted='ACCEPTED|'+old;
 s.deadlines['42']=now-1;s.deadlines['43']=now+200*86400000;
 const records=[accepted+'|42','SENDING|'+old+'|42',accepted+'|43',accepted+'|99',accepted,'ACCEPTED|'+now+'|42',accepted+'|42'];
 s.properties.MI_EMAIL_LEDGER_SPREADSHEET_ID='synthetic-ledger';
 s.rows.push(['Chiave tecnica','Stato consegna']);
 records.forEach((value,i)=>{const key='MI_EMAIL_DELIVERY_'+s.message(i+1).delivery_key;s.rows.push([key,value]);s.properties[key]=value;});
 s.ctx.pulisciRicevuteEmail_(s.props,{});
 assert.equal(s.rows.length,6);assert.equal(s.calls.retention,1);
 for(const n of [1,7])assert.equal(s.properties['MI_EMAIL_DELIVERY_'+s.message(n).delivery_key],undefined);
 for(const n of [2,3,4,5,6])assert.ok(s.properties['MI_EMAIL_DELIVERY_'+s.message(n).delivery_key]);
 assert.deepEqual(s.calls.deletions,[[8,1],[2,1]]);
 s.ctx.pulisciRicevuteEmail_(s.props,{});assert.equal(s.calls.retention,1);
});

test('retention service failures postpone cleanup without blocking a new delivery',()=>{
 const s=setup(),key='MI_EMAIL_DELIVERY_'+s.message().delivery_key;
 s.properties[key]='ACCEPTED|'+(Date.now()-200*86400000)+'|42';
 s.ctx.inviaComandoWordPress_=()=>{throw Error('offline');};
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message(2,{event_id:'42'})).ok,true);
 assert.ok(s.properties[key]);assert.equal(s.sent.length,1);
});

test('a signed replay associates a legacy receipt with its event and keeps its original acceptance time',()=>{
 const s=setup(),key='MI_EMAIL_DELIVERY_'+s.message().delivery_key;
 const receipt='ACCEPTED|'+(Date.now()-100*86400000);s.properties[key]=receipt;
 assert.equal(s.ctx.inviaEmailConfermaDaWordPress_(s.message(1,{event_id:'42'})).replayed,true);
 assert.equal(s.properties[key],receipt+'|42');assert.equal(s.sent.length,0);
});

test('cleanup interrupted between deletions never loses an uncertain receipt and can resume',()=>{
 const s=setup(),old=Date.now()-200*86400000;s.deadlines['42']=Date.now()-1;
 s.properties.MI_EMAIL_LEDGER_SPREADSHEET_ID='synthetic-ledger';
 s.rows.push(['Chiave tecnica','Stato consegna']);
 for(const [n,state]of [[1,'ACCEPTED'],[2,'SENDING'],[3,'ACCEPTED']])s.rows.push(['MI_EMAIL_DELIVERY_'+s.message(n).delivery_key,state+'|'+old+'|42']);
 const remove=s.sheet.deleteRows;let first=true;s.sheet.deleteRows=(start,n)=>{remove(start,n);if(first){first=false;throw Error('interruption');}};
 assert.throws(()=>s.ctx.pulisciRicevuteEmail_(s.props,{}),/interruption/);
 assert.equal(s.properties.MI_EMAIL_LEDGER_CLEANED_AT,undefined);
 s.ctx.pulisciRicevuteEmail_(s.props,{});
 assert.equal(s.rows.length,2);assert.match(s.rows[1][1],/^SENDING/);
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
