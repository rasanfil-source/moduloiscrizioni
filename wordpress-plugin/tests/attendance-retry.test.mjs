import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {randomUUID} from 'node:crypto';
import vm from 'node:vm';
const source=await readFile(new URL('../modulo-iscrizioni/assets/portal-management.js',import.meta.url),'utf8');
const requestIdSource=source.match(/^  const requestId = .*;$/m)[0];
const requestSource=requestIdSource+'\n'+source.slice(source.indexOf('    const attendanceRetries='),source.indexOf('    if(sheetButton)sheetButton.addEventListener'));
test('attendance network retries retain the request key; later edits invalidate stale keys',async()=>{
 const sent=[];let fail=true;
 const c=vm.createContext({event:42,order:'',root:{dataset:{endpoint:'/ajax',nonce:'test'},querySelector:()=>null},crypto:{randomUUID},AbortController,URLSearchParams,setTimeout,clearTimeout,document:{dispatchEvent(){}},Event,
  fetch:async(url,options)=>{sent.push(Object.fromEntries(options.body));if(fail)throw Error('synthetic network failure');return {ok:true,json:async()=>({success:true,data:{saved:true}})};}
 });
 vm.runInContext(requestSource,c);
 const present={data:JSON.stringify([{id:1,attendance:'PRESENT'}])};
 const absent={data:JSON.stringify([{id:1,attendance:'ABSENT'}])};
 await assert.rejects(c.request('attendance_bulk',present),/synthetic network failure/);
 await assert.rejects(c.request('attendance_bulk',present),/synthetic network failure/);
 assert.equal(sent[0].request_id,sent[1].request_id);
 fail=false;await c.request('attendance_bulk',absent);await c.request('attendance_bulk',present);
 assert.notEqual(sent[0].request_id,sent[3].request_id);
});
