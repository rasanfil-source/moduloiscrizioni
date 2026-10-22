import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const config=fs.readFileSync(new URL('../src/Config.gs',import.meta.url),'utf8');
const setup=fs.readFileSync(new URL('../src/Setup.gs',import.meta.url),'utf8');
const builder=fs.readFileSync(new URL('../../tools/prepara-codice-workspace.mjs',import.meta.url),'utf8');
const webApp=fs.readFileSync(new URL('../src/WebApp.gs',import.meta.url),'utf8');

test('schema capability is advertised only by the autonomous project',()=>{
 for(const standalone of [false,true]){
  const context=vm.createContext({});
  vm.runInContext(`${standalone?'const MI_STANDALONE_MODE=true;':''}\nconst MI_SCHEMA_VERSION='1.13.0';\n${webApp}`,context);
  context.verificaBusta_=()=>({ok:true});
  context.creaRispostaJson_=value=>value;
  const send=action=>context.doPost({postData:{contents:JSON.stringify({action,payload:{}})}});
  const state=send('STATO_SCHEMA');
  assert.equal(state.direct_projection,standalone);
  assert.equal(state.standalone,standalone);
  assert.equal(state.central_workbook,!standalone);
  assert.equal(state.projection_pull,standalone);
  if(!standalone)assert.equal(send('PROIETTA_EVENTO').error,'USE_STANDALONE_PROJECT');
 }
});

test('the complete standalone source parses with the retirement guard enabled',()=>{
 const directory=new URL('../src/',import.meta.url);
 const names=fs.readdirSync(directory).filter(name=>name.endsWith('.gs')).sort();
 const bundle='const MI_STANDALONE_MODE = true;\n'+names.map(name=>fs.readFileSync(new URL(name,directory),'utf8')).join('\n');
 assert.doesNotThrow(()=>new vm.Script(bundle));
});

test('the standalone bundle disables every central workbook accessor before Google is touched',()=>{
 let activeReads=0,propertyReads=0;
 const context=vm.createContext({
  SpreadsheetApp:{getActiveSpreadsheet:()=>{activeReads++;return {};},getUi:()=>{throw new Error('Legacy menu opened');}},
  PropertiesService:{getScriptProperties:()=>{propertyReads++;return {getProperty:()=>''};}},
 });
 vm.runInContext(`const MI_STANDALONE_MODE=true;\n${config}\n${setup}`,context);
 assert.throws(()=>context.ottieniFoglioDiLavoroAssociato_(),/CENTRAL_WORKBOOK_RETIRED/);
 context.onOpen();
 assert.equal(activeReads,0);
 assert.equal(propertyReads,0);
 assert.match(builder,/const MI_STANDALONE_MODE = true/);
 assert.match(builder,/const codiceProgetto = `const MI_STANDALONE_MODE = true/);
});
