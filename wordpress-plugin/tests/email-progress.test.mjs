import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const script=fs.readFileSync(new URL('../modulo-iscrizioni/assets/portal-communications.js',import.meta.url),'utf8');
async function run(state,{networkFailure=false}={}){
 const panel={dataset:{job:'a'.repeat(64),url:'/ajax',nonce:'nonce'},textContent:''},timers=[],requests=[];
 const c={
  document:{querySelectorAll:s=>s==='[data-mi-email-progress]'?[panel]:[],querySelector:()=>null},
  FormData:class{set(k,v){this[k]=v;}},AbortController:class{signal={};abort(){}},
  setTimeout:(f,ms)=>{timers.push({f,ms});return timers.length;},clearTimeout(){},
  fetch:async(url,options)=>{requests.push({url,options});if(networkFailure)throw Error('offline');return {ok:true,json:async()=>({success:true,data:state})};}
 };
 vm.createContext(c);vm.runInContext(script,c);
 for(let i=0;i<10;i++)await Promise.resolve();
 return {panel,timers,requests};
}
test('198 campaign progress reports Google acceptance and stops when complete',async()=>{
 const s=await run({prepared:198,total:198,sent:198,pending:0,failed:0,done:true,quota_remaining:1302});
 assert.match(s.panel.textContent,/198\/198/);assert.match(s.panel.textContent,/Accettate da Google: 198/);
 assert.match(s.panel.textContent,/Elaborazione conclusa/);assert.equal(s.requests[0].options.body.action,'mi_email_progress');
 assert.equal(s.timers.filter(t=>t.ms===3000).length,0);
});
test('quota pause and terminal error remain visible instead of claiming delivery',async()=>{
 const s=await run({prepared:198,total:198,sent:17,pending:180,failed:1,done:false,retry_after:900,error:'Esito incerto',quota_remaining:0});
 assert.match(s.panel.textContent,/Errori: 1/);assert.match(s.panel.textContent,/15 minuti/);
 assert.match(s.panel.textContent,/Esito incerto/);assert.equal(s.timers.filter(t=>t.ms===15000).length,1);
});
test('failed status polling preserves saved-request guidance',async()=>{
 const s=await run({}, {networkFailure:true});assert.match(s.panel.textContent,/richiesta resta salvata/);
 assert.equal(s.timers.filter(t=>t.ms===10000).length,2);
});
