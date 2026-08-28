function onOpen() {
  SpreadsheetApp.getUi().createMenu('Modulo iscrizioni')
    .addItem('Inizializza/aggiorna struttura', 'configuraCartellaDiLavoro')
    .addSeparator()
    .addItem('Apri segreteria', 'apriSchedaPrenotazione')
    .addItem('Configura elenco operativo', 'apriConfigurazioneElencoOperativo')
    .addItem('Comunicazioni operative', 'apriComunicazioniOperative')
    .addItem('Configura collegamento WordPress', 'configuraEndpointWordPress')
    .addSeparator()
    .addItem('Convalida pagamenti selezionati', 'convalidaPagamentiSelezionati')
    .addItem('Convalida tutti i pagamenti in attesa', 'convalidaPagamentiInAttesa')
    .addSeparator()
    .addItem('Configura destinatario email di test', 'configuraDestinatarioTestEmail')
    .addItem('Invia coda al solo destinatario di test', 'inviaCodaEmailDiTest')
    .addToUi();
}

function configuraCartellaDiLavoro() {
  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const spreadsheet = ottieniFoglioDiLavoroAssociato_();
    rinominaSchedePrecedenti_(spreadsheet);
    const existing = spreadsheet.getSheets();
    if (!spreadsheet.getSheetByName(MI_SHEETS.CONFIG) && existing.length === 1 && existing[0].getLastRow() <= 1 && existing[0].getLastColumn() <= 1) {
      existing[0].clear();
      existing[0].setName(MI_SHEETS.CONFIG);
    }

    Object.keys(MI_HEADERS).forEach(function (name) {
      const sheet = spreadsheet.getSheetByName(name) || spreadsheet.insertSheet(name);
      inizializzaScheda_(sheet, MI_HEADERS[name]);
    });

    inizializzaConfigurazione_();
    inizializzaConvalidaPagamenti_();
    applicaProtezioniConAvviso_();
    aggiungiControllo_('SETUP_WORKBOOK', 'WORKBOOK', 'BOUND', 'SUCCESS', Session.getActiveUser().getEmail(), MI_SCHEMA_VERSION, 'WORKSPACE_UI');
    SpreadsheetApp.flush();
    SpreadsheetApp.getUi().alert('Struttura aggiornata. Email e integrazione restano in modalità PREVIEW.');
  } finally {
    lock.releaseLock();
  }
}

function rinominaSchedePrecedenti_(spreadsheet) {
  Object.keys(MI_LEGACY_SHEET_NAMES).forEach(function (oldName) {
    const newName = MI_LEGACY_SHEET_NAMES[oldName];
    const oldSheet = spreadsheet.getSheetByName(oldName);
    if (oldSheet && !spreadsheet.getSheetByName(newName)) oldSheet.setName(newName);
  });
}

function inizializzaScheda_(sheet, headers) {
  const current = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  const hasData = sheet.getLastRow() > 1;
  const previous = MI_LEGACY_HEADERS[sheet.getName()] || [];
	const usesPreviousHeaders = previous.length > 0 && current.slice(0, previous.length).join('|') === previous.join('|') && current.slice(previous.length).every(function (value) { return value === ''; });
	const italianPrevious = MI_INTESTAZIONI_PRECEDENTI[sheet.getName()] || [];
	const usesItalianPrevious = italianPrevious.length > 0 && current.slice(0, italianPrevious.length).join('|') === italianPrevious.join('|') && current.slice(italianPrevious.length).every(function (value) { return value === ''; });
	const immediatelyPrevious = sheet.getName() === MI_SHEETS.PARTICIPANTS ? headers.slice(0, -2) : ([MI_SHEETS.REGISTRATIONS, MI_SHEETS.PAYMENTS].indexOf(sheet.getName()) >= 0 ? headers.slice(0, -1) : []);
	const usesImmediatelyPrevious = immediatelyPrevious.length > 0 && current.slice(0, immediatelyPrevious.length).join('|') === immediatelyPrevious.join('|') && current.slice(immediatelyPrevious.length).every(function (value) { return value === ''; });
  if (hasData && current.join('|') !== headers.join('|') && !usesPreviousHeaders && !usesItalianPrevious && !usesImmediatelyPrevious) {
    throw new Error('Intestazioni inattese nel foglio ' + sheet.getName() + '. Intervento manuale richiesto.');
  }
  if (hasData && sheet.getName() === MI_SHEETS.PARTICIPANTS && (usesItalianPrevious || usesPreviousHeaders)) {
    const oldRows = sheet.getRange(2, 1, sheet.getLastRow() - 1, 5).getValues();
    const migratedRows = oldRows.map(function (row) { return [row[0], row[1], '', 0, row[2], row[3], row[4], '[]']; });
    sheet.getRange(2, 1, migratedRows.length, headers.length).clearContent().setValues(migratedRows);
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

function inizializzaConfigurazione_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.CONFIG);
	if (sheet.getLastRow() > 1) {
		const keys = sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getDisplayValues().map(function (row) { return String(row[0]); });
		let versionIndex = keys.indexOf('versione_schema');
		if (versionIndex < 0) versionIndex = keys.indexOf('schema_version');
		if (versionIndex >= 0) sheet.getRange(versionIndex + 2, 2).setValue(MI_SCHEMA_VERSION);
		return;
	}
  sheet.getRange(2, 1, 5, 3).setValues([
    ['versione_schema', MI_SCHEMA_VERSION, 'Versione della struttura Workspace'],
    ['ambiente', 'ANTEPRIMA', 'Anteprima finché il collaudo non è concluso'],
    ['fuso_orario', 'Europe/Rome', 'Fuso operativo'],
    ['valuta', 'EUR', 'Valuta degli importi'],
    ['modalita_email', 'ANTEPRIMA', 'Nessuna email reale in questa fase']
  ]);
}

function inizializzaConvalidaPagamenti_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
  const index = creaIndiceIntestazioni_(sheet);
  const rowCount = Math.max(1, sheet.getMaxRows() - 1);
  sheet.getRange(2, index.tipo_movimento + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.tipo_rata + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.fonte_pagamento + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.stato_convalida + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['IN_ATTESA', 'CONVALIDATO', 'RIFIUTATO', 'DA_VERIFICARE'], true).setAllowInvalid(false).build());
  sheet.getRange(2, index.data_effettiva + 1, rowCount, 1).setNumberFormat('yyyy-mm-dd hh:mm');
  sheet.getRange(2, index.importo + 1, rowCount, 1).setNumberFormat('#,##0.00 [$€-it-IT]');
}

function applicaProtezioniConAvviso_() {
  const editable = [MI_SHEETS.PAYMENT_INTAKE, MI_SHEETS.SECRETARY_OPERATIONS, MI_SHEETS.OPERATIONAL_VIEWS, MI_SHEETS.ACCOMMODATIONS];
  Object.keys(MI_HEADERS).forEach(function (name) {
    const sheet = ottieniSchedaObbligatoria_(name);
    sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).forEach(function (protection) { protection.remove(); });
    if (editable.indexOf(name) < 0) sheet.protect().setDescription('Gestito da Modulo Iscrizioni').setWarningOnly(true);
  });
}
