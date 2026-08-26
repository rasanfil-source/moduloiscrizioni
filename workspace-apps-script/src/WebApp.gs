function doGet() {
  return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
}

function doPost(event) {
  try {
    if (!event || !event.postData || !event.postData.contents) return creaRispostaJson_({ ok: false, error: 'EMPTY_PAYLOAD' });
    const envelope = JSON.parse(event.postData.contents);
    const verified = verificaBusta_(envelope);
    if (!verified.ok) return creaRispostaJson_({ ok: false, error: verified.error });
    if (envelope.action === 'PING') return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
	if (envelope.action === 'STATO_SCHEMA') return creaRispostaJson_({ ok: true, schema_version: MI_SCHEMA_VERSION, registration_headers: MI_HEADERS[MI_SHEETS.REGISTRATIONS], mode: 'PREVIEW' });
    if (envelope.action === 'ELENCA_PAGAMENTI') return creaRispostaJson_(elencaPagamenti_(envelope.payload));
    if (envelope.action !== 'APPEND_REGISTRATION') return creaRispostaJson_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
    return creaRispostaJson_(aggiungiIscrizione_(envelope.payload));
  } catch (error) {
		try { aggiungiControllo_('WEBAPP_REQUEST', 'REQUEST', 'UNAVAILABLE', 'ERROR', 'WORDPRESS', 'UNHANDLED_ERROR', 'WORDPRESS_PROXY'); } catch (auditError) {}
    return creaRispostaJson_({ ok: false, error: 'REQUEST_FAILED' });
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

function verificaBusta_(envelope) {
  const timestamp = Number(envelope.timestamp || 0);
  const nonce = normalizzaTesto_(envelope.nonce, 80);
  const signature = normalizzaTesto_(envelope.signature, 200);
  if (!timestamp || Math.abs(Date.now() - timestamp) > 120000) return { ok: false, error: 'STALE_REQUEST' };
  if (nonce.length < 16 || signature.length < 32) return { ok: false, error: 'INVALID_SIGNATURE' };
  const message = timestamp + '\n' + nonce + '\n' + String(envelope.action || '') + '\n' + serializzaInModoStabile_(envelope.payload || {});
  const digest = Utilities.computeHmacSha256Signature(message, ottieniSegretoScript_());
  const expected = Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
  if (!confrontaInTempoCostante_(expected, signature)) return { ok: false, error: 'INVALID_SIGNATURE' };
  const lock = LockService.getScriptLock();
  lock.waitLock(5000);
  try {
    const cache = CacheService.getScriptCache();
    if (cache.get('nonce_' + nonce)) return { ok: false, error: 'REPLAYED_REQUEST' };
    cache.put('nonce_' + nonce, '1', 180);
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
  const orderCode = normalizzaTesto_(payload.order_code, 64);
  const eventId = normalizzaTesto_(payload.event_id, 64);
  const idempotencyKey = normalizzaTesto_(payload.idempotency_key, 64);
  const buyer = payload.buyer || {};
  const participants = Array.isArray(payload.participants) ? payload.participants : [];
	const tickets = Array.isArray(payload.tickets) ? payload.tickets : [];
  const snapshotJson = String(payload.snapshot_json || '');
	const revisionId = normalizzaTesto_(payload.event_revision_id, 40);
	const revisionHash = normalizzaTesto_(payload.event_revision_hash, 64);
	const registrationStatus = normalizzaValoreElenco_(payload.status, ['CONFIRMED', 'WAITLISTED', 'CANCELLED', 'EXPIRED']);
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
		const hasOptionalData = firstName || lastName || Object.keys(participant.fields || {}).some(function (key) { return normalizzaTesto_(participant.fields[key], 5000); }) || Object.keys(participant.options || {}).some(function (key) { return Number(participant.options[key]) > 0; });
		return (participantPosition === 0 || hasOptionalData) && (!firstName || !lastName) || fieldsJson.length > 5000 || optionsJson.length > 5000;
	})) return { ok: false, error: 'INVALID_PARTICIPANTS' };

  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const registrations = ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS);
    const registrationRows = convertiRigheInOggetti_(registrations);
    const byKey = registrationRows.find(function (item) { return String(item.chiave_idempotenza) === idempotencyKey; });
    const byCode = registrationRows.find(function (item) { return String(item.codice_ordine) === orderCode; });
    if ((byKey && String(byKey.codice_ordine) !== orderCode) || (byCode && String(byCode.chiave_idempotenza) !== idempotencyKey)) return { ok: false, error: 'IDEMPOTENCY_CONFLICT' };
    const existing = byKey || byCode;
    const registrationValues = [
      neutralizzaFormula_(orderCode, 64),
      neutralizzaFormula_(eventId, 64),
      registrationStatus,
      neutralizzaFormula_(buyer.first_name, 80),
      neutralizzaFormula_(buyer.last_name, 80),
      neutralizzaFormula_(buyer.email, 254),
      neutralizzaFormula_(buyer.phone, 32),
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
	  JSON.stringify(Array.isArray(payload.order_options) ? payload.order_options : [])
    ];
    if (existing) registrations.getRange(existing._row, 1, 1, registrationValues.length).setValues([registrationValues]);
    else registrations.appendRow(registrationValues);

    const participantRows = participants.map(function (participant, index) {
      return [
        neutralizzaFormula_(orderCode, 64),
        index + 1,
        neutralizzaFormula_(participant.ticket_type_code, 64),
        Math.max(1, Math.round(Number(participant.ticket_index) || 1)),
        neutralizzaFormula_(participant.first_name, 80),
        neutralizzaFormula_(participant.last_name, 80),
        JSON.stringify(participant.fields || {}),
        JSON.stringify(participant.options || [])
      ];
    });
    const participantSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS);
    convertiRigheInOggetti_(participantSheet).filter(function (row) { return String(row.codice_ordine) === orderCode; }).sort(function (a, b) { return b._row - a._row; }).forEach(function (row) { participantSheet.deleteRow(row._row); });
    participantSheet.getRange(participantSheet.getLastRow() + 1, 1, participantRows.length, participantRows[0].length).setValues(participantRows);
    const outbox = ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX);
    const message = convertiRigheInOggetti_(outbox).find(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION'; });
    const snapshotBuyer = snapshotData && snapshotData.buyer ? snapshotData.buyer : buyer;
    const originalRecipient = normalizzaTesto_(snapshotBuyer.email || buyer.email, 254);
    const currentStatus = normalizzaValoreElenco_(payload.status, ['CONFIRMED', 'WAITLISTED']);
    // Una promozione aggiorna lo stato corrente pur conservando l'istantanea
    // originaria WAITLISTED. Le cancellazioni continuano invece a usare lo
    // stato dell'istantanea per non generare una nuova conferma.
    const originalStatus = currentStatus === 'CONFIRMED' ? 'CONFIRMED' : normalizzaValoreElenco_(snapshotData && snapshotData.status, ['CONFIRMED', 'WAITLISTED']) || currentStatus || 'CONFIRMED';
    const messageValues = [message ? message.id_messaggio : creaIdentificativoOpaco_('msg'), neutralizzaFormula_(orderCode, 64), neutralizzaFormula_(originalRecipient, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: originalStatus }), 'PREVIEW', message && message.data_creazione ? message.data_creazione : new Date()];
    if (message) outbox.getRange(message._row, 1, 1, messageValues.length).setValues([messageValues]); else outbox.appendRow(messageValues);
    sincronizzaPagamenti_(orderCode, payload.payments);
    const registrationComplete = convertiRigheInOggetti_(registrations).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.chiave_idempotenza) === idempotencyKey && String(row.hash_revisione_evento) === revisionHash && String(row.snapshot_json) === snapshotJson; });
    const participantCount = convertiRigheInOggetti_(participantSheet).filter(function (row) { return String(row.codice_ordine) === orderCode; }).length;
    const outboxComplete = convertiRigheInOggetti_(outbox).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION' && String(row.destinatario) === originalRecipient; });
    const complete = registrationComplete && participantCount === participants.length && outboxComplete;
    aggiungiControllo_('APPEND_REGISTRATION', 'REGISTRATION', orderCode, 'SUCCESS', 'WORDPRESS', 'REGISTRATION_RECORDED', 'WORDPRESS_PROXY');
    return { ok: complete, complete: complete, replayed: Boolean(existing), order_code: orderCode, error: complete ? undefined : 'INCOMPLETE_REPLICA' };
  } finally {
    lock.releaseLock();
  }
}

function sincronizzaPagamenti_(orderCode, payments) {
  if (!Array.isArray(payments) || payments.length === 0) return;
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
  const existing = convertiRigheInOggetti_(sheet);
  const kindMap = { PAYMENT: 'INCASSO', REFUND: 'RIMBORSO', INCASSO: 'INCASSO', RIMBORSO: 'RIMBORSO', STORNO: 'STORNO' };
  const sourceMap = { BANK_TRANSFER: 'BONIFICO', CARD: 'CARTA', CASH: 'CONTANTE', BONIFICO: 'BONIFICO', CARTA: 'CARTA', CONTANTE: 'CONTANTE' };
  const installmentMap = { DEPOSIT: 'CAPARRA', BALANCE: 'SALDO', FULL: 'INTERO', OTHER: 'NON_ASSEGNATO', CAPARRA: 'CAPARRA', SALDO: 'SALDO', INTERO: 'INTERO', NON_ASSEGNATO: 'NON_ASSEGNATO' };
  payments.slice(0, 100).forEach(function (payment) {
    const kind = kindMap[String(payment.transaction_kind || '').toUpperCase()];
    const source = sourceMap[String(payment.payment_source || '').toUpperCase()];
    const installment = installmentMap[String(payment.installment_kind || '').toUpperCase()] || 'NON_ASSEGNATO';
    const amount = Math.max(0, Math.round(Number(payment.amount_cents) || 0));
    if (!kind || !source || amount < 1) return;
    const effective = normalizzaTesto_(payment.effective_at, 40);
    const effectiveDate = effective ? new Date(effective) : new Date();
    if (isNaN(effectiveDate.getTime())) {
      aggiungiControllo_('SYNC_PAYMENT', 'PAYMENT', orderCode, 'REJECTED', 'WORDPRESS', 'INVALID_EFFECTIVE_AT', 'WORDPRESS_PROXY');
      return;
    }
    const reference = normalizzaTesto_(payment.external_reference, 120);
    const origin = 'WP|' + orderCode + '|' + kind + '|' + installment + '|' + effective + '|' + amount + '|' + source + '|' + reference;
    if (existing.some(function (row) { return String(row.id_inserimento_origine) === origin; })) return;
    sheet.appendRow([creaIdentificativoOpaco_('pay'), neutralizzaFormula_(orderCode, 64), kind, installment, effectiveDate, amount, 'EUR', source, neutralizzaFormula_(reference, 120), neutralizzaFormula_(payment.operator_label, 100), 'WORDPRESS', origin, new Date(), neutralizzaFormula_(payment.administrative_note, 500)]);
    existing.push({ id_inserimento_origine: origin });
  });
}
