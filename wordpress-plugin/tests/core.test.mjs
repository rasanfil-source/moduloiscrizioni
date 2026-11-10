import assert from 'node:assert/strict';
import test from 'node:test';

await import('../modulo-iscrizioni/assets/core.js');
const core = globalThis.MIRegistrationCore;

test('le quantità sono intere e rispettano i limiti', () => {
  assert.equal(core.clampQuantity('3', 5), 3);
  assert.equal(core.clampQuantity('-2', 5), 0);
  assert.equal(core.clampQuantity('12', 5), 5);
  assert.equal(core.clampQuantity('x', 5), 0);
});

test('la selezione viene normalizzata e sommata', () => {
  const selection = core.normalizeSelection({ intero: { value: '2', max: 5 }, ridotto: { value: '9', max: 3 } });
  assert.deepEqual(selection, { intero: 2, ridotto: 3 });
  assert.equal(core.sumQuantities(selection), 5);
});

test('la caparra fissa viene limitata e ripartita per quota individuale come sul server', () => {
  assert.equal(core.fixedDepositCents([100, 1000], 0, 500), 600);
  assert.equal(core.fixedDepositCents([100, 1000], 101, 500), 651);
  assert.equal(core.fixedDepositCents([100, 1000], 101, 0), 0);
  assert.equal(core.fixedDepositCents([], 101, 500), 0);
});

test('il cellulare italiano viene completato con il prefisso internazionale', () => {
  assert.equal(core.normalizePhone('333 1234567'), '+39 333 1234567');
  assert.equal(core.normalizePhone('39 333 1234567'), '+39 333 1234567');
  assert.equal(core.normalizePhone('0039 333 1234567'), '+39 333 1234567');
  assert.equal(core.isValidPhone('+39 333 123 4567'), true);
  assert.equal(core.isValidPhone('3331234567'), true);
  assert.equal(core.isValidPhone('333123'), false);
  assert.equal(core.isValidPhone('+00 123'), false);
});
