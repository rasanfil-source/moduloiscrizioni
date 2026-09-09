/** I fogli evento contengono soltanto proiezioni consultabili. */
function eventoPrevedeMovimenti_(idEvento) {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).some(function (r) { return String(r.id_evento) === String(idEvento) && Number(r.totale_centesimi) > 0; });
}
function configuraSchedeEconomicheEvento_(foglio, idEvento) {
  const modulo = foglio.getSheetByName('Registra movimento');
  if (modulo && foglio.getSheets().length > 1) foglio.deleteSheet(modulo);
  ScriptApp.getProjectTriggers().filter(function (t) { return t.getHandlerFunction() === 'gestisciModificaInterfacciaMovimentoEvento' && String(t.getTriggerSourceId()) === String(foglio.getId()); }).forEach(function (t) { ScriptApp.deleteTrigger(t); });
  preparaPagamentiEvento_(foglio, idEvento);
  preparaAccessoGestioneEvento_(foglio, idEvento);
}
