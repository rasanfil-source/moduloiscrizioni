import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../src/FogliOperativi.gs',import.meta.url),'utf8');
test('interrupted formatting resumes the registered file; direct verification never treats access errors as missing',()=>{
 const props=new Map(),links=[];let creates=0,fail=true,denied=false;
 const sheet={setName(){}};
 const file={getId:()=> 'sheet',getUrl:()=> 'https://docs.google.com/spreadsheets/d/sheet/edit',getSheets:()=>[sheet],getSheetByName:()=>sheet};
 const save=v=>{links[0]={id_evento:v[0],id_foglio:v[2],url_foglio:v[3],_row:2};};
 const registry={appendRow:save,getRange:()=>({setValues:v=>save(v[0])})};
 const c=vm.createContext({MI_SHEETS:{EVENT_WORKSPACES:'links',EVENTS:'events'},
 normalizzaTesto_:String,neutralizzaFormula_:v=>v,decodificaElenco_:()=>[],
 PropertiesService:{getScriptProperties:()=>({getProperty:k=>props.get(k),setProperty:(k,v)=>props.set(k,v),deleteProperty:k=>props.delete(k)})},
 ottieniSchedaObbligatoria_:k=>k==='links'?registry:[],convertiRigheInOggetti_:s=>s===registry?links:s,
 DriveApp:{Access:{ANYONE_WITH_LINK:'LINK'},Permission:{VIEW:'VIEW'},getFileById:()=>{if(denied)throw Error('denied');return {isTrashed:()=>false,getSharingAccess:()=> 'LINK',getSharingPermission:()=> 'VIEW'};}},
 SpreadsheetApp:{create:()=>{creates++;return file;},openById:()=>file,flush(){}},
 applicaSchemaColonneEvento_(){},aggiungiColonneServizi_(){},aggiungiControllo_(){},Session:{getActiveUser:()=>({getEmail:()=>''})}});
 vm.runInContext(source,c);
 c.generaVistaOperativaIniziale_=c.generaVistaOperativaEvento_=()=>({evento:{titolo:'Test'},colonne:[],righe:[]});
 c.spostaFoglioAccantoAlDatabase_=()=>'';c.configuraSchedeEconomicheEvento_=()=>{};
 c.scriviProiezioneEvento_=()=>{if(fail)throw Error('interrupted');};
 const run=()=>c.apriFoglioOperativoConLock_({id_evento:'42',titolo:'Test'});
 assert.throws(run,/interrupted/);assert.equal(creates,1);assert.equal(links[0].id_foglio,'sheet');
 fail=false;run();assert.equal(creates,1);assert.equal(props.size,0);
 assert.equal(run().creato,false);
 denied=true;assert.throws(run,/duplicato/);assert.equal(creates,1);
 c.abilitaLetturaFoglioEventoConLink_=()=>{};
 c.apriFoglioEventoFirmato_=()=>({book:{getId:()=> 'abcdefghijklmnopqrst',getUrl:()=> 'https://docs.google.com/spreadsheets/d/abcdefghijklmnopqrst/edit'}});
 assert.equal(c.verificaFoglioEventoDaWordPress_({id_evento:'42',id_foglio:'abcdefghijklmnopqrst',direct_projection:true}).esiste,true);
 c.apriFoglioEventoFirmato_=()=>{throw Error('permission denied');};
 assert.deepEqual(JSON.parse(JSON.stringify(c.verificaFoglioEventoDaWordPress_({id_evento:'42',id_foglio:'abcdefghijklmnopqrst',direct_projection:true}))),{ok:false,error:'SHEET_UNAVAILABLE',id_evento:'42'});
 c.apriFoglioEventoFirmato_=()=>{throw Error('EVENT_SHEET_MISSING');};
 assert.deepEqual(JSON.parse(JSON.stringify(c.verificaFoglioEventoDaWordPress_({id_evento:'42',id_foglio:'abcdefghijklmnopqrst',direct_projection:true}))),{ok:true,esiste:false,id_evento:'42'});
 assert.equal(c.verificaFoglioEventoDaWordPress_({id_evento:'42'}).error,'USE_DIRECT_PROJECTION');
});
