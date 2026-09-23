import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';
const directory=fileURLToPath(new URL('../../src/',import.meta.url));
const files=['Core.gs','Segreteria.gs','DomandeEvento.gs','SincronizzazioneManuale.gs','ProiezioneDiretta.gs'];
export function projectionFixture(count){
 const options=Array.from({length:12},(_,i)=>({code:'service_'+i,name:'Servizio '+i,scope:'PARTICIPANT',price_cents:100}));
 const schema={fields:[{key:'custom_test',label:'Domanda'},{key:'document_number',label:'Documento'}],options,pricing:'CALCULATED',room:true};
 const snapshot=JSON.stringify({event:{operational_profile:'VIAGGIO_COMPLESSO',participant_fields:[{key:'custom_test',label:'Domanda'}]},padding:'x'.repeat(2048)});
 const result={event:{id_evento:'42',titolo:'Sintetico',profilo_operativo:'VIAGGIO_COMPLESSO',schema_vista_json:JSON.stringify(schema),servizi_json:JSON.stringify(options),domande_json:'[]'},registrations:[],participants:[],payments:[]};
 for(let i=0;i<count;i++){
  const code='ORD-'+i;
  result.registrations.push({codice_ordine:code,id_evento:'42',workspace_revision:'1',stato:'CONFIRMED',totale_centesimi:1200,snapshot_json:snapshot});
  result.participants.push({codice_ordine:code,numero_partecipante:1,nome:'Persona',cognome:String(i),stato_partecipante:'ACTIVE',dati_aggiuntivi_json:JSON.stringify({custom_test:String(i),document_number:'SYNTHETIC-'+i}),opzioni_json:JSON.stringify(options.map(o=>({code:o.code,name:o.name,quantity:1}))),totale_centesimi:1200,versato_centesimi:0,saldo_centesimi:1200});
 }
 return result;
}
export function projectionContext(baselineDirectory){
 let parses=0;
 const context=vm.createContext({JSON:{parse:value=>{parses++;return JSON.parse(value);},stringify:JSON.stringify}});
 const source=files.map(file=>fs.readFileSync(baselineDirectory&&fs.existsSync(path.join(baselineDirectory,file))?path.join(baselineDirectory,file):path.join(directory,file),'utf8')).join('\n');
 vm.runInContext(source,context);
 return {context,reset:()=>{parses=0;},parses:()=>parses};
}
