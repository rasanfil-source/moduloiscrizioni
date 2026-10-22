import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../src/WebApp.gs', import.meta.url), 'utf8');
test('payment timestamps are UTC without stable IDs and preserve explicit offsets', () => {
  const previous = process.env.TZ;
  process.env.TZ = 'America/New_York';
  try {
    const rows = [];
    const context = vm.createContext({
      Date, MI_SHEETS: { PAYMENTS: 'payments' },
      ottieniSchedaObbligatoria_: () => ({ appendRow: row => rows.push(row) }),
      convertiRigheInOggetti_: () => [],
      normalizzaTesto_: value => String(value || '').trim(),
      neutralizzaFormula_: value => value || '',
      creaIdentificativoOpaco_: () => 'synthetic-payment',
      aggiungiControllo_: () => {},
    });
    vm.runInContext(source, context);
    for (const payment_id of [undefined, '17']) {
      for (const effective_at of ['2026-09-09 08:00:00', '2026-09-09T08:00:00', '2026-09-09T08:00:00Z', '2026-09-09T10:00:00+02:00']) {
        const payment = { payment_id, effective_at, transaction_kind: 'PAYMENT', amount_cents: 100, payment_source: 'CASH' };
        context.sincronizzaPagamenti_('DEMO', [payment, payment]);
        assert.equal(rows.at(-1)[4].toISOString(), '2026-09-09T08:00:00.000Z');
      }
    }
    assert.equal(rows.length, 8, 'replays in the same batch must remain idempotent');
    assert.throws(() => context.sincronizzaPagamenti_('DEMO', [{ effective_at: 'invalid', transaction_kind: 'PAYMENT', amount_cents: 100, payment_source: 'CASH' }]), /INVALID_EFFECTIVE_AT/);
  } finally {
    if (previous === undefined) delete process.env.TZ;
    else process.env.TZ = previous;
  }
});
