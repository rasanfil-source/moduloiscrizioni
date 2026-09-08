// Sorgente: Assegnazioni.gs
/** Carica una vista collettiva per camere e pullman di un evento. */
function caricaAssegnazioniEvento(form) {
  form = form || {};
  const eventId = normalizzaTesto_(form.id_evento, 40);
  if (!eventId) throw new Error('Scegli un evento.');
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) {
    return String(row.id_evento) === eventId && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0;
  });
  const allowed = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = true; return result; }, {});
  const operational = indiceStatoOperativo_();
  const participants = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) {
    return allowed[String(row.codice_ordine)] && String(row.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED';
  }).map(function (row) {
    const orderCode = String(row.codice_ordine); const number = Number(row.numero_partecipante) || 0;
    const fields = datiOperativiPartecipante_(row, operational[orderCode + '|' + number] || {});
    return { codice_ordine: orderCode, numero_partecipante: number, nome: String(row.nome || ''), cognome: String(row.cognome || ''), camera: String(fields.room || fields.camera || fields.alloggio || ''), pullman: String(fields.pullman || fields.transport || '') };
  }).sort(function (left, right) { return (left.cognome + ' ' + left.nome).localeCompare(right.cognome + ' ' + right.nome, 'it', { sensitivity: 'base' }); });
  return { partecipanti: participants, sistemazioni: elencaSistemazioniDisponibili_(eventId) };
}

/** Applica un gruppo di assegnazioni in un unico lock e registra ogni modifica. */
function salvaAssegnazioniEvento(form) {
  form = form || {};
  const eventId = normalizzaTesto_(form.id_evento, 40);
  const changes = Array.isArray(form.modifiche) ? form.modifiche.slice(0, 500) : [];
  if (!eventId || !changes.length) throw new Error('Scegli un evento e almeno una modifica.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  const reason = normalizzaTesto_(form.motivo, 500);
  if (reason.length < 3) throw new Error('Indica il motivo delle assegnazioni.');
  const lock = LockService.getDocumentLock(); lock.waitLock(30000);
  let applied = 0;
  try {
  const assignmentContext = caricaAssegnazioniEvento({ id_evento: eventId });
  const validParticipants = assignmentContext.partecipanti.reduce(function (result, row) { result[row.codice_ordine + '|' + row.numero_partecipante] = row; return result; }, {});
  const rooms = assignmentContext.sistemazioni.reduce(function (result, room) { result[String(room.code)] = room; return result; }, {});
  const plannedRooms = assignmentContext.partecipanti.reduce(function (result, row) { result[row.codice_ordine + '|' + row.numero_partecipante] = row.camera; return result; }, {});
  const normalizedChanges = changes.map(function (change) {
    const orderCode = normalizzaTesto_(change.codice_ordine, 64);
    const number = Math.max(0, Math.round(Number(change.numero_partecipante) || 0));
    const key = normalizzaValoreElenco_(change.campo, ['ROOM', 'PULLMAN']);
    const value = normalizzaTesto_(change.valore, 80);
    const participantKey = orderCode + '|' + number;
    if (!validParticipants[participantKey] || !key) throw new Error('Assegnazione non valida o fuori dall’evento selezionato.');
    if (key === 'ROOM' && value && !rooms[value]) throw new Error('La sistemazione “' + value + '” non è disponibile per questo evento.');
    if (key === 'ROOM') plannedRooms[participantKey] = value;
    return { orderCode: orderCode, number: number, participantKey: participantKey, key: key, value: value };
  });
  const finalRoomCounts = Object.keys(plannedRooms).reduce(function (result, participantKey) {
    const code = plannedRooms[participantKey];
    if (code) result[code] = (result[code] || 0) + 1;
    return result;
  }, {});
  Object.keys(finalRoomCounts).forEach(function (code) {
    if (!rooms[code] || finalRoomCounts[code] > rooms[code].capacity) throw new Error('La sistemazione “' + code + '” supererebbe la capienza disponibile.');
  });
    normalizedChanges.forEach(function (change) {
      const stateKey = change.key === 'ROOM' ? 'room' : 'pullman';
      registraOperazioneSegreteria_(change.orderCode, change.number, change.key === 'ROOM' ? 'CHANGE_ACCOMMODATION' : 'CHANGE_TRANSPORT', { key: stateKey, value: change.value }, reason, operator, 'Assegnazione collettiva aggiornata.');
      applied += 1;
    });
    aggiungiControllo_('BULK_ASSIGNMENTS', 'EVENT', eventId, 'SUCCESS', operator, String(applied), 'WORKSPACE_UI');
  } finally { lock.releaseLock(); }
  return { ok: true, applied: applied, message: 'Assegnazioni aggiornate: ' + applied + '.' };
}


// Sorgente: Config.gs
const MI_SCHEMA_VERSION = '1.8.0';
const MI_SHEETS = Object.freeze({
  CONFIG: 'Configurazione',
  GROUPS: 'Gruppi',
  EVENTS: 'Eventi',
  REGISTRATIONS: 'Iscrizioni',
  PARTICIPANTS: 'Partecipanti',
  PAYMENT_INTAKE: 'Inserimento pagamenti',
  PAYMENT_FORM: 'Registra movimento',
  REGISTRATION_FORM: 'Registra iscrizione',
  PAYMENTS: 'Pagamenti',
  EMAIL_OUTBOX: 'Coda email',
  SECRETARY_OPERATIONS: 'Operazioni segreteria',
  OPERATIONAL_STATE: 'Stato operativo',
  EVENT_WORKSPACES: 'Fogli iniziative',
  OPERATIONAL_VIEWS: 'Viste operative',
  OPERATIONAL_LIST: 'Elenco operativo',
  REPORT_TEMPLATES: 'Modelli report',
  ACCOMMODATIONS: 'Sistemazioni',
  AUDIT_LOG: 'Registro controlli'
});

const MI_HEADERS = Object.freeze({
  'Configurazione': ['chiave', 'valore', 'descrizione'],
  'Gruppi': ['id_gruppo', 'nome', 'slug', 'stato', 'logo_url', 'immagine_url', 'data_aggiornamento'],
  'Eventi': ['id_evento', 'id_gruppo', 'titolo', 'stato', 'capienza', 'apertura_iscrizioni', 'chiusura_iscrizioni', 'modalita_prezzo', 'data_aggiornamento'],
  'Iscrizioni': ['codice_ordine', 'id_evento', 'stato', 'nome_referente', 'cognome_referente', 'email_referente', 'telefono_referente', 'richieste_particolari', 'numero_partecipanti', 'totale_centesimi', 'chiave_idempotenza', 'data_creazione', 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json', 'id_revisione_evento', 'hash_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'versione_informativa_privacy', 'data_accettazione_privacy', 'biglietti_json', 'id_consenso_marketing', 'data_accettazione_marketing', 'opzioni_ordine_json'],
  'Partecipanti': ['codice_ordine', 'numero_partecipante', 'codice_tipologia', 'indice_tipologia', 'nome', 'cognome', 'dati_aggiuntivi_json', 'opzioni_json', 'stato_partecipante', 'data_annullamento'],
  'Inserimento pagamenti': ['id_inserimento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'nota_amministrativa', 'stato_convalida', 'messaggio_convalida', 'data_convalida'],
  'Pagamenti': ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione', 'nota_amministrativa'],
  'Coda email': ['id_messaggio', 'codice_ordine', 'destinatario', 'tipo_modello', 'contenuto_json', 'stato', 'data_creazione'],
  'Operazioni segreteria': ['id_operazione', 'data_richiesta', 'codice_ordine', 'numero_partecipante', 'tipo_operazione', 'dati_json', 'motivo', 'etichetta_operatore', 'stato', 'messaggio', 'data_esito'],
  'Stato operativo': ['codice_ordine', 'numero_partecipante', 'chiave', 'valore', 'data_aggiornamento', 'etichetta_operatore', 'id_ultima_operazione'],
  'Fogli iniziative': ['id_evento', 'titolo', 'id_foglio', 'url_foglio', 'url_iscrizione', 'url_saldo', 'data_creazione'],
  'Viste operative': ['id_evento', 'campi_json', 'data_aggiornamento', 'etichetta_operatore'],
  'Elenco operativo': ['evento', 'codice_ordine', 'numero_partecipante', 'nome', 'cognome', 'stato'],
  'Modelli report': ['id_modello', 'nome', 'tipo', 'id_evento', 'colonne_json', 'filtri_json', 'raggruppamenti_json', 'ordinamento_json', 'predefinito', 'data_aggiornamento', 'etichetta_operatore'],
  'Sistemazioni': ['id_evento', 'codice', 'nome', 'capienza', 'attiva', 'note'],
  'Registro controlli': ['id_controllo', 'data_evento', 'canale', 'azione', 'tipo_entita', 'riferimento_entita', 'esito', 'etichetta_attore', 'codice_dettaglio']
});

const MI_LEGACY_SHEET_NAMES = Object.freeze({
  Config: MI_SHEETS.CONFIG,
  Events: MI_SHEETS.EVENTS,
  Registrations: MI_SHEETS.REGISTRATIONS,
  Participants: MI_SHEETS.PARTICIPANTS,
  PaymentIntake: MI_SHEETS.PAYMENT_INTAKE,
  Payments: MI_SHEETS.PAYMENTS,
  EmailOutbox: MI_SHEETS.EMAIL_OUTBOX,
  AuditLog: MI_SHEETS.AUDIT_LOG
});

const MI_INTESTAZIONI_PRECEDENTI = Object.freeze({
  'Eventi': ['id_evento', 'id_attivita', 'titolo', 'stato', 'capienza', 'apertura_iscrizioni', 'chiusura_iscrizioni', 'modalita_prezzo', 'data_aggiornamento'],
  'Iscrizioni': ['codice_ordine', 'id_evento', 'stato', 'nome_referente', 'cognome_referente', 'email_referente', 'telefono_referente', 'numero_partecipanti', 'totale_centesimi', 'chiave_idempotenza', 'data_creazione', 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json'],
  'Partecipanti': ['codice_ordine', 'numero_partecipante', 'nome', 'cognome', 'dati_aggiuntivi_json'],
  'Pagamenti': ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione']
});

const MI_LEGACY_HEADERS = Object.freeze({
  'Configurazione': ['key', 'value', 'description'],
  'Eventi': ['event_id', 'activity_id', 'title', 'status', 'capacity', 'opens_at', 'closes_at', 'pricing_mode', 'updated_at'],
  'Iscrizioni': ['order_code', 'event_id', 'status', 'buyer_first_name', 'buyer_last_name', 'buyer_email', 'buyer_phone', 'total_qty', 'total_cents', 'idempotency_key', 'created_at'],
  'Partecipanti': ['order_code', 'participant_index', 'first_name', 'last_name', 'fields_json'],
  'Inserimento pagamenti': ['intake_id', 'order_code', 'transaction_kind', 'installment_kind', 'effective_at', 'amount', 'payment_source', 'external_reference', 'operator_label', 'administrative_note', 'validation_status', 'validation_message', 'validated_at'],
  'Pagamenti': ['payment_id', 'order_code', 'transaction_kind', 'installment_kind', 'effective_at', 'amount_cents', 'currency', 'payment_source', 'external_reference', 'operator_label', 'recording_channel', 'source_intake_id', 'created_at'],
  'Coda email': ['message_id', 'order_code', 'recipient', 'template_type', 'payload_json', 'status', 'created_at'],
  'Registro controlli': ['audit_id', 'occurred_at', 'channel', 'action', 'entity_type', 'entity_ref', 'outcome', 'actor_label', 'detail_code']
});

const MI_PAYMENT_ENUMS = Object.freeze({
  transactionKinds: ['INCASSO', 'RIMBORSO', 'STORNO'],
  installmentKinds: ['INTERO', 'CAPARRA', 'INTERMEDIO', 'SALDO', 'NON_ASSEGNATO'],
  paymentSources: ['BONIFICO', 'CARTA', 'CONTANTE']
});

function ottieniFoglioDiLavoroAssociato_() {
  const properties = typeof PropertiesService !== 'undefined' ? PropertiesService.getScriptProperties() : null;
  const configuredId = properties ? String(properties.getProperty('MI_SPREADSHEET_ID') || '').trim() : '';
  if (configuredId) return SpreadsheetApp.openById(configuredId);
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) throw new Error('Foglio operativo non configurato. Esegui Inizializza/aggiorna struttura dal Google Sheet.');
  if (properties) properties.setProperty('MI_SPREADSHEET_ID', spreadsheet.getId());
  return spreadsheet;
}

function ottieniSchedaObbligatoria_(name) {
  const sheet = ottieniFoglioDiLavoroAssociato_().getSheetByName(name);
  if (!sheet) throw new Error('Foglio mancante: ' + name + '. Esegui configuraCartellaDiLavoro().');
  return sheet;
}

function ottieniSegretoScript_() {
  const secret = PropertiesService.getScriptProperties().getProperty('MI_SHARED_SECRET');
  if (!secret || secret.length < 32) throw new Error('MI_SHARED_SECRET non configurato o troppo corto.');
  return secret;
}

function ottieniConfigurazione_(key, fallback) {
  const rows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.CONFIG));
  const row = rows.find(function (item) { return String(item.chiave || item.key) === key; });
  return row ? String(row.valore == null ? row.value : row.valore).trim() : fallback;
}


// Sorgente: Core.gs
function normalizzaValoreElenco_(value, allowed) {
  const normalized = String(value || '').trim().toUpperCase();
  return allowed.indexOf(normalized) >= 0 ? normalized : '';
}

function normalizzaTesto_(value, maxLength) {
  let text = String(value == null ? '' : value).replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
  if (text.length > maxLength) text = text.slice(0, maxLength);
  return text;
}

function neutralizzaFormula_(value, maxLength) {
  const text = normalizzaTesto_(value, maxLength);
  return /^[=+\-@]/.test(text) ? "'" + text : text;
}

function convertiEuroInCentesimi_(value) {
  if (typeof value === 'string') value = value.replace(',', '.').trim();
  const amount = Number(value);
  if (!Number.isFinite(amount) || amount <= 0 || amount > 1000000) return null;
  return Math.round(amount * 100);
}

function contienePossibileNumeroCarta_(value) {
  const groups = String(value || '').match(/(?:\d[ -]?){13,19}/g) || [];
  return groups.some(function (group) {
    const digits = group.replace(/\D/g, '');
    if (digits.length < 13 || digits.length > 19) return false;
    let sum = 0;
    let alternate = false;
    for (let index = digits.length - 1; index >= 0; index -= 1) {
      let digit = Number(digits.charAt(index));
      if (alternate) {
        digit *= 2;
        if (digit > 9) digit -= 9;
      }
      sum += digit;
      alternate = !alternate;
    }
    return sum % 10 === 0;
  });
}

function serializzaInModoStabile_(value) {
  if (value === null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) return '[' + value.map(serializzaInModoStabile_).join(',') + ']';
  return '{' + Object.keys(value).sort().map(function (key) {
    return JSON.stringify(key) + ':' + serializzaInModoStabile_(value[key]);
  }).join(',') + '}';
}

function creaIdentificativoOpaco_(prefix) {
  return prefix + '_' + Utilities.getUuid().replace(/-/g, '').slice(0, 24);
}

function creaRispostaJson_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload)).setMimeType(ContentService.MimeType.JSON);
}

function aggiungiControllo_(action, entityType, entityRef, outcome, actorLabel, detailCode, channel) {
  ottieniSchedaObbligatoria_(MI_SHEETS.AUDIT_LOG).appendRow([
    creaIdentificativoOpaco_('aud'),
    new Date(),
    normalizzaTesto_(channel || 'WORKSPACE', 30),
    normalizzaTesto_(action, 60),
    normalizzaTesto_(entityType, 40),
    neutralizzaFormula_(entityRef, 100),
    normalizzaValoreElenco_(outcome, ['SUCCESS', 'REJECTED', 'ERROR']) || 'ERROR',
    neutralizzaFormula_(actorLabel || 'UNVERIFIED', 100),
    normalizzaTesto_(detailCode || '', 100)
  ]);
}

function creaIndiceIntestazioni_(sheet) {
  const lastColumn = sheet.getLastColumn();
  if (lastColumn < 1) return {};
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  return headers.reduce(function (index, header, position) {
    if (header) index[header] = position;
    return index;
  }, {});
}

function convertiRigheInOggetti_(sheet) {
  if (sheet.getLastRow() < 2) return [];
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, headers.length).getValues().map(function (row, offset) {
    const object = { _row: offset + 2 };
    headers.forEach(function (header, index) { object[header] = row[index]; });
    return object;
  });
}


// Sorgente: Email.gs
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


// Sorgente: FinestraPagamenti.gs
/** Finestra riservata agli operatori del foglio centrale, senza endpoint pubblico. */
function contenutoFinestraPagamenti_() {
  return HtmlService.createHtmlOutputFromFile('FinestraPagamenti').getContent();
}

function apriFinestraPagamenti() {
  SpreadsheetApp.getUi().showModalDialog(HtmlService.createHtmlOutput(contenutoFinestraPagamenti_()).setWidth(920).setHeight(720), 'Inserisci pagamento');
}

function caricaPrenotazioniFinestraPagamenti() {
  const eventi = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS));
  const titoli = {};
  eventi.forEach(function (e) { titoli[String(e.id_evento)] = String(e.titolo || ''); });
  return {
    operatore: Session.getActiveUser().getEmail(),
    data: Utilities.formatDate(new Date(), ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone(), 'yyyy-MM-dd'),
    prenotazioni: convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).map(function (p) {
      return { codice: String(p.codice_ordine), nome: [p.nome_referente, p.cognome_referente].filter(String).join(' '), evento: titoli[String(p.id_evento)] || String(p.id_evento) };
    }).sort(function (a, b) { return a.nome.localeCompare(b.nome, 'it'); })
  };
}

function caricaSaldoFinestraPagamenti(codice) {
  const r = riepilogoMovimentoGuidato_(normalizzaTesto_(codice, 64));
  return { totale: r.total, versato: r.paid, residuo: r.balance, nome: r.referent, evento: r.eventTitle, stato: String(r.registration.stato || ''), rata: String(r.registration.modalita_economica) === 'DEPOSIT_BALANCE' ? (r.paid ? 'SALDO' : 'CAPARRA') : 'INTERO' };
}

function salvaFinestraPagamenti(form) {
  if (!form || !/^dlg_[a-zA-Z0-9-]{15,55}$/.test(String(form.request_id))) throw new Error('Identificativo del movimento non valido. Riapri la finestra.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(form.data))) throw new Error('Indica una data valida.');
  const data = Utilities.parseDate(form.data, ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone(), 'yyyy-MM-dd');
  const risultato = registraPagamentoValidato_({
    intake_id: form.request_id, order_code: form.codice, transaction_kind: form.tipo,
    installment_kind: form.rata, effective_at: data, amount: form.importo,
    payment_source: form.metodo, external_reference: form.riferimento,
    operator_label: form.operatore, administrative_note: form.nota, recording_channel: 'WORKSPACE_UI'
  });
  if (risultato.status !== 'CONVALIDATO') return { ok: false, message: risultato.message };
  let message = risultato.message;
  try {
    const r = riepilogoMovimentoGuidato_(form.codice);
    const link = trovaCollegamentoFoglioOperativo_(String(r.registration.id_evento));
    aggiornaProiezionePagamentiPrenotazioneEvento_(SpreadsheetApp.openById(String(link.id_foglio)), String(r.registration.id_evento), form.codice);
  } catch (error) { message += ' Il movimento è salvato; lo storico del foglio evento richiede un aggiornamento.'; }
  return { ok: true, message: message };
}


// Sorgente: FogliOperativi.gs
/** Prepara il registro dell'evento e il relativo foglio operativo su richiesta firmata di WordPress. */
function preparaProduzioniEventoDaWordPress_(payload) {
  payload = payload || {};
  const idEvento = normalizzaTesto_(payload.id_evento, 40);
  const titolo = normalizzaTesto_(payload.titolo, 200);
  if (!/^\d+$/.test(idEvento) || !titolo) return { ok: false, error: 'EVENTO_NON_VALIDO' };
  const eventi = ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const esistente = convertiRigheInOggetti_(eventi).find(function (riga) { return String(riga.id_evento) === idEvento; });
  const valori = [
    idEvento,
    normalizzaTesto_(payload.id_gruppo, 40),
    neutralizzaFormula_(titolo, 200),
    normalizzaValoreElenco_(payload.stato, ['BOZZA', 'PUBBLICATO', 'PRIVATO']) || 'BOZZA',
    Math.max(1, Math.round(Number(payload.capienza) || 1)),
    normalizzaTesto_(payload.apertura_iscrizioni, 40),
    normalizzaTesto_(payload.chiusura_iscrizioni, 40),
    payload.evento_gratuito === true ? 'ZERO' : normalizzaTesto_(payload.modalita_prezzo, 40),
    new Date()
  ];
  if (esistente) eventi.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
  else eventi.appendRow(valori);
	const profiloOperativo = normalizzaValoreElenco_(payload.profilo_operativo, ['AUTOMATICO', 'MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO']) || 'AUTOMATICO';
  const risultato = apriFoglioOperativoEvento({ id_evento: idEvento, titolo: titolo, profilo_operativo: profiloOperativo });
	const urlIscrizione = normalizzaUrlPubblico_(payload.url_iscrizione);
	const urlSaldo = normalizzaUrlPubblico_(payload.url_saldo);
	const emailGestore = payload.email_gestore ? normalizzaEmailGestore_(payload.email_gestore) : '';
	const condivisione = { ok: false, email: emailGestore, avviso: emailGestore ? 'Il foglio è privato. La condivisione al gestore va completata separatamente secondo le regole del dominio Workspace.' : 'Nessun gestore indicato: nessuna nuova condivisione. Restano invariati gli accessi Google già autorizzati.' };
	aggiornaCollegamentiProduzioneEvento_(idEvento, urlIscrizione, urlSaldo);
  aggiungiControllo_('PRODUZIONI_EVENTO', 'PREPARE', idEvento, 'SUCCESS', 'WORDPRESS', risultato.creato ? 'SHEET_CREATED' : 'SHEET_REUSED', 'WORDPRESS_PROXY');
  return { ok: true, id_evento: idEvento, id_foglio: risultato.id_foglio, url_foglio: risultato.url_foglio, url_iscrizione: urlIscrizione, url_saldo: urlSaldo, cartella: risultato.cartella || '', creato: risultato.creato, condivisione: condivisione, mode: 'PREVIEW' };
}

/** Mantiene il proprietario Workspace e un solo gestore esplicitamente indicato da WordPress. */
function condividiFoglioSoltantoConGestore_(idFoglio, emailGestore) {
	const file = DriveApp.getFileById(String(idFoglio));
	try {
		file.addEditor(emailGestore);
		return { ok: true, email: emailGestore };
	} catch (errore) {
		aggiungiControllo_('PRODUZIONI_EVENTO', 'SHARE', String(idFoglio), 'WARNING', 'WORDPRESS', normalizzaTesto_(errore && errore.message ? errore.message : errore, 500), 'WORDPRESS_PROXY');
		return { ok: false, email: emailGestore, avviso: 'Il foglio è stato creato, ma Workspace non ha consentito la condivisione automatica con il gestore.' };
	}
}

function normalizzaEmailGestore_(valore) {
	const email = normalizzaTesto_(valore, 254).toLowerCase();
	if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new Error('Indirizzo email del gestore non valido.');
	return email;
}

/** Restituisce il foglio operativo dell'evento, creandolo soltanto se manca. */
function apriFoglioOperativoEvento(form) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try { return apriFoglioOperativoConLock_(form); }
  finally { lock.releaseLock(); }
}

function apriFoglioOperativoConLock_(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
  const esistente = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (esistente && esistente.id_foglio) {
		try {
			const fileEsistente = DriveApp.getFileById(String(esistente.id_foglio));
			if (!fileEsistente.isTrashed()) {
				SpreadsheetApp.openById(String(esistente.id_foglio));
				return { id_evento: idEvento, id_foglio: String(esistente.id_foglio), url_foglio: String(esistente.url_foglio || ('https://docs.google.com/spreadsheets/d/' + esistente.id_foglio + '/edit')), cartella: '', creato: false };
			}
		} catch (errore) {
			aggiungiControllo_('FOGLIO_OPERATIVO', 'VERIFY', idEvento, 'WARNING', 'WORDPRESS', 'SHEET_MISSING_RECREATE', 'WORDPRESS_PROXY');
		}
  }
	// Un evento appena creato non possiede ancora iscrizioni: evitiamo di rileggere
	// l'intero database e prepariamo subito la struttura scelta in WordPress.
	const vista = esistente ? generaVistaOperativaEvento_(idEvento) : generaVistaOperativaIniziale_(idEvento, normalizzaTesto_(form.titolo, 200), normalizzaTesto_(form.profilo_operativo, 30));
	const titoloPulito = String(vista.evento.titolo || idEvento).replace(/[\\/:*?"<>|#%{}]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
	const titolo = 'Evento ' + idEvento + ' - ' + titoloPulito;
  const foglio = SpreadsheetApp.create(titolo);
	const cartella = spostaFoglioAccantoAlDatabase_(foglio.getId());
  const scheda = foglio.getSheets()[0];
  scheda.setName('Dati operativi');
  aggiornaDatiIncrementaliEvento_(scheda, vista);
  configuraSchedeEconomicheEvento_(foglio, idEvento);
  const valori = [idEvento, neutralizzaFormula_(vista.evento.titolo, 200), foglio.getId(), foglio.getUrl(), '', '', new Date()];
	if (esistente) registro.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
	else registro.appendRow(valori);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'CREATE', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'CREATED', 'SEGRETERIA');
  return { id_evento: idEvento, id_foglio: foglio.getId(), url_foglio: foglio.getUrl(), cartella: cartella, creato: true };
}

function generaVistaOperativaIniziale_(idEvento, titolo, profiloRichiesto) {
	const profili = {
		MINIMO: ['last_name', 'first_name', 'phone'],
		QUOTA_UNICA: ['last_name', 'first_name', 'phone', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'],
		SERVIZI_MULTIPLI: ['last_name', 'first_name', 'phone', 'transport', 'lunch', 'options', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'],
		VIAGGIO_COMPLESSO: ['last_name', 'first_name', 'phone', 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality', 'transport', 'room', 'lunch', 'insurance', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance']
	};
	const profilo = profili[profiloRichiesto] ? profiloRichiesto : 'MINIMO';
	const catalogo = campiElencoOperativo_(false).reduce(function (indice, campo) { indice[campo.key] = campo; return indice; }, {});
	const colonne = profili[profilo].filter(function (chiave) { return !!catalogo[chiave]; }).map(function (chiave) {
		return { key: chiave, label: catalogo[chiave].label, gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 };
	});
	return { evento: { id: idEvento, titolo: titolo || idEvento }, profilo: profilo, nome_profilo: profilo, personalizzata: false, conservata: false, colonne: colonne, righe: [] };
}

/** Controlla che il documento registrato esista davvero e sia accessibile. */
function verificaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
	const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
	if (!collegamento || !collegamento.id_foglio) return { ok: true, esiste: false, id_evento: idEvento };
	try {
		const file = DriveApp.getFileById(String(collegamento.id_foglio));
		if (file.isTrashed()) return { ok: true, esiste: false, id_evento: idEvento };
		SpreadsheetApp.openById(String(collegamento.id_foglio));
		return { ok: true, esiste: true, id_evento: idEvento, id_foglio: String(collegamento.id_foglio), url_foglio: String(collegamento.url_foglio || file.getUrl()) };
	} catch (errore) {
		return { ok: true, esiste: false, id_evento: idEvento };
	}
}

/** Verifica in una sola richiesta i documenti di più eventi. */
function verificaFogliEventoDaWordPress_(payload) {
	const ids = (Array.isArray((payload || {}).id_eventi) ? payload.id_eventi : []).slice(0, 100).map(function (id) { return normalizzaTesto_(id, 40); }).filter(function (id) { return /^\d+$/.test(id); });
	const visti = {};
	const stati = [];
	ids.forEach(function (idEvento) {
		if (visti[idEvento]) return;
		visti[idEvento] = true;
		const stato = verificaFoglioEventoDaWordPress_({ id_evento: idEvento });
		stati.push({ id_evento: idEvento, esiste: !!stato.esiste, id_foglio: String(stato.id_foglio || ''), url_foglio: String(stato.url_foglio || '') });
	});
	return { ok: true, stati: stati };
}

/** Sposta in «EVENTI/EVENTI PASSATI» il foglio di un evento passato, senza cancellarlo. */
function archiviaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
	const file = DriveApp.getFileById(String(collegamento.id_foglio));
	if (file.isTrashed()) return { ok: false, error: 'FOGLIO_NON_DISPONIBILE' };
	const archivio = ottieniCartelleEventi_().passati;
	file.moveTo(archivio);
	aggiungiControllo_('FOGLIO_OPERATIVO', 'ARCHIVE', idEvento, 'SUCCESS', 'WORDPRESS', 'MOVED_TO_COMPLETED', 'WORDPRESS_PROXY');
	return { ok: true, id_evento: idEvento, id_foglio: String(collegamento.id_foglio), cartella: 'EVENTI/EVENTI PASSATI' };
}

/** Riallinea in blocco i fogli esistenti alla stessa distinzione mostrata nel portale. */
function organizzaFogliEventoDaWordPress_(payload) {
	payload = payload || {};
	const correnti = normalizzaIdentificativiEvento_(payload.eventi_correnti);
	const passati = normalizzaIdentificativiEvento_(payload.eventi_passati);
	const cartelle = ottieniCartelleEventi_();
	const risultati = [];
	const sposta = function (idEvento, cartella, destinazione) {
		try {
			const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
			const file = DriveApp.getFileById(String(collegamento.id_foglio));
			if (file.isTrashed()) throw new Error('FOGLIO_NON_DISPONIBILE');
			file.moveTo(cartella);
			risultati.push({ id_evento: idEvento, ok: true, cartella: destinazione });
		} catch (errore) {
			risultati.push({ id_evento: idEvento, ok: false, errore: normalizzaTesto_(errore && errore.message ? errore.message : errore, 200) });
		}
	};
	correnti.forEach(function (idEvento) { sposta(idEvento, cartelle.eventi, 'EVENTI'); });
	passati.forEach(function (idEvento) { sposta(idEvento, cartelle.passati, 'EVENTI/EVENTI PASSATI'); });
	return { ok: true, risultati: risultati };
}

function normalizzaIdentificativiEvento_(valori) {
	const visti = {};
	return (Array.isArray(valori) ? valori : []).slice(0, 200).map(function (valore) { return normalizzaTesto_(valore, 40); }).filter(function (valore) {
		if (!/^\d+$/.test(valore) || visti[valore]) return false;
		visti[valore] = true;
		return true;
	});
}

/** Cestina il foglio collegato e rimuove l'associazione di una bozza eliminata. */
function eliminaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
	const collegamento = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
	if (!collegamento) return { ok: true, id_evento: idEvento, eliminato: false };
	if (collegamento.id_foglio) {
		try { DriveApp.getFileById(String(collegamento.id_foglio)).setTrashed(true); } catch (errore) {}
	}
	registro.deleteRow(collegamento._row);
	aggiungiControllo_('FOGLIO_OPERATIVO', 'DELETE', idEvento, 'SUCCESS', 'WORDPRESS', 'MOVED_TO_TRASH', 'WORDPRESS_PROXY');
	return { ok: true, id_evento: idEvento, eliminato: true };
}

/** Crea o riusa la struttura EVENTI nella radice di Google Drive. */
function ottieniCartelleEventi_() {
	const principale = DriveApp.getRootFolder();
	const esistenti = principale.getFoldersByName('EVENTI');
	const eventi = esistenti.hasNext() ? esistenti.next() : principale.createFolder('EVENTI');
	const archivi = eventi.getFoldersByName('EVENTI PASSATI');
	const passati = archivi.hasNext() ? archivi.next() : eventi.createFolder('EVENTI PASSATI');
	return { eventi: eventi, passati: passati };
}

/** Sposta il nuovo foglio nella cartella EVENTI della radice Drive. */
function spostaFoglioAccantoAlDatabase_(idFoglio) {
	const cartella = ottieniCartelleEventi_().eventi;
	DriveApp.getFileById(String(idFoglio)).moveTo(cartella);
	return 'EVENTI';
}

/** Registra gli indirizzi pubblici prodotti da WordPress senza accettare protocolli diversi da HTTPS. */
function aggiornaCollegamentiProduzioneEvento_(idEvento, urlIscrizione, urlSaldo) {
	const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
	const collegamento = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === String(idEvento); });
	if (!collegamento) throw new Error('Collegamento al foglio operativo non trovato.');
	registro.getRange(collegamento._row, 5, 1, 2).setValues([[urlIscrizione, urlSaldo]]);
}

function normalizzaUrlPubblico_(valore) {
	const url = normalizzaTesto_(valore, 1000);
	if (!url) return '';
	if (!/^https:\/\/[^\s]+$/i.test(url)) throw new Error('Indirizzo pubblico non valido.');
	return neutralizzaFormula_(url, 1000);
}

/** Riallinea dal database soltanto dopo una conferma esplicita nell'interfaccia. */
function aggiornaFoglioOperativoEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
  const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const vista = generaVistaOperativaEvento_(idEvento);
  const esito = scriviFoglioOperativoEvento_(scheda, vista);
  configuraSchedeEconomicheEvento_(foglio, idEvento);
  if (eventoPrevedeMovimenti_(idEvento)) aggiornaProiezionePagamentiEvento_(foglio, idEvento);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'REFRESH', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'DATABASE_TO_EVENT_SHEET', 'SEGRETERIA');
  return { ok: true, url_foglio: foglio.getUrl(), righe: vista.righe.length, esito: esito, message: 'Aggiornamento completato: ' + esito.aggiunte + ' partecipanti aggiunti, ' + esito.manuali + ' modifiche manuali conservate, ' + esito.conflitti + ' celle da verificare.' };
}

/** Confronta il foglio evento con DB_MODULI senza scrivere alcun dato. */
function preparaSincronizzazioneFoglioOperativo(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const mappa = mappaColonneEvento_(scheda);
  if (!mappa._ordine || !mappa._numero || !mappa._base) throw new Error('Verifica prima la migrazione del foglio agli identificativi stabili.');
  const campi = Object.keys(mappa).filter(function (campo) { return campo.charAt(0) !== '_'; });
  const vista = generaVistaOperativaEvento_(idEvento, campi);
  const centrali = vista.righe.reduce(function (indice, riga) { indice[riga.codice_ordine + '|' + riga.numero_partecipante] = riga; return indice; }, {});
  const valori = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, scheda.getLastColumn()).getValues() : [];
  // Servizi, sistemazioni e domande dinamiche richiedono un comando coordinato con WordPress.
  const modificabili = ['email', 'phone', 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality', 'emergency_contact'];
  const modifiche = [];
  const problemi = [];
  const viste = {};
  const identitaViste = {};
  vista.colonne.forEach(function (colonna) { viste[colonna.key] = colonna.label; });
  valori.forEach(function (riga, indiceRiga) {
    if (riga.every(function (valore) { return valore === ''; })) return;
    const codice = normalizzaTesto_(riga[mappa._ordine - 1], 64);
    const numero = Number(riga[mappa._numero - 1]);
    const id = identitaRigaEvento_(idEvento, codice, numero);
    const centrale = centrali[codice + '|' + numero];
    if (!id || !centrale || identitaViste[id]) { problemi.push('Riga ' + (indiceRiga + 2) + ': collegamento tecnico assente, storico o duplicato; nessuna cancellazione automatica.'); return; }
    identitaViste[id] = true;
    let base;
    try { base = baseRigaEvento_(riga[mappa._base - 1], id); }
    catch (errore) { problemi.push('Riga ' + (indiceRiga + 2) + ': ' + errore.message); return; }
    campi.forEach(function (campo) {
      if (!Object.prototype.hasOwnProperty.call(centrale.valori, campo)) return; // Colonna storica.
      const nuovo = testoCellaEvento_(riga[mappa[campo] - 1]);
      const precedente = testoCellaEvento_(centrale.valori[campo]);
      const stato = statoCellaEvento_(nuovo, precedente, base.campi[campo], scheda.getRange(indiceRiga + 2, mappa[campo]).getFormula());
      if (stato === 'ALLINEATO' || stato === 'AGGIORNAMENTO_CENTRALE') return;
      if (stato !== 'MODIFICA_MANUALE' || modificabili.indexOf(campo) < 0) { problemi.push('Riga ' + (indiceRiga + 2) + ': “' + (viste[campo] || campo) + '” richiede verifica (' + stato + ').'); return; }
      if (nuovo.length > 1000 || (campo === 'email' && nuovo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(nuovo))) { problemi.push('Riga ' + (indiceRiga + 2) + ': valore non valido per ' + viste[campo] + '.'); return; }
      modifiche.push({ codice_ordine: codice, numero_partecipante: numero, campo: campo, etichetta: viste[campo] || campo, precedente: precedente, nuovo: nuovo });
    });
  });
  if (modifiche.length > 200) throw new Error('Sono state rilevate più di 200 modifiche: suddividere il lavoro in blocchi più piccoli.');
  const firma = creaFirmaSincronizzazioneFoglio_(idEvento, modifiche);
  return { id_evento: idEvento, modifiche: modifiche, problemi: problemi.slice(0, 50), firma: firma, applicabile: modifiche.length > 0 && problemi.length === 0 };
}

/** Applica soltanto una differenza appena ricalcolata e confermata dall'operatore. */
function confermaSincronizzazioneFoglioOperativo(form) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try { return applicaSincronizzazioneFoglioOperativo_(form); }
  finally { lock.releaseLock(); }
}

function applicaSincronizzazioneFoglioOperativo_(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  const firma = normalizzaTesto_(form.firma, 128);
  const motivo = normalizzaTesto_(form.motivo, 500);
  if (!idEvento || !firma || !motivo) throw new Error('Evento, firma e motivo della sincronizzazione sono obbligatori.');
  const anteprima = preparaSincronizzazioneFoglioOperativo({ id_evento: idEvento });
  if (!anteprima.applicabile || anteprima.firma !== firma) throw new Error('Il foglio è cambiato dopo l’anteprima: controllare nuovamente le differenze.');
  const operatore = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  anteprima.modifiche.forEach(function (modifica) {
    if (modifica.campo === 'room') {
      cambiaSistemazioneSegreteria({ order_code: modifica.codice_ordine, participant_number: modifica.numero_partecipante, room_code: modifica.nuovo, reason: motivo });
      return;
    }
    registraOperazioneSegreteria_(modifica.codice_ordine, modifica.numero_partecipante, 'SYNC_EVENT_SHEET', { key: modifica.campo, value: modifica.nuovo, previous: modifica.precedente }, motivo, operatore, 'Modifica confermata dal foglio operativo dell’evento.');
  });
  aggiungiControllo_('FOGLIO_OPERATIVO', 'SYNC', idEvento, 'SUCCESS', operatore, String(anteprima.modifiche.length), 'SEGRETERIA');
  return { ok: true, count: anteprima.modifiche.length, message: 'Sincronizzate ' + anteprima.modifiche.length + ' modifiche con storico.' };
}

function trovaCollegamentoFoglioOperativo_(idEvento) {
  const collegamento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  return collegamento;
}

function creaFirmaSincronizzazioneFoglio_(idEvento, modifiche) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, idEvento + '|' + JSON.stringify(modifiche), Utilities.Charset.UTF_8).map(function (valore) { return ('0' + (valore & 255).toString(16)).slice(-2); }).join('');
}

function scriviFoglioOperativoEvento_(scheda, vista) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try { return aggiornaDatiIncrementaliEvento_(scheda, vista); }
  finally { lock.releaseLock(); }
}

function rimuoviRaggruppamentiColonne_(scheda) {
  for (let colonna = 1; colonna <= scheda.getMaxColumns(); colonna += 1) {
    let profondita = scheda.getColumnGroupDepth(colonna);
    while (profondita > 0) {
      const raggruppamento = scheda.getColumnGroup(colonna, profondita);
      if (!raggruppamento) break;
      raggruppamento.remove();
      profondita = scheda.getColumnGroupDepth(colonna);
    }
  }
}

function raggruppaColonneFoglioOperativo_(scheda, colonne) {
  let inizio = -1;
  let gruppo = '';
  const chiudi = function (fine) {
    if (inizio < 0 || fine < inizio) return;
    scheda.getRange(1, inizio + 3, Math.max(1, scheda.getMaxRows()), fine - inizio + 1).shiftColumnGroupDepth(1);
  };
  colonne.forEach(function (colonna, indice) {
    const corrente = colonna.gruppo === 'persona' ? '' : colonna.gruppo;
    if (corrente === gruppo) return;
    chiudi(indice - 1);
    gruppo = corrente;
    inizio = corrente ? indice : -1;
  });
  chiudi(colonne.length - 1);
}


// Sorgente: FoglioIncrementale.gs
/** Identità delle colonne legata ai metadati Google, che seguono gli spostamenti. */
function mappaColonneEvento_(scheda) {
  const mappa = Object.create(null);
  const posizioni = {};
  scheda.createDeveloperMetadataFinder().withKey('MI_CAMPO').find().forEach(function (meta) {
    const range = meta.getLocation().getColumn();
    if (!range) throw new Error('Metadato campo senza colonna.');
    const key = meta.getValue();
    const col = range.getColumn();
    if (mappa[key] || posizioni[col]) throw new Error('Identificativi di colonna duplicati: correggere il foglio.');
    mappa[key] = col;
    posizioni[col] = true;
  });
  return mappa;
}

function identificaColonnaEvento_(scheda, colonna, key) {
  intervalloColonnaEvento_(scheda, colonna).addDeveloperMetadata('MI_CAMPO', key);
}

/** I metadati richiedono una colonna non delimitata, anche se il range copre tutte le righe. */
function intervalloColonnaEvento_(scheda, colonna) {
  let lettere = '';
  for (let n = colonna; n > 0; n = Math.floor((n - 1) / 26)) {
    lettere = String.fromCharCode(65 + (n - 1) % 26) + lettere;
  }
  return scheda.getRange(lettere + ':' + lettere);
}

function testoCellaEvento_(valore) {
  return valore instanceof Date ? valore.toISOString() : String(valore == null ? '' : valore);
}

function improntaCellaEvento_(valore) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, testoCellaEvento_(valore), Utilities.Charset.UTF_8)
    .map(function (byte) { return ('0' + (byte & 255).toString(16)).slice(-2); }).join('');
}

function identitaRigaEvento_(evento, codice, numero) {
  if (!codice || !Number.isInteger(Number(numero)) || Number(numero) < 1) return '';
  return JSON.stringify([String(evento), String(codice), Number(numero)]);
}

function baseRigaEvento_(valore, id) {
  if (!valore) return { id: id, campi: {} };
  let base;
  try { base = JSON.parse(String(valore)); } catch (errore) { throw new Error('Versione di confronto danneggiata.'); }
  if (base.id !== id || !base.campi || typeof base.campi !== 'object' || Array.isArray(base.campi)) throw new Error('Identità della riga modificata: sincronizzazione sospesa.');
  return base;
}

/** Non deduce mai una vecchia struttura dai titoli: su fogli popolati serve una migrazione verificata. */
function preparaStrutturaIncrementale_(scheda, vista) {
  let mappa = mappaColonneEvento_(scheda);
  const nuovo = !Object.keys(mappa).length;
  if (nuovo && scheda.getLastRow() > 1) throw new Error('Foglio precedente con dati: occorre verificare la corrispondenza delle colonne prima della migrazione. Nessun dato è stato modificato.');
  if (nuovo && scheda.getLastColumn() > 0) {
    throw new Error('Foglio senza identificativi stabili: verificare la struttura prima della migrazione.');
  }
  const tecniche = [
    { key: '_ordine', label: 'Codice prenotazione' },
    { key: '_numero', label: 'Numero partecipante' },
    { key: '_base', label: 'Versione sincronizzata' }
  ];
  if (!nuovo && tecniche.some(function (campo) { return !mappa[campo.key]; })) throw new Error('Manca una colonna tecnica: ripristinarla prima di aggiornare.');
  const campi = tecniche.concat(vista.colonne).concat([{ key: 'mi_sync_state', label: 'Verifica sincronizzazione', gruppo: 'persona' }]);
  const nuove = {};
  const visti = {};
  campi.forEach(function (campo) {
    if (!campo.key || visti[campo.key]) throw new Error('Identificativo campo duplicato o vuoto.');
    visti[campo.key] = true;
  });
  campi.forEach(function (campo, indice) {
    if (mappa[campo.key]) return;
    nuove[campo.key] = true;
    let precedente = 0;
    for (let i = indice - 1; i >= 0; i -= 1) {
      if (mappa[campi[i].key]) { precedente = mappa[campi[i].key]; break; }
    }
    const col = precedente + 1;
    if (col <= scheda.getLastColumn()) scheda.insertColumnBefore(col);
    else if (col > scheda.getMaxColumns()) scheda.insertColumnsAfter(scheda.getMaxColumns(), col - scheda.getMaxColumns());
    identificaColonnaEvento_(scheda, col, campo.key);
    intervalloColonnaEvento_(scheda, col).addDeveloperMetadata('MI_BASE_VUOTA', '1');
    scheda.getRange(1, col).setValue(campo.label).setFontWeight('bold').setBackground('#172554').setFontColor('#ffffff');
    mappa = mappaColonneEvento_(scheda);
    if (campo.key.charAt(0) === '_') scheda.hideColumns(col);
    else if (campo.gruppo && campo.gruppo !== 'persona' && scheda.getColumnGroupDepth(col) === 0) {
      scheda.getRange(1, col, scheda.getMaxRows(), 1).shiftColumnGroupDepth(1);
    }
  });
  scheda.setFrozenRows(1);
  return { mappa: mappa, nuove: nuove };
}

/** Tre versioni: ultima sincronizzata, cella manuale, valore centrale. */
function statoCellaEvento_(locale, centrale, base, formula) {
  const improntaLocale = improntaCellaEvento_(locale);
  const improntaCentrale = improntaCellaEvento_(centrale);
  if (formula) return 'FORMULA_MANUALE';
  if (improntaLocale === improntaCentrale) return 'ALLINEATO';
  if (!base) return 'DA_VERIFICARE';
  if (improntaLocale === base) return 'AGGIORNAMENTO_CENTRALE';
  if (improntaCentrale === base) return 'MODIFICA_MANUALE';
  return 'CONFLITTO';
}

function aggiornaDatiIncrementaliEvento_(scheda, vista) {
  const metadati = scheda.getDeveloperMetadata();
  const evento = metadati.find(function (meta) { return meta.getKey() === 'MI_ID_EVENTO'; });
  if (evento && String(evento.getValue()) !== String(vista.evento.id)) throw new Error('Il foglio appartiene a un altro evento.');
  if (!Object.keys(mappaColonneEvento_(scheda)).length && scheda.getLastColumn() > 0) migraStrutturaEventoVerificata_(scheda, vista);
  const struttura = preparaStrutturaIncrementale_(scheda, vista);
  const mappa = struttura.mappa;
  const basiVuote = {};
  scheda.createDeveloperMetadataFinder().withKey('MI_BASE_VUOTA').find().forEach(function (meta) {
    const range = meta.getLocation().getColumn();
    if (range) basiVuote[range.getColumn()] = true;
  });
  impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', vista.evento.id);
  const dati = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, scheda.getLastColumn()).getValues() : [];
  const indice = Object.create(null);
  // Verifica tutte le identità prima di modificare le celle dei partecipanti.
  dati.forEach(function (riga, offset) {
    if (riga.every(function (valore) { return valore === ''; })) return;
    const id = identitaRigaEvento_(vista.evento.id, riga[mappa._ordine - 1], riga[mappa._numero - 1]);
    if (!id) {
      if (riga[mappa._base - 1]) throw new Error('Identità tecnica rimossa da una riga già sincronizzata.');
      return; // Una riga manuale incompleta non registra una prenotazione.
    }
    if (indice[id]) throw new Error('Identificativo partecipante duplicato nel foglio.');
    baseRigaEvento_(riga[mappa._base - 1], id);
    indice[id] = offset + 2;
  });
  const centrali = {};
  vista.righe.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga.codice_ordine, riga.numero_partecipante);
    if (!id || centrali[id]) throw new Error('Identificativo partecipante centrale non valido o duplicato.');
    centrali[id] = true;
  });
  const risultato = { aggiunte: 0, aggiornate: 0, manuali: 0, conflitti: 0 };
  dati.forEach(function (riga, offset) {
    const id = identitaRigaEvento_(vista.evento.id, riga[mappa._ordine - 1], riga[mappa._numero - 1]);
    if (!id || !centrali[id]) scheda.getRange(offset + 2, mappa.mi_sync_state).setValue(id ? 'Non presente tra le iscrizioni attive: verificare' : 'Bozza manuale: non convalidata, nessun posto impegnato');
  });
  vista.righe.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga.codice_ordine, riga.numero_partecipante);
    const nuova = !indice[id];
    const numero = indice[id] || Math.max(2, scheda.getLastRow() + 1);
    if (numero > scheda.getMaxRows()) scheda.insertRowsAfter(scheda.getMaxRows(), numero - scheda.getMaxRows());
    if (nuova) {
      const iniziale = Array(scheda.getLastColumn()).fill('');
      const baseIniziale = { id: id, campi: {} };
      vista.colonne.forEach(function (campo) {
        const valore = normalizzaTesto_(testoCellaEvento_(riga.valori[campo.key]), 5000);
        iniziale[mappa[campo.key] - 1] = neutralizzaFormula_(valore, 5000);
        baseIniziale.campi[campo.key] = improntaCellaEvento_(valore);
      });
      iniziale[mappa._ordine - 1] = riga.codice_ordine;
      iniziale[mappa._numero - 1] = riga.numero_partecipante;
      iniziale[mappa._base - 1] = JSON.stringify(baseIniziale);
      iniziale[mappa.mi_sync_state - 1] = 'Allineato';
      // Una sola scrittura evita identità parziali se il processo si interrompe.
      scheda.getRange(numero, 1, 1, iniziale.length).setNumberFormat('@').setValues([iniziale]);
      indice[id] = numero;
      risultato.aggiunte += 1;
      risultato.aggiornate += vista.colonne.length;
      return;
    }
    const rangeRiga = scheda.getRange(numero, 1, 1, scheda.getLastColumn());
    const valoriLocali = rangeRiga.getValues()[0];
    const formuleLocali = rangeRiga.getFormulas()[0];
    const base = baseRigaEvento_(valoriLocali[mappa._base - 1], id);
    const prima = { manuali: risultato.manuali, conflitti: risultato.conflitti };
    vista.colonne.forEach(function (campo) {
      const cella = scheda.getRange(numero, mappa[campo.key]);
      const valore = normalizzaTesto_(testoCellaEvento_(riga.valori[campo.key]), 5000);
      if (!base.campi[campo.key] && basiVuote[mappa[campo.key]]) base.campi[campo.key] = improntaCellaEvento_('');
      const stato = statoCellaEvento_(valoriLocali[mappa[campo.key] - 1], valore, base.campi[campo.key], formuleLocali[mappa[campo.key] - 1]);
      if (stato === 'AGGIORNAMENTO_CENTRALE') {
        // Rilettura immediata: il lock serializza gli script, non le digitazioni umane.
        const attuale = cella.getValue();
        if (!nuova && (cella.getFormula() || improntaCellaEvento_(attuale) !== base.campi[campo.key])) { risultato.conflitti += 1; return; }
        cella.setNumberFormat('@').setValue(neutralizzaFormula_(valore, 5000));
        base.campi[campo.key] = improntaCellaEvento_(valore);
        risultato.aggiornate += 1;
      } else if (stato === 'ALLINEATO') base.campi[campo.key] = improntaCellaEvento_(valore);
      else if (stato === 'MODIFICA_MANUALE' || stato === 'FORMULA_MANUALE') risultato.manuali += 1;
      else risultato.conflitti += 1;
    });
    scheda.getRange(numero, mappa._base).setValue(JSON.stringify(base));
    scheda.getRange(numero, mappa.mi_sync_state).setValue(risultato.conflitti > prima.conflitti ? 'Conflitto o dati precedenti da verificare' : (risultato.manuali > prima.manuali ? 'Modifiche manuali da convalidare' : 'Allineato'));
  });
  impostaMetadatoVista_(scheda, 'MI_DATA_AGGIORNAMENTO', new Date().toISOString());
  return risultato;
}

/** Adozione una tantum del vecchio formato solo se ogni intestazione è ancora verificabile. */
function migraStrutturaEventoVerificata_(scheda, vista) {
  const meta = scheda.getDeveloperMetadata().find(function (item) { return item.getKey() === 'MI_CAMPI'; });
  let campi;
  try { campi = JSON.parse(meta ? meta.getValue() : 'null'); } catch (errore) {}
  if (!Array.isArray(campi) || !campi.length || new Set(campi).size !== campi.length || campi.some(function (key) { return typeof key !== 'string' || key.charAt(0) === '_'; })) throw new Error('Struttura precedente non verificabile: nessun dato modificato.');
  const catalogo = campiElencoOperativo_(false).concat(vista.colonne).reduce(function (result, campo) { result[campo.key] = campo.label; return result; }, {});
  const attese = ['Codice prenotazione', 'Numero partecipante'].concat(campi.map(function (key) { return catalogo[key]; }));
  const reali = scheda.getRange(1, 1, 1, attese.length).getValues()[0];
  if (attese.some(function (label, index) { return !label || label !== reali[index]; })) throw new Error('Colonne precedenti spostate o rinominate: verificare la migrazione senza sovrascrivere il foglio.');
  const dati = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, 2).getValues() : [];
  const ids = {};
  dati.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga[0], riga[1]);
    if (id && ids[id]) throw new Error('Identificativi duplicati nel foglio precedente.');
    if (id) ids[id] = true;
  });
  ['_ordine', '_numero'].concat(campi).forEach(function (key, index) { identificaColonnaEvento_(scheda, index + 1, key); });
  const col = scheda.getLastColumn() + 1;
  if (col > scheda.getMaxColumns()) scheda.insertColumnsAfter(scheda.getMaxColumns(), 1);
  scheda.getRange(1, col).setValue('Versione sincronizzata');
  identificaColonnaEvento_(scheda, col, '_base');
  scheda.hideColumns(col);
  // La prima lettura riconosce come base soltanto celle uguali al centro.
  // Le divergenze preesistenti rimangono DA_VERIFICARE, mai attribuite arbitrariamente.
}


// Sorgente: Gruppi.gs
function elencaGruppi() {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS)).map(function (row) {
    return {
      id: normalizzaTesto_(row.id_gruppo, 64),
      nome: normalizzaTesto_(row.nome, 120),
      slug: normalizzaTesto_(row.slug, 80),
      stato: normalizzaTesto_(row.stato, 20),
      logo_url: normalizzaUrlImmagineGruppo_(row.logo_url),
      immagine_url: normalizzaUrlImmagineGruppo_(row.immagine_url)
    };
  }).filter(function (row) { return row.id && row.nome && row.stato !== 'ARCHIVIATO'; });
}

function aggiungiGruppo(form) {
  form = form || {};
  const nome = normalizzaTesto_(form.nome, 120);
  if (nome.length < 2) throw new Error('Indica il nome del gruppo.');
  const slug = creaSlugGruppo_(form.slug || nome);
  if (!slug) throw new Error('Il nome del gruppo non produce un identificativo valido.');
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  const existing = elencaGruppi();
  if (existing.some(function (group) { return group.slug === slug || group.nome.toLowerCase() === nome.toLowerCase(); })) throw new Error('Il gruppo esiste già.');
  const logoUrl = normalizzaUrlImmagineGruppo_(form.logo_url);
  const imageUrl = normalizzaUrlImmagineGruppo_(form.immagine_url);
  const wordpress = inviaComandoWordPress_('CREATE_GROUP', { name: nome, slug: slug, logo_url: logoUrl, image_url: imageUrl });
  const wordpressId = Math.round(Number(wordpress.group_id) || 0);
  if (wordpressId < 1) throw new Error('WordPress non ha restituito l’identificativo del gruppo.');
  const id = String(wordpressId);
  sheet.appendRow([id, nome, slug, 'ATTIVO', logoUrl, imageUrl, new Date()]);
  return { ok: true, id: id, nome: nome, slug: slug, esistente_in_wordpress: wordpress.existing === true };
}

/** Allinea gli identificativi dei gruppi esistenti con WordPress senza cancellare righe. */
function sincronizzaGruppiConWordPress() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  const rows = convertiRigheInOggetti_(sheet);
  let updated = 0;
  rows.forEach(function (row) {
    const name = normalizzaTesto_(row.nome, 120);
    const slug = creaSlugGruppo_(row.slug || name);
    if (!name || !slug) return;
    const wordpress = inviaComandoWordPress_('CREATE_GROUP', { name: name, slug: slug, logo_url: normalizzaUrlImmagineGruppo_(row.logo_url), image_url: normalizzaUrlImmagineGruppo_(row.immagine_url) });
    const wordpressId = Math.round(Number(wordpress.group_id) || 0);
    if (wordpressId < 1) throw new Error('WordPress non ha restituito l’identificativo del gruppo ' + name + '.');
    if (String(row.id_gruppo) !== String(wordpressId)) {
      sheet.getRange(row._row, 1).setValue(String(wordpressId));
      updated += 1;
    }
  });
  aggiungiControllo_('SYNC_GROUPS', 'GROUPS', 'ALL', 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail(), 120), String(updated), 'WORKSPACE_UI');
  return { ok: true, updated: updated, message: 'Gruppi allineati con WordPress: ' + updated + ' identificativi aggiornati.' };
}

/** Allinea gli identificativi dei gruppi esistenti con WordPress senza cancellare righe. */
function sincronizzaGruppiConWordPress() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  const rows = convertiRigheInOggetti_(sheet);
  let updated = 0;
  rows.forEach(function (row) {
    const name = normalizzaTesto_(row.nome, 120);
    const slug = creaSlugGruppo_(row.slug || name);
    if (!name || !slug) return;
    const wordpress = inviaComandoWordPress_('CREATE_GROUP', { name: name, slug: slug });
    const wordpressId = Math.round(Number(wordpress.group_id) || 0);
    if (wordpressId < 1) throw new Error('WordPress non ha restituito l’identificativo del gruppo ' + name + '.');
    if (String(row.id_gruppo) !== String(wordpressId)) {
      sheet.getRange(row._row, 1).setValue(String(wordpressId));
      updated += 1;
    }
  });
  aggiungiControllo_('SYNC_GROUPS', 'GROUPS', 'ALL', 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail(), 120), String(updated), 'WORKSPACE_UI');
  return { ok: true, updated: updated, message: 'Gruppi allineati con WordPress: ' + updated + ' identificativi aggiornati.' };
}

function creaSlugGruppo_(value) {
  return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
}

function normalizzaUrlImmagineGruppo_(value) {
  const url = normalizzaTesto_(value, 500);
  if (!url) return '';
  if (!/^https:\/\/[^\s]+$/i.test(url)) throw new Error('Logo o immagine devono usare un URL HTTPS.');
  return url;
}


// Sorgente: InterfacciaMovimentiEvento.gs
const MI_EVENT_MOVEMENT_FORM = Object.freeze({
  VERSION: '2', SHEET: 'Registra movimento', ORDER: 'B7', ORDER_STATUS: 'F7', EVENT: 'B9', REFERENT: 'E9',
  TOTAL_VALUE: 'C10', PAID_VALUE: 'E10', BALANCE_VALUE: 'G10', TRANSACTION: 'B13', INSTALLMENT: 'D13', DATE: 'F13',
  AMOUNT: 'B15', SOURCE: 'D15', REFERENCE: 'F15', OPERATOR: 'B19', NOTE: 'B21', COMMAND: 'E25', STATUS: 'B28',
  REQUEST_ID: 'Z1', MARKER: 'Z2', EVENT_ID: 'Z3', CHOICE_LABELS: 'AA2:AA', CHOICE_CODES: 'AB2:AB'
});

/** Crea nel file dell'evento un modulo visuale; DB_MODULI resta il libro autorevole. */
function preparaInterfacciaMovimentoEvento_(foglio, idEvento) {
  let sheet = foglio.getSheetByName(MI_EVENT_MOVEMENT_FORM.SHEET);
  const nuova = !sheet;
  if (!sheet) sheet = foglio.insertSheet(MI_EVENT_MOVEMENT_FORM.SHEET, 0);
  if (nuova || String(sheet.getRange(MI_EVENT_MOVEMENT_FORM.MARKER).getValue()) !== MI_EVENT_MOVEMENT_FORM.VERSION) costruisciInterfacciaMovimentoEvento_(sheet, idEvento);
  if (String(sheet.getRange('Z4').getValue()) !== 'pagamenti-legibili-1') applicaStileModuloPagamenti_(sheet, true);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).setValue(String(idEvento));
  aggiornaScelteInterfacciaMovimentoEvento_(sheet, idEvento);
  assicuraTriggerInterfacciaMovimentoEvento_(foglio);
  return sheet;
}

/** Aggiorna solo la presentazione: conserva valori, convalide e chiave del movimento in corso. */
function aggiornaGraficaModuliPagamenti() {
  const centrale = ottieniFoglioDiLavoroAssociato_();
  const esiti = [];
  const aggiorna = function (foglio, evento, idEvento) {
    const scheda = foglio.getSheetByName('Registra movimento');
    if (!scheda) return;
    applicaStileModuloPagamenti_(scheda, evento);
    if (evento) {
      scheda.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).setValue(String(idEvento));
      aggiornaScelteInterfacciaMovimentoEvento_(scheda, idEvento);
      assicuraTriggerInterfacciaMovimentoEvento_(foglio);
    }
    esiti.push({ foglio: foglio.getName(), url: foglio.getUrl() + '#gid=' + scheda.getSheetId(), aggiornato: true });
  };
  aggiorna(centrale, false);
  const collegamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
  const visitati = {};
  visitati[centrale.getId()] = true;
  collegamenti.forEach(function (item) {
    const id = String(item.id_foglio || '');
    if (!id || visitati[id]) return;
    visitati[id] = true;
    try { aggiorna(SpreadsheetApp.openById(id), true, String(item.id_evento)); }
    catch (error) { esiti.push({ id_evento: String(item.id_evento), aggiornato: false, errore: String(error.message || error) }); }
  });
  SpreadsheetApp.flush();
  console.log(JSON.stringify(esiti));
  if (esiti.some(function (esito) { return !esito.aggiornato; })) throw new Error('Alcuni moduli non sono stati aggiornati: consultare il log.');
  return esiti;
}

function applicaStileModuloPagamenti_(sheet, moduloEvento) {
  const bordo = SpreadsheetApp.BorderStyle.SOLID;
  sheet.setHiddenGridlines(true);
  sheet.setFrozenRows(2);
  sheet.setFrozenColumns(0);
  sheet.setTabColor('#244fc2');
  sheet.setColumnWidth(1, 24);
  [104, 158, 104, 158, 124, 112, 112].forEach(function (larghezza, indice) { sheet.setColumnWidth(indice + 2, larghezza); });
  sheet.setColumnWidth(9, 24);
  sheet.hideColumns(10, sheet.getMaxColumns() - 9);
  // Il canvas termina dopo l'esito; le righe tecniche rimangono disponibili, nascoste.
  if (sheet.getMaxRows() > 30) sheet.hideRows(31, sheet.getMaxRows() - 30);
  sheet.getRange('A1:I30').setFontFamily('Arial').setFontSize(12)
    .setVerticalAlignment('middle').setWrap(true);
  sheet.getRange('B1:H27').setFontColor('#172033');
  sheet.getRangeList(['A1:A30', 'I1:I30', 'B4:H4', 'B16:H16', 'B23:H23', 'B30:H30']).setBackground('#f5f7fa');
  sheet.setRowHeights(1, 30, 24);
  sheet.setRowHeights(1, 2, 28).setRowHeight(3, 40);
  [4, 16, 23, 30].forEach(function (riga) { sheet.setRowHeight(riga, 12); });
  [5, 11, 17, 24].forEach(function (riga) { sheet.setRowHeight(riga, 32); });
  [7, 13, 15, 19, 25].forEach(function (riga) { sheet.setRowHeight(riga, 44); });
  sheet.setRowHeight(9, 64).setRowHeight(10, 52).setRowHeight(26, 44).setRowHeights(28, 2, 44);
  sheet.getRange('B1:H2').setFontSize(24).setFontWeight('bold').setBackground('#17224a').setFontColor('#ffffff');
  sheet.getRange('B3:H3').setFontSize(12).setFontColor('#475569').setBackground('#ffffff');
  sheet.getRangeList(['B5:H5', 'B11:H11', 'B17:H17', 'B24:H24'])
    .setFontSize(13).setFontWeight('bold').setBackground('#e8edf7').setFontColor('#17224a');
  sheet.getRangeList(['B6:H6', 'B8:H8', 'B12:H12', 'B14:H14', 'B18:H18', 'B20:H20', 'B27:H27'])
    .setFontSize(11).setFontWeight('bold').setFontColor('#475569').setBackground('#ffffff');
  const campi = ['B7:E7', 'B13:C13', 'D13:E13', 'F13:H13', 'B15:C15', 'D15:E15', 'F15:H15', 'B19:H19', 'B21:H22'];
  sheet.getRangeList(campi).setBackground('#fffdf3').setFontSize(14).setFontColor('#172033');
  sheet.getRangeList(campi).getRanges().forEach(function (range) {
    range.setBorder(true, true, true, true, false, false, '#94a3b8', bordo);
  });
  sheet.getRange('B7:E7').setFontWeight('bold');
  sheet.getRange('B21:H22').setFontSize(12).setVerticalAlignment('top');
  sheet.getRange('B9:H9').setBackground('#f1f5f9').setFontSize(12);
  sheet.getRange('F7:H7').setBackground('#f1f5f9').setFontSize(12).setFontWeight('bold');
  sheet.getRange('B10:H10').setBackground('#f1f5f9');
  sheet.getRangeList(['B10', 'D10', 'F10']).setFontSize(11).setFontWeight('normal').setFontColor('#475569');
  sheet.getRangeList(['C10', 'E10', 'G10:H10']).setFontSize(16).setFontWeight('bold').setHorizontalAlignment('right')
    .setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange('F10:H10').setBackground('#fff1cc').setFontColor('#713f12');
  sheet.getRange('B15:C15').setFontSize(20).setFontWeight('bold').setHorizontalAlignment('right');
  sheet.getRange('B28:H29').setFontSize(12); // Conserva il colore dell'ultimo esito.
  sheet.getRange('B14:C14').setValue('Importo in euro *');
  sheet.getRange('D14:E14').setValue('Metodo *');
  sheet.getRange('F14:H14').setValue('Riferimento (facoltativo)');
  sheet.getRange('B20:H20').setValue('Nota amministrativa (facoltativa)');
  sheet.getRange('B3:H3').setValue('1. Scegli la prenotazione   →   2. Compila il movimento   →   3. Controlla e registra');
  sheet.getRange('B15').setNote('Inserisci l’importo in euro, con al massimo due decimali. Esempio: 125,50.');
  sheet.getRange('F15').setNote('Facoltativo: identificativo del bonifico o della ricevuta.');
  if (moduloEvento) {
    // Alcuni fogli storici hanno ancora il comando esteso da B25 a H25.
    const comandoStorico = sheet.getRange('B25:H25').getMergedRanges().some(function (range) { return range.getA1Notation() === 'B25:H25'; });
    if (comandoStorico) {
      sheet.getRange('B25:H25').breakApart().clearContent().clearDataValidations();
      sheet.getRange('B25:D25').merge();
      sheet.getRange('E25:H25').merge().setBackground('#64748b').setHorizontalAlignment('center');
      sheet.getRange('E25').setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['REGISTRA MOVIMENTO'], true).setAllowInvalid(false).build());
    }
    sheet.getRange('B25:D25').setValue('* Obbligatori · celle chiare da compilare').setBackground('#ffffff').setFontSize(11).setFontColor('#475569');
    sheet.getRange('E25:H25').setFontSize(14).setFontWeight('bold').setFontColor('#ffffff');
    sheet.getRange('B26:H26').setValue('Per salvare, apri la tendina blu qui sopra e scegli REGISTRA MOVIMENTO. Si attiva dopo la scelta della prenotazione.')
      .setFontSize(11).setFontColor('#475569').setHorizontalAlignment('left');
  } else {
    sheet.getRange('B25:H25').setBackground('#eef2ff').setFontSize(13);
    sheet.getRange('B26:H26').merge().setValue('Dopo la verifica, usa Modulo iscrizioni → Registra movimento guidato.').setFontSize(11).setFontColor('#475569');
  }
  sheet.getRange('Z4').setValue('pagamenti-legibili-1');
}

/** ZERO è la scelta esplicita “Evento totalmente gratuito”; i dati precedenti usavano REGISTRATION_ONLY. */
function eventoPrevedeMovimenti_(idEvento) {
  const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (item) { return String(item.id_evento) === String(idEvento); });
  if (!evento) throw new Error('Evento non trovato in DB_MODULI.');
  return ['ZERO', 'REGISTRATION_ONLY'].indexOf(String(evento.modalita_prezzo || '').toUpperCase()) < 0;
}

function configuraSchedeEconomicheEvento_(foglio, idEvento) {
  if (!eventoPrevedeMovimenti_(idEvento)) {
    ['Registra movimento', 'Pagamenti'].forEach(function (nome) {
      const scheda = foglio.getSheetByName(nome);
      if (scheda && !scheda.isSheetHidden() && foglio.getSheets().length > 1) scheda.hideSheet();
    });
    rimuoviTriggerInterfacciaMovimentoEvento_(foglio);
    return { pagamenti: false };
  }
  const pagamenti = preparaPagamentiEvento_(foglio, idEvento);
  if (pagamenti.isSheetHidden()) pagamenti.showSheet();
  const modulo = preparaInterfacciaMovimentoEvento_(foglio, idEvento);
  if (modulo.isSheetHidden()) modulo.showSheet();
  return { pagamenti: true };
}

function costruisciInterfacciaMovimentoEvento_(sheet, idEvento) {
  sheet.getDataRange().breakApart();
  sheet.clear();
  if (sheet.getMaxColumns() < 28) sheet.insertColumnsAfter(sheet.getMaxColumns(), 28 - sheet.getMaxColumns());
  sheet.setHiddenGridlines(true).setFrozenRows(4);
  sheet.setColumnWidth(1, 28); sheet.setColumnWidths(2, 7, 120);
  sheet.setRowHeights(1, 2, 36).setRowHeight(3, 34).setRowHeights(5, 25, 32).setRowHeight(7, 38).setRowHeight(10, 40).setRowHeight(15, 38).setRowHeight(25, 40).setRowHeights(28, 2, 34);
  sheet.getRange('B1:H2').merge().setValue('Registra un movimento').setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(20).setHorizontalAlignment('left').setVerticalAlignment('middle');
  sheet.getRange('B3:H3').merge().setValue('Scegli la prenotazione, controlla il saldo e registra il movimento senza modificare lo storico.').setFontColor('#657084').setFontSize(11);
  [['B5:H5', '1 · Prenotazione'], ['B11:H11', '2 · Movimento'], ['B17:H17', '3 · Tracciabilità'], ['B24:H24', '4 · Registra']].forEach(function (item) {
    sheet.getRange(item[0]).merge().setValue(item[1]).setBackground('#e8edf7').setFontColor('#17224a').setFontWeight('bold').setFontSize(12);
  });
  sheet.getRange('B6:E6').merge().setValue('Prenotazione *'); sheet.getRange('F6:H6').merge().setValue('Stato');
  sheet.getRange('B7:E7').merge(); sheet.getRange('F7:H7').merge();
  sheet.getRange('B8:D8').merge().setValue('Evento'); sheet.getRange('E8:H8').merge().setValue('Referente');
  sheet.getRange('B9:D9').merge(); sheet.getRange('E9:H9').merge();
  sheet.getRange('B10').setValue('Totale'); sheet.getRange('D10').setValue('Versato'); sheet.getRange('F10').setValue('Residuo'); sheet.getRange('G10:H10').merge();
  sheet.getRange('B12:C12').merge().setValue('Tipo movimento'); sheet.getRange('D12:E12').merge().setValue('Rata'); sheet.getRange('F12:H12').merge().setValue('Data effettiva');
  sheet.getRange('B13:C13').merge().setValue('INCASSO'); sheet.getRange('D13:E13').merge().setValue('CAPARRA'); sheet.getRange('F13:H13').merge().setValue(new Date()).setNumberFormat('dd/mm/yyyy');
  sheet.getRange('B14:C14').merge().setValue('Importo *'); sheet.getRange('D14:E14').merge().setValue('Metodo *'); sheet.getRange('F14:H14').merge().setValue('Riferimento (facoltativo)');
  sheet.getRange('B15:C15').merge().setNumberFormat('#,##0.00 [$€-it-IT]'); sheet.getRange('D15:E15').merge().setValue('BONIFICO'); sheet.getRange('F15:H15').merge();
  sheet.getRange('B18:H18').merge().setValue('Operatore'); sheet.getRange('B19:H19').merge().setValue(normalizzaTesto_(Session.getActiveUser().getEmail(), 120));
  sheet.getRange('B20:H20').merge().setValue('Nota amministrativa'); sheet.getRange('B21:H22').merge().setWrap(true).setVerticalAlignment('top');
  sheet.getRange('B25:D25').merge().setValue('* Campi obbligatori').setFontColor('#657084').setFontSize(10).setVerticalAlignment('middle');
  sheet.getRange('E25:H25').merge().setValue('').setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['REGISTRA MOVIMENTO'], true).setAllowInvalid(false).build()).setBackground('#8b98aa').setFontColor('#ffffff').setFontWeight('bold').setHorizontalAlignment('center').setVerticalAlignment('middle');
  sheet.getRange('B26:H26').merge().setValue('Il comando si attiva dopo la scelta della prenotazione. Il saldo viene ricontrollato prima del salvataggio.').setFontColor('#657084').setFontSize(10).setHorizontalAlignment('right');
  sheet.getRange('B27:H27').merge().setValue('Esito'); sheet.getRange('B28:H29').merge().setValue('Scegli una prenotazione per caricare il riepilogo.').setWrap(true);
  sheet.getRangeList(['B6:H6', 'B8:H8', 'B12:H12', 'B14:H14', 'B18:H18', 'B20:H20', 'B27:H27']).setFontColor('#657084').setFontWeight('bold').setFontSize(10);
  const superfici = sheet.getRangeList(['B7:H7', 'B9:H10', 'B13:H15', 'B19:H19', 'B21:H22', 'B28:H29']);
  superfici.setBackground('#ffffff').setFontColor('#172033');
  superfici.getRanges().forEach(function (range) { range.setBorder(true, true, true, true, false, false, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID); });
  sheet.getRangeList(['B7:E7', 'B13:C13', 'D13:E13', 'F13:H13', 'B15:C15', 'D15:E15', 'F15:H15', 'B19:H19', 'B21:H22']).setBackground('#fffdf3');
  sheet.getRangeList(['F7:H7', 'B9:H10']).setBackground('#f3f6f9');
  sheet.getRange('F10:H10').setBackground('#fff8e6');
  sheet.getRange('B28:H29').setBackground('#f8fafc').setFontColor('#475569');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build());
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pevui'));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.MARKER).setValue(MI_EVENT_MOVEMENT_FORM.VERSION);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).setValue(String(idEvento));
  sheet.hideColumns(26, 3);
}

function prenotazioniInterfacciaMovimentoEvento_(idEvento) {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (item) { return String(item.id_evento) === String(idEvento); });
}

function creaSceltePrenotazioniInterfacciaMovimentoEvento_(prenotazioni) {
  return prenotazioni.map(function (item) {
    const nome = [item.nome_referente, item.cognome_referente].map(function (parte) { return normalizzaTesto_(parte, 80); }).filter(String).join(' ') || 'Prenotazione senza nominativo';
    const codice = String(item.codice_ordine);
    return { etichetta: nome + ' · ' + codice, codice: codice };
  }).sort(function (a, b) { return a.etichetta.localeCompare(b.etichetta, 'it', { sensitivity: 'base' }); });
}

function aggiornaScelteInterfacciaMovimentoEvento_(sheet, idEvento) {
  const prenotazioni = prenotazioniInterfacciaMovimentoEvento_(idEvento).filter(function (item) { return !!String(item.codice_ordine || ''); });
  const scelte = creaSceltePrenotazioniInterfacciaMovimentoEvento_(prenotazioni);
  const selezione = String(sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER).getValue() || '');
  const righeDaPulire = Math.max(sheet.getLastRow(), scelte.length + 1, 2);
  sheet.getRange(1, 27, righeDaPulire, 2).clearContent();
  sheet.getRange('AA1:AB1').setValues([['Etichetta prenotazione', 'Codice prenotazione']]);
  if (scelte.length) sheet.getRange(2, 27, scelte.length, 2).setValues(scelte.map(function (item) { return [item.etichetta, item.codice]; }));
  const cella = sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER).clearDataValidations();
  if (scelte.length) cella.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInRange(sheet.getRange(2, 27, scelte.length, 1), true).setAllowInvalid(false).build());
  const precedente = scelte.find(function (scelta) { return scelta.codice === selezione; });
  if (precedente) cella.setValue(precedente.etichetta);
  sheet.getRange('B6:E6').setValue('Prenotazione · cerca nome o cognome *');
  cella.setNote('Digita le prime lettere del nome o del cognome per restringere l’elenco. Il codice prenotazione resta associato internamente.');
}

function codiceSceltaInterfacciaMovimentoEvento_(sheet, scelta) {
  const testo = normalizzaTesto_(scelta, 180);
  if (!testo) return '';
  const ultimaRiga = Math.max(2, sheet.getLastRow());
  const mappa = sheet.getRange(2, 27, ultimaRiga - 1, 2).getDisplayValues();
  const corrispondenza = mappa.find(function (riga) { return riga[0] === testo; });
  return corrispondenza ? String(corrispondenza[1]) : testo;
}

function aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento) {
  const scelta = sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER).getValue();
  const codice = codiceSceltaInterfacciaMovimentoEvento_(sheet, scelta);
  const ordine = prenotazioniInterfacciaMovimentoEvento_(idEvento).find(function (item) { return String(item.codice_ordine) === codice; });
  if (!ordine) throw new Error('Scegli una prenotazione valida di questo evento.');
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (item) { return String(item.codice_ordine) === codice; });
  const versato = Math.max(0, pagamenti.reduce(function (totale, item) { const importo = Math.max(0, Number(item.importo_centesimi) || 0); return totale + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -importo : importo); }, 0));
  const totale = Math.max(0, Number(ordine.totale_centesimi) || 0);
  const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (item) { return String(item.id_evento) === String(idEvento); });
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.ORDER_STATUS).setValue(normalizzaTesto_(ordine.stato, 40));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT).setValue(evento ? String(evento.titolo || '') : 'Evento ' + idEvento);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REFERENT).setValue([ordine.nome_referente, ordine.cognome_referente].filter(String).join(' '));
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.TOTAL_VALUE).setValue(totale / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.PAID_VALUE).setValue(versato / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.BALANCE_VALUE).setValue(Math.max(0, totale - versato) / 100).setNumberFormat('#,##0.00 [$€-it-IT]');
  applicaConfigurazioneInterfacciaMovimentoEvento_(sheet, ordine, totale, versato);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#f0f8f3').setFontColor('#19764a').setValue('Dati pronti. Completa importo e metodo, quindi usa il comando finale.');
  return { ordine: ordine, totale: totale, versato: versato };
}

function applicaConfigurazioneInterfacciaMovimentoEvento_(sheet, ordine, totale, versato) {
  const residuo = Math.max(0, totale - versato);
  const movimenti = [];
  if (residuo > 0 && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(ordine.stato || '').toUpperCase()) < 0) movimenti.push('INCASSO');
  if (versato > 0) movimenti.push('RIMBORSO', 'STORNO');
  const modalita = String(ordine.modalita_economica || '').toUpperCase();
  const rate = modalita === 'DEPOSIT_BALANCE' ? ['CAPARRA', 'INTERMEDIO', 'SALDO'] : ['INTERO'];
  let metodi = [];
  try { metodi = JSON.parse(String(ordine.fonti_pagamento_json || '[]')); } catch (error) { metodi = []; }
  const mappaMetodi = { BANK_TRANSFER: 'BONIFICO', TRANSFER: 'BONIFICO', BONIFICO: 'BONIFICO', CARD: 'CARTA', CARTA: 'CARTA', PAYPAL: 'CARTA', CASH: 'CONTANTE', CONTANTE: 'CONTANTE' };
  metodi = metodi.map(function (item) { return mappaMetodi[String(item).toUpperCase()] || ''; }).filter(function (item, index, elenco) { return item && elenco.indexOf(item) === index; });
  if (!metodi.length) metodi = MI_PAYMENT_ENUMS.paymentSources.slice();
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION), movimenti, 'Nessun movimento disponibile');
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT), rate, '');
  impostaElencoInterfacciaMovimentoEvento_(sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE), metodi, '');
  const comando = sheet.getRange(MI_EVENT_MOVEMENT_FORM.COMMAND).clearContent().clearDataValidations();
  if (movimenti.length) comando.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['REGISTRA MOVIMENTO'], true).setAllowInvalid(false).build()).setBackground('#1d4ed8').setFontColor('#ffffff');
  else comando.setBackground('#8b98aa').setFontColor('#ffffff');
}

function impostaElencoInterfacciaMovimentoEvento_(cella, valori, vuoto) {
  cella.clearDataValidations();
  if (!valori.length) { cella.setValue(vuoto || ''); return; }
  cella.setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(valori, true).setAllowInvalid(false).build());
  if (valori.indexOf(String(cella.getValue())) < 0) cella.setValue(valori[0]);
}

function registraMovimentoInterfacciaEvento_(foglio, sheet, idEvento) {
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#fff9e8').setFontColor('#9a6700').setValue('Registrazione in corso. Attendi: il movimento viene verificato e salvato una sola volta.');
  SpreadsheetApp.flush();
  const collegamento = trovaCollegamentoFoglioOperativo_(String(idEvento));
  if (String(collegamento.id_foglio) !== String(foglio.getId())) throw new Error('Questo file non è il foglio operativo registrato per l’evento.');
  const riepilogo = aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
  const risultato = registraPagamentoValidato_({
    intake_id: sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).getValue(), order_code: riepilogo.ordine.codice_ordine,
    transaction_kind: sheet.getRange(MI_EVENT_MOVEMENT_FORM.TRANSACTION).getValue(), installment_kind: sheet.getRange(MI_EVENT_MOVEMENT_FORM.INSTALLMENT).getValue(),
    effective_at: sheet.getRange(MI_EVENT_MOVEMENT_FORM.DATE).getValue(), amount: sheet.getRange(MI_EVENT_MOVEMENT_FORM.AMOUNT).getValue(),
    payment_source: sheet.getRange(MI_EVENT_MOVEMENT_FORM.SOURCE).getValue(), external_reference: sheet.getRange(MI_EVENT_MOVEMENT_FORM.REFERENCE).getValue(),
    operator_label: sheet.getRange(MI_EVENT_MOVEMENT_FORM.OPERATOR).getValue(), administrative_note: sheet.getRange(MI_EVENT_MOVEMENT_FORM.NOTE).getValue(), recording_channel: 'WORKSPACE_UI'
  });
  if (risultato.status !== 'CONVALIDATO') throw new Error(risultato.message);
  aggiornaProiezionePagamentiPrenotazioneEvento_(foglio, String(idEvento), String(riepilogo.ordine.codice_ordine));
  // La chiave completata non deve mai restare riutilizzabile se la pulizia del modulo fallisce a metà.
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.REQUEST_ID).setValue(creaIdentificativoOpaco_('pevui'));
  sheet.getRangeList([MI_EVENT_MOVEMENT_FORM.AMOUNT, MI_EVENT_MOVEMENT_FORM.REFERENCE, MI_EVENT_MOVEMENT_FORM.NOTE]).clearContent();
  aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
  sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#eaf5ef').setFontColor('#25745e').setValue(risultato.message + ' Lo storico Pagamenti è stato aggiornato.');
  foglio.toast('Movimento registrato e controllato.', 'Modulo iscrizioni', 5);
}

/** Trigger installabile: opera soltanto sulle due celle comando del modulo evento. */
function gestisciModificaInterfacciaMovimentoEvento(e) {
  if (!e || !e.source || !e.range || e.range.getSheet().getName() !== MI_EVENT_MOVEMENT_FORM.SHEET) return;
  const sheet = e.range.getSheet();
  const idEvento = String(sheet.getRange(MI_EVENT_MOVEMENT_FORM.EVENT_ID).getValue() || '');
  if (!idEvento) return;
  try {
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.ORDER) aggiornaRiepilogoInterfacciaMovimentoEvento_(sheet, idEvento);
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.COMMAND && String(e.value || '') === 'REGISTRA MOVIMENTO') registraMovimentoInterfacciaEvento_(e.source, sheet, idEvento);
  } catch (error) {
    sheet.getRange(MI_EVENT_MOVEMENT_FORM.STATUS).setBackground('#fff2f0').setFontColor('#b42318').setValue('Movimento non registrato. ' + normalizzaTesto_(error && error.message ? error.message : error, 270));
    e.source.toast('Movimento non registrato: controlla i dati.', 'Modulo iscrizioni', 6);
  } finally {
    if (e.range.getA1Notation() === MI_EVENT_MOVEMENT_FORM.COMMAND) sheet.getRange(MI_EVENT_MOVEMENT_FORM.COMMAND).clearContent();
  }
}

function assicuraTriggerInterfacciaMovimentoEvento_(foglio) {
  const presente = ScriptApp.getProjectTriggers().some(function (trigger) { return trigger.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(trigger.getTriggerSourceId() || '') === String(foglio.getId()); });
  if (!presente) ScriptApp.newTrigger('gestisciModificaInterfacciaMovimentoEvento').forSpreadsheet(foglio).onEdit().create();
  return !presente;
}

function rimuoviTriggerInterfacciaMovimentoEvento_(foglio) {
  ScriptApp.getProjectTriggers().forEach(function (trigger) {
    if (trigger.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(trigger.getTriggerSourceId() || '') === String(foglio.getId())) ScriptApp.deleteTrigger(trigger);
  });
}

/** Migrazione manuale e ripetibile dei file evento già esistenti; non registra movimenti. */
function preparaInterfacceMovimentiFogliEventi() {
  const collegamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(function (item) { return !!item.id_foglio; });
  return collegamenti.map(function (item) {
    try {
      const foglio = SpreadsheetApp.openById(String(item.id_foglio));
      const configurazione = configuraSchedeEconomicheEvento_(foglio, String(item.id_evento));
      return { id_evento: String(item.id_evento), id_foglio: String(item.id_foglio), pagamenti: configurazione.pagamenti, ok: true };
    } catch (error) {
      return { id_evento: String(item.id_evento), id_foglio: String(item.id_foglio), ok: false, errore: normalizzaTesto_(error && error.message ? error.message : error, 240) };
    }
  });
}


// Sorgente: PagamentiEvento.gs
function colonnePagamentiEvento_() {
  return [['_movimento', 'Identificativo movimento'], ['_registrato', 'Identificativo centrale'], ['data', 'Data'], ['ordine', 'Prenotazione'], ['tipo', 'Movimento'], ['importo', 'Importo (€)'], ['fonte', 'Modalità'], ['causale', 'Causale'], ['note', 'Note'], ['riferimento', 'Riferimento'], ['operatore', 'Operatore'], ['convalida', 'Convalida'], ['esito', 'Esito']];
}

/** Il registro locale è un ingresso esplicito e una proiezione del libro movimenti centrale. */
function preparaPagamentiEvento_(foglio, idEvento) {
  let scheda = foglio.getSheetByName('Pagamenti');
  if (!scheda) scheda = foglio.insertSheet('Pagamenti');
  let mappa = mappaColonneEvento_(scheda);
  const colonne = colonnePagamentiEvento_();
  if (!Object.keys(mappa).length) {
    if (scheda.getLastRow() > 0) throw new Error('La scheda Pagamenti esistente va verificata prima di collegarla.');
    if (scheda.getMaxColumns() < colonne.length) scheda.insertColumnsAfter(scheda.getMaxColumns(), colonne.length - scheda.getMaxColumns());
    scheda.getRange(1, 1, 1, colonne.length).setValues([colonne.map(function (campo) { return campo[1]; })]).setFontWeight('bold');
    colonne.forEach(function (campo, index) { identificaColonnaEvento_(scheda, index + 1, campo[0]); });
    scheda.setFrozenRows(1);
    scheda.hideColumns(1, 2);
    impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', idEvento);
    mappa = mappaColonneEvento_(scheda);
  }
  if (colonne.some(function (campo) { return !mappa[campo[0]]; })) throw new Error('Colonne Pagamenti mancanti: ripristinare gli identificativi.');
  const evento = scheda.getDeveloperMetadata().find(function (meta) { return meta.getKey() === 'MI_ID_EVENTO'; });
  if (!evento || String(evento.getValue()) !== String(idEvento)) throw new Error('Scheda Pagamenti collegata a un altro evento.');
  const righe = Math.max(1, scheda.getMaxRows() - 1);
  [['tipo', ['Incasso', 'Rimborso', 'Storno']], ['fonte', ['Contanti', 'Bonifico', 'Carta/PayPal']], ['causale', ['Intero', 'Caparra', 'Intermedio', 'Saldo', 'Non assegnato']]].forEach(function (campo) {
    scheda.getRange(2, mappa[campo[0]], righe, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(campo[1], true).setAllowInvalid(false).build());
  });
  scheda.getRange(2, mappa.convalida, righe, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireCheckbox().build());
  return scheda;
}

function payloadPagamentoEvento_(riga, mappa) {
  const leggi = function (key) { return riga[mappa[key] - 1]; };
  const fonti = { 'Contanti': 'CONTANTE', 'Bonifico': 'BONIFICO', 'Carta/PayPal': 'CARTA' };
  const causali = { 'Intero': 'INTERO', 'Caparra': 'CAPARRA', 'Intermedio': 'INTERMEDIO', 'Saldo': 'SALDO', 'Non assegnato': 'NON_ASSEGNATO' };
  return { intake_id: leggi('_movimento'), order_code: leggi('ordine'), transaction_kind: String(leggi('tipo') || '').toUpperCase(), installment_kind: causali[leggi('causale')] || '', effective_at: leggi('data'), amount: leggi('importo'), payment_source: fonti[leggi('fonte')] || '', administrative_note: leggi('note'), external_reference: leggi('riferimento'), operator_label: leggi('operatore'), recording_channel: 'MANUAL_SHEET' };
}

/** Ogni riga richiede la spunta Convalida. L'identificativo è persistito PRIMA del versamento. */
function acquisisciPagamentiEvento_(foglio, idEvento) {
  const scheda = preparaPagamentiEvento_(foglio, idEvento);
  const mappa = mappaColonneEvento_(scheda);
  const ordini = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (riga) { return String(riga.id_evento) === String(idEvento); });
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const visti = {};
    for (let numero = 2; numero <= scheda.getLastRow(); numero += 1) {
      const riga = scheda.getRange(numero, 1, 1, scheda.getLastColumn()).getValues()[0];
      const id = String(riga[mappa._movimento - 1] || '');
      if (id && visti[id]) throw new Error('Identificativo movimento duplicato nel foglio.');
      if (id) visti[id] = true;
    }
    for (let numero = 2; numero <= scheda.getLastRow(); numero += 1) {
      const riga = scheda.getRange(numero, 1, 1, scheda.getLastColumn()).getValues()[0];
      if (riga[mappa.convalida - 1] !== true) continue;
      if (riga[mappa._registrato - 1]) continue; // Un movimento acquisito è immutabile.
      if (riga[mappa._registrato - 1]) continue; // Un movimento acquisito è immutabile.
      const payload = payloadPagamentoEvento_(riga, mappa);
      if (!ordini.some(function (ordine) { return String(ordine.codice_ordine) === String(payload.order_code); })) {
        scheda.getRange(numero, mappa.esito).setValue('Prenotazione assente o appartenente a un altro evento.');
        continue;
      }
      // Date testuali ambigue (gg/mm o mm/gg) non sono interpretate arbitrariamente.
      if (!(payload.effective_at instanceof Date) || isNaN(payload.effective_at.getTime())) {
        scheda.getRange(numero, mappa.esito).setValue('Inserire una data valida nella cella Data.');
        continue;
      }
      if (!payload.intake_id) {
        payload.intake_id = creaIdentificativoOpaco_('pev');
        scheda.getRange(numero, mappa._movimento).setValue(payload.intake_id);
        SpreadsheetApp.flush();
      }
      const esito = registraPagamentoConLock_(payload);
      scheda.getRange(numero, mappa.esito).setValue(esito.message);
      if (esito.status === 'CONVALIDATO') {
        scheda.getRange(numero, mappa._registrato).setValue(esito.paymentId);
        scheda.getRange(numero, mappa.convalida).setValue(false);
      }
    }
    proiettaPagamentiEvento_(scheda, mappa, ordini);
  } finally { lock.releaseLock(); }
  return { ok: true };
}

function proiettaPagamentiEvento_(scheda, mappa, ordini, codiciOrdine) {
  const filtroOrdini = Array.isArray(codiciOrdine) && codiciOrdine.length ? codiciOrdine.reduce(function (indice, codice) {
    indice[String(codice)] = true;
    return indice;
  }, {}) : null;
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (movimento) {
    const codice = String(movimento.codice_ordine);
    return (!filtroOrdini || filtroOrdini[codice]) && ordini.some(function (ordine) { return String(ordine.codice_ordine) === codice; });
  });
  const fonti = { CONTANTE: 'Contanti', BONIFICO: 'Bonifico', CARTA: 'Carta/PayPal' };
  const causali = { INTERO: 'Intero', CAPARRA: 'Caparra', INTERMEDIO: 'Intermedio', SALDO: 'Saldo', NON_ASSEGNATO: 'Non assegnato' };
  const locali = {};
  const ultimaRiga = scheda.getLastRow();
  const righeEsistenti = ultimaRiga > 1 ? scheda.getRange(2, 1, ultimaRiga - 1, scheda.getLastColumn()).getValues() : [];
  for (let indiceRiga = 0; indiceRiga < righeEsistenti.length; indiceRiga += 1) {
    const numero = indiceRiga + 2;
    const riga = righeEsistenti[indiceRiga];
    if (filtroOrdini && !filtroOrdini[String(riga[mappa.ordine - 1] || '')]) continue;
    const origine = pagamenti.find(function (movimento) { return String(movimento.id_inserimento_origine) === String(riga[mappa._movimento - 1] || '') && !!riga[mappa._movimento - 1]; });
    const id = String(riga[mappa._registrato - 1] || (origine && origine.id_pagamento) || '');
    if (!id) continue;
    if (!riga[mappa._registrato - 1]) scheda.getRange(numero, mappa._registrato).setValue(id);
    if (locali[id]) throw new Error('Movimento centrale duplicato nel foglio.');
    locali[id] = numero;
    const centrale = pagamenti.find(function (movimento) { return String(movimento.id_pagamento) === id; });
    const coerente = centrale && pagamentoCorrisponde_(centrale, payloadPagamentoEvento_(riga, mappa));
    scheda.getRange(numero, mappa.esito).setValue(coerente ? 'Registrato in DB_MODULI' : 'Dati modificati dopo la registrazione: ripristinare i valori o registrare un nuovo storno/rimborso.');
  }
  pagamenti.forEach(function (movimento) {
    if (locali[String(movimento.id_pagamento)]) return;
    const valori = { _movimento: String(movimento.id_pagamento), _registrato: String(movimento.id_pagamento), data: movimento.data_effettiva, ordine: movimento.codice_ordine, tipo: String(movimento.tipo_movimento).charAt(0) + String(movimento.tipo_movimento).slice(1).toLowerCase(), importo: Number(movimento.importo_centesimi) / 100, fonte: fonti[movimento.fonte_pagamento], causale: causali[movimento.tipo_rata], note: movimento.nota_amministrativa || '', riferimento: movimento.riferimento_esterno || '', operatore: movimento.etichetta_operatore || '', convalida: false, esito: 'Registrato in DB_MODULI' };
    const riga = Array(scheda.getLastColumn()).fill('');
    Object.keys(valori).forEach(function (key) { const valore = valori[key]; riga[mappa[key] - 1] = typeof valore === 'string' ? neutralizzaFormula_(valore, 5000) : valore; });
    const numero = scheda.getLastRow() + 1;
    if (numero > scheda.getMaxRows()) scheda.insertRowsAfter(scheda.getMaxRows(), 1);
    scheda.getRange(numero, 1, 1, riga.length).setValues([riga]);
    locali[String(movimento.id_pagamento)] = numero;
  });
}

function aggiornaProiezionePagamentiPrenotazioneEvento_(foglio, idEvento, codiceOrdine) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const scheda = preparaPagamentiEvento_(foglio, idEvento);
    const ordini = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (riga) {
      return String(riga.id_evento) === String(idEvento) && String(riga.codice_ordine) === String(codiceOrdine);
    });
    if (ordini.length !== 1) throw new Error('Prenotazione non trovata oppure non appartenente a questo evento.');
    proiettaPagamentiEvento_(scheda, mappaColonneEvento_(scheda), ordini, [String(codiceOrdine)]);
  } finally { lock.releaseLock(); }
}

function aggiornaProiezionePagamentiEvento_(foglio, idEvento) {
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try {
    const scheda = preparaPagamentiEvento_(foglio, idEvento);
    const ordini = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (riga) { return String(riga.id_evento) === String(idEvento); });
    proiettaPagamentiEvento_(scheda, mappaColonneEvento_(scheda), ordini);
  } finally { lock.releaseLock(); }
}

/** Eseguibile dal menu centrale; ogni evento fallito resta recuperabile al giro successivo. */
function sincronizzaFogliEventi() {
  const collegamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(function (riga) { return !!riga.id_foglio; });
  if (!collegamenti.length) return [];
  const proprieta = PropertiesService.getScriptProperties();
  const inizio = Math.max(0, Number(proprieta.getProperty('MI_EVENT_SYNC_CURSOR')) || 0) % collegamenti.length;
  const scadenza = Date.now() + 180000;
  const risultati = [];
  for (let offset = 0; offset < collegamenti.length && Date.now() < scadenza; offset += 1) {
    const indice = (inizio + offset) % collegamenti.length;
    const riga = collegamenti[indice];
    // Avanzare prima dell'evento evita che un timeout sullo stesso file blocchi gli altri.
    proprieta.setProperty('MI_EVENT_SYNC_CURSOR', String((indice + 1) % collegamenti.length));
    try {
      const foglio = SpreadsheetApp.openById(String(riga.id_foglio));
      configuraSchedeEconomicheEvento_(foglio, String(riga.id_evento));
      if (eventoPrevedeMovimenti_(String(riga.id_evento))) acquisisciPagamentiEvento_(foglio, String(riga.id_evento));
      const risultato = aggiornaFoglioOperativoEvento({ id_evento: String(riga.id_evento) });
      risultati.push({ id_evento: String(riga.id_evento), ok: true, esito: risultato.esito });
    } catch (errore) {
      aggiungiControllo_('FOGLIO_OPERATIVO', 'RETRY', String(riga.id_evento), 'ERROR', 'SEGRETERIA', normalizzaTesto_(errore.message, 300), 'SEGRETERIA');
      risultati.push({ id_evento: String(riga.id_evento), ok: false });
    }
  }
  return risultati;
}

/** Da attivare una volta dal proprietario del progetto, dopo la distribuzione verificata. */
function attivaSincronizzazioneFogliEventi() {
  ottieniFoglioDiLavoroAssociato_();
  const esistente = ScriptApp.getProjectTriggers().some(function (trigger) { return trigger.getHandlerFunction() === 'sincronizzaFogliEventi'; });
  if (!esistente) ScriptApp.newTrigger('sincronizzaFogliEventi').timeBased().everyMinutes(5).create();
  return { ok: true, creato: !esistente };
}


// Sorgente: Payments.gs
function convalidaPagamentiSelezionati() {
  const sheet = SpreadsheetApp.getActiveSheet();
  if (!sheet || sheet.getName() !== MI_SHEETS.PAYMENT_INTAKE) throw new Error('Apri Inserimento pagamenti e seleziona le righe da convalidare.');
  const range = sheet.getActiveRange();
  const start = Math.max(2, range.getRow());
  convalidaRighePagamento_(start, range.getNumRows());
}

function convalidaPagamentiInAttesa() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
  if (sheet.getLastRow() < 2) return;
  convalidaRighePagamento_(2, sheet.getLastRow() - 1, true);
}

function convalidaRighePagamento_(startRow, rowCount, pendingOnly) {
    const intake = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
    const index = creaIndiceIntestazioni_(intake);
    const rows = intake.getRange(startRow, 1, rowCount, intake.getLastColumn()).getValues();
    rows.forEach(function (row, offset) {
      const rowNumber = startRow + offset;
      const currentStatus = normalizzaValoreElenco_(row[index.stato_convalida], ['IN_ATTESA', 'CONVALIDATO', 'RIFIUTATO', 'DA_VERIFICARE']);
      if (pendingOnly && currentStatus && currentStatus !== 'IN_ATTESA') return;
      const result = convalidaRigaPagamento_(row, index);
      intake.getRange(rowNumber, index.id_inserimento + 1).setValue(result.intakeId || row[index.id_inserimento]);
      intake.getRange(rowNumber, index.stato_convalida + 1).setValue(result.status);
      intake.getRange(rowNumber, index.messaggio_convalida + 1).setValue(result.message);
      intake.getRange(rowNumber, index.data_convalida + 1).setValue(new Date());
    });
    SpreadsheetApp.flush();
}

function convalidaRigaPagamento_(row, index) {
  return registraPagamentoValidato_({
    intake_id: row[index.id_inserimento],
    order_code: row[index.codice_ordine],
    transaction_kind: row[index.tipo_movimento],
    installment_kind: row[index.tipo_rata],
    effective_at: row[index.data_effettiva],
    amount: row[index.importo],
    payment_source: row[index.fonte_pagamento],
    external_reference: row[index.riferimento_esterno],
    operator_label: row[index.etichetta_operatore],
    administrative_note: row[index.nota_amministrativa],
    recording_channel: 'MANUAL_SHEET'
  });
}

function registraVersamentoSegreteria(form) {
  form = form || {};
  const activeOperator = normalizzaTesto_(Session.getActiveUser().getEmail(), 120);
  const result = registraPagamentoValidato_({
    intake_id: form.request_id,
    order_code: form.order_code,
    transaction_kind: 'INCASSO',
    installment_kind: form.installment_kind || 'NON_ASSEGNATO',
    effective_at: new Date(),
    amount: form.amount,
    payment_source: form.payment_source,
    external_reference: form.external_reference,
    operator_label: activeOperator || form.operator_label,
    administrative_note: form.administrative_note,
    recording_channel: 'WORKSPACE_UI'
  });
  if (result.status !== 'CONVALIDATO') throw new Error(result.message);
  return { ok: true, payment_id: result.paymentId, message: result.message };
}

function registraPagamentoValidato_(payload) {
  const lock = LockService.getScriptLock();
  lock.waitLock(5000);
  try { return registraPagamentoConLock_(payload); }
  finally { lock.releaseLock(); }
}

function pagamentoCorrisponde_(pagamento, payload) {
  const data = payload.effective_at instanceof Date ? payload.effective_at : new Date(payload.effective_at);
  const registrata = pagamento.data_effettiva instanceof Date ? pagamento.data_effettiva : new Date(pagamento.data_effettiva);
  return String(pagamento.codice_ordine) === String(payload.order_code)
    && String(pagamento.tipo_movimento) === String(payload.transaction_kind)
    && String(pagamento.tipo_rata) === String(payload.installment_kind)
    && Number(pagamento.importo_centesimi) === convertiEuroInCentesimi_(payload.amount)
    && String(pagamento.fonte_pagamento) === String(payload.payment_source)
    && registrata.getTime() === data.getTime()
    && String(pagamento.riferimento_esterno || '') === neutralizzaFormula_(payload.external_reference, 120)
    && String(pagamento.nota_amministrativa || '') === neutralizzaFormula_(payload.administrative_note, 500)
    && String(pagamento.etichetta_operatore || '') === neutralizzaFormula_(payload.operator_label, 100);
}

function registraPagamentoConLock_(payload) {
  payload = payload || {};
  const channel = ['MANUAL_SHEET', 'WORKSPACE_UI'].indexOf(String(payload.recording_channel)) >= 0 ? String(payload.recording_channel) : 'MANUAL_SHEET';
  const intakeId = normalizzaTesto_(payload.intake_id, 64) || creaIdentificativoOpaco_(channel === 'WORKSPACE_UI' ? 'pui' : 'pin');
  const orderCode = normalizzaTesto_(payload.order_code, 64);
  const transactionKind = normalizzaValoreElenco_(payload.transaction_kind, MI_PAYMENT_ENUMS.transactionKinds);
  const installmentKind = normalizzaValoreElenco_(payload.installment_kind, MI_PAYMENT_ENUMS.installmentKinds);
  const paymentSource = normalizzaValoreElenco_(payload.payment_source, MI_PAYMENT_ENUMS.paymentSources);
  const amountCents = convertiEuroInCentesimi_(payload.amount);
  const effectiveAt = payload.effective_at instanceof Date ? payload.effective_at : new Date(payload.effective_at);
  const operatorLabel = neutralizzaFormula_(payload.operator_label, 100);
  const externalReference = payload.external_reference;
  const administrativeNote = payload.administrative_note;
  const reject = function (code, message, status) {
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT_INTAKE', intakeId, 'REJECTED', operatorLabel, code, channel);
    return { intakeId: intakeId, status: status || 'RIFIUTATO', message: message };
  };

  if (!orderCode) return reject('ORDER_REQUIRED', 'Codice ordine obbligatorio.');
  if (!transactionKind) return reject('TRANSACTION_KIND', 'Tipo movimento non valido.');
  if (!installmentKind) return reject('INSTALLMENT_KIND', 'Tipo rata non valido.');
  if (!paymentSource) return reject('PAYMENT_SOURCE', 'Fonte pagamento non valida.');
  if (amountCents === null) return reject('AMOUNT', 'Importo non valido o non positivo.');
  if (!(effectiveAt instanceof Date) || isNaN(effectiveAt.getTime())) return reject('EFFECTIVE_AT', 'Data effettiva non valida.');
  if (paymentSource === 'CONTANTE' && !operatorLabel) return reject('CASH_OPERATOR', 'Operatore obbligatorio per i contanti.');
  if (contienePossibileNumeroCarta_(externalReference) || contienePossibileNumeroCarta_(administrativeNote)) return reject('CARD_DATA', 'Non inserire numeri completi di carta.');

  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (item) { return String(item.codice_ordine) === orderCode; });
  if (!registration) return reject('ORDER_NOT_FOUND', 'Ordine non trovato.');
  if (transactionKind === 'INCASSO' && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(registration.stato).toUpperCase()) >= 0) return reject('ORDER_REVIEW', 'Ordine da verificare manualmente.', 'DA_VERIFICARE');

    const paymentSheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS);
    const payments = convertiRigheInOggetti_(paymentSheet);
    const duplicate = payments.find(function (item) { return String(item.id_inserimento_origine) === intakeId; });
    if (duplicate) {
      if (!pagamentoCorrisponde_(duplicate, Object.assign({}, payload, { order_code: orderCode, transaction_kind: transactionKind, installment_kind: installmentKind, payment_source: paymentSource }))) return reject('PAYMENT_ID_CONFLICT', 'Movimento già acquisito con dati diversi: registrare uno storno o un rimborso su una nuova riga.', 'DA_VERIFICARE');
      return { intakeId: intakeId, paymentId: String(duplicate.id_pagamento || ''), status: 'CONVALIDATO', message: 'Movimento già acquisito.' };
    }
    const currentPaid = payments.filter(function (item) { return String(item.codice_ordine) === orderCode; }).reduce(function (total, item) {
      const amount = Math.max(0, Number(item.importo_centesimi) || 0);
      return total + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount);
    }, 0);
    const netPaid = Math.max(0, currentPaid);
    const orderTotal = Math.max(0, Number(registration.totale_centesimi) || 0);
    if (transactionKind === 'INCASSO' && orderTotal < 1) return reject('FREE_ORDER', 'L’evento non prevede pagamenti.', 'DA_VERIFICARE');
    if (transactionKind === 'INCASSO' && amountCents > Math.max(0, orderTotal - netPaid)) return reject('OVERPAYMENT', 'L’importo supera il saldo residuo.', 'DA_VERIFICARE');
    if (['RIMBORSO', 'STORNO'].indexOf(transactionKind) >= 0 && amountCents > netPaid) return reject('EXCESS_REFUND', 'Il rimborso o storno supera quanto versato.', 'DA_VERIFICARE');
    const paymentId = creaIdentificativoOpaco_('pay');
    paymentSheet.appendRow([
      paymentId,
      neutralizzaFormula_(orderCode, 64),
      transactionKind,
      installmentKind,
      effectiveAt,
      amountCents,
      'EUR',
      paymentSource,
      neutralizzaFormula_(externalReference, 120),
      operatorLabel,
      channel,
      intakeId,
      new Date(),
      neutralizzaFormula_(administrativeNote, 500)
    ]);
    aggiungiControllo_('VALIDATE_PAYMENT', 'PAYMENT', paymentId, 'SUCCESS', operatorLabel, 'PAYMENT_RECORDED', channel);
    return { intakeId: intakeId, paymentId: paymentId, status: 'CONVALIDATO', message: 'Versamento registrato e controllato.' };
}


// Sorgente: Report.gs
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
  const count = generaElencoOperativo_(eventId, columns, { ordinamento: model.ordinamento, raggruppamenti: model.raggruppamenti });
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

function decodificaConfigurazioneReport_(value) {
  try {
    const parsed = JSON.parse(String(value || '[]'));
    return Array.isArray(parsed) ? parsed.map(function (item) { return normalizzaTesto_(item, 64); }).filter(Boolean).slice(0, 30) : [];
  } catch (error) {
    return [];
  }
}


// Sorgente: Segreteria.gs
function apriSchedaPrenotazione() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'LISTA'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Segreteria eventi'));
}

function apriDialogoPrenotazione(orderCode) {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'PRENOTAZIONE'; template.codiceOrdineIniziale = normalizzaTesto_(orderCode, 64); template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showModelessDialog(template.evaluate().setWidth(680).setHeight(720), 'Scheda prenotazione');
  return { ok: true };
}

function apriConfigurazioneElencoOperativo() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ELENCO'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Elenco operativo'));
}

function apriAssegnazioniEvento() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ASSEGNAZIONI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Assegnazioni collettive'));
}

function apriConfigurazioneModelliReport() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'REPORT'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Modelli report'));
}

function apriGestioneGruppi() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'GRUPPI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Gruppi'));
}

function apriComunicazioniOperative() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'COMUNICAZIONI'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Comunicazioni operative'));
}

function configuraEndpointWordPress() {
  const ui = SpreadsheetApp.getUi();
  const response = ui.prompt('Collegamento WordPress', 'Inserisci l’endpoint HTTPS /wp-json/modulo-iscrizioni/v1/workspace/commands. Resterà nelle proprietà private dello script.', ui.ButtonSet.OK_CANCEL);
  if (response.getSelectedButton() !== ui.Button.OK) return;
  const url = normalizzaTesto_(response.getResponseText(), 500);
  if (!/^https:\/\/[^\s]+\/wp-json\/modulo-iscrizioni\/v1\/workspace\/commands\/?$/.test(url)) throw new Error('Endpoint WordPress non valido.');
  PropertiesService.getScriptProperties().setProperty('MI_WORDPRESS_COMMAND_URL', url);
  ui.alert('Collegamento WordPress salvato.');
}

function caricaContestoSegreteria() {
  const events = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).map(function (row) { return { id: String(row.id_evento || ''), title: String(row.titolo || row.id_evento || ''), status: String(row.stato || '') }; });
  const groups = elencaGruppi();
  const views = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS)).reduce(function (result, row) { try { result[String(row.id_evento)] = JSON.parse(String(row.campi_json || '[]')); } catch (error) { result[String(row.id_evento)] = []; } return result; }, {});
  return { events: events, groups: groups, views: views, report_models: elencaModelliReport(), available_fields: campiElencoOperativo_(), active_operator: normalizzaTesto_(Session.getActiveUser().getEmail(), 120), email_mode: String(ottieniConfigurazione_('modalita_email', 'ANTEPRIMA')).toUpperCase() };
}

function cercaPrenotazioniSegreteria(form) {
  form = form || {};
  const query = normalizzaTesto_(form.query, 120).toLowerCase();
  const eventId = normalizzaTesto_(form.event_id, 40);
  const paymentFilter = normalizzaTesto_(form.payment_status, 40).toUpperCase();
  const roomFilter = normalizzaTesto_(form.room, 80).toLowerCase();
  const limit = Math.min(50, Math.max(10, Math.round(Number(form.limit) || 30)));
  const events = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).reduce(function (result, row) { result[String(row.id_evento)] = row; return result; }, {});
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return !eventId || String(row.id_evento) === eventId; });
  const byOrder = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = row; return result; }, {});
  const paidByOrder = calcolaVersatoPerOrdine_(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)));
  const operational = indiceStatoOperativo_();
  const items = [];
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).slice().reverse().forEach(function (participant) {
    const orderCode = String(participant.codice_ordine || ''); const registration = byOrder[orderCode]; if (!registration) return;
    const personNumber = Number(participant.numero_partecipante) || 0;
    const fields = datiOperativiPartecipante_(participant, operational[orderCode + '|' + personNumber] || {});
    const room = String(fields.room || fields.camera || fields.alloggio || '');
    const payment = statoPagamento_(registration, paidByOrder[orderCode] || 0);
    const searchable = [orderCode, participant.nome, participant.cognome, registration.nome_referente, registration.cognome_referente].join(' ').toLowerCase();
    const roomDoesNotMatch = roomFilter === 'non assegnata' ? !!room : (roomFilter && room.toLowerCase().indexOf(roomFilter) < 0);
    if ((query && searchable.indexOf(query) < 0) || (paymentFilter && payment.code !== paymentFilter) || roomDoesNotMatch) return;
    items.push({ order_code: orderCode, participant_number: personNumber, first_name: String(participant.nome || ''), last_name: String(participant.cognome || ''), event_id: String(registration.id_evento || ''), event_title: String((events[String(registration.id_evento)] || {}).titolo || registration.id_evento || ''), registration_status: String(registration.stato || ''), payment_status: payment, room: room, participant_status: String(participant.stato_partecipante || 'ACTIVE') });
  });
  return { items: items.slice(0, limit), total: items.length, has_more: items.length > limit };
}

function caricaSchedaPrenotazione(orderCode) {
  orderCode = normalizzaTesto_(orderCode, 64);
  const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (row) { return String(row.codice_ordine) === orderCode; });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(registration.id_evento); }) || {};
  const operational = indiceStatoOperativo_();
  const participants = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; }).map(function (row) {
    const number = Number(row.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(row, operational[orderCode + '|' + number] || {}); const room = String(fields.room || fields.camera || fields.alloggio || '');
    delete fields.room; delete fields.camera; delete fields.alloggio;
    return { number: number, first_name: String(row.nome || ''), last_name: String(row.cognome || ''), ticket_type: String(row.codice_tipologia || ''), status: String(row.stato_partecipante || 'ACTIVE'), room: room, fields: fields, options: decodificaElenco_(row.opzioni_json) };
  });
  const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (row) { return String(row.codice_ordine) === orderCode; });
  const netPaid = calcolaVersatoPerOrdine_(payments)[orderCode] || 0;
  return { order_code: orderCode, event_id: String(registration.id_evento || ''), event_title: String(event.titolo || registration.id_evento || ''), status: String(registration.stato || ''), payment_status: statoPagamento_(registration, netPaid), created_at: registration.data_creazione, buyer: { first_name: String(registration.nome_referente || ''), last_name: String(registration.cognome_referente || ''), email: String(registration.email_referente || ''), phone: String(registration.telefono_referente || '') }, special_requests: String(registration.richieste_particolari || ''), total_cents: Number(registration.totale_centesimi) || 0, deposit_cents: Number(registration.primo_versamento_centesimi) || 0, paid_cents: netPaid, balance_cents: Math.max(0, (Number(registration.totale_centesimi) || 0) - netPaid), participants: participants, accommodations: elencaSistemazioniDisponibili_(String(registration.id_evento || '')), active_operator: normalizzaTesto_(Session.getActiveUser().getEmail(), 120) };
}

function salvaModifichePrenotazione(form) {
  form = form || {}; const orderCode = normalizzaTesto_(form.order_code, 64); const changes = Array.isArray(form.changes) ? form.changes.slice(0, 100) : [];
  if (!orderCode || !changes.length) throw new Error('Nessuna modifica da confermare.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  changes.forEach(function (change) {
    const participantNumber = Math.max(0, Math.round(Number(change.participant_number) || 0)); const key = normalizzaTesto_(change.key, 80); const value = normalizzaTesto_(change.value, 1000);
    if (!key) return;
    if (['room', 'camera', 'alloggio'].indexOf(key.toLowerCase()) >= 0) throw new Error('La sistemazione deve essere modificata con il selettore dedicato.');
    registraOperazioneSegreteria_(orderCode, participantNumber, 'UPDATE_FIELD', { key: key, value: value }, form.reason, operator, 'Modifica confermata dalla scheda prenotazione.');
  });
  aggiungiControllo_('BOOKING_UPDATE', 'REGISTRATION', orderCode, 'SUCCESS', operator, String(changes.length), 'WORKSPACE_UI');
  return { ok: true, message: 'Modifiche confermate e registrate nello storico.' };
}

function cambiaSistemazioneSegreteria(form) {
  form = form || {}; const orderCode = normalizzaTesto_(form.order_code, 64); const participantNumber = Math.max(0, Math.round(Number(form.participant_number) || 0)); const roomCode = normalizzaTesto_(form.room_code, 80);
  if (!orderCode || !participantNumber || !roomCode) throw new Error('Partecipante o sistemazione non validi.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120); const lock = LockService.getDocumentLock(); lock.waitLock(5000);
  try {
    const registration = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).find(function (row) { return String(row.codice_ordine) === orderCode; });
    const participant = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber; });
    if (!registration || !participant || String(participant.stato_partecipante || 'ACTIVE').toUpperCase() === 'CANCELLED') throw new Error('Partecipante non disponibile.');
    const selected = elencaSistemazioniDisponibili_(String(registration.id_evento || '')).find(function (room) { return room.code === roomCode; });
    const currentFields = datiOperativiPartecipante_(participant, indiceStatoOperativo_()[orderCode + '|' + participantNumber] || {}); const currentRoom = String(currentFields.room || currentFields.camera || currentFields.alloggio || '');
    if (!selected) throw new Error('Sistemazione non disponibile per questo evento.');
    if (selected.available < 1 && currentRoom !== roomCode) throw new Error('La sistemazione selezionata è al completo.');
    registraOperazioneSegreteria_(orderCode, participantNumber, 'CHANGE_ACCOMMODATION', { key: 'room', value: roomCode, previous: currentRoom }, form.reason, operator, 'Sistemazione aggiornata con controllo capienza.');
    aggiungiControllo_('CHANGE_ACCOMMODATION', 'PARTICIPANT', orderCode + ':' + participantNumber, 'SUCCESS', operator, roomCode, 'WORKSPACE_UI');
    return { ok: true, message: 'Sistemazione aggiornata.' };
  } finally { lock.releaseLock(); }
}

function configuraElencoOperativo(form) {
  form = form || {}; const eventId = normalizzaTesto_(form.event_id, 40); const allowed = campiElencoOperativo_().map(function (field) { return field.key; });
  const fields = (Array.isArray(form.fields) ? form.fields : []).map(function (field) { return normalizzaTesto_(field, 80); }).filter(function (field, index, list) { return allowed.indexOf(field) >= 0 && list.indexOf(field) === index; });
  if (!eventId || !fields.length) throw new Error('Scegli un evento e almeno una colonna.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120); const settings = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS); const existing = convertiRigheInOggetti_(settings).find(function (row) { return String(row.id_evento) === eventId; }); const values = [eventId, JSON.stringify(fields), new Date(), neutralizzaFormula_(operator, 120)];
  if (existing) settings.getRange(existing._row, 1, 1, values.length).setValues([values]); else settings.appendRow(values);
  const count = generaElencoOperativo_(eventId, fields);
  return { ok: true, count: count, sheet_name: MI_SHEETS.OPERATIONAL_LIST, print_url: creaUrlStampaElenco_(), message: 'Elenco aggiornato con ' + count + ' partecipanti attivi.' };
}

function generaElencoOperativo_(eventId, fields, options) {
	options = options || {};
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_LIST); const event = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(function (row) { return String(row.id_evento) === String(eventId); }) || {};
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return String(row.id_evento) === String(eventId) && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0; }); const byOrder = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = row; return result; }, {});
  const operational = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE)); const payments = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)); const labels = campiElencoOperativo_().reduce(function (result, field) { result[field.key] = field.label; return result; }, {});
  const rows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return !!byOrder[String(row.codice_ordine)] && String(row.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED'; }).map(function (participant) { const registration = byOrder[String(participant.codice_ordine)]; const data = decodificaOggetto_(participant.dati_aggiuntivi_json); operational.filter(function (state) { return String(state.codice_ordine) === String(participant.codice_ordine) && Number(state.numero_partecipante) === Number(participant.numero_partecipante); }).forEach(function (state) { data[String(state.chiave)] = state.valore; }); return fields.map(function (field) { return neutralizzaFormula_(valoreCampoElenco_(field, event, registration, participant, data, payments), 1000); }); });
	const grouping = normalizzaScelteReport_(options.raggruppamenti, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const ordering = normalizzaScelteReport_(options.ordinamento, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const sortColumns = grouping.concat(ordering).filter(function (column, index, list) { return list.indexOf(column) === index; });
	if (sortColumns.length) rows.sort(function (left, right) { for (let index = 0; index < sortColumns.length; index += 1) { const column = sortColumns[index]; const comparison = String(left[column] == null ? '' : left[column]).localeCompare(String(right[column] == null ? '' : right[column]), 'it', { numeric: true, sensitivity: 'base' }); if (comparison) return comparison; } return 0; });
  sheet.clear(); sheet.getRange(1, 1, 1, fields.length).merge().setValue('Elenco operativo — ' + String(event.titolo || eventId)).setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(14); sheet.getRange(2, 1, 1, fields.length).setValues([fields.map(function (field) { return labels[field] || field; })]).setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  if (rows.length) sheet.getRange(3, 1, rows.length, fields.length).setValues(rows).setWrap(true).setVerticalAlignment('middle');
  if (grouping.length && rows.length) rows.forEach(function (row, index) { const previous = index ? rows[index - 1] : null; const startsGroup = !previous || grouping.some(function (column) { return String(row[column]) !== String(previous[column]); }); if (startsGroup) sheet.getRange(index + 3, 1, 1, fields.length).setBorder(true, null, null, null, null, null, '#17224a', SpreadsheetApp.BorderStyle.SOLID_MEDIUM); });
  sheet.setFrozenRows(2); sheet.setHiddenGridlines(true); sheet.autoResizeColumns(1, fields.length); for (let column = 1; column <= fields.length; column += 1) sheet.setColumnWidth(column, Math.min(210, Math.max(90, sheet.getColumnWidth(column)))); sheet.getRange(1, 1, Math.max(2, rows.length + 2), fields.length).setBorder(true, true, true, true, true, true, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID);
  return rows.length;
}

function preparaComunicazioneOperativa(form) {
  form = form || {}; const eventId = normalizzaTesto_(form.event_id, 40); const templateType = normalizzaValoreElenco_(form.template_type, ['PRE_DEPARTURE_REMINDER', 'BALANCE_REMINDER']); const message = normalizzaTesto_(form.message, 4000);
  if (!eventId || !templateType) throw new Error('Scegli evento e tipo di comunicazione.');
  if (templateType === 'PRE_DEPARTURE_REMINDER' && !message) throw new Error('Scrivi le informazioni operative da comunicare.');
  const recipients = destinatariComunicazioneOperativa_(eventId, templateType);
  if (!recipients.length) return { ok: true, count: 0, mode: String(ottieniConfigurazione_('modalita_email', 'ANTEPRIMA')).toUpperCase(), message: 'Nessun destinatario corrisponde ai criteri.' };
  const communicationId = normalizzaTesto_(form.request_id, 64) || creaIdentificativoOpaco_('com');
  const result = inviaComandoWordPress_('QUEUE_OPERATIONAL_EMAILS', { communication_id: communicationId, event_id: eventId, template_type: templateType, message: message, recipients: recipients, allow_operational: form.allow_operational === true });
  return { ok: true, count: Number(result.count) || 0, mode: String(result.mode || 'ANTEPRIMA'), message: String(result.message || 'Comunicazione preparata.') };
}

function statoComunicazioniOperative() {
  const result = inviaComandoWordPress_('GET_EMAIL_MODE', {});
  return { mode: String(result.mode || 'ANTEPRIMA').toUpperCase() };
}

function destinatariComunicazioneOperativa_(eventId, templateType) {
  const paidByOrder = calcolaVersatoPerOrdine_(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)));
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (registration) {
    return String(registration.id_evento) === String(eventId) && ['CONFERMATA', 'IN_ATTESA_PAGAMENTO', 'CONFIRMED', 'PENDING_PAYMENT'].indexOf(String(registration.stato).toUpperCase()) >= 0;
  }).map(function (registration) {
    const orderCode = normalizzaTesto_(registration.codice_ordine, 64); const total = Math.max(0, Number(registration.totale_centesimi) || 0); const paid = Math.max(0, Number(paidByOrder[orderCode]) || 0); const balance = Math.max(0, total - paid);
    return { order_code: orderCode, paid_cents: paid, balance_cents: balance };
  }).filter(function (recipient) {
    return recipient.order_code && (templateType !== 'BALANCE_REMINDER' || recipient.balance_cents > 0);
  }).slice(0, 1000);
}

function inviaComandoWordPress_(action, payload) {
  const properties = PropertiesService.getScriptProperties(); const url = String(properties.getProperty('MI_WORDPRESS_COMMAND_URL') || '').trim(); if (!/^https:\/\//.test(url)) throw new Error('Configura prima il collegamento WordPress dal menu Modulo iscrizioni.');
  const timestamp = Date.now(); const nonce = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, ''); const message = timestamp + '\n' + nonce + '\n' + action + '\n' + serializzaInModoStabile_(payload || {}); const signature = Utilities.base64EncodeWebSafe(Utilities.computeHmacSha256Signature(message, ottieniSegretoScript_())).replace(/=+$/, '');
  const response = UrlFetchApp.fetch(url, { method: 'post', contentType: 'application/json', payload: JSON.stringify({ timestamp: timestamp, nonce: nonce, action: action, payload: payload || {}, signature: signature }), muteHttpExceptions: true }); const status = response.getResponseCode(); let body = {}; try { body = JSON.parse(response.getContentText() || '{}'); } catch (error) {}
  if (status < 200 || status >= 300 || body.ok === false) throw new Error(String(body.message || body.error || 'WordPress non ha accettato la richiesta.')); return body;
}

function registraOperazioneSegreteria_(orderCode, participantNumber, operationType, data, reason, operator, message) {
  const operationId = creaIdentificativoOpaco_('op');
  ottieniSchedaObbligatoria_(MI_SHEETS.SECRETARY_OPERATIONS).appendRow([operationId, new Date(), neutralizzaFormula_(orderCode, 64), participantNumber, operationType, JSON.stringify(data || {}), neutralizzaFormula_(reason, 500), neutralizzaFormula_(operator, 120), 'APPLICATA', message, new Date()]);
  if (data && data.key) aggiornaStatoOperativo_(orderCode, participantNumber, String(data.key), String(data.value == null ? '' : data.value), operator, operationId);
  return operationId;
}

function indiceStatoOperativo_() {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE)).reduce(function (result, row) { const id = String(row.codice_ordine) + '|' + Number(row.numero_partecipante || 0); result[id] = result[id] || {}; result[id][String(row.chiave)] = row.valore; return result; }, {});
}

function datiOperativiPartecipante_(participant, overrides) {
  const fields = decodificaOggetto_(participant.dati_aggiuntivi_json); Object.keys(overrides || {}).forEach(function (key) { fields[key] = overrides[key]; }); return fields;
}

function elencaSistemazioniDisponibili_(eventId) {
  const accommodationsSheet = ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS);
  let rooms = convertiRigheInOggetti_(accommodationsSheet).filter(function (row) { return String(row.id_evento) === String(eventId) && ['0', 'NO', 'FALSE', 'INATTIVA'].indexOf(String(row.attiva).toUpperCase()) < 0; });
  if (!rooms.length && eventId) {
    [['SINGOLA','Camera singola',1],['DOPPIA','Camera doppia',2],['TRIPLA','Camera tripla',3],['MULTIPLA','Camera multipla',8]].forEach(function (item) {
      accommodationsSheet.appendRow([String(eventId), item[0], item[1], item[2], 'SI', 'Opzione dimostrativa predefinita']);
    });
    rooms = convertiRigheInOggetti_(accommodationsSheet).filter(function (row) { return String(row.id_evento) === String(eventId); });
  }
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) { return String(row.id_evento) === String(eventId) && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0; }); const allowedOrders = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = true; return result; }, {}); const operational = indiceStatoOperativo_(); const occupied = {};
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).forEach(function (participant) { const orderCode = String(participant.codice_ordine || ''); if (!allowedOrders[orderCode] || String(participant.stato_partecipante || 'ACTIVE').toUpperCase() === 'CANCELLED') return; const number = Number(participant.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(participant, operational[orderCode + '|' + number] || {}); const code = String(fields.room || fields.camera || fields.alloggio || ''); if (code) occupied[code] = (occupied[code] || 0) + 1; });
  return rooms.map(function (room) { const code = String(room.codice || ''); const capacity = Math.max(0, Math.round(Number(room.capienza) || 0)); const used = occupied[code] || 0; return { code: code, name: String(room.nome || code), capacity: capacity, occupied: used, available: Math.max(0, capacity - used) }; });
}

function calcolaVersatoPerOrdine_(payments) {
  return (payments || []).reduce(function (result, row) { const code = String(row.codice_ordine || ''); const amount = Number(row.importo_centesimi) || 0; result[code] = (result[code] || 0) + (['RIMBORSO', 'STORNO'].indexOf(String(row.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); return result; }, {});
}

function statoPagamento_(registration, paid) {
  const total = Math.max(0, Number(registration.totale_centesimi) || 0); const deposit = Math.max(0, Number(registration.primo_versamento_centesimi) || 0); const balance = Math.max(0, total - paid);
  if (!total) return { code: 'GRATUITO', label: 'Gratuito', paid_cents: paid, balance_cents: 0 };
  if (paid >= total) return { code: 'SALDATO', label: 'Saldato', paid_cents: paid, balance_cents: 0 };
  if (paid >= deposit && deposit > 0) return { code: 'CAPARRA_RICEVUTA', label: 'Caparra ricevuta', paid_cents: paid, balance_cents: balance };
  if (paid > 0) return { code: 'PARZIALE', label: 'Versamento parziale', paid_cents: paid, balance_cents: balance };
  return { code: deposit > 0 ? 'CAPARRA_DOVUTA' : 'DA_PAGARE', label: deposit > 0 ? 'Caparra dovuta' : 'Da pagare', paid_cents: 0, balance_cents: balance };
}

function creaUrlStampaElenco_() {
  const spreadsheet = ottieniFoglioDiLavoroAssociato_(); const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_LIST);
  return 'https://docs.google.com/spreadsheets/d/' + encodeURIComponent(spreadsheet.getId()) + '/export?format=pdf&gid=' + sheet.getSheetId() + '&size=A4&portrait=false&fitw=true&sheetnames=false&printtitle=false&pagenumbers=true&gridlines=false&fzr=true';
}

/** Restituisce la vista quotidiana più adatta ai dati realmente raccolti dall'evento. */
function caricaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  if (!form.forza_generazione) {
    const vistaConservata = leggiVistaOperativaConservata_(idEvento);
    if (vistaConservata) return vistaConservata;
  }
  return generaVistaOperativaEvento_(idEvento);
}

/** Approva la vista dimostrativa e la conserva in una scheda dedicata all'evento. */
function approvaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const vista = generaVistaOperativaEvento_(idEvento);
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

/** Aggiorna soltanto i dati, mantenendo le colonne già approvate. */
function aggiornaDatiVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const precedente = leggiVistaOperativaConservata_(idEvento);
  if (!precedente) throw new Error('Approva prima la vista dimostrativa.');
  const vista = generaVistaOperativaEvento_(idEvento, precedente.colonne.map(function (colonna) { return colonna.key; }));
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

/** Rigenera modello e colonne, quindi sostituisce la vista conservata. */
function rigeneraStrutturaVistaOperativaEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const vista = generaVistaOperativaEvento_(idEvento);
  salvaVistaOperativaConservata_(vista);
  return leggiVistaOperativaConservata_(idEvento);
}

function generaVistaOperativaEvento_(idEvento, campiForzati) {
  const eventi = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS));
  const evento = eventi.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!evento) throw new Error('Evento non trovato.');
  const iscrizioni = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (riga) {
    return String(riga.id_evento) === idEvento && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(riga.stato).toUpperCase()) < 0;
  });
  const iscrizioniPerCodice = iscrizioni.reduce(function (indice, riga) { indice[String(riga.codice_ordine)] = riga; return indice; }, {});
  const partecipanti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (riga) {
    return !!iscrizioniPerCodice[String(riga.codice_ordine)] && String(riga.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED';
  });
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS));
  const statoOperativo = indiceStatoOperativo_();
  const rigaVistaSalvata = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_VIEWS)).find(function (riga) { return String(riga.id_evento) === idEvento; });
  let vistaSalvata = [];
  try { vistaSalvata = rigaVistaSalvata ? JSON.parse(String(rigaVistaSalvata.campi_json || '[]')) : []; } catch (errore) { vistaSalvata = []; }
  if (!Array.isArray(vistaSalvata)) vistaSalvata = [];
  const profilo = determinaProfiloVistaOperativa_(iscrizioni, partecipanti);
  const campi = Array.isArray(campiForzati) && campiForzati.length ? campiForzati : (vistaSalvata.length ? vistaSalvata : profilo.campi);
  const catalogo = campiElencoOperativo_().reduce(function (indice, campo) { indice[campo.key] = campo; return indice; }, {});
  const colonne = campi.filter(function (chiave) { return !!catalogo[chiave]; }).map(function (chiave) {
    return { key: chiave, label: catalogo[chiave].label, gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 };
  });
  const righe = partecipanti.map(function (partecipante) {
    const iscrizione = iscrizioniPerCodice[String(partecipante.codice_ordine)];
    const numero = Number(partecipante.numero_partecipante) || 0;
    const dati = datiOperativiPartecipante_(partecipante, statoOperativo[String(partecipante.codice_ordine) + '|' + numero] || {});
    const valori = {};
    colonne.forEach(function (colonna) { valori[colonna.key] = valoreCampoElenco_(colonna.key, evento, iscrizione, partecipante, dati, pagamenti); });
    return { codice_ordine: String(partecipante.codice_ordine), numero_partecipante: numero, valori: valori };
  });
  return { evento: { id: idEvento, titolo: String(evento.titolo || idEvento) }, profilo: profilo.id, nome_profilo: profilo.nome, personalizzata: !!vistaSalvata.length, conservata: false, colonne: colonne, righe: righe };
}

function nomeSchedaVistaOperativa_(idEvento) {
  const impronta = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, idEvento, Utilities.Charset.UTF_8).slice(0, 6).map(function (valore) { return ('0' + (valore & 255).toString(16)).slice(-2); }).join('');
  return 'Vista operativa ' + impronta;
}

function impostaMetadatoVista_(scheda, chiave, valore) {
  const metadato = scheda.getDeveloperMetadata().find(function (elemento) { return elemento.getKey() === chiave; });
  if (metadato) metadato.setValue(String(valore)); else scheda.addDeveloperMetadata(chiave, String(valore));
}

function salvaVistaOperativaConservata_(vista) {
  const foglio = ottieniFoglioDiLavoroAssociato_();
  const nome = nomeSchedaVistaOperativa_(vista.evento.id);
  let scheda = foglio.getSheetByName(nome);
  if (!scheda) scheda = foglio.insertSheet(nome);
  scheda.clear();
  const intestazioni = ['Codice prenotazione', 'Numero partecipante'].concat(vista.colonne.map(function (colonna) { return colonna.label; }));
  const righe = vista.righe.map(function (riga) {
    return [neutralizzaFormula_(riga.codice_ordine, 64), Number(riga.numero_partecipante) || 0].concat(vista.colonne.map(function (colonna) { return neutralizzaFormula_(riga.valori[colonna.key], 5000); }));
  });
  scheda.getRange(1, 1, 1, intestazioni.length).setValues([intestazioni]);
  if (righe.length) scheda.getRange(2, 1, righe.length, intestazioni.length).setValues(righe);
  scheda.setFrozenRows(1);
  scheda.hideColumns(1, 2);
  scheda.getRange(1, 1, 1, intestazioni.length).setFontWeight('bold').setBackground('#172554').setFontColor('#ffffff');
  scheda.autoResizeColumns(3, Math.max(1, intestazioni.length - 2));
  impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', vista.evento.id);
  impostaMetadatoVista_(scheda, 'MI_TITOLO_EVENTO', vista.evento.titolo);
  impostaMetadatoVista_(scheda, 'MI_PROFILO', vista.profilo);
  impostaMetadatoVista_(scheda, 'MI_NOME_PROFILO', vista.nome_profilo);
  impostaMetadatoVista_(scheda, 'MI_PERSONALIZZATA', vista.personalizzata ? '1' : '0');
  impostaMetadatoVista_(scheda, 'MI_CAMPI', JSON.stringify(vista.colonne.map(function (colonna) { return colonna.key; })));
  impostaMetadatoVista_(scheda, 'MI_DATA_AGGIORNAMENTO', new Date().toISOString());
}

function leggiVistaOperativaConservata_(idEvento) {
  const scheda = ottieniFoglioDiLavoroAssociato_().getSheetByName(nomeSchedaVistaOperativa_(idEvento));
  if (!scheda) return null;
  const metadati = scheda.getDeveloperMetadata().reduce(function (indice, elemento) { indice[elemento.getKey()] = elemento.getValue(); return indice; }, {});
  if (String(metadati.MI_ID_EVENTO || '') !== idEvento) return null;
  let campi = [];
  try { campi = JSON.parse(String(metadati.MI_CAMPI || '[]')); } catch (errore) { campi = []; }
  if (!Array.isArray(campi) || !campi.length) return null;
  const intestazioni = scheda.getRange(1, 1, 1, Math.max(2 + campi.length, 2)).getDisplayValues()[0];
  const colonne = campi.map(function (chiave, indice) { return { key: chiave, label: String(intestazioni[indice + 2] || chiave), gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 }; });
  const valori = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, 2 + colonne.length).getDisplayValues() : [];
  const righe = valori.map(function (riga) {
    const dati = {};
    colonne.forEach(function (colonna, indice) { dati[colonna.key] = riga[indice + 2]; });
    return { codice_ordine: String(riga[0] || ''), numero_partecipante: Number(riga[1]) || 0, valori: dati };
  });
  return { evento: { id: idEvento, titolo: String(metadati.MI_TITOLO_EVENTO || idEvento) }, profilo: String(metadati.MI_PROFILO || ''), nome_profilo: String(metadati.MI_NOME_PROFILO || 'Vista operativa'), personalizzata: String(metadati.MI_PERSONALIZZATA || '') === '1', conservata: true, data_aggiornamento: String(metadati.MI_DATA_AGGIORNAMENTO || ''), colonne: colonne, righe: righe };
}

function determinaProfiloVistaOperativa_(iscrizioni, partecipanti) {
  const profiliEspliciti = ['MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO'];
  let profiloEsplicito = '';
  iscrizioni.some(function (riga) {
    const istantanea = decodificaOggetto_(riga.snapshot_json);
    const candidato = normalizzaTesto_((istantanea.event || {}).operational_profile, 30).toUpperCase();
    if (profiliEspliciti.indexOf(candidato) < 0) return false;
    profiloEsplicito = candidato;
    return true;
  });
  let haDocumenti = false, haServizi = false;
  partecipanti.forEach(function (riga) {
    const dati = decodificaOggetto_(riga.dati_aggiuntivi_json);
    const opzioni = JSON.stringify(decodificaElenco_(riga.opzioni_json)).toLowerCase();
    if (dati.document_number || dati.numero_documento || dati.document_expiry_date || dati.scadenza_documento || dati.room || dati.camera || dati.alloggio) haDocumenti = true;
    if (dati.transport || dati.pullman || dati.lunch || dati.pranzo || /pullman|pranzo|colazione|cena/.test(opzioni)) haServizi = true;
  });
  const profili = {
    VIAGGIO_COMPLESSO: { id: 'VIAGGIO_COMPLESSO', nome: 'Viaggio complesso', campi: ['last_name', 'first_name', 'phone', 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality', 'transport', 'room', 'lunch', 'insurance', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    SERVIZI_MULTIPLI: { id: 'SERVIZI_MULTIPLI', nome: 'Gita con più servizi', campi: ['last_name', 'first_name', 'phone', 'transport', 'lunch', 'options', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    QUOTA_UNICA: { id: 'QUOTA_UNICA', nome: 'Evento con quota unica', campi: ['last_name', 'first_name', 'phone', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'] },
    MINIMO: { id: 'MINIMO', nome: 'Elenco minimo', campi: ['last_name', 'first_name', 'phone'] }
  };
  if (profiloEsplicito) return profili[profiloEsplicito];
  if (haDocumenti) return profili.VIAGGIO_COMPLESSO;
  if (haServizi) return profili.SERVIZI_MULTIPLI;
  if (iscrizioni.some(function (riga) { return Number(riga.totale_centesimi) > 0; })) return profili.QUOTA_UNICA;
  return profili.MINIMO;
}

function gruppoCampoVistaOperativa_(chiave) {
  if (['total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'].indexOf(chiave) >= 0) return 'pagamenti';
  if (['birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality'].indexOf(chiave) >= 0) return 'documenti';
  if (['room', 'transport', 'breakfast', 'lunch', 'insurance', 'options'].indexOf(chiave) >= 0) return 'servizi';
  return 'persona';
}

function campiElencoOperativo_(includiDinamici) {
  const fields = [
    { key: 'event', label: 'Evento' }, { key: 'order_code', label: 'Codice prenotazione' }, { key: 'participant_number', label: 'N.' }, { key: 'first_name', label: 'Nome' }, { key: 'last_name', label: 'Cognome' }, { key: 'status', label: 'Stato' },
    { key: 'email', label: 'Email' }, { key: 'phone', label: 'Cellulare' }, { key: 'birth_date', label: 'Data di nascita' }, { key: 'document_type', label: 'Tipo documento' }, { key: 'document_number', label: 'Numero documento' }, { key: 'document_issue_date', label: 'Data emissione documento' }, { key: 'document_expiry_date', label: 'Scadenza documento' }, { key: 'nationality', label: 'Nazionalità' }, { key: 'room', label: 'Alloggio' }, { key: 'transport', label: 'Pullman/trasporto' }, { key: 'breakfast', label: 'Colazione' },
    { key: 'lunch', label: 'Pranzo' }, { key: 'insurance', label: 'Assicurazione' }, { key: 'emergency_contact', label: 'Contatto di emergenza' }, { key: 'options', label: 'Altre opzioni' }, { key: 'total', label: 'Totale' }, { key: 'paid', label: 'Incassato' }, { key: 'paid_cash', label: 'Contanti' }, { key: 'paid_transfer', label: 'Bonifico' }, { key: 'paid_card', label: 'Carta/PayPal' }, { key: 'balance', label: 'Da incassare' }, { key: 'special_requests', label: 'Richieste particolari' }
  ];
	if (includiDinamici === false) return fields;
  const known = fields.reduce(function (result, field) { result[field.key] = true; return result; }, {});
  convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).forEach(function (participant) {
    const data = decodificaOggetto_(participant.dati_aggiuntivi_json);
    Object.keys(data).forEach(function (key) {
      if (known[key] || data[key] == null || String(data[key]).trim() === '' || !/^[A-Za-z0-9_-]{1,80}$/.test(key)) return;
      known[key] = true;
      const labels = { birth_date: 'Data di nascita', room: 'Alloggio', transport: 'Pullman/trasporto', breakfast: 'Colazione', lunch: 'Pranzo', insurance: 'Assicurazione' };
      fields.push({ key: key, label: labels[key] || key.replace(/[_-]+/g, ' ').replace(/^./, function (letter) { return letter.toUpperCase(); }) });
    });
  });
  return fields;
}

function valoreCampoElenco_(field, event, registration, participant, data, payments) {
  const aliases = { email: ['participant_email', 'email'], phone: ['participant_phone', 'phone', 'mobile'], birth_date: ['birth_date', 'data_nascita'], document_type: ['document_type', 'tipo_documento'], document_number: ['document_number', 'numero_documento'], document_issue_date: ['document_issue_date', 'data_emissione_documento'], document_expiry_date: ['document_expiry_date', 'document_expiry', 'scadenza_documento'], nationality: ['nationality', 'nazionalita'], room: ['room', 'camera', 'alloggio'], transport: ['pullman', 'transport'], breakfast: ['colazione', 'breakfast'], lunch: ['pranzo', 'lunch'], insurance: ['assicurazione', 'insurance'], emergency_contact: ['emergency_contact', 'emergency_phone', 'contatto_emergenza', 'telefono_emergenza'] };
  const direct = { event: event.titolo || registration.id_evento, order_code: registration.codice_ordine, participant_number: participant.numero_partecipante, first_name: participant.nome, last_name: participant.cognome, status: participant.stato_partecipante || registration.stato, special_requests: registration.richieste_particolari || '' };
  if (Object.prototype.hasOwnProperty.call(direct, field)) return direct[field];
  if (field === 'options') return decodificaElenco_(participant.opzioni_json).map(function (option) { return option.name || option.label || option.code || ''; }).filter(Boolean).join(', ');
  const pagamentiOrdine = payments.filter(function (payment) { return String(payment.codice_ordine) === String(registration.codice_ordine); });
  const sommaPagamenti = function (fonte) { return pagamentiOrdine.reduce(function (total, payment) { if (fonte && String(payment.fonte_pagamento).toUpperCase() !== fonte) return total; const amount = Number(payment.importo_centesimi) || 0; return total + (['RIMBORSO', 'STORNO'].indexOf(String(payment.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); }, 0); };
  const paid = sommaPagamenti('');
  if (field === 'total') return (Number(registration.totale_centesimi) || 0) / 100;
  if (field === 'paid') return paid / 100;
  if (field === 'paid_cash') return sommaPagamenti('CONTANTE') / 100;
  if (field === 'paid_transfer') return sommaPagamenti('BONIFICO') / 100;
  if (field === 'paid_card') return sommaPagamenti('CARTA') / 100;
  if (field === 'balance') return Math.max(0, (Number(registration.totale_centesimi) || 0) - paid) / 100;
  const candidates = aliases[field] || [field]; for (let index = 0; index < candidates.length; index += 1) if (data[candidates[index]] != null && data[candidates[index]] !== '') return data[candidates[index]];
  if (field === 'email') return registration.email_referente || ''; if (field === 'phone') return registration.telefono_referente || ''; return '';
}

function aggiornaStatoOperativo_(orderCode, participantNumber, key, value, operator, operationId) {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE); const existing = convertiRigheInOggetti_(sheet).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber && String(row.chiave) === key; }); const values = [neutralizzaFormula_(orderCode, 64), participantNumber, neutralizzaFormula_(key, 80), neutralizzaFormula_(value, 1000), new Date(), neutralizzaFormula_(operator, 120), operationId];
  if (existing) sheet.getRange(existing._row, 1, 1, values.length).setValues([values]); else sheet.appendRow(values);
}

function decodificaOggetto_(value) { try { const parsed = JSON.parse(String(value || '{}')); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}; } catch (error) { return {}; } }
function decodificaElenco_(value) { try { const parsed = JSON.parse(String(value || '[]')); return Array.isArray(parsed) ? parsed : []; } catch (error) { return []; } }


// Sorgente: Setup.gs
function onOpen() {
  SpreadsheetApp.getUi().createMenu('Modulo iscrizioni')
    .addItem('Inserisci pagamento · finestra', 'apriFinestraPagamenti')
    .addItem('Inizializza/aggiorna struttura', 'configuraCartellaDiLavoro')
    .addSeparator()
    .addItem('Assegna camere e pullman', 'apriAssegnazioniEvento')
    .addItem('Configura elenco operativo', 'apriConfigurazioneElencoOperativo')
    .addItem('Configura modelli report', 'apriConfigurazioneModelliReport')
    .addItem('Gestisci gruppi', 'apriGestioneGruppi')
    .addItem('Allinea gruppi con WordPress', 'sincronizzaGruppiConWordPress')
    .addItem('Configura collegamento WordPress', 'configuraEndpointWordPress')
    .addSeparator()
    .addItem('Apri iscrizione manuale', 'apriIscrizioneManuale')
    .addItem('Aggiorna campi iscrizione manuale', 'aggiornaSchemaIscrizioneManuale')
    .addSeparator()
    .addItem('Apri inserimento guidato', 'apriInserimentoMovimentoGuidato')
    .addItem('Aggiorna riepilogo movimento', 'aggiornaRiepilogoMovimentoGuidato')
    .addItem('Riallinea movimenti della prenotazione', 'aggiornaProiezioneMovimentoGuidato')
    .addItem('Registra movimento guidato', 'registraMovimentoGuidato')
    .addItem('Convalida pagamenti selezionati', 'convalidaPagamentiSelezionati')
    .addItem('Convalida tutti i pagamenti in attesa', 'convalidaPagamentiInAttesa')
    .addItem('Prepara moduli movimento nei fogli evento', 'preparaInterfacceMovimentiFogliEventi')
    .addItem('Sincronizza fogli e pagamenti degli eventi', 'sincronizzaFogliEventi')
    .addItem('Attiva sincronizzazione automatica eventi', 'attivaSincronizzazioneFogliEventi')
    .addSeparator()
    .addItem('Configura destinatario email di test', 'configuraDestinatarioTestEmail')
    .addItem('Invia coda al solo destinatario di test', 'inviaCodaEmailDiTest')
    .addToUi();
}

function configuraCartellaDiLavoro() {
  const lock = LockService.getDocumentLock();
  lock.waitLock(30000);
  try {
    const spreadsheet = ottieniFoglioDiLavoroAssociato_();
    PropertiesService.getScriptProperties().setProperty('MI_SPREADSHEET_ID', spreadsheet.getId());
    rinominaSchedePrecedenti_(spreadsheet);
    const existing = spreadsheet.getSheets();
    if (!spreadsheet.getSheetByName(MI_SHEETS.CONFIG) && existing.length === 1 && existing[0].getLastRow() <= 1 && existing[0].getLastColumn() <= 1) {
      existing[0].clear();
      existing[0].setName(MI_SHEETS.CONFIG);
    }

    Object.keys(MI_HEADERS).forEach(function (name) {
      const sheet = spreadsheet.getSheetByName(name) || spreadsheet.insertSheet(name);
      inizializzaScheda_(sheet, MI_HEADERS[name]);
    });

    inizializzaConfigurazione_();
		inizializzaGruppi_();
    inizializzaConvalidaPagamenti_();
		inizializzaInterfacciaMovimenti_();
		inizializzaInterfacciaIscrizioni_();
		inizializzaModelliReport_();
    applicaProtezioniConAvviso_();
    aggiungiControllo_('SETUP_WORKBOOK', 'WORKBOOK', 'BOUND', 'SUCCESS', Session.getActiveUser().getEmail(), MI_SCHEMA_VERSION, 'WORKSPACE_UI');
    SpreadsheetApp.flush();
    try {
      SpreadsheetApp.getUi().alert('Struttura aggiornata. Email e integrazione restano in modalità PREVIEW.');
    } catch (error) {
      console.log('Struttura aggiornata. Email e integrazione restano in modalità PREVIEW.');
    }
  } finally {
    lock.releaseLock();
  }
}

/** Crea i gruppi iniziali soltanto quando la scheda Gruppi non contiene dati. */
function inizializzaGruppi_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  if (sheet.getLastRow() > 1) return;
  const now = new Date();
  sheet.getRange(2, 1, 5, 7).setValues([
    ['parrocchia', 'Parrocchia', 'parrocchia', 'ATTIVO', '', '', now],
    ['12-ceste', '12 Ceste', '12-ceste', 'ATTIVO', '', '', now],
    ['icef', 'ICEF', 'icef', 'ATTIVO', '', '', now],
    ['escursioni', 'Escursioni', 'escursioni', 'ATTIVO', '', '', now],
    ['visite', 'Visite', 'visite', 'ATTIVO', '', '', now]
  ]);
}

/** Inserisce i modelli standard soltanto se il catalogo è ancora vuoto. */
function inizializzaModelliReport_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.REPORT_TEMPLATES);
  if (sheet.getLastRow() > 1) return;
  const modelli = [
    ['partecipanti', 'Elenco partecipanti', 'STANDARD', '', '["participant_number","last_name","first_name","email","phone","status"]', '["evento","stato_iscrizione","gruppo"]', '[]', '["last_name","first_name"]', 'SI', new Date(), 'SISTEMA'],
    ['documenti', 'Documenti e dati anagrafici', 'STANDARD', '', '["last_name","first_name","birth_date","document_type","document_number","document_issue_date","document_expiry_date","nationality"]', '["evento","documenti_mancanti"]', '[]', '["last_name","first_name"]', 'SI', new Date(), 'SISTEMA'],
    ['logistica', 'Camere e sistemazioni', 'STANDARD', '', '["last_name","first_name","room","special_requests"]', '["evento","sistemazione","camera"]', '["room"]', '["room","last_name","first_name"]', 'SI', new Date(), 'SISTEMA'],
    ['pullman', 'Assegnazione pullman', 'STANDARD', '', '["last_name","first_name","transport","phone"]', '["evento","pullman"]', '["transport"]', '["transport","last_name","first_name"]', 'SI', new Date(), 'SISTEMA'],
    ['pagamenti', 'Situazione pagamenti', 'STANDARD', '', '["order_code","last_name","first_name","total","paid","balance"]', '["evento","stato_pagamento","fonte_pagamento","data_versamento"]', '[]', '["last_name","first_name"]', 'SI', new Date(), 'SISTEMA']
  ];
  sheet.getRange(2, 1, modelli.length, modelli[0].length).setValues(modelli);
}

function configuraModelliReport() {
  inizializzaModelliReport_();
  SpreadsheetApp.getUi().alert('Modelli report pronti. La webapp potrà crearne di personalizzati senza alterare quelli standard.');
}

function rinominaSchedePrecedenti_(spreadsheet) {
  Object.keys(MI_LEGACY_SHEET_NAMES).forEach(function (oldName) {
    const newName = MI_LEGACY_SHEET_NAMES[oldName];
    const oldSheet = spreadsheet.getSheetByName(oldName);
    if (oldSheet && !spreadsheet.getSheetByName(newName)) oldSheet.setName(newName);
  });
}

function inizializzaScheda_(sheet, headers) {
  const current = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  const hasData = sheet.getLastRow() > 1;
  const previous = MI_LEGACY_HEADERS[sheet.getName()] || [];
	const usesPreviousHeaders = previous.length > 0 && current.slice(0, previous.length).join('|') === previous.join('|') && current.slice(previous.length).every(function (value) { return value === ''; });
	const italianPrevious = MI_INTESTAZIONI_PRECEDENTI[sheet.getName()] || [];
	const usesItalianPrevious = italianPrevious.length > 0 && current.slice(0, italianPrevious.length).join('|') === italianPrevious.join('|') && current.slice(italianPrevious.length).every(function (value) { return value === ''; });
	const immediatelyPrevious = sheet.getName() === MI_SHEETS.PARTICIPANTS ? headers.slice(0, -2) : ([MI_SHEETS.REGISTRATIONS, MI_SHEETS.PAYMENTS].indexOf(sheet.getName()) >= 0 ? headers.slice(0, -1) : []);
	const usesImmediatelyPrevious = immediatelyPrevious.length > 0 && current.slice(0, immediatelyPrevious.length).join('|') === immediatelyPrevious.join('|') && current.slice(immediatelyPrevious.length).every(function (value) { return value === ''; });
  if (hasData && current.join('|') !== headers.join('|') && !usesPreviousHeaders && !usesItalianPrevious && !usesImmediatelyPrevious) {
    throw new Error('Intestazioni inattese nel foglio ' + sheet.getName() + '. Intervento manuale richiesto.');
  }
  if (hasData && sheet.getName() === MI_SHEETS.PARTICIPANTS && (usesItalianPrevious || usesPreviousHeaders)) {
    const oldRows = sheet.getRange(2, 1, sheet.getLastRow() - 1, 5).getValues();
    const migratedRows = oldRows.map(function (row) {
      return [row[0], row[1], '', 0, row[2], row[3], row[4], '[]', 'ATTIVO', ''];
    });
    sheet.getRange(2, 1, migratedRows.length, headers.length).clearContent().setValues(migratedRows);
  }
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  sheet.setFrozenRows(1);
  sheet.setHiddenGridlines(true);
  const header = sheet.getRange(1, 1, 1, headers.length);
  header.setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  sheet.autoResizeColumns(1, headers.length);
  for (let column = 1; column <= headers.length; column += 1) {
    sheet.setColumnWidth(column, Math.min(220, Math.max(110, sheet.getColumnWidth(column))));
  }
  sheet.getRange(2, 1, Math.max(1, sheet.getMaxRows() - 1), headers.length).setVerticalAlignment('top');
}

function inizializzaConfigurazione_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.CONFIG);
	if (sheet.getLastRow() > 1) {
		const keys = sheet.getRange(2, 1, sheet.getLastRow() - 1, 1).getDisplayValues().map(function (row) { return String(row[0]); });
		let versionIndex = keys.indexOf('versione_schema');
		if (versionIndex < 0) versionIndex = keys.indexOf('schema_version');
		if (versionIndex >= 0) sheet.getRange(versionIndex + 2, 2).setValue(MI_SCHEMA_VERSION);
		return;
	}
  sheet.getRange(2, 1, 5, 3).setValues([
    ['versione_schema', MI_SCHEMA_VERSION, 'Versione della struttura Workspace'],
    ['ambiente', 'ANTEPRIMA', 'Anteprima finché il collaudo non è concluso'],
    ['fuso_orario', 'Europe/Rome', 'Fuso operativo'],
    ['valuta', 'EUR', 'Valuta degli importi'],
    ['modalita_email', 'ANTEPRIMA', 'Nessuna email reale in questa fase']
  ]);
}

function inizializzaConvalidaPagamenti_() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENT_INTAKE);
  const index = creaIndiceIntestazioni_(sheet);
  const rowCount = Math.max(1, sheet.getMaxRows() - 1);
  sheet.getRange(2, index.tipo_movimento + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.transactionKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.tipo_rata + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.installmentKinds, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.fonte_pagamento + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(MI_PAYMENT_ENUMS.paymentSources, true).setAllowInvalid(false).build());
  sheet.getRange(2, index.stato_convalida + 1, rowCount, 1).setDataValidation(SpreadsheetApp.newDataValidation().requireValueInList(['IN_ATTESA', 'CONVALIDATO', 'RIFIUTATO', 'DA_VERIFICARE'], true).setAllowInvalid(false).build());
  sheet.getRange(2, index.data_effettiva + 1, rowCount, 1).setNumberFormat('yyyy-mm-dd hh:mm');
  sheet.getRange(2, index.importo + 1, rowCount, 1).setNumberFormat('#,##0.00 [$€-it-IT]');
}

function applicaProtezioniConAvviso_() {
  const editable = [MI_SHEETS.PAYMENT_INTAKE, MI_SHEETS.SECRETARY_OPERATIONS, MI_SHEETS.OPERATIONAL_VIEWS, MI_SHEETS.ACCOMMODATIONS];
  Object.keys(MI_HEADERS).forEach(function (name) {
    const sheet = ottieniSchedaObbligatoria_(name);
    sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).forEach(function (protection) { protection.remove(); });
    if (editable.indexOf(name) < 0) sheet.protect().setDescription('Gestito da Modulo Iscrizioni').setWarningOnly(true);
  });
}


// Sorgente: WebApp.gs
function doGet(event) {
  return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
}

function doPost(event) {
  try {
    if (!event || !event.postData || !event.postData.contents) return creaRispostaJson_({ ok: false, error: 'EMPTY_PAYLOAD' });
    const envelope = JSON.parse(event.postData.contents);
    const verified = verificaBusta_(envelope);
    if (!verified.ok) return creaRispostaJson_({ ok: false, error: verified.error });
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
