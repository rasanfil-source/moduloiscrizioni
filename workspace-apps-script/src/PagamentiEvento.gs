/** Storico consultabile: nessuna riga locale viene acquisita. */
function preparaPagamentiEvento_(foglio, idEvento) {
  return foglio.getSheetByName('Pagamenti') || foglio.insertSheet('Pagamenti');
}
function aggiornaProiezionePagamentiEvento_(foglio, idEvento) {
  const lock=LockService.getScriptLock(); lock.waitLock(30000);
  try { return aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento); }
  finally {lock.releaseLock();}
}
function aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento) {
    const s=preparaPagamentiEvento_(foglio,idEvento);
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
function aggiornaProiezionePagamentiPrenotazioneEvento_(foglio,idEvento,codice) {return aggiornaProiezionePagamentiEvento_(foglio,idEvento);}
function sincronizzaFogliEventi() {
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
      const risultato = aggiornaFoglioOperativoEvento({ id_evento: String(riga.id_evento), soloModificati: true });
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
