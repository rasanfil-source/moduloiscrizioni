// Rilascio Workspace 3.26.136
// Sorgente: AccessoGestione.gs
/** URL ricavato dall'endpoint WordPress configurato, mai da dati inseriti nelle celle. */
function urlGestioneWeb_(vista, evento, ordine) {
  const endpoint = String(PropertiesService.getScriptProperties().getProperty('MI_WORDPRESS_COMMAND_URL') || '');
  const base = endpoint.replace(/\/wp-json\/modulo-iscrizioni\/v1\/workspace\/commands\/?$/, '/');
  if (base === endpoint || !/^https:\/\//.test(base)) throw new Error('Configura prima il collegamento WordPress.');
  return base + '?mi_portal=1&mi_portal_view=' + encodeURIComponent(vista || 'management') + (evento ? '&mi_portal_event=' + encodeURIComponent(evento) : '') + (ordine ? '&mi_order=' + encodeURIComponent(ordine) : '');
}
function apriGestioneWeb(vista) {
  const url = urlGestioneWeb_(vista);
  SpreadsheetApp.getUi().showModalDialog(HtmlService.createHtmlOutput('<p><a target="_blank" rel="noopener" href="' + url.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '">Apri la gestione eventi</a></p><p>Accedi con le tue credenziali WordPress.</p>').setWidth(430).setHeight(160), 'Gestione web');
}
function proteggiProiezione_(scheda, modificabili) {
  const protections = scheda.getProtections(SpreadsheetApp.ProtectionType.SHEET);
  const p = protections.find(function (item) { return item.getDescription() === 'MI_PROIEZIONE'; }) || scheda.protect().setDescription('MI_PROIEZIONE');
  p.setWarningOnly(false);
  p.addEditor(Session.getEffectiveUser());
  const me = Session.getEffectiveUser().getEmail();
  p.removeEditors(p.getEditors().filter(function (u) { return u.getEmail() !== me; }));
  if (p.canDomainEdit()) p.setDomainEdit(false);
  p.setUnprotectedRanges(modificabili || []);
}
function preparaAccessoGestioneEvento_(foglio, idEvento) {
  const scheda = foglio.getSheetByName('Gestione evento') || foglio.insertSheet('Gestione evento', 0);
  scheda.clear();
  scheda.getRange('A1').setValue('Gestione evento').setFontSize(24).setFontWeight('bold');
  scheda.getRange('A2').setValue('Modifica le celle azzurre in Dati operativi, poi sincronizza. I pagamenti si registrano dal portale.');
  try {
    scheda.getRange('A4').setRichTextValue(SpreadsheetApp.newRichTextValue().setText('Apri gestione e riepilogo evento').setLinkUrl(urlGestioneWeb_('management', idEvento)).build());
    scheda.getRange('A8').setRichTextValue(SpreadsheetApp.newRichTextValue().setText('SINCRONIZZA').setLinkUrl(urlGestioneWeb_('management', idEvento)+'&mi_sheet_sync=1').build()).setFontSize(22).setFontWeight('bold').setBackground('#174c78').setFontColor('#ffffff');
    scheda.setRowHeight(8,54);
    scheda.getRange('A9').setValue('Apri il riepilogo delle modifiche e conferma con il tuo accesso al portale.');
  } catch (e) { scheda.getRange('A4').setValue(e.message); }
  scheda.getRange('A6').setValue('Ultimo controllo automatico');
  scheda.getRange('B6').setValue(new Date()).setNumberFormat('dd/mm/yyyy hh:mm');
  scheda.setColumnWidth(1, 650);
  scheda.setColumnWidth(2, 220);
  scheda.getRange('A4').setFontSize(16).setFontWeight('bold').setWrap(true);
  scheda.getRange('A6:B6').setFontSize(16).setWrap(true);
  scheda.getRange('A9').setFontSize(16).setWrap(true);
  scheda.getRange('A1:B9').setVerticalAlignment('middle');
  scheda.getRange('A8').setHorizontalAlignment('center').setVerticalAlignment('middle');
  scheda.setRowHeight(1, 44);
  scheda.setRowHeight(4, 48);
  scheda.setRowHeight(6, 40);
  scheda.setRowHeight(9, 64);
  proteggiProiezione_(scheda);
}
/** Pending edits keep the current view intact until the operator synchronizes them. */
function scriviProiezioneEvento_(scheda, vista) {
  const precedenteProtezione = scheda.getProtections(SpreadsheetApp.ProtectionType.SHEET).find(p=>p.getDescription()==='MI_PROIEZIONE');
  const intervalliPrecedenti = precedenteProtezione ? precedenteProtezione.getUnprotectedRanges() : [];
  proteggiProiezione_(scheda);
  SpreadsheetApp.flush();
  try {
  allineaBaseConVista_(scheda, vista);
  const pending = modificheCorrentiFoglio_(scheda);
  if (pending.changes.length || pending.errors.length) {
    proteggiProiezione_(scheda, intervalliPrecedenti);
    return {aggiunte:0,manuali:pending.changes.length,conflitti:pending.errors.length};
  }
  scheda.createDeveloperMetadataFinder().withKey('MI_CAMPO').find().forEach(function (m) { m.remove(); });
  scheda.clear();
  scheda.getRange(1,1,scheda.getMaxRows(),scheda.getMaxColumns()).clearDataValidations();
  scheda.getRange(1,1,scheda.getMaxRows(),scheda.getMaxColumns()).breakApart();
  scheda.showColumns(1,scheda.getMaxColumns());
  const colonne = [{key:'_numero',label:'Partecipante'}].concat(vista.colonne, [{key:'_ordine',label:'Prenotazione'}]);
  if (scheda.getMaxColumns() < colonne.length) scheda.insertColumnsAfter(scheda.getMaxColumns(), colonne.length - scheda.getMaxColumns());
  const rows = vista.righe.map(function (r) { return [r.numero_partecipante].concat(vista.colonne.map(function (c) { return neutralizzaFormula_(r.valori[c.key], 5000); }), [r.codice_ordine]); });
  if (scheda.getMaxRows() < rows.length + 1) scheda.insertRowsAfter(scheda.getMaxRows(), rows.length + 1 - scheda.getMaxRows());
  scheda.getRange(1,1,1,colonne.length).setValues([colonne.map(function (c) {return c.label;})]).setFontWeight('bold');
  colonne.forEach(function (c,i) { identificaColonnaEvento_(scheda,i+1,c.key); });
  if (rows.length) scheda.getRange(2,1,rows.length,colonne.length).setValues(rows);
  scheda.setFrozenRows(1);
  const modificabili = [];
  colonne.forEach(function(c,i) {
    if (!vista.sola_lettura && campoModificabileFoglio_(c.key) && rows.length) {
      const range = scheda.getRange(2,i+1,rows.length,1);
      range.setNumberFormat('@').setBackground('#eef5fc');
      modificabili.push(range);
    }
  });
  salvaBaseFoglio_(scheda);
  proteggiProiezione_(scheda, modificabili);
  return { aggiunte:rows.length, manuali:0, conflitti:0 };
  } catch(error) {
    proteggiProiezione_(scheda, intervalliPrecedenti);
    throw error;
  }
}


// Sorgente: Config.gs
const MI_SCHEMA_VERSION = '1.13.0';
const MI_SHEETS = Object.freeze({
  CONFIG: 'Configurazione',
  GROUPS: 'Gruppi',
  EVENTS: 'Eventi',
  REGISTRATIONS: 'Iscrizioni',
  PARTICIPANTS: 'Partecipanti',
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
  REPLICA_REVISIONS: 'Revisioni replica',
  AUDIT_LOG: 'Registro controlli'
});

const MI_HEADERS = Object.freeze({
  'Configurazione': ['chiave', 'valore', 'descrizione'],
  'Gruppi': ['id_gruppo', 'nome', 'slug', 'stato', 'logo_url', 'immagine_url', 'data_aggiornamento'],
  'Eventi': ['id_evento', 'id_gruppo', 'titolo', 'stato', 'capienza', 'apertura_iscrizioni', 'chiusura_iscrizioni', 'modalita_prezzo', 'data_aggiornamento', 'servizi_json', 'domande_json', 'profilo_operativo', 'schema_vista_json'],
  'Iscrizioni': ['codice_ordine', 'id_evento', 'stato', 'nome_referente', 'cognome_referente', 'email_referente', 'telefono_referente', 'richieste_particolari', 'numero_partecipanti', 'totale_centesimi', 'chiave_idempotenza', 'data_creazione', 'modalita_economica', 'primo_versamento_centesimi', 'saldo_centesimi', 'fonti_pagamento_json', 'id_revisione_evento', 'hash_revisione_evento', 'snapshot_json', 'id_consenso_privacy', 'versione_informativa_privacy', 'data_accettazione_privacy', 'biglietti_json', 'id_consenso_marketing', 'data_accettazione_marketing', 'opzioni_ordine_json', 'workspace_revision', 'versato_centesimi', 'replica_completa_revision'],
  'Partecipanti': ['codice_ordine', 'numero_partecipante', 'codice_tipologia', 'indice_tipologia', 'nome', 'cognome', 'dati_aggiuntivi_json', 'opzioni_json', 'stato_partecipante', 'data_annullamento', 'totale_centesimi', 'versato_centesimi', 'saldo_centesimi', 'caparra_centesimi', 'caparra_residua_centesimi'],
  'Pagamenti': ['id_pagamento', 'codice_ordine', 'tipo_movimento', 'tipo_rata', 'data_effettiva', 'importo_centesimi', 'valuta', 'fonte_pagamento', 'riferimento_esterno', 'etichetta_operatore', 'canale_registrazione', 'id_inserimento_origine', 'data_creazione', 'nota_amministrativa', 'attribuzioni_partecipanti_json'],
  'Coda email': ['id_messaggio', 'codice_ordine', 'destinatario', 'tipo_modello', 'contenuto_json', 'stato', 'data_creazione'],
  'Operazioni segreteria': ['id_operazione', 'data_richiesta', 'codice_ordine', 'numero_partecipante', 'tipo_operazione', 'dati_json', 'motivo', 'etichetta_operatore', 'stato', 'messaggio', 'data_esito'],
  'Stato operativo': ['codice_ordine', 'numero_partecipante', 'chiave', 'valore', 'data_aggiornamento', 'etichetta_operatore', 'id_ultima_operazione'],
  'Fogli iniziative': ['id_evento', 'titolo', 'id_foglio', 'url_foglio', 'url_iscrizione', 'url_saldo', 'data_creazione'],
  'Viste operative': ['id_evento', 'campi_json', 'data_aggiornamento', 'etichetta_operatore'],
  'Elenco operativo': ['evento', 'codice_ordine', 'numero_partecipante', 'nome', 'cognome', 'stato'],
  'Modelli report': ['id_modello', 'nome', 'tipo', 'id_evento', 'colonne_json', 'filtri_json', 'raggruppamenti_json', 'ordinamento_json', 'predefinito', 'data_aggiornamento', 'etichetta_operatore'],
  'Sistemazioni': ['id_evento', 'codice', 'nome', 'capienza', 'attiva', 'note'],
  'Revisioni replica': ['id_evento', 'revisione_camere'],
  'Registro controlli': ['id_controllo', 'data_evento', 'canale', 'azione', 'tipo_entita', 'riferimento_entita', 'esito', 'etichetta_attore', 'codice_dettaglio']
});

const MI_LEGACY_SHEET_NAMES = Object.freeze({
  Config: MI_SHEETS.CONFIG,
  Events: MI_SHEETS.EVENTS,
  Registrations: MI_SHEETS.REGISTRATIONS,
  Participants: MI_SHEETS.PARTICIPANTS,
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
  'Pagamenti': ['payment_id', 'order_code', 'transaction_kind', 'installment_kind', 'effective_at', 'amount_cents', 'currency', 'payment_source', 'external_reference', 'operator_label', 'recording_channel', 'source_intake_id', 'created_at'],
  'Coda email': ['message_id', 'order_code', 'recipient', 'template_type', 'payload_json', 'status', 'created_at'],
  'Registro controlli': ['audit_id', 'occurred_at', 'channel', 'action', 'entity_type', 'entity_ref', 'outcome', 'actor_label', 'detail_code']
});

function ottieniFoglioDiLavoroAssociato_() {
  const properties = typeof PropertiesService !== 'undefined' ? PropertiesService.getScriptProperties() : null;
  const configuredId = properties ? String(properties.getProperty('MI_SPREADSHEET_ID') || '').trim() : '';
  // Quando il codice viene eseguito dal menu di un progetto associato,
  // il foglio attivo è la fonte attendibile. L'ID memorizzato può essere
  // rimasto da una precedente configurazione e causare PERMISSION_DENIED.
  const active = typeof SpreadsheetApp !== 'undefined' ? SpreadsheetApp.getActiveSpreadsheet() : null;
  if (active) {
    if (typeof active.getId === 'function') {
      const activeId = String(active.getId());
      if (properties && configuredId !== activeId) properties.setProperty('MI_SPREADSHEET_ID', activeId);
    }
    return active;
  }
  if (configuredId) return SpreadsheetApp.openById(configuredId);
  throw new Error('Foglio operativo non configurato. Esegui Inizializza/aggiorna struttura dal Google Sheet.');
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

/** Servizi privati chiamati dal proxy WordPress dopo autorizzazione sull'evento. */
function versioneGestione_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, JSON.stringify(value), Utilities.Charset.UTF_8).map(function(b){return ('0'+(b&255).toString(16)).slice(-2);}).join('');
}


// Sorgente: CronWordPress.gs
/** Avvia il cron WordPress quando l'hosting disabilita l'avvio tramite visite.
 * Esecuzione separata dalla proiezione: non tenere lock mentre WordPress richiama GAS.
 */
function avviaCronWordPress() {
  const endpoint = String(PropertiesService.getScriptProperties().getProperty('MI_WORDPRESS_COMMAND_URL') || '');
  const base = endpoint.replace(/\/wp-json\/modulo-iscrizioni\/v1\/workspace\/commands\/?$/, '/');
  if (base === endpoint || !/^https:\/\/[^/?#]+\/$/.test(base)) throw new Error('Collegamento WordPress non valido.');
  const response = UrlFetchApp.fetch(base + 'wp-cron.php', {method:'get',followRedirects:false,muteHttpExceptions:true});
  const code = response.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('Avvio cron WordPress: HTTP ' + code);
  return {ok:true,http_status:code};
}

function attivaCronWordPress() {
  const exists = ScriptApp.getProjectTriggers().some(t=>t.getHandlerFunction()==='avviaCronWordPress');
  if (!exists) ScriptApp.newTrigger('avviaCronWordPress').timeBased().everyMinutes(5).create();
  return {ok:true,creato:!exists};
}


// Sorgente: DomandeEvento.gs
/** Include both optional and required questions, even in saved operational views. */
function aggiungiColonneDomande_(colonne, evento, iscrizioni, partecipanti) {
  const definitions = new Map();
  const add = field => {
    if (!field || !/^custom_[A-Za-z0-9_-]{1,73}$/.test(String(field.key || ''))) return;
    definitions.set(field.key, String(field.label || field.key));
  };
  iscrizioni.forEach(r => {
    const snapshot = decodificaOggetto_(r.snapshot_json);
    ((snapshot.event || {}).participant_fields || []).forEach(add);
  });
  decodificaElenco_(evento.domande_json).forEach(add);
  partecipanti.forEach(p => Object.keys(decodificaOggetto_(p.dati_aggiuntivi_json)).forEach(key => {
    if (!definitions.has(key)) add({key:key,label:key.replace(/^custom_/, '').replace(/_/g, ' ')});
  }));
  definitions.forEach((label, key) => {
    const existing = colonne.find(c => c.key === key);
    if (existing) existing.label = label;
    else colonne.push({key:key,label:label,gruppo:'Domande',comprimibile:false});
  });
}

/** The configured fields, not a generic travel profile, define the event sheet. */
function vistaEventoSolaLettura_(evento, colonne) {
  const schema = decodificaOggetto_(evento.schema_vista_json);
  return Array.isArray(schema.fields) && Array.isArray(schema.options) && schema.pricing === 'ZERO' && !colonne.some(c => c.key === 'total');
}

function applicaSchemaColonneEvento_(colonne, evento, iscrizioni, partecipanti, pagamenti) {
  const schema = decodificaOggetto_(evento.schema_vista_json);
  if (!Array.isArray(schema.fields) || !Array.isArray(schema.options)) return;
  const catalogo = campiElencoOperativo_(false);
  const result = [];
  const add = (key, label) => {
    if (!/^[a-z][a-z0-9_-]{0,79}$/.test(String(key)) || result.some(c=>c.key===key)) return;
    const known = catalogo.find(c=>c.key===key);
    result.push({key:key,label:String(label || (known && known.label) || key),gruppo:gruppoCampoVistaOperativa_(key),comprimibile:['paid_cash','paid_transfer','paid_card'].includes(key)});
  };
  ['last_name','first_name','phone'].forEach(key=>add(key));
  schema.fields.forEach(field=>{if (field) add(field.key, field.label);});
  if (schema.room) add('room');
  aggiungiColonneServizi_(result, schema.options);
  if (schema.special_requests) add('special_requests');
  const codes = new Set(iscrizioni.map(r=>String(r.codice_ordine)));
  const economic = schema.pricing !== 'ZERO' || iscrizioni.some(r=>Number(r.totale_centesimi)>0 || Number(r.versato_centesimi)>0) || pagamenti.some(p=>codes.has(String(p.codice_ordine)));
  if (economic) ['total','paid','paid_cash','paid_transfer','paid_card','balance'].forEach(key=>add(key));
  if (partecipanti.some(p=>['PRESENT','ABSENT','UNRECORDED'].includes(decodificaOggetto_(p.dati_aggiuntivi_json).attendance))) add('attendance','Presenza effettiva');
  colonne.splice(0, colonne.length, ...result);
}


// Sorgente: EliminazioneEvento.gs
/** Small permanent tombstone rejects delayed deliveries after event removal. */
function eventoInEliminazione_(id) {
  return !!PropertiesService.getScriptProperties().getProperty('MI_DELETED_EVENT_' + String(id));
}

/** Signed, bounded and repeatable. Children are removed before their order identities. */
function eliminaDatiEventoDaWordPress_(payload) {
  payload = payload || {};
  const id = String(payload.id_evento || '');
  const request = String(payload.request_id || '');
  const mode = String(payload.mode || '');
  if (!/^\d+$/.test(id) || !/^[a-f0-9-]{36}$/.test(request) || !['keep','trash'].includes(mode)) return {ok:false,error:'INVALID_DELETION'};
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(5000)) return {ok:false,error:'EVENT_BUSY'};
  try {
    const props = PropertiesService.getScriptProperties();
    const key = 'MI_DELETED_EVENT_' + id;
    let job = JSON.parse(props.getProperty(key) || 'null');
    if (job && (job.request !== request || job.mode !== mode)) return {ok:false,error:'DELETION_CONFLICT'};
    if (!job) {
      const links = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(r=>String(r.id_evento)===id);
      const files = Array.from(new Set(links.map(r=>String(r.id_foglio||'')).concat(String(payload.id_foglio||'')).filter(Boolean)));
      // A conflicting association must be investigated rather than deleting someone else's file.
      const allLinks = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
      if (allLinks.some(r=>String(r.id_evento)!==id && files.includes(String(r.id_foglio)))) return {ok:false,error:'SHARED_EVENT_SHEET'};
      job = {request:request,mode:mode,files:files,fileIndex:0,complete:false};
      props.setProperty(key,JSON.stringify(job));
    }
    const sheetUrl = job.files.length ? 'https://docs.google.com/spreadsheets/d/'+job.files[0]+'/edit' : '';
    if (job.complete) return {ok:true,complete:true,sheet_url:sheetUrl};
    // Do not swallow permission/not-found errors: retain the reference for an explicit retry.
    while (job.fileIndex < job.files.length) {
      const file = DriveApp.getFileById(job.files[job.fileIndex]);
      if (mode==='trash' && !file.isTrashed()) file.setTrashed(true);
      job.fileIndex++;
      props.setProperty(key,JSON.stringify(job));
    }
    const registrations = ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS);
    const codes = new Set((Array.isArray(payload.order_codes)?payload.order_codes:[]).map(String));
    convertiRigheInOggetti_(registrations).filter(r=>String(r.id_evento)===id).forEach(r=>codes.add(String(r.codice_ordine)));
    const children = [MI_SHEETS.PARTICIPANTS,MI_SHEETS.PAYMENTS,MI_SHEETS.EMAIL_OUTBOX,MI_SHEETS.SECRETARY_OPERATIONS,MI_SHEETS.OPERATIONAL_STATE,MI_SHEETS.OPERATIONAL_LIST];
    const direct = [MI_SHEETS.OPERATIONAL_VIEWS,MI_SHEETS.ACCOMMODATIONS,MI_SHEETS.REPLICA_REVISIONS,MI_SHEETS.REPORT_TEMPLATES,MI_SHEETS.EVENT_WORKSPACES,MI_SHEETS.EVENTS];
    const book = ottieniFoglioDiLavoroAssociato_();
    const deadline = Date.now()+7000;
    let removed=0;
    const targets=children.map(name=>({name:name,match:r=>codes.has(String(r.codice_ordine))}));
    targets.push({name:MI_SHEETS.AUDIT_LOG,match:r=>codes.has(String(r.riferimento_entita)) || (String(r.riferimento_entita)===id && ['FOGLIO_OPERATIVO','PRODUZIONI_EVENTO','EVENTO'].includes(String(r.azione)))});
    direct.forEach(name=>targets.push({name:name,match:r=>String(r.id_evento)===id}));
    // Preserve central order rows until every dependent row is removed, including across retries.
    targets.push({name:MI_SHEETS.REGISTRATIONS,match:r=>String(r.id_evento)===id});
    for (const target of targets) {
      const sheet=book.getSheetByName(target.name);
      if (!sheet) continue;
      const rows=convertiRigheInOggetti_(sheet).filter(target.match).sort((a,b)=>b._row-a._row);
      for (let index=0; index<rows.length;) {
        if (removed>=100 || (removed>0 && Date.now()>=deadline)) return {ok:true,complete:false,removed:removed,sheet_url:sheetUrl};
        // Descending contiguous ranges cannot include another event's rows.
        let start=rows[index]._row, count=1;
        while (index+count<rows.length && count<100-removed && rows[index+count]._row===start-1) {
          start--; count++;
        }
        sheet.deleteRows(start,count);
        removed+=count; index+=count;
      }
    }
    // Retired per-event views are identified by metadata, never by a title match.
    book.getSheets().forEach(sheet=>{
      if (Object.values(MI_SHEETS).includes(sheet.getName())) return;
      if (sheet.getDeveloperMetadata().some(m=>m.getKey()==='MI_ID_EVENTO' && String(m.getValue())===id)) book.deleteSheet(sheet);
    });
    job.files.forEach(file=>props.deleteProperty('MI_EVENT_VIEW_'+file));
    job.complete=true; props.setProperty(key,JSON.stringify(job));
    return {ok:true,complete:true,removed:removed,sheet_url:sheetUrl};
  } finally {lock.releaseLock();}
}


// Sorgente: Email.gs
const MI_TEST_EMAIL_PROPERTY = 'MI_EMAIL_TEST_RECIPIENT';

function statoCanaleEmail_() {
  const sender = String(Session.getEffectiveUser().getEmail() || '').trim().toLowerCase();
  const expected = String(PropertiesService.getScriptProperties().getProperty('MI_EMAIL_SENDER') || '').trim().toLowerCase();
  const authorized = expected && sender === expected;
  return { ok: !!authorized, error: authorized ? '' : 'EMAIL_SENDER_NOT_AUTHORIZED', channel: 'GOOGLE_WORKSPACE', sender: sender };
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
    const book = ottieniFoglioDiLavoroAssociato_();
    let sheet = book.getSheetByName('Registro invii email');
    if (!sheet) { sheet = book.insertSheet('Registro invii email'); sheet.appendRow(['delivery_key', 'stato', 'data_utc']); }
    const rows = sheet.getLastRow() > 1 ? sheet.getRange(2, 1, sheet.getLastRow() - 1, 2).getValues() : [];
    const previous = rows.find(row => row[0] === p.delivery_key);
    if (previous) return previous[1] === 'ACCEPTED' ? { ok: true, channel: 'GOOGLE_WORKSPACE', replayed: true } : { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' };
    if (MailApp.getRemainingDailyQuota() < 1) return { ok: false, error: 'EMAIL_QUOTA_EXCEEDED' };
    sheet.appendRow([p.delivery_key, 'SENDING', new Date().toISOString()]);
    const row = sheet.getLastRow();
    SpreadsheetApp.flush();
    const options = { to: recipient, subject: String(p.oggetto), body: String(p.testo), htmlBody: String(p.html), name: 'Parrocchia Sant’Eugenio', replyTo: recipient };
    if (p.mode === 'OPERATIVO') options.replyTo = replyTo;
    if (p.codice_svg) options.inlineImages = { 'mi-registration-code': Utilities.newBlob(String(p.codice_svg), 'image/svg+xml', 'codice-iscrizione.svg') };
    try { MailApp.sendEmail(options); }
    catch (error) { console.error('EMAIL_SEND_FAILED', String(error)); return { ok: false, error: 'EMAIL_DELIVERY_UNCERTAIN' }; }
    sheet.getRange(row, 2).setValue('ACCEPTED');
    SpreadsheetApp.flush();
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


// Sorgente: FogliOperativi.gs
/** Prepara il registro dell'evento e il relativo foglio operativo su richiesta firmata di WordPress. */
function trovaCollegamentoFoglioOperativo_(idEvento) {
  const id=String(idEvento);
  const rows=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(row=>String(row.id_evento)===id);
  if (rows.length!==1 || !rows[0].id_foglio) throw new Error('Collegamento univoco al foglio operativo non disponibile.');
  return rows[0];
}
function preparaProduzioniEventoDaWordPress_(payload) {
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  try {
    if (eventoInEliminazione_(String((payload||{}).id_evento||''))) return {ok:false,error:'EVENT_DELETED'};
    return preparaProduzioniEventoConLock_(payload);
  } finally {lock.releaseLock();}
}
function preparaProduzioniEventoConLock_(payload) {
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
    new Date(),
    JSON.stringify(Array.isArray(payload.servizi) ? payload.servizi : []),
    JSON.stringify(Array.isArray(payload.domande_partecipanti) ? payload.domande_partecipanti : decodificaElenco_((esistente || {}).domande_json)),
    normalizzaValoreElenco_(payload.profilo_operativo, ['MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO']) || (esistente || {}).profilo_operativo || ''
  ];
  if (eventi.getMaxColumns() < 12) eventi.insertColumnsAfter(eventi.getMaxColumns(), 12 - eventi.getMaxColumns());
  eventi.getRange(1, 12).setValue('profilo_operativo');
  eventi.getRange(1, 11).setValue('domande_json');
  if (esistente) eventi.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
  else eventi.appendRow(valori);
  if (payload.event_schema !== undefined) aggiornaSchemaEventoMysql_(idEvento, payload.event_schema);
	const profiloOperativo = normalizzaValoreElenco_(payload.profilo_operativo, ['AUTOMATICO', 'MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO']) || 'AUTOMATICO';
  const risultato = apriFoglioOperativoConLock_({ id_evento: idEvento, titolo: titolo, profilo_operativo: profiloOperativo });
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
		file.addViewer(emailGestore);
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
  if (typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(idEvento)) throw new Error('Evento eliminato o in eliminazione.');
  const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
  const esistente = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
  const proprieta = PropertiesService.getScriptProperties();
  const chiavePreparazione = 'MI_SHEET_PREPARING_' + idEvento;
  let foglioDaCompletare = null;
  if (esistente && esistente.id_foglio) {
		try {
			const fileEsistente = DriveApp.getFileById(String(esistente.id_foglio));
			if (!fileEsistente.isTrashed()) {
				const aperto = SpreadsheetApp.openById(String(esistente.id_foglio));
				if (proprieta.getProperty(chiavePreparazione) === String(esistente.id_foglio)) foglioDaCompletare = aperto;
				else return { id_evento: idEvento, id_foglio: String(esistente.id_foglio), url_foglio: String(esistente.url_foglio || ('https://docs.google.com/spreadsheets/d/' + esistente.id_foglio + '/edit')), cartella: '', creato: false };
			}
		} catch (errore) {
			throw new Error('Il foglio registrato non è al momento accessibile. Riprova senza creare un duplicato.');
		}
  }
	// Un evento appena creato non possiede ancora iscrizioni: evitiamo di rileggere
	// l'intero database e prepariamo subito la struttura scelta in WordPress.
	const vista = esistente ? generaVistaOperativaEvento_(idEvento) : generaVistaOperativaIniziale_(idEvento, normalizzaTesto_(form.titolo, 200), normalizzaTesto_(form.profilo_operativo, 30));
	const titoloPulito = String(vista.evento.titolo || idEvento).replace(/[\\/:*?"<>|#%{}]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
	const titolo = 'Evento ' + idEvento + ' - ' + titoloPulito;
  const foglio = foglioDaCompletare || SpreadsheetApp.create(titolo);
  // Persist the identity before formatting: a retry resumes the same document.
  proprieta.setProperty(chiavePreparazione, String(foglio.getId()));
  const valori = [idEvento, neutralizzaFormula_(vista.evento.titolo, 200), foglio.getId(), foglio.getUrl(), '', '', new Date()];
	if (esistente) registro.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
	else registro.appendRow(valori);
  SpreadsheetApp.flush();
	const cartella = spostaFoglioAccantoAlDatabase_(foglio.getId());
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  scheda.setName('Dati operativi');
  scriviProiezioneEvento_(scheda, vista);
  configuraSchedeEconomicheEvento_(foglio, idEvento, vista.sola_lettura);
  SpreadsheetApp.flush();
  proprieta.deleteProperty(chiavePreparazione);
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
	const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(r => String(r.id_evento) === String(idEvento)) || {};
	aggiungiColonneDomande_(colonne, evento, [], []);
	aggiungiColonneServizi_(colonne, decodificaElenco_(evento.servizi_json));
	applicaSchemaColonneEvento_(colonne, evento, [], [], []);
	return { evento: { id: idEvento, titolo: titolo || idEvento }, sola_lettura: vistaEventoSolaLettura_(evento, colonne), profilo: profilo, nome_profilo: profilo, personalizzata: false, conservata: false, colonne: colonne, righe: [] };
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
		if (PropertiesService.getScriptProperties().getProperty('MI_SHEET_PREPARING_' + idEvento) === String(collegamento.id_foglio)) return { ok: true, esiste: false, id_evento: idEvento, preparazione_in_corso: true };
		return { ok: true, esiste: true, id_evento: idEvento, id_foglio: String(collegamento.id_foglio), url_foglio: String(collegamento.url_foglio || file.getUrl()) };
	} catch (errore) {
		return { ok: false, error: 'SHEET_UNAVAILABLE', id_evento: idEvento };
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
		DriveApp.getFileById(String(collegamento.id_foglio)).setTrashed(true);
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

/** Riallinea dal database; le richieste interattive precedono i giri automatici. */
function aggiornaFoglioOperativoEvento(form) {
  const lock = LockService.getScriptLock();
  if (form && form.background) {
    if (Number(PropertiesService.getScriptProperties().getProperty('MI_INTERACTIVE_OPEN_UNTIL')) > Date.now() || !lock.tryLock(100)) return {ok:false,busy:true};
  } else lock.waitLock(30000);
  try { return aggiornaFoglioOperativoEventoConLock_(form); }
  finally { lock.releaseLock(); }
}

function aggiornaFoglioOperativoEventoConLock_(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  if (typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(idEvento)) throw new Error('Evento eliminato o in eliminazione.');
  const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
  const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const vista = generaVistaOperativaEvento_(idEvento);
  const proprieta = PropertiesService.getScriptProperties();
  const chiaveProiezione = 'MI_EVENT_VIEW_' + String(collegamento.id_foglio);
  const ordiniEvento = new Set(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r=>String(r.id_evento)===idEvento).map(r=>String(r.codice_ordine)));
  const movimentiEvento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(r=>ordiniEvento.has(String(r.codice_ordine))).map(r=>{const copia=Object.assign({},r);delete copia._row;return copia;});
  const impronta = versioneGestione_({versioneProiezione:2,vista:vista,movimenti:movimentiEvento});
  const pending = modificheCorrentiFoglio_(scheda);
  if (form.soloModificati === true && proprieta.getProperty(chiaveProiezione) === impronta && !pending.changes.length && !pending.errors.length) {
    return {ok:true, invariato:true, url_foglio:foglio.getUrl(), esito:{aggiunte:0,manuali:0,conflitti:0}};
  }
  const esito = scriviProiezioneEvento_(scheda, vista);
  if (!esito.manuali && !esito.conflitti) configuraSchedeEconomicheEvento_(foglio, idEvento, vista.sola_lettura);
  aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento);
  // Store only after all writes succeed. Pending edits remain in the sheet;
  // a new canonical value changes the fingerprint and retries acknowledgment.
  if (!esito.manuali && !esito.conflitti) proprieta.setProperty(chiaveProiezione, impronta);
  else proprieta.deleteProperty(chiaveProiezione);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'REFRESH', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'DATABASE_TO_EVENT_SHEET', 'SEGRETERIA');
  return { ok: true, url_foglio: foglio.getUrl(), righe: vista.righe.length, esito: esito, message: 'Controllo completato. Le modifiche nelle celle blu si inviano con Sincronizza.' };
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

/** Identità usata dalla verifica di consegna WordPress. */
function identitaRigaEvento_(evento, codice, numero) {
  if (!codice || !Number.isInteger(Number(numero)) || Number(numero) < 1) return '';
  return JSON.stringify([String(evento), String(codice), Number(numero)]);
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
/** I fogli evento contengono soltanto proiezioni consultabili. */
function eventoPrevedeMovimenti_(idEvento) {
  const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(r => String(r.id_evento) === String(idEvento));
  if (!evento || String(evento.modalita_prezzo).toUpperCase() !== 'ZERO') return true;
  const ordini = new Set(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r => String(r.id_evento) === String(idEvento)).map(r => String(r.codice_ordine)));
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).some(r => ordini.has(String(r.codice_ordine)));
}
function configuraSchedeEconomicheEvento_(foglio, idEvento, solaLettura) {
  const modulo = foglio.getSheetByName('Registra movimento');
  if (modulo && foglio.getSheets().length > 1) foglio.deleteSheet(modulo);
  ScriptApp.getProjectTriggers().filter(function (t) { return t.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(t.getTriggerSourceId()) === String(foglio.getId()); }).forEach(function (t) { ScriptApp.deleteTrigger(t); });
  preparaPagamentiEvento_(foglio, idEvento);
  if (solaLettura) {
    const dati = foglio.getSheetByName('Dati operativi');
    if (dati) dati.showSheet();
    const gestione = foglio.getSheetByName('Gestione evento');
    if (gestione && dati) gestione.hideSheet();
    return;
  }
  const gestione = foglio.getSheetByName('Gestione evento');
  if (gestione) gestione.showSheet();
  preparaAccessoGestioneEvento_(foglio, idEvento);
}


// Sorgente: PagamentiEvento.gs
/** Storico consultabile: nessuna riga locale viene acquisita. */
function preparaPagamentiEvento_(foglio, idEvento) {
  if (!eventoPrevedeMovimenti_(idEvento)) {
    const existing = foglio.getSheetByName('Pagamenti');
    if (existing && foglio.getSheets().some(s => s.getName() !== 'Pagamenti' && !s.isSheetHidden())) existing.hideSheet();
    return null;
  }
  const existing = foglio.getSheetByName('Pagamenti');
  if (existing) { existing.showSheet(); return existing; }
  return foglio.getSheetByName('Pagamenti') || foglio.insertSheet('Pagamenti');
}
function aggiornaProiezionePagamentiEvento_(foglio, idEvento) {
  const lock=LockService.getScriptLock(); lock.waitLock(30000);
  try { return aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento); }
  finally {lock.releaseLock();}
}
function aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento) {
    const s=preparaPagamentiEvento_(foglio,idEvento);
    if (!s) return;
    const ordini=new Set(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r=>String(r.id_evento)===String(idEvento)).map(r=>String(r.codice_ordine)));
    const rows=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(r=>ordini.has(String(r.codice_ordine))).sort((a,b)=>new Date(a.data_effettiva)-new Date(b.data_effettiva)).map(r=>{const m=serializzaMovimento_(r);return [m.id,r.codice_ordine,m.data,m.tipo,m.importo/100,m.metodo,m.riferimento,m.operatore,m.nota].map(v=>typeof v==='string'?neutralizzaFormula_(v,5000):v);});
    s.clear();
    s.getRange(1,1,s.getMaxRows(),s.getMaxColumns()).clearDataValidations();
    s.showColumns(1,s.getMaxColumns());
    const headers=['Movimento','Prenotazione','Data','Tipo','Importo (€)','Metodo','Riferimento','Operatore','Nota'];
    if(s.getMaxRows()<rows.length+1)s.insertRowsAfter(s.getMaxRows(),rows.length+1-s.getMaxRows());
    if(s.getMaxColumns()<headers.length)s.insertColumnsAfter(s.getMaxColumns(),headers.length-s.getMaxColumns());
    s.getRange(1,1,1,headers.length).setValues([headers]).setFontWeight('bold');
    if(rows.length){s.getRange(2,1,rows.length,headers.length).setValues(rows);s.getRange(2,5,rows.length,1).setNumberFormat('#,##0.00');}
    s.setFrozenRows(1);proteggiProiezione_(s);
}
function sincronizzaFogliEventi() {
  if (Number(PropertiesService.getScriptProperties().getProperty('MI_INTERACTIVE_OPEN_UNTIL')) > Date.now()) return [];
  const collegamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(function (riga) { return !!riga.id_foglio && !(typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(riga.id_evento)); });
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
      const risultato = aggiornaFoglioOperativoEvento({ id_evento: String(riga.id_evento), soloModificati: true, background: true });
      if (risultato.busy) { proprieta.setProperty('MI_EVENT_SYNC_CURSOR', String(indice)); break; }
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

function serializzaMovimento_(r) {
  const amount = Number(r.importo_centesimi) || 0;
  const tipo = String(r.tipo_movimento).toUpperCase();
  return { id:String(r.id_pagamento), data:r.data_effettiva instanceof Date ? r.data_effettiva.toISOString() : String(r.data_effettiva || ''), tipo:tipo, importo:['RIMBORSO','STORNO'].includes(tipo) ? -amount : amount, metodo:String(r.fonte_pagamento || ''), riferimento:String(r.riferimento_esterno || ''), operatore:String(r.etichetta_operatore || ''), nota:String(r.nota_amministrativa || '') };
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
  const filters = normalizzaFiltriEsecuzioneReport_(form.valori_filtri);
  const count = generaElencoOperativo_(eventId, columns, { ordinamento: model.ordinamento, raggruppamenti: model.raggruppamenti, filtri: filters });
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

function normalizzaFiltriEsecuzioneReport_(raw) {
  raw = raw || {};
  if (typeof raw !== 'object' || Array.isArray(raw)) throw new Error('Filtri report non validi.');
  const allowed = ['query', 'status', 'room', 'transport'];
  if (Object.keys(raw).some(function (key) { return allowed.indexOf(key) < 0; })) throw new Error('Filtro report non disponibile.');
  const result = {};
  allowed.forEach(function (key) { result[key] = normalizzaTesto_(raw[key] || '', 120); });
  if (['', 'CONFIRMED', 'PENDING_PAYMENT', 'WAITLISTED', 'WAITLIST_OFFERED'].indexOf(result.status) < 0) throw new Error('Stato report non valido.');
  return result;
}

function corrispondeFiltriReport_(filters, registration, participant, data) {
  filters = filters || {};
  const aliases = { CONFERMATA: 'CONFIRMED', IN_ATTESA_PAGAMENTO: 'PENDING_PAYMENT' };
  const status = String(registration.stato || '').toUpperCase();
  if (filters.status && filters.status !== (aliases[status] || status)) return false;
  const query = String(filters.query || '').trim().toLocaleLowerCase('it');
  const name = [participant.nome, participant.cognome, participant.cognome, participant.nome, registration.codice_ordine, registration.email_referente].join(' ').toLocaleLowerCase('it');
  if (query && name.indexOf(query) < 0) return false;
  const room = data.room || data.camera || data.alloggio || '';
  const transport = data.pullman || data.transport || '';
  return (!filters.room || String(room).toLocaleLowerCase('it') === filters.room.toLocaleLowerCase('it')) && (!filters.transport || String(transport).toLocaleLowerCase('it') === filters.transport.toLocaleLowerCase('it'));
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
function apriSchedaPrenotazione() { return apriGestioneWeb(); }

function apriDialogoPrenotazione() { return apriGestioneWeb(); }

function apriConfigurazioneElencoOperativo() {
  const template = HtmlService.createTemplateFromFile('Segreteria');
  template.modalita = 'ELENCO'; template.codiceOrdineIniziale = ''; template.isWebApp = false; template.webAppUrl = '';
  SpreadsheetApp.getUi().showSidebar(template.evaluate().setTitle('Elenco operativo'));
}

function apriAssegnazioniEvento() { return apriGestioneWeb(); }

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
    const payment = statoPagamentoPartecipante_(participant, registration, paidByOrder[orderCode] || 0);
    const searchable = [orderCode, participant.nome, participant.cognome, registration.nome_referente, registration.cognome_referente].join(' ').toLowerCase();
    const roomDoesNotMatch = roomFilter === 'non assegnata' ? !!room : (roomFilter && room.toLowerCase().indexOf(roomFilter) < 0);
    if ((query && searchable.indexOf(query) < 0) || (paymentFilter && payment.code !== paymentFilter) || roomDoesNotMatch) return;
    items.push({ order_code: orderCode, participant_number: personNumber, first_name: String(participant.nome || ''), last_name: String(participant.cognome || ''), event_id: String(registration.id_evento || ''), event_title: String((events[String(registration.id_evento)] || {}).titolo || registration.id_evento || ''), registration_status: String(registration.stato || ''), payment_status: payment, room: room, participant_status: String(participant.stato_partecipante || 'ACTIVE') });
  });
  return { items: items.slice(0, limit), total: items.length, has_more: items.length > limit };
}

/** Letture riutilizzate solo durante questa chiamata; mai condivise tra richieste o scritture. */
function creaLetturaGestione_() {
  const righe = Object.create(null);
  let stato;
  return {
    righe: function(nome) {
      if (!Object.prototype.hasOwnProperty.call(righe, nome)) righe[nome] = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(nome));
      return righe[nome];
    },
    stato: function() { if (!stato) stato = indiceStatoOperativo_(); return stato; }
  };
}
function caricaSchedaPrenotazione(orderCode) {
  return caricaSchedaPrenotazioneConDati_(orderCode, creaLetturaGestione_());
}
function caricaSchedaPrenotazioneConDati_(orderCode, lettura) {
  orderCode = normalizzaTesto_(orderCode, 64);
  const registration = lettura.righe(MI_SHEETS.REGISTRATIONS).find(function (row) { return String(row.codice_ordine) === orderCode; });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const event = lettura.righe(MI_SHEETS.EVENTS).find(function (row) { return String(row.id_evento) === String(registration.id_evento); }) || {};
  const operational = lettura.stato();
  const participants = lettura.righe(MI_SHEETS.PARTICIPANTS).filter(function (row) { return String(row.codice_ordine) === orderCode; }).map(function (row) {
    const number = Number(row.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(row, operational[orderCode + '|' + number] || {}); const room = String(fields.room || fields.camera || fields.alloggio || '');
    delete fields.room; delete fields.camera; delete fields.alloggio;
    return { number: number, first_name: String(row.nome || ''), last_name: String(row.cognome || ''), ticket_type: String(row.codice_tipologia || ''), status: String(row.stato_partecipante || 'ACTIVE'), room: room, fields: fields, options: decodificaElenco_(row.opzioni_json), payment_status: statoPagamentoPartecipante_(row, registration, 0) };
  });
  const payments = lettura.righe(MI_SHEETS.PAYMENTS).filter(function (row) { return String(row.codice_ordine) === orderCode; });
  const netPaid = calcolaVersatoPerOrdine_(payments)[orderCode] || 0; const economic = posizioneEconomicaRegistrazione_(registration, netPaid);
  return { order_code: orderCode, event_id: String(registration.id_evento || ''), event_title: String(event.titolo || registration.id_evento || ''), status: String(registration.stato || ''), payment_status: statoPagamento_(registration, netPaid), created_at: registration.data_creazione, buyer: { first_name: String(registration.nome_referente || ''), last_name: String(registration.cognome_referente || ''), email: String(registration.email_referente || ''), phone: String(registration.telefono_referente || '') }, special_requests: String(registration.richieste_particolari || ''), total_cents: economic.total, deposit_cents: Number(registration.primo_versamento_centesimi) || 0, paid_cents: economic.paid, balance_cents: economic.balance, participants: participants, accommodations: elencaSistemazioniConDati_(String(registration.id_evento || ''), lettura), active_operator: normalizzaTesto_(Session.getActiveUser().getEmail(), 120) };
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
  const rows = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) { return !!byOrder[String(row.codice_ordine)] && String(row.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED'; }).map(function (participant) { const registration = byOrder[String(participant.codice_ordine)]; const data = decodificaOggetto_(participant.dati_aggiuntivi_json); operational.filter(function (state) { return String(state.codice_ordine) === String(participant.codice_ordine) && Number(state.numero_partecipante) === Number(participant.numero_partecipante); }).forEach(function (state) { data[String(state.chiave)] = state.valore; }); if (!corrispondeFiltriReport_(options.filtri, registration, participant, data)) return null; const row = fields.map(function (field) { return neutralizzaFormula_(valoreCampoElenco_(field, event, registration, participant, data, payments), 1000); }); row._orderCode = String(participant.codice_ordine); return row; }).filter(Boolean);
	const grouping = normalizzaScelteReport_(options.raggruppamenti, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const ordering = normalizzaScelteReport_(options.ordinamento, fields, 5).map(function (field) { return fields.indexOf(field); }).filter(function (index) { return index >= 0; });
	const sortColumns = grouping.concat(ordering).filter(function (column, index, list) { return list.indexOf(column) === index; });
	if (sortColumns.length) rows.sort(function (left, right) { for (let index = 0; index < sortColumns.length; index += 1) { const column = sortColumns[index]; const comparison = String(left[column] == null ? '' : left[column]).localeCompare(String(right[column] == null ? '' : right[column]), 'it', { numeric: true, sensitivity: 'base' }); if (comparison) return comparison; } return 0; });
  limitaImportiAUnaRigaPerOrdine_(rows, fields);
  sheet.clear(); sheet.getRange(1, 1, 1, fields.length).merge().setValue('Elenco operativo — ' + String(event.titolo || eventId) + '\nCopia sincronizzata: importi e stati possono non includere gli ultimi movimenti. Verificare nel portale WordPress prima di richiedere pagamenti o rimborsi.').setWrap(true).setBackground('#17224a').setFontColor('#ffffff').setFontWeight('bold').setFontSize(14); sheet.getRange(2, 1, 1, fields.length).setValues([fields.map(function (field) { return labels[field] || field; })]).setBackground('#1f4e78').setFontColor('#ffffff').setFontWeight('bold').setWrap(true);
  if (rows.length) sheet.getRange(3, 1, rows.length, fields.length).setValues(rows).setWrap(true).setVerticalAlignment('middle');
  if (grouping.length && rows.length) rows.forEach(function (row, index) { const previous = index ? rows[index - 1] : null; const startsGroup = !previous || grouping.some(function (column) { return String(row[column]) !== String(previous[column]); }); if (startsGroup) sheet.getRange(index + 3, 1, 1, fields.length).setBorder(true, null, null, null, null, null, '#17224a', SpreadsheetApp.BorderStyle.SOLID_MEDIUM); });
  sheet.setFrozenRows(2); sheet.setHiddenGridlines(true); sheet.autoResizeColumns(1, fields.length); for (let column = 1; column <= fields.length; column += 1) sheet.setColumnWidth(column, Math.min(210, Math.max(90, sheet.getColumnWidth(column)))); sheet.getRange(1, 1, Math.max(2, rows.length + 2), fields.length).setBorder(true, true, true, true, true, true, '#d7dde6', SpreadsheetApp.BorderStyle.SOLID);
  // Le celle unite non si adattano sempre automaticamente al testo in stampa.
  sheet.setRowHeight(1, 48 + Math.ceil(210 / (fields.length * 8)) * 20);
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
    const orderCode = normalizzaTesto_(registration.codice_ordine, 64); const economic = posizioneEconomicaRegistrazione_(registration, paidByOrder[orderCode] || 0);
    return { order_code: orderCode, paid_cents: economic.paid, balance_cents: economic.balance };
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
  const fields = decodificaOggetto_(participant.dati_aggiuntivi_json); Object.keys(overrides || {}).forEach(function (key) { fields[key] = overrides[key]; });
  if (Object.prototype.hasOwnProperty.call(overrides || {}, 'room')) { delete fields.camera; delete fields.alloggio; }
  return fields;
}

function elencaSistemazioniDisponibili_(eventId) {
  return elencaSistemazioniConDati_(eventId, creaLetturaGestione_());
}
function elencaSistemazioniConDati_(eventId, lettura) {
  let rooms = lettura.righe(MI_SHEETS.ACCOMMODATIONS).filter(function (row) { return String(row.id_evento) === String(eventId) && ['0', 'NO', 'FALSE', 'INATTIVA'].indexOf(String(row.attiva).toUpperCase()) < 0; });
  const registrations = lettura.righe(MI_SHEETS.REGISTRATIONS).filter(function (row) { return String(row.id_evento) === String(eventId) && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0; }); const allowedOrders = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = true; return result; }, {}); const operational = lettura.stato(); const occupied = {};
  lettura.righe(MI_SHEETS.PARTICIPANTS).forEach(function (participant) { const orderCode = String(participant.codice_ordine || ''); if (!allowedOrders[orderCode] || String(participant.stato_partecipante || 'ACTIVE').toUpperCase() === 'CANCELLED') return; const number = Number(participant.numero_partecipante) || 0; const fields = datiOperativiPartecipante_(participant, operational[orderCode + '|' + number] || {}); const code = String(fields.room || fields.camera || fields.alloggio || ''); if (code) occupied[code] = (occupied[code] || 0) + 1; });
  return rooms.map(function (room) { const code = String(room.codice || ''); const capacity = Math.max(0, Math.round(Number(room.capienza) || 0)); const used = occupied[code] || 0; return { code: code, name: String(room.nome || code), capacity: capacity, occupied: used, available: Math.max(0, capacity - used) }; });
}

function calcolaVersatoPerOrdine_(payments) {
  return (payments || []).reduce(function (result, row) { const code = String(row.codice_ordine || ''); const amount = Number(row.importo_centesimi) || 0; result[code] = (result[code] || 0) + (['RIMBORSO', 'STORNO'].indexOf(String(row.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); return result; }, {});
}

function posizioneEconomicaRegistrazione_(registration, paidFallback) {
  const total = Math.max(0, Number(registration.totale_centesimi) || 0); const rawBalance = registration.saldo_centesimi; const hasBalance = rawBalance !== '' && rawBalance !== null && rawBalance !== undefined && Number.isFinite(Number(rawBalance));
  const balance = hasBalance ? Math.max(0, Number(rawBalance)) : Math.max(0, total - Math.max(0, Number(paidFallback) || 0));
  const rawPaid = registration.versato_centesimi;
  const paid = rawPaid !== '' && rawPaid != null && Number.isFinite(Number(rawPaid)) ? Math.max(0, Number(rawPaid)) : Math.max(0, Number(paidFallback) || 0);
  return { total: total, paid: paid, balance: balance };
}

function statoPagamento_(registration, paid) {
  const economic = posizioneEconomicaRegistrazione_(registration, paid); const total = economic.total; const deposit = Math.max(0, Number(registration.primo_versamento_centesimi) || 0); const balance = economic.balance; paid = economic.paid;
  if (!total) return { code: 'GRATUITO', label: 'Gratuito', paid_cents: paid, balance_cents: 0 };
  if (!balance) return { code: 'SALDATO', label: 'Saldato', paid_cents: paid, balance_cents: 0 };
  if (deposit > 0 && ['CONFERMATA', 'CONFIRMED'].indexOf(String(registration.stato).toUpperCase()) >= 0) return { code: 'CAPARRA_RICEVUTA', label: 'Caparra ricevuta', paid_cents: paid, balance_cents: balance };
  if (paid > 0) return { code: 'PARZIALE', label: 'Versamento parziale', paid_cents: paid, balance_cents: balance };
  return { code: deposit > 0 ? 'CAPARRA_DOVUTA' : 'DA_PAGARE', label: deposit > 0 ? 'Caparra dovuta' : 'Da pagare', paid_cents: 0, balance_cents: balance };
}

function statoPagamentoPartecipante_(participant, registration, paidFallback) {
  const hasIndividual = participant.totale_centesimi !== '' && participant.totale_centesimi !== null && participant.totale_centesimi !== undefined && Number.isFinite(Number(participant.totale_centesimi));
  if (!hasIndividual) return statoPagamento_(registration, paidFallback);
  const total = Math.max(0, Number(participant.totale_centesimi) || 0); const paid = Math.max(0, Number(participant.versato_centesimi) || 0); const balance = Math.max(0, Number(participant.saldo_centesimi) || 0); const deposit = Math.max(0, Number(participant.caparra_centesimi) || 0); const missing = Math.max(0, Number(participant.caparra_residua_centesimi) || 0);
  if (!total) return { code: 'GRATUITO', label: 'Gratuito', paid_cents: paid, balance_cents: 0 };
  if (!balance) return { code: 'SALDATO', label: 'Saldato', paid_cents: paid, balance_cents: 0 };
  if (deposit > 0 && !missing) return { code: 'CAPARRA_RICEVUTA', label: 'Caparra ricevuta', paid_cents: paid, balance_cents: balance };
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
  const profilo = determinaProfiloVistaOperativa_(iscrizioni, partecipanti, evento.profilo_operativo);
  const campi = Array.isArray(campiForzati) && campiForzati.length ? campiForzati : (vistaSalvata.length ? vistaSalvata : profilo.campi);
  const catalogo = campiElencoOperativo_().reduce(function (indice, campo) { indice[campo.key] = campo; return indice; }, {});
  const colonne = campi.filter(function (chiave) { return !!catalogo[chiave]; }).map(function (chiave) {
    return { key: chiave, label: catalogo[chiave].label, gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 };
  });
  aggiungiColonneServizi_(colonne, decodificaElenco_(evento.servizi_json));
  aggiungiColonneDomande_(colonne, evento, iscrizioni, partecipanti);
  if (partecipanti.some(p=>['PRESENT','ABSENT','UNRECORDED'].includes(decodificaOggetto_(p.dati_aggiuntivi_json).attendance)) && !colonne.some(c=>c.key==='attendance')) colonne.push({key:'attendance',label:'Presenza effettiva',gruppo:'persona',comprimibile:false});
  iscrizioni.forEach(r=>{const snapshot=decodificaOggetto_(r.snapshot_json);aggiungiColonneServizi_(colonne, (snapshot.event||{}).options||[]);});
  applicaSchemaColonneEvento_(colonne, evento, iscrizioni, partecipanti, pagamenti);
  const righe = partecipanti.map(function (partecipante) {
    const iscrizione = iscrizioniPerCodice[String(partecipante.codice_ordine)];
    const numero = Number(partecipante.numero_partecipante) || 0;
    const dati = datiOperativiPartecipante_(partecipante, statoOperativo[String(partecipante.codice_ordine) + '|' + numero] || {});
    const valori = {};
    colonne.forEach(function (colonna) { valori[colonna.key] = valoreCampoElenco_(colonna.key, evento, iscrizione, partecipante, dati, pagamenti); });
    return { codice_ordine: String(partecipante.codice_ordine), numero_partecipante: numero, valori: valori };
  });
  return { evento: { id: idEvento, titolo: String(evento.titolo || idEvento) }, sola_lettura: vistaEventoSolaLettura_(evento, colonne), profilo: profilo.id, nome_profilo: profilo.nome, personalizzata: !!vistaSalvata.length, conservata: false, colonne: colonne, righe: righe };
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

function determinaProfiloVistaOperativa_(iscrizioni, partecipanti, profiloEvento) {
  const profiliEspliciti = ['MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO'];
  let profiloEsplicito = profiliEspliciti.includes(profiloEvento) ? profiloEvento : '';
  if (!profiloEsplicito) iscrizioni.some(function (riga) {
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
    if (dati.document_number || dati.numero_documento || dati.document_issue_date || dati.data_rilascio_documento || dati.document_expiry_date || dati.scadenza_documento || dati.room || dati.camera || dati.alloggio) haDocumenti = true;
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
    { key: 'email', label: 'Email' }, { key: 'phone', label: 'Cellulare' }, { key: 'birth_date', label: 'Data di nascita' }, { key: 'document_type', label: 'Tipo documento' }, { key: 'document_number', label: 'Numero documento' }, { key: 'document_issue_date', label: 'Data di rilascio del documento' }, { key: 'document_expiry_date', label: 'Scadenza documento' }, { key: 'nationality', label: 'Nazionalità' }, { key: 'room', label: 'Alloggio' }, { key: 'transport', label: 'Pullman/trasporto' }, { key: 'breakfast', label: 'Colazione' },
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
  if (field === 'attendance') return ({PRESENT:'Presente',ABSENT:'Assente',UNRECORDED:'Non rilevata'})[data.attendance] || 'Non rilevata';
  if (field === 'status') return etichettaStatoIscrizione_(['CANCELLED', 'CANCELLED_PARTICIPANT'].indexOf(String(participant.stato_partecipante).toUpperCase()) >= 0 ? 'CANCELLED' : registration.stato);
  if (String(field).indexOf('option_')===0) {
    const option=decodificaElenco_(participant.opzioni_json).find(o=>'option_'+String(o.code)===field);
    return option ? Number(option.quantity)||0 : 0;
  }
  const aliases = { email: ['participant_email', 'email'], phone: ['participant_phone', 'phone', 'mobile'], birth_date: ['birth_date', 'data_nascita'], document_type: ['document_type', 'tipo_documento'], document_number: ['document_number', 'numero_documento'], document_issue_date: ['document_issue_date', 'data_rilascio_documento', 'data_emissione_documento'], document_expiry_date: ['document_expiry_date', 'document_expiry', 'scadenza_documento'], nationality: ['nationality', 'nazionalita'], room: ['room', 'camera', 'alloggio'], transport: ['pullman', 'transport'], breakfast: ['colazione', 'breakfast'], lunch: ['pranzo', 'lunch'], insurance: ['assicurazione', 'insurance'], emergency_contact: ['emergency_contact', 'emergency_phone', 'contatto_emergenza', 'telefono_emergenza'] };
  const direct = { event: event.titolo || registration.id_evento, order_code: registration.codice_ordine, participant_number: participant.numero_partecipante, first_name: participant.nome, last_name: participant.cognome, status: participant.stato_partecipante || registration.stato, special_requests: registration.richieste_particolari || '' };
  if (Object.prototype.hasOwnProperty.call(direct, field)) return direct[field];
  if (field === 'options') return decodificaElenco_(participant.opzioni_json).map(function (option) { return option.name || option.label || option.code || ''; }).filter(Boolean).join(', ');
  const pagamentiOrdine = payments.filter(function (payment) { return String(payment.codice_ordine) === String(registration.codice_ordine); });
  const sommaPagamenti = function (fonte) { return pagamentiOrdine.reduce(function (total, payment) { if (fonte && String(payment.fonte_pagamento).toUpperCase() !== fonte) return total; const amount = Number(payment.importo_centesimi) || 0; return total + (['RIMBORSO', 'STORNO'].indexOf(String(payment.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount); }, 0); };
  const economic = posizioneEconomicaRegistrazione_(registration, sommaPagamenti(''));
  if (field === 'total') return (Number(registration.totale_centesimi) || 0) / 100;
  if (field === 'paid') return economic.paid / 100;
  if (field === 'paid_cash') return sommaPagamenti('CONTANTE') / 100;
  if (field === 'paid_transfer') return sommaPagamenti('BONIFICO') / 100;
  if (field === 'paid_card') return sommaPagamenti('CARTA') / 100;
  if (field === 'balance') return economic.balance / 100;
  const candidates = aliases[field] || [field]; for (let index = 0; index < candidates.length; index += 1) if (data[candidates[index]] != null && data[candidates[index]] !== '') return data[candidates[index]];
  if (field === 'email') return registration.email_referente || ''; if (field === 'phone') return registration.telefono_referente || ''; return '';
}

function etichettaStatoIscrizione_(value) {
  const labels = { PENDING_PAYMENT: 'Da pagare', IN_ATTESA_PAGAMENTO: 'Da pagare', CONFIRMED: 'Confermata', CONFERMATA: 'Confermata', WAITLISTED: 'Lista d’attesa', WAITLIST_OFFERED: 'Posto proposto', CANCELLED: 'Annullata', EXPIRED: 'Scaduta', ACTIVE: 'Attiva', CANCELLED_PARTICIPANT: 'Annullata' };
  return labels[String(value || '').toUpperCase()] || String(value || '');
}

function aggiornaStatoOperativo_(orderCode, participantNumber, key, value, operator, operationId) {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.OPERATIONAL_STATE); const existing = convertiRigheInOggetti_(sheet).find(function (row) { return String(row.codice_ordine) === orderCode && Number(row.numero_partecipante) === participantNumber && String(row.chiave) === key; }); const values = [neutralizzaFormula_(orderCode, 64), participantNumber, neutralizzaFormula_(key, 80), neutralizzaFormula_(value, 1000), new Date(), neutralizzaFormula_(operator, 120), operationId];
  if (existing) sheet.getRange(existing._row, 1, 1, values.length).setValues([values]); else sheet.appendRow(values);
}

function decodificaOggetto_(value) { try { const parsed = JSON.parse(String(value || '{}')); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}; } catch (error) { return {}; } }
function decodificaElenco_(value) { try { const parsed = JSON.parse(String(value || '[]')); return Array.isArray(parsed) ? parsed : []; } catch (error) { return []; } }

/** Economic columns belong to the order, even in a person-based report. */
function limitaImportiAUnaRigaPerOrdine_(rows, fields) {
  const monetary = ['total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'];
  const seen = Object.create(null);
  rows.forEach(function (row) {
    const code = row._orderCode;
    if (!code) return;
    if (seen[code]) fields.forEach(function (field, index) { if (monetary.indexOf(field) >= 0) row[index] = ''; });
    seen[code] = true;
  });
  return rows;
}


// Sorgente: Setup.gs
function onOpen() {
  SpreadsheetApp.getUi().createMenu('Modulo iscrizioni')
    .addItem('Apri gestione web', 'apriGestioneWeb')
    .addItem('Inizializza/aggiorna struttura', 'configuraCartellaDiLavoro')
    .addSeparator()
    .addItem('Configura elenco operativo', 'apriConfigurazioneElencoOperativo')
    .addItem('Configura modelli report', 'apriConfigurazioneModelliReport')
    .addItem('Gestisci gruppi', 'apriGestioneGruppi')
    .addItem('Allinea gruppi con WordPress', 'sincronizzaGruppiConWordPress')
    .addItem('Configura collegamento WordPress', 'configuraEndpointWordPress')
    .addSeparator()
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
    ['Registra movimento','Inserimento pagamenti','Registra iscrizione'].forEach(function(nome) { const s=spreadsheet.getSheetByName(nome); if(s && spreadsheet.getSheets().length>1)spreadsheet.deleteSheet(s); });
		inizializzaGruppi_();
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
  if (sheet.getMaxColumns() < headers.length) sheet.insertColumnsAfter(sheet.getMaxColumns(), headers.length-sheet.getMaxColumns());
  const current = sheet.getRange(1, 1, 1, headers.length).getDisplayValues()[0];
  const hasData = sheet.getLastRow() > 1;
  const previous = MI_LEGACY_HEADERS[sheet.getName()] || [];
	const usesPreviousHeaders = previous.length > 0 && current.slice(0, previous.length).join('|') === previous.join('|') && current.slice(previous.length).every(function (value) { return value === ''; });
	const italianPrevious = MI_INTESTAZIONI_PRECEDENTI[sheet.getName()] || [];
	const usesItalianPrevious = italianPrevious.length > 0 && current.slice(0, italianPrevious.length).join('|') === italianPrevious.join('|') && current.slice(italianPrevious.length).every(function (value) { return value === ''; });
	const immediatelyPrevious = sheet.getName() === MI_SHEETS.EVENTS ? [headers.slice(0, -1), headers.slice(0, -2), headers.slice(0, -3)] : sheet.getName() === MI_SHEETS.PARTICIPANTS ? [headers.slice(0, -5), headers.slice(0, -7)] : ([MI_SHEETS.REGISTRATIONS, MI_SHEETS.PAYMENTS, MI_SHEETS.EVENTS].indexOf(sheet.getName()) >= 0 ? [headers.slice(0, -1)] : []);
	const usesImmediatelyPrevious = immediatelyPrevious.some(function (candidate) { return candidate.length > 0 && current.slice(0, candidate.length).join('|') === candidate.join('|') && current.slice(candidate.length).every(function (value) { return value === ''; }); });
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

function applicaProtezioniConAvviso_() {
  const editable = [MI_SHEETS.SECRETARY_OPERATIONS, MI_SHEETS.OPERATIONAL_VIEWS, MI_SHEETS.ACCOMMODATIONS];
  Object.keys(MI_HEADERS).forEach(function (name) {
    const sheet = ottieniSchedaObbligatoria_(name);
    sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).forEach(function (protection) { protection.remove(); });
    if (editable.indexOf(name) < 0) sheet.protect().setDescription('Gestito da Modulo Iscrizioni').setWarningOnly(true);
  });
}


// Sorgente: SincronizzazioneManuale.gs
/** Editable fields are data, never payment totals or booking identifiers. */
function campoModificabileFoglio_(key) {
  return /^[a-z][a-z0-9_]{0,79}$/.test(key) && !String(key).startsWith('option_') && !['_ordine','_numero','event','order_code','participant_number','status','options','total','paid','paid_cash','paid_transfer','paid_card','balance','special_requests','attendance','constructor','prototype'].includes(key);
}
function aggiungiColonneServizi_(columns, options) {
  (Array.isArray(options)?options:[]).forEach(option=>{
    if (option.scope!=='TICKET' || !/^[a-z0-9_-]{1,64}$/.test(String(option.code))) return;
    const key='option_'+option.code;
    if (!columns.some(column=>column.key===key)) columns.push({key:key,label:String(option.name||option.code),gruppo:'servizi',comprimibile:false});
  });
}
function confrontaModificheFoglio_(base, current) {
  const changes = [], errors = [], seen = new Set();
  current.forEach(row => {
    const identity = JSON.stringify([String(row.order), Number(row.number)]);
    if (seen.has(identity)) { errors.push('Partecipante duplicato nel foglio: ' + row.order); return; }
    seen.add(identity);
    const original = base[identity];
    if (!original) { errors.push('Riga senza prenotazione riconosciuta. Inserisci nuove iscrizioni dal portale.'); return; }
    Object.keys(original).forEach(key => {
      if (!Object.prototype.hasOwnProperty.call(row.values, key)) { errors.push('Colonna mancante: ' + key); return; }
      const before = String(original[key] ?? ''), after = String(row.values[key] ?? '');
      if (before !== after) {
        if (!campoModificabileFoglio_(key)) errors.push('Colonna non modificabile: ' + key);
        else changes.push({order_code:String(row.order),number:Number(row.number),key:key,before:before,after:after});
      }
    });
  });
  Object.keys(base).forEach(key => { if (!seen.has(key)) errors.push('Riga rimossa: annulla la partecipazione dal portale.'); });
  return {changes:changes,errors:errors};
}
function leggiBaseFoglio_(foglio) {
  const sheet = foglio.getSheetByName('_MI_BASE');
  if (!sheet) return null;
  const base = Object.create(null);
  if (sheet.getLastRow() < 2) return base;
  sheet.getRange(2,1,sheet.getLastRow()-1,3).getValues().forEach(row => {
    const key = JSON.stringify([String(row[0]),Number(row[1])]);
    if (base[key]) throw new Error('Base del foglio duplicata.');
    base[key] = JSON.parse(String(row[2]));
  });
  return base;
}
function modificheCorrentiFoglio_(sheet) {
  const base = leggiBaseFoglio_(sheet.getParent());
  if (!base) return {changes:[],errors:[],initialized:false};
  const columns = mappaColonneEvento_(sheet);
  if (!columns._ordine || !columns._numero) return {changes:[],errors:['Identificativi del foglio mancanti.'],initialized:true};
  const rows = sheet.getLastRow() > 1 ? sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues() : [];
  const current = rows.filter(r => r.some(v=>String(v)!=='')).map(row => {
    const values = {};
    Object.keys(columns).filter(key=>!['_ordine','_numero'].includes(key)).forEach(key=>values[key]=row[columns[key]-1]);
    return {order:String(row[columns._ordine-1]),number:Number(row[columns._numero-1]),values:values};
  });
  return Object.assign({initialized:true},confrontaModificheFoglio_(base,current));
}
function salvaBaseFoglio_(sheet) {
  const foglio = sheet.getParent();
  const base = foglio.getSheetByName('_MI_BASE') || foglio.insertSheet('_MI_BASE');
  const columns = mappaColonneEvento_(sheet);
  const rows = sheet.getLastRow()>1 ? sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues() : [];
  const values = rows.map(row => {
    const fields = {};
    Object.keys(columns).filter(key=>!['_ordine','_numero'].includes(key)).forEach(key=>fields[key]=row[columns[key]-1]);
    return [row[columns._ordine-1],Number(row[columns._numero-1]),JSON.stringify(fields)];
  });
  base.clearContents();
  base.getRange(1,1,1,3).setValues([['Prenotazione','Partecipante','Valori confermati']]);
  if (base.getMaxRows()<values.length+1) base.insertRowsAfter(base.getMaxRows(),values.length+1-base.getMaxRows());
  if (values.length) base.getRange(2,1,values.length,3).setValues(values);
  proteggiProiezione_(base);
  base.hideSheet();
}

/** Confirm only cells whose edited value has actually returned from MySQL. */
function allineaBaseConVista_(sheet, vista) {
  const pending = modificheCorrentiFoglio_(sheet);
  if (!pending.changes.length || pending.errors.length) return;
  const incoming = Object.create(null);
  vista.righe.forEach(row=>incoming[JSON.stringify([String(row.codice_ordine),Number(row.numero_partecipante)])]=row.valori);
  const confirmed = Object.create(null);
  pending.changes.forEach(change=>{
    const id=JSON.stringify([change.order_code,change.number]), values=incoming[id];
    if (values && Object.prototype.hasOwnProperty.call(values,change.key) && normalizzaTesto_(values[change.key],5000)===change.after) {
      if (!confirmed[id]) confirmed[id]={};
      confirmed[id][change.key]=change.after;
    }
  });
  const base=sheet.getParent().getSheetByName('_MI_BASE');
  if (!base || base.getLastRow()<2) return;
  base.getRange(2,1,base.getLastRow()-1,3).getValues().forEach((row,index)=>{
    const changes=confirmed[JSON.stringify([String(row[0]),Number(row[1])])];
    if (changes) base.getRange(index+2,3).setValue(JSON.stringify(Object.assign(JSON.parse(String(row[2])),changes)));
  });
}

function leggiModificheEventoMysql_(payload) {
  const eventId=String(payload.event_id||'');
  if (!/^[1-9][0-9]*$/.test(eventId)) throw new Error('Evento non valido.');
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  try {
    const record=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).find(r=>String(r.id_evento)===eventId);
    if (!record || !record.id_foglio) throw new Error('Foglio evento non disponibile.');
    const sheet=SpreadsheetApp.openById(String(record.id_foglio)).getSheetByName('Dati operativi');
    if (!sheet) throw new Error('Scheda Dati operativi non disponibile.');
    const result=modificheCorrentiFoglio_(sheet);
    if (!result.initialized) throw new Error('Aggiorna il foglio evento prima di sincronizzarlo.');
    return {ok:true,changes:result.changes,errors:result.errors};
  } finally {lock.releaseLock();}
}

/** Signed MySQL receipts normalize only the submitted cell, never a newer edit.
 * Keep the base unchanged: only the returning canonical view acknowledges it.
 */
function confermaModificheFoglio_(payload) {
  const eventId=String(payload.event_id||''), receipts=payload.confirmations;
  if (!/^[1-9][0-9]*$/.test(eventId) || !Array.isArray(receipts) || receipts.length>500) throw new Error('INVALID_SHEET_RECEIPT');
  const lock=LockService.getScriptLock(); lock.waitLock(30000);
  try {
    const record=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).find(r=>String(r.id_evento)===eventId);
    if (!record || !record.id_foglio) throw new Error('EVENT_SHEET_MISSING');
    const sheet=SpreadsheetApp.openById(String(record.id_foglio)).getSheetByName('Dati operativi');
    if (!sheet) throw new Error('EVENT_SHEET_MISSING');
    let updated=0;
    const protection=sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).find(p=>p.getDescription()==='MI_PROIEZIONE');
    const editable=protection?protection.getUnprotectedRanges():[];
    proteggiProiezione_(sheet); SpreadsheetApp.flush();
    try {
    const base=leggiBaseFoglio_(sheet.getParent()), columns=mappaColonneEvento_(sheet);
    if (!base || !columns._ordine || !columns._numero) throw new Error('SHEET_BASE_MISSING');
    const rows=sheet.getLastRow()>1?sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues():[];
    receipts.forEach(receipt=>{
      if (!campoModificabileFoglio_(receipt.key) || !columns[receipt.key] || typeof receipt.accepted!=='string') return;
      const identity=JSON.stringify([String(receipt.order_code),Number(receipt.number)]);
      if (!base[identity] || String(base[identity][receipt.key]??'')!==receipt.before) return;
      const matches=rows.map((row,index)=>({row,index})).filter(item=>String(item.row[columns._ordine-1])===String(receipt.order_code) && Number(item.row[columns._numero-1])===Number(receipt.number));
      if (matches.length!==1) return;
      const cell=sheet.getRange(matches[0].index+2,columns[receipt.key]);
      if (cell.getDisplayValue()!==receipt.after) return;
      cell.setNumberFormat('@').setValue(neutralizzaFormula_(receipt.accepted,5000)); updated++;
    }); SpreadsheetApp.flush();
    } finally {proteggiProiezione_(sheet,editable);}
    return {ok:true,updated:updated};
  } finally {lock.releaseLock();}
}

/** Verify the exact MySQL revisions, then refresh the event under the same lock. */
function preparaAperturaFoglio_(payload) {
  const eventId=String(payload.event_id||''), expected=payload.registrations;
  if (!/^[1-9][0-9]*$/.test(eventId) || !Array.isArray(expected)) throw new Error('INVALID_OPEN_REQUEST');
  const lock=LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:true,ready:false,busy:true,retry_after:3};
  try {
    if (typeof eventoInEliminazione_==='function' && eventoInEliminazione_(eventId)) throw new Error('EVENT_DELETED');
    const rows=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(row=>String(row.id_evento)===eventId);
    const codes=new Set(expected.map(row=>String(row.order_code)));
    if (codes.size!==expected.length || rows.some(row=>!codes.has(String(row.codice_ordine)))) throw new Error('REPLICA_MISMATCH');
    const needs=expected.filter(row=>{
      const found=rows.filter(value=>String(value.codice_ordine)===String(row.order_code));
      return found.length!==1 || String(found[0].workspace_revision)!==String(row.revision) || String(found[0].replica_completa_revision)!==String(row.revision);
    }).map(row=>String(row.order_code));
    if (needs.length) return {ok:true,ready:false,needs_sync:needs};
    sincronizzaCamereMysql_(eventId,payload.rooms,payload.workspace_event_revision);
    const revision=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REPLICA_REVISIONS)).find(row=>String(row.id_evento)===eventId);
    if (!revision || String(revision.revisione_camere)!==String(payload.workspace_event_revision)) throw new Error('REPLICA_MISMATCH');
    if (payload.operational_profile !== undefined) aggiornaProfiloEventoMysql_(eventId, payload.operational_profile);
    if (payload.event_schema !== undefined) aggiornaSchemaEventoMysql_(eventId, payload.event_schema);
    const result=aggiornaFoglioOperativoEventoConLock_({id_evento:eventId,soloModificati:true});
    const complete=!!result.ok && !!result.esito && !result.esito.manuali && !result.esito.conflitti;
    SpreadsheetApp.flush();
    return {ok:true,ready:complete,event_sheet_complete:complete,event_schema:payload.event_schema,operational_profile:payload.operational_profile,url_foglio:complete?result.url_foglio:undefined};
  } finally {lock.releaseLock();}
}

/** Called only under the script lock, from an authenticated WordPress request. */
function aggiornaProfiloEventoMysql_(eventId, profile) {
  if (!['MINIMO','QUOTA_UNICA','SERVIZI_MULTIPLI','VIAGGIO_COMPLESSO'].includes(profile)) throw new Error('INVALID_OPERATIONAL_PROFILE');
  const sheet=ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const row=convertiRigheInOggetti_(sheet).find(value=>String(value.id_evento)===eventId);
  if (!row) throw new Error('EVENT_NOT_FOUND');
  if (String(row.profilo_operativo || '') === profile) return;
  if (sheet.getMaxColumns()<12) sheet.insertColumnsAfter(sheet.getMaxColumns(),12-sheet.getMaxColumns());
  sheet.getRange(1,12).setValue('profilo_operativo');
  sheet.getRange(row._row,12).setValue(profile);
}

function aggiornaSchemaEventoMysql_(eventId, schema) {
  if (!schema || !Array.isArray(schema.fields) || !Array.isArray(schema.options) || typeof schema.room !== 'boolean' || typeof schema.pricing !== 'string') throw new Error('INVALID_EVENT_SCHEMA');
  const sheet=ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const row=convertiRigheInOggetti_(sheet).find(value=>String(value.id_evento)===eventId);
  if (!row) throw new Error('EVENT_NOT_FOUND');
  if (serializzaInModoStabile_(decodificaOggetto_(row.schema_vista_json)) === serializzaInModoStabile_(schema) && String(row.modalita_prezzo) === schema.pricing) return;
  if (sheet.getMaxColumns()<13) sheet.insertColumnsAfter(sheet.getMaxColumns(),13-sheet.getMaxColumns());
  sheet.getRange(1,13).setValue('schema_vista_json');
  sheet.getRange(row._row,13).setValue(JSON.stringify(schema));
  sheet.getRange(row._row,8).setValue(schema.pricing);
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
    if (!verified.ok) return creaRispostaJson_(verified.error === 'WORKSPACE_BUSY' && envelope.action === 'PREPARA_APERTURA_FOGLIO' ? {ok:true,ready:false,busy:true,retry_after:3} : { ok: false, error: verified.error });
    if (envelope.action === 'ELIMINA_DATI_EVENTO') return creaRispostaJson_(eliminaDatiEventoDaWordPress_(envelope.payload));
    if (envelope.action === 'PING') return creaRispostaJson_({ ok: true, service: 'modulo-iscrizioni-workspace', schema_version: MI_SCHEMA_VERSION, mode: 'PREVIEW' });
	if (envelope.action === 'STATO_SCHEMA') return creaRispostaJson_({ ok: true, schema_version: MI_SCHEMA_VERSION, registration_headers: MI_HEADERS[MI_SHEETS.REGISTRATIONS], participant_headers: MI_HEADERS[MI_SHEETS.PARTICIPANTS], accommodation_headers: MI_HEADERS[MI_SHEETS.ACCOMMODATIONS], group_headers: MI_HEADERS[MI_SHEETS.GROUPS], report_template_headers: MI_HEADERS[MI_SHEETS.REPORT_TEMPLATES], event_headers: MI_HEADERS[MI_SHEETS.EVENTS], mode: 'PREVIEW' });
	if (envelope.action === 'STATO_REPLICA_ISCRIZIONE') return creaRispostaJson_(statoReplicaIscrizione_(envelope.payload));
	if (envelope.action === 'PREPARA_PRODUZIONI_EVENTO') return creaRispostaJson_(preparaProduzioniEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'VERIFICA_FOGLIO_EVENTO') return creaRispostaJson_(verificaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'VERIFICA_FOGLI_EVENTO') return creaRispostaJson_(verificaFogliEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ARCHIVIA_FOGLIO_EVENTO') return creaRispostaJson_(archiviaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ORGANIZZA_FOGLI_EVENTO') return creaRispostaJson_(organizzaFogliEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'ELIMINA_FOGLIO_EVENTO') return creaRispostaJson_(eliminaFoglioEventoDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_PROVA') return creaRispostaJson_(inviaEmailProvaDaWordPress_(envelope.payload));
	if (envelope.action === 'INVIA_EMAIL_CONFERMA') return creaRispostaJson_(inviaEmailConfermaDaWordPress_(envelope.payload));
	if (envelope.action === 'STATO_CANALE_EMAIL') return creaRispostaJson_(statoCanaleEmail_());
    if (envelope.action === 'REGISTRA_PAGAMENTO_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_PAYMENT_LEDGER'});
    if (envelope.action === 'LEGGI_MODIFICHE_FOGLIO') return creaRispostaJson_(leggiModificheEventoMysql_(envelope.payload));
    if (envelope.action === 'CONFERMA_MODIFICHE_FOGLIO') return creaRispostaJson_(confermaModificheFoglio_(envelope.payload));
    if (envelope.action === 'PREPARA_APERTURA_FOGLIO') return creaRispostaJson_(preparaAperturaFoglio_(envelope.payload));
    if (envelope.action === 'SCHEDA_GESTIONE_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_MANAGEMENT'});
    if (envelope.action === 'AGGIORNA_GESTIONE_PORTALE') return creaRispostaJson_({ok:false,error:'USE_MYSQL_MANAGEMENT'});
    if (envelope.action !== 'APPEND_REGISTRATION') return creaRispostaJson_({ ok: false, error: 'ACTION_NOT_ALLOWED' });
    return creaRispostaJson_(aggiungiIscrizione_(envelope.payload));
  } catch (error) {
		console.error('WEBAPP_REQUEST_FAILED', error && error.stack ? error.stack : String(error));
		try { aggiungiControllo_('WEBAPP_REQUEST', 'REQUEST', 'UNAVAILABLE', 'ERROR', 'WORDPRESS', 'UNHANDLED_ERROR', 'WORDPRESS_PROXY'); } catch (auditError) {}
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
  // Only authenticated requests can pause background projections. A short lease
  // gives the interactive opener a turn after the current writer finishes.
  if (envelope.action === 'PREPARA_APERTURA_FOGLIO') PropertiesService.getScriptProperties().setProperty('MI_INTERACTIVE_OPEN_UNTIL', String(Date.now()+90000));
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:false,error:'WORKSPACE_BUSY'};
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
    if (existing) registrations.getRange(existing._row, 1, 1, registrationValues.length).setValues([registrationValues]);
    else registrations.appendRow(registrationValues);

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
    eliminaRigheContigue_(participantSheet, convertiRigheInOggetti_(participantSheet).filter(row => String(row.codice_ordine) === orderCode));
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
    const messageValues = [message ? message.id_messaggio : creaIdentificativoOpaco_('msg'), neutralizzaFormula_(orderCode, 64), neutralizzaFormula_(originalRecipient, 254), 'REGISTRATION_CONFIRMATION', JSON.stringify({ order_code: orderCode, status: originalStatus }), message ? message.stato : 'PREVIEW', message && message.data_creazione ? message.data_creazione : new Date()];
    if (message) outbox.getRange(message._row, 1, 1, messageValues.length).setValues([messageValues]); else outbox.appendRow(messageValues);
    sincronizzaPagamenti_(orderCode, payload.payments);
    const registrationComplete = convertiRigheInOggetti_(registrations).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.chiave_idempotenza) === idempotencyKey && String(row.hash_revisione_evento) === revisionHash && String(row.snapshot_json) === snapshotJson; });
    const participantCount = convertiRigheInOggetti_(participantSheet).filter(function (row) { return String(row.codice_ordine) === orderCode; }).length;
    const outboxComplete = convertiRigheInOggetti_(outbox).some(function (row) { return String(row.codice_ordine) === orderCode && String(row.tipo_modello) === 'REGISTRATION_CONFIRMATION' && String(row.destinatario) === originalRecipient; });
    const complete = registrationComplete && participantCount === participants.length && outboxComplete;
    if (complete) {
      const saved = convertiRigheInOggetti_(registrations).find(row=>String(row.codice_ordine)===orderCode);
      registrations.getRange(saved._row,registrationValues.length,1,1).setValues([[workspaceRevision]]);
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
  if (values.length) sheet.getRange(sheet.getLastRow()+1, 1, values.length, values[0].length).setValues(values);
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
    const effectiveDate = effective ? new Date(stableId && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(effective) ? effective.replace(' ', 'T') + 'Z' : effective) : new Date();
    if (isNaN(effectiveDate.getTime())) {
      aggiungiControllo_('SYNC_PAYMENT', 'PAYMENT', orderCode, 'REJECTED', 'WORDPRESS', 'INVALID_EFFECTIVE_AT', 'WORDPRESS_PROXY');
      throw new Error('INVALID_EFFECTIVE_AT');
    }
    const reference = normalizzaTesto_(payment.external_reference, 120);
    const origin = stableId ? 'MYSQL|' + orderCode + '|' + stableId : 'WP|' + orderCode + '|' + kind + '|' + installment + '|' + effective + '|' + amount + '|' + source + '|' + reference;
    const duplicate = existing.find(function (row) { return String(row.id_inserimento_origine) === origin; });
    if (duplicate) {
	  if (stableId && (String(duplicate.tipo_movimento) !== kind || Number(duplicate.importo_centesimi) !== amount || String(duplicate.fonte_pagamento) !== source || new Date(duplicate.data_effettiva).getTime() !== effectiveDate.getTime() || String(duplicate.attribuzioni_partecipanti_json || '') !== allocationsJson)) throw new Error('PAYMENT_ID_CONFLICT');
	  return;
	}
	sheet.appendRow([creaIdentificativoOpaco_('pay'), neutralizzaFormula_(orderCode, 64), kind, installment, effectiveDate, amount, 'EUR', source, neutralizzaFormula_(reference, 120), neutralizzaFormula_(payment.operator_label, 100), 'WORDPRESS', origin, new Date(), neutralizzaFormula_(payment.administrative_note, 500), allocationsJson]);
	existing.push({ id_inserimento_origine: origin, tipo_movimento: kind, importo_centesimi: amount, fonte_pagamento: source, data_effettiva: effectiveDate, attribuzioni_partecipanti_json: allocationsJson });
  });
}
