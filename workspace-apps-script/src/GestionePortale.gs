/** Servizi privati chiamati dal proxy WordPress dopo autorizzazione sull'evento. */
function versioneGestione_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, JSON.stringify(value), Utilities.Charset.UTF_8).map(function(b){return ('0'+(b&255).toString(16)).slice(-2);}).join('');
}
function schedaGestionePortale_(payload) {
  const lettura=creaLetturaGestione_();
  const registration=verificaPrenotazionePagamentoPortale_(payload, lettura);
  const code=String(registration.codice_ordine);
  const booking=caricaSchedaPrenotazioneConDati_(code, lettura);
  booking.ok=true;
  const snapshot=decodificaOggetto_(registration.snapshot_json);
  const definitions=(snapshot.event && snapshot.event.participant_fields) || [];
  booking.fields=Array.isArray(definitions)?definitions.filter(f=>f && /^[a-z][a-z0-9_]{0,79}$/.test(String(f.key)) && !['__proto__','constructor','prototype','room','first_name','last_name'].includes(f.key)).map(f=>({key:f.key,label:String(f.label||f.key),type:String(f.type||'text'),required:!!f.required,options:f.options||[]})):[];
  if(!booking.fields.some(f=>f.key==='pullman'))booking.fields.push({key:'pullman',label:'Assegnazione pullman (sigla)',type:'text',required:false});
  const state=lettura.stato();
  booking.participants.forEach(function(p){const s=state[code+'|'+p.number]||{};p.first_name=String(s.first_name||p.first_name);p.last_name=String(s.last_name||p.last_name);delete p.fields.first_name;delete p.fields.last_name;});
  booking.movements=riepilogoPagamenti_(code, lettura).movements;
  booking.version=versioneGestione_({participants:booking.participants,accommodations:booking.accommodations});
  const interrupted=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.SECRETARY_OPERATIONS)).find(r=>String(r.codice_ordine)===code&&r.stato==='IN_CORSO');
  if(interrupted){const saved=decodificaOggetto_(interrupted.dati_json);const request=saved.request;if(request&&String(request.operator_label).split(' · ')[0]===String(payload.operator_label||'').split(' · ')[0])booking.pending={operation:request.operation,data:JSON.stringify(request.data),version:request.version,request_id:String(request.request_id).replace(/^wp_[0-9]+_/,'')};}
  // Date e oggetti GAS non attraversano il confine RPC.
  return JSON.parse(JSON.stringify(booking));
}
function aggiornaGestionePortale_(payload) {
  payload=payload||{};
  if(!/^wp_[0-9]+_[a-f0-9-]{36}$/i.test(String(payload.request_id)) || !/^WP#[0-9]+ · /.test(String(payload.operator_label)))throw erroreDatiGestione_('Operatore o richiesta non validi.');
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  let eventId, previous, writing=false;
  try {
    const registration=verificaPrenotazionePagamentoPortale_(payload);
    eventId=String(registration.id_evento);
    const code=String(registration.codice_ordine);
    const fingerprint=versioneGestione_({order:code,operation:payload.operation,data:payload.data});
    const journalData=JSON.stringify({fingerprint:fingerprint,request:payload});
    const journal=ottieniSchedaObbligatoria_(MI_SHEETS.SECRETARY_OPERATIONS);
    previous=convertiRigheInOggetti_(journal).find(r=>String(r.id_operazione)===String(payload.request_id));
    if(previous){if(String(decodificaOggetto_(previous.dati_json).fingerprint||previous.dati_json)!==fingerprint)throw erroreDatiGestione_('Identificativo già utilizzato con dati diversi.');if(previous.stato==='APPLICATA')return {ok:true,saved:true,message:'Modifica già registrata.'};}
    const eventOrders=new Set(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r=>String(r.id_evento)===eventId).map(r=>String(r.codice_ordine)));
    const interrupted=convertiRigheInOggetti_(journal).find(r=>eventOrders.has(String(r.codice_ordine))&&r.stato==='IN_CORSO'&&String(r.id_operazione)!==String(payload.request_id));
    if(interrupted)throw erroreDatiGestione_('Un salvataggio precedente richiede un nuovo tentativo prima di altre modifiche.');
    const booking=schedaGestionePortale_(payload);
    if(!previous && payload.version!==booking.version)throw erroreDatiGestione_('I dati sono cambiati: ricarica la scheda prima di salvare.');
    const d=payload.data||{};
    const operator=normalizzaTesto_(payload.operator_label,100);
    if(payload.operation==='participant') {
      const participant=booking.participants.find(p=>p.number===Number(d.number)&&p.status!=='CANCELLED');
      if(!participant)throw erroreDatiGestione_('Partecipante non disponibile.');
      const changes=[{key:'first_name',value:normalizzaTesto_(d.first_name,80)},{key:'last_name',value:normalizzaTesto_(d.last_name,80)}];
      if(changes.some(c=>!c.value))throw erroreDatiGestione_('Nome e cognome sono obbligatori.');
      const fields=d.fields||{};
      const allowed=booking.fields.reduce((m,f)=>(m[f.key]=f,m),Object.create(null));
      // I campi già presenti restano modificabili; non si introducono chiavi arbitrarie.
      Object.keys(participant.fields).forEach(k=>{if(!allowed[k] && /^[a-z][a-z0-9_]{0,79}$/.test(k))allowed[k]={key:k,type:'text'};});
      Object.keys(fields).forEach(function(key){
        if(!allowed[key]||['__proto__','constructor','prototype','room','camera','alloggio','first_name','last_name'].includes(key))throw erroreDatiGestione_('Campo non modificabile.');
        if(typeof fields[key]!=='string'||fields[key].length>1000)throw erroreDatiGestione_('Valore del campo non valido.');
        const value=normalizzaTesto_(fields[key],1000),f=allowed[key];
        if(value && f.type==='email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))throw erroreDatiGestione_('Email non valida.');
        if(value && ['select','yesno'].includes(f.type) && !(f.options||[]).includes(value) && String(participant.fields[key]||'')!==value)throw erroreDatiGestione_('Scegli uno dei valori disponibili.');
        if(value && f.type==='date' && (!/^\d{4}-\d{2}-\d{2}$/.test(value)||isNaN(new Date(value).getTime())||new Date(value).toISOString().slice(0,10)!==value))throw erroreDatiGestione_('Data non valida.');
        changes.push({key:key,value:value});
      });
      if(Object.prototype.hasOwnProperty.call(d,'room')) {
        const room=String(d.room||'');
        const selected=booking.accommodations.find(r=>r.code===room);
        if(room && (!selected || (selected.available<1 && participant.room!==room)))throw erroreDatiGestione_('Camera non disponibile o al completo.');
        changes.push({key:'room',value:room});
      }
      writing=true;
      if(!previous)journal.appendRow([payload.request_id,new Date(),code,participant.number,payload.operation,journalData,'',operator,'IN_CORSO','Salvataggio da completare',new Date()]);
      SpreadsheetApp.flush();
      changes.forEach(c=>aggiornaStatoOperativo_(code,participant.number,c.key,c.value,operator,payload.request_id));
      // Anche le ricerche e le proiezioni leggono i nomi corretti dal registro.
      const sheet=ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS);
      const row=convertiRigheInOggetti_(sheet).find(r=>String(r.codice_ordine)===code&&Number(r.numero_partecipante)===participant.number);
      const headers=creaIndiceIntestazioni_(sheet);
      sheet.getRange(row._row,headers.nome+1).setValue(neutralizzaFormula_(changes[0].value,80));
      sheet.getRange(row._row,headers.cognome+1).setValue(neutralizzaFormula_(changes[1].value,80));
    } else if(payload.operation==='room_save'||payload.operation==='room_delete') {
      const roomCode=normalizzaTesto_(d.code,80);
      if(!/^[A-Za-z0-9_-]{1,80}$/.test(roomCode))throw erroreDatiGestione_('Usa un codice camera con lettere, numeri o trattini.');
      const rooms=ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS);
      const existing=convertiRigheInOggetti_(rooms).find(r=>String(r.id_evento)===eventId&&String(r.codice)===roomCode);
      const current=booking.accommodations.find(r=>r.code===roomCode);
      if(payload.operation==='room_delete') {
        if(current && current.occupied>0)throw erroreDatiGestione_('Riassegna gli occupanti prima di eliminare la camera.');
        writing=true;
      if(!previous)journal.appendRow([payload.request_id,new Date(),code,0,payload.operation,journalData,'',operator,'IN_CORSO','Salvataggio da completare',new Date()]);
        SpreadsheetApp.flush();
        if(existing)rooms.deleteRow(existing._row);
      } else {
        const capacity=Number(d.capacity),name=normalizzaTesto_(d.name,120);
        if(!name||!Number.isInteger(capacity)||capacity<1||capacity>1000||capacity<Number(current&&current.occupied||0))throw erroreDatiGestione_('Nome o capienza non validi.');
        writing=true;
      if(!previous)journal.appendRow([payload.request_id,new Date(),code,0,payload.operation,journalData,'',operator,'IN_CORSO','Salvataggio da completare',new Date()]);
        SpreadsheetApp.flush();
        const values=[eventId,roomCode,neutralizzaFormula_(name,120),capacity,'SI',''];
        if(existing)rooms.getRange(existing._row,1,1,values.length).setValues([values]);else rooms.appendRow(values);
      }
    } else throw erroreDatiGestione_('Operazione non disponibile.');
    const saved=convertiRigheInOggetti_(journal).find(r=>String(r.id_operazione)===String(payload.request_id));
    const journalValues=[payload.request_id,new Date(),code,Number(d.number)||0,payload.operation,journalData,'',operator,'APPLICATA','Modifica dal portale',new Date()];
    if(saved)journal.getRange(saved._row,1,1,journalValues.length).setValues([journalValues]);else journal.appendRow(journalValues);
  } catch(error) { error.retryRequired=writing||!!previous; throw error; } finally {lock.releaseLock();}
  let message='Modifica salvata.';
  try {aggiornaFoglioOperativoEvento({id_evento:eventId});}catch(e){message+=' Il foglio evento sarà aggiornato al prossimo allineamento.';}
  return {ok:true,saved:true,message:message};
}
function riepilogoGestioneEvento_(payload) {
  const eventId=normalizzaTesto_(payload.event_id,40);
  if(!/^\d+$/.test(eventId))throw erroreDatiGestione_('Evento non valido.');
  const orders=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r=>String(r.id_evento)===eventId);
  const participants=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS));
  const paid=calcolaVersatoPerOrdine_(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)));
  const state=indiceStatoOperativo_();
  const hasRooms=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.ACCOMMODATIONS)).some(r=>String(r.id_evento)===eventId && String(r.attiva)!=='NO');
  const items=orders.map(function(r){
    const code=String(r.codice_ordine),snapshot=decodificaOggetto_(r.snapshot_json);
    const definitions=(snapshot.event&&snapshot.event.participant_fields)||[];
    const active=!['CANCELLED','EXPIRED','ANNULLATO','SCADUTO'].includes(String(r.stato));
    const people=participants.filter(p=>String(p.codice_ordine)===code&&String(p.stato_partecipante)!=='CANCELLED');
    let missing=0,unassigned=0;
    people.forEach(function(p){const data=datiOperativiPartecipante_(p,state[code+'|'+p.numero_partecipante]||{});const requiresExtra=String((snapshot.event||{}).participant_extra_scope)==='ALL'||Number(p.numero_partecipante)===1;if(requiresExtra&&definitions.some(f=>f.required&&!String(data[f.key]||'').trim()))missing++;if(hasRooms&&!String(data.room||data.camera||data.alloggio||''))unassigned++;});
    return {code:code,name:[r.nome_referente,r.cognome_referente].join(' '),status:String(r.stato),active:active,participants:people.length,total:Number(r.totale_centesimi)||0,paid:paid[code]||0,balance:Math.max(0,(Number(r.totale_centesimi)||0)-(paid[code]||0)),missing:missing,unassigned:unassigned};
  });
  return {ok:true,items:items,updated_at:new Date().toISOString()};
}

function erroreDatiGestione_(message) { const error=new Error(message); error.gestioneValidation=true; return error; }
function rispostaAggiornamentoGestione_(payload) {
  try { return aggiornaGestionePortale_(payload); }
  catch(error) { if(!error.gestioneValidation)throw error; return {ok:true,saved:false,rejected:!error.retryRequired,message:error.message}; }
}
