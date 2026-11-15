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

test('removed central commands cannot mutate sheets even with an authenticated envelope',()=>{
 const {context,sheets}=environment(),before=JSON.stringify(sheets);
 context.verificaBusta_=()=>({ok:true});context.creaRispostaJson_=v=>v;
 for(const action of ['APPEND_REGISTRATION','REGISTRATION_STATUS']){
  const response=context.doPost({postData:{contents:JSON.stringify({action,payload:{event_id:'42'}})}});
  assert.equal(response.error,'ACTION_NOT_ALLOWED');
 }
 assert.equal(JSON.stringify(sheets),before);
 for(const name of ['statoReplicaIscrizione_','aggiungiIscrizione_','registraIscrizioneCentrale_','sincronizzaPagamenti_'])assert.equal(context[name],undefined);
});
