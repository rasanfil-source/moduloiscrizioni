import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8');
const people = read('../modulo-iscrizioni/includes/class-mi-payment-people.php');
const ledger = read('../modulo-iscrizioni/includes/class-mi-payment-ledger.php');
const management = read('../modulo-iscrizioni/includes/class-mi-management-service.php');
const paymentsUi = read('../modulo-iscrizioni/assets/portal-payments.js');
const managementUi = read('../modulo-iscrizioni/assets/portal-management.js');

test('rimborsi e storni mantengono la primazia della persona', () => {
  assert.match(people, /function refund_plan/);
  assert.match(people, /1 !== count\( \$ids \)/);
  assert.match(people, /\$amount > max\( 0, \(int\) \$person\['paid'\] \)/);
  assert.match(ledger, /MI_Payment_People::refund_plan/);
  assert.match(paymentsUi, /requires_refund_allocation/);
  assert.match(paymentsUi, /Seleziona una sola persona/);
});

test('la conferma richiede la copertura personale e non il solo totale aggregato', () => {
  assert.match(ledger, /\$updated_individual = MI_Payment_People::read/);
  assert.match(ledger, /'DEPOSIT_BALANCE' === \$r\['economic_mode'\] \? 'deposit_missing' : 'balance'/);
  assert.match(ledger, /\$person\['active'\] && \(int\) \$person\[\$field\] > 0/);
});

test('la caparra percentuale viene ricalcolata senza cambiare la caparra fissa', () => {
  assert.match(management, /function percentage_deposits/);
  assert.match(management, /'PERCENTAGE' !== strtoupper/);
  assert.match(management, /round\( \$sum \* \$percentage \/ 100 \)/);
  assert.match(management, /'deposits' => null !== \$deposits/);
});

test('la variazione dei servizi richiede il permesso pagamenti nel backend e nella UI', () => {
  assert.match(management, /'change_options' === \$operation && ! MI_Portal_Payments::allowed\(\)/);
  assert.match(management, /function options_preview[\s\S]*?mi_options_permission/);
  assert.match(management, /can_change_options/);
  assert.match(managementUi, /b\.can_change_options&&/);
});
