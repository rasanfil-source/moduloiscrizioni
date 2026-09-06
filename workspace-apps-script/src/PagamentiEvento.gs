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

function proiettaPagamentiEvento_(scheda, mappa, ordini) {
  const pagamenti = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(function (movimento) { return ordini.some(function (ordine) { return String(ordine.codice_ordine) === String(movimento.codice_ordine); }); });
  const fonti = { CONTANTE: 'Contanti', BONIFICO: 'Bonifico', CARTA: 'Carta/PayPal' };
  const causali = { INTERO: 'Intero', CAPARRA: 'Caparra', INTERMEDIO: 'Intermedio', SALDO: 'Saldo', NON_ASSEGNATO: 'Non assegnato' };
  const locali = {};
  for (let numero = 2; numero <= scheda.getLastRow(); numero += 1) {
    const riga = scheda.getRange(numero, 1, 1, scheda.getLastColumn()).getValues()[0];
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
      acquisisciPagamentiEvento_(foglio, String(riga.id_evento));
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
