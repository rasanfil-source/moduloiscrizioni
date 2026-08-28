function convalidaPagamentiSelezionati() {
  const sheet = SpreadsheetApp.getActiveSheet();
  if (!sheet || sheet.getName() !== MI_SHEETS.PAYMENT_INTAKE) throw new Error('Apri Inserimento pagamenti e seleziona le righe da convalidare.');
  const range = sheet.getActiveRange();
  const start = Math.max(2, range.getRow());
  convalidaRighePagamento_(start, range.getNumRows());
}

function convalidaPagamentiInAttesa() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
  if (sheet.getLastRow() < 2) return;
  convalidaRighePagamento_(2, sheet.getLastRow() - 1, true);
}

function convalidaRighePagamento_(startRow, rowCount, pendingOnly) {
    const intake = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
    const index = creaIndiceIntestazioni_(intake);
    const rows = intake.getRange(startRow, 1, rowCount, intake.getLastColumn()).getValues();
    rows.forEach(function (row, offset) {
      const rowNumber = startRow + offset;
      const currentStatus = normalizzaValoreElenco_(row[index.stato_convalida], ['IN_ATTESA', 'CONVALIDATO', 'RIFIUTATO', 'DA_VERIFICARE']);
      if (pendingOnly && currentStatus && currentStatus !== 'IN_ATTESA') return;
      const result = convalidaRigaPagamento_(row, index);
      intake.getRange(rowNumber, index.id_inserimento + 1).setValue(result.intakeId || row[index.id_inserimento]);
      intake.getRange(rowNumber, index.stato_convalida + 1).setValue(result.status);
      intake.getRange(rowNumber, index.messaggio_convalida + 1).setValue(result.message);
      intake.getRange(rowNumber, index.data_convalida + 1).setValue(new Date());
    });
    SpreadsheetApp.flush();
}

function convalidaRigaPagamento_(row, index) {
  return registraPagamentoValidato_({
    intake_id: row[index.id_inserimento],
    order_code: row[index.codice_ordine],
    transaction_kind: row[index.tipo_movimento],
    installment_kind: row[index.tipo_rata],
    effective_at: row[index.data_effettiva],
    amount: row[index.importo],
    payment_source: row[index.fonte_pagamento],
    external_reference: row[index.riferimento_esterno],
    operator_label: row[index.etichetta_operatore],
    administrative_note: row[index.nota_amministrativa],
    recording_channel: 'MANUAL_SHEET'
  });
}

function registraVersamentoSegreteria(form) {
  form = form || {};
  const activeOperator = normalizzaTesto_(Session.getActiveUser().getEmail(), 120);
  const result = registraPagamentoValidato_({
    intake_id: form.request_id,
    order_code: form.order_code,
    transaction_kind: 'INCASSO',
    installment_kind: form.installment_kind || 'NON_ASSEGNATO',
    effective_at: new Date(),
    amount: form.amount,
    payment_source: form.payment_source,
    external_reference: form.external_reference,
    operator_label: activeOperator || form.operator_label,
    administrative_note: form.administrative_note,
    recording_channel: 'WORKSPACE_UI'
  });
  if (result.status !== 'CONVALIDATO') throw new Error(result.message);
  return { ok: true, payment_id: result.paymentId, message: result.message };
}

function registraPagamentoValidato_(payload) {
  payload = payload || {};
  const channel = ['MANUAL_SHEET', 'WORKSPACE_UI'].indexOf(String(payload.recording_channel)) >= 0 ? String(payload.recording_channel) : 'MANUAL_SHEET';
  const intakeId = normalizzaTesto_(payload.intake_id, 64) || creaIdentificativoOpaco_(channel === 'WORKSPACE_UI' ? 'pui' : 'pin');
  const orderCode = normalizzaTesto_(payload.order_code, 64);
  const transactionKind = normalizzaValoreElenco_(payload.transaction_kind, MI_PAYMENT_ENUMS.transactionKinds);
  const installmentKind = normalizzaValoreElenco_(payload.installment_kind, MI_PAYMENT_ENUMS.installmentKinds);
  const paymentSource = normalizzaValoreElenco_(payload.payment_source, MI_PAYMENT_ENUMS.paymentSources);
  const amountCents = convertiEuroInCentesimi_(payload.amount);
  const effectiveAt = payload.effective_at instanceof Date ? payload.effective_at : new Date(payload.effective_at);
  const operatorLabel = neutralizzaFormula_(payload.operator_label, 100);
  const externalReference = payload.external_reference;
  const administrativeNote = payload.administrative_note;
  const reject = function (code, message, status) {
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT_INTAKE', intakeId, 'REJECTED', operatorLabel, code, channel);
    return { intakeId: intakeId, status: status || 'RIFIUTATO', message: message };
  };

  if (!orderCode) return reject('ORDER_REQUIRED', 'Codice ordine obbligatorio.');
  if (!transactionKind) return reject('TRANSACTION_KIND', 'Tipo movimento non valido.');
  if (!installmentKind) return reject('INSTALLMENT_KIND', 'Tipo rata non valido.');
  if (!paymentSource) return reject('PAYMENT_SOURCE', 'Fonte pagamento non valida.');
  if (amountCents === null) return reject('AMOUNT', 'Importo non valido o non positivo.');
  if (!(effectiveAt instanceof Date) || isNaN(effectiveAt.getTime())) return reject('EFFECTIVE_AT', 'Data effettiva non valida.');
  if (paymentSource === 'CONTANTE' && !operatorLabel) return reject('CASH_OPERATOR', 'Operatore obbligatorio per i contanti.');
  if (contienePossibileNumeroCarta_(externalReference) || contienePossibileNumeroCarta_(administrativeNote)) return reject('CARD_DATA', 'Non inserire numeri completi di carta.');

  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (item) { return String(item.codice_ordine) === orderCode; });
  if (!registration) return reject('ORDER_NOT_FOUND', 'Ordine non trovato.');
  if (['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(registration.stato).toUpperCase()) >= 0) return reject('ORDER_REVIEW', 'Ordine da verificare manualmente.', 'DA_VERIFICARE');

  const lock = LockService.getDocumentLock();
  lock.waitLock(5000);
  try {
    const paymentSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
    const payments = convertiRigheInOggetti_(paymentSheet);
    const duplicate = payments.find(function (item) { return String(item.id_inserimento_origine) === intakeId; });
    if (duplicate) return { intakeId: intakeId, paymentId: String(duplicate.id_pagamento || ''), status: 'CONVALIDATO', message: 'Movimento già acquisito.' };
    const currentPaid = payments.filter(function (item) { return String(item.codice_ordine) === orderCode; }).reduce(function (total, item) {
      const amount = Math.max(0, Number(item.importo_centesimi) || 0);
      return total + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount);
    }, 0);
    const netPaid = Math.max(0, currentPaid);
    const orderTotal = Math.max(0, Number(registration.totale_centesimi) || 0);
    if (transactionKind === 'INCASSO' && orderTotal < 1) return reject('FREE_ORDER', 'L’evento non prevede pagamenti.', 'DA_VERIFICARE');
    if (transactionKind === 'INCASSO' && amountCents > Math.max(0, orderTotal - netPaid)) return reject('OVERPAYMENT', 'L’importo supera il saldo residuo.', 'DA_VERIFICARE');
    if (['RIMBORSO', 'STORNO'].indexOf(transactionKind) >= 0 && amountCents > netPaid) return reject('EXCESS_REFUND', 'Il rimborso o storno supera quanto versato.', 'DA_VERIFICARE');
    const paymentId = creaIdentificativoOpaco_('pay');
    paymentSheet.appendRow([
      paymentId,
      neutralizzaFormula_(orderCode, 64),
      transactionKind,
      installmentKind,
      effectiveAt,
      amountCents,
      'EUR',
      paymentSource,
      neutralizzaFormula_(externalReference, 120),
      operatorLabel,
      channel,
      intakeId,
      new Date(),
      neutralizzaFormula_(administrativeNote, 500)
    ]);
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT', paymentId, 'SUCCESS', operatorLabel, 'PAYMENT_RECORDED', channel);
    return { intakeId: intakeId, paymentId: paymentId, status: 'CONVALIDATO', message: 'Versamento registrato e controllato.' };
  } finally {
    lock.releaseLock();
  }
}
