/** Solo dati fittizi. Non registra eventi o iscrizioni nel database di produzione.
 * Verifica Google e la proiezione reale; NON sostituisce il collaudo MySQL end-to-end.
 */
function collaudaAccessoFoglio147() {
  const props=PropertiesService.getScriptProperties();
  const previous=props.getProperty('MI_TEST_ACCESS_SHEET_147');
  const book=previous?SpreadsheetApp.openById(previous):SpreadsheetApp.create('COLLAUDO ACCESSO 147 - SOLO DATI FITTIZI');
  props.setProperty('MI_TEST_ACCESS_SHEET_147',book.getId());
  const sheet=book.getSheets()[0];sheet.setName('Dati operativi');
  const file=DriveApp.getFileById(book.getId());
  file.setSharing(DriveApp.Access.ANYONE_WITH_LINK,DriveApp.Permission.VIEW);
  const view={sola_lettura:false,colonne:[{key:'first_name',label:'Nome'},{key:'balance',label:'Residuo'}],righe:[{codice_ordine:'TEST-ACCESSO-147',numero_partecipante:1,valori:{first_name:'COLLAUDO147ANONIMO',balance:100}}]};
  scriviProiezioneEvento_(sheet,view);
  SpreadsheetApp.flush();
  const protection=sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).find(p=>p.getDescription()==='MI_PROIEZIONE');
  if(protection.getUnprotectedRanges().map(r=>r.getA1Notation()).join(',')!=='B2')throw Error('Intervalli modificabili errati');
  if(file.getSharingAccess()!==DriveApp.Access.ANYONE_WITH_LINK||file.getSharingPermission()!==DriveApp.Permission.VIEW)throw Error('Permessi link errati');
  const response=UrlFetchApp.fetch('https://docs.google.com/spreadsheets/d/'+book.getId()+'/export?format=csv&gid='+sheet.getSheetId(),{muteHttpExceptions:true});
  if(response.getResponseCode()!==200||response.getContentText().indexOf('COLLAUDO147ANONIMO')<0)throw Error('Lettura anonima non confermata: '+response.getResponseCode());
  console.log(JSON.stringify({google_read_without_credentials:true,editable_range:'B2',url:book.getUrl(),mysql_tested:false}));
}
