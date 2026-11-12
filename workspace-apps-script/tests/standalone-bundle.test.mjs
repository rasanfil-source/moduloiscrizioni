import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {standaloneSource,entryPoints} from '../../tools/workspace-standalone.mjs';
import {fixture,view} from './helpers/projection-sheet.mjs';

const config=fs.readFileSync(new URL('../src/Config.gs',import.meta.url),'utf8');
const setup=fs.readFileSync(new URL('../src/Setup.gs',import.meta.url),'utf8');
const builder=fs.readFileSync(new URL('../../tools/prepara-codice-workspace.mjs',import.meta.url),'utf8');
const webApp=fs.readFileSync(new URL('../src/WebApp.gs',import.meta.url),'utf8');
const groups=fs.readFileSync(new URL('../src/Gruppi.gs',import.meta.url),'utf8');

test('schema capability is advertised only by the autonomous project',()=>{
 for(const standalone of [false,true]){
  const context=vm.createContext({});
  vm.runInContext(`${standalone?'const MI_STANDALONE_MODE=true;':''}\n${config}\n${webApp}`,context);
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

test('the generated standalone source keeps operational entry points and removes retired interfaces',async()=>{
 const directory=new URL('../src/',import.meta.url);
 const names=fs.readdirSync(directory).filter(name=>name.endsWith('.gs')).sort();
 const bundle=await standaloneSource(names.map(name=>fs.readFileSync(new URL(name,directory),'utf8')).join('\n'));
 assert.doesNotThrow(()=>new vm.Script(bundle));
 const context=vm.createContext({});vm.runInContext(bundle,context);
 for(const name of entryPoints)assert.equal(typeof context[name],'function',name);
 for(const name of ['sincronizzaGruppiConWordPress','configuraCartellaDiLavoro','registraIscrizioneCentrale_','apriConfigurazioneElencoOperativo','inviaCodaEmailDiTest'])assert.equal(context[name],undefined,name);
 context.creaRispostaJson_=value=>value;context.verificaBusta_=()=>({ok:true});
 assert.equal(context.doGet({}).standalone,true);
 const state=context.doPost({postData:{contents:JSON.stringify({action:'STATO_SCHEMA',payload:{}})}});
 assert.equal(state.direct_projection,true);assert.equal(state.central_workbook,false);
 const f=fixture(),c=f.context(bundle),v=view();
 c.scriviProiezioneEvento_(f.sheet,v);f.calls.length=0;
 c.scriviProiezioneEvento_(f.sheet,v);assert.equal(f.calls.length,0);
 f.sheet.cells[1][1]='Nome modificato';
 assert.equal(c.modificheCorrentiFoglio_(f.sheet).changes.length,1);
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
 assert.match(builder,/await standaloneSource\(codice\)/);
});

test('a malformed legacy group image no longer blocks group listing or synchronization',()=>{
 const rows=[
  {id_gruppo:'0',nome:'Gruppo errato',slug:'gruppo-errato',stato:'ATTIVO',logo_url:'http://bad.example/logo.png',immagine_url:'' ,_row:2},
  {id_gruppo:'0',nome:'Gruppo valido',slug:'gruppo-valido',stato:'ATTIVO',logo_url:'https://ok.example/logo.png',immagine_url:'',_row:3},
 ];
 const writes=[];const requests=[];
 const context=vm.createContext({
  MI_SHEETS:{GROUPS:'Gruppi'},
  convertiRigheInOggetti_:()=>rows,
  ottieniSchedaObbligatoria_:()=>({getRange:(row,column)=>({setValue:value=>writes.push([row,column,value])})}),
  normalizzaTesto_:(value,max)=>String(value??'').trim().slice(0,max),
  inviaComandoWordPress_:(action,payload)=>{requests.push(payload);return {group_id:payload.slug==='gruppo-errato'?4:9};},
  aggiungiControllo_:()=>{},
  Session:{getActiveUser:()=>({getEmail:()=> 'segreteria@example.invalid'})},
 });
 vm.runInContext(groups,context);
 const listed=context.elencaGruppi();
 assert.equal(listed.length,2);
 assert.equal(listed[0].logo_url,'');
 assert.match(listed[0].avviso_asset,/non HTTPS/);
 const synced=context.sincronizzaGruppiConWordPress();
 assert.equal(synced.updated,2);
 assert.equal(synced.warning,true);
 assert.equal(requests.length,2);
 assert.equal(requests[0].logo_url,'');
 assert.equal(requests[1].logo_url,'https://ok.example/logo.png');
 assert.deepEqual(writes,[[2,1,'4'],[3,1,'9']]);
});
