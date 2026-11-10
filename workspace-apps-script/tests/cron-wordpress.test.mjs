import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
const source=readFileSync(new URL('../src/CronWordPress.gs',import.meta.url),'utf8');
test('cron usa solo il sito configurato e segnala errori HTTP',()=>{
 let endpoint='https://example.org/wp-json/modulo-iscrizioni/v1/workspace/commands',status=200;
 const ctx=vm.createContext({PropertiesService:{getScriptProperties:()=>({getProperty:()=>endpoint})},UrlFetchApp:{fetch(url,options){assert.equal(url,'https://example.org/wp-cron.php');assert.equal(options.followRedirects,true);return {getResponseCode:()=>status};}}});vm.runInContext(source,ctx);
 assert.equal(ctx.avviaCronWordPress().ok,true);status=503;assert.throws(()=>ctx.avviaCronWordPress(),/HTTP 503/);
 endpoint='https://example.org/unrelated';assert.throws(()=>ctx.avviaCronWordPress(),/non valido/);
});

test('cron segue il redirect canonico e verifica lo stato finale',()=>{
 let finalStatus=200;
 const ctx=vm.createContext({PropertiesService:{getScriptProperties:()=>({getProperty:()=> 'https://example.org/wp-json/modulo-iscrizioni/v1/workspace/commands'})},UrlFetchApp:{fetch(url,options){return {getResponseCode:()=>options.followRedirects?finalStatus:301};}}});
 vm.runInContext(source,ctx);
 assert.equal(ctx.avviaCronWordPress().http_status,200);
 finalStatus=503;assert.throws(()=>ctx.avviaCronWordPress(),/HTTP 503/);
});
