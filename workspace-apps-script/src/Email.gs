const MI_TEST_EMAIL_PROPERTY = 'MI_EMAIL_TEST_RECIPIENT';

/** Archivio tecnico separato dai fogli evento: conserva solo chiavi e stati, senza destinatari o contenuti. */
function archivioRicevuteEmail_(props, context, create) {
  if (context.ledger) return context.ledger;
  let id=String(props.getProperty('MI_EMAIL_LEDGER_SPREADSHEET_ID')||'');
  if (!id && !create) return null;
  let book;
  if (!id) {
    book=SpreadsheetApp.create('Modulo Iscrizioni — Registro tecnico consegne');
    const sheet=book.getSheets()[0];sheet.setName('Consegne');sheet.getRange(1,1,1,2).setValues([['Chiave tecnica','Stato consegna']]);
    SpreadsheetApp.flush();id=book.getId();props.setProperty('MI_EMAIL_LEDGER_SPREADSHEET_ID',id);
  } else book=SpreadsheetApp.openById(id);
  context.ledger=book.getSheetByName('Consegne');
  if (!context.ledger) throw new Error('EMAIL_LEDGER_UNAVAILABLE');
  return context.ledger;
}

function cercaRicevutaEmail_(props, context, key) {
  const current=String(props.getProperty(key)||'');
  if (current) return current;
  const sheet=archivioRicevuteEmail_(props,context,false);
  if (!sheet || sheet.getLastRow()<2) return '';
  const found=sheet.getRange(2,1,sheet.getLastRow()-1,1).createTextFinder(key).matchEntireCell(true).findAll();
  return found.length ? String(sheet.getRange(found[found.length-1].getRow(),2).getValue()||'') : '';
}

function assicuraSpazioRicevuteEmail_(props, context) {
  const all=props.getProperties();
  // Stima prudenziale UTF-16: lascia spazio alle altre proprieta dello stesso progetto.
  const bytes=Object.keys(all).reduce((n,k)=>n+2*(k.length+String(all[k]).length),0);
  if (bytes<300000) return;
  const keys=Object.keys(all).filter(k=>/^MI_EMAIL_DELIVERY_[a-f0-9]{64}$/.test(k)&&/^(ACCEPTED|SENDING)\|/.test(String(all[k]))).sort();
  if (!keys.length) throw new Error('EMAIL_LEDGER_CAPACITY');
  const sheet=archivioRicevuteEmail_(props,context,true), rows=keys.map(k=>[k,String(all[k])]);
  sheet.getRange(sheet.getLastRow()+1,1,rows.length,2).setValues(rows);
  SpreadsheetApp.flush();
  // Prima rendi durevole la copia; un'interruzione puo duplicare righe tecniche, mai una consegna.
  keys.forEach(k=>props.deleteProperty(k));
}

function statoCanaleEmail_() {
  const sender = String(Session.getEffectiveUser().getEmail() || '').trim().toLowerCase();
  const expected = String(PropertiesService.getScriptProperties().getProperty('MI_EMAIL_SENDER') || '').trim().toLowerCase();
  const authorized = expected && sender === expected;
  return { ok: !!authorized, error: authorized ? '' : 'EMAIL_SENDER_NOT_AUTHORIZED', channel: 'GOOGLE_WORKSPACE', sender: sender };
}

/** Limita il controllo delle ripetizioni; MySQL resta la coda autorevole. */
function pulisciRicevuteEmail_ (props) {
  const now=Date.now(), last=Number(props.getProperty('MI_EMAIL_LEDGER_CLEANED_AT')||0);
  if (now-last<86400000) return;
  const all=props.getProperties();
  Object.keys(all).forEach(key=>{
    if (!/^MI_EMAIL_DELIVERY_[a-f0-9]{64}$/.test(key)) return;
    const value=String(all[key]||''), parts=value.split('|');
    // Lo stato SENDING non viene eliminato in automatico: l'esito della consegna è incerto.
    if (parts[0]==='ACCEPTED'&&Number(parts[1])>0&&now-Number(parts[1])>90*86400000) props.deleteProperty(key);
  });
  props.setProperty('MI_EMAIL_LEDGER_CLEANED_AT',String(now));
}

/** Coda WordPress firmata. Registra l'intento prima dell'invio per consentire la verifica degli esiti incerti. */
function inviaEmailConfermaDaWordPress_(payload, context) {
  context=context||{};
  context.transportStarted=false;
  try { return eseguiConsegnaEmail_(payload,context); }
  catch (error) { console.error('EMAIL_PREPARE_FAILED',String(error));return {ok:false,error:context.transportStarted?'EMAIL_DELIVERY_UNCERTAIN':'EMAIL_STORAGE_UNAVAILABLE'}; }
}

function eseguiConsegnaEmail_(payload, context) {
  const p = payload || {};
  const sender = context.sender || (context.sender=statoCanaleEmail_());
  if (!sender.ok) return sender;
  const recipient = String(p.destinatario || '').trim().toLowerCase();
  if (recipient.length>254 || !/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(recipient) || !/^[a-f0-9]{64}$/.test(String(p.delivery_key || '')) || !['PROVA', 'PROVA_PUNTUALE', 'OPERATIVO'].includes(p.mode)) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  if (p.mode === 'PROVA_PUNTUALE' && p.test_only !== true) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  const props = PropertiesService.getScriptProperties();
  if (p.mode === 'PROVA' && recipient !== String(props.getProperty(MI_TEST_EMAIL_PROPERTY) || '').trim().toLowerCase()) return { ok: false, error: 'TEST_RECIPIENT_MISMATCH' };
  const replyTo = String(p.reply_to || sender.sender).trim().toLowerCase();
  if (p.mode !== 'PROVA' && (replyTo.length > 254 || !/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(replyTo))) return { ok: false, error: 'INVALID_REPLY_TO' };
  if (!p.oggetto || !p.testo || !p.html || String(p.oggetto).length > 250 || String(p.testo).length > 100000 || String(p.html).length > 300000 || /[\r\n]/.test(String(p.oggetto))) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  const lock = LockService.getScriptLock();
  const senderName = String(p.nome_mittente || '').trim() || 'Parrocchia Sant’Eugenio';
  if (senderName.length > 120 || /[\r\n\x00]/.test(senderName)) return { ok: false, error: 'INVALID_SENDER_NAME' };
  const requestedSender = String(p.indirizzo_mittente || sender.sender).trim().toLowerCase();
  if (requestedSender.length > 254 || !/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/.test(requestedSender)) return { ok: false, error: 'INVALID_SENDER_EMAIL' };
  if (!lock.tryLock(1000)) return { ok: false, error: 'EMAIL_BUSY' };
  try {
    const key='MI_EMAIL_DELIVERY_'+p.delivery_key;
    let previous;
    try {
      pulisciRicevuteEmail_(props);
      assicuraSpazioRicevuteEmail_(props,context);
      previous=cercaRicevutaEmail_(props,context,key);
    } catch (error) { return {ok:false,error:'EMAIL_STORAGE_UNAVAILABLE'}; }
    if (previous) return previous.startsWith('ACCEPTED|') ? { ok: true, channel: 'GOOGLE_WORKSPACE', replayed: true } : { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' };
    let alias = '';
    if (requestedSender !== sender.sender) {
      try { const aliases=context.aliases||(context.aliases=GmailApp.getAliases());alias = aliases.find(address => String(address).toLowerCase() === requestedSender) || ''; }
      catch (error) { return { ok: false, error: 'EMAIL_ALIAS_CHECK_FAILED' }; }
      if (!alias) return { ok: false, error: 'EMAIL_ALIAS_NOT_AUTHORIZED' };
    }
    if (MailApp.getRemainingDailyQuota() < 1) return { ok: false, error: 'EMAIL_QUOTA_EXCEEDED' };
    const options = { to: recipient, subject: String(p.oggetto), body: String(p.testo), htmlBody: String(p.html), name: senderName, replyTo: ['PROVA','PROVA_PUNTUALE'].includes(p.mode) ? recipient : replyTo };
    if (p.codice_svg) options.inlineImages = { 'mi-registration-code': Utilities.newBlob(String(p.codice_svg), 'image/svg+xml', 'codice-iscrizione.svg') };
    // La quota viene controllata prima di registrare SENDING. Dopo sendEmail,
    // un'eccezione non prova che Google non abbia accettato il messaggio:
    // conserviamo l'intento per evitare duplicati, senza dedurlo dal testo
    // dell'errore (localizzato e non un codice di consegna affidabile).
    try { props.setProperty(key,'SENDING|'+Date.now()); }
    catch (error) { return {ok:false,error:'EMAIL_STORAGE_UNAVAILABLE'}; }
    context.transportStarted=true;
    try {
      if (alias) {
        options.from = alias;
        GmailApp.sendEmail(recipient, options.subject, options.body, options);
      } else { MailApp.sendEmail(options); }
    }
    catch (error) { console.error('EMAIL_SEND_FAILED', String(error)); return { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' }; }
    try { props.setProperty(key,'ACCEPTED|'+Date.now()); }
    catch (error) { return {ok:false,error:'EMAIL_DELIVERY_UNCERTAIN'}; }
    return { ok: true, channel: 'GOOGLE_WORKSPACE', sender: requestedSender };
  } finally { lock.releaseLock(); }
}

/** Eseguire dall'editor con l'account della distribuzione per autorizzare Gmail. Non invia email. */
function verificaMittentiEmailAutorizzati() {
  const state = statoCanaleEmail_();
  if (!state.ok) throw new Error(state.error);
  const aliases = GmailApp.getAliases();
  console.log('Mittenti aggiuntivi autorizzati: ' + aliases.join(', '));
  return { sender: state.sender, aliases: aliases };
}

/** Invia soltanto la prova del Modulo Iscrizioni richiesta da WordPress. */
function inviaEmailProvaDaWordPress_(payload) {
  payload = payload && typeof payload === 'object' ? payload : {};
  const sender = statoCanaleEmail_();
  if (!sender.ok) return sender;
  const destinatarioConfigurato = String(PropertiesService.getScriptProperties().getProperty(MI_TEST_EMAIL_PROPERTY) || '').trim().toLowerCase();
  const destinatario = normalizzaTesto_(payload.destinatario, 254).trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(destinatarioConfigurato)) return { ok: false, error: 'TEST_RECIPIENT_NOT_CONFIGURED' };
  if (destinatario !== destinatarioConfigurato) return { ok: false, error: 'TEST_RECIPIENT_MISMATCH' };
  const oggetto = normalizzaTesto_(payload.oggetto, 180);
  const testo = String(payload.testo || '').slice(0, 12000);
  const html = String(payload.html || '').slice(0, 60000);
  if (!oggetto || !testo || !html) return { ok: false, error: 'INVALID_EMAIL_PAYLOAD' };
  if (MailApp.getRemainingDailyQuota() < 1) return { ok: false, error: 'EMAIL_QUOTA_EXCEEDED' };
  return inviaEmailConfermaDaWordPress_({destinatario:destinatarioConfigurato,oggetto:oggetto,testo:testo,html:html,
    delivery_key:payload.delivery_key,indirizzo_mittente:payload.indirizzo_mittente||sender.sender,mode:'PROVA',nome_mittente:'Modulo Iscrizioni',reply_to:destinatarioConfigurato});
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

  const startedAt = Date.now();
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) throw new Error('Invio occupato. Riprova tra poco.');
  let sent = 0;
  let remaining = 0;
  let uncertain = 0;
  try {
    const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.EMAIL_OUTBOX);
    const index = creaIndiceIntestazioni_(sheet);
    const allRows = convertiRigheInOggetti_(sheet);
    const rows = allRows.filter(function (row) { return String(row.stato).toUpperCase() === 'PREVIEW'; });
    if (!Number.isInteger(index.stato)) throw new Error('Colonna stato mancante nella coda email.');
    uncertain = allRows.filter(row => String(row.stato).toUpperCase() === 'TEST_IN_CORSO').length;
    const actor = Session.getActiveUser().getEmail();
    remaining = rows.length;
    // Esecuzioni brevi e riprendibili. Registra ogni intento prima di contattare il servizio email:
    // un timeout o un esito incerto non avvia mai un nuovo invio automatico.
    for (const row of rows) {
      if (sent >= 25 || Date.now() - startedAt >= 240000 || MailApp.getRemainingDailyQuota() < 1) break;
      const payload = JSON.parse(String(row.contenuto_json || '{}'));
      const orderCode = normalizzaTesto_(row.codice_ordine, 64);
      sheet.getRange(row._row, index.stato + 1).setValue('TEST_IN_CORSO');
      SpreadsheetApp.flush();
      try {
        MailApp.sendEmail({
          to: recipient,
          subject: '[TEST] Iscrizione ' + orderCode,
          body: 'Questa è una prova protetta.\n\nCodice ordine: ' + orderCode + '\nStato: ' + normalizzaTesto_(payload.status, 30) + '\nModello: ' + normalizzaTesto_(row.tipo_modello, 80) + '\n\nNessun messaggio è stato inviato al destinatario originale.',
          name: 'Modulo iscrizioni — TEST'
        });
        sheet.getRange(row._row, index.stato + 1).setValue('TEST_INVIATA');
        SpreadsheetApp.flush();
      } catch (error) {
        throw new Error('Invio di test interrotto: verifica la riga ' + row._row + ' prima di riprovare. Le righe TEST_IN_CORSO non vengono reinviate automaticamente.');
      }
      aggiungiControllo_('SEND_TEST_EMAIL', 'EMAIL', row.id_messaggio, 'SUCCESS', actor, 'TEST_RECIPIENT_ONLY', 'WORKSPACE_UI');
      sent += 1;
      remaining -= 1;
    }
    SpreadsheetApp.flush();
  } finally { lock.releaseLock(); }
  // Gli avvisi dell'interfaccia sospendono l'esecuzione: mostrarli solo dopo aver rilasciato il lock.
  SpreadsheetApp.getUi().alert('Email di test inviate: ' + sent + '. Tutte esclusivamente al destinatario privato configurato.' +
    (remaining ? ' Restano ' + remaining + ' email PREVIEW: ripeti il comando quando la quota è disponibile.' : ' Nessuna email PREVIEW rimanente.') +
    (uncertain ? ' Sono presenti ' + uncertain + ' righe TEST_IN_CORSO: verifica la consegna prima di modificarne lo stato.' : ''));
}


/** Dieci consegne distinte per busta; il registro durevole protegge la ripetizione dell'intero lotto. */
function inviaEmailLottoDaWordPress_(payload) {
  const messages = payload && payload.messages;
  if (!Array.isArray(messages) || messages.length < 1 || messages.length > 10 ||
      messages.some(p => !p || !/^[a-f0-9]{64}$/.test(String(p.delivery_key || ''))) ||
      new Set(messages.map(p => p.delivery_key)).size !== messages.length) return {ok:false,error:'INVALID_EMAIL_PAYLOAD'};
  const results = {}, started = Date.now(), context={};
  let stop = '';
  for (const message of messages) {
    const key = message.delivery_key;
    if (stop || Date.now() - started >= 20000) { results[key] = {ok:false,error:stop || 'EMAIL_DEFERRED'}; continue; }
    try { results[key] = inviaEmailConfermaDaWordPress_(message,context); }
    catch (error) { console.error('EMAIL_BATCH_ITEM_FAILED', String(error)); results[key] = {ok:false,error:'EMAIL_DELIVERY_UNCERTAIN'}; }
    if (['EMAIL_QUOTA_EXCEEDED','EMAIL_BUSY','WORKSPACE_BUSY','EMAIL_STORAGE_UNAVAILABLE'].includes(results[key].error)) stop = results[key].error;
  }
  return {ok:true,channel:'GOOGLE_WORKSPACE',results:results,quota_remaining:MailApp.getRemainingDailyQuota()};
}
