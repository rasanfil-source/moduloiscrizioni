function apriSchedaPrenotazione() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'PRENOTAZIONE';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Scheda prenotazione').setWidth(440));
}

function apriConfigurazioneElencoOperativo() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ELENCO';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Elenco operativo').setWidth(440));
}

function caricaContestoSegreteria() {
  const events = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).map(function (row) {
    return { id: String(row.id_evento || ''), title: String(row.titolo || ''), status: String(row.stato || '') };
  });
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).slice(-1000).reverse().map(function (row) {
    return { order_code: String(row.codice_ordine || ''), event_id: String(row.id_evento || ''), label: [row.codice_ordine, row.nome_referente, row.cognome_referente].filter(Boolean).join(' · '), status: String(row.stato || '') };
  });
  const views = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS)).reduce(function (result, row) {
    try { result[String(row.id_evento)] = JSON.parse(String(row.campi_json || '[]')); } catch (error) { result[String(row.id_evento)] = []; }
    return result;
  }, {});
  return { events: events, registrations: registrations, views: views, available_fields: campiElencoOperativo_() };
}

function caricaSchedaPrenotazione(orderCode) {
  orderCode = normalizzaTesto_(orderCode, 64);
  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (row) { return String(row.codice_ordine) === orderCode; });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(registration.id_evento); }) || {};
  const operational = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE)).filter(function (row) { return String(row.codice_ordine) === orderCode; });
  const participants = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; }).map(function (row) {
    const fields = decodificaOggetto_(row.dati_aggiuntivi_json);
    operational.filter(function (state) { return Number(state.numero_partecipante) === Number(row.numero_partecipante); }).forEach(function (state) { fields[String(state.chiave)] = state.valore; });
    return { number: Number(row.numero_partecipante), first_name: String(row.nome || ''), last_name: String(row.cognome || ''), ticket_type: String(row.codice_tipologia || ''), status: String(row.stato_partecipante || 'ACTIVE'), fields: fields, options: decodificaElenco_(row.opzioni_json) };
  });
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; });
  const netPaid = payments.reduce(function (total, row) { const amount = Number(row.importo_centesimi) || 0; return total + (String(row.tipo_movimento) === 'RIMBORSO' ? -amount : amount); }, 0);
  return { order_code: orderCode, event_title: String(event.titolo || registration.id_evento || ''), status: String(registration.stato || ''), created_at: registration.data_creazione, buyer: { first_name: String(registration.nome_referente || ''), last_name: String(registration.cognome_referente || ''), email: String(registration.email_referente || ''), phone: String(registration.telefono_referente || '') }, special_requests: String(registration.richieste_particolari || ''), total_cents: Number(registration.totale_centesimi) || 0, paid_cents: netPaid, balance_cents: Math.max(0, (Number(registration.totale_centesimi) || 0) - netPaid), participants: participants };
}

function salvaModifichePrenotazione(form) {
  form = form || {};
  const orderCode = normalizzaTesto_(form.order_code, 64);
  const changes = Array.isArray(form.changes) ? form.changes.slice(0, 100) : [];
  if (!orderCode || !changes.length) throw new Error('Nessuna modifica da confermare.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  changes.forEach(function (change) {
    const participantNumber = Math.max(0, Math.round(Number(change.participant_number) || 0));
    const key = normalizzaTesto_(change.key, 80);
    const value = normalizzaTesto_(change.value, 1000);
    if (!key) return;
    const operationId = creaIdentificativoOpaco_('op');
    ottieniSchedaObbligatoria_(MI_SHEETS.SECRETARY_OPERATIONS).appendRow([operationId, new Date(), neutralizzaFormula_(orderCode, 64), participantNumber, 'UPDATE_FIELD', JSON.stringify({ key: key, value: value }), neutralizzaFormula_(form.reason, 500), neutralizzaFormula_(operator, 120), 'APPLICATA', 'Modifica confermata dalla scheda prenotazione.', new Date()]);
    aggiornaStatoOperativo_(orderCode, participantNumber, key, value, operator, operationId);
  });
  aggiungiControllo_('BOOKING_UPDATE', 'REGISTRATION', orderCode, 'SUCCESS', operator, String(changes.length), 'WORKSPACE_UI');
  return { ok: true, message: 'Modifiche confermate e registrate nello storico.' };
}

function configuraElencoOperativo(form) {
  form = form || {};
  const eventId = normalizzaTesto_(form.event_id, 40);
  const allowed = campiElencoOperativo_().map(function (field) { return field.key; });
  const fields = (Array.isArray(form.fields) ? form.fields : []).map(function (field) { return normalizzaTesto_(field, 80); }).filter(function (field, index, list) { return allowed.indexOf(field) >= 0 && list.indexOf(field) === index; });
  if (!eventId || !fields.length) throw new Error('Scegli un evento e almeno una colonna.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  const settings = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS);
  const existing = convertiRigheInOggetti_(settings).find(function (row) { return String(row.id_evento) === eventId; });
  const values = [eventId, JSON.stringify(fields), new Date(), neutralizzaFormula_(operator, 120)];
  if (existing) settings.getRange(existing._row, 1, 1, values.length).setValues([values]); else settings.appendRow(values);
  const count = generaElencoOperativo_(eventId, fields);
  return { ok: true, count: count, sheet_name: MI_SHEETS.OPERATIONAL_LIST, message: 'Elenco aggiornato con ' + count + ' partecipanti.' };
}

function generaElencoOperativo_(eventId, fields) {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_LIST);
  const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(eventId); }) || {};
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return String(row.id_evento) === String(eventId); });
  const byOrder = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = row; return result; }, {});
  const operational = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE));
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS));
  const labels = campiElencoOperativo_().reduce(function (result, field) { result[field.key] = field.label; return result; }, {});
  const rows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return !!byOrder[String(row.codice_ordine)]; }).map(function (participant) {
    const registration = byOrder[String(participant.codice_ordine)];
    const data = decodificaOggetto_(participant.dati_aggiuntivi_json);
    operational.filter(function (state) { return String(state.codice_ordine) === String(participant.codice_ordine) && Number(state.numero_partecipante) === Number(participant.numero_partecipante); }).forEach(function (state) { data[String(state.chiave)] = state.valore; });
    return fields.map(function (field) { return neutralizzaFormula_(valoreCampoElenco_(field, event, registration, participant, data, payments), 1000); });
  });
  sheet.clearContents();
  sheet.getRange(1, 1, 1, fields.length).setValues([fields.map(function (field) { return labels[field] || field; })]).setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold');
  if (rows.length) sheet.getRange(2, 1, rows.length, fields.length).setValues(rows);
  sheet.setFrozenRows(1); sheet.setHiddenGridlines(true); sheet.autoResizeColumns(1, fields.length);
  return rows.length;
}

function campiElencoOperativo_() {
  return [
    { key: 'event', label: 'Evento' }, { key: 'order_code', label: 'Codice prenotazione' }, { key: 'participant_number', label: 'N.' }, { key: 'first_name', label: 'Nome' }, { key: 'last_name', label: 'Cognome' }, { key: 'status', label: 'Stato' },
    { key: 'email', label: 'Email' }, { key: 'phone', label: 'Cellulare' }, { key: 'birth_date', label: 'Data di nascita' }, { key: 'room', label: 'Alloggio' }, { key: 'transport', label: 'Pullman/trasporto' }, { key: 'breakfast', label: 'Colazione' },
    { key: 'lunch', label: 'Pranzo' }, { key: 'insurance', label: 'Assicurazione' }, { key: 'options', label: 'Altre opzioni' }, { key: 'total', label: 'Totale' }, { key: 'paid', label: 'Versato' }, { key: 'balance', label: 'Saldo' }, { key: 'special_requests', label: 'Richieste particolari' }
  ];
}

function valoreCampoElenco_(field, event, registration, participant, data, payments) {
  const aliases = { email: ['participant_email', 'email'], phone: ['participant_phone', 'phone', 'mobile'], birth_date: ['birth_date'], room: ['camera', 'room', 'alloggio'], transport: ['pullman', 'transport'], breakfast: ['colazione', 'breakfast'], lunch: ['pranzo', 'lunch'], insurance: ['assicurazione', 'insurance'] };
  const direct = { event: event.titolo || registration.id_evento, order_code: registration.codice_ordine, participant_number: participant.numero_partecipante, first_name: participant.nome, last_name: participant.cognome, status: participant.stato_partecipante || registration.stato, special_requests: registration.richieste_particolari || '' };
  if (Object.prototype.hasOwnProperty.call(direct, field)) return direct[field];
  if (field === 'options') return decodificaElenco_(participant.opzioni_json).map(function (option) { return option.name || option.label || option.code || ''; }).filter(Boolean).join(', ');
  const paid = payments.filter(function (payment) { return String(payment.codice_ordine) === String(registration.codice_ordine); }).reduce(function (total, payment) { const amount = Number(payment.importo_centesimi) || 0; return total + (String(payment.tipo_movimento) === 'RIMBORSO' ? -amount : amount); }, 0);
  if (field === 'total') return (Number(registration.totale_centesimi) || 0) / 100;
  if (field === 'paid') return paid / 100;
  if (field === 'balance') return Math.max(0, (Number(registration.totale_centesimi) || 0) - paid) / 100;
  const candidates = aliases[field] || [field];
  for (let index = 0; index < candidates.length; index += 1) if (data[candidates[index]] != null && data[candidates[index]] !== '') return data[candidates[index]];
  if (field === 'email') return registration.email_referente || '';
  if (field === 'phone') return registration.telefono_referente || '';
  return '';
}

function aggiornaStatoOperativo_(orderCode, participantNumber, key, value, operator, operationId) {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE);
  const existing = convertiRigheInOggetti_(sheet).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber && String(row.chiave) === key; });
  const values = [neutralizzaFormula_(orderCode, 64), participantNumber, neutralizzaFormula_(key, 80), neutralizzaFormula_(value, 1000), new Date(), neutralizzaFormula_(operator, 120), operationId];
  if (existing) sheet.getRange(existing._row, 1, 1, values.length).setValues([values]); else sheet.appendRow(values);
}

function decodificaOggetto_(value) { try { const parsed = JSON.parse(String(value || '{}')); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}; } catch (error) { return {}; } }
function decodificaElenco_(value) { try { const parsed = JSON.parse(String(value || '[]')); return Array.isArray(parsed) ? parsed : []; } catch (error) { return []; } }
