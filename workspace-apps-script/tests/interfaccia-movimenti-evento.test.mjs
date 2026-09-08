import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await Promise.all(['Config.gs', 'Core.gs', 'InterfacciaMovimentiEvento.gs'].map((name) => readFile(new URL('../src/' + name, import.meta.url), 'utf8')))).join('\n');

test('il restyling conserva bozza, chiave, convalide e colori dell’esito anche se ripetuto', () => {
  const writes = [];
  const mutations = [];
  const range = (address) => new Proxy({}, {
    get(_, method) {
      if (method === 'getRanges') return () => address.map(range);
      if (method === 'getMergedRanges') return () => [];
      return (...args) => {
        mutations.push({ address, method, args });
        if (method === 'setValue') writes.push(address);
        return range(address);
      };
    }
  });
  const sheet = new Proxy({}, {
    get(_, method) {
      if (method === 'getRange' || method === 'getRangeList') return range;
      if (method === 'getMaxColumns') return () => 28;
      if (method === 'getMaxRows') return () => 1000;
      if (method === 'setFrozenRows' || method === 'setFrozenColumns') return () => null;
      return () => sheet;
    }
  });
  const context = { SpreadsheetApp: { BorderStyle: { SOLID: 'SOLID' } } };
  vm.createContext(context);
  vm.runInContext(source, context);
  for (const evento of [true, false, true]) context.applicaStileModuloPagamenti_(sheet, evento);
  const labels = new Set(['B14:C14', 'D14:E14', 'F14:H14', 'B20:H20', 'B3:H3', 'B25:D25', 'B26:H26', 'Z4']);
  assert.ok(writes.every((address) => labels.has(address)), 'solo etichette e versione grafica possono cambiare valore');
  assert.ok(!mutations.some(({ method }) => /clear|Validation/.test(method)), 'nessuna cancellazione o modifica delle convalide');
  assert.ok(!mutations.some(({ address, method }) => address === 'B28:H29' && ['setBackground', 'setFontColor', 'setValue'].includes(method)));
});

function environment(events = []) {
  const cells = new Map();
  const context = {
    Date, console,
    SpreadsheetApp: {
      newDataValidation: () => {
        const rule = { values: [] };
        return { requireValueInList(values) { rule.values = [...values]; return this; }, requireValueInRange(range) { rule.range = range; return this; }, setAllowInvalid() { return this; }, build() { return rule; } };
      }
    }
  };
  vm.createContext(context); vm.runInContext(source, context);
  context.ottieniSchedaObbligatoria_ = () => ({ rows: events });
  context.convertiRigheInOggetti_ = (sheet) => sheet.rows;
  const sheet = {
    getRange(a1) {
      if (!cells.has(a1)) cells.set(a1, { value: '', validation: null });
      const state = cells.get(a1);
      return {
        clearDataValidations() { state.validation = null; return this; },
        clearContent() { state.value = ''; return this; },
        setDataValidation(rule) { state.validation = rule; return this; },
        setValue(value) { state.value = value; return this; },
        setBackground(value) { state.background = value; return this; },
        setFontColor(value) { state.fontColor = value; return this; },
        getValue() { return state.value; }
      };
    }
  };
  return { context, sheet, cell: (a1) => cells.get(a1) };
}

test('le schede economiche distinguono evento totalmente gratuito e tutti gli altri', () => {
  const env = environment([
    { id_evento: '1', modalita_prezzo: 'ZERO' },
    { id_evento: '2', modalita_prezzo: 'FIXED' },
    { id_evento: '3', modalita_prezzo: 'CALCULATED' },
    { id_evento: '4', modalita_prezzo: 'NONE' },
    { id_evento: '5', modalita_prezzo: 'REGISTRATION_ONLY' }
  ]);
  assert.equal(env.context.eventoPrevedeMovimenti_('1'), false);
  assert.equal(env.context.eventoPrevedeMovimenti_('2'), true);
  assert.equal(env.context.eventoPrevedeMovimenti_('3'), true);
  assert.equal(env.context.eventoPrevedeMovimenti_('4'), true);
  assert.equal(env.context.eventoPrevedeMovimenti_('5'), false);
});

test('il selettore mostra nomi ordinati e il codice in seconda posizione per distinguere gli omonimi', () => {
  const env = environment();
  const scelte = env.context.creaSceltePrenotazioniInterfacciaMovimentoEvento_([
    { codice_ordine: 'ORD-3', nome_referente: 'Luca', cognome_referente: 'Bianchi' },
    { codice_ordine: 'ORD-1', nome_referente: 'Anna', cognome_referente: 'Rossi' },
    { codice_ordine: 'ORD-2', nome_referente: 'Anna', cognome_referente: 'Rossi' }
  ]);
  assert.deepEqual(JSON.parse(JSON.stringify(scelte)), [
    { etichetta: 'Anna Rossi · ORD-1', codice: 'ORD-1' },
    { etichetta: 'Anna Rossi · ORD-2', codice: 'ORD-2' },
    { etichetta: 'Luca Bianchi · ORD-3', codice: 'ORD-3' }
  ]);
});

test('il modulo propone rata e metodo ammessi dalla configurazione della prenotazione', () => {
  const env = environment();
  env.context.applicaConfigurazioneInterfacciaMovimentoEvento_(env.sheet, {
    stato: 'CONFIRMED', modalita_economica: 'DEPOSIT_BALANCE', fonti_pagamento_json: '["BANK_TRANSFER","CARD"]'
  }, 10000, 3000);
  assert.deepEqual(env.cell('B13').validation.values, ['INCASSO', 'RIMBORSO', 'STORNO']);
  assert.deepEqual(env.cell('D13').validation.values, ['CAPARRA', 'INTERMEDIO', 'SALDO']);
  assert.deepEqual(env.cell('D15').validation.values, ['BONIFICO', 'CARTA']);
});

test('il modulo omette incasso a saldo completo e usa Intero per la soluzione unica', () => {
  const env = environment();
  env.context.applicaConfigurazioneInterfacciaMovimentoEvento_(env.sheet, {
    stato: 'CONFIRMED', modalita_economica: 'FULL_PAYMENT', fonti_pagamento_json: '["CASH"]'
  }, 10000, 10000);
  assert.deepEqual(env.cell('B13').validation.values, ['RIMBORSO', 'STORNO']);
  assert.deepEqual(env.cell('D13').validation.values, ['INTERO']);
  assert.deepEqual(env.cell('D15').validation.values, ['CONTANTE']);
});
