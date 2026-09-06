import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import vm from 'node:vm';
import test from 'node:test';

const source = (await Promise.all(['Core.gs', 'Segreteria.gs', 'FogliOperativi.gs', 'FoglioIncrementale.gs'].map(name => readFile(new URL('../src/' + name, import.meta.url), 'utf8')))).join('\n');

class Range {
  constructor(sheet, row, col, rows = 1, cols = 1) { Object.assign(this, { sheet, row, col, rows, cols }); }
  getColumn() { return this.col; }
  getValues() { return Array.from({ length: this.rows }, (_, y) => Array.from({ length: this.cols }, (_, x) => this.sheet.data[this.row - 1 + y]?.[this.col - 1 + x] ?? '')); }
  getValue() { return this.getValues()[0][0]; }
  setValue(value) { return this.setValues([[value]]); }
  setValues(values) {
    values.forEach((row, y) => row.forEach((value, x) => {
      this.sheet.data[this.row + y - 1] ??= [];
      // Apps Script escapes formula-like text with a leading apostrophe.
      this.sheet.data[this.row + y - 1][this.col + x - 1] = typeof value === 'string' && /^'[=+@-]/.test(value) ? value.slice(1) : value;
    }));
    return this;
  }
  getFormula() { return this.sheet.formulas[`${this.row}:${this.col}`] || ''; }
  getFormulas() { return Array.from({ length: this.rows }, (_, y) => Array.from({ length: this.cols }, (_, x) => this.sheet.formulas[`${this.row + y}:${this.col + x}`] || '')); }
  addDeveloperMetadata(key, value) { this.sheet.meta.push(new Metadata(this.sheet, key, value, this.col)); return this; }
  setFontWeight() { return this; }
  setBackground() { return this; }
  setFontColor() { return this; }
  setNumberFormat() { return this; }
  shiftColumnGroupDepth() { this.sheet.groups.add(this.col); return this; }
}
class Metadata {
  constructor(sheet, key, value, col = null) { Object.assign(this, { sheet, key, value, col }); }
  getKey() { return this.key; }
  getValue() { return this.value; }
  setValue(value) { this.value = value; }
  getLocation() { return { getColumn: () => this.col ? this.sheet.getRange(1, this.col) : null }; }
}
class Sheet {
  constructor() { this.data = []; this.meta = []; this.formulas = {}; this.groups = new Set(); this.maxRows = 10; this.maxCols = 10; }
  getRange(...args) { return new Range(this, ...args); }
  getDeveloperMetadata() { return this.meta.filter(item => !item.col); }
  addDeveloperMetadata(key, value) { this.meta.push(new Metadata(this, key, value)); }
  createDeveloperMetadataFinder() { return { withKey: key => ({ find: () => this.meta.filter(item => item.key === key) }) }; }
  getLastRow() { return this.data.length; }
  getLastColumn() { return Math.max(0, ...this.data.map(row => row.length)); }
  getMaxRows() { return this.maxRows; }
  getMaxColumns() { return this.maxCols; }
  setFrozenRows() {}
  hideColumns() {}
  getColumnGroupDepth(col) { return this.groups.has(col) ? 1 : 0; }
  insertColumnsAfter(col, count) { this.maxCols += count; }
  insertRowsAfter(row, count) { this.maxRows += count; }
  insertColumnBefore(col) {
    this.data.forEach(row => row.splice(col - 1, 0, ''));
    this.meta.forEach(item => { if (item.col >= col) item.col++; });
    this.formulas = Object.fromEntries(Object.entries(this.formulas).map(([key, value]) => { const [r, c] = key.split(':').map(Number); return [`${r}:${c >= col ? c + 1 : c}`, value]; }));
    this.groups = new Set([...this.groups].map(c => c >= col ? c + 1 : c));
    this.maxCols++;
  }
  swapColumns(a, b) {
    this.data.forEach(row => { [row[a - 1], row[b - 1]] = [row[b - 1], row[a - 1]]; });
    this.meta.forEach(item => { if (item.col === a) item.col = b; else if (item.col === b) item.col = a; });
  }
}

function environment() {
  const sheet = new Sheet();
  let vista;
  const context = { Date, console, Utilities: { DigestAlgorithm: { SHA_256: 'sha256' }, Charset: { UTF_8: 'utf8' }, computeDigest: (_, value) => [...createHash('sha256').update(value).digest()] }, LockService: { getScriptLock: () => ({ waitLock() {}, releaseLock() {} }) }, SpreadsheetApp: { openById: () => ({ getSheetByName: () => sheet }) } };
  vm.createContext(context);
  vm.runInContext(source, context);
  context.trovaCollegamentoFoglioOperativo_ = () => ({ id_foglio: 'demo' });
  context.generaVistaOperativaEvento_ = () => vista;
  return { context, sheet, setVista(value) { vista = value; }, write(value) { vista = value; return context.scriviFoglioOperativoEvento_(sheet, value); }, map() { return context.mappaColonneEvento_(sheet); }, preview() { return context.preparaSincronizzazioneFoglioOperativo({ id_evento: '42' }); } };
}
function view(values = {}, fields = ['phone', 'email']) {
  return { evento: { id: '42' }, colonne: fields.map(key => ({ key, label: { phone: 'Cellulare', email: 'Email' }[key] || key, gruppo: key === 'transport' ? 'servizi' : 'persona' })), righe: [{ codice_ordine: 'ORD-DEMO', numero_partecipante: 1, valori: { phone: '00123', email: 'demo@example.invalid', ...values } }] };
}

test('refresh ripetibile, colonne rinominate/spostate e righe riordinate conservano identità', () => {
  const env = environment();
  env.write(view());
  env.sheet.swapColumns(env.map().phone, env.map().email);
  env.sheet.getRange(1, env.map().phone).setValue('Telefono referente');
  const second = view({ phone: '00456' });
  second.righe.push({ codice_ordine: 'ORD-DUE', numero_partecipante: 1, valori: { phone: '00789', email: '' } });
  env.write(second);
  [env.sheet.data[1], env.sheet.data[2]] = [env.sheet.data[2], env.sheet.data[1]];
  env.write(second);
  assert.equal(env.sheet.getLastRow(), 3);
  assert.equal(env.sheet.getRange(1, env.map().phone).getValue(), 'Telefono referente');
  assert.equal(env.sheet.getRange(3, env.map().phone).getValue(), '00456');
  assert.equal(env.preview().modifiche.length, 0);
});

test('aggiunta adiacente, colonna storica, formula e colonna libera sopravvivono al refresh', () => {
  const env = environment(); env.write(view());
  const free = env.sheet.getLastColumn() + 1;
  env.sheet.getRange(1, free).setValue('Note private'); env.sheet.getRange(2, free).setValue('Preservare');
  env.sheet.formulas[`2:${env.map().email}`] = '=A2';
  env.sheet.getRange(2, env.map().email).setValue('Formula manuale');
  env.write(view({ transport: 'Pullman A' }, ['phone', 'transport', 'email']));
  assert.equal(env.map().transport, env.map().phone + 1);
  assert.equal(env.sheet.getRange(2, env.map().transport).getValue(), 'Pullman A');
  assert.equal(env.sheet.getRange(2, env.map().email).getFormula(), '=A2');
  env.write(view({}, ['phone']));
  assert.equal(env.sheet.getRange(2, env.map().transport).getValue(), 'Pullman A');
  assert.equal(env.sheet.data[1].at(-1), 'Preservare');
});

test('modifica manuale e conflitto non sono sovrascritti né confusi con aggiornamenti centrali', () => {
  const env = environment(); env.write(view());
  env.sheet.getRange(2, env.map().phone).setValue('00999');
  assert.equal(env.write(view()).manuali, 1);
  assert.equal(env.preview().modifiche[0].nuovo, '00999');
  const result = env.write(view({ phone: '00888' }));
  assert.equal(result.conflitti, 1);
  assert.equal(env.sheet.getRange(2, env.map().phone).getValue(), '00999');
  assert.equal(env.preview().applicabile, false);
  assert.match(env.preview().problemi[0], /CONFLITTO/);
});

test('valore aggiornato solo al centro non diventa una modifica da importare', () => {
  const env = environment(); env.write(view()); env.setVista(view({ phone: '00777' }));
  assert.equal(env.preview().modifiche.length, 0);
});

test('riga incompleta non viene acquisita; riga cancellata viene ripristinata senza annullamento', () => {
  const env = environment(); env.write(view());
  env.sheet.getRange(3, env.map().phone).setValue('Bozza manuale');
  env.write(view());
  assert.equal(env.sheet.getRange(3, env.map().phone).getValue(), 'Bozza manuale');
  assert.equal(env.preview().applicabile, false);
  env.sheet.data.splice(1, 1);
  assert.equal(env.write(view()).aggiunte, 1);
  assert.equal(env.sheet.getLastRow(), 3);
});

test('identità duplicata o alterata blocca gli aggiornamenti', () => {
  const env = environment(); env.write(view());
  env.sheet.data.push([...env.sheet.data[1]]);
  assert.throws(() => env.write(view()), /duplicato/);
  env.sheet.data.pop();
  env.sheet.getRange(2, env.map()._numero).setValue(2);
  assert.throws(() => env.write(view()), /Identità/);
});

test('la sincronizzazione non importa servizi con impatto sui posti', () => {
  const env = environment(); env.write(view({ transport: 'A' }, ['phone', 'transport']));
  env.sheet.getRange(2, env.map().transport).setValue('B');
  assert.equal(env.preview().applicabile, false);
  assert.equal(env.preview().modifiche.length, 0);
});

test('migrazione verificata conserva divergenze vecchie e colonne libere', () => {
  const env = environment();
  env.sheet.data = [['Codice prenotazione', 'Numero partecipante', 'Cellulare', 'Email', 'Note'], ['ORD-DEMO', 1, 'manuale precedente', 'demo@example.invalid', 'Conservare']];
  env.sheet.addDeveloperMetadata('MI_CAMPI', JSON.stringify(['phone', 'email']));
  assert.equal(env.write(view()).conflitti, 1);
  assert.equal(env.sheet.getRange(2, env.map().phone).getValue(), 'manuale precedente');
  assert.equal(env.sheet.getRange(2, env.sheet.data[0].indexOf('Note') + 1).getValue(), 'Conservare');
  assert.equal(env.preview().applicabile, false);
});

test('vecchio foglio con titoli alterati si ferma senza alcuna scrittura', () => {
  const env = environment(); env.sheet.data = [['Codice prenotazione', 'Numero partecipante', 'Telefono'], ['ORD-DEMO', 1, '123']];
  env.sheet.addDeveloperMetadata('MI_CAMPI', '["phone"]');
  const before = JSON.stringify(env.sheet.data);
  assert.throws(() => env.write(view()), /migrazione/);
  assert.equal(JSON.stringify(env.sheet.data), before);
  assert.equal(Object.keys(env.map()).length, 0);
});

test('retry dopo creazione della colonna e prima dei valori ne completa il popolamento', () => {
  const env = environment(); env.write(view());
  const nuovaVista = view({ transport: 'Pullman A' }, ['phone', 'transport', 'email']);
  env.context.preparaStrutturaIncrementale_(env.sheet, nuovaVista);
  env.write(nuovaVista);
  assert.equal(env.sheet.getRange(2, env.map().transport).getValue(), 'Pullman A');
  assert.equal(env.write(nuovaVista).conflitti, 0);
});
