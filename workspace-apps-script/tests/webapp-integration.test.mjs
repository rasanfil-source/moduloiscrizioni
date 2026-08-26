import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const sourceDir = new URL('../src/', import.meta.url);
const source = (await Promise.all(['Config.gs', 'Core.gs', 'WebApp.gs'].map((name) => readFile(new URL(name, sourceDir), 'utf8')))).join('\n');

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
  constructor(headers) { this.rows = [[...headers]]; }
  getLastRow() { return this.rows.length; }
  getLastColumn() { return Math.max(0, ...this.rows.map((row) => row.length)); }
  getRange(row, column, rowCount = 1, columnCount = 1) { return new FakeRange(this, row, column, rowCount, columnCount); }
  appendRow(row) { this.rows.push([...row]); }
  deleteRow(row) { this.rows.splice(row - 1, 1); }
}

function environment() {
  const headers = {
    Iscrizioni: ['codice_ordine', 'id_evento', 'stato', 'nome_referente', 'cognome_referente', 'email_referente', 'telefono_referente', 'numero_partecipanti', 'totale_centesimi', 'chiave_idempotenza', 'data_creazione', 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json', 'id_revisione_evento', 'hash_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'versione_informativa_privacy', 'data_accettazione_privacy', 'biglietti_json', 'id_consenso_marketing', 'data_accettazione_marketing', 'opzioni_ordine_json'],
    Partecipanti: ['codice_ordine', 'numero_partecipante', 'codice_tipologia', 'indice_tipologia', 'nome', 'cognome', 'dati_aggiuntivi_json', 'opzioni_json'],
    Pagamenti: ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione', 'nota_amministrativa'],
    'Coda email': ['id_messaggio', 'codice_ordine', 'destinatario', 'tipo_modello', 'contenuto_json', 'stato', 'data_creazione'],
    'Registro controlli': ['id_controllo', 'data_evento', 'canale', 'azione', 'tipo_entita', 'riferimento_entita', 'esito', 'etichetta_attore', 'codice_dettaglio']
  };
  const sheets = Object.fromEntries(Object.entries(headers).map(([name, row]) => [name, new FakeSheet(row)]));
  const spreadsheet = { getSheetByName: (name) => sheets[name] || null };
  let uuid = 0;
  const context = {
    console, Date, JSON, Math, Number, String, Array, Object, RegExp, Boolean,
    SpreadsheetApp: { getActiveSpreadsheet: () => spreadsheet },
    LockService: { getDocumentLock: () => ({ waitLock() {}, releaseLock() {} }) },
    Utilities: { getUuid: () => `00000000-0000-4000-8000-${String(++uuid).padStart(12, '0')}` },
    ContentService: { MimeType: { JSON: 'JSON' }, createTextOutput: () => ({ setMimeType() { return this; } }) }
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  return { context, sheets };
}

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

test('APPEND_REGISTRATION riconcilia retry e ripara una proiezione partecipanti parziale', () => {
  const { context, sheets } = environment();
  const first = context.aggiungiIscrizione_(payload());
  assert.equal(first.ok, true);
  assert.equal(sheets.Iscrizioni.rows.length, 2);
  assert.equal(sheets.Partecipanti.rows.length, 3);
  const createdAt = sheets.Iscrizioni.rows[1][10].getTime();
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
  assert.equal(sheets.Iscrizioni.rows[1][10].getTime(), createdAt);
  assert.equal(sheets['Coda email'].rows[1][6].getTime(), messageCreatedAt);

  const cancelled = context.aggiungiIscrizione_(payload({ status: 'CANCELLED' }));
  assert.equal(cancelled.ok, true);
  assert.equal(JSON.parse(sheets['Coda email'].rows[1][4]).status, 'CONFIRMED');
});

test('APPEND_REGISTRATION rifiuta conflitti e mapping partecipanti non biunivoci', () => {
  const { context } = environment();
  assert.equal(context.aggiungiIscrizione_(payload()).ok, true);
  assert.equal(context.aggiungiIscrizione_(payload({ order_code: 'MI-ALTRO-CODICE' })).error, 'IDEMPOTENCY_CONFLICT');
  const invalid = payload({ order_code: 'MI-260825-ALTRO123', idempotency_key: 'abcdef1234567890abcdef1234567890' });
  invalid.participants[1].ticket_index = 1;
  assert.equal(context.aggiungiIscrizione_(invalid).error, 'INVALID_PARTICIPANTS');
});

test('APPEND_REGISTRATION accetta partecipanti secondari lasciati facoltativamente vuoti', () => {
  const { context } = environment();
  const optional = payload();
  optional.participants[1] = { ticket_type_code: 'standard', ticket_index: 2, first_name: '', last_name: '', fields: {}, options: [] };
  assert.equal(context.aggiungiIscrizione_(optional).ok, true);
});
