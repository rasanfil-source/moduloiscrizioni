/** Il contrassegno permanente respinge le consegne ritardate dopo la rimozione dell'evento. */
function eventoInEliminazione_(id) {
  return !!PropertiesService.getScriptProperties().getProperty('MI_DELETED_EVENT_' + String(id));
}

/** La richiesta firmata è ripetibile; i dati MySQL vengono rimossi soltanto da WordPress. */
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
    if (job && job.request !== request) return {ok:false,error:'DELETION_CONFLICT'};
    if (job && job.mode !== mode) {
      // Solo una decisione amministrativa firmata da WordPress può interrompere i tentativi di cestinamento.
      if (job.mode!=='trash' || mode!=='keep' || payload.preserve_file!==true || String(payload.id_foglio||'')!==String(job.files[0]||'')) return {ok:false,error:'DELETION_CONFLICT'};
      job.previous_mode=job.mode;job.mode='keep';job.preserve_file=true;
      props.setProperty(key,JSON.stringify(job));
    }
    if (!job) {
      const registered=String(props.getProperty('MI_DIRECT_SHEET_'+id)||''), supplied=String(payload.id_foglio||'');
      if (registered && supplied && registered!==supplied) return {ok:false,error:'EVENT_SHEET_MISMATCH'};
      const files=Array.from(new Set([registered,supplied].filter(Boolean)));
      job = {request:request,mode:mode,files:files,fileIndex:0,complete:false};
      props.setProperty(key,JSON.stringify(job));
    }
    const sheetUrl = job.files.length ? 'https://docs.google.com/spreadsheets/d/'+job.files[0]+'/edit' : '';
    if (job.complete) return {ok:true,complete:true,sheet_url:sheetUrl};
    // Gli errori di permesso o di elemento non trovato restano visibili; il riferimento consente un nuovo tentativo esplicito.
    while (job.fileIndex < job.files.length) {
      if (mode==='trash') {
        const file = DriveApp.getFileById(job.files[job.fileIndex]);
        if (!file.isTrashed()) file.setTrashed(true);
      }
      job.fileIndex++;
      props.setProperty(key,JSON.stringify(job));
    }
		job.files.forEach(file=>{
			props.deleteProperty('MI_DIRECT_VIEW_'+file);
			props.deleteProperty('MI_READ_ONLY_'+file);
			props.deleteProperty('MI_MONEY_FORMAT_'+file);
			props.deleteProperty('MI_DIRECT_MANAGEMENT_'+file);
		});
		props.deleteProperty('MI_DIRECT_GENERATION_'+id);
    props.deleteProperty('MI_DIRECT_SHEET_'+id);
    job.complete=true; props.setProperty(key,JSON.stringify(job));
    return {ok:true,complete:true,removed:0,sheet_url:sheetUrl};
  } finally {lock.releaseLock();}
}

