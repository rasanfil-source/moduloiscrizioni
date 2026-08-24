function onOpen() {
  SpreadsheetApp.getUi().createMenu('Modulo iscrizioni')
    .addItem('Inizializza/aggiorna struttura', 'setupWorkbook')
    .addSeparator()
    .addItem('Convalida pagamenti selezionati', 'validateSelectedPayments')
    .addItem('Convalida tutti i pagamenti in attesa', 'validatePendingPayments')
    .addToUi();
}

function setupWorkbook() {
  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const spreadsheet = getBoundSpreadsheet_();
    const existing = spreadsheet.getSheets();
    if (!spreadsheet.getSheetByName(MI_SHEETS.CONFIG) && existing.length === 1 && existing[0].getLastRow() <= 1 && existing[0].getLastColumn() <= 1) {
      existing[0].clear();
      existing[0].setName(MI_SHEETS.CONFIG);
    }

    Object.keys(MI_HEADERS).forEach(function (name) {
      const sheet = spreadsheet.getSheetByName(name) || spreadsheet.insertSheet(name);
      initializeSheet_(sheet, MI_HEADERS[name]);
    });

    initializeConfig_();
    initializePaymentValidation_();
    applySoftProtections_();
    appendAudit_('SETUP_WORKBOOK', 'WORKBOOK', 'BOUND', 'SUCCESS', Session.getActiveUser().getEmail(), MI_SCHEMA_VERSION, 'WORKSPACE_UI');
    SpreadsheetApp.flush();
    SpreadsheetApp.getUi().alert('Struttura aggiornata. Email e integrazione restano in modalità PREVIEW.');
  } finally {
    lock.releaseLock();
  }
}

function initializeSheet_(sheet, headers) {
  const current = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  const hasData = sheet.getLastRow() > 1;
  if (hasData && current.join('|') !== headers.join('|')) {
    throw new Error('Intestazioni inattese nel foglio ' + sheet.getName() + '. Intervento manuale richiesto.');
  }
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  sheet.setFrozenRows(1);
  sheet.setHiddenGridlines(true);
  const header = sheet.getRange(1, 1, 1, headers.length);
  header.setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  sheet.autoResizeColumns(1, headers.length);
  for (let column = 1; column <= headers.length; column += 1) {
    sheet.setColumnWidth(column, Math.min(220, Math.max(110, sheet.getColumnWidth(column))));
  }
  sheet.getRange(2, 1, Math.max(1, sheet.getMaxRows() - 1), headers.length).setVerticalAlignment('top');
}

function initializeConfig_() {
  const sheet = getRequiredSheet_(MI_SHEETS.CONFIG);
  if (sheet.getLastRow() > 1) return;
  sheet.getRange(2, 1, 5, 3).setValues([
    ['schema_version', MI_SCHEMA_VERSION, 'Versione della struttura Workspace'],
    ['environment', 'PREVIEW', 'PREVIEW finché il collaudo non è concluso'],
    ['timezone', 'Europe/Rome', 'Fuso operativo'],
    ['currency', 'EUR', 'Valuta degli importi'],
    ['email_mode', 'PREVIEW', 'Nessuna email reale in questa fase']
  ]);
}

function initializePaymentValidation_() {
  const sheet = getRequiredSheet_(MI_SHEETS.PAYMENT_INTAKE);
  const index = headerIndex_(sheet);
  const rowCount = Math.max(1, sheet.getMaxRows() - 1);
  sheet.getRange(2, index.transaction_kind + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.installment_kind + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.payment_source + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.validation_status + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['PENDING', 'VALIDATED', 'REJECTED', 'REVIEW_REQUIRED'], true).setAllowInvalid(false).build());
  sheet.getRange(2, index.effective_at + 1, rowCount, 1).setNumberFormat('yyyy-mm-dd hh:mm');
  sheet.getRange(2, index.amount + 1, rowCount, 1).setNumberFormat('#,##0.00 [$€-it-IT]');
}

function applySoftProtections_() {
  const editable = [MI_SHEETS.PAYMENT_INTAKE];
  Object.keys(MI_HEADERS).forEach(function (name) {
    const sheet = getRequiredSheet_(name);
    sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).forEach(function (protection) { protection.remove(); });
    if (editable.indexOf(name) < 0) sheet.protect().setDescription('Gestito da Modulo Iscrizioni').setWarningOnly(true);
  });
}
