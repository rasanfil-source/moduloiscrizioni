const MI_SCHEMA_VERSION = '1.0.0';
const MI_SHEETS = Object.freeze({
  CONFIG: 'Config',
  EVENTS: 'Events',
  REGISTRATIONS: 'Registrations',
  PARTICIPANTS: 'Participants',
  PAYMENT_INTAKE: 'PaymentIntake',
  PAYMENTS: 'Payments',
  EMAIL_OUTBOX: 'EmailOutbox',
  AUDIT_LOG: 'AuditLog'
});

const MI_HEADERS = Object.freeze({
  Config: ['key', 'value', 'description'],
  Events: ['event_id', 'activity_id', 'title', 'status', 'capacity', 'opens_at', 'closes_at', 'pricing_mode', 'updated_at'],
  Registrations: ['order_code', 'event_id', 'status', 'buyer_first_name', 'buyer_last_name', 'buyer_email', 'buyer_phone', 'total_qty', 'total_cents', 'idempotency_key', 'created_at'],
  Participants: ['order_code', 'participant_index', 'first_name', 'last_name', 'fields_json'],
  PaymentIntake: ['intake_id', 'order_code', 'transaction_kind', 'installment_kind', 'effective_at', 'amount', 'payment_source', 'external_reference', 'operator_label', 'administrative_note', 'validation_status', 'validation_message', 'validated_at'],
  Payments: ['payment_id', 'order_code', 'transaction_kind', 'installment_kind', 'effective_at', 'amount_cents', 'currency', 'payment_source', 'external_reference', 'operator_label', 'recording_channel', 'source_intake_id', 'created_at'],
  EmailOutbox: ['message_id', 'order_code', 'recipient', 'template_type', 'payload_json', 'status', 'created_at'],
  AuditLog: ['audit_id', 'occurred_at', 'channel', 'action', 'entity_type', 'entity_ref', 'outcome', 'actor_label', 'detail_code']
});

const MI_PAYMENT_ENUMS = Object.freeze({
  transactionKinds: ['RECEIPT', 'REFUND', 'REVERSAL'],
  installmentKinds: ['FULL', 'DEPOSIT', 'INTERIM', 'BALANCE', 'UNALLOCATED'],
  paymentSources: ['BANK_TRANSFER', 'CARD', 'CASH']
});

function getBoundSpreadsheet_() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) throw new Error('Il progetto deve essere associato a un Google Sheet.');
  return spreadsheet;
}

function getRequiredSheet_(name) {
  const sheet = getBoundSpreadsheet_().getSheetByName(name);
  if (!sheet) throw new Error('Foglio mancante: ' + name + '. Esegui setupWorkbook().');
  return sheet;
}

function getScriptSecret_() {
  const secret = PropertiesService.getScriptProperties().getProperty('MI_SHARED_SECRET');
  if (!secret || secret.length < 32) throw new Error('MI_SHARED_SECRET non configurato o troppo corto.');
  return secret;
}
