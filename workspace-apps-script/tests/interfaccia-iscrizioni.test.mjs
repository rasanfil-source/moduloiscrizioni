import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../src/InterfacciaIscrizioni.gs', import.meta.url), 'utf8');
let formattedTimeZone = '';
let nextId = 0;
const context = vm.createContext({
  Date, JSON, Object, String, Array, Number,
  Utilities: { formatDate(value, timeZone) { formattedTimeZone = timeZone; return '2026-09-07'; } },
  normalizzaTesto_: value => String(value || '').trim(),
  creaIdentificativoOpaco_: () => `regui-${++nextId}`
});
vm.runInContext(source, context);

test('la cache precedente riconosce nome e codice oltre alla nuova scelta composta', () => {
  const map = context.mappaTipologieIscrizioneManuale_([
    { code: 'adulto', name: 'Adulto', choice: 'adulto — Adulto' },
    { code: 'ragazzo', name: 'Ragazzo' }
  ]);
  assert.equal(map['adulto — Adulto'], 'adulto');
  assert.equal(map.Adulto, 'adulto');
  assert.equal(map.Ragazzo, 'ragazzo');
  assert.equal(map.ragazzo, 'ragazzo');
});

test('la data civile usa il fuso orario del foglio passato dal chiamante', () => {
  const value = context.normalizzaValoreCampoIscrizioneManuale_(new Date('2026-09-06T23:30:00Z'), 'date', 'Asia/Tokyo');
  assert.equal(value, '2026-09-07');
  assert.equal(formattedTimeZone, 'Asia/Tokyo');
});

test('la chiave viene ruotata anche se la pulizia successiva fallisce', () => {
  const values = { AZ1: 'regui-completata' };
  const sheet = {
    getRange(a1) {
      return {
        setValue(value) { values[a1] = value; return this; },
        clearContent() { throw new Error('pulizia interrotta'); }
      };
    },
    getRangeList() { return { clearContent() { throw new Error('pulizia interrotta'); } }; }
  };
  assert.throws(() => context.preparaNuovaIscrizioneManuale_(sheet, ['nome']), /pulizia interrotta/);
  assert.equal(values.AZ1, 'regui-1');
});
