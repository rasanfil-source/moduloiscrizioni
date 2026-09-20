import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import {createHash} from 'node:crypto';

const sourceDir = new URL('../src/', import.meta.url);
const source = (await Promise.all(['Config.gs', 'Core.gs', 'WebApp.gs', 'SincronizzazioneManuale.gs'].map((name) => readFile(new URL(name, sourceDir), 'utf8')))).join('\n');

class FakeRange {
  constructor(sheet, row, column, rowCount, columnCount) { Object.assign(this, { sheet, row, column, rowCount, columnCount }); }
  getValues() { return Array.from({ length: this.rowCount }, (_, y) => Array.from({ length: this.columnCount }, (_, x) => this.sheet.rows[this.row - 1 + y]?.[this.column - 1 + x] ?? '')); }
  getDisplayValues() { return this.getValues().map((row) => row.map((value) => value instanceof Date ? value.toISOString() : String(value ?? ''))); }
  setValues(values) {
    for (let y = 0; y < this.rowCount; y += 1) {
      if (!this.sheet.rows[this.row - 1 + y]) this.sheet.rows[this.row - 1 + y] = [];
      for (let x = 0; x < this.columnCount; x += 1) this.sheet.rows[this.row - 1 + y][this.column - 1 + x] = values[y][x];
    }
    return this;
  }
}

class FakeSheet {
  constructor(headers) { this.rows = [[...headers]]; this.capacity=5; }
  getMaxRows(){return this.capacity;}
  insertRowsAfter(after,count){assert.ok(after<=this.capacity);this.capacity+=count;}
  getLastRow() { return this.rows.length; }
  getLastColumn() { return Math.max(0, ...this.rows.map((row) => row.length)); }
  getRange(row, column, rowCount = 1, columnCount = 1) { if(row+rowCount-1>this.capacity)throw Error('OUTSIDE_GRID');return new FakeRange(this, row, column, rowCount, columnCount); }
  appendRow(row) { this.rows.push([...row]);this.capacity=Math.max(this.capacity,this.rows.length); }
  deleteRow(row) { this.rows.splice(row - 1, 1); }
  deleteRows(row, count) { this.rows.splice(row - 1, count);this.capacity-=count; }
}

function environment() {
  const headers = {
    Iscrizioni: ['codice_ordine', 'id_evento', 'stato', 'nome_referente', 'cognome_referente', 'email_referente', 'telefono_referente', 'richieste_particolari', 'numero_partecipanti', 'totale_centesimi', 'chiave_idempotenza', 'data_creazione', 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json', 'id_revisione_evento', 'hash_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'versione_informativa_privacy', 'data_accettazione_privacy', 'biglietti_json', 'id_consenso_marketing', 'data_accettazione_marketing', 'opzioni_ordine_json', 'workspace_revision', 'versato_centesimi', 'replica_completa_revision'],
    Partecipanti: ['codice_ordine', 'numero_partecipante', 'codice_tipologia', 'indice_tipologia', 'nome', 'cognome', 'dati_aggiuntivi_json', 'opzioni_json', 'stato_partecipante', 'data_annullamento', 'totale_centesimi', 'versato_centesimi', 'saldo_centesimi', 'caparra_centesimi', 'caparra_residua_centesimi'],
    Pagamenti: ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione', 'nota_amministrativa', 'attribuzioni_partecipanti_json'],
    'Coda email': ['id_messaggio', 'codice_ordine', 'destinatario', 'tipo_modello', 'contenuto_json', 'stato', 'data_creazione'],
    'Registro controlli': ['id_controllo', 'data_evento', 'canale', 'azione', 'tipo_entita', 'riferimento_entita', 'esito', 'etichetta_attore', 'codice_dettaglio']
  };
  const sheets = Object.fromEntries(Object.entries(headers).map(([name, row]) => [name, new FakeSheet(row)]));
  const spreadsheet = { getSheetByName: (name) => sheets[name] || null };
  let uuid = 0;
	const properties=new Map();
  const context = {
    console, Date, JSON, Math, Number, String, Array, Object, RegExp, Boolean,
    SpreadsheetApp: { getActiveSpreadsheet: () => spreadsheet },
    LockService: { getScriptLock: () => ({ tryLock() {return true;}, waitLock() {}, releaseLock() {} }) },
    PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k)||null,setProperty:(k,v)=>properties.set(k,v),deleteProperty:k=>properties.delete(k)})},
    Utilities: { DigestAlgorithm:{SHA_256:'sha256'},computeDigest:(alg,text)=>Array.from(createHash(alg).update(text).digest()),base64EncodeWebSafe:v=>Buffer.from(v).toString('base64url'), getUuid: () => `00000000-0000-4000-8000-${String(++uuid).padStart(12, '0')}` },
    ContentService: { MimeType: { JSON: 'JSON' }, createTextOutput: () => ({ setMimeType() { return this; } }) }
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  for (const [name, headers] of Object.entries(vm.runInContext('MI_HEADERS', context))) if (!sheets[name]) sheets[name] = new FakeSheet(headers);
  context.indiceStatoOperativo_ = () => ({});
  context.aggiornaFoglioOperativoEvento = () => ({ ok: true });
  return { context, sheets };
}

test('identical room snapshot skips writes but repairs partial data at the same revision',()=>{
 const {context:c,sheets}=environment();const rooms=[{code:'double',name:'Doppia',capacity:2}];
 c.sincronizzaCamereMysql_('42',rooms,'3');
 const sheet=sheets.Sistemazioni;let writes=0;const range=sheet.getRange.bind(sheet),remove=sheet.deleteRows.bind(sheet);
 sheet.deleteRows=(...args)=>{writes++;return remove(...args);};
 sheet.getRange=(...args)=>{const r=range(...args),set=r.setValues.bind(r);r.setValues=v=>{writes++;return set(v);};return r;};
 c.sincronizzaCamereMysql_('42',rooms,'3');assert.equal(writes,0);
 sheet.rows[1][2]='Parziale';c.sincronizzaCamereMysql_('42',rooms,'3');assert.equal(writes,2);assert.equal(sheet.rows[1][2],'Doppia');
});

test('replica MySQL conserva movimenti identici distinti, oltre 100 righe e revisione', () => {
  const {context,sheets}=environment();
	const payments=Array.from({length:105},(_,i)=>({payment_id:String(i+1),transaction_kind:'PAYMENT',movement_kind:'INCASSO',installment_kind:'OTHER',effective_at:'2026-09-09 08:00:00',amount_cents:10,payment_source:'CASH',external_reference:'',operator_label:'Test',administrative_note:'',participant_allocations_json:JSON.stringify([{participant_id:1,name:'Persona Uno',amount_cents:10}])}));
  const p=payload({payments,workspace_revision:'9'});
  assert.equal(context.aggiungiIscrizione_(p).workspace_revision,'9');
  assert.equal(sheets.Pagamenti.rows.length,106);
  assert.equal(context.aggiungiIscrizione_(p).complete,true);
  assert.equal(sheets.Pagamenti.rows.length,106);
	assert.equal(sheets.Pagamenti.rows[1][4].toISOString(),'2026-09-09T08:00:00.000Z');
	assert.deepEqual(JSON.parse(sheets.Pagamenti.rows[1][14]),[{participant_id:1,name:'Persona Uno',amount_cents:10}]);
	payments[0].amount_cents=11;
	payments[0].participant_allocations_json=JSON.stringify([{participant_id:1,name:'Persona Uno',amount_cents:11}]);
  assert.throws(()=>context.aggiungiIscrizione_(p),/PAYMENT_ID_CONFLICT/);
});

function payload(overrides = {}) {
  const buyer = { first_name: 'Referente', last_name: 'Demo', email: 'demo@example.invalid', phone: '+39 000 0000000' };
  return {
    order_code: 'MI-260825-DEMO1234', event_id: '42', idempotency_key: '1234567890abcdef1234567890abcdef', status: 'CONFIRMED',
    buyer,
    tickets: [{ ticket_type_code: 'standard', quantity: 2, unit_price_cents: 1000 }],
    participants: [
      { ticket_type_code: 'standard', ticket_index: 1, first_name: 'Persona', last_name: 'Uno', fields: {}, options: [] },
      { ticket_type_code: 'standard', ticket_index: 2, first_name: 'Persona', last_name: 'Due', fields: {}, options: [] }
    ],
    total_cents: 2000, economic_mode: 'PRICE_ONLY', initial_due_cents: 0, balance_cents: 0, payment_methods: [], order_options: [],
    event_revision_id: '7', event_revision_hash: 'a'.repeat(64), snapshot_json: JSON.stringify({ schema_version: '3.4.1', event: 42, status: 'CONFIRMED', buyer }),
    privacy_consent_id: 'privacy-42', privacy_policy_version: '2026-08', privacy_accepted_at: '2026-08-25 10:00:00', payments: [],
    ...overrides
  };
}

test('repliche ripetute ed espansione partecipanti non esauriscono la griglia',()=>{
 const {context,sheets}=environment();
 for(let revision=1;revision<=25;revision++){
  const count=revision%2?20:2;
  const participants=Array.from({length:count},(_,i)=>({ticket_type_code:'standard',ticket_index:i+1,first_name:'Persona',last_name:String(i),fields:{},options:[]}));
  assert.equal(context.aggiungiIscrizione_(payload({workspace_revision:String(revision),participants,tickets:[{ticket_type_code:'standard',quantity:count,unit_price_cents:1000}]})).ok,true);
  assert.equal(sheets.Partecipanti.rows.length,count+1);
 }
});

test('una richiesta vecchia non sovrascrive la revisione nuova, anche dopo un errore di proiezione', () => {
  const { context, sheets } = environment();
  context.aggiornaFoglioOperativoEvento = () => { throw new Error('Offline'); };
  assert.equal(context.aggiungiIscrizione_(payload({ workspace_revision: '10', status: 'CANCELLED' })).central_complete, true);
  const before = JSON.stringify(sheets.Iscrizioni.rows);
  assert.equal(context.aggiungiIscrizione_(payload({ workspace_revision: '9' })).error, 'STALE_WORKSPACE_REVISION');
  assert.equal(JSON.stringify(sheets.Iscrizioni.rows), before);
  context.aggiornaFoglioOperativoEvento = () => ({ ok: true });
  assert.equal(context.aggiungiIscrizione_(payload({ workspace_revision: '10', status: 'CANCELLED' })).complete, true);
});

test('la replica MySQL conferma il centro senza attendere la vista e ignora camere più vecchie', () => {
  const {context,sheets}=environment();
  context.aggiornaFoglioOperativoEvento = () => { throw new Error('La vista non deve bloccare la risposta MySQL'); };
  const canonical={canonical_source:'MYSQL',workspace_revision:'1',workspace_event_revision:'10',rooms:[{code:'A',name:'Camera A',capacity:'2'}]};
  const response=context.aggiungiIscrizione_(payload(canonical));
  assert.equal(response.complete,true);
  assert.equal(response.central_complete,true);
  assert.equal(response.event_sheet_pending,true);
  assert.equal(response.event_sheet_complete,false);
  assert.equal(sheets.Sistemazioni.rows[1][2],'Camera A');
  assert.equal(context.aggiungiIscrizione_(payload({...canonical,workspace_revision:'2',workspace_event_revision:'9',rooms:[]})).complete,true);
  assert.equal(sheets.Sistemazioni.rows.length,2);
  assert.equal(context.aggiungiIscrizione_(payload({...canonical,workspace_revision:'3',workspace_event_revision:'11',rooms:[]})).complete,true);
  assert.equal(sheets.Sistemazioni.rows.length,1);
});

test('APPEND_REGISTRATION riconcilia retry e ripara una proiezione partecipanti parziale', () => {
  const { context, sheets } = environment();
  const first = context.aggiungiIscrizione_(payload());
  assert.equal(first.ok, true);
  assert.equal(sheets.Iscrizioni.rows.length, 2);
  assert.equal(sheets.Partecipanti.rows.length, 3);
  const createdAt = sheets.Iscrizioni.rows[1][11].getTime();
  const messageCreatedAt = sheets['Coda email'].rows[1][6].getTime();

  sheets.Partecipanti.deleteRow(3);
  const replay = context.aggiungiIscrizione_(payload({ participants: [
    { ticket_type_code: 'standard', ticket_index: 1, first_name: 'Persona', last_name: 'Uno', fields: {}, options: [] },
    { ticket_type_code: 'standard', ticket_index: 2, first_name: 'Persona', last_name: 'Due aggiornata', fields: {}, options: [] }
  ] }));
  assert.equal(replay.ok, true);
  assert.equal(replay.replayed, true);
  assert.equal(sheets.Iscrizioni.rows.length, 2);
  assert.equal(sheets.Partecipanti.rows.length, 3);
  assert.equal(sheets.Partecipanti.rows[2][5], 'Due aggiornata');
  assert.equal(sheets.Iscrizioni.rows[1][11].getTime(), createdAt);
  assert.equal(sheets['Coda email'].rows[1][6].getTime(), messageCreatedAt);

  const cancelled = context.aggiungiIscrizione_(payload({ status: 'CANCELLED' }));
  assert.equal(cancelled.ok, true);
	assert.equal(sheets.Iscrizioni.rows[1][8], 0);
	assert.equal(sheets.Partecipanti.rows.length, 3);
  assert.equal(JSON.parse(sheets['Coda email'].rows[1][4]).status, 'CONFIRMED');
});

test('la prenotazione conserva gli annullati ma conta soltanto i partecipanti attivi', () => {
  const { context, sheets } = environment();
  const participants = [
    { ticket_type_code: 'standard', ticket_index: 1, first_name: 'Persona', last_name: 'Uno', status: 'ACTIVE', fields: {}, options: [] },
    { ticket_type_code: 'standard', ticket_index: 2, first_name: 'Persona', last_name: 'Due', status: 'CANCELLED', cancelled_at: '2026-09-16 10:00:00', fields: {}, options: [] }
  ];
  const response = context.aggiungiIscrizione_(payload({ workspace_revision: '2', participants }));
  assert.equal(response.complete, true);
  assert.equal(sheets.Iscrizioni.rows[1][8], 1);
  assert.equal(sheets.Partecipanti.rows.length, 3);
  assert.equal(sheets.Partecipanti.rows[2][8], 'CANCELLED');
});

test('APPEND_REGISTRATION rifiuta conflitti e mapping partecipanti non biunivoci', () => {
  const { context } = environment();
  assert.equal(context.aggiungiIscrizione_(payload()).ok, true);
  assert.equal(context.aggiungiIscrizione_(payload({ order_code: 'MI-ALTRO-CODICE' })).error, 'IDEMPOTENCY_CONFLICT');
  const invalid = payload({ order_code: 'MI-260825-ALTRO123', idempotency_key: 'abcdef1234567890abcdef1234567890' });
  invalid.participants[1].ticket_index = 1;
  assert.equal(context.aggiungiIscrizione_(invalid).error, 'INVALID_PARTICIPANTS');
});

test('APPEND_REGISTRATION richiede nome e cognome per ogni partecipante', () => {
  const { context } = environment();
  const optional = payload();
  optional.participants[1] = { ticket_type_code: 'standard', ticket_index: 2, first_name: '', last_name: '', fields: {}, options: [] };
  assert.equal(context.aggiungiIscrizione_(optional).error, 'INVALID_PARTICIPANTS');
});

test('la consegna è incompleta se il foglio evento fallisce, il retry conserva una sola iscrizione', () => {
  const { context, sheets } = environment();
  context.aggiornaFoglioOperativoEvento = () => { throw new Error('Sheet temporaneamente indisponibile'); };
  const failed = context.aggiungiIscrizione_(payload());
  assert.equal(failed.complete, false);
  assert.equal(failed.central_complete, true);
  assert.equal(failed.error, 'EVENT_SHEET_PENDING');
  context.aggiornaFoglioOperativoEvento = () => ({ ok: true });
  const retry = context.aggiungiIscrizione_(payload());
  assert.equal(retry.complete, true);
  assert.equal(retry.event_sheet_complete, true);
  assert.equal(sheets.Iscrizioni.rows.length, 2);
  assert.equal(sheets.Partecipanti.rows.length, 3);
});

test('la replica conserva il versato anche con residuo zero e credito',()=>{const {context,sheets}=environment();const result=context.aggiungiIscrizione_(payload({paid_cents:12000,total_cents:10000,balance_cents:0}));assert.equal(result.complete,true);assert.equal(sheets.Iscrizioni.rows[1][27],12000);});

test('Apri richiede le revisioni correnti e una proiezione completata',()=>{
 const {context,sheets}=environment();context.SpreadsheetApp.flush=()=>{};
 const p=payload({canonical_source:'MYSQL',workspace_revision:'3',workspace_event_revision:'2',rooms:[]});
 assert.equal(context.aggiungiIscrizione_(p).complete,true);
 let writes=0,pending=false;
 context.aggiornaFoglioOperativoEventoConLock_=()=>{writes++;return {ok:true,url_foglio:'https://docs.google.com/spreadsheets/d/synthetic/edit',esito:{manuali:pending?1:0,conflitti:0}};};
 const request={event_id:'42',registrations:[{order_code:p.order_code,revision:'4'}],rooms:[],workspace_event_revision:'2'};
 assert.deepEqual(Array.from(context.preparaAperturaFoglio_(request).needs_sync),[p.order_code]);assert.equal(writes,0);
 request.registrations[0].revision='3';pending=true;let result=context.preparaAperturaFoglio_(request);assert.equal(result.ready,false);assert.equal(result.url_foglio,undefined);
 pending=false;result=context.preparaAperturaFoglio_(request);assert.equal(result.ready,true);assert.equal(result.event_sheet_complete,true);
 request.workspace_event_revision='1';assert.throws(()=>context.preparaAperturaFoglio_(request),/REPLICA_MISMATCH/);
 request.registrations=[];assert.throws(()=>context.preparaAperturaFoglio_(request),/REPLICA_MISMATCH/);
});

test('replica conserva TEST_INVIATA e cancella solo blocchi contigui selezionati',()=>{
 const {context,sheets}=environment();const p=payload();context.aggiungiIscrizione_(p);
 sheets['Coda email'].rows[1][5]='TEST_INVIATA';context.aggiungiIscrizione_(p);assert.equal(sheets['Coda email'].rows[1][5],'TEST_INVIATA');
 const rows=['header','a','b','foreign','c','d','e','foreign2'];const calls=[];
 context.eliminaRigheContigue_({deleteRows:(start,count)=>{calls.push([start,count]);rows.splice(start-1,count);}},[2,3,5,6,7].map(_row=>({_row})));
 assert.deepEqual(calls,[[5,3],[2,2]]);assert.deepEqual(rows,['header','foreign','foreign2']);
});

test('prepared view skips reconstruction, but manual edits and changed revisions invalidate it',()=>{
 const {context:c}=environment();let writes=0,manual=false;
 const url='https://docs.google.com/spreadsheets/d/synthetic/edit';
 c.SpreadsheetApp.flush=()=>{};
 c.trovaCollegamentoFoglioOperativo_=()=>({id_foglio:'synthetic'});
 c.SpreadsheetApp.openById=()=>({getUrl:()=>url,getSheetByName:()=>({})});
 c.modificheCorrentiFoglio_=()=>({changes:manual?[{}]:[],errors:[]});
 c.aggiornaFoglioOperativoEventoConLock_=()=>{writes++;return {ok:true,read_only:false,url_foglio:url,esito:{manuali:manual?1:0,conflitti:0}};};
 const p=payload({canonical_source:'MYSQL',workspace_revision:'3',workspace_event_revision:'2',rooms:[]});c.aggiungiIscrizione_(p);
 const req={event_id:'42',registrations:[{order_code:p.order_code,revision:'3'}],rooms:[],workspace_event_revision:'2'};
 assert.equal(c.preparaAperturaFoglio_(req).ready,true);assert.equal(writes,1);
 assert.equal(c.preparaAperturaFoglio_({...req,background:true}).ready,true);assert.equal(writes,1);
 manual=true;assert.equal(c.preparaAperturaFoglio_(req).ready,false);assert.equal(writes,2);
 manual=false;assert.equal(c.preparaAperturaFoglio_(req).ready,true);assert.equal(writes,3);
 req.registrations[0].revision='4';assert.equal(c.preparaAperturaFoglio_(req).needs_sync.length,1);assert.equal(writes,3);
 c.aggiungiIscrizione_({...p,workspace_revision:'4'});assert.equal(c.preparaAperturaFoglio_(req).ready,true);assert.equal(writes,4);
});

test('una replica interrotta non autorizza Apri anche se la revisione è già scritta',()=>{
 const {context,sheets}=environment();context.SpreadsheetApp.flush=()=>{};
 const p=payload({canonical_source:'MYSQL',workspace_revision:'3',workspace_event_revision:'2',rooms:[]});
 context.aggiungiIscrizione_(p);
 const original=sheets.Partecipanti.getRange.bind(sheets.Partecipanti);
 sheets.Partecipanti.getRange=(...args)=>{if(args[0]>1)throw Error('synthetic write failure');return original(...args);};
 assert.throws(()=>context.aggiungiIscrizione_({...p,workspace_revision:'4'}),/synthetic write failure/);
 assert.equal(sheets.Iscrizioni.rows[1][26],'4');assert.equal(sheets.Iscrizioni.rows[1][28],'');
 const request={event_id:'42',registrations:[{order_code:p.order_code,revision:'4'}],rooms:[],workspace_event_revision:'2'};
 context.aggiornaFoglioOperativoEventoConLock_=()=>{throw Error('must not render partial replica');};
 assert.deepEqual(Array.from(context.preparaAperturaFoglio_(request).needs_sync),[p.order_code]);
 sheets.Partecipanti.getRange=original;assert.equal(context.aggiungiIscrizione_({...p,workspace_revision:'4'}).complete,true);
 assert.equal(sheets.Iscrizioni.rows[1][28],'4');
});
