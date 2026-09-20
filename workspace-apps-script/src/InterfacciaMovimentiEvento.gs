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
