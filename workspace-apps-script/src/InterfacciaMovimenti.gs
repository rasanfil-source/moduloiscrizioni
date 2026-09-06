const MI_MOVEMENT_FORM = Object.freeze({
  VERSION: '1',
  SHEET: 'Registra movimento',
  ORDER: 'B7',
  ORDER_STATUS: 'F7',
  EVENT: 'B9',
  REFERENT: 'E9',
  TOTAL: 'B10',
  PAID: 'D10',
  BALANCE: 'F10',
  TRANSACTION: 'B13',
  INSTALLMENT: 'D13',
  DATE: 'F13',
  AMOUNT: 'B15',
  SOURCE: 'D15',
  REFERENCE: 'F15',
  OPERATOR: 'B18',
  NOTE: 'B20',
  CONFIRM: 'B25',
  STATUS: 'B28',
  REQUEST_ID: 'Z1',
  MARKER: 'Z2'
});

function inizializzaInterfacciaMovimenti_() {
  const spreadsheet = ottieniFoglioDiLavoroAssociato_();
  let sheet = spreadsheet.getSheetByName(MI_MOVEMENT_FORM.SHEET);
  const nuova = !sheet;
  if (!sheet) sheet = spreadsheet.insertSheet(MI_MOVEMENT_FORM.SHEET, 0);
  if (nuova || String(sheet.getRange(MI_MOVEMENT_FORM.MARKER).getValue()) !== MI_MOVEMENT_FORM.VERSION) costruisciInterfacciaMovimenti_(sheet);
  aggiornaConvalideInterfacciaMovimenti_(sheet);
  return sheet;
}

function costruisciInterfacciaMovimenti_(sheet) {
  sheet.clear();
  sheet.setHiddenGridlines(true);
  sheet.setFrozenRows(4);
  sheet.setColumnWidth(1, 28);
  sheet.setColumnWidths(2, 7, 120);
  sheet.setRowHeights(1, 2, 34);
  sheet.setRowHeight(3, 32);
  sheet.setRowHeights(5, 25, 28);
  sheet.getRange('B1:H2').merge().setValue('Registra un movimento')
    .setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(20)
    .setHorizontalAlignment('left').setVerticalAlignment('middle');
  sheet.getRange('B3:H3').merge().setValue('Scegli la prenotazione, controlla il saldo, descrivi il movimento e conferma.')
    .setFontColor('#657084').setFontSize(11);
  [
    ['B5:H5', '1 · Prenotazione'],
    ['B11:H11', '2 · Movimento'],
    ['B17:H17', '3 · Tracciabilità'],
    ['B24:H24', '4 · Verifica e registra']
  ].forEach(function (item) {
    sheet.getRange(item[0]).merge().setValue(item[1]).setBackground('#e8edf7')
      .setFontColor('#17224a').setFontWeight('bold').setFontSize(12);
  });
  sheet.getRange('B6:E6').merge().setValue('Codice prenotazione');
  sheet.getRange('F6:H6').merge().setValue('Stato');
  sheet.getRange('B7:E7').merge();
  sheet.getRange('F7:H7').merge();
  sheet.getRange('B8:D8').merge().setValue('Evento');
  sheet.getRange('E8:H8').merge().setValue('Referente');
  sheet.getRange('B9:D9').merge();
  sheet.getRange('E9:H9').merge();
  sheet.getRange('B10').setValue('Totale');
  sheet.getRange('D10').setValue('Versato');
  sheet.getRange('F10').setValue('Residuo');
  sheet.getRange('B12:C12').merge().setValue('Tipo movimento');
  sheet.getRange('D12:E12').merge().setValue('Rata');
  sheet.getRange('F12:H12').merge().setValue('Data effettiva');
  sheet.getRange('B13:C13').merge().setValue('INCASSO');
  sheet.getRange('D13:E13').merge().setValue('CAPARRA');
  sheet.getRange('F13:H13').merge().setValue(new Date()).setNumberFormat('dd/mm/yyyy');
  sheet.getRange('B14:C14').merge().setValue('Importo');
  sheet.getRange('D14:E14').merge().setValue('Metodo');
  sheet.getRange('F14:H14').merge().setValue('Riferimento');
  sheet.getRange('B15:C15').merge().setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange('D15:E15').merge().setValue('BONIFICO');
  sheet.getRange('F15:H15').merge();
  sheet.getRange('B16:H16').merge().setValue('Operatore');
  sheet.getRange('B18:H18').merge().setValue(normalizzaTesto_(Session.getActiveUser().getEmail(), 120));
  sheet.getRange('B19:H19').merge().setValue('Nota amministrativa');
  sheet.getRange('B20:H22').merge().setWrap(true).setVerticalAlignment('top');
  sheet.getRange('B25').insertCheckboxes().setValue(false);
  sheet.getRange('C25:H25').merge().setValue('Ho verificato prenotazione, tipo, importo e data.');
  sheet.getRange('B27:H27').merge().setValue('Esito');
  sheet.getRange('B28:H29').merge().setValue('Compila i dati e aggiorna il riepilogo prima di registrare.').setWrap(true);
  sheet.getRangeList(['B6:H6', 'B8:H8', 'B12:H12', 'B14:H14', 'B16:H16', 'B19:H19', 'B27:H27'])
    .setFontColor('#657084').setFontWeight('bold').setFontSize(10);
  const surfaces = sheet.getRangeList(['B7:H7', 'B9:H10', 'B13:H15', 'B18:H18', 'B20:H22', 'B25:H25', 'B28:H29']);
  surfaces.setBackground('#ffffff').setFontColor('#172033');
  surfaces.getRanges().forEach(function (range) {
    range.setBorder(true, true, true, true, false, false, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID);
  });
  sheet.getRangeList(['B7:E7', 'B13:C13', 'D13:E13', 'F13:H13', 'B15:C15', 'D15:E15', 'F15:H15', 'B18:H18', 'B20:H22'])
    .setBackground('#f8fafc');
  sheet.getRange('B28:H29').setBackground('#eaf5ef').setFontColor('#25745e');
  sheet.getRange(MI_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pui'));
  sheet.getRange(MI_MOVEMENT_FORM.MARKER).setValue(MI_MOVEMENT_FORM.VERSION);
  sheet.hideColumns(26);
  sheet.setActiveSelection(MI_MOVEMENT_FORM.ORDER);
}

function aggiornaConvalideInterfacciaMovimenti_(sheet) {
  const registrations = ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS);
  const orderRange = registrations.getRange(2, 1, Math.max(1, registrations.getMaxRows() - 1), 1);
  sheet.getRange(MI_MOVEMENT_FORM.ORDER).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInRange(orderRange, true).setAllowInvalid(false).build()
  );
  sheet.getRange(MI_MOVEMENT_FORM.TRANSACTION).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build()
  );
  sheet.getRange(MI_MOVEMENT_FORM.INSTALLMENT).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build()
  );
  sheet.getRange(MI_MOVEMENT_FORM.SOURCE).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build()
  );
}

function apriInserimentoMovimentoGuidato() {
  const sheet = inizializzaInterfacciaMovimenti_();
  sheet.activate();
  sheet.setActiveSelection(MI_MOVEMENT_FORM.ORDER);
}

function riepilogoMovimentoGuidato_(orderCode) {
  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (item) {
    return String(item.codice_ordine) === orderCode;
  });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (item) {
    return String(item.codice_ordine) === orderCode;
  });
  const paid = Math.max(0, payments.reduce(function (total, item) {
    const amount = Math.max(0, Number(item.importo_centesimi) || 0);
    return total + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount);
  }, 0));
  const total = Math.max(0, Number(registration.totale_centesimi) || 0);
  const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (item) {
    return String(item.id_evento) === String(registration.id_evento);
  });
  return {
    registration: registration,
    eventTitle: event ? String(event.titolo || '') : 'Evento ' + String(registration.id_evento),
    referent: [registration.nome_referente, registration.cognome_referente].filter(String).join(' '),
    total: total,
    paid: paid,
    balance: Math.max(0, total - paid)
  };
}

function aggiornaRiepilogoMovimentoGuidato(mantieniEsito) {
  const sheet = inizializzaInterfacciaMovimenti_();
  const orderCode = normalizzaTesto_(sheet.getRange(MI_MOVEMENT_FORM.ORDER).getValue(), 64);
  if (!orderCode) throw new Error('Scegli prima una prenotazione.');
  const summary = riepilogoMovimentoGuidato_(orderCode);
  sheet.getRange(MI_MOVEMENT_FORM.ORDER_STATUS).setValue(normalizzaTesto_(summary.registration.stato, 40));
  sheet.getRange(MI_MOVEMENT_FORM.EVENT).setValue(summary.eventTitle);
  sheet.getRange(MI_MOVEMENT_FORM.REFERENT).setValue(summary.referent);
  sheet.getRange(MI_MOVEMENT_FORM.TOTAL).offset(0, 1).setValue(summary.total / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange(MI_MOVEMENT_FORM.PAID).offset(0, 1).setValue(summary.paid / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  const balanceValue = sheet.getRange(MI_MOVEMENT_FORM.BALANCE).offset(0, 1, 1, 2);
  if (!balanceValue.isPartOfMerge()) balanceValue.merge();
  balanceValue.setValue(summary.balance / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  const transaction = String(sheet.getRange(MI_MOVEMENT_FORM.TRANSACTION).getValue());
  if (transaction === 'RIMBORSO' || transaction === 'STORNO') sheet.getRange(MI_MOVEMENT_FORM.INSTALLMENT).setValue('NON_ASSEGNATO');
  else if (!String(sheet.getRange(MI_MOVEMENT_FORM.INSTALLMENT).getValue())) {
    sheet.getRange(MI_MOVEMENT_FORM.INSTALLMENT).setValue(summary.paid < Number(summary.registration.primo_versamento_centesimi || 0) ? 'CAPARRA' : 'SALDO');
  }
  if (!mantieniEsito) sheet.getRange(MI_MOVEMENT_FORM.STATUS).setValue('Riepilogo aggiornato. Controlla i dati e spunta la conferma.');
  sheet.activate();
  return summary;
}

function registraMovimentoGuidato() {
  const sheet = inizializzaInterfacciaMovimenti_();
  if (sheet.getRange(MI_MOVEMENT_FORM.CONFIRM).getValue() !== true) throw new Error('Spunta la conferma dopo aver verificato i dati.');
  const orderCode = normalizzaTesto_(sheet.getRange(MI_MOVEMENT_FORM.ORDER).getValue(), 64);
  const summary = riepilogoMovimentoGuidato_(orderCode);
  const result = registraPagamentoValidato_({
    intake_id: normalizzaTesto_(sheet.getRange(MI_MOVEMENT_FORM.REQUEST_ID).getValue(), 64),
    order_code: orderCode,
    transaction_kind: sheet.getRange(MI_MOVEMENT_FORM.TRANSACTION).getValue(),
    installment_kind: sheet.getRange(MI_MOVEMENT_FORM.INSTALLMENT).getValue(),
    effective_at: sheet.getRange(MI_MOVEMENT_FORM.DATE).getValue(),
    amount: sheet.getRange(MI_MOVEMENT_FORM.AMOUNT).getValue(),
    payment_source: sheet.getRange(MI_MOVEMENT_FORM.SOURCE).getValue(),
    external_reference: sheet.getRange(MI_MOVEMENT_FORM.REFERENCE).getValue(),
    operator_label: sheet.getRange(MI_MOVEMENT_FORM.OPERATOR).getValue(),
    administrative_note: sheet.getRange(MI_MOVEMENT_FORM.NOTE).getValue(),
    recording_channel: 'WORKSPACE_UI'
  });
  if (result.status !== 'CONVALIDATO') {
    sheet.getRange(MI_MOVEMENT_FORM.STATUS).setBackground('#fff4e5').setFontColor('#8a4b08').setValue(result.message);
    sheet.getRange(MI_MOVEMENT_FORM.CONFIRM).setValue(false);
    throw new Error(result.message);
  }
  let projectionMessage = '';
  try {
    const link = trovaCollegamentoFoglioOperativo_(String(summary.registration.id_evento));
    aggiornaProiezionePagamentiEvento_(SpreadsheetApp.openById(String(link.id_foglio)), String(summary.registration.id_evento));
    projectionMessage = ' Foglio evento aggiornato.';
  } catch (error) {
    projectionMessage = ' Movimento registrato; aggiorna in seguito il foglio evento.';
  }
  sheet.getRange(MI_MOVEMENT_FORM.STATUS).setBackground('#eaf5ef').setFontColor('#25745e').setValue(result.message + projectionMessage);
  sheet.getRange(MI_MOVEMENT_FORM.CONFIRM).setValue(false);
  sheet.getRangeList([MI_MOVEMENT_FORM.AMOUNT, MI_MOVEMENT_FORM.REFERENCE, MI_MOVEMENT_FORM.NOTE]).clearContent();
  sheet.getRange(MI_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pui'));
  aggiornaRiepilogoMovimentoGuidato(true);
  SpreadsheetApp.getActive().toast('Movimento registrato e controllato.', 'Modulo iscrizioni', 5);
  return result;
}
