import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8');
const people = read('../modulo-iscrizioni/includes/class-mi-payment-people.php');
const ledger = read('../modulo-iscrizioni/includes/class-mi-payment-ledger.php');
const management = read('../modulo-iscrizioni/includes/class-mi-management-service.php');
const paymentsUi = read('../modulo-iscrizioni/assets/portal-payments.js');
const managementUi = read('../modulo-iscrizioni/assets/portal-management.js');
const admin = read('../modulo-iscrizioni/includes/class-mi-admin.php');
const registration = read('../modulo-iscrizioni/includes/class-mi-registration-service.php');
const publicBalance = read('../modulo-iscrizioni/includes/class-mi-public-balance.php');
const publicUi = read('../modulo-iscrizioni/assets/public.js');

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
	assert.match(ledger, /MI_Payment_People::covered\( \$updated_individual, \$r\['economic_mode'\] \)/);
	assert.match(people, /if \( empty\( \$person\['active'\] \) \) continue/);
});

test('la caparra percentuale viene ricalcolata senza cambiare la caparra fissa', () => {
	assert.match(management, /function percentage_deposits/);
	assert.match(management, /MI_Payment_People::projected_deposits/);
	assert.match(people, /'PERCENTAGE' !== strtoupper/);
	assert.match(people, /round\( \$sum \* \$percentage \/ 100 \)/);
  assert.match(management, /'deposits' => null !== \$deposits/);
	assert.match(managementUi, /caparra è percentuale viene ricalcolata sulle quote individuali/);
});

test('la variazione dei servizi richiede il permesso pagamenti nel backend e nella UI', () => {
  assert.match(management, /'change_options' === \$operation && ! MI_Portal_Payments::allowed\(\)/);
  assert.match(management, /function options_preview[\s\S]*?mi_options_permission/);
  assert.match(management, /can_change_options/);
  assert.match(managementUi, /b\.can_change_options&&/);
});

test('il riepilogo per metodo sottrae rimborsi e storni dal relativo incasso', () => {
  for (const source of ['BANK_TRANSFER', 'CARD', 'CASH']) {
    assert.match(admin, new RegExp(`payment_source='${source}' THEN CASE WHEN p\\.transaction_kind='REFUND' THEN -p\\.amount_cents ELSE p\\.amount_cents END`));
  }
  assert.match(admin, /formatta_importo_firmato\( \$summary\['BANK_TRANSFER'\] \)/);
});

test('le opzioni a pagamento valgono anche con prezzo NONE, mentre ZERO resta gratuito', () => {
  assert.match(registration, /'ZERO' === \( \$event\['pricing_mode'\] \?\? '' \) \? 0 : self::options_total/);
  assert.doesNotMatch(registration, /in_array\( \$event\['pricing_mode'\], array\( 'FIXED', 'CALCULATED' \)/);
  assert.match(publicUi, /config\.event\.pricing_mode === 'ZERO'\s*\? \{\}/);
});

test('una posizione riaperta non riusa una scadenza già trascorsa', () => {
  assert.match(registration, /function reopened_payment_deadline/);
  assert.match(registration, /\$deadline_timestamp > \$now/);
  assert.match(ledger, /MI_Registration_Service::reopened_payment_deadline\( \$r \)/);
  assert.match(management, /MI_Registration_Service::reopened_payment_deadline\( \$row \)/);
  assert.match(management, /MI_Registration_Service::reopened_payment_deadline\( \$locked \)/);
  assert.match(publicBalance, /MI_Registration_Service::reopened_payment_deadline\( \$r \)/);
});

test('i dati condivisi passano al primo partecipante ancora attivo', () => {
  assert.match(management, /\$participants = array_values[\s\S]*?\$first_person_id = \(int\) \( \$participants\[0\]\['id'\]/);
  assert.doesNotMatch(management, /\$first_person_id = \(int\) \( \$all_participants\[0\]/);
  assert.match(publicBalance, /\(int\) \( \$active\[0\]\['id'\] \?\? 0 \) === \$id/);
  assert.doesNotMatch(publicBalance, /\$b\['people'\]\[0\]\['id'\]/);
});

test('NONE azzera la quota base residua nel client ma conserva i servizi', () => {
  assert.match(publicUi, /pricing_mode === 'CALCULATED' \? Number\(ticket\.price_cents\) \|\| 0 : 0/);
  assert.match(publicUi, /pricing_mode === 'CALCULATED' \? Number\(ticket\?\.price_cents\) \|\| 0 : 0/);
  assert.match(publicUi, /pricing_mode === 'ZERO'\s*\? \{\}\s*: Object\.fromEntries/);
});
