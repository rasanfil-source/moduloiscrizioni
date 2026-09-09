function doGet(event) {
  return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
}

function doPost(event) {
  try {
    if (!event || !event.postData || !event.postData.contents) return creaRispostaJson_({ ok: false, error: 'EMPTY_PAYLOAD' });
    const envelope = JSON.parse(event.postData.contents);
    const verified = verificaBusta_(envelope);
    if (!verified.ok) return creaRispostaJson_({ ok: false, error: verified.error });
    if (envelope.action === 'ELIMINA_DATI_EVENTO') return creaRispostaJson_(eliminaDatiEventoDaWordPress_(envelope.payload));
    if (envelope.action === 'PING') return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
	if (envelope.action === 'STATO_SCHEMA') return creaRispostaJson_({ ok: true, schema_version: MI_SCHEMA_VERSION, registration_headers: MI_HEADERS[MI_SHEETS.REGISTRATIONS], accommodation_headers: MI_HEADERS[MI_SHEETS.ACCOMMODATIONS], group_headers: MI_HEADERS[MI_SHEETS.GROUPS], report_template_headers: MI_HEADERS[MI_SHEETS.REPORT_TEMPLATES], event_headers: MI_HEADERS[MI_SHEETS.EVENTS], mode: 'PREVIEW' });
	if (envelope.action === 'STATO_REPLICA_ISCRIZIONE') return creaRispostaJson_(statoReplicaIscrizione_(envelope.payload));
	if (envelope.action === 'PREPARA_PRODUZIONI_EVENTO') return creaRispostaJson_(preparaProduzioniEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'VERIFICA_FOGLIO_EVENTO') return creaRispostaJson_(verificaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'VERIFICA_FOGLI_EVENTO') return creaRispostaJson_(verificaFogliEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ARCHIVIA_FOGLIO_EVENTO') return creaRispostaJson_(archiviaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ORGANIZZA_FOGLI_EVENTO') return creaRispostaJson_(organizzaFogliEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ELIMINA_FOGLIO_EVENTO') return creaRispostaJson_(eliminaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_PROVA') return creaRispostaJson_(inviaEmailProvaDaWordPress_(envelope.payload));
    if (envelope.action === 'SALDO_PAGAMENTO_PORTALE') return creaRispostaJson_(saldoPagamentoPortale_(envelope.payload));
    if (envelope.action === 'REGISTRA_PAGAMENTO_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_PAYMENT_LEDGER'});
    if (envelope.action === 'LEGGI_MODIFICHE_FOGLIO') return creaRispostaJson_(leggiModificheEventoMysql_(envelope.payload));
    if (envelope.action === 'SCHEDA_GESTIONE_PORTALE') return creaRispostaJson_(schedaGestionePortale_(envelope.payload));
    if (envelope.action === 'AGGIORNA_GESTIONE_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_MANAGEMENT'});
    if (envelope.action === 'RIEPILOGO_GESTIONE_EVENTO') return creaRispostaJson_(riepilogoGestioneEvento_(envelope.payload));
    if (envelope.action === 'ELENCA_PAGAMENTI') return creaRispostaJson_(elencaPagamenti_(envelope.payload));
    if (envelope.action !== 'APPEND_REGISTRATION') return creaRispostaJson_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
    return creaRispostaJson_(aggiungiIscrizione_(envelope.payload));
  } catch (error) {
		console.error('WEBAPP_REQUEST_FAILED', error && error.stack ? error.stack : String(error));
		try { aggiungiControllo_('WEBAPP_REQUEST', 'REQUEST', 'UNAVAILABLE', 'ERROR', 'WORDPRESS', 'UNHANDLED_ERROR', 'WORDPRESS_PROXY'); } catch (auditError) {}
    return creaRispostaJson_({ ok: false, error: 'REQUEST_FAILED', diagnostic: normalizzaTesto_(error && error.message ? error.message : String(error), 300) });
  }
}

function elencaPagamenti_(payload) {
  const orderCodes = (Array.isArray(payload.order_codes) ? payload.order_codes : []).slice(0, 50).map(function (code) { return normalizzaTesto_(code, 64); }).filter(function (code) { return /^[A-Za-z0-9_-]{3,64}$/.test(code); });
  if (!orderCodes.length) return { ok: false, error: 'ORDER_CODES_REQUIRED' };
  const allowed = {};
  orderCodes.forEach(function (code) { allowed[code] = true; });
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (row) { return allowed[String(row.codice_ordine)] && String(row.canale_registrazione).toUpperCase() !== 'WORDPRESS'; }).slice(0, 500).map(function (row) {
    return { id_pagamento: normalizzaTesto_(row.id_pagamento, 64), codice_ordine: normalizzaTesto_(row.codice_ordine, 64), tipo_movimento: normalizzaTesto_(row.tipo_movimento, 24), tipo_rata: normalizzaTesto_(row.tipo_rata, 24), data_effettiva: row.data_effettiva instanceof Date ? row.data_effettiva.toISOString() : normalizzaTesto_(row.data_effettiva, 40), importo_centesimi: Math.max(0, Math.round(Number(row.importo_centesimi) || 0)), fonte_pagamento: normalizzaTesto_(row.fonte_pagamento, 24), riferimento_esterno: normalizzaTesto_(row.riferimento_esterno, 120), etichetta_operatore: normalizzaTesto_(row.etichetta_operatore, 100), nota_amministrativa: normalizzaTesto_(row.nota_amministrativa, 500) };
  });
  return { ok: true, payments: payments };
}

function statoReplicaIscrizione_(payload) {
  payload = payload || {};
  const orderCode = normalizzaTesto_(payload.order_code, 64);
  const idempotencyKey = normalizzaTesto_(payload.idempotency_key, 64);
  if (!/^[A-Za-z0-9_-]{3,64}$/.test(orderCode) || !/^[A-Za-z0-9_-]{16,64}$/.test(idempotencyKey)) return { ok: false, complete: false, error: 'INVALID_REGISTRATION_REFERENCE' };
  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (item) {
    return String(item.codice_ordine) === orderCode && String(item.chiave_idempotenza) === idempotencyKey;
  });
  if (!registration) return { ok: true, complete: false, central_complete: false, event_sheet_complete: false, order_code: orderCode };
  const expected = Math.max(0, Number(registration.numero_partecipanti) || 0);
  const participantCount = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (item) {
    return String(item.codice_ordine) === orderCode;
  }).length;
  const centralComplete = expected > 0 && participantCount === expected;
  let eventSheetComplete = false;
  if (centralComplete) {
    try {
      const link = trovaCollegamentoFoglioOperativo_(String(registration.id_evento));
      const spreadsheet = SpreadsheetApp.openById(String(link.id_foglio));
      const sheet = spreadsheet.getSheetByName('Dati operativi') || spreadsheet.getSheets()[0];
      const map = mappaColonneEvento_(sheet);
      const values = sheet.getLastRow() > 1 ? sheet.getRange(2, 1, sheet.getLastRow() - 1, sheet.getLastColumn()).getValues() : [];
      const found = {};
      values.forEach(function (row) {
        const id = identitaRigaEvento_(String(registration.id_evento), row[map._ordine - 1], row[map._numero - 1]);
        if (id && String(row[map._ordine - 1]) === orderCode) found[id] = true;
      });
      eventSheetComplete = Object.keys(found).length === expected;
    } catch (error) {
      eventSheetComplete = false;
    }
  }
  return { ok: true, complete: centralComplete && eventSheetComplete, central_complete: centralComplete, event_sheet_complete: eventSheetComplete, order_code: orderCode };
}

function verificaBusta_(envelope) {
  const timestamp = Number(envelope.timestamp || 0);
  const nonce = normalizzaTesto_(envelope.nonce, 80);
  const signature = normalizzaTesto_(envelope.signature, 200);
  if (!timestamp || Math.abs(Date.now() - timestamp) > 120000) return { ok: false, error: 'STALE_REQUEST' };
  if (nonce.length < 16 || signature.length < 32) return { ok: false, error: 'INVALID_SIGNATURE' };
  let payloadFirmato = '';
  if (typeof envelope.payload_firmato === 'string' && envelope.payload_firmato.length <= 100000) {
    payloadFirmato = envelope.payload_firmato;
    let payloadDecodificato;
    try { payloadDecodificato = JSON.parse(payloadFirmato); } catch (errore) { return { ok: false, error: 'INVALID_SIGNATURE' }; }
    if (!payloadDecodificato || typeof payloadDecodificato !== 'object' || Array.isArray(payloadDecodificato)) return { ok: false, error: 'INVALID_SIGNATURE' };
    envelope.payload = payloadDecodificato;
  } else {
    payloadFirmato = serializzaInModoStabile_(envelope.payload || {});
  }
  let contenutoFirma = payloadFirmato;
  if (Number(envelope.protocollo || 1) === 2) {
    const payloadHash = normalizzaTesto_(envelope.payload_hash, 64).toLowerCase();
    const hashCalcolato = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, payloadFirmato, Utilities.Charset.UTF_8).map(function (valore) { return ('0' + (valore & 255).toString(16)).slice(-2); }).join('');
    if (!/^[a-f0-9]{64}$/.test(payloadHash) || !confrontaInTempoCostante_(hashCalcolato, payloadHash)) return { ok: false, error: 'INVALID_PAYLOAD_HASH' };
    contenutoFirma = payloadHash;
  }
  const message = timestamp + '\n' + nonce + '\n' + String(envelope.action || '') + '\n' + contenutoFirma;
  const digest = Utilities.computeHmacSha256Signature(message, ottieniSegretoScript_());
  const expected = Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
  if (!confrontaInTempoCostante_(expected, signature)) return { ok: false, error: 'INVALID_SIGNATURE' };
  const lock = LockService.getScriptLock();
  lock.waitLock(5000);
  try {
    const cache = CacheService.getScriptCache();
	const nonceKey = Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, nonce)).replace(/=+$/, '');
	const properties = PropertiesService.getScriptProperties();
	let durableNonces = {};
	try { durableNonces = JSON.parse(properties.getProperty('MI_USED_NONCES') || '{}'); } catch (error) { durableNonces = {}; }
	const nonceCutoff = Date.now() - 180000;
	Object.keys(durableNonces).forEach(function (key) { if (Number(durableNonces[key]) < nonceCutoff) delete durableNonces[key]; });
	if (cache.get('nonce_' + nonce) || durableNonces[nonceKey]) return { ok: false, error: 'REPLAYED_REQUEST' };
    cache.put('nonce_' + nonce, '1', 180);
	durableNonces[nonceKey] = Date.now();
	const nonceKeys = Object.keys(durableNonces).sort(function (left, right) { return Number(durableNonces[right]) - Number(durableNonces[left]); });
	nonceKeys.slice(500).forEach(function (key) { delete durableNonces[key]; });
	properties.setProperty('MI_USED_NONCES', JSON.stringify(durableNonces));
  } finally {
    lock.releaseLock();
  }
  return { ok: true };
}

function confrontaInTempoCostante_(left, right) {
  left = String(left); right = String(right);
  let result = left.length ^ right.length;
  const length = Math.max(left.length, right.length);
  for (let index = 0; index < length; index += 1) result |= (left.charCodeAt(index % Math.max(1, left.length)) || 0) ^ (right.charCodeAt(index % Math.max(1, right.length)) || 0);
  return result === 0;
}

function aggiungiIscrizione_(payload) {
  const risultato = registraIscrizioneCentrale_(payload);
  if (!risultato.complete) return risultato;
  // MySQL acknowledges the central replica independently of slow sheet formatting.
  // The periodic event job refreshes the views and retains pending operator edits.
  if (payload.canonical_source === 'MYSQL') {
    risultato.central_complete = true;
    risultato.event_sheet_complete = false;
    risultato.event_sheet_pending = true;
    return risultato;
  }
  // La consegna al foglio avviene dopo il rilascio del lock del registro centrale.
  try {
    aggiornaFoglioOperativoEvento({ id_evento: String(payload.event_id) });
    risultato.event_sheet_complete = true;
    return risultato;
  } catch (errore) {
    aggiungiControllo_('APPEND_REGISTRATION', 'EVENT_SHEET', String(payload.order_code), 'ERROR', 'WORDPRESS', normalizzaTesto_(errore.message, 300), 'WORDPRESS_PROXY');
    return { ok: false, complete: false, central_complete: true, event_sheet_complete: false, order_code: risultato.order_code, error: 'EVENT_SHEET_PENDING' };
  }
}

function registraIscrizioneCentrale_(payload) {
  const workspaceRevision = String(payload.workspace_revision === undefined ? '0' : payload.workspace_revision);
  if (!/^(0|[1-9][0-9]{0,19})$/.test(workspaceRevision)) return { ok: false, error: 'INVALID_WORKSPACE_REVISION' };
  const orderCode = normalizzaTesto_(payload.order_code, 64);
  const eventId = normalizzaTesto_(payload.event_id, 64);
  const idempotencyKey = normalizzaTesto_(payload.idempotency_key, 64);
  const buyer = payload.buyer || {};
  const participants = Array.isArray(payload.participants) ? payload.participants : [];
	const tickets = Array.isArray(payload.tickets) ? payload.tickets : [];
  const snapshotJson = String(payload.snapshot_json || '');
	const revisionId = normalizzaTesto_(payload.event_revision_id, 40);
	const revisionHash = normalizzaTesto_(payload.event_revision_hash, 64);
	const registrationStatus = normalizzaValoreElenco_(payload.status, ['PENDING_PAYMENT', 'CONFIRMED', 'WAITLISTED', 'CANCELLED', 'EXPIRED']);
	const economicMode = normalizzaValoreElenco_(payload.economic_mode, ['REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE']);
  if (!/^[A-Za-z0-9_-]{3,64}$/.test(orderCode) || !/^\d+$/.test(eventId) || !/^[A-Za-z0-9_-]{16,64}$/.test(idempotencyKey) || !registrationStatus || !economicMode || participants.length < 1 || participants.length > 20) return { ok: false, error: 'INVALID_REGISTRATION' };
	if (!/^\d+$/.test(revisionId) || !/^[a-f0-9]{64}$/i.test(revisionHash) || !normalizzaTesto_(payload.privacy_consent_id, 100) || !normalizzaTesto_(payload.privacy_policy_version, 64) || !normalizzaTesto_(payload.privacy_accepted_at, 40)) return { ok: false, error: 'INVALID_REVISION_OR_CONSENT' };
	if (!snapshotJson || snapshotJson.length > 45000) return { ok: false, error: snapshotJson ? 'SNAPSHOT_TOO_LARGE' : 'SNAPSHOT_REQUIRED' };
	let snapshotData;
	try { snapshotData = JSON.parse(snapshotJson); } catch (snapshotError) { return { ok: false, error: 'INVALID_SNAPSHOT' }; }
	if (!snapshotData || typeof snapshotData !== 'object' || Array.isArray(snapshotData)) return { ok: false, error: 'INVALID_SNAPSHOT' };
	if (!normalizzaTesto_(buyer.first_name, 80) || !normalizzaTesto_(buyer.last_name, 80) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(buyer.email || ''))) return { ok: false, error: 'INVALID_BUYER' };
	const ticketCounts = {};
	let ticketQuantity = 0;
	if (!tickets.length || tickets.some(function (ticket) {
		const code = normalizzaTesto_(ticket.ticket_type_code || ticket.code, 64);
		const quantity = Number(ticket.quantity);
		if (!/^[A-Za-z0-9_-]{1,64}$/.test(code) || !Number.isInteger(quantity) || quantity < 1 || quantity > 20 || ticketCounts[code]) return true;
		ticketCounts[code] = quantity;
		ticketQuantity += quantity;
		return false;
	}) || ticketQuantity !== participants.length) return { ok: false, error: 'INVALID_TICKETS' };
	const participantIndexes = {};
	if (participants.some(function (participant, participantPosition) {
		const fieldsJson = JSON.stringify(participant.fields || {});
		const optionsJson = JSON.stringify(participant.options || []);
		const code = normalizzaTesto_(participant.ticket_type_code, 64);
		const ticketIndex = Number(participant.ticket_index);
		const indexKey = code + ':' + ticketIndex;
		if (!/^[A-Za-z0-9_-]{1,64}$/.test(code) || !Number.isInteger(ticketIndex) || ticketIndex < 1 || ticketIndex > Number(ticketCounts[code] || 0) || participantIndexes[indexKey]) return true;
		participantIndexes[indexKey] = true;
		const firstName = normalizzaTesto_(participant.first_name, 80);
		const lastName = normalizzaTesto_(participant.last_name, 80);
		return !firstName || !lastName || fieldsJson.length > 5000 || optionsJson.length > 5000;
	})) return { ok: false, error: 'INVALID_PARTICIPANTS' };

  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    if (typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(eventId)) return {ok:false,error:'EVENT_DELETED'};
    const registrations = ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS);
    const registrationRows = convertiRigheInOggetti_(registrations);
    const byKey = registrationRows.find(function (item) { return String(item.chiave_idempotenza) === idempotencyKey; });
    const byCode = registrationRows.find(function (item) { return String(item.codice_ordine) === orderCode; });
    if ((byKey && String(byKey.codice_ordine) !== orderCode) || (byCode && String(byCode.chiave_idempotenza) !== idempotencyKey)) return { ok: false, error: 'IDEMPOTENCY_CONFLICT' };
    const existing = byKey || byCode;
    const previousRevision = String(existing && existing.workspace_revision || '0');
    if (previousRevision.length > workspaceRevision.length || (previousRevision.length === workspaceRevision.length && previousRevision > workspaceRevision)) return { ok: false, complete: false, error: 'STALE_WORKSPACE_REVISION' };
    const registrationValues = [
      neutralizzaFormula_(orderCode, 64),
      neutralizzaFormula_(eventId, 64),
      registrationStatus,
      neutralizzaFormula_(buyer.first_name, 80),
      neutralizzaFormula_(buyer.last_name, 80),
      neutralizzaFormula_(buyer.email, 254),
		neutralizzaFormula_(buyer.phone, 32),
		neutralizzaFormula_(payload.special_requests, 2000),
		participants.length,
      Math.max(0, Math.round(Number(payload.total_cents) || 0)),
      neutralizzaFormula_(idempotencyKey, 64),
	  existing && existing.data_creazione ? existing.data_creazione : new Date(),
	  economicMode,
	  Math.max(0, Math.round(Number(payload.initial_due_cents) || 0)),
	  Math.max(0, Math.round(Number(payload.balance_cents) || 0)),
	  JSON.stringify((Array.isArray(payload.payment_methods) ? payload.payment_methods : []).filter(function (method) { return ['BANK_TRANSFER', 'CARD', 'CASH'].indexOf(method) >= 0; })),
      neutralizzaFormula_(revisionId, 40),
      revisionHash,
      snapshotJson,
      normalizzaTesto_(payload.privacy_consent_id, 100),
      normalizzaTesto_(payload.privacy_policy_version, 64),
	  normalizzaTesto_(payload.privacy_accepted_at, 40),
	  JSON.stringify(tickets),
	  normalizzaTesto_(payload.marketing_consent_id, 100),
	  normalizzaTesto_(payload.marketing_accepted_at, 40),
	  JSON.stringify(Array.isArray(payload.order_options) ? payload.order_options : []),
      workspaceRevision
    ];
    if (existing) registrations.getRange(existing._row, 1, 1, registrationValues.length).setValues([registrationValues]);
    else registrations.appendRow(registrationValues);

    const correzioni = payload.canonical_source === 'MYSQL' ? {} : indiceStatoOperativo_();
    if (payload.canonical_source === 'MYSQL') {
      sincronizzaCamereMysql_(eventId, payload.rooms, payload.workspace_event_revision);
      // Legacy overrides must not hide values now maintained by the canonical service.
      const stato = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE);
      convertiRigheInOggetti_(stato).filter(r => String(r.codice_ordine) === orderCode).sort((a,b) => b._row-a._row).forEach(r => stato.deleteRow(r._row));
    }
    const participantRows = participants.map(function (participant, index) {
      return [
        neutralizzaFormula_(orderCode, 64),
        index + 1,
        neutralizzaFormula_(participant.ticket_type_code, 64),
        Math.max(1, Math.round(Number(participant.ticket_index) || 1)),
        neutralizzaFormula_((correzioni[orderCode + '|' + (index + 1)] || {}).first_name || participant.first_name, 80),
        neutralizzaFormula_((correzioni[orderCode + '|' + (index + 1)] || {}).last_name || participant.last_name, 80),
        JSON.stringify(participant.fields || {}),
        JSON.stringify(participant.options || []),
        normalizzaValoreElenco_(participant.status, ['ACTIVE', 'CANCELLED']) || 'ACTIVE',
        normalizzaTesto_(participant.cancelled_at, 40)
      ];
    });
    const participantSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS);
    convertiRigheInOggetti_(participantSheet).filter(function (row) { return String(row.codice_ordine) === orderCode; }).sort(function (a, b) { return b._row - a._row; }).forEach(function (row) { participantSheet.deleteRow(row._row); });
    participantSheet.getRange(participantSheet.getLastRow() + 1, 1, participantRows.length, participantRows[0].length).setValues(participantRows);
    const outbox = ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX);
    const message = convertiRigheInOggetti_(outbox).find(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION'; });
    const snapshotBuyer = snapshotData && snapshotData.buyer ? snapshotData.buyer : buyer;
    const originalRecipient = normalizzaTesto_(snapshotBuyer.email || buyer.email, 254);
    const currentStatus = normalizzaValoreElenco_(payload.status, ['PENDING_PAYMENT', 'CONFIRMED', 'WAITLISTED']);
    // Una promozione aggiorna lo stato corrente pur conservando l'istantanea
    // originaria WAITLISTED. Le cancellazioni continuano invece a usare lo
    // stato dell'istantanea per non generare una nuova conferma.
    const originalStatus = ['CONFIRMED', 'PENDING_PAYMENT'].indexOf(currentStatus) >= 0 ? currentStatus : normalizzaValoreElenco_(snapshotData && snapshotData.status, ['PENDING_PAYMENT', 'CONFIRMED', 'WAITLISTED']) || currentStatus || 'CONFIRMED';
    const messageValues = [message ? message.id_messaggio : creaIdentificativoOpaco_('msg'), neutralizzaFormula_(orderCode, 64), neutralizzaFormula_(originalRecipient, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: originalStatus }), 'PREVIEW', message && message.data_creazione ? message.data_creazione : new Date()];
    if (message) outbox.getRange(message._row, 1, 1, messageValues.length).setValues([messageValues]); else outbox.appendRow(messageValues);
    sincronizzaPagamenti_(orderCode, payload.payments);
    const registrationComplete = convertiRigheInOggetti_(registrations).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.chiave_idempotenza) === idempotencyKey && String(row.hash_revisione_evento) === revisionHash && String(row.snapshot_json) === snapshotJson; });
    const participantCount = convertiRigheInOggetti_(participantSheet).filter(function (row) { return String(row.codice_ordine) === orderCode; }).length;
    const outboxComplete = convertiRigheInOggetti_(outbox).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION' && String(row.destinatario) === originalRecipient; });
    const complete = registrationComplete && participantCount === participants.length && outboxComplete;
    aggiungiControllo_('APPEND_REGISTRATION', 'REGISTRATION', orderCode, 'SUCCESS', 'WORDPRESS', 'REGISTRATION_RECORDED', 'WORDPRESS_PROXY');
    return { ok: complete, complete: complete, workspace_revision: String(payload.workspace_revision === undefined ? '' : payload.workspace_revision), replayed: Boolean(existing), order_code: orderCode, error: complete ? undefined : 'INCOMPLETE_REPLICA' };
  } finally {
    lock.releaseLock();
  }
}

/** Called under the central script lock. Room snapshots have an event-wide revision. */
function sincronizzaCamereMysql_(eventId, rooms, revision) {
  revision = String(revision);
  if (!/^(0|[1-9][0-9]{0,19})$/.test(revision) || !Array.isArray(rooms)) throw new Error('INVALID_ROOM_SNAPSHOT');
  const codes = new Set();
  const values = rooms.map(room => {
    const code = String(room.code || ''), name = normalizzaTesto_(room.name, 120), capacity = Number(room.capacity);
    if (!/^[A-Za-z0-9_-]{1,80}$/.test(code) || codes.has(code) || !name || !Number.isInteger(capacity) || capacity < 1 || capacity > 1000) throw new Error('INVALID_ROOM_SNAPSHOT');
    codes.add(code);
    return [eventId, code, neutralizzaFormula_(name, 120), capacity, 'SI', ''];
  });
  const versions = ottieniSchedaObbligatoria_(MI_SHEETS.REPLICA_REVISIONS);
  const previous = convertiRigheInOggetti_(versions).find(r => String(r.id_evento) === eventId);
  const old = String(previous && previous.revisione_camere || '0');
  if (old.length > revision.length || (old.length === revision.length && old > revision)) return;
  // Record the high-water mark before changing rows; an identical retry repairs a partial write.
  if (previous) versions.getRange(previous._row, 1, 1, 2).setValues([[eventId, revision]]);
  else versions.appendRow([eventId, revision]);
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS);
  convertiRigheInOggetti_(sheet).filter(r => String(r.id_evento) === eventId).sort((a,b) => b._row-a._row).forEach(r => sheet.deleteRow(r._row));
  if (values.length) sheet.getRange(sheet.getLastRow()+1, 1, values.length, values[0].length).setValues(values);
}

function sincronizzaPagamenti_(orderCode, payments) {
  if (!Array.isArray(payments) || payments.length === 0) return;
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
  const existing = convertiRigheInOggetti_(sheet);
  const kindMap = { PAYMENT: 'INCASSO', REFUND: 'RIMBORSO', INCASSO: 'INCASSO', RIMBORSO: 'RIMBORSO', STORNO: 'STORNO' };
  const sourceMap = { BANK_TRANSFER: 'BONIFICO', CARD: 'CARTA', CASH: 'CONTANTE', BONIFICO: 'BONIFICO', CARTA: 'CARTA', CONTANTE: 'CONTANTE' };
  const installmentMap = { DEPOSIT: 'CAPARRA', BALANCE: 'SALDO', FULL: 'INTERO', OTHER: 'NON_ASSEGNATO', CAPARRA: 'CAPARRA', SALDO: 'SALDO', INTERO: 'INTERO', NON_ASSEGNATO: 'NON_ASSEGNATO' };
  payments.forEach(function (payment) {
    const stableId = String(payment.payment_id || '');
    if (stableId && !/^[1-9][0-9]*$/.test(stableId)) throw new Error('INVALID_PAYMENT_ID');
    const kind = kindMap[String(payment.movement_kind || payment.transaction_kind || '').toUpperCase()];
    const source = sourceMap[String(payment.payment_source || '').toUpperCase()];
    const installment = installmentMap[String(payment.installment_kind || '').toUpperCase()] || 'NON_ASSEGNATO';
    const amount = Math.max(0, Math.round(Number(payment.amount_cents) || 0));
    if (!kind || !source || amount < 1) throw new Error('INVALID_PAYMENT');
    const effective = normalizzaTesto_(payment.effective_at, 40);
    const effectiveDate = effective ? new Date(stableId && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(effective) ? effective.replace(' ', 'T') + 'Z' : effective) : new Date();
    if (isNaN(effectiveDate.getTime())) {
      aggiungiControllo_('SYNC_PAYMENT', 'PAYMENT', orderCode, 'REJECTED', 'WORDPRESS', 'INVALID_EFFECTIVE_AT', 'WORDPRESS_PROXY');
      throw new Error('INVALID_EFFECTIVE_AT');
    }
    const reference = normalizzaTesto_(payment.external_reference, 120);
    const origin = stableId ? 'MYSQL|' + orderCode + '|' + stableId : 'WP|' + orderCode + '|' + kind + '|' + installment + '|' + effective + '|' + amount + '|' + source + '|' + reference;
    const duplicate = existing.find(function (row) { return String(row.id_inserimento_origine) === origin; });
    if (duplicate) {
      if (stableId && (String(duplicate.tipo_movimento) !== kind || Number(duplicate.importo_centesimi) !== amount || String(duplicate.fonte_pagamento) !== source || new Date(duplicate.data_effettiva).getTime() !== effectiveDate.getTime())) throw new Error('PAYMENT_ID_CONFLICT');
      return;
    }
    sheet.appendRow([creaIdentificativoOpaco_('pay'), neutralizzaFormula_(orderCode, 64), kind, installment, effectiveDate, amount, 'EUR', source, neutralizzaFormula_(reference, 120), neutralizzaFormula_(payment.operator_label, 100), 'WORDPRESS', origin, new Date(), neutralizzaFormula_(payment.administrative_note, 500)]);
    existing.push({ id_inserimento_origine: origin, tipo_movimento: kind, importo_centesimi: amount, fonte_pagamento: source, data_effettiva: effectiveDate });
  });
}
