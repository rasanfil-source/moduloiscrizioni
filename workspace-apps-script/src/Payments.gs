function validateSelectedPayments() {
  const sheet = SpreadsheetApp.getActiveSheet();
  if (!sheet || sheet.getName() !== MI_SHEETS.PAYMENT_INTAKE) throw new Error('Apri PaymentIntake e seleziona le righe da convalidare.');
  const range = sheet.getActiveRange();
  const start = Math.max(2, range.getRow());
  validatePaymentRows_(start, range.getNumRows());
}

function validatePendingPayments() {
  const sheet = getRequiredSheet_(MI_SHEETS.PAYMENT_INTAKE);
  if (sheet.getLastRow() < 2) return;
  validatePaymentRows_(2, sheet.getLastRow() - 1, true);
}

function validatePaymentRows_(startRow, rowCount, pendingOnly) {
  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const intake = getRequiredSheet_(MI_SHEETS.PAYMENT_INTAKE);
    const index = headerIndex_(intake);
    const rows = intake.getRange(startRow, 1, rowCount, intake.getLastColumn()).getValues();
    rows.forEach(function (row, offset) {
      const rowNumber = startRow + offset;
      const currentStatus = normalizeEnum_(row[index.validation_status], ['PENDING', 'VALIDATED', 'REJECTED', 'REVIEW_REQUIRED']);
      if (pendingOnly && currentStatus && currentStatus !== 'PENDING') return;
      const result = validatePaymentRow_(row, index);
      intake.getRange(rowNumber, index.intake_id + 1).setValue(result.intakeId || row[index.intake_id]);
      intake.getRange(rowNumber, index.validation_status + 1).setValue(result.status);
      intake.getRange(rowNumber, index.validation_message + 1).setValue(result.message);
      intake.getRange(rowNumber, index.validated_at + 1).setValue(new Date());
    });
    SpreadsheetApp.flush();
  } finally {
    lock.releaseLock();
  }
}

function validatePaymentRow_(row, index) {
  const intakeId = normalizeText_(row[index.intake_id], 64) || makeOpaqueId_('pin');
  const orderCode = normalizeText_(row[index.order_code], 64);
  const transactionKind = normalizeEnum_(row[index.transaction_kind], MI_PAYMENT_ENUMS.transactionKinds);
  const installmentKind = normalizeEnum_(row[index.installment_kind], MI_PAYMENT_ENUMS.installmentKinds);
  const paymentSource = normalizeEnum_(row[index.payment_source], MI_PAYMENT_ENUMS.paymentSources);
  const amountCents = euroToCents_(row[index.amount]);
  const effectiveAt = row[index.effective_at];
  const operatorLabel = neutralizeFormula_(row[index.operator_label], 100);
	const externalReference = row[index.external_reference];
	const administrativeNote = row[index.administrative_note];

  const reject = function (code, message, status) {
    appendAudit_('VALIDATE_PAYMENT', 'PAYMENT_INTAKE', intakeId, 'REJECTED', operatorLabel, code, 'MANUAL_SHEET');
    return { intakeId: intakeId, status: status || 'REJECTED', message: message };
  };

  if (!orderCode) return reject('ORDER_REQUIRED', 'Codice ordine obbligatorio.');
  if (!transactionKind) return reject('TRANSACTION_KIND', 'Tipo movimento non valido.');
  if (!installmentKind) return reject('INSTALLMENT_KIND', 'Tipo rata non valido.');
  if (!paymentSource) return reject('PAYMENT_SOURCE', 'Fonte pagamento non valida.');
  if (amountCents === null) return reject('AMOUNT', 'Importo non valido o non positivo.');
  if (!(effectiveAt instanceof Date) || isNaN(effectiveAt.getTime())) return reject('EFFECTIVE_AT', 'Data effettiva non valida.');
  if (paymentSource === 'CASH' && !operatorLabel) return reject('CASH_OPERATOR', 'Operatore obbligatorio per i contanti.');
	if (containsCardNumberLike_(externalReference) || containsCardNumberLike_(administrativeNote)) return reject('CARD_DATA', 'Non inserire numeri completi di carta.');

  const registrations = rowsAsObjects_(getRequiredSheet_(MI_SHEETS.REGISTRATIONS));
  const registration = registrations.find(function (item) { return String(item.order_code) === orderCode; });
  if (!registration) return reject('ORDER_NOT_FOUND', 'Ordine non trovato.');
  if (['CANCELLED', 'EXPIRED'].indexOf(String(registration.status).toUpperCase()) >= 0) return reject('ORDER_REVIEW', 'Ordine da verificare manualmente.', 'REVIEW_REQUIRED');

  const payments = rowsAsObjects_(getRequiredSheet_(MI_SHEETS.PAYMENTS));
  if (payments.some(function (item) { return String(item.source_intake_id) === intakeId; })) {
    return { intakeId: intakeId, status: 'VALIDATED', message: 'Movimento già acquisito.' };
  }

  const paymentId = makeOpaqueId_('pay');
  getRequiredSheet_(MI_SHEETS.PAYMENTS).appendRow([
    paymentId,
    neutralizeFormula_(orderCode, 64),
    transactionKind,
    installmentKind,
    effectiveAt,
    amountCents,
    'EUR',
    paymentSource,
		neutralizeFormula_(externalReference, 120),
    operatorLabel,
    'MANUAL_SHEET',
    intakeId,
    new Date()
  ]);
  appendAudit_('VALIDATE_PAYMENT', 'PAYMENT', paymentId, 'SUCCESS', operatorLabel, 'PAYMENT_RECORDED', 'MANUAL_SHEET');
  return { intakeId: intakeId, status: 'VALIDATED', message: 'Movimento acquisito.' };
}
