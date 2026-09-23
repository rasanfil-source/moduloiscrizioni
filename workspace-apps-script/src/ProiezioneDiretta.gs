/** MySQL is authoritative; this module materializes a signed event snapshot directly. */
function decodificaProiezioneDiretta_(payload) {
  const encoded=String((payload||{}).projection_gzip||''), expected=String((payload||{}).projection_hash||'').toLowerCase();
  if (!encoded || encoded.length>8000000 || !/^[a-f0-9]{64}$/.test(expected)) throw new Error('INVALID_EVENT_PROJECTION');
  let json;
  try {
    const bytes=Utilities.base64Decode(encoded);
    json=Utilities.ungzip(Utilities.newBlob(bytes,'application/gzip','event-projection.gz')).getDataAsString('UTF-8');
  } catch(error) { throw new Error('INVALID_EVENT_PROJECTION'); }
  if (json.length>12000000) throw new Error('EVENT_PROJECTION_TOO_LARGE');
  const actual=Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,json,Utilities.Charset.UTF_8).map(value=>('0'+(value&255).toString(16)).slice(-2)).join('');
  if (!confrontaInTempoCostante_(actual,expected)) throw new Error('INVALID_EVENT_PROJECTION_HASH');
  let projection;try{projection=JSON.parse(json);}catch(error){throw new Error('INVALID_EVENT_PROJECTION');}
  if (!projection || typeof projection!=='object' || Array.isArray(projection)) throw new Error('INVALID_EVENT_PROJECTION');
  return projection;
}

/** Pull oversized snapshots through the separately signed Workspace → WordPress API. */
function caricaProiezioneDiretta_(payload) {
  if ((payload||{}).projection_pull!==true) return decodificaProiezioneDiretta_(payload);
  if ((payload||{}).projection_gzip) throw new Error('INVALID_EVENT_PROJECTION');
  const expected=String(payload.projection_hash||'').toLowerCase(), fingerprint=String(payload.fingerprint||'').toLowerCase();
  if(!/^[a-f0-9]{64}$/.test(expected)||!/^[a-f0-9]{64}$/.test(fingerprint))throw new Error('INVALID_EVENT_PROJECTION');
  const result=inviaComandoWordPress_('GET_EVENT_PROJECTION',{event_id:String(payload.event_id||''),projection_hash:expected,fingerprint:fingerprint});
  if(!result||result.ok!==true||String(result.projection_hash||'').toLowerCase()!==expected)throw new Error('INVALID_EVENT_PROJECTION_HASH');
  return decodificaProiezioneDiretta_({projection_gzip:result.projection_gzip,projection_hash:expected});
}

function validaProiezioneDiretta_(eventId, projection) {
  const event=projection.event||{}, registrations=projection.registrations, participants=projection.participants, payments=projection.payments;
  if (String(event.id_evento)!==eventId || !Array.isArray(registrations) || !Array.isArray(participants) || !Array.isArray(payments) || registrations.length>10000 || participants.length>50000 || payments.length>100000) throw new Error('INVALID_EVENT_PROJECTION');
  const orders=new Set();
  registrations.forEach(row=>{const code=String(row.codice_ordine||'');if(!/^[A-Za-z0-9_-]{3,64}$/.test(code)||String(row.id_evento)!==eventId||orders.has(code)||!/^(0|[1-9][0-9]{0,19})$/.test(String(row.workspace_revision)))throw new Error('INVALID_EVENT_PROJECTION');orders.add(code);});
  const identities=new Set();
  participants.forEach(row=>{const id=JSON.stringify([String(row.codice_ordine||''),Number(row.numero_partecipante)]);if(!orders.has(String(row.codice_ordine))||!Number.isInteger(Number(row.numero_partecipante))||Number(row.numero_partecipante)<1||identities.has(id))throw new Error('INVALID_EVENT_PROJECTION');identities.add(id);});
  payments.forEach(row=>{if(!orders.has(String(row.codice_ordine))||!String(row.id_pagamento||''))throw new Error('INVALID_EVENT_PROJECTION');});
}

function generaVistaDaProiezioneDiretta_(projection) {
  const cache=creaCacheDecodificaVista_();
  const evento=projection.event, idEvento=String(evento.id_evento), iscrizioni=projection.registrations, partecipanti=projection.participants, pagamenti=projection.payments;
  const iscrizioniPerCodice=iscrizioni.reduce((index,row)=>{index[String(row.codice_ordine)]=row;return index;},{});
  const attive=partecipanti.filter(row=>iscrizioniPerCodice[String(row.codice_ordine)] && !['ANNULLATO','SCADUTO','CANCELLED','EXPIRED'].includes(String(iscrizioniPerCodice[String(row.codice_ordine)].stato).toUpperCase()) && String(row.stato_partecipante||'ACTIVE').toUpperCase()!=='CANCELLED');
  const profilo=determinaProfiloVistaOperativa_(iscrizioni,attive,evento.profilo_operativo,cache);
  const catalogo=campiElencoOperativo_(true,attive,cache).reduce((index,field)=>{index[field.key]=field;return index;},{});
  const colonne=profilo.campi.filter(key=>!!catalogo[key]).map(key=>({key:key,label:catalogo[key].label,gruppo:gruppoCampoVistaOperativa_(key),comprimibile:['paid_cash','paid_transfer','paid_card'].includes(key)}));
  aggiungiColonneServizi_(colonne,decodificaElenco_(evento.servizi_json));
  aggiungiColonneDomande_(colonne,evento,iscrizioni,attive,cache);
  applicaSchemaColonneEvento_(colonne,evento,iscrizioni,attive,pagamenti,cache);
  anteponiColonnaProgressiva_(colonne);
  const ordiniEconomici=new Set();
  const righe=attive.map((persona,indice)=>{
    const iscrizione=iscrizioniPerCodice[String(persona.codice_ordine)], dati=cache.object(persona.dati_aggiuntivi_json), valori={};
    colonne.forEach(colonna=>valori[colonna.key]=valoreCampoElenco_(colonna.key,evento,iscrizione,persona,dati,pagamenti,cache));
    if(Object.prototype.hasOwnProperty.call(valori,'participant_number'))valori.participant_number=indice+1;
    const personale={total:'totale_centesimi',paid:'versato_centesimi',balance:'saldo_centesimi'};
    Object.keys(personale).forEach(key=>{const amount=persona[personale[key]];if(amount!==''&&amount!=null&&Number.isFinite(Number(amount)))valori[key]=Number(amount)/100;else if(ordiniEconomici.has(String(persona.codice_ordine)))valori[key]='';});
    if(ordiniEconomici.has(String(persona.codice_ordine)))['paid_cash','paid_transfer','paid_card'].forEach(key=>valori[key]='');
    ordiniEconomici.add(String(persona.codice_ordine));
    return {codice_ordine:String(persona.codice_ordine),numero_partecipante:Number(persona.numero_partecipante),valori:valori,workspace_revision:String(iscrizione.workspace_revision||'')};
  });
  return {evento:{id:idEvento,titolo:String(evento.titolo||idEvento)},sola_lettura:vistaEventoSolaLettura_(evento,colonne),profilo:profilo.id,nome_profilo:profilo.nome,personalizzata:false,conservata:false,colonne:colonne,righe:righe};
}

function apriFoglioEventoFirmato_(payload, create, title) {
  const eventId=String((payload||{}).event_id||''), properties=PropertiesService.getScriptProperties(), registryKey='MI_DIRECT_SHEET_'+eventId, explicit=String((payload||{}).sheet_id||'');
  let requested=String(explicit||properties.getProperty(registryKey)||'');
  if(!/^[1-9][0-9]*$/.test(eventId)|| (requested && !/^[A-Za-z0-9_-]{20,}$/.test(requested)))throw new Error('INVALID_EVENT_SHEET');
  let book,created=false;
  if(requested){try{const file=DriveApp.getFileById(requested);if(file.isTrashed())throw new Error('EVENT_SHEET_MISSING');book=SpreadsheetApp.openById(requested);}catch(error){if(explicit||!create)throw error;properties.deleteProperty(registryKey);requested='';}}
  if(!book){if(!create)throw new Error('EVENT_SHEET_MISSING');book=SpreadsheetApp.create('Evento '+eventId+' - '+String(title||eventId).replace(/[\\/:*?"<>|#%{}]/g,' ').replace(/\s+/g,' ').trim().slice(0,140));properties.setProperty(registryKey,book.getId());spostaFoglioAccantoAlDatabase_(book.getId());created=true;}
  let sheet=book.getSheetByName('Dati operativi');
  if(!sheet){sheet=book.getSheets()[0];sheet.setName('Dati operativi');}
  const metadata=sheet.getDeveloperMetadata().find(item=>item.getKey()==='MI_ID_EVENTO');
  if(metadata && metadata.getValue()!==eventId)throw new Error('EVENT_SHEET_MISMATCH');
  if(!metadata)sheet.addDeveloperMetadata('MI_ID_EVENTO',eventId);
  if(properties.getProperty(registryKey)!==book.getId())properties.setProperty(registryKey,book.getId());
  return {book:book,sheet:sheet,created:created,eventId:eventId};
}

function aggiornaPagamentiDaProiezione_(book, projection, readOnly) {
  const schema=decodificaOggetto_(projection.event.schema_vista_json), payments=projection.payments;
  const props=PropertiesService.getScriptProperties(),management=book.getSheetByName('Gestione evento');
  const managementKey='MI_DIRECT_MANAGEMENT_'+book.getId(),managementVersion=String(props.getProperty('MI_WORDPRESS_COMMAND_URL')||'');
  if(readOnly){if(management&&!management.isSheetHidden())management.hideSheet();}
  else {
    if(management&&management.isSheetHidden())management.showSheet();
    if(!management||props.getProperty(managementKey)!==managementVersion){preparaAccessoGestioneEvento_(book,String(projection.event.id_evento));props.setProperty(managementKey,managementVersion);}
  }
  let sheet=book.getSheetByName('Pagamenti');
  if(schema.pricing==='ZERO'&&!payments.length){if(sheet&&book.getSheets().some(s=>s.getName()!=='Pagamenti'&&!s.isSheetHidden()))sheet.hideSheet();return;}
  const created=!sheet;
  sheet=sheet||book.insertSheet('Pagamenti');if(sheet.isSheetHidden())sheet.showSheet();
  const headers=['Movimento','Prenotazione','Data','Tipo','Importo (€)','Metodo','Riferimento','Operatore','Nota'];
  const rows=payments.map(row=>[row.id_pagamento,row.codice_ordine,row.data_effettiva,row.tipo_movimento,(Number(row.importo_centesimi)||0)/100,row.fonte_pagamento,row.riferimento_esterno,row.etichetta_operatore,row.nota_amministrativa].map(value=>typeof value==='string'?neutralizzaFormula_(value,5000):value));
  estendiGrigliaProiezione_(sheet,rows.length+1,headers.length);
  const oldCount=Math.max(0,sheet.getLastRow()-1),old=oldCount?sheet.getRange(2,1,oldCount,headers.length).getValues():[];
  if(!righeProiezioneUguali_(sheet.getRange(1,1,1,headers.length).getValues()[0],headers))sheet.getRange(1,1,1,headers.length).setValues([headers]).setFontWeight('bold');
  const blocks=blocchiRigheProiezione_(old,rows,2);
  blocks.forEach(block=>{sheet.getRange(block.row,1,block.values.length,headers.length).setNumberFormat('@').setValues(block.values);sheet.getRange(block.row,5,block.values.length,1).setNumberFormat('#,##0.00');});
  if(oldCount>rows.length)sheet.getRange(rows.length+2,1,oldCount-rows.length,headers.length).clearContent();
  if(created){sheet.setFrozenRows(1);proteggiProiezione_(sheet);}
}

function proiettaEventoDaWordPress_(payload) {
  const started=Date.now(), timings={};
  const projection=caricaProiezioneDiretta_(payload), eventId=String((payload||{}).event_id||'');validaProiezioneDiretta_(eventId,projection);
  timings.load_ms=Date.now()-started;
  const lock=LockService.getScriptLock();if((payload||{}).background===true){if(!lock.tryLock(100))return {ok:true,ready:false,busy:true,retry_after:3};}else if(!lock.tryLock(1000))return {ok:true,ready:false,busy:true,retry_after:3};
  const lockedAt=Date.now();timings.lock_wait_ms=lockedAt-started-timings.load_ms;
  try{
    if(typeof eventoInEliminazione_==='function'&&eventoInEliminazione_(eventId))throw new Error('EVENT_DELETED');
    verificaGenerazioneProiezione_(eventId,payload);
    const opened=apriFoglioEventoFirmato_(payload,true,projection.event.titolo), properties=PropertiesService.getScriptProperties(), key='MI_DIRECT_VIEW_'+opened.book.getId(), fingerprint=String(payload.fingerprint||'');
    // Older receipts contain only the fingerprint: rebuild once to learn read_only.
    let receipt;try{receipt=JSON.parse(properties.getProperty(key)||'null');}catch(error){receipt=null;}
    if(typeof riprendiScritturaProiezione_==='function')riprendiScritturaProiezione_(opened.sheet);
    abilitaLetturaFoglioEventoConLink_(opened.book.getId());
    if((!receipt||receipt.layout_version!==2)&&typeof riparaProgressivoSuIdentitaLegacy_==='function')riparaProgressivoSuIdentitaLegacy_(opened.sheet);
    const pending=modificheCorrentiFoglio_(opened.sheet);
    let result={aggiunte:0,manuali:pending.changes.length,conflitti:pending.errors.length};
    const changed=!receipt||receipt.fingerprint!==fingerprint||receipt.layout_version!==2||typeof receipt.read_only!=='boolean'||pending.changes.length||pending.errors.length;
    timings.inspect_ms=Date.now()-lockedAt;
    const viewStarted=Date.now(),view=changed?generaVistaDaProiezioneDiretta_(projection):null;
    const readOnly=view?view.sola_lettura===true:receipt.read_only;
    timings.view_ms=Date.now()-viewStarted;
    const writeStarted=Date.now();
    if(changed){
      // Invalidate before writing so an interrupted update cannot reuse an old receipt.
      properties.deleteProperty(key);
      result=scriviProiezioneEvento_(opened.sheet,view);
      if(!result.manuali&&!result.conflitti)aggiornaPagamentiDaProiezione_(opened.book,projection,readOnly);
    }
    SpreadsheetApp.flush();
    const complete=!result.manuali&&!result.conflitti;
    if(changed&&complete)properties.setProperty(key,JSON.stringify({fingerprint:fingerprint,read_only:readOnly,layout_version:2}));
    timings.write_ms=Date.now()-writeStarted;timings.lock_ms=Date.now()-lockedAt;timings.total_ms=Date.now()-started;
    const response={ok:true,ready:complete,event_sheet_complete:complete,read_only:readOnly,id_foglio:opened.book.getId(),url_foglio:complete?opened.book.getUrl():undefined,creato:opened.created,projection_hash:String(payload.projection_hash||''),fingerprint:fingerprint,event_schema:decodificaOggetto_(projection.event.schema_vista_json),operational_profile:String(projection.event.profilo_operativo||''),esito:result};
    if((payload||{}).measure_performance===true)response.performance=Object.assign(timings,{view_built:Boolean(changed),registrations:projection.registrations.length,participants:projection.participants.length,payments:projection.payments.length});
    return response;
  }finally{lock.releaseLock();}
}

/** Record the high-water mark before writing: a failed new delivery must never
 * allow an older one to roll the sheet back. Same-generation retries can repair it.
 */
function verificaGenerazioneProiezione_(eventId,payload) {
  const props=PropertiesService.getScriptProperties(),key='MI_DIRECT_GENERATION_'+eventId;
  const stored=JSON.parse(props.getProperty(key)||'null'),generation=String(payload.projection_generation||'');
  if(!generation){if(stored)throw new Error('PROJECTION_GENERATION_REQUIRED');return;}
  if(!/^[1-9][0-9]{0,15}$/.test(generation)||!Number.isSafeInteger(Number(generation)))throw new Error('INVALID_PROJECTION_GENERATION');
  const hash=String(payload.projection_hash||'');
  if(stored&&(Number(generation)<Number(stored.generation)||(generation===stored.generation&&hash!==stored.hash)))throw new Error('STALE_EVENT_PROJECTION');
  if(!stored||generation!==stored.generation)props.setProperty(key,JSON.stringify({generation:generation,hash:hash}));
}
