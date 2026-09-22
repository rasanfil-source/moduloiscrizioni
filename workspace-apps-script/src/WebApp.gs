function doGet(event) {
  return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, standalone: progettoAutonomo_(), mode: 'PREVIEW' });
}

function progettoAutonomo_() { return typeof MI_STANDALONE_MODE !== 'undefined' && MI_STANDALONE_MODE === true; }

function doPost(event) {
  try {
    if (!event || !event.postData || !event.postData.contents) return creaRispostaJson_({ ok: false, error: 'EMPTY_PAYLOAD' });
    const envelope = JSON.parse(event.postData.contents);
    const verified = verificaBusta_(envelope);
    if (!verified.ok) return creaRispostaJson_(verified.error === 'WORKSPACE_BUSY' && envelope.action === 'PROIETTA_EVENTO' ? {ok:true,ready:false,busy:true,retry_after:3} : { ok: false, error: verified.error });
    if (envelope.action === 'STATO_SCHEMA') return creaRispostaJson_({ ok: true, schema_version: MI_SCHEMA_VERSION, direct_projection: progettoAutonomo_(), standalone: progettoAutonomo_(), central_workbook: !progettoAutonomo_(), projection_pull: progettoAutonomo_(), mode: 'PREVIEW' });
    if (['PROIETTA_EVENTO','ELIMINA_DATI_EVENTO','VERIFICA_FOGLIO_EVENTO','VERIFICA_FOGLI_EVENTO','ORGANIZZA_FOGLI_EVENTO','LEGGI_MODIFICHE_FOGLIO','CONFERMA_MODIFICHE_FOGLIO'].includes(envelope.action) && !progettoAutonomo_()) return creaRispostaJson_({ok:false,error:'USE_STANDALONE_PROJECT'});
    if (envelope.action === 'ELIMINA_DATI_EVENTO') return creaRispostaJson_(envelope.payload.direct_projection === true ? eliminaDatiEventoDaWordPress_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
    if (envelope.action === 'PING') return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
	if (envelope.action === 'VERIFICA_FOGLIO_EVENTO') return creaRispostaJson_(envelope.payload.direct_projection === true ? verificaFoglioEventoDaWordPress_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
	if (envelope.action === 'VERIFICA_FOGLI_EVENTO') return creaRispostaJson_(envelope.payload.direct_projection === true ? verificaFogliEventoDaWordPress_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
	if (envelope.action === 'ORGANIZZA_FOGLI_EVENTO') return creaRispostaJson_(envelope.payload.direct_projection === true ? organizzaFogliEventoDaWordPress_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
	if (envelope.action === 'INVIA_EMAIL_PROVA') return creaRispostaJson_(inviaEmailProvaDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_CONFERMA') return creaRispostaJson_(inviaEmailConfermaDaWordPress_(envelope.payload));
	if (envelope.action === 'STATO_CANALE_EMAIL') return creaRispostaJson_(statoCanaleEmail_());
    if (envelope.action === 'PROIETTA_EVENTO') return creaRispostaJson_(proiettaEventoDaWordPress_(envelope.payload));
    if (envelope.action === 'REGISTRA_PAGAMENTO_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_PAYMENT_LEDGER'});
    if (envelope.action === 'LEGGI_MODIFICHE_FOGLIO') return creaRispostaJson_(envelope.payload.direct_projection === true ? leggiModificheEventoMysql_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
    if (envelope.action === 'CONFERMA_MODIFICHE_FOGLIO') return creaRispostaJson_(envelope.payload.direct_projection === true ? confermaModificheFoglio_(envelope.payload) : {ok:false,error:'USE_DIRECT_PROJECTION'});
    if (envelope.action === 'SCHEDA_GESTIONE_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_MANAGEMENT'});
    if (envelope.action === 'AGGIORNA_GESTIONE_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_MANAGEMENT'});
    return creaRispostaJson_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
  } catch (error) {
		console.error('WEBAPP_REQUEST_FAILED', error && error.stack ? error.stack : String(error));
    return creaRispostaJson_({ ok: false, error: 'REQUEST_FAILED', diagnostic: normalizzaTesto_(error && error.message ? error.message : String(error), 300) });
  }
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
  const participantRows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (item) {
    return String(item.codice_ordine) === orderCode;
  });
	const registrationClosed = ['CANCELLED', 'EXPIRED', 'ANNULLATO', 'SCADUTO'].indexOf(String(registration.stato || '').toUpperCase()) >= 0;
	const activeParticipantCount = registrationClosed ? 0 : participantRows.filter(function (item) { return String(item.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED'; }).length;
	const centralComplete = participantRows.length > 0 && activeParticipantCount === expected;
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
  if (typeof envelope.payload_firmato === 'string') {
    if (envelope.payload_firmato.length > 2000000) return {ok:false,error:'PAYLOAD_TOO_LARGE'};
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
  // Only authenticated requests can pause background projections. A short lease
  // gives the interactive opener a turn after the current writer finishes.
  if (envelope.action === 'PROIETTA_EVENTO' && (envelope.payload || {}).background !== true) PropertiesService.getScriptProperties().setProperty('MI_INTERACTIVE_OPEN_UNTIL', String(Date.now()+90000));
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:false,error:'WORKSPACE_BUSY'};
  try {
    const cache = CacheService.getScriptCache();
	const nonceKey = Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, nonce)).replace(/=+$/, '');
	const properties = PropertiesService.getScriptProperties();
	// Shard the durable replay guard below the 9 KB property limit. Cache eviction
	// must not authorize replay; never evict a still-valid durable nonce.
	const bucketKey='MI_USED_NONCES_'+nonceKey.charAt(0);
	const durableNonces=JSON.parse(properties.getProperty(bucketKey)||'{}');
	const legacy=JSON.parse(properties.getProperty('MI_USED_NONCES')||'{}');
	Object.keys(durableNonces).forEach(key=>{if(Number(durableNonces[key])<Date.now())delete durableNonces[key];});
	if(cache.get('nonce_'+nonce)||durableNonces[nonceKey]||Number(legacy[nonceKey])>Date.now()-250000)return {ok:false,error:'REPLAYED_REQUEST'};
	durableNonces[nonceKey]=timestamp+121000;
	const encoded=JSON.stringify(durableNonces);
	if(encoded.length>8000)return {ok:false,error:'WORKSPACE_BUSY'};
	properties.setProperty(bucketKey,encoded);
	cache.put('nonce_'+nonce,'1',250);
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
	const activeParticipantCount = ['CANCELLED', 'EXPIRED'].indexOf(registrationStatus) >= 0 ? 0 : participants.filter(function (participant) { return normalizzaValoreElenco_(participant.status, ['ACTIVE', 'CANCELLED']) !== 'CANCELLED'; }).length;
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
		activeParticipantCount,
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
      workspaceRevision,
      payload.paid_cents == null ? '' : Math.max(0, Math.round(Number(payload.paid_cents) || 0)),
      '' // Cleared before writes; revision alone must not certify a partial replica.
    ];
    const registrationRow = existing ? existing._row : registrations.getLastRow() + 1;
    registrations.getRange(registrationRow, 1, 1, registrationValues.length).setValues([registrationValues]);

    const correzioni = payload.canonical_source === 'MYSQL' ? {} : indiceStatoOperativo_();
    if (payload.canonical_source === 'MYSQL') {
      sincronizzaCamereMysql_(eventId, payload.rooms, payload.workspace_event_revision);
      // Legacy overrides must not hide values now maintained by the canonical service.
      const stato = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE);
      eliminaRigheContigue_(stato, convertiRigheInOggetti_(stato).filter(r => String(r.codice_ordine) === orderCode));
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
        normalizzaTesto_(participant.cancelled_at, 40),
		participant.total_cents == null ? '' : Math.max(0, Math.round(Number(participant.total_cents) || 0)),
		participant.paid_cents == null ? '' : Math.max(0, Math.round(Number(participant.paid_cents) || 0)),
		participant.balance_cents == null ? '' : Math.max(0, Math.round(Number(participant.balance_cents) || 0)),
		participant.deposit_due_cents == null ? '' : Math.max(0, Math.round(Number(participant.deposit_due_cents) || 0)),
		participant.deposit_missing_cents == null ? '' : Math.max(0, Math.round(Number(participant.deposit_missing_cents) || 0))
      ];
    });
    const participantSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS);
    const oldRows=convertiRigheInOggetti_(participantSheet).filter(row=>String(row.codice_ordine)===orderCode).sort((a,b)=>a._row-b._row);
    const contiguous=oldRows.length===participantRows.length && oldRows.every((row,i)=>row._row===oldRows[0]._row+i);
    let participantStartRow;
    if (contiguous) {
      participantStartRow = oldRows[0]._row;
      participantSheet.getRange(participantStartRow,1,participantRows.length,participantRows[0].length).setValues(participantRows);
    }
    else {
      eliminaRigheContigue_(participantSheet,oldRows);
      assicuraRighe_(participantSheet,participantSheet.getLastRow()+participantRows.length);
      participantStartRow = participantSheet.getLastRow()+1;
      participantSheet.getRange(participantStartRow,1,participantRows.length,participantRows[0].length).setValues(participantRows);
    }
    const outbox = ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX);
    const message = convertiRigheInOggetti_(outbox).find(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION'; });
    const snapshotBuyer = snapshotData && snapshotData.buyer ? snapshotData.buyer : buyer;
    const originalRecipient = normalizzaTesto_(snapshotBuyer.email || buyer.email, 254);
    const currentStatus = normalizzaValoreElenco_(payload.status, ['PENDING_PAYMENT', 'CONFIRMED', 'WAITLISTED']);
    // Una promozione aggiorna lo stato corrente pur conservando l'istantanea
    // originaria WAITLISTED. Le cancellazioni continuano invece a usare lo
    // stato dell'istantanea per non generare una nuova conferma.
    const originalStatus = ['CONFIRMED', 'PENDING_PAYMENT'].indexOf(currentStatus) >= 0 ? currentStatus : normalizzaValoreElenco_(snapshotData && snapshotData.status, ['PENDING_PAYMENT', 'CONFIRMED', 'WAITLISTED']) || currentStatus || 'CONFIRMED';
    const messageValues = [message ? message.id_messaggio : creaIdentificativoOpaco_('msg'), neutralizzaFormula_(orderCode, 64), neutralizzaFormula_(originalRecipient, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: originalStatus }), message ? message.stato : 'PREVIEW', message && message.data_creazione ? message.data_creazione : new Date()];
    const outboxRow = message ? message._row : outbox.getLastRow() + 1;
    outbox.getRange(outboxRow, 1, 1, messageValues.length).setValues([messageValues]);
    sincronizzaPagamenti_(orderCode, payload.payments);
    // Verifica soltanto le righe appena scritte: rileggere ogni volta l'intera
    // storia di tre fogli rendeva il costo di una replica crescente nel tempo.
    const savedRegistration = registrations.getRange(registrationRow, 1, 1, registrationValues.length).getValues()[0];
    const registrationComplete = String(savedRegistration[0]) === orderCode && String(savedRegistration[10]) === idempotencyKey && String(savedRegistration[17]) === revisionHash && String(savedRegistration[18]) === snapshotJson;
    const savedParticipantCodes = participantSheet.getRange(participantStartRow, 1, participants.length, 1).getValues();
    const participantsComplete = savedParticipantCodes.length === participants.length && savedParticipantCodes.every(function (row) { return String(row[0]) === orderCode; });
    const savedMessage = outbox.getRange(outboxRow, 1, 1, messageValues.length).getValues()[0];
    const outboxComplete = String(savedMessage[1]) === orderCode && String(savedMessage[2]) === originalRecipient && String(savedMessage[3]) === 'REGISTRATION_CONFIRMATION';
    const complete = registrationComplete && participantsComplete && outboxComplete;
    if (complete) {
      registrations.getRange(registrationRow,registrationValues.length,1,1).setValues([[workspaceRevision]]);
    }
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
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS);
  const existing = convertiRigheInOggetti_(sheet).filter(r => String(r.id_evento) === eventId);
  // The revision alone is insufficient: a prior writer may have stopped mid-write.
  const actual = existing.map(r => [String(r.id_evento), String(r.codice), String(r.nome), Number(r.capienza), String(r.attiva), String(r.note || '')]);
  if (previous && old === revision && serializzaInModoStabile_(actual) === serializzaInModoStabile_(values)) return;
  // Record the high-water mark before changing rows; an identical retry repairs a partial write.
  if (previous) versions.getRange(previous._row, 1, 1, 2).setValues([[eventId, revision]]);
  else versions.appendRow([eventId, revision]);
  eliminaRigheContigue_(sheet, existing);
  if (values.length) { assicuraRighe_(sheet,sheet.getLastRow()+values.length); sheet.getRange(sheet.getLastRow()+1, 1, values.length, values[0].length).setValues(values); }
}

function assicuraRighe_(sheet, required) {
  const available=sheet.getMaxRows();
  if(required>available)sheet.insertRowsAfter(available,required-available);
}

/** Delete bottom-up, preserving rows belonging to other events and retry repair. */
function eliminaRigheContigue_(sheet, rows) {
  const indices = [...new Set(rows.map(row => row._row))].sort((a,b) => b-a);
  for (let i=0; i<indices.length;) {
    const end=indices[i]; let start=end; i++;
    while (i<indices.length && indices[i]===start-1) { start=indices[i]; i++; }
    sheet.deleteRows(start,end-start+1);
  }
}

function sincronizzaPagamenti_(orderCode, payments) {
  if (!Array.isArray(payments) || payments.length === 0) return;
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
  const existing = convertiRigheInOggetti_(sheet);
  const byOrigin = new Map(existing.map(row=>[String(row.id_inserimento_origine),row]));
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
	let allocations = payment.participant_allocations_json || [];
	try { if (typeof allocations === 'string') allocations = allocations ? JSON.parse(allocations) : []; } catch (error) { throw new Error('INVALID_PAYMENT_ALLOCATIONS'); }
	if (!Array.isArray(allocations)) throw new Error('INVALID_PAYMENT_ALLOCATIONS');
	const participantIds = new Set();
	let allocated = 0;
	allocations = allocations.map(function (allocation) {
	  const participantId = Math.round(Number(allocation && allocation.participant_id));
	  const allocatedAmount = Math.round(Number(allocation && allocation.amount_cents));
	  if (participantId < 1 || allocatedAmount < 1 || participantIds.has(participantId)) throw new Error('INVALID_PAYMENT_ALLOCATIONS');
	  participantIds.add(participantId); allocated += allocatedAmount;
	  return { participant_id: participantId, name: normalizzaTesto_(allocation.name, 200), amount_cents: allocatedAmount };
	});
	if (allocations.length && allocated !== amount) throw new Error('INVALID_PAYMENT_ALLOCATIONS');
	const allocationsJson = allocations.length ? JSON.stringify(allocations) : '';
    const effective = normalizzaTesto_(payment.effective_at, 40);
    // WordPress sends SQL datetimes in UTC, including legacy rows without an ID.
    const effectiveDate = effective ? new Date(/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/.test(effective) ? effective.replace(' ', 'T') + 'Z' : effective) : new Date();
    if (isNaN(effectiveDate.getTime())) {
      aggiungiControllo_('SYNC_PAYMENT', 'PAYMENT', orderCode, 'REJECTED', 'WORDPRESS', 'INVALID_EFFECTIVE_AT', 'WORDPRESS_PROXY');
      throw new Error('INVALID_EFFECTIVE_AT');
    }
    const reference = normalizzaTesto_(payment.external_reference, 120);
    const origin = stableId ? 'MYSQL|' + orderCode + '|' + stableId : 'WP|' + orderCode + '|' + kind + '|' + installment + '|' + effective + '|' + amount + '|' + source + '|' + reference;
    const duplicate = byOrigin.get(origin);
    if (duplicate) {
	  if (stableId && (String(duplicate.tipo_movimento) !== kind || Number(duplicate.importo_centesimi) !== amount || String(duplicate.fonte_pagamento) !== source || new Date(duplicate.data_effettiva).getTime() !== effectiveDate.getTime() || String(duplicate.attribuzioni_partecipanti_json || '') !== allocationsJson)) throw new Error('PAYMENT_ID_CONFLICT');
	  return;
	}
	sheet.appendRow([creaIdentificativoOpaco_('pay'), neutralizzaFormula_(orderCode, 64), kind, installment, effectiveDate, amount, 'EUR', source, neutralizzaFormula_(reference, 120), neutralizzaFormula_(payment.operator_label, 100), 'WORDPRESS', origin, new Date(), neutralizzaFormula_(payment.administrative_note, 500), allocationsJson]);
	byOrigin.set(origin, { id_inserimento_origine: origin, tipo_movimento: kind, importo_centesimi: amount, fonte_pagamento: source, data_effettiva: effectiveDate, attribuzioni_partecipanti_json: allocationsJson });
  });
}
