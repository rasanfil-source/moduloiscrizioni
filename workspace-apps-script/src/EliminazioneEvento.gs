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
      for (const row of rows) {
        if (removed>=100 || (removed>0 && Date.now()>=deadline)) return {ok:true,complete:false,removed:removed,sheet_url:sheetUrl};
        sheet.deleteRow(row._row); removed++;
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
