import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source=fs.readFileSync(new URL('../modulo-iscrizioni/assets/portal-push-sw.js',import.meta.url),'utf8');
for(const expiredSession of [false,true])test('push renewal with '+(expiredSession?'expired':'active')+' portal session',async()=>{
 const handlers={},requests=[],subscription={toJSON:()=>({endpoint:'https://push.invalid/new',keys:{auth:'new'}})};
 let subscribed=0,pending;
 vm.runInNewContext(source,{URL,URLSearchParams,MI_PUSH_AJAX:'https://site.invalid/ajax',self:{addEventListener:(name,fn)=>handlers[name]=fn,registration:{pushManager:{subscribe:async options=>{assert.equal(options.userVisibleOnly,true);subscribed++;return subscription;}}}},fetch:async(url,options)=>{requests.push({url,options});return {ok:!expiredSession,json:async()=>({success:!expiredSession,data:{nonce:'synthetic'}})};}});
 handlers.pushsubscriptionchange({oldSubscription:{options:{userVisibleOnly:true}},waitUntil:p=>pending=p});await pending;
 assert.equal(subscribed,1);assert.equal(requests.length,expiredSession?1:2);
 assert.equal(requests[0].options.credentials,'same-origin');
 if(!expiredSession){assert.equal(requests[1].options.body.get('nonce'),'synthetic');assert.deepEqual(JSON.parse(requests[1].options.body.get('subscription')),subscription.toJSON());}
});
