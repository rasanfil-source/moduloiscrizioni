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
  riprendiScritturaProiezione_(scheda);
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
  const result=scriviVistaIncrementale_(scheda,vista);
  if(!result.scritto)proteggiProiezione_(scheda,intervalliPrecedenti);
  return result;
  } catch(error) {
    // Do not reopen cells while a journal still needs to finish writing them.
    if(!PropertiesService.getScriptProperties().getProperty('MI_WRITE_'+scheda.getParent().getId()))proteggiProiezione_(scheda, intervalliPrecedenti);
    throw error;
  }
}
