const MI_TEST_EMAIL_PROPERTY = 'MI_EMAIL_TEST_RECIPIENT';

function statoCanaleEmail_() {
  const sender = String(Session.getEffectiveUser().getEmail() || '').trim().toLowerCase();
  const expected = String(PropertiesService.getScriptProperties().getProperty('MI_EMAIL_SENDER') || '').trim().toLowerCase();
  const authorized = expected && sender === expected;
  return { ok: !!authorized, error: authorized ? '' : 'EMAIL_SENDER_NOT_AUTHORIZED', channel: 'GOOGLE_WORKSPACE', sender: sender };
}

/** Keep a bounded replay guard; MySQL remains the authoritative outbox. */
function pulisciRicevuteEmail_ (props) {
  const now=Date.now(), last=Number(props.getProperty('MI_EMAIL_LEDGER_CLEANED_AT')||0);
  if (now-last<86400000) return;
  const all=props.getProperties();
  Object.keys(all).forEach(key=>{
    if (!/^MI_EMAIL_DELIVERY_[a-f0-9]{64}$/.test(key)) return;
    const value=String(all[key]||''), parts=value.split('|');
    // SENDING is never discarded automatically: its delivery is uncertain.
    if (parts[0]==='ACCEPTED'&&Number(parts[1])>0&&now-Number(parts[1])>90*86400000) props.deleteProperty(key);
  });
  props.setProperty('MI_EMAIL_LEDGER_CLEANED_AT',String(now));
}

/** Signed WordPress outbox. Persist intent before sending: uncertain deliveries require review. */
function inviaEmailConfermaDaWordPress_(payload) {
  const p = payload || {};
  const sender = statoCanaleEmail_();
  if (!sender.ok) return sender;
  const recipient = String(p.destinatario || '').trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient) || !/^[a-f0-9]{64}$/.test(String(p.delivery_key || '')) || !['PROVA', 'OPERATIVO'].includes(p.mode)) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  const props = PropertiesService.getScriptProperties();
  if (p.mode === 'PROVA' && recipient !== String(props.getProperty(MI_TEST_EMAIL_PROPERTY) || '').trim().toLowerCase()) return { ok: false, error: 'TEST_RECIPIENT_MISMATCH' };
  const replyTo = String(p.reply_to || sender.sender).trim().toLowerCase();
  if (p.mode === 'OPERATIVO' && (replyTo.length > 254 || !/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(replyTo))) return { ok: false, error: 'INVALID_REPLY_TO' };
  if (!p.oggetto || !p.testo || !p.html || String(p.oggetto).length > 250 || String(p.testo).length > 100000 || String(p.html).length > 300000 || /[\r\n]/.test(String(p.oggetto))) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return { ok: false, error: 'EMAIL_BUSY' };
  try {
    pulisciRicevuteEmail_(props);
    const key='MI_EMAIL_DELIVERY_'+p.delivery_key;
    const previous=String(props.getProperty(key)||'');
    if (previous) return previous.startsWith('ACCEPTED|') ? { ok: true, channel: 'GOOGLE_WORKSPACE', replayed: true } : { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' };
    if (MailApp.getRemainingDailyQuota() < 1) return { ok: false, error: 'EMAIL_QUOTA_EXCEEDED' };
    props.setProperty(key,'SENDING|'+Date.now());
    const options = { to: recipient, subject: String(p.oggetto), body: String(p.testo), htmlBody: String(p.html), name: 'Parrocchia Sant’Eugenio', replyTo: recipient };
    if (p.mode === 'OPERATIVO') options.replyTo = replyTo;
    if (p.codice_svg) options.inlineImages = { 'mi-registration-code': Utilities.newBlob(String(p.codice_svg), 'image/svg+xml', 'codice-iscrizione.svg') };
    // La quota viene controllata prima di registrare SENDING. Dopo sendEmail,
    // un'eccezione non prova che Google non abbia accettato il messaggio:
    // conserviamo l'intento per evitare duplicati, senza dedurlo dal testo
    // dell'errore (localizzato e non un codice di consegna affidabile).
    try { MailApp.sendEmail(options); }
    catch (error) { console.error('EMAIL_SEND_FAILED', String(error)); return { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' }; }
    props.setProperty(key,'ACCEPTED|'+Date.now());
    return { ok: true, channel: 'GOOGLE_WORKSPACE', sender: sender.sender };
  } finally { lock.releaseLock(); }
}

/** Invia soltanto la prova del Modulo Iscrizioni richiesta da WordPress. */
function inviaEmailProvaDaWordPress_(payload) {
  payload = payload && typeof payload === 'object' ? payload : {};
  const destinatarioConfigurato = String(PropertiesService.getScriptProperties().getProperty(MI_TEST_EMAIL_PROPERTY) || '').trim().toLowerCase();
  const destinatario = normalizzaTesto_(payload.destinatario, 254).trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(destinatarioConfigurato)) return { ok: false, error: 'TEST_RECIPIENT_NOT_CONFIGURED' };
  if (destinatario !== destinatarioConfigurato) return { ok: false, error: 'TEST_RECIPIENT_MISMATCH' };
  const oggetto = normalizzaTesto_(payload.oggetto, 180);
  const testo = String(payload.testo || '').slice(0, 12000);
  const html = String(payload.html || '').slice(0, 60000);
  if (!oggetto || !testo || !html) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  MailApp.sendEmail({ to: destinatarioConfigurato, subject: oggetto, body: testo, htmlBody: html, name: 'Modulo Iscrizioni' });
  return { ok: true, channel: 'GOOGLE_WORKSPACE' };
}

function configuraDestinatarioTestEmail() {
  const ui = SpreadsheetApp.getUi();
  const response = ui.prompt('Destinatario email di test', 'Inserisci l’indirizzo privato che riceverà tutte le prove. Non sarà salvato nel foglio né nel repository.', ui.ButtonSet.OK_CANCEL);
  if (response.getSelectedButton() !== ui.Button.OK) return;
  const recipient = normalizzaTesto_(response.getResponseText(), 254).toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) throw new Error('Indirizzo email di test non valido.');
  PropertiesService.getScriptProperties().setProperty(MI_TEST_EMAIL_PROPERTY, recipient);
  ui.alert('Destinatario di test configurato nelle proprietà private dello script.');
}

function inviaCodaEmailDiTest() {
  if (String(ottieniConfigurazione_('modalita_email', 'ANTEPRIMA')).toUpperCase() !== 'TEST') {
    throw new Error('Imposta modalita_email su TEST nel foglio Configurazione prima di spedire.');
  }
  const recipient = String(PropertiesService.getScriptProperties().getProperty(MI_TEST_EMAIL_PROPERTY) || '').trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) throw new Error('Configura prima il destinatario email di test dal menu Modulo iscrizioni.');

  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX);
  const index = creaIndiceIntestazioni_(sheet);
  const rows = convertiRigheInOggetti_(sheet).filter(function (row) { return String(row.stato).toUpperCase() === 'PREVIEW'; });
  let sent = 0;
  rows.forEach(function (row) {
    const payload = JSON.parse(String(row.contenuto_json || '{}'));
    const orderCode = normalizzaTesto_(row.codice_ordine, 64);
    MailApp.sendEmail({
      to: recipient,
      subject: '[TEST] Iscrizione ' + orderCode,
      body: 'Questa è una prova protetta.\n\nCodice ordine: ' + orderCode + '\nStato: ' + normalizzaTesto_(payload.status, 30) + '\nModello: ' + normalizzaTesto_(row.tipo_modello, 80) + '\n\nNessun messaggio è stato inviato al destinatario originale.',
      name: 'Modulo iscrizioni — TEST'
    });
    sheet.getRange(row._row, index.stato + 1).setValue('TEST_INVIATA');
    aggiungiControllo_('SEND_TEST_EMAIL', 'EMAIL', row.id_messaggio, 'SUCCESS', Session.getActiveUser().getEmail(), 'TEST_RECIPIENT_ONLY', 'WORKSPACE_UI');
    sent += 1;
  });
  SpreadsheetApp.flush();
  SpreadsheetApp.getUi().alert(sent ? 'Email di test inviate: ' + sent + '. Tutte esclusivamente al destinatario privato configurato.' : 'Nessuna email PREVIEW da inviare.');
}
