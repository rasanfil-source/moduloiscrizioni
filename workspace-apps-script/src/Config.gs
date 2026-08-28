const MI_SCHEMA_VERSION = '1.6.0';
const MI_SHEETS = Object.freeze({
  CONFIG: 'Configurazione',
  EVENTS: 'Eventi',
  REGISTRATIONS: 'Iscrizioni',
  PARTICIPANTS: 'Partecipanti',
  PAYMENT_INTAKE: 'Inserimento pagamenti',
  PAYMENTS: 'Pagamenti',
  EMAIL_OUTBOX: 'Coda email',
  SECRETARY_OPERATIONS: 'Operazioni segreteria',
  OPERATIONAL_STATE: 'Stato operativo',
  EVENT_WORKSPACES: 'Fogli iniziative',
  OPERATIONAL_VIEWS: 'Viste operative',
  OPERATIONAL_LIST: 'Elenco operativo',
  ACCOMMODATIONS: 'Sistemazioni',
  AUDIT_LOG: 'Registro controlli'
});

const MI_HEADERS = Object.freeze({
  'Configurazione': ['chiave', 'valore', 'descrizione'],
  'Eventi': ['id_evento', 'id_attivita', 'titolo', 'stato', 'capienza', 'apertura_iscrizioni', 'chiusura_iscrizioni', 'modalita_prezzo', 'data_aggiornamento'],
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
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) throw new Error('Il progetto deve essere associato a un Google Sheet.');
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
