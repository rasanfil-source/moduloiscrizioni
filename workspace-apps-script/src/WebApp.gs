function doGet() {
  return jsonResponse_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
}

function doPost(event) {
  try {
    if (!event || !event.postData || !event.postData.contents) return jsonResponse_({ ok: false, error: 'EMPTY_PAYLOAD' });
    const envelope = JSON.parse(event.postData.contents);
    const verified = verifyEnvelope_(envelope);
    if (!verified.ok) return jsonResponse_({ ok: false, error: verified.error });
    if (envelope.action === 'PING') return jsonResponse_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
    if (envelope.action !== 'APPEND_REGISTRATION') return jsonResponse_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
    return jsonResponse_(appendRegistration_(envelope.payload));
  } catch (error) {
		try { appendAudit_('WEBAPP_REQUEST', 'REQUEST', 'UNAVAILABLE', 'ERROR', 'WORDPRESS', 'UNHANDLED_ERROR', 'WORDPRESS_PROXY'); } catch (auditError) {}
    return jsonResponse_({ ok: false, error: 'REQUEST_FAILED' });
  }
}

function verifyEnvelope_(envelope) {
  const timestamp = Number(envelope.timestamp || 0);
  const nonce = normalizeText_(envelope.nonce, 80);
  const signature = normalizeText_(envelope.signature, 200);
  if (!timestamp || Math.abs(Date.now() - timestamp) > 300000) return { ok: false, error: 'STALE_REQUEST' };
  if (nonce.length < 16 || signature.length < 32) return { ok: false, error: 'INVALID_SIGNATURE' };
  const cache = CacheService.getScriptCache();
  if (cache.get('nonce_' + nonce)) return { ok: false, error: 'REPLAYED_REQUEST' };
  const message = timestamp + '\n' + nonce + '\n' + String(envelope.action || '') + '\n' + stableStringify_(envelope.payload || {});
  const digest = Utilities.computeHmacSha256Signature(message, getScriptSecret_());
  const expected = Utilities.base64EncodeWebSafe(digest).replace(/=+$/, '');
  if (!constantTimeEquals_(expected, signature)) return { ok: false, error: 'INVALID_SIGNATURE' };
  cache.put('nonce_' + nonce, '1', 600);
  return { ok: true };
}

function constantTimeEquals_(left, right) {
  left = String(left); right = String(right);
  let result = left.length ^ right.length;
  const length = Math.max(left.length, right.length);
  for (let index = 0; index < length; index += 1) result |= (left.charCodeAt(index % Math.max(1, left.length)) || 0) ^ (right.charCodeAt(index % Math.max(1, right.length)) || 0);
  return result === 0;
}

function appendRegistration_(payload) {
  const orderCode = normalizeText_(payload.order_code, 64);
  const eventId = normalizeText_(payload.event_id, 64);
  const idempotencyKey = normalizeText_(payload.idempotency_key, 64);
  const buyer = payload.buyer || {};
  const participants = Array.isArray(payload.participants) ? payload.participants : [];
  if (!orderCode || !eventId || idempotencyKey.length < 16 || participants.length < 1 || participants.length > 20) return { ok: false, error: 'INVALID_REGISTRATION' };
	if (!normalizeText_(buyer.first_name, 80) || !normalizeText_(buyer.last_name, 80) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(buyer.email || ''))) return { ok: false, error: 'INVALID_BUYER' };
	if (participants.some(function (participant) {
		const fieldsJson = JSON.stringify(participant.fields || {});
		return !normalizeText_(participant.first_name, 80) || !normalizeText_(participant.last_name, 80) || fieldsJson.length > 5000;
	})) return { ok: false, error: 'INVALID_PARTICIPANTS' };

  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const registrations = getRequiredSheet_(MI_SHEETS.REGISTRATIONS);
    const existing = rowsAsObjects_(registrations).find(function (item) { return String(item.idempotency_key) === idempotencyKey || String(item.order_code) === orderCode; });
    if (existing) return { ok: true, replayed: true, order_code: orderCode };

    registrations.appendRow([
      neutralizeFormula_(orderCode, 64),
      neutralizeFormula_(eventId, 64),
      normalizeEnum_(payload.status, ['CONFIRMED', 'WAITLISTED']) || 'CONFIRMED',
      neutralizeFormula_(buyer.first_name, 80),
      neutralizeFormula_(buyer.last_name, 80),
      neutralizeFormula_(buyer.email, 254),
      neutralizeFormula_(buyer.phone, 32),
      participants.length,
      Math.max(0, Math.round(Number(payload.total_cents) || 0)),
      neutralizeFormula_(idempotencyKey, 64),
      new Date()
    ]);

    const participantRows = participants.map(function (participant, index) {
      return [
        neutralizeFormula_(orderCode, 64),
        index + 1,
        neutralizeFormula_(participant.first_name, 80),
        neutralizeFormula_(participant.last_name, 80),
        JSON.stringify(participant.fields || {})
      ];
    });
    getRequiredSheet_(MI_SHEETS.PARTICIPANTS).getRange(getRequiredSheet_(MI_SHEETS.PARTICIPANTS).getLastRow() + 1, 1, participantRows.length, participantRows[0].length).setValues(participantRows);
    getRequiredSheet_(MI_SHEETS.EMAIL_OUTBOX).appendRow([makeOpaqueId_('msg'), neutralizeFormula_(orderCode, 64), neutralizeFormula_(buyer.email, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: payload.status }), 'PREVIEW', new Date()]);
    appendAudit_('APPEND_REGISTRATION', 'REGISTRATION', orderCode, 'SUCCESS', 'WORDPRESS', 'REGISTRATION_RECORDED', 'WORDPRESS_PROXY');
    return { ok: true, replayed: false, order_code: orderCode };
  } finally {
    lock.releaseLock();
  }
}
