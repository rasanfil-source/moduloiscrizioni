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
  scheda.getRange('A1').setValue('Gestione evento').setFontSize(20).setFontWeight('bold');
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
  const colonne = [{key:'_ordine',label:'Prenotazione'}, {key:'_numero',label:'Partecipante'}].concat(vista.colonne);
  if (scheda.getMaxColumns() < colonne.length) scheda.insertColumnsAfter(scheda.getMaxColumns(), colonne.length - scheda.getMaxColumns());
  const rows = vista.righe.map(function (r) { return [r.codice_ordine, r.numero_partecipante].concat(vista.colonne.map(function (c) { return neutralizzaFormula_(r.valori[c.key], 5000); })); });
  if (scheda.getMaxRows() < rows.length + 1) scheda.insertRowsAfter(scheda.getMaxRows(), rows.length + 1 - scheda.getMaxRows());
  scheda.getRange(1,1,1,colonne.length).setValues([colonne.map(function (c) {return c.label;})]).setFontWeight('bold');
  colonne.forEach(function (c,i) { identificaColonnaEvento_(scheda,i+1,c.key); });
  if (rows.length) scheda.getRange(2,1,rows.length,colonne.length).setValues(rows);
  scheda.setFrozenRows(1);
  const modificabili = [];
  colonne.forEach(function(c,i) {
    if (campoModificabileFoglio_(c.key) && rows.length) {
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
