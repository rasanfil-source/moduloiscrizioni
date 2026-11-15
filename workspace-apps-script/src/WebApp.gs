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
	if (envelope.action === 'INVIA_EMAIL_LOTTO') return creaRispostaJson_(inviaEmailLottoDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_PROVA') return creaRispostaJson_(inviaEmailProvaDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_CONFERMA') return creaRispostaJson_(inviaEmailConfermaDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_CONFERMA_MITTENTE') return creaRispostaJson_(envelope.payload && envelope.payload.indirizzo_mittente ? inviaEmailConfermaDaWordPress_(envelope.payload) : {ok:false,error:'INVALID_SENDER_EMAIL'});
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
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:false,error:'WORKSPACE_BUSY'};
  try {
    const cache = CacheService.getScriptCache();
	const nonceKey = Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, nonce)).replace(/=+$/, '');
	const properties = PropertiesService.getScriptProperties();
	// Suddivide il controllo persistente delle ripetizioni per rispettare il limite di 9 KB delle proprietà.
	// La rimozione dalla cache non autorizza la ripetizione; conserva ogni nonce ancora valido.
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
    // La pausa interattiva vale solo per una richiesta autenticata e nuova,
    // dopo lock, controllo replay e registrazione durevole del nonce.
    if (envelope.action === 'PROIETTA_EVENTO' && (envelope.payload || {}).background !== true) {
      try { properties.setProperty('MI_INTERACTIVE_OPEN_UNTIL', String(Date.now()+90000)); }
      catch (error) { console.warn('INTERACTIVE_PAUSE_UNAVAILABLE', String(error)); }
    }
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

/** Viene chiamata sotto il lock dello script. Le sistemazioni condividono una revisione per evento. */
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
  // La revisione da sola non basta: una scrittura precedente può essersi interrotta a metà.
  const actual = existing.map(r => [String(r.id_evento), String(r.codice), String(r.nome), Number(r.capienza), String(r.attiva), String(r.note || '')]);
  if (previous && old === revision && serializzaInModoStabile_(actual) === serializzaInModoStabile_(values)) return;
  // Registra la revisione massima prima di modificare le righe; la stessa richiesta completa una scrittura parziale.
  if (previous) versions.getRange(previous._row, 1, 1, 2).setValues([[eventId, revision]]);
  else versions.appendRow([eventId, revision]);
  eliminaRigheContigue_(sheet, existing);
  if (values.length) { assicuraRighe_(sheet,sheet.getLastRow()+values.length); sheet.getRange(sheet.getLastRow()+1, 1, values.length, values[0].length).setValues(values); }
}

function assicuraRighe_(sheet, required) {
  const available=sheet.getMaxRows();
  if(required>available)sheet.insertRowsAfter(available,required-available);
}

/** Elimina le righe dal basso verso l'alto, conservando quelle degli altri eventi e consentendo la ripresa. */
function eliminaRigheContigue_(sheet, rows) {
  const indices = [...new Set(rows.map(row => row._row))].sort((a,b) => b-a);
  for (let i=0; i<indices.length;) {
    const end=indices[i]; let start=end; i++;
    while (i<indices.length && indices[i]===start-1) { start=indices[i]; i++; }
    sheet.deleteRows(start,end-start+1);
  }
}
