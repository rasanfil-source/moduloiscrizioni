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
  const intakeId = normalizzaTesto_(row[index.id_inserimento], 64) || creaIdentificativoOpaco_('pin');
  const orderCode = normalizzaTesto_(row[index.codice_ordine], 64);
  const transactionKind = normalizzaValoreElenco_(row[index.tipo_movimento], MI_PAYMENT_ENUMS.transactionKinds);
  const installmentKind = normalizzaValoreElenco_(row[index.tipo_rata], MI_PAYMENT_ENUMS.installmentKinds);
  const paymentSource = normalizzaValoreElenco_(row[index.fonte_pagamento], MI_PAYMENT_ENUMS.paymentSources);
  const amountCents = convertiEuroInCentesimi_(row[index.importo]);
  const effectiveAt = row[index.data_effettiva];
  const operatorLabel = neutralizzaFormula_(row[index.etichetta_operatore], 100);
	const externalReference = row[index.riferimento_esterno];
	const administrativeNote = row[index.nota_amministrativa];

  const reject = function (code, message, status) {
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT_INTAKE', intakeId, 'REJECTED', operatorLabel, code, 'MANUAL_SHEET');
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

  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS));
  const registration = registrations.find(function (item) { return String(item.codice_ordine) === orderCode; });
  if (!registration) return reject('ORDER_NOT_FOUND', 'Ordine non trovato.');
  if (['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(registration.stato).toUpperCase()) >= 0) return reject('ORDER_REVIEW', 'Ordine da verificare manualmente.', 'DA_VERIFICARE');

  const lock = LockService.getDocumentLock();
  lock.waitLock(5000);
  try {
    const paymentSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
    const payments = convertiRigheInOggetti_(paymentSheet);
    if (payments.some(function (item) { return String(item.id_inserimento_origine) === intakeId; })) {
      return { intakeId: intakeId, status: 'CONVALIDATO', message: 'Movimento già acquisito.' };
    }
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
    'MANUAL_SHEET',
    intakeId,
      new Date(),
      neutralizzaFormula_(administrativeNote, 500)
    ]);
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT', paymentId, 'SUCCESS', operatorLabel, 'PAYMENT_RECORDED', 'MANUAL_SHEET');
    return { intakeId: intakeId, status: 'CONVALIDATO', message: 'Movimento acquisito.' };
  } finally {
    lock.releaseLock();
  }
}
