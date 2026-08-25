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
    if (envelope.action !== 'APPEND_REGISTRATION') return creaRispostaJson_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
    return creaRispostaJson_(aggiungiIscrizione_(envelope.payload));
  } catch (error) {
		try { aggiungiControllo_('WEBAPP_REQUEST', 'REQUEST', 'UNAVAILABLE', 'ERROR', 'WORDPRESS', 'UNHANDLED_ERROR', 'WORDPRESS_PROXY'); } catch (auditError) {}
    return creaRispostaJson_({ ok: false, error: 'REQUEST_FAILED' });
  }
}

function verificaBusta_(envelope) {
  const timestamp = Number(envelope.timestamp || 0);
  const nonce = normalizzaTesto_(envelope.nonce, 80);
  const signature = normalizzaTesto_(envelope.signature, 200);
  if (!timestamp || Math.abs(Date.now() - timestamp) > 300000) return { ok: false, error: 'STALE_REQUEST' };
  if (nonce.length < 16 || signature.length < 32) return { ok: false, error: 'INVALID_SIGNATURE' };
  const cache = CacheService.getScriptCache();
  if (cache.get('nonce_' + nonce)) return { ok: false, error: 'REPLAYED_REQUEST' };
  const message = timestamp + '\n' + nonce + '\n' + String(envelope.action || '') + '\n' + serializzaInModoStabile_(envelope.payload || {});
  const digest = Utilities.computeHmacSha256Signature(message, ottieniSegretoScript_());
  const expected = Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
  if (!confrontaInTempoCostante_(expected, signature)) return { ok: false, error: 'INVALID_SIGNATURE' };
  cache.put('nonce_' + nonce, '1', 600);
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
  if (!orderCode || !eventId || idempotencyKey.length < 16 || participants.length < 1 || participants.length > 20) return { ok: false, error: 'INVALID_REGISTRATION' };
	if (!normalizzaTesto_(buyer.first_name, 80) || !normalizzaTesto_(buyer.last_name, 80) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(buyer.email || ''))) return { ok: false, error: 'INVALID_BUYER' };
	if (participants.some(function (participant) {
		const fieldsJson = JSON.stringify(participant.fields || {});
		return !normalizzaTesto_(participant.first_name, 80) || !normalizzaTesto_(participant.last_name, 80) || fieldsJson.length > 5000;
	})) return { ok: false, error: 'INVALID_PARTICIPANTS' };

  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const registrations = ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS);
    const existing = convertiRigheInOggetti_(registrations).find(function (item) { return String(item.chiave_idempotenza) === idempotencyKey || String(item.codice_ordine) === orderCode; });
    if (existing) { sincronizzaPagamenti_(orderCode, payload.payments); return { ok: true, replayed: true, order_code: orderCode }; }

    registrations.appendRow([
      neutralizzaFormula_(orderCode, 64),
      neutralizzaFormula_(eventId, 64),
      normalizzaValoreElenco_(payload.status, ['CONFIRMED', 'WAITLISTED']) || 'CONFIRMED',
      neutralizzaFormula_(buyer.first_name, 80),
      neutralizzaFormula_(buyer.last_name, 80),
      neutralizzaFormula_(buyer.email, 254),
      neutralizzaFormula_(buyer.phone, 32),
      participants.length,
      Math.max(0, Math.round(Number(payload.total_cents) || 0)),
      neutralizzaFormula_(idempotencyKey, 64),
	  new Date(),
	  normalizzaValoreElenco_(payload.economic_mode, ['REGISTRATION_ONLY', 'PRICE_ONLY', 'FULL_PAYMENT', 'DEPOSIT_BALANCE']) || 'REGISTRATION_ONLY',
	  Math.max(0, Math.round(Number(payload.initial_due_cents) || 0)),
	  Math.max(0, Math.round(Number(payload.balance_cents) || 0)),
	  JSON.stringify((Array.isArray(payload.payment_methods) ? payload.payment_methods : []).filter(function (method) { return ['BANK_TRANSFER', 'CARD', 'CASH'].indexOf(method) >= 0; }))
    ]);

    const participantRows = participants.map(function (participant, index) {
      return [
        neutralizzaFormula_(orderCode, 64),
        index + 1,
        neutralizzaFormula_(participant.first_name, 80),
        neutralizzaFormula_(participant.last_name, 80),
        JSON.stringify(participant.fields || {})
      ];
    });
    ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS).getRange(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS).getLastRow() + 1, 1, participantRows.length, participantRows[0].length).setValues(participantRows);
    ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX).appendRow([creaIdentificativoOpaco_('msg'), neutralizzaFormula_(orderCode, 64), neutralizzaFormula_(buyer.email, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: payload.status }), 'PREVIEW', new Date()]);
    sincronizzaPagamenti_(orderCode, payload.payments);
    aggiungiControllo_('APPEND_REGISTRATION', 'REGISTRATION', orderCode, 'SUCCESS', 'WORDPRESS', 'REGISTRATION_RECORDED', 'WORDPRESS_PROXY');
    return { ok: true, replayed: false, order_code: orderCode };
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
  payments.slice(0, 100).forEach(function (payment) {
    const kind = kindMap[String(payment.transaction_kind || '').toUpperCase()];
    const source = sourceMap[String(payment.payment_source || '').toUpperCase()];
    const amount = Math.max(0, Math.round(Number(payment.amount_cents) || 0));
    if (!kind || !source || amount < 1) return;
    const effective = normalizzaTesto_(payment.effective_at, 40);
    const reference = normalizzaTesto_(payment.external_reference, 120);
    const origin = 'WP|' + orderCode + '|' + kind + '|' + effective + '|' + amount + '|' + source + '|' + reference;
    if (existing.some(function (row) { return String(row.id_inserimento_origine) === origin; })) return;
    sheet.appendRow([creaIdentificativoOpaco_('pay'), neutralizzaFormula_(orderCode, 64), kind, normalizzaValoreElenco_(payment.installment_kind, MI_PAYMENT_ENUMS.installmentKinds) || 'NON_ASSEGNATO', effective ? new Date(effective) : new Date(), amount, 'EUR', source, neutralizzaFormula_(reference, 120), neutralizzaFormula_(payment.operator_label, 100), 'WORDPRESS', origin, new Date()]);
    existing.push({ id_inserimento_origine: origin });
  });
}
