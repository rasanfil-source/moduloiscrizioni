/** Restituisce i modelli di report disponibili alla segreteria. */
function elencaModelliReport() {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REPORT_TEMPLATES)).map(function (row) {
    return {
      id: normalizzaTesto_(row.id_modello, 64),
      nome: normalizzaTesto_(row.nome, 120),
      tipo: normalizzaTesto_(row.tipo, 20),
      id_evento: normalizzaTesto_(row.id_evento, 40),
      colonne: decodificaConfigurazioneReport_(row.colonne_json),
      filtri: decodificaConfigurazioneReport_(row.filtri_json),
      raggruppamenti: decodificaConfigurazioneReport_(row.raggruppamenti_json),
      ordinamento: decodificaConfigurazioneReport_(row.ordinamento_json),
      predefinito: String(row.predefinito || '').toUpperCase() === 'SI'
    };
  }).filter(function (row) { return row.id && row.nome; });
}

/** Salva esclusivamente modelli personalizzati; i modelli standard restano immutabili. */
function salvaModelloReport(form) {
  form = form || {};
  const nome = normalizzaTesto_(form.nome, 120);
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (nome.length < 3) throw new Error('Indica un nome di almeno tre caratteri.');
  if (idEvento && !/^[A-Za-z0-9_-]{1,40}$/.test(idEvento)) throw new Error('Evento non valido.');
  const campiAmmessi = campiElencoOperativo_().map(function (field) { return String(field.key); });
  const colonne = normalizzaScelteReport_(form.colonne, campiAmmessi, 30);
  if (!colonne.length) throw new Error('Seleziona almeno una colonna.');
  const filtri = normalizzaScelteReport_(form.filtri, ['evento', 'gruppo', 'stato_iscrizione', 'stato_pagamento', 'fonte_pagamento', 'data_versamento', 'camera', 'sistemazione', 'pullman', 'documenti_mancanti'], 20);
  const raggruppamenti = normalizzaScelteReport_(form.raggruppamenti, campiAmmessi, 5);
  const ordinamento = normalizzaScelteReport_(form.ordinamento, campiAmmessi, 5);
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.REPORT_TEMPLATES);
  const id = 'report-' + Utilities.getUuid();
  sheet.appendRow([id, nome, 'PERSONALIZZATO', idEvento, JSON.stringify(colonne), JSON.stringify(filtri), JSON.stringify(raggruppamenti), JSON.stringify(ordinamento), 'NO', new Date(), normalizzaTesto_(Session.getActiveUser().getEmail(), 120)]);
  return { ok: true, id: id, nome: nome };
}

/** Genera la vista stampabile usando esclusivamente un modello salvato. */
function generaReportDaModello(form) {
  form = form || {};
  const id = normalizzaTesto_(form.id_modello, 64);
  const model = elencaModelliReport().find(function (item) { return item.id === id; });
  if (!model) throw new Error('Modello report non trovato.');
  const eventId = normalizzaTesto_(form.id_evento || model.id_evento, 40);
  if (!eventId) throw new Error('Scegli l’evento del report.');
  if (model.id_evento && model.id_evento !== eventId) throw new Error('Il modello è riservato a un altro evento.');
  const allowed = campiElencoOperativo_().map(function (field) { return String(field.key); });
  const columns = normalizzaScelteReport_(model.colonne, allowed, 30);
  if (!columns.length) throw new Error('Il modello non contiene colonne disponibili per questo evento.');
  const filters = normalizzaFiltriEsecuzioneReport_(form.valori_filtri);
  const count = generaElencoOperativo_(eventId, columns, { ordinamento: model.ordinamento, raggruppamenti: model.raggruppamenti, filtri: filters });
  aggiungiControllo_('GENERATE_REPORT', 'REPORT_TEMPLATE', id, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail(), 120), eventId + ':' + count, 'WORKSPACE_UI');
  return { ok: true, count: count, nome: model.nome, print_url: creaUrlStampaElenco_(), message: 'Report generato con ' + count + ' partecipanti.' };
}

function normalizzaScelteReport_(values, allowed, limit) {
  const seen = {};
  return (Array.isArray(values) ? values : []).map(function (value) { return normalizzaTesto_(value, 64); }).filter(function (value) {
    if (!value || allowed.indexOf(value) < 0 || seen[value]) return false;
    seen[value] = true;
    return true;
  }).slice(0, limit);
}

function normalizzaFiltriEsecuzioneReport_(raw) {
  raw = raw || {};
  if (typeof raw !== 'object' || Array.isArray(raw)) throw new Error('Filtri report non validi.');
  const allowed = ['query', 'status', 'room', 'transport'];
  if (Object.keys(raw).some(function (key) { return allowed.indexOf(key) < 0; })) throw new Error('Filtro report non disponibile.');
  const result = {};
  allowed.forEach(function (key) { result[key] = normalizzaTesto_(raw[key] || '', 120); });
  if (['', 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED', 'WAITLIST_OFFERED'].indexOf(result.status) < 0) throw new Error('Stato report non valido.');
  return result;
}

function corrispondeFiltriReport_(filters, registration, participant, data) {
  filters = filters || {};
  const aliases = { CONFERMATA: 'CONFIRMED', IN_ATTESA_PAGAMENTO: 'PENDING_PAYMENT' };
  const status = String(registration.stato || '').toUpperCase();
  if (filters.status && filters.status !== (aliases[status] || status)) return false;
  const query = String(filters.query || '').trim().toLocaleLowerCase('it');
  const name = [participant.nome, participant.cognome, participant.cognome, participant.nome, registration.codice_ordine, registration.email_referente].join(' ').toLocaleLowerCase('it');
  if (query && name.indexOf(query) < 0) return false;
  const room = data.room || data.camera || data.alloggio || '';
  const transport = data.pullman || data.transport || '';
  return (!filters.room || String(room).toLocaleLowerCase('it') === filters.room.toLocaleLowerCase('it')) && (!filters.transport || String(transport).toLocaleLowerCase('it') === filters.transport.toLocaleLowerCase('it'));
}

function decodificaConfigurazioneReport_(value) {
  try {
    const parsed = JSON.parse(String(value || '[]'));
    return Array.isArray(parsed) ? parsed.map(function (item) { return normalizzaTesto_(item, 64); }).filter(Boolean).slice(0, 30) : [];
  } catch (error) {
    return [];
  }
}
