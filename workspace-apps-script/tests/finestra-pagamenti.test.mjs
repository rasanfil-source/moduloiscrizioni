import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const context = vm.createContext({ Date });
vm.runInContext(await readFile(new URL('../src/FinestraPagamenti.gs', import.meta.url), 'utf8'), context);
for (const [tipo, expected] of [['INCASSO', 2500], ['incasso', 2500], ['RIMBORSO', -2500], ['rimborso', -2500], ['STORNO', -2500], ['Storno', -2500]]) {
  test(`il popup conserva il segno di ${tipo}`, () => {
    const result = context.serializzaMovimento_({ id_pagamento: 'test', tipo_movimento: tipo, importo_centesimi: '2500', data_effettiva: new Date('2026-09-18T10:00:00Z') });
    assert.equal(result.importo, expected);
    assert.equal(result.tipo, tipo.toUpperCase());
    assert.equal(result.data, '2026-09-18T10:00:00.000Z');
  });
}
