import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const full=fs.readFileSync(new URL('../modulo-iscrizioni/assets/portal.js',import.meta.url),'utf8');
const source=full.slice(full.indexOf('function miSetupDeletion('),full.indexOf('// Event selection'));
function setup(fetch,automatic=false){
 const button={},status={setAttribute(){}},timers=new Map();let handler,n=0,redirect='';
 const form={dataset:{miDeleteAjax:'/ajax'},querySelector:()=>button,after(){},setAttribute(){},removeAttribute(){},addEventListener(type,fn){handler=fn;},hasAttribute:()=>automatic};
 const c=vm.createContext({document:{querySelector:()=>form,createElement:()=>status},FormData:class{},fetch,setTimeout:fn=>{timers.set(++n,fn);return n;},clearTimeout:id=>timers.delete(id),location:{assign:url=>{redirect=url;}}});vm.runInContext(source,c);
 return {button,status,timers,submit:()=>handler({preventDefault(){}}),redirect:()=>redirect};
}
const flush=()=>new Promise(resolve=>setImmediate(resolve));
test('deletion ignores double submit and redirects only on completion',async()=>{
 let resolve,calls=0;const x=setup(()=>{calls++;return new Promise(r=>resolve=r);});
 x.submit();x.submit();assert.equal(calls,1);assert.equal(x.button.disabled,true);
 resolve({ok:true,json:async()=>({success:true,data:{complete:true,url:'/done'}})});await flush();
 assert.equal(x.redirect(),'/done');assert.equal(x.timers.size,0);
});
test('partial result schedules one next step without navigation',async()=>{
 const x=setup(async()=>({ok:true,json:async()=>({success:true,data:{complete:false,message:'Next'}})}));
 x.submit();await flush();assert.equal(x.redirect(),'');assert.equal(x.timers.size,1);assert.equal(x.status.textContent,'Next');
});
test('network or application error stops automatic retries and restores button',async()=>{
 for(const fetch of [async()=>{throw Error('Network');},async()=>({ok:true,json:async()=>({success:false,data:{message:'Denied'}})})]){
 const x=setup(fetch);x.submit();await flush();assert.equal(x.timers.size,0);assert.equal(x.button.disabled,false);assert.match(x.status.textContent,/Premi Riprendi/);
 }
});
