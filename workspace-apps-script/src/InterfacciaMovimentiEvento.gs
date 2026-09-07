const MI_EVENT_MOVEMENT_FORM = Object.freeze({
  VERSION: '2', SHEET: 'Registra movimento', ORDER: 'B7', ORDER_STATUS: 'F7', EVENT: 'B9', REFERENT: 'E9',
  TOTAL_VALUE: 'C10', PAID_VALUE: 'E10', BALANCE_VALUE: 'G10', TRANSACTION: 'B13', INSTALLMENT: 'D13', DATE: 'F13',
  AMOUNT: 'B15', SOURCE: 'D15', REFERENCE: 'F15', OPERATOR: 'B19', NOTE: 'B21', COMMAND: 'E25', STATUS: 'B28',
  REQUEST_ID: 'Z1', MARKER: 'Z2', EVENT_ID: 'Z3', CHOICE_LABELS: 'AA2:AA', CHOICE_CODES: 'AB2:AB'
});

/** Crea nel file dell'evento un modulo visuale; DB_MODULI resta il libro autorevole. */
function preparaInterfacciaMovimentoEvento_(foglio, idEvento) {
  let sheet = foglio.getSheetByName(MI_EVENT_MOVEMENT_FORM.SHEET);
  const nuova = !sheet;
  if (!sheet) sheet = foglio.insertSheet(MI_EVENT_MOVEMENT_FORM.SHEET, 0);
  if (nuova || String(sheet.getRange(MI_EVENT_MOVEMENT_FORM.MARKER).getValue()) !== MI_EVENT_MOVEMENT_FORM.VERSION) costruisciInterfacciaMovimentoEvento_(sheet, idEvento);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).setValue(String(idEvento));
  aggiornaScelteInterfacciaMovimentoEvento_(sheet, idEvento);
  assicuraTriggerInterfacciaMovimentoEvento_(foglio);
  return sheet;
}

/** ZERO è la scelta esplicita “Evento totalmente gratuito”; i dati precedenti usavano REGISTRATION_ONLY. */
function eventoPrevedeMovimenti_(idEvento) {
  const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (item) { return String(item.id_evento) === String(idEvento); });
  if (!evento) throw new Error('Evento non trovato in DB_MODULI.');
  return ['ZERO', 'REGISTRATION_ONLY'].indexOf(String(evento.modalita_prezzo || '').toUpperCase()) < 0;
}

function configuraSchedeEconomicheEvento_(foglio, idEvento) {
  if (!eventoPrevedeMovimenti_(idEvento)) {
    ['Registra movimento', 'Pagamenti'].forEach(function (nome) {
      const scheda = foglio.getSheetByName(nome);
      if (scheda && !scheda.isSheetHidden() && foglio.getSheets().length > 1) scheda.hideSheet();
    });
    rimuoviTriggerInterfacciaMovimentoEvento_(foglio);
    return { pagamenti: false };
  }
  const pagamenti = preparaPagamentiEvento_(foglio, idEvento);
  if (pagamenti.isSheetHidden()) pagamenti.showSheet();
  const modulo = preparaInterfacciaMovimentoEvento_(foglio, idEvento);
  if (modulo.isSheetHidden()) modulo.showSheet();
  return { pagamenti: true };
}

function costruisciInterfacciaMovimentoEvento_(sheet, idEvento) {
  sheet.getDataRange().breakApart();
  sheet.clear();
  if (sheet.getMaxColumns() < 28) sheet.insertColumnsAfter(sheet.getMaxColumns(), 28 - sheet.getMaxColumns());
  sheet.setHiddenGridlines(true).setFrozenRows(4);
  sheet.setColumnWidth(1, 28); sheet.setColumnWidths(2, 7, 120);
  sheet.setRowHeights(1, 2, 36).setRowHeight(3, 34).setRowHeights(5, 25, 32).setRowHeight(7, 38).setRowHeight(10, 40).setRowHeight(15, 38).setRowHeight(25, 40).setRowHeights(28, 2, 34);
  sheet.getRange('B1:H2').merge().setValue('Registra un movimento').setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(20).setHorizontalAlignment('left').setVerticalAlignment('middle');
  sheet.getRange('B3:H3').merge().setValue('Scegli la prenotazione, controlla il saldo e registra il movimento senza modificare lo storico.').setFontColor('#657084').setFontSize(11);
  [['B5:H5', '1 · Prenotazione'], ['B11:H11', '2 · Movimento'], ['B17:H17', '3 · Tracciabilità'], ['B24:H24', '4 · Registra']].forEach(function (item) {
    sheet.getRange(item[0]).merge().setValue(item[1]).setBackground('#e8edf7').setFontColor('#17224a').setFontWeight('bold').setFontSize(12);
  });
  sheet.getRange('B6:E6').merge().setValue('Prenotazione *'); sheet.getRange('F6:H6').merge().setValue('Stato');
  sheet.getRange('B7:E7').merge(); sheet.getRange('F7:H7').merge();
  sheet.getRange('B8:D8').merge().setValue('Evento'); sheet.getRange('E8:H8').merge().setValue('Referente');
  sheet.getRange('B9:D9').merge(); sheet.getRange('E9:H9').merge();
  sheet.getRange('B10').setValue('Totale'); sheet.getRange('D10').setValue('Versato'); sheet.getRange('F10').setValue('Residuo'); sheet.getRange('G10:H10').merge();
  sheet.getRange('B12:C12').merge().setValue('Tipo movimento'); sheet.getRange('D12:E12').merge().setValue('Rata'); sheet.getRange('F12:H12').merge().setValue('Data effettiva');
  sheet.getRange('B13:C13').merge().setValue('INCASSO'); sheet.getRange('D13:E13').merge().setValue('CAPARRA'); sheet.getRange('F13:H13').merge().setValue(new Date()).setNumberFormat('dd/mm/yyyy');
  sheet.getRange('B14:C14').merge().setValue('Importo *'); sheet.getRange('D14:E14').merge().setValue('Metodo *'); sheet.getRange('F14:H14').merge().setValue('Riferimento (facoltativo)');
  sheet.getRange('B15:C15').merge().setNumberFormat('#,##0.00 [$€-it-IT]'); sheet.getRange('D15:E15').merge().setValue('BONIFICO'); sheet.getRange('F15:H15').merge();
  sheet.getRange('B18:H18').merge().setValue('Operatore'); sheet.getRange('B19:H19').merge().setValue(normalizzaTesto_(Session.getActiveUser().getEmail(), 120));
  sheet.getRange('B20:H20').merge().setValue('Nota amministrativa'); sheet.getRange('B21:H22').merge().setWrap(true).setVerticalAlignment('top');
  sheet.getRange('B25:D25').merge().setValue('* Campi obbligatori').setFontColor('#657084').setFontSize(10).setVerticalAlignment('middle');
  sheet.getRange('E25:H25').merge().setValue('').setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['REGISTRA MOVIMENTO'], true).setAllowInvalid(false).build()).setBackground('#8b98aa').setFontColor('#ffffff').setFontWeight('bold').setHorizontalAlignment('center').setVerticalAlignment('middle');
  sheet.getRange('B26:H26').merge().setValue('Il comando si attiva dopo la scelta della prenotazione. Il saldo viene ricontrollato prima del salvataggio.').setFontColor('#657084').setFontSize(10).setHorizontalAlignment('right');
  sheet.getRange('B27:H27').merge().setValue('Esito'); sheet.getRange('B28:H29').merge().setValue('Scegli una prenotazione per caricare il riepilogo.').setWrap(true);
  sheet.getRangeList(['B6:H6', 'B8:H8', 'B12:H12', 'B14:H14', 'B18:H18', 'B20:H20', 'B27:H27']).setFontColor('#657084').setFontWeight('bold').setFontSize(10);
  const superfici = sheet.getRangeList(['B7:H7', 'B9:H10', 'B13:H15', 'B19:H19', 'B21:H22', 'B28:H29']);
  superfici.setBackground('#ffffff').setFontColor('#172033');
  superfici.getRanges().forEach(function (range) { range.setBorder(true, true, true, true, false, false, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID); });
  sheet.getRangeList(['B7:E7', 'B13:C13', 'D13:E13', 'F13:H13', 'B15:C15', 'D15:E15', 'F15:H15', 'B19:H19', 'B21:H22']).setBackground('#fffdf3');
  sheet.getRangeList(['F7:H7', 'B9:H10']).setBackground('#f3f6f9');
  sheet.getRange('F10:H10').setBackground('#fff8e6');
  sheet.getRange('B28:H29').setBackground('#f8fafc').setFontColor('#475569');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pevui'));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.MARKER).setValue(MI_EVENT_MOVEMENT_FORM.VERSION);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).setValue(String(idEvento));
  sheet.hideColumns(26, 3);
}

function prenotazioniInterfacciaMovimentoEvento_(idEvento) {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (item) { return String(item.id_evento) === String(idEvento); });
}

function creaSceltePrenotazioniInterfacciaMovimentoEvento_(prenotazioni) {
  const ricorrenze = {};
  prenotazioni.forEach(function (item) {
    const nome = [item.nome_referente, item.cognome_referente].map(function (parte) { return normalizzaTesto_(parte, 80); }).filter(String).join(' ') || 'Prenotazione senza nominativo';
    ricorrenze[nome] = (ricorrenze[nome] || 0) + 1;
  });
  const progressivi = {};
  return prenotazioni.map(function (item) {
    const nome = [item.nome_referente, item.cognome_referente].map(function (parte) { return normalizzaTesto_(parte, 80); }).filter(String).join(' ') || 'Prenotazione senza nominativo';
    progressivi[nome] = (progressivi[nome] || 0) + 1;
    return { etichetta: ricorrenze[nome] > 1 ? nome + ' (' + progressivi[nome] + ')' : nome, codice: String(item.codice_ordine) };
  }).sort(function (a, b) { return a.etichetta.localeCompare(b.etichetta, 'it', { sensitivity: 'base' }); });
}

function aggiornaScelteInterfacciaMovimentoEvento_(sheet, idEvento) {
  const prenotazioni = prenotazioniInterfacciaMovimentoEvento_(idEvento).filter(function (item) { return !!String(item.codice_ordine || ''); });
  const scelte = creaSceltePrenotazioniInterfacciaMovimentoEvento_(prenotazioni);
  const righeDaPulire = Math.max(sheet.getLastRow(), scelte.length + 1, 2);
  sheet.getRange(1, 27, righeDaPulire, 2).clearContent();
  sheet.getRange('AA1:AB1').setValues([['Etichetta prenotazione', 'Codice prenotazione']]);
  if (scelte.length) sheet.getRange(2, 27, scelte.length, 2).setValues(scelte.map(function (item) { return [item.etichetta, item.codice]; }));
  const cella = sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER).clearDataValidations();
  if (scelte.length) cella.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInRange(sheet.getRange(2, 27, scelte.length, 1), true).setAllowInvalid(false).build());
  cella.setNote('Digita le prime lettere del nome o del cognome per restringere l’elenco. Il codice prenotazione resta associato internamente.');
}

function codiceSceltaInterfacciaMovimentoEvento_(sheet, scelta) {
  const testo = normalizzaTesto_(scelta, 180);
  if (!testo) return '';
  const ultimaRiga = Math.max(2, sheet.getLastRow());
  const mappa = sheet.getRange(2, 27, ultimaRiga - 1, 2).getDisplayValues();
  const corrispondenza = mappa.find(function (riga) { return riga[0] === testo; });
  return corrispondenza ? String(corrispondenza[1]) : testo;
}

function aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento) {
  const scelta = sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER).getValue();
  const codice = codiceSceltaInterfacciaMovimentoEvento_(sheet, scelta);
  const ordine = prenotazioniInterfacciaMovimentoEvento_(idEvento).find(function (item) { return String(item.codice_ordine) === codice; });
  if (!ordine) throw new Error('Scegli una prenotazione valida di questo evento.');
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (item) { return String(item.codice_ordine) === codice; });
  const versato = Math.max(0, pagamenti.reduce(function (totale, item) { const importo = Math.max(0, Number(item.importo_centesimi) || 0); return totale + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -importo : importo); }, 0));
  const totale = Math.max(0, Number(ordine.totale_centesimi) || 0);
  const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (item) { return String(item.id_evento) === String(idEvento); });
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER_STATUS).setValue(normalizzaTesto_(ordine.stato, 40));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT).setValue(evento ? String(evento.titolo || '') : 'Evento ' + idEvento);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REFERENT).setValue([ordine.nome_referente, ordine.cognome_referente].filter(String).join(' '));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.TOTAL_VALUE).setValue(totale / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.PAID_VALUE).setValue(versato / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.BALANCE_VALUE).setValue(Math.max(0, totale - versato) / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  applicaConfigurazioneInterfacciaMovimentoEvento_(sheet, ordine, totale, versato);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#f0f8f3').setFontColor('#19764a').setValue('Dati pronti. Completa importo e metodo, quindi usa il comando finale.');
  return { ordine: ordine, totale: totale, versato: versato };
}

function applicaConfigurazioneInterfacciaMovimentoEvento_(sheet, ordine, totale, versato) {
  const residuo = Math.max(0, totale - versato);
  const movimenti = [];
  if (residuo > 0 && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(ordine.stato || '').toUpperCase()) < 0) movimenti.push('INCASSO');
  if (versato > 0) movimenti.push('RIMBORSO', 'STORNO');
  const modalita = String(ordine.modalita_economica || '').toUpperCase();
  const rate = modalita === 'DEPOSIT_BALANCE' ? ['CAPARRA', 'INTERMEDIO', 'SALDO'] : ['INTERO'];
  let metodi = [];
  try { metodi = JSON.parse(String(ordine.fonti_pagamento_json || '[]')); } catch (error) { metodi = []; }
  const mappaMetodi = { BANK_TRANSFER: 'BONIFICO', TRANSFER: 'BONIFICO', BONIFICO: 'BONIFICO', CARD: 'CARTA', CARTA: 'CARTA', PAYPAL: 'CARTA', CASH: 'CONTANTE', CONTANTE: 'CONTANTE' };
  metodi = metodi.map(function (item) { return mappaMetodi[String(item).toUpperCase()] || ''; }).filter(function (item, index, elenco) { return item && elenco.indexOf(item) === index; });
  if (!metodi.length) metodi = MI_PAYMENT_ENUMS.paymentSources.slice();
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION), movimenti, 'Nessun movimento disponibile');
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT), rate, '');
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE), metodi, '');
  const comando = sheet.getRange(MI_EVENT_MOVEMENT_FORM.COMMAND).clearContent().clearDataValidations();
  if (movimenti.length) comando.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['REGISTRA MOVIMENTO'], true).setAllowInvalid(false).build()).setBackground('#1d4ed8').setFontColor('#ffffff');
  else comando.setBackground('#8b98aa').setFontColor('#ffffff');
}

function impostaElencoInterfacciaMovimentoEvento_(cella, valori, vuoto) {
  cella.clearDataValidations();
  if (!valori.length) { cella.setValue(vuoto || ''); return; }
  cella.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(valori, true).setAllowInvalid(false).build());
  if (valori.indexOf(String(cella.getValue())) < 0) cella.setValue(valori[0]);
}

function registraMovimentoInterfacciaEvento_(foglio, sheet, idEvento) {
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#fff9e8').setFontColor('#9a6700').setValue('Registrazione in corso. Attendi: il movimento viene verificato e salvato una sola volta.');
  SpreadsheetApp.flush();
  const collegamento = trovaCollegamentoFoglioOperativo_(String(idEvento));
  if (String(collegamento.id_foglio) !== String(foglio.getId())) throw new Error('Questo file non è il foglio operativo registrato per l’evento.');
  const riepilogo = aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
  const risultato = registraPagamentoValidato_({
    intake_id: sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).getValue(), order_code: riepilogo.ordine.codice_ordine,
    transaction_kind: sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION).getValue(), installment_kind: sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT).getValue(),
    effective_at: sheet.getRange(MI_EVENT_MOVEMENT_FORM.DATE).getValue(), amount: sheet.getRange(MI_EVENT_MOVEMENT_FORM.AMOUNT).getValue(),
    payment_source: sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE).getValue(), external_reference: sheet.getRange(MI_EVENT_MOVEMENT_FORM.REFERENCE).getValue(),
    operator_label: sheet.getRange(MI_EVENT_MOVEMENT_FORM.OPERATOR).getValue(), administrative_note: sheet.getRange(MI_EVENT_MOVEMENT_FORM.NOTE).getValue(), recording_channel: 'WORKSPACE_UI'
  });
  if (risultato.status !== 'CONVALIDATO') throw new Error(risultato.message);
  aggiornaProiezionePagamentiPrenotazioneEvento_(foglio, String(idEvento), String(riepilogo.ordine.codice_ordine));
  // La chiave completata non deve mai restare riutilizzabile se la pulizia del modulo fallisce a metà.
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pevui'));
  sheet.getRangeList([MI_EVENT_MOVEMENT_FORM.AMOUNT, MI_EVENT_MOVEMENT_FORM.REFERENCE, MI_EVENT_MOVEMENT_FORM.NOTE]).clearContent();
  aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#eaf5ef').setFontColor('#25745e').setValue(risultato.message + ' Lo storico Pagamenti è stato aggiornato.');
  foglio.toast('Movimento registrato e controllato.', 'Modulo iscrizioni', 5);
}

/** Trigger installabile: opera soltanto sulle due celle comando del modulo evento. */
function gestisciModificaInterfacciaMovimentoEvento(e) {
  if (!e || !e.source || !e.range || e.range.getSheet().getName() !== MI_EVENT_MOVEMENT_FORM.SHEET) return;
  const sheet = e.range.getSheet();
  const idEvento = String(sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).getValue() || '');
  if (!idEvento) return;
  try {
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.ORDER) aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.COMMAND && String(e.value || '') === 'REGISTRA MOVIMENTO') registraMovimentoInterfacciaEvento_(e.source, sheet, idEvento);
  } catch (error) {
    sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#fff2f0').setFontColor('#b42318').setValue('Movimento non registrato. ' + normalizzaTesto_(error && error.message ? error.message : error, 270));
    e.source.toast('Movimento non registrato: controlla i dati.', 'Modulo iscrizioni', 6);
  } finally {
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.COMMAND) sheet.getRange(MI_EVENT_MOVEMENT_FORM.COMMAND).clearContent();
  }
}

function assicuraTriggerInterfacciaMovimentoEvento_(foglio) {
  const presente = ScriptApp.getProjectTriggers().some(function (trigger) { return trigger.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(trigger.getTriggerSourceId() || '') === String(foglio.getId()); });
  if (!presente) ScriptApp.newTrigger('gestisciModificaInterfacciaMovimentoEvento').forSpreadsheet(foglio).onEdit().create();
  return !presente;
}

function rimuoviTriggerInterfacciaMovimentoEvento_(foglio) {
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(trigger.getTriggerSourceId() || '') === String(foglio.getId())) ScriptApp.deleteTrigger(trigger);
  });
}

/** Migrazione manuale e ripetibile dei file evento già esistenti; non registra movimenti. */
function preparaInterfacceMovimentiFogliEventi() {
  const collegamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(function (item) { return !!item.id_foglio; });
  return collegamenti.map(function (item) {
    try {
      const foglio = SpreadsheetApp.openById(String(item.id_foglio));
      const configurazione = configuraSchedeEconomicheEvento_(foglio, String(item.id_evento));
      return { id_evento: String(item.id_evento), id_foglio: String(item.id_foglio), pagamenti: configurazione.pagamenti, ok: true };
    } catch (error) {
      return { id_evento: String(item.id_evento), id_foglio: String(item.id_foglio), ok: false, errore: normalizzaTesto_(error && error.message ? error.message : error, 240) };
    }
  });
}
