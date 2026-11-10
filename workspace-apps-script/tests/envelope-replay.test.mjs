import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createHash,createHmac} from 'node:crypto';
import vm from 'node:vm';
const source=(await Promise.all(['Core.gs','WebApp.gs'].map(f=>readFile(new URL('../src/'+f,import.meta.url),'utf8')))).join('\n');
test('buste grandi, replay persistente oltre 200 richieste e timestamp futuri',()=>{
 let now=1800000000000;const properties=new Map(),cache=new Map(),secret='synthetic-test-secret';
 const c={Date:{now:()=>now},ottieniSegretoScript_:()=>secret,LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock(){}})},PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k)||null,setProperty:(k,v)=>properties.set(k,v)})},CacheService:{getScriptCache:()=>({get:k=>cache.get(k),put:(k,v)=>cache.set(k,v)})},Utilities:{DigestAlgorithm:{SHA_256:'sha256'},Charset:{UTF_8:'utf8'},computeDigest:(a,s)=>[...createHash(a).update(s).digest()],computeHmacSha256Signature:(s,k)=>[...createHmac('sha256',k).update(s).digest()],base64EncodeWebSafe:v=>Buffer.from(v).toString('base64url')}};
 vm.createContext(c);vm.runInContext(source,c);
 const envelope=(nonce,timestamp=now,payload={text:'x'})=>{const text=JSON.stringify(payload),hash=createHash('sha256').update(text).digest('hex');return {protocollo:2,timestamp,nonce:nonce.padStart(32,'0'),action:'PING',payload_firmato:text,payload_hash:hash,signature:createHmac('sha256',secret).update(timestamp+'\n'+nonce.padStart(32,'0')+'\nPING\n'+hash).digest('base64url')};};
 const first=envelope('future',now+119000,{text:'x'.repeat(150000)});
 assert.equal(c.verificaBusta_(first).ok,true);
 for(let i=0;i<600;i++)assert.equal(c.verificaBusta_(envelope(String(i))).ok,true);
 cache.clear();now+=130000;
 assert.equal(c.verificaBusta_(first).error,'REPLAYED_REQUEST');
 assert.ok([...properties.values()].every(s=>s.length<=8000));
 assert.equal(c.verificaBusta_(envelope('oversize',now,{text:'x'.repeat(2000001)})).error,'PAYLOAD_TOO_LARGE');
});

test('la pausa interattiva scatta solo dopo lock e controllo replay',()=>{
 let now=1800000000000,busy=false;const properties=new Map(),cache=new Map(),secret='synthetic-test-secret';
 const makeContext=()=>{
  const c={Date:{now:()=>now},ottieniSegretoScript_:()=>secret,LockService:{getScriptLock:()=>({tryLock:()=>!busy,releaseLock(){}})},PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k)||null,setProperty:(k,v)=>properties.set(k,v)})},CacheService:{getScriptCache:()=>({get:k=>cache.get(k),put:(k,v)=>cache.set(k,v)})},Utilities:{DigestAlgorithm:{SHA_256:'sha256'},Charset:{UTF_8:'utf8'},computeDigest:(a,s)=>[...createHash(a).update(s).digest()],computeHmacSha256Signature:(s,k)=>[...createHmac('sha256',k).update(s).digest()],base64EncodeWebSafe:v=>Buffer.from(v).toString('base64url')}};
  vm.createContext(c);vm.runInContext(source,c);return c;
 };
 const c=makeContext();
 const envelope=(nonce,payload={background:false})=>{const action='PROIETTA_EVENTO',text=JSON.stringify(payload),hash=createHash('sha256').update(text).digest('hex');return {protocollo:2,timestamp:now,nonce:nonce.padStart(32,'0'),action,payload_firmato:text,payload_hash:hash,signature:createHmac('sha256',secret).update(now+'\n'+nonce.padStart(32,'0')+'\n'+action+'\n'+hash).digest('base64url')};};
 const valid=envelope('interactive');assert.equal(c.verificaBusta_(valid).ok,true);
 const pause=properties.get('MI_INTERACTIVE_OPEN_UNTIL');assert.equal(Number(pause),now+90000);
 now+=1000;assert.equal(c.verificaBusta_(valid).error,'REPLAYED_REQUEST');assert.equal(properties.get('MI_INTERACTIVE_OPEN_UNTIL'),pause);
 properties.delete('MI_INTERACTIVE_OPEN_UNTIL');busy=true;const blocked=makeContext();assert.equal(blocked.verificaBusta_(envelope('lockbusy')).error,'WORKSPACE_BUSY');assert.equal(properties.has('MI_INTERACTIVE_OPEN_UNTIL'),false);
});
