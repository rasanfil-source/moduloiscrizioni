import assert from 'node:assert/strict';
import test from 'node:test';

await import('../modulo-iscrizioni/assets/core.js');
const core = globalThis.MIRegistrationCore;

test('la scelta di capienza considera il gruppo intero, le tipologie e i posti in attesa', () => {
  const event = {waitlist_enabled:true,waitlist_capacity:5,availability:{remaining:1,waitlisted:2,ticket_types:{a:{remaining:null}}}};
  assert.equal(core.selectionCapacity(event,{a:1}).fits,true);
  const group = core.selectionCapacity(event,{a:2});
  assert.equal(group.fits,false);assert.equal(group.available,1);assert.equal(group.canWait,true);
  assert.equal(core.selectionCapacity(event,{a:4}).canWait,false);
  assert.equal(core.selectionCapacity({...event,waitlist_enabled:false},{a:2}).canWait,false);
  event.availability.remaining=10;event.availability.ticket_types.a.remaining=1;
  const mixed=core.selectionCapacity(event,{a:2,b:1});
  assert.equal(mixed.fits,false);assert.equal(mixed.available,2);
  event.availability.waitlist_remaining=0;
  assert.equal(core.selectionCapacity(event,{a:2}).canWait,false);
  event.availability.remaining=0;
  assert.equal(core.selectionCapacity(event,{a:1}).available,0);
  assert.equal(core.selectionCapacity({}, {a:2}),null);
});

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
