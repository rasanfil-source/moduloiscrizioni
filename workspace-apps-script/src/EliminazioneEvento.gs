/** Small permanent tombstone rejects delayed deliveries after event removal. */
function eventoInEliminazione_(id) {
  return !!PropertiesService.getScriptProperties().getProperty('MI_DELETED_EVENT_' + String(id));
}

/** Signed, direct and repeatable: MySQL data is removed only by WordPress. */
function eliminaDatiEventoDaWordPress_(payload) {
  payload = payload || {};
  const id = String(payload.id_evento || '');
  const request = String(payload.request_id || '');
  const mode = String(payload.mode || '');
  if (payload.direct_projection !== true) return {ok:false,error:'USE_DIRECT_PROJECTION'};
  if (!/^\d+$/.test(id) || !/^[a-f0-9-]{36}$/.test(request) || !['keep','trash'].includes(mode)) return {ok:false,error:'INVALID_DELETION'};
  const lock = LockService.getScriptLock();
  if (!lock.tryLock(5000)) return {ok:false,error:'EVENT_BUSY'};
  try {
    const props = PropertiesService.getScriptProperties();
    const key = 'MI_DELETED_EVENT_' + id;
    let job = JSON.parse(props.getProperty(key) || 'null');
    if (job && (job.request !== request || job.mode !== mode)) return {ok:false,error:'DELETION_CONFLICT'};
    if (!job) {
      const registered=String(props.getProperty('MI_DIRECT_SHEET_'+id)||''), supplied=String(payload.id_foglio||'');
      if (registered && supplied && registered!==supplied) return {ok:false,error:'EVENT_SHEET_MISMATCH'};
      const files=Array.from(new Set([registered,supplied].filter(Boolean)));
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
    job.files.forEach(file=>props.deleteProperty('MI_DIRECT_VIEW_'+file));
    props.deleteProperty('MI_DIRECT_SHEET_'+id);
    job.complete=true; props.setProperty(key,JSON.stringify(job));
    return {ok:true,complete:true,removed:0,sheet_url:sheetUrl};
  } finally {lock.releaseLock();}
}
