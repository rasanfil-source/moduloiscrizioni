const MI_TEST_EMAIL_PROPERTY = 'MI_EMAIL_TEST_RECIPIENT';

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
  aggiungiControllo_('SEND_TEST_EMAIL', 'EMAIL', 'PROVA_WORDPRESS', 'SUCCESS', 'WORDPRESS', 'SIGNED_TEST_RECIPIENT', 'WORDPRESS_PROXY');
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
