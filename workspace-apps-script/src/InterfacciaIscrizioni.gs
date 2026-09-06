const MI_MANUAL_REGISTRATION_FORM = Object.freeze({
  VERSION: '1', SHEET: 'Registra iscrizione', EVENT: 'B7',
  BUYER_FIRST: 'B10', BUYER_LAST: 'D10', BUYER_EMAIL: 'F10', BUYER_PHONE: 'H10',
  FIRST_ROW: 15, LAST_ROW: 34, KEY_ROW: 13, HEADER_ROW: 14,
  CONFIRM: 'B37', PRIVACY: 'B38', STATUS: 'B41', REQUEST_ID: 'AZ1', MARKER: 'AZ2'
});

function inizializzaInterfacciaIscrizioni_() {
  const spreadsheet = ottieniFoglioDiLavoroAssociato_();
  let sheet = spreadsheet.getSheetByName(MI_MANUAL_REGISTRATION_FORM.SHEET);
  const nuova = !sheet;
  if (!sheet) sheet = spreadsheet.insertSheet(MI_MANUAL_REGISTRATION_FORM.SHEET, 0);
  if (nuova || String(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.MARKER).getValue()) !== MI_MANUAL_REGISTRATION_FORM.VERSION) costruisciInterfacciaIscrizioni_(sheet);
  aggiornaElencoEventiIscrizioneManuale_(sheet);
  return sheet;
}

function costruisciInterfacciaIscrizioni_(sheet) {
  sheet.clear(); sheet.setHiddenGridlines(true); sheet.setFrozenRows(14);
  sheet.setColumnWidth(1, 28); sheet.setColumnWidths(2, 8, 135);
  sheet.getRange('B1:I2').merge().setValue('Registra un’iscrizione manuale').setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(20).setVerticalAlignment('middle');
  sheet.getRange('B3:I3').merge().setValue('WordPress verifica apertura, disponibilità e capienza prima di assegnare i posti.').setFontColor('#657084');
  [['B5:I5','1 · Evento'],['B8:I8','2 · Referente'],['B12:I12','3 · Partecipanti'],['B36:I36','4 · Verifica e registra'],['B40:I40','Esito']].forEach(function (item) {
    sheet.getRange(item[0]).merge().setValue(item[1]).setBackground('#e8edf7').setFontColor('#17224a').setFontWeight('bold');
  });
  sheet.getRange('B6:I6').merge().setValue('Evento pubblicato'); sheet.getRange('B7:I7').merge();
  [['B9:C9','Nome'],['D9:E9','Cognome'],['F9:G9','Email'],['H9:I9','Cellulare']].forEach(function(item){ sheet.getRange(item[0]).merge().setValue(item[1]); });
  ['B10:C10','D10:E10','F10:G10','H10:I10'].forEach(function(a1){ sheet.getRange(a1).merge().setBackground('#f8fafc'); });
  sheet.getRange('B14').setValue('Nome'); sheet.getRange('C14').setValue('Cognome'); sheet.getRange('D14').setValue('Tipologia');
  sheet.getRange('B15:D34').setBackground('#f8fafc').setBorder(true,true,true,true,true,true,'#d7dde6',SpreadsheetApp.BorderStyle.SOLID);
  sheet.getRange('B37').insertCheckboxes().setValue(false); sheet.getRange('C37:I37').merge().setValue('Ho verificato evento, referente e partecipanti.');
  sheet.getRange('B38').insertCheckboxes().setValue(false); sheet.getRange('C38:I38').merge().setValue('Il referente ha ricevuto l’informativa e ha autorizzato la registrazione dei dati.');
  sheet.getRange('B41:I42').merge().setValue('Scegli l’evento e aggiorna i campi prima di registrare.').setWrap(true).setBackground('#eaf5ef').setFontColor('#25745e');
  sheet.getRangeList(['B6:I6','B9:I9','B14:I14']).setFontColor('#657084').setFontWeight('bold').setFontSize(10);
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('regui'));
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.MARKER).setValue(MI_MANUAL_REGISTRATION_FORM.VERSION);
  sheet.hideColumns(52); sheet.hideRows(MI_MANUAL_REGISTRATION_FORM.KEY_ROW);
}

function aggiornaElencoEventiIscrizioneManuale_(sheet) {
  const titoli = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).filter(function(row){ return String(row.stato || '').toUpperCase() !== 'ANNULLATO'; }).map(function(row){ return String(row.titolo || '').trim(); }).filter(String);
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.EVENT).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(titoli, true).setAllowInvalid(false).build());
}

function apriIscrizioneManuale() {
  const sheet = inizializzaInterfacciaIscrizioni_(); sheet.activate(); sheet.setActiveSelection(MI_MANUAL_REGISTRATION_FORM.EVENT);
}

function eventoIscrizioneManuale_(titolo) {
  const matches = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).filter(function(row){ return String(row.titolo || '') === titolo; });
  if (matches.length !== 1) throw new Error(matches.length ? 'Esistono più eventi con questo titolo: rinominali prima di continuare.' : 'Evento non trovato.');
  return matches[0];
}

function aggiornaSchemaIscrizioneManuale() {
  const sheet = inizializzaInterfacciaIscrizioni_();
  const titolo = normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.EVENT).getValue(), 180);
  if (!titolo) throw new Error('Scegli prima un evento.');
  const event = eventoIscrizioneManuale_(titolo);
  const schema = inviaComandoWordPress_('GET_MANUAL_REGISTRATION_SCHEMA', { event_id: String(event.id_evento) });
  if (String(schema.registration_state) !== 'OPEN') throw new Error('Le iscrizioni dell’evento non sono aperte: ' + String(schema.registration_state || 'stato non disponibile') + '.');
  sheet.getRange(13, 2, 22, 49).clearContent().clearDataValidations();
  sheet.getRange('B14').setValue('Nome'); sheet.getRange('C14').setValue('Cognome'); sheet.getRange('D14').setValue('Tipologia');
  const tickets = (schema.ticket_types || []).map(function(item){ return { code:String(item.code), name:String(item.name || item.code) }; });
  if (!tickets.length) throw new Error('L’evento non contiene tipologie utilizzabili.');
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.FIRST_ROW, 4, 20, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(tickets.map(function(item){return item.name;}), true).setAllowInvalid(false).build());
  let column = 5;
  (schema.participant_fields || []).slice(0, 20).forEach(function(field){
    sheet.getRange(MI_MANUAL_REGISTRATION_FORM.KEY_ROW, column).setValue('field:' + String(field.key));
    sheet.getRange(MI_MANUAL_REGISTRATION_FORM.HEADER_ROW, column).setValue(String(field.label || field.key) + (field.required ? ' *' : ''));
    const range = sheet.getRange(MI_MANUAL_REGISTRATION_FORM.FIRST_ROW, column, 20, 1);
    if (field.type === 'yesno') range.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['Sì','No'], true).setAllowInvalid(false).build());
    else if (field.type === 'select' && Array.isArray(field.options)) range.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(field.options.map(String), true).setAllowInvalid(false).build());
    column += 1;
  });
  (schema.options || []).filter(function(option){ return String(option.scope).toUpperCase() === 'TICKET'; }).slice(0, 20).forEach(function(option){
    sheet.getRange(MI_MANUAL_REGISTRATION_FORM.KEY_ROW, column).setValue('option:' + String(option.code));
    sheet.getRange(MI_MANUAL_REGISTRATION_FORM.HEADER_ROW, column).setValue(String(option.name || option.code));
    sheet.getRange(MI_MANUAL_REGISTRATION_FORM.FIRST_ROW, column, 20, 1).insertCheckboxes(); column += 1;
  });
  const width = Math.max(3, column - 2); sheet.getRange(14, 2, 21, width).setBorder(true,true,true,true,true,true,'#d7dde6',SpreadsheetApp.BorderStyle.SOLID);
  sheet.getRange(14, 2, 1, width).setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  sheet.setColumnWidths(2, width, 135); sheet.getRange(MI_MANUAL_REGISTRATION_FORM.STATUS).setValue('Campi aggiornati. Compila una riga per ogni partecipante.');
  PropertiesService.getDocumentProperties().setProperty('MI_MANUAL_REGISTRATION_SCHEMA', JSON.stringify({ event_id:String(event.id_evento), tickets:tickets, columns:sheet.getRange(13, 2, 1, width).getValues()[0] }));
  sheet.activate(); return { ok:true, event_id:String(event.id_evento), columns:width };
}

function registraIscrizioneManuale() {
  const sheet = inizializzaInterfacciaIscrizioni_();
  if (sheet.getRange(MI_MANUAL_REGISTRATION_FORM.CONFIRM).getValue() !== true || sheet.getRange(MI_MANUAL_REGISTRATION_FORM.PRIVACY).getValue() !== true) throw new Error('Spunta entrambe le conferme prima di registrare.');
  const cache = JSON.parse(PropertiesService.getDocumentProperties().getProperty('MI_MANUAL_REGISTRATION_SCHEMA') || '{}');
  const event = eventoIscrizioneManuale_(normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.EVENT).getValue(), 180));
  if (String(cache.event_id || '') !== String(event.id_evento)) throw new Error('Aggiorna i campi dopo aver scelto l’evento.');
  const columns = cache.columns || [];
  const ticketByName = (cache.tickets || []).reduce(function(result,item){ result[String(item.name)] = String(item.code); return result; }, {});
  const values = sheet.getRange(MI_MANUAL_REGISTRATION_FORM.FIRST_ROW, 2, 20, columns.length).getValues();
  const counts = {}; const participants = [];
  values.forEach(function(row){
    const first = normalizzaTesto_(row[0],80), last = normalizzaTesto_(row[1],80); if (!first && !last) return;
    if (!first || !last) throw new Error('Ogni partecipante deve avere nome e cognome.');
    const ticket = ticketByName[String(row[2] || '')]; if (!ticket) throw new Error('Scegli la tipologia per ogni partecipante.');
    counts[ticket] = (counts[ticket] || 0) + 1; const fields = {}, options = {};
    columns.forEach(function(key,index){
      if (String(key).indexOf('field:') === 0 && row[index] !== '') fields[String(key).slice(6)] = row[index];
      if (String(key).indexOf('option:') === 0 && row[index] === true) options[String(key).slice(7)] = 1;
    });
    participants.push({ ticket_type_code:ticket, ticket_index:counts[ticket], first_name:first, last_name:last, fields:fields, options:options });
  });
  if (!participants.length) throw new Error('Inserisci almeno un partecipante.');
  const tickets = Object.keys(counts).map(function(code){ return { code:code, quantity:counts[code] }; });
  const buyer = { first_name:normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.BUYER_FIRST).getValue(),80), last_name:normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.BUYER_LAST).getValue(),80), email:normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.BUYER_EMAIL).getValue(),254), phone:normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.BUYER_PHONE).getValue(),40) };
  const result = inviaComandoWordPress_('CREATE_MANUAL_REGISTRATION', { event_id:String(event.id_evento), idempotency_key:normalizzaTesto_(sheet.getRange(MI_MANUAL_REGISTRATION_FORM.REQUEST_ID).getValue(),64), operator_label:normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA',120), registration:{ tickets:tickets, participants:participants, buyer:buyer, order_options:{}, privacy_accepted:true, marketing_accepted:false } });
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.STATUS).setValue('Iscrizione ' + String(result.order_code) + ' registrata in WordPress. Replica Workspace: ' + String(result.workspace_status || 'PENDING') + '.');
  sheet.getRange(MI_MANUAL_REGISTRATION_FORM.CONFIRM).setValue(false); sheet.getRange(MI_MANUAL_REGISTRATION_FORM.PRIVACY).setValue(false); sheet.getRange(MI_MANUAL_REGISTRATION_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('regui'));
  SpreadsheetApp.getActive().toast('Iscrizione registrata con controllo dei posti.', 'Modulo iscrizioni', 6); return result;
}
