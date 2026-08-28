function apriSchedaPrenotazione() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'LISTA'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Segreteria eventi'));
}

function apriSegreteriaWeb() {
  const configured = PropertiesService.getScriptProperties().getProperty('MI_SECRETARY_WEBAPP_URL') || '';
  const serviceUrl = configured || ScriptApp.getService().getUrl() || '';
  if (!/^https:\/\/script\.google\.com\//.test(serviceUrl)) {
    SpreadsheetApp.getUi().alert('La Web App della segreteria non è ancora stata distribuita.');
    return;
  }
  const separator = serviceUrl.indexOf('?') >= 0 ? '&' : '?';
  const url = serviceUrl + separator + 'view=segreteria';
  const safeUrl = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
  const html = HtmlService.createHtmlOutput(
    '<!doctype html><html><head><base target="_blank"><meta name="viewport" content="width=device-width,initial-scale=1"></head>' +
    '<body style="font:16px Arial,sans-serif;padding:22px;color:#172033"><h2 style="margin-top:0">Segreteria eventi</h2>' +
    '<p>Apri la gestione in una pagina web autonoma.</p><p><a href="' + safeUrl + '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#17224a;color:#fff;text-decoration:none;font-weight:700">Apri segreteria</a></p></body></html>'
  ).setWidth(390).setHeight(210);
  SpreadsheetApp.getUi().showModalDialog(html, 'Apri segreteria');
}

function configuraUrlSegreteriaWeb() {
  const ui = SpreadsheetApp.getUi();
  const response = ui.prompt('URL Web App segreteria', 'Incolla l’URL /exec della distribuzione riservata alla segreteria.', ui.ButtonSet.OK_CANCEL);
  if (response.getSelectedButton() !== ui.Button.OK) return;
  const url = normalizzaTesto_(response.getResponseText(), 500);
  if (!/^https:\/\/script\.google\.com\/macros\/s\/[A-Za-z0-9_-]+\/exec\/?$/.test(url)) throw new Error('URL Web App non valido.');
  PropertiesService.getScriptProperties().setProperty('MI_SECRETARY_WEBAPP_URL', url.replace(/\/$/, ''));
  ui.alert('Collegamento alla segreteria Web salvato.');
}

function apriDialogoPrenotazione(orderCode) {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'PRENOTAZIONE'; template.codiceOrdineIniziale = normalizzaTesto_(orderCode, 64); template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showModelessDialog(template.evaluate().setWidth(680).setHeight(720), 'Scheda prenotazione');
  return { ok: true };
}

function apriConfigurazioneElencoOperativo() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ELENCO'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Elenco operativo'));
}

function apriAssegnazioniEvento() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ASSEGNAZIONI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Assegnazioni collettive'));
}

function apriConfigurazioneModelliReport() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'REPORT'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Modelli report'));
}

function apriGestioneGruppi() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'GRUPPI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Gruppi'));
}

function apriComunicazioniOperative() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'COMUNICAZIONI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Comunicazioni operative'));
}

function configuraEndpointWordPress() {
  const ui = SpreadsheetApp.getUi();
  const response = ui.prompt('Collegamento WordPress', 'Inserisci l’endpoint HTTPS /wp-json/modulo-iscrizioni/v1/workspace/commands. Resterà nelle proprietà private dello script.', ui.ButtonSet.OK_CANCEL);
  if (response.getSelectedButton() !== ui.Button.OK) return;
  const url = normalizzaTesto_(response.getResponseText(), 500);
  if (!/^https:\/\/[^\s]+\/wp-json\/modulo-iscrizioni\/v1\/workspace\/commands\/?$/.test(url)) throw new Error('Endpoint WordPress non valido.');
  PropertiesService.getScriptProperties().setProperty('MI_WORDPRESS_COMMAND_URL', url);
  ui.alert('Collegamento WordPress salvato.');
}

function caricaContestoSegreteria() {
  const events = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).map(function (row) { return { id: String(row.id_evento || ''), title: String(row.titolo || row.id_evento || ''), status: String(row.stato || '') }; });
  const groups = elencaGruppi();
  const views = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS)).reduce(function (result, row) { try { result[String(row.id_evento)] = JSON.parse(String(row.campi_json || '[]')); } catch (error) { result[String(row.id_evento)] = []; } return result; }, {});
  return { events: events, groups: groups, views: views, report_models: elencaModelliReport(), available_fields: campiElencoOperativo_(), active_operator: normalizzaTesto_(Session.getActiveUser().getEmail(), 120), email_mode: String(ottieniConfigurazione_('modalita_email', 'ANTEPRIMA')).toUpperCase() };
}

function cercaPrenotazioniSegreteria(form) {
  form = form || {};
  const query = normalizzaTesto_(form.query, 120).toLowerCase();
  const eventId = normalizzaTesto_(form.event_id, 40);
  const paymentFilter = normalizzaTesto_(form.payment_status, 40).toUpperCase();
  const roomFilter = normalizzaTesto_(form.room, 80).toLowerCase();
  const limit = Math.min(50, Math.max(10, Math.round(Number(form.limit) || 30)));
  const events = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).reduce(function (result, row) { result[String(row.id_evento)] = row; return result; }, {});
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return !eventId || String(row.id_evento) === eventId; });
  const byOrder = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = row; return result; }, {});
  const paidByOrder = calcolaVersatoPerOrdine_(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)));
  const operational = indiceStatoOperativo_();
  const items = [];
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).slice().reverse().forEach(function (participant) {
    const orderCode = String(participant.codice_ordine || ''); const registration = byOrder[orderCode]; if (!registration) return;
    const personNumber = Number(participant.numero_partecipante) || 0;
    const fields = datiOperativiPartecipante_(participant, operational[orderCode + '|' + personNumber] || {});
    const room = String(fields.room || fields.camera || fields.alloggio || '');
    const payment = statoPagamento_(registration, paidByOrder[orderCode] || 0);
    const searchable = [orderCode, participant.nome, participant.cognome, registration.nome_referente, registration.cognome_referente].join(' ').toLowerCase();
    const roomDoesNotMatch = roomFilter === 'non assegnata' ? !!room : (roomFilter && room.toLowerCase().indexOf(roomFilter) < 0);
    if ((query && searchable.indexOf(query) < 0) || (paymentFilter && payment.code !== paymentFilter) || roomDoesNotMatch) return;
    items.push({ order_code: orderCode, participant_number: personNumber, first_name: String(participant.nome || ''), last_name: String(participant.cognome || ''), event_id: String(registration.id_evento || ''), event_title: String((events[String(registration.id_evento)] || {}).titolo || registration.id_evento || ''), registration_status: String(registration.stato || ''), payment_status: payment, room: room, participant_status: String(participant.stato_partecipante || 'ACTIVE') });
  });
  return { items: items.slice(0, limit), total: items.length, has_more: items.length > limit };
}

function caricaSchedaPrenotazione(orderCode) {
  orderCode = normalizzaTesto_(orderCode, 64);
  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (row) { return String(row.codice_ordine) === orderCode; });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(registration.id_evento); }) || {};
  const operational = indiceStatoOperativo_();
  const participants = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; }).map(function (row) {
    const number = Number(row.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(row, operational[orderCode + '|' + number] || {}); const room = String(fields.room || fields.camera || fields.alloggio || '');
    delete fields.room; delete fields.camera; delete fields.alloggio;
    return { number: number, first_name: String(row.nome || ''), last_name: String(row.cognome || ''), ticket_type: String(row.codice_tipologia || ''), status: String(row.stato_partecipante || 'ACTIVE'), room: room, fields: fields, options: decodificaElenco_(row.opzioni_json) };
  });
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; });
  const netPaid = calcolaVersatoPerOrdine_(payments)[orderCode] || 0;
  return { order_code: orderCode, event_id: String(registration.id_evento || ''), event_title: String(event.titolo || registration.id_evento || ''), status: String(registration.stato || ''), payment_status: statoPagamento_(registration, netPaid), created_at: registration.data_creazione, buyer: { first_name: String(registration.nome_referente || ''), last_name: String(registration.cognome_referente || ''), email: String(registration.email_referente || ''), phone: String(registration.telefono_referente || '') }, special_requests: String(registration.richieste_particolari || ''), total_cents: Number(registration.totale_centesimi) || 0, deposit_cents: Number(registration.primo_versamento_centesimi) || 0, paid_cents: netPaid, balance_cents: Math.max(0, (Number(registration.totale_centesimi) || 0) - netPaid), participants: participants, accommodations: elencaSistemazioniDisponibili_(String(registration.id_evento || '')), active_operator: normalizzaTesto_(Session.getActiveUser().getEmail(), 120) };
}

function salvaModifichePrenotazione(form) {
  form = form || {}; const orderCode = normalizzaTesto_(form.order_code, 64); const changes = Array.isArray(form.changes) ? form.changes.slice(0, 100) : [];
  if (!orderCode || !changes.length) throw new Error('Nessuna modifica da confermare.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  changes.forEach(function (change) {
    const participantNumber = Math.max(0, Math.round(Number(change.participant_number) || 0)); const key = normalizzaTesto_(change.key, 80); const value = normalizzaTesto_(change.value, 1000);
    if (!key) return;
    if (['room', 'camera', 'alloggio'].indexOf(key.toLowerCase()) >= 0) throw new Error('La sistemazione deve essere modificata con il selettore dedicato.');
    registraOperazioneSegreteria_(orderCode, participantNumber, 'UPDATE_FIELD', { key: key, value: value }, form.reason, operator, 'Modifica confermata dalla scheda prenotazione.');
  });
  aggiungiControllo_('BOOKING_UPDATE', 'REGISTRATION', orderCode, 'SUCCESS', operator, String(changes.length), 'WORKSPACE_UI');
  return { ok: true, message: 'Modifiche confermate e registrate nello storico.' };
}

function cambiaSistemazioneSegreteria(form) {
  form = form || {}; const orderCode = normalizzaTesto_(form.order_code, 64); const participantNumber = Math.max(0, Math.round(Number(form.participant_number) || 0)); const roomCode = normalizzaTesto_(form.room_code, 80);
  if (!orderCode || !participantNumber || !roomCode) throw new Error('Partecipante o sistemazione non validi.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120); const lock = LockService.getDocumentLock(); lock.waitLock(5000);
  try {
    const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (row) { return String(row.codice_ordine) === orderCode; });
    const participant = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber; });
    if (!registration || !participant || String(participant.stato_partecipante || 'ACTIVE').toUpperCase() === 'CANCELLED') throw new Error('Partecipante non disponibile.');
    const selected = elencaSistemazioniDisponibili_(String(registration.id_evento || '')).find(function (room) { return room.code === roomCode; });
    const currentFields = datiOperativiPartecipante_(participant, indiceStatoOperativo_()[orderCode + '|' + participantNumber] || {}); const currentRoom = String(currentFields.room || currentFields.camera || currentFields.alloggio || '');
    if (!selected) throw new Error('Sistemazione non disponibile per questo evento.');
    if (selected.available < 1 && currentRoom !== roomCode) throw new Error('La sistemazione selezionata è al completo.');
    registraOperazioneSegreteria_(orderCode, participantNumber, 'CHANGE_ACCOMMODATION', { key: 'room', value: roomCode, previous: currentRoom }, form.reason, operator, 'Sistemazione aggiornata con controllo capienza.');
    aggiungiControllo_('CHANGE_ACCOMMODATION', 'PARTICIPANT', orderCode + ':' + participantNumber, 'SUCCESS', operator, roomCode, 'WORKSPACE_UI');
    return { ok: true, message: 'Sistemazione aggiornata.' };
  } finally { lock.releaseLock(); }
}

function configuraElencoOperativo(form) {
  form = form || {}; const eventId = normalizzaTesto_(form.event_id, 40); const allowed = campiElencoOperativo_().map(function (field) { return field.key; });
  const fields = (Array.isArray(form.fields) ? form.fields : []).map(function (field) { return normalizzaTesto_(field, 80); }).filter(function (field, index, list) { return allowed.indexOf(field) >= 0 && list.indexOf(field) === index; });
  if (!eventId || !fields.length) throw new Error('Scegli un evento e almeno una colonna.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120); const settings = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS); const existing = convertiRigheInOggetti_(settings).find(function (row) { return String(row.id_evento) === eventId; }); const values = [eventId, JSON.stringify(fields), new Date(), neutralizzaFormula_(operator, 120)];
  if (existing) settings.getRange(existing._row, 1, 1, values.length).setValues([values]); else settings.appendRow(values);
  const count = generaElencoOperativo_(eventId, fields);
  return { ok: true, count: count, sheet_name: MI_SHEETS.OPERATIONAL_LIST, print_url: creaUrlStampaElenco_(), message: 'Elenco aggiornato con ' + count + ' partecipanti attivi.' };
}

function generaElencoOperativo_(eventId, fields, options) {
	options = options || {};
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_LIST); const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(eventId); }) || {};
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return String(row.id_evento) === String(eventId) && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0; }); const byOrder = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = row; return result; }, {});
  const operational = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE)); const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)); const labels = campiElencoOperativo_().reduce(function (result, field) { result[field.key] = field.label; return result; }, {});
  const rows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return !!byOrder[String(row.codice_ordine)] && String(row.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED'; }).map(function (participant) { const registration = byOrder[String(participant.codice_ordine)]; const data = decodificaOggetto_(participant.dati_aggiuntivi_json); operational.filter(function (state) { return String(state.codice_ordine) === String(participant.codice_ordine) && Number(state.numero_partecipante) === Number(participant.numero_partecipante); }).forEach(function (state) { data[String(state.chiave)] = state.valore; }); return fields.map(function (field) { return neutralizzaFormula_(valoreCampoElenco_(field, event, registration, participant, data, payments), 1000); }); });
	const grouping = normalizzaScelteReport_(options.raggruppamenti, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const ordering = normalizzaScelteReport_(options.ordinamento, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const sortColumns = grouping.concat(ordering).filter(function (column, index, list) { return list.indexOf(column) === index; });
	if (sortColumns.length) rows.sort(function (left, right) { for (let index = 0; index < sortColumns.length; index += 1) { const column = sortColumns[index]; const comparison = String(left[column] == null ? '' : left[column]).localeCompare(String(right[column] == null ? '' : right[column]), 'it', { numeric: true, sensitivity: 'base' }); if (comparison) return comparison; } return 0; });
  sheet.clear(); sheet.getRange(1, 1, 1, fields.length).merge().setValue('Elenco operativo — ' + String(event.titolo || eventId)).setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(14); sheet.getRange(2, 1, 1, fields.length).setValues([fields.map(function (field) { return labels[field] || field; })]).setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  if (rows.length) sheet.getRange(3, 1, rows.length, fields.length).setValues(rows).setWrap(true).setVerticalAlignment('middle');
  if (grouping.length && rows.length) rows.forEach(function (row, index) { const previous = index ? rows[index - 1] : null; const startsGroup = !previous || grouping.some(function (column) { return String(row[column]) !== String(previous[column]); }); if (startsGroup) sheet.getRange(index + 3, 1, 1, fields.length).setBorder(true, null, null, null, null, null, '#17224a', SpreadsheetApp.BorderStyle.SOLID_MEDIUM); });
  sheet.setFrozenRows(2); sheet.setHiddenGridlines(true); sheet.autoResizeColumns(1, fields.length); for (let column = 1; column <= fields.length; column += 1) sheet.setColumnWidth(column, Math.min(210, Math.max(90, sheet.getColumnWidth(column)))); sheet.getRange(1, 1, Math.max(2, rows.length + 2), fields.length).setBorder(true, true, true, true, true, true, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID);
  return rows.length;
}

function preparaComunicazioneOperativa(form) {
  form = form || {}; const eventId = normalizzaTesto_(form.event_id, 40); const templateType = normalizzaValoreElenco_(form.template_type, ['PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER']); const message = normalizzaTesto_(form.message, 4000);
  if (!eventId || !templateType) throw new Error('Scegli evento e tipo di comunicazione.');
  if (templateType === 'PRE_DEPARTURE_REMINDER' && !message) throw new Error('Scrivi le informazioni operative da comunicare.');
  const recipients = destinatariComunicazioneOperativa_(eventId, templateType);
  if (!recipients.length) return { ok: true, count: 0, mode: String(ottieniConfigurazione_('modalita_email', 'ANTEPRIMA')).toUpperCase(), message: 'Nessun destinatario corrisponde ai criteri.' };
  const communicationId = normalizzaTesto_(form.request_id, 64) || creaIdentificativoOpaco_('com');
  const result = inviaComandoWordPress_('QUEUE_OPERATIONAL_EMAILS', { communication_id: communicationId, event_id: eventId, template_type: templateType, message: message, recipients: recipients, allow_operational: form.allow_operational === true });
  return { ok: true, count: Number(result.count) || 0, mode: String(result.mode || 'ANTEPRIMA'), message: String(result.message || 'Comunicazione preparata.') };
}

function statoComunicazioniOperative() {
  const result = inviaComandoWordPress_('GET_EMAIL_MODE', {});
  return { mode: String(result.mode || 'ANTEPRIMA').toUpperCase() };
}

function destinatariComunicazioneOperativa_(eventId, templateType) {
  const paidByOrder = calcolaVersatoPerOrdine_(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)));
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (registration) {
    return String(registration.id_evento) === String(eventId) && ['CONFERMATA', 'IN_ATTESA_PAGAMENTO', 'CONFIRMED', 'PENDING_PAYMENT'].indexOf(String(registration.stato).toUpperCase()) >= 0;
  }).map(function (registration) {
    const orderCode = normalizzaTesto_(registration.codice_ordine, 64); const total = Math.max(0, Number(registration.totale_centesimi) || 0); const paid = Math.max(0, Number(paidByOrder[orderCode]) || 0); const balance = Math.max(0, total - paid);
    return { order_code: orderCode, paid_cents: paid, balance_cents: balance };
  }).filter(function (recipient) {
    return recipient.order_code && (templateType !== 'BALANCE_REMINDER' || recipient.balance_cents > 0);
  }).slice(0, 1000);
}

function inviaComandoWordPress_(action, payload) {
  const properties = PropertiesService.getScriptProperties(); const url = String(properties.getProperty('MI_WORDPRESS_COMMAND_URL') || '').trim(); if (!/^https:\/\//.test(url)) throw new Error('Configura prima il collegamento WordPress dal menu Modulo iscrizioni.');
  const timestamp = Date.now(); const nonce = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, ''); const message = timestamp + '\n' + nonce + '\n' + action + '\n' + serializzaInModoStabile_(payload || {}); const signature = Utilities.base64EncodeWebSafe(Utilities.computeHmacSha256Signature(message, ottieniSegretoScript_())).replace(/=+$/, '');
  const response = UrlFetchApp.fetch(url, { method: 'post', contentType: 'application/json', payload: JSON.stringify({ timestamp: timestamp, nonce: nonce, action: action, payload: payload || {}, signature: signature }), muteHttpExceptions: true }); const status = response.getResponseCode(); let body = {}; try { body = JSON.parse(response.getContentText() || '{}'); } catch (error) {}
  if (status < 200 || status >= 300 || body.ok === false) throw new Error(String(body.message || body.error || 'WordPress non ha accettato la richiesta.')); return body;
}

function registraOperazioneSegreteria_(orderCode, participantNumber, operationType, data, reason, operator, message) {
  const operationId = creaIdentificativoOpaco_('op');
  ottieniSchedaObbligatoria_(MI_SHEETS.SECRETARY_OPERATIONS).appendRow([operationId, new Date(), neutralizzaFormula_(orderCode, 64), participantNumber, operationType, JSON.stringify(data || {}), neutralizzaFormula_(reason, 500), neutralizzaFormula_(operator, 120), 'APPLICATA', message, new Date()]);
  if (data && data.key) aggiornaStatoOperativo_(orderCode, participantNumber, String(data.key), String(data.value == null ? '' : data.value), operator, operationId);
  return operationId;
}

function indiceStatoOperativo_() {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE)).reduce(function (result, row) { const id = String(row.codice_ordine) + '|' + Number(row.numero_partecipante || 0); result[id] = result[id] || {}; result[id][String(row.chiave)] = row.valore; return result; }, {});
}

function datiOperativiPartecipante_(participant, overrides) {
  const fields = decodificaOggetto_(participant.dati_aggiuntivi_json); Object.keys(overrides || {}).forEach(function (key) { fields[key] = overrides[key]; }); return fields;
}

function elencaSistemazioniDisponibili_(eventId) {
  const accommodationsSheet = ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS);
  let rooms = convertiRigheInOggetti_(accommodationsSheet).filter(function (row) { return String(row.id_evento) === String(eventId) && ['0', 'NO', 'FALSE', 'INATTIVA'].indexOf(String(row.attiva).toUpperCase()) < 0; });
  if (!rooms.length && eventId) {
    [['SINGOLA','Camera singola',1],['DOPPIA','Camera doppia',2],['TRIPLA','Camera tripla',3],['MULTIPLA','Camera multipla',8]].forEach(function (item) {
      accommodationsSheet.appendRow([String(eventId), item[0], item[1], item[2], 'SI', 'Opzione dimostrativa predefinita']);
    });
    rooms = convertiRigheInOggetti_(accommodationsSheet).filter(function (row) { return String(row.id_evento) === String(eventId); });
  }
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return String(row.id_evento) === String(eventId) && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0; }); const allowedOrders = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = true; return result; }, {}); const operational = indiceStatoOperativo_(); const occupied = {};
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).forEach(function (participant) { const orderCode = String(participant.codice_ordine || ''); if (!allowedOrders[orderCode] || String(participant.stato_partecipante || 'ACTIVE').toUpperCase() === 'CANCELLED') return; const number = Number(participant.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(participant, operational[orderCode + '|' + number] || {}); const code = String(fields.room || fields.camera || fields.alloggio || ''); if (code) occupied[code] = (occupied[code] || 0) + 1; });
  return rooms.map(function (room) { const code = String(room.codice || ''); const capacity = Math.max(0, Math.round(Number(room.capienza) || 0)); const used = occupied[code] || 0; return { code: code, name: String(room.nome || code), capacity: capacity, occupied: used, available: Math.max(0, capacity - used) }; });
}

function calcolaVersatoPerOrdine_(payments) {
  return (payments || []).reduce(function (result, row) { const code = String(row.codice_ordine || ''); const amount = Number(row.importo_centesimi) || 0; result[code] = (result[code] || 0) + (['RIMBORSO', 'STORNO'].indexOf(String(row.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); return result; }, {});
}

function statoPagamento_(registration, paid) {
  const total = Math.max(0, Number(registration.totale_centesimi) || 0); const deposit = Math.max(0, Number(registration.primo_versamento_centesimi) || 0); const balance = Math.max(0, total - paid);
  if (!total) return { code: 'GRATUITO', label: 'Gratuito', paid_cents: paid, balance_cents: 0 };
  if (paid >= total) return { code: 'SALDATO', label: 'Saldato', paid_cents: paid, balance_cents: 0 };
  if (paid >= deposit && deposit > 0) return { code: 'CAPARRA_RICEVUTA', label: 'Caparra ricevuta', paid_cents: paid, balance_cents: balance };
  if (paid > 0) return { code: 'PARZIALE', label: 'Versamento parziale', paid_cents: paid, balance_cents: balance };
  return { code: deposit > 0 ? 'CAPARRA_DOVUTA' : 'DA_PAGARE', label: deposit > 0 ? 'Caparra dovuta' : 'Da pagare', paid_cents: 0, balance_cents: balance };
}

function creaUrlStampaElenco_() {
  const spreadsheet = ottieniFoglioDiLavoroAssociato_(); const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_LIST);
  return 'https://docs.google.com/spreadsheets/d/' + encodeURIComponent(spreadsheet.getId()) + '/export?format=pdf&gid=' + sheet.getSheetId() + '&size=A4&portrait=false&fitw=true&sheetnames=false&printtitle=false&pagenumbers=true&gridlines=false&fzr=true';
}

/** Restituisce la vista quotidiana più adatta ai dati realmente raccolti dall'evento. */
function caricaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  if (!form.forza_generazione) {
    const vistaConservata = leggiVistaOperativaConservata_(idEvento);
    if (vistaConservata) return vistaConservata;
  }
  return generaVistaOperativaEvento_(idEvento);
}

/** Approva la vista dimostrativa e la conserva in una scheda dedicata all'evento. */
function approvaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const vista = generaVistaOperativaEvento_(idEvento);
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

/** Aggiorna soltanto i dati, mantenendo le colonne già approvate. */
function aggiornaDatiVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const precedente = leggiVistaOperativaConservata_(idEvento);
  if (!precedente) throw new Error('Approva prima la vista dimostrativa.');
  const vista = generaVistaOperativaEvento_(idEvento, precedente.colonne.map(function (colonna) { return colonna.key; }));
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

/** Rigenera modello e colonne, quindi sostituisce la vista conservata. */
function rigeneraStrutturaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const vista = generaVistaOperativaEvento_(idEvento);
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

function generaVistaOperativaEvento_(idEvento, campiForzati) {
  const eventi = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS));
  const evento = eventi.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!evento) throw new Error('Evento non trovato.');
  const iscrizioni = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (riga) {
    return String(riga.id_evento) === idEvento && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(riga.stato).toUpperCase()) < 0;
  });
  const iscrizioniPerCodice = iscrizioni.reduce(function (indice, riga) { indice[String(riga.codice_ordine)] = riga; return indice; }, {});
  const partecipanti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (riga) {
    return !!iscrizioniPerCodice[String(riga.codice_ordine)] && String(riga.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED';
  });
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS));
  const statoOperativo = indiceStatoOperativo_();
  const rigaVistaSalvata = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS)).find(function (riga) { return String(riga.id_evento) === idEvento; });
  let vistaSalvata = [];
  try { vistaSalvata = rigaVistaSalvata ? JSON.parse(String(rigaVistaSalvata.campi_json || '[]')) : []; } catch (errore) { vistaSalvata = []; }
  if (!Array.isArray(vistaSalvata)) vistaSalvata = [];
  const profilo = determinaProfiloVistaOperativa_(iscrizioni, partecipanti);
  const campi = Array.isArray(campiForzati) && campiForzati.length ? campiForzati : (vistaSalvata.length ? vistaSalvata : profilo.campi);
  const catalogo = campiElencoOperativo_().reduce(function (indice, campo) { indice[campo.key] = campo; return indice; }, {});
  const colonne = campi.filter(function (chiave) { return !!catalogo[chiave]; }).map(function (chiave) {
    return { key: chiave, label: catalogo[chiave].label, gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 };
  });
  const righe = partecipanti.map(function (partecipante) {
    const iscrizione = iscrizioniPerCodice[String(partecipante.codice_ordine)];
    const numero = Number(partecipante.numero_partecipante) || 0;
    const dati = datiOperativiPartecipante_(partecipante, statoOperativo[String(partecipante.codice_ordine) + '|' + numero] || {});
    const valori = {};
    colonne.forEach(function (colonna) { valori[colonna.key] = valoreCampoElenco_(colonna.key, evento, iscrizione, partecipante, dati, pagamenti); });
    return { codice_ordine: String(partecipante.codice_ordine), numero_partecipante: numero, valori: valori };
  });
  return { evento: { id: idEvento, titolo: String(evento.titolo || idEvento) }, profilo: profilo.id, nome_profilo: profilo.nome, personalizzata: !!vistaSalvata.length, conservata: false, colonne: colonne, righe: righe };
}

function nomeSchedaVistaOperativa_(idEvento) {
  const impronta = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, idEvento, Utilities.Charset.UTF_8).slice(0, 6).map(function (valore) { return ('0' + (valore & 255).toString(16)).slice(-2); }).join('');
  return 'Vista operativa ' + impronta;
}

function impostaMetadatoVista_(scheda, chiave, valore) {
  const metadato = scheda.getDeveloperMetadata().find(function (elemento) { return elemento.getKey() === chiave; });
  if (metadato) metadato.setValue(String(valore)); else scheda.addDeveloperMetadata(chiave, String(valore));
}

function salvaVistaOperativaConservata_(vista) {
  const foglio = ottieniFoglioDiLavoroAssociato_();
  const nome = nomeSchedaVistaOperativa_(vista.evento.id);
  let scheda = foglio.getSheetByName(nome);
  if (!scheda) scheda = foglio.insertSheet(nome);
  scheda.clear();
  const intestazioni = ['Codice prenotazione', 'Numero partecipante'].concat(vista.colonne.map(function (colonna) { return colonna.label; }));
  const righe = vista.righe.map(function (riga) {
    return [neutralizzaFormula_(riga.codice_ordine, 64), Number(riga.numero_partecipante) || 0].concat(vista.colonne.map(function (colonna) { return neutralizzaFormula_(riga.valori[colonna.key], 5000); }));
  });
  scheda.getRange(1, 1, 1, intestazioni.length).setValues([intestazioni]);
  if (righe.length) scheda.getRange(2, 1, righe.length, intestazioni.length).setValues(righe);
  scheda.setFrozenRows(1);
  scheda.hideColumns(1, 2);
  scheda.getRange(1, 1, 1, intestazioni.length).setFontWeight('bold').setBackground('#172554').setFontColor('#ffffff');
  scheda.autoResizeColumns(3, Math.max(1, intestazioni.length - 2));
  impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', vista.evento.id);
  impostaMetadatoVista_(scheda, 'MI_TITOLO_EVENTO', vista.evento.titolo);
  impostaMetadatoVista_(scheda, 'MI_PROFILO', vista.profilo);
  impostaMetadatoVista_(scheda, 'MI_NOME_PROFILO', vista.nome_profilo);
  impostaMetadatoVista_(scheda, 'MI_PERSONALIZZATA', vista.personalizzata ? '1' : '0');
  impostaMetadatoVista_(scheda, 'MI_CAMPI', JSON.stringify(vista.colonne.map(function (colonna) { return colonna.key; })));
  impostaMetadatoVista_(scheda, 'MI_DATA_AGGIORNAMENTO', new Date().toISOString());
}

function leggiVistaOperativaConservata_(idEvento) {
  const scheda = ottieniFoglioDiLavoroAssociato_().getSheetByName(nomeSchedaVistaOperativa_(idEvento));
  if (!scheda) return null;
  const metadati = scheda.getDeveloperMetadata().reduce(function (indice, elemento) { indice[elemento.getKey()] = elemento.getValue(); return indice; }, {});
  if (String(metadati.MI_ID_EVENTO || '') !== idEvento) return null;
  let campi = [];
  try { campi = JSON.parse(String(metadati.MI_CAMPI || '[]')); } catch (errore) { campi = []; }
  if (!Array.isArray(campi) || !campi.length) return null;
  const intestazioni = scheda.getRange(1, 1, 1, Math.max(2 + campi.length, 2)).getDisplayValues()[0];
  const colonne = campi.map(function (chiave, indice) { return { key: chiave, label: String(intestazioni[indice + 2] || chiave), gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 }; });
  const valori = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, 2 + colonne.length).getDisplayValues() : [];
  const righe = valori.map(function (riga) {
    const dati = {};
    colonne.forEach(function (colonna, indice) { dati[colonna.key] = riga[indice + 2]; });
    return { codice_ordine: String(riga[0] || ''), numero_partecipante: Number(riga[1]) || 0, valori: dati };
  });
  return { evento: { id: idEvento, titolo: String(metadati.MI_TITOLO_EVENTO || idEvento) }, profilo: String(metadati.MI_PROFILO || ''), nome_profilo: String(metadati.MI_NOME_PROFILO || 'Vista operativa'), personalizzata: String(metadati.MI_PERSONALIZZATA || '') === '1', conservata: true, data_aggiornamento: String(metadati.MI_DATA_AGGIORNAMENTO || ''), colonne: colonne, righe: righe };
}

function determinaProfiloVistaOperativa_(iscrizioni, partecipanti) {
  const profiliEspliciti = ['MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO'];
  let profiloEsplicito = '';
  iscrizioni.some(function (riga) {
    const istantanea = decodificaOggetto_(riga.snapshot_json);
    const candidato = normalizzaTesto_((istantanea.event || {}).operational_profile, 30).toUpperCase();
    if (profiliEspliciti.indexOf(candidato) < 0) return false;
    profiloEsplicito = candidato;
    return true;
  });
  let haDocumenti = false, haServizi = false;
  partecipanti.forEach(function (riga) {
    const dati = decodificaOggetto_(riga.dati_aggiuntivi_json);
    const opzioni = JSON.stringify(decodificaElenco_(riga.opzioni_json)).toLowerCase();
    if (dati.document_number || dati.numero_documento || dati.document_expiry_date || dati.scadenza_documento || dati.room || dati.camera || dati.alloggio) haDocumenti = true;
    if (dati.transport || dati.pullman || dati.lunch || dati.pranzo || /pullman|pranzo|colazione|cena/.test(opzioni)) haServizi = true;
  });
  const profili = {
    VIAGGIO_COMPLESSO: { id: 'VIAGGIO_COMPLESSO', nome: 'Viaggio complesso', campi: ['last_name', 'first_name', 'phone', 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality', 'transport', 'room', 'lunch', 'insurance', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    SERVIZI_MULTIPLI: { id: 'SERVIZI_MULTIPLI', nome: 'Gita con più servizi', campi: ['last_name', 'first_name', 'phone', 'transport', 'lunch', 'options', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    QUOTA_UNICA: { id: 'QUOTA_UNICA', nome: 'Evento con quota unica', campi: ['last_name', 'first_name', 'phone', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    MINIMO: { id: 'MINIMO', nome: 'Elenco minimo', campi: ['last_name', 'first_name', 'phone'] }
  };
  if (profiloEsplicito) return profili[profiloEsplicito];
  if (haDocumenti) return profili.VIAGGIO_COMPLESSO;
  if (haServizi) return profili.SERVIZI_MULTIPLI;
  if (iscrizioni.some(function (riga) { return Number(riga.totale_centesimi) > 0; })) return profili.QUOTA_UNICA;
  return profili.MINIMO;
}

function gruppoCampoVistaOperativa_(chiave) {
  if (['total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'].indexOf(chiave) >= 0) return 'pagamenti';
  if (['birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality'].indexOf(chiave) >= 0) return 'documenti';
  if (['room', 'transport', 'breakfast', 'lunch', 'insurance', 'options'].indexOf(chiave) >= 0) return 'servizi';
  return 'persona';
}

function campiElencoOperativo_() {
  const fields = [
    { key: 'event', label: 'Evento' }, { key: 'order_code', label: 'Codice prenotazione' }, { key: 'participant_number', label: 'N.' }, { key: 'first_name', label: 'Nome' }, { key: 'last_name', label: 'Cognome' }, { key: 'status', label: 'Stato' },
    { key: 'email', label: 'Email' }, { key: 'phone', label: 'Cellulare' }, { key: 'birth_date', label: 'Data di nascita' }, { key: 'document_type', label: 'Tipo documento' }, { key: 'document_number', label: 'Numero documento' }, { key: 'document_issue_date', label: 'Data emissione documento' }, { key: 'document_expiry_date', label: 'Scadenza documento' }, { key: 'nationality', label: 'Nazionalità' }, { key: 'room', label: 'Alloggio' }, { key: 'transport', label: 'Pullman/trasporto' }, { key: 'breakfast', label: 'Colazione' },
    { key: 'lunch', label: 'Pranzo' }, { key: 'insurance', label: 'Assicurazione' }, { key: 'emergency_contact', label: 'Contatto di emergenza' }, { key: 'options', label: 'Altre opzioni' }, { key: 'total', label: 'Totale' }, { key: 'paid', label: 'Incassato' }, { key: 'paid_cash', label: 'Contanti' }, { key: 'paid_transfer', label: 'Bonifico' }, { key: 'paid_card', label: 'Carta/PayPal' }, { key: 'balance', label: 'Da incassare' }, { key: 'special_requests', label: 'Richieste particolari' }
  ];
  const known = fields.reduce(function (result, field) { result[field.key] = true; return result; }, {});
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).forEach(function (participant) {
    const data = decodificaOggetto_(participant.dati_aggiuntivi_json);
    Object.keys(data).forEach(function (key) {
      if (known[key] || data[key] == null || String(data[key]).trim() === '' || !/^[A-Za-z0-9_-]{1,80}$/.test(key)) return;
      known[key] = true;
      const labels = { birth_date: 'Data di nascita', room: 'Alloggio', transport: 'Pullman/trasporto', breakfast: 'Colazione', lunch: 'Pranzo', insurance: 'Assicurazione' };
      fields.push({ key: key, label: labels[key] || key.replace(/[_-]+/g, ' ').replace(/^./, function (letter) { return letter.toUpperCase(); }) });
    });
  });
  return fields;
}

function valoreCampoElenco_(field, event, registration, participant, data, payments) {
  const aliases = { email: ['participant_email', 'email'], phone: ['participant_phone', 'phone', 'mobile'], birth_date: ['birth_date', 'data_nascita'], document_type: ['document_type', 'tipo_documento'], document_number: ['document_number', 'numero_documento'], document_issue_date: ['document_issue_date', 'data_emissione_documento'], document_expiry_date: ['document_expiry_date', 'document_expiry', 'scadenza_documento'], nationality: ['nationality', 'nazionalita'], room: ['room', 'camera', 'alloggio'], transport: ['pullman', 'transport'], breakfast: ['colazione', 'breakfast'], lunch: ['pranzo', 'lunch'], insurance: ['assicurazione', 'insurance'], emergency_contact: ['emergency_contact', 'emergency_phone', 'contatto_emergenza', 'telefono_emergenza'] };
  const direct = { event: event.titolo || registration.id_evento, order_code: registration.codice_ordine, participant_number: participant.numero_partecipante, first_name: participant.nome, last_name: participant.cognome, status: participant.stato_partecipante || registration.stato, special_requests: registration.richieste_particolari || '' };
  if (Object.prototype.hasOwnProperty.call(direct, field)) return direct[field];
  if (field === 'options') return decodificaElenco_(participant.opzioni_json).map(function (option) { return option.name || option.label || option.code || ''; }).filter(Boolean).join(', ');
  const pagamentiOrdine = payments.filter(function (payment) { return String(payment.codice_ordine) === String(registration.codice_ordine); });
  const sommaPagamenti = function (fonte) { return pagamentiOrdine.reduce(function (total, payment) { if (fonte && String(payment.fonte_pagamento).toUpperCase() !== fonte) return total; const amount = Number(payment.importo_centesimi) || 0; return total + (['RIMBORSO', 'STORNO'].indexOf(String(payment.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); }, 0); };
  const paid = sommaPagamenti('');
  if (field === 'total') return (Number(registration.totale_centesimi) || 0) / 100;
  if (field === 'paid') return paid / 100;
  if (field === 'paid_cash') return sommaPagamenti('CONTANTE') / 100;
  if (field === 'paid_transfer') return sommaPagamenti('BONIFICO') / 100;
  if (field === 'paid_card') return sommaPagamenti('CARTA') / 100;
  if (field === 'balance') return Math.max(0, (Number(registration.totale_centesimi) || 0) - paid) / 100;
  const candidates = aliases[field] || [field]; for (let index = 0; index < candidates.length; index += 1) if (data[candidates[index]] != null && data[candidates[index]] !== '') return data[candidates[index]];
  if (field === 'email') return registration.email_referente || ''; if (field === 'phone') return registration.telefono_referente || ''; return '';
}

function aggiornaStatoOperativo_(orderCode, participantNumber, key, value, operator, operationId) {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE); const existing = convertiRigheInOggetti_(sheet).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber && String(row.chiave) === key; }); const values = [neutralizzaFormula_(orderCode, 64), participantNumber, neutralizzaFormula_(key, 80), neutralizzaFormula_(value, 1000), new Date(), neutralizzaFormula_(operator, 120), operationId];
  if (existing) sheet.getRange(existing._row, 1, 1, values.length).setValues([values]); else sheet.appendRow(values);
}

function decodificaOggetto_(value) { try { const parsed = JSON.parse(String(value || '{}')); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}; } catch (error) { return {}; } }
function decodificaElenco_(value) { try { const parsed = JSON.parse(String(value || '[]')); return Array.isArray(parsed) ? parsed : []; } catch (error) { return []; } }
