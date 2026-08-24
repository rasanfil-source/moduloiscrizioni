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

test('il cellulare richiede il prefisso internazionale', () => {
  assert.equal(core.isValidPhone('+39 333 123 4567'), true);
  assert.equal(core.isValidPhone('3331234567'), false);
  assert.equal(core.isValidPhone('+00 123'), false);
});
