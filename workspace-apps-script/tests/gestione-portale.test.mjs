import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createHash} from 'node:crypto';
import vm from 'node:vm';
import test from 'node:test';
const names=['Config.gs','Core.gs','Segreteria.gs','PagamentiPortale.gs','RiepilogoPagamenti.gs','FinestraPagamenti.gs','GestionePortale.gs'];
const source=(await Promise.all(names.map(n=>readFile(new URL('../src/'+n,import.meta.url),'utf8')))).join('\n');
function env(){
 const context=vm.createContext({Date,console,Utilities:{DigestAlgorithm:{SHA_256:'sha256'},Charset:{UTF_8:'utf8'},computeDigest:(_,s)=>[...createHash('sha256').update(s).digest()]},LockService:{getScriptLock:()=>({waitLock(){},releaseLock(){}})},SpreadsheetApp:{flush(){}},Session:{getActiveUser:()=>({getEmail:()=>''})}});
 vm.runInContext(source,context);const headers=vm.runInContext('MI_HEADERS',context),sheets={};
 for(const [name,h]of Object.entries(headers)){const rows=[];sheets[name]={rows,appendRow(values){rows.push(Object.fromEntries(h.map((k,i)=>[k,values[i]])));},deleteRow(n){rows.splice(n-2,1);},getRange(n,c,rc=1,cc=1){return{setValue(v){rows[n-2][h[c-1]]=v;},setValues(values){for(let y=0;y<rc;y++)for(let x=0;x<cc;x++)rows[n-2+y][h[c-1+x]]=values[y][x];}};}};}
 context.ottieniSchedaObbligatoria_=name=>sheets[name];context.convertiRigheInOggetti_=s=>s.rows.map((r,i)=>({...r,_row:i+2}));context.creaIndiceIntestazioni_=s=>Object.fromEntries(headers[Object.keys(sheets).find(n=>sheets[n]===s)].map((k,i)=>[k,i]));context.aggiornaFoglioOperativoEvento=()=>({ok:true});
 sheets.Iscrizioni.rows.push({codice_ordine:'ORD-DEMO',id_evento:'42',stato:'CONFIRMED',nome_referente:'Persona',cognome_referente:'Demo',totale_centesimi:10000,snapshot_json:JSON.stringify({event:{participant_fields:[{key:'email',label:'Email',type:'email',required:true}]}})});
 sheets.Partecipanti.rows.push({codice_ordine:'ORD-DEMO',numero_partecipante:1,nome:'Persona',cognome:'Demo',stato_partecipante:'ACTIVE',dati_aggiuntivi_json:'{}',opzioni_json:'[]'});
 sheets.Sistemazioni.rows.push({id_evento:'42',codice:'A',nome:'Camera A',capienza:1,attiva:'SI'});
 const base={event_id:'42',order_code:'ORD-DEMO',request_id:'wp_7_12345678-1234-4234-8234-123456789abc',operator_label:'WP#7 · Operatore demo'};
 const detail=()=>context.schedaGestionePortale_(base);
 const payload=(data,operation='participant')=>({...base,version:detail().version,operation,data});
 return {context,sheets,base,detail,payload,save:p=>context.aggiornaGestionePortale_(p)};
}
test('scheda legge ogni foglio una volta e la richiesta successiva vede movimenti nuovi',()=>{
 const e=env(),counts={},original=e.context.convertiRigheInOggetti_;
 e.context.convertiRigheInOggetti_=sheet=>{const name=Object.keys(e.sheets).find(n=>e.sheets[n]===sheet);counts[name]=(counts[name]||0)+1;return original(sheet);};
 const first=e.detail();
 assert.equal(first.paid_cents,0);
 assert.equal(Object.keys(counts).length,7);
 for(const [name,count] of Object.entries(counts))assert.equal(count,1,name);
 e.sheets.Pagamenti.rows.push({id_pagamento:'new',codice_ordine:'ORD-DEMO',tipo_movimento:'INCASSO',importo_centesimi:2500,data_effettiva:new Date('2026-09-08')});
 const second=e.detail();
 assert.equal(second.paid_cents,2500);assert.equal(second.balance_cents,7500);assert.equal(second.movements.length,1);
 for(const [name,count] of Object.entries(counts))assert.equal(count,2,name);
});

test('campi mancanti visibili, nessuna camera dimostrativa creata dalla lettura',()=>{const e=env();e.sheets.Sistemazioni.rows.length=0;assert.equal(e.detail().fields[0].key,'email');assert.equal(e.detail().accommodations.length,0);assert.equal(e.sheets.Sistemazioni.rows.length,0);});
test('correzione nome, dati e camera; retry identico non duplica',()=>{const e=env();const p=e.payload({number:1,first_name:'Nuovo',last_name:'Demo',room:'A',fields:{email:'demo@example.invalid'}});assert.equal(e.save(p).saved,true);assert.equal(e.save(p).saved,true);assert.equal(e.detail().participants[0].first_name,'Nuovo');assert.equal(e.detail().participants[0].room,'A');assert.equal(e.sheets['Operazioni segreteria'].rows.length,1);assert.equal(e.detail().accommodations[0].available,0);});
test('stesso id con contenuto diverso e versione obsoleta vengono respinti',()=>{const e=env();const p=e.payload({number:1,first_name:'Nuovo',last_name:'Demo',fields:{}});e.save(p);assert.throws(()=>e.save({...p,data:{...p.data,first_name:'Altro'}}),/Identificativo/);assert.throws(()=>e.save({...p,request_id:p.request_id.replace('abc','abd')}),/cambiati/);});
test('evento alterato, email e data non valide non producono scritture',()=>{const e=env();const p=e.payload({number:1,first_name:'Nuovo',last_name:'Demo',fields:{email:'invalid'}});assert.throws(()=>e.save({...p,event_id:'43'}));assert.throws(()=>e.save(p),/Email/);assert.equal(e.sheets['Stato operativo'].rows.length,0);});
test('camera piena, eliminazione camera occupata e capienza insufficiente vengono respinte',()=>{const e=env();e.sheets.Partecipanti.rows.push({...e.sheets.Partecipanti.rows[0],numero_partecipante:2,dati_aggiuntivi_json:'{"room":"A"}'});assert.throws(()=>e.save(e.payload({number:1,first_name:'Persona',last_name:'Demo',fields:{},room:'A'})),/Camera/);assert.throws(()=>e.save(e.payload({code:'A'},'room_delete')),/Riassegna/);assert.throws(()=>e.save(e.payload({code:'A',name:'A',capacity:0},'room_save')),/capienza/);});
test('crea e cancella una camera vuota con nuovi identificativi',()=>{const e=env();assert.equal(e.save(e.payload({code:'B',name:'Camera B',capacity:2},'room_save')).saved,true);const p=e.payload({code:'B'},'room_delete');p.request_id=p.request_id.replace('abc','abd');assert.equal(e.save(p).saved,true);assert.equal(e.detail().accommodations.some(r=>r.code==='B'),false);});
test('interruzione a metà salvataggio riprende con la stessa richiesta',()=>{const e=env();const p=e.payload({number:1,first_name:'Nuovo',last_name:'Demo',fields:{email:'demo@example.invalid'}});const original=e.context.aggiornaStatoOperativo_;let count=0;e.context.aggiornaStatoOperativo_=(...args)=>{if(++count===2)throw Error('Interruzione');return original(...args);};assert.throws(()=>e.save(p),/Interruzione/);e.context.aggiornaStatoOperativo_=original;assert.equal(e.save(p).saved,true);assert.equal(e.detail().participants[0].fields.email,'demo@example.invalid');assert.equal(e.sheets['Operazioni segreteria'].rows[0].stato,'APPLICATA');});
test('riepilogo non duplica gli importi per partecipante e ignora annullati nei conteggi operativi',()=>{const e=env();e.sheets.Pagamenti.rows.push({id_pagamento:'p1',codice_ordine:'ORD-DEMO',tipo_movimento:'INCASSO',importo_centesimi:2000,data_effettiva:new Date()});e.sheets.Partecipanti.rows.push({...e.sheets.Partecipanti.rows[0],numero_partecipante:2});const r=e.context.riepilogoGestioneEvento_({event_id:'42'});assert.equal(r.items[0].participants,2);assert.equal(r.items[0].paid,2000);assert.equal(r.items[0].balance,8000);assert.equal(e.detail().movements.length,1);});
test('errori di validazione arrivano al portale senza essere scambiati per salvataggi',()=>{const e=env();const r=e.context.rispostaAggiornamentoGestione_(e.payload({number:1,first_name:'',last_name:'Demo',fields:{}}));assert.equal(r.saved,false);assert.equal(r.rejected,true);assert.match(r.message,/obbligatori/);assert.equal(e.sheets['Operazioni segreteria'].rows.length,0);});
test('il riepilogo rispetta dati richiesti a uno solo e non chiede camere non previste',()=>{const e=env();e.sheets.Sistemazioni.rows.length=0;e.sheets.Partecipanti.rows.push({...e.sheets.Partecipanti.rows[0],numero_partecipante:2});const r=e.context.riepilogoGestioneEvento_({event_id:'42'});assert.equal(r.items[0].missing,1);assert.equal(r.items[0].unassigned,0);});
test('un nuovo caricamento recupera il salvataggio interrotto solo per il suo operatore',()=>{const e=env();const p=e.payload({number:1,first_name:'Nuovo',last_name:'Demo',fields:{}});const original=e.context.aggiornaStatoOperativo_;e.context.aggiornaStatoOperativo_=()=>{throw Error('Interruzione');};assert.throws(()=>e.save(p));const recovered=e.detail().pending;assert.equal(recovered.request_id,p.request_id.replace(/^wp_7_/,''));assert.equal(JSON.parse(recovered.data).first_name,'Nuovo');assert.equal(e.context.schedaGestionePortale_({...e.base,operator_label:'WP#8 · Altro'}).pending,undefined);e.context.aggiornaStatoOperativo_=original;assert.equal(e.save({...p,data:JSON.parse(recovered.data)}).saved,true);assert.equal(e.detail().pending,undefined);});
