import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {gzipSync,gunzipSync} from 'node:zlib';
import {createHash} from 'node:crypto';

const source=fs.readFileSync(new URL('../src/ProiezioneDiretta.gs',import.meta.url),'utf8');
const projection={
 event:{id_evento:'42',titolo:'Evento',profilo_operativo:'MINIMO',schema_vista_json:'{"fields":[],"options":[],"pricing":"ZERO"}',servizi_json:'[]',domande_json:'[]'},
 registrations:[{codice_ordine:'ORD-42',id_evento:'42',workspace_revision:'3',stato:'CONFIRMED'}],
 participants:[{codice_ordine:'ORD-42',numero_partecipante:1,stato_partecipante:'ACTIVE',dati_aggiuntivi_json:'{}'}],payments:[],rooms:[]
};

test('a gzip projection with no registrations decodes using a typed blob',()=>{
 const empty={...projection,registrations:[],participants:[],payments:[]};
 const json=JSON.stringify(empty), gzip=gzipSync(json), hash=createHash('sha256').update(json).digest('hex');
 const calls=[];
 const context=vm.createContext({Utilities:{
  base64Decode:value=>Array.from(Buffer.from(value,'base64')),
  newBlob:(bytes,type,name)=>{calls.push({type,name});return {bytes,type};},
  ungzip:blob=>{if(!blob.type)throw new Error('Blob object must have non-null content type for this operation.');return {getDataAsString:()=>gunzipSync(Buffer.from(blob.bytes)).toString('utf8')};},
  computeDigest:(_algorithm,value)=>Array.from(createHash('sha256').update(value).digest()),
  DigestAlgorithm:{SHA_256:'SHA_256'},Charset:{UTF_8:'UTF_8'}
 },confrontaInTempoCostante_:(a,b)=>a===b});
 vm.runInContext(source,context);
 const decoded=context.decodificaProiezioneDiretta_({projection_gzip:gzip.toString('base64'),projection_hash:hash});
 context.validaProiezioneDiretta_('42',decoded);
 assert.equal(calls[0].type,'application/gzip');
 assert.equal(calls[0].name,'event-projection.gz');
 assert.equal(decoded.registrations.length,0);
});

test('the direct projection rejects a participant without a canonical order',()=>{
 const context=vm.createContext({});vm.runInContext(source,context);
 assert.throws(()=>context.validaProiezioneDiretta_('42',{...projection,participants:[{...projection.participants[0],codice_ordine:'UNKNOWN'}]}),/INVALID_EVENT_PROJECTION/);
 assert.throws(()=>context.validaProiezioneDiretta_('43',projection),/INVALID_EVENT_PROJECTION/);
});

test('large projections are fetched only through the signed WordPress command and checked against the expected hash',()=>{
 const requests=[];
 const context=vm.createContext({inviaComandoWordPress_:(action,payload)=>{requests.push([action,payload]);return {ok:true,projection_hash:'b'.repeat(64),projection_gzip:'encoded'};}});
 vm.runInContext(source,context);
 context.decodificaProiezioneDiretta_=payload=>payload;
 const payload={event_id:'42',projection_pull:true,projection_hash:'b'.repeat(64),fingerprint:'a'.repeat(64)};
 const result=context.caricaProiezioneDiretta_(payload);
 assert.equal(result.projection_gzip,'encoded');
 assert.equal(requests.length,1);
 assert.equal(requests[0][0],'GET_EVENT_PROJECTION');
 assert.equal(requests[0][1].event_id,'42');
 assert.throws(()=>context.caricaProiezioneDiretta_({...payload,projection_hash:'c'.repeat(64)}),/INVALID_EVENT_PROJECTION_HASH/);
 assert.throws(()=>context.caricaProiezioneDiretta_({...payload,projection_gzip:'untrusted'}),/INVALID_EVENT_PROJECTION/);
});

test('an unchanged direct projection does not rewrite the event sheet',()=>{
 const values=new Map(), calls={write:0,payments:0,flush:0,view:0};
 const props={getProperty:key=>values.get(key)||null,setProperty:(key,value)=>values.set(key,value),deleteProperty:key=>values.delete(key)};
 const book={getId:()=> 'sheet-id',getUrl:()=> 'https://docs.google.com/spreadsheets/d/sheet-id/edit'};
 const context=vm.createContext({
  PropertiesService:{getScriptProperties:()=>props},
  LockService:{getScriptLock:()=>({tryLock:()=>true,releaseLock:()=>{}})},
  SpreadsheetApp:{flush:()=>{calls.flush++;}},
  abilitaLetturaFoglioEventoConLink_:()=>{},
  modificheCorrentiFoglio_:()=>({changes:[],errors:[]}),
  scriviProiezioneEvento_:()=>{calls.write++;return {aggiunte:1,manuali:0,conflitti:0};},
 });
 vm.runInContext(source,context);
 context.aggiornaPagamentiDaProiezione_=()=>{calls.payments++;};
 context.decodificaProiezioneDiretta_=()=>projection;
 context.apriFoglioEventoFirmato_=()=>({book,sheet:{}});
 context.generaVistaDaProiezioneDiretta_=()=>{calls.view++;return {sola_lettura:true};};
 context.decodificaOggetto_=JSON.parse;
 const request={event_id:'42',fingerprint:'revision-three',projection_hash:'hash'};
 const first=context.proiettaEventoDaWordPress_(request);
 assert.equal(first.ready,true);assert.equal(calls.write,1);assert.equal(calls.payments,1);
 const second=context.proiettaEventoDaWordPress_(request);
 assert.equal(second.ready,true);assert.equal(calls.write,1);assert.equal(calls.payments,1);
 assert.equal(calls.view,1);
 assert.deepEqual(JSON.parse(values.get('MI_DIRECT_VIEW_sheet-id')),{fingerprint:'revision-three',read_only:true,layout_version:2});
 const measured=context.proiettaEventoDaWordPress_({...request,measure_performance:true});
 assert.equal(measured.performance.view_built,false);assert.equal(measured.performance.participants,1);
 assert.equal(Object.hasOwn(second,'performance'),false);

 // Existing installations upgrade their old receipt once, without assuming read-only.
 values.set('MI_DIRECT_VIEW_sheet-id','revision-three');context.proiettaEventoDaWordPress_(request);
 assert.equal(calls.view,2);
 context.modificheCorrentiFoglio_=()=>({changes:[{id:1}],errors:[]});
 context.scriviProiezioneEvento_=()=>({aggiunte:0,manuali:1,conflitti:0});
 assert.equal(context.proiettaEventoDaWordPress_(request).ready,false);
 assert.equal(values.has('MI_DIRECT_VIEW_sheet-id'),false);

 // A failed flush must not publish a ready receipt; a later request retries.
 context.modificheCorrentiFoglio_=()=>({changes:[],errors:[]});
 context.scriviProiezioneEvento_=()=>({aggiunte:0,manuali:0,conflitti:0});
 context.SpreadsheetApp.flush=()=>{throw Error('synthetic flush failure');};
 assert.throws(()=>context.proiettaEventoDaWordPress_(request),/flush failure/);
 assert.equal(values.has('MI_DIRECT_VIEW_sheet-id'),false);
 context.SpreadsheetApp.flush=()=>{};
 assert.equal(context.proiettaEventoDaWordPress_(request).ready,true);
});
