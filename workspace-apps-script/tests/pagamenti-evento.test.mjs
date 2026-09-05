import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await Promise.all(['Config.gs', 'Core.gs', 'Payments.gs', 'PagamentiEvento.gs'].map(name => readFile(new URL('../src/' + name, import.meta.url), 'utf8')))).join('\n');

function environment() {
  let uuid = 0;
  let failAck = false;
  const payments = [];
  const orders = [{ codice_ordine: 'ORD-DEMO', id_evento: '42', stato: 'CONFIRMED', totale_centesimi: 10000 }, { codice_ordine: 'ORD-ALTRO', id_evento: '43', stato: 'CONFIRMED', totale_centesimi: 10000 }];
  const paymentHeaders = ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione', 'nota_amministrativa'];
  const ledger = { rows: payments, appendRow: row => payments.push(Object.fromEntries(paymentHeaders.map((key, index) => [key, row[index]]))) };
  const context = { Date, console, Utilities: { getUuid: () => `demo-${++uuid}` }, LockService: { getScriptLock: () => ({ waitLock() {}, releaseLock() {} }) }, SpreadsheetApp: { flush() {} } };
  vm.createContext(context); vm.runInContext(source, context);
  const columns = context.colonnePagamentiEvento_();
  const map = Object.fromEntries(columns.map(([key], index) => [key, index + 1]));
  const sheet = {
    rows: [columns.map(([, label]) => label)],
    getLastRow() { return this.rows.length; }, getLastColumn() { return columns.length; }, getMaxRows() { return 100; },
    getRange(row, col, rows = 1, cols = 1) {
      return {
        getValues: () => Array.from({ length: rows }, (_, y) => Array.from({ length: cols }, (_, x) => sheet.rows[row - 1 + y]?.[col - 1 + x] ?? '')),
        setValue(value) { if (failAck && col === map._registrato) { failAck = false; throw Error('errore simulato dopo incasso'); } return this.setValues([[value]]); },
        setValues(values) { values.forEach((valuesRow, y) => valuesRow.forEach((value, x) => { sheet.rows[row - 1 + y] ??= []; sheet.rows[row - 1 + y][col - 1 + x] = value; })); return this; }
      };
    }
  };
  context.ottieniSchedaObbligatoria_ = name => name === 'Iscrizioni' ? { rows: orders } : ledger;
  context.convertiRigheInOggetti_ = sheet => sheet.rows;
  context.aggiungiControllo_ = () => {};
  context.preparaPagamentiEvento_ = () => sheet;
  context.mappaColonneEvento_ = () => map;
  return { context, sheet, map, payments, orders,
    failAck() { failAck = true; },
    row(values = {}) {
      const data = { data: new Date('2026-09-01T12:00:00Z'), ordine: 'ORD-DEMO', tipo: 'Incasso', importo: 20, fonte: 'Contanti', causale: 'Caparra', operatore: 'Operatore demo', note: '', riferimento: '', convalida: true, ...values };
      sheet.rows.push(columns.map(([key]) => data[key] ?? ''));
    }, run() { return context.acquisisciPagamentiEvento_({}, '42'); }
  };
}

test('due incassi e rimborso restano movimenti distinti, retry non duplica', () => {
  const env = environment(); env.row(); env.run(); env.run();
  assert.equal(env.payments.length, 1);
  env.row({ importo: 30, causale: 'Saldo' }); env.run();
  env.row({ tipo: 'Rimborso', importo: 10 }); env.run(); env.run();
  assert.equal(env.payments.length, 3);
  assert.equal(env.payments.reduce((sum, p) => sum + (p.tipo_movimento === 'INCASSO' ? 1 : -1) * p.importo_centesimi, 0), 4000);
});

test('righe senza spunta, ordini di altro evento e date ambigue non incassano', () => {
  const env = environment(); env.row({ convalida: false }); env.row({ ordine: 'ORD-ALTRO' }); env.row({ data: '01/09/2026' }); env.run();
  assert.equal(env.payments.length, 0);
});

test('errore dopo incasso prima della conferma nel foglio è recuperabile', () => {
  const env = environment(); env.row(); env.failAck();
  assert.throws(() => env.run(), /simulato/);
  assert.equal(env.payments.length, 1);
  assert.ok(env.sheet.rows[1][env.map._movimento - 1]);
  env.run(); assert.equal(env.payments.length, 1);
  assert.equal(env.sheet.rows[1][env.map._registrato - 1], env.payments[0].id_pagamento);
});

test('riuso identificativo con importo diverso viene segnalato e non accettato', () => {
  const env = environment(); env.row(); env.failAck(); assert.throws(() => env.run());
  env.sheet.rows[1][env.map.importo - 1] = 25;
  env.run();
  assert.equal(env.payments.length, 1);
  assert.match(env.sheet.rows[1][env.map.esito - 1], /modificati dopo/);
});

test('modifiche a movimenti acquisiti non cambiano storico; riga cancellata viene ripristinata', () => {
  const env = environment(); env.row(); env.run();
  env.sheet.rows[1][env.map.importo - 1] = 90;
  env.sheet.rows[1][env.map.convalida - 1] = true;
  env.run(); assert.equal(env.payments.length, 1);
  assert.equal(env.payments[0].importo_centesimi, 2000);
  assert.match(env.sheet.rows[1][env.map.esito - 1], /modificati dopo/);
  env.sheet.rows.splice(1, 1); env.run(); env.run();
  assert.equal(env.sheet.rows.length, 2);
  assert.equal(env.sheet.rows[1][env.map.importo - 1], 20);
});

test('sovraversamenti e rimborsi eccessivi sono rifiutati; rimborso dopo annullamento ammesso', () => {
  const env = environment(); env.row(); env.run();
  env.row({ importo: 90 }); env.row({ tipo: 'Rimborso', importo: 30 }); env.run();
  assert.equal(env.payments.length, 1);
  env.orders[0].stato = 'CANCELLED';
  env.row({ tipo: 'Rimborso', importo: 20 }); env.run();
  assert.equal(env.payments.length, 2);
});
