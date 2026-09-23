/** Editable fields are data, never payment totals or booking identifiers. */
function campoModificabileFoglio_(key) {
  return /^[a-z][a-z0-9_-]{0,79}$/.test(key) && !String(key).startsWith('option_') && !['_ordine','_numero','event','order_code','participant_number','status','options','total','paid','paid_cash','paid_transfer','paid_card','balance','special_requests','attendance','constructor','prototype'].includes(key);
}
/** Valori di presentazione modificabili nel foglio, ma non inviati a WordPress. */
function campoLocaleFoglio_(key) {
  return key === 'participant_number';
}
function aggiungiColonneServizi_(columns, options) {
  (Array.isArray(options)?options:[]).forEach(option=>{
    if (option.scope!=='TICKET' || !/^[a-z0-9_-]{1,64}$/.test(String(option.code))) return;
    const key='option_'+option.code;
    if (!columns.some(column=>column.key===key)) columns.push({key:key,label:String(option.name||option.code),gruppo:'servizi',comprimibile:false});
  });
}
function confrontaModificheFoglio_(base, current) {
  const changes = [], errors = [], seen = new Set();
  current.forEach(row => {
    const identity = JSON.stringify([String(row.order), Number(row.number)]);
    if (seen.has(identity)) { errors.push('Partecipante duplicato nel foglio: ' + row.order); return; }
    seen.add(identity);
    const original = base[identity];
    if (!original) { errors.push('Riga senza prenotazione riconosciuta. Inserisci nuove iscrizioni dal portale.'); return; }
    Object.keys(original).forEach(key => {
      if (!Object.prototype.hasOwnProperty.call(row.values, key)) { errors.push('Colonna mancante: ' + key); return; }
      const before = String(original[key] ?? ''), after = String(row.values[key] ?? '');
      if (before !== after) {
        if (campoLocaleFoglio_(key)) return;
        if (!campoModificabileFoglio_(key)) errors.push('Colonna non modificabile: ' + key);
        else changes.push({order_code:String(row.order),number:Number(row.number),key:key,before:before,after:after});
      }
    });
  });
  Object.keys(base).forEach(key => { if (!seen.has(key)) errors.push('Riga rimossa: annulla la partecipazione dal portale.'); });
  return {changes:changes,errors:errors};
}
function leggiBaseFoglio_(foglio) {
  const sheet = foglio.getSheetByName('_MI_BASE');
  if (!sheet) return null;
  const base = Object.create(null);
  if (sheet.getLastRow() < 2) return base;
  sheet.getRange(2,1,sheet.getLastRow()-1,3).getValues().forEach(row => {
    const key = JSON.stringify([String(row[0]),Number(row[1])]);
    if (base[key]) throw new Error('Base del foglio duplicata.');
    base[key] = JSON.parse(String(row[2]));
  });
  return base;
}

/** Recupera il caso circoscritto in cui il progressivo 1..N e' stato scritto
 * nella prima colonna del vecchio tracciato, che conteneva l'identificatore
 * interno del partecipante. La riparazione e' ammessa soltanto se ordine,
 * posizione e tutti gli altri valori coincidono con la base protetta. */
function riparaProgressivoSuIdentitaLegacy_(sheet) {
  const columns=mappaColonneEvento_(sheet);
  if (columns.participant_number || columns._numero!==1 || !columns._ordine) return false;
  const baseSheet=sheet.getParent().getSheetByName('_MI_BASE');
  const count=Math.max(0,sheet.getLastRow()-1);
  if (!baseSheet || count<1 || baseSheet.getLastRow()-1!==count) return false;
  const current=sheet.getRange(2,1,count,sheet.getLastColumn()).getDisplayValues();
  const baseRows=baseSheet.getRange(2,1,count,3).getValues();
  const keys=Object.keys(columns).filter(key=>!['_ordine','_numero'].includes(key));
  const identities=new Set();
  for (let index=0;index<count;index+=1) {
    const row=current[index], baseRow=baseRows[index], number=Number(baseRow[1]);
    if (String(row[0]).trim()!==String(index+1) || String(row[columns._ordine-1])!==String(baseRow[0]) || !Number.isInteger(number) || number<1) return false;
    const identity=JSON.stringify([String(baseRow[0]),number]);
    if (identities.has(identity)) return false;
    identities.add(identity);
    let values;try{values=JSON.parse(String(baseRow[2]));}catch(error){return false;}
    if (!values || typeof values!=='object' || Array.isArray(values)) return false;
    if (keys.some(key=>!Object.prototype.hasOwnProperty.call(values,key) || String(row[columns[key]-1])!==String(values[key]??''))) return false;
  }
  const protection=sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).find(item=>item.getDescription()==='MI_PROIEZIONE');
  const editable=protection?protection.getUnprotectedRanges():[];
  proteggiProiezione_(sheet);SpreadsheetApp.flush();
  try {
    sheet.getRange(2,columns._numero,count,1).setValues(baseRows.map(row=>[Number(row[1])]));
    SpreadsheetApp.flush();
  } finally { proteggiProiezione_(sheet,editable); }
  return true;
}
function modificheCorrentiFoglio_(sheet) {
  const base = leggiBaseFoglio_(sheet.getParent());
  if (!base) return {changes:[],errors:[],initialized:false};
  const columns = mappaColonneEvento_(sheet);
  if (!columns._ordine || !columns._numero) return {changes:[],errors:['Identificativi del foglio mancanti.'],initialized:true};
  const rows = sheet.getLastRow() > 1 ? sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues() : [];
  const current = rows.filter(r => r.some(v=>String(v)!=='')).map(row => {
    const values = {};
    Object.keys(columns).filter(key=>!['_ordine','_numero'].includes(key)).forEach(key=>values[key]=row[columns[key]-1]);
    return {order:String(row[columns._ordine-1]),number:Number(row[columns._numero-1]),values:values};
  });
  return Object.assign({initialized:true},confrontaModificheFoglio_(base,current));
}
function salvaBaseFoglio_(sheet) {
  const foglio = sheet.getParent();
  const base = foglio.getSheetByName('_MI_BASE') || foglio.insertSheet('_MI_BASE');
  const columns = mappaColonneEvento_(sheet);
  const rows = sheet.getLastRow()>1 ? sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues() : [];
  const values = rows.map(row => {
    const fields = {};
    Object.keys(columns).filter(key=>!['_ordine','_numero'].includes(key)).forEach(key=>fields[key]=row[columns[key]-1]);
    return [row[columns._ordine-1],Number(row[columns._numero-1]),JSON.stringify(fields)];
  });
  base.clearContents();
  base.getRange(1,1,1,3).setValues([['Prenotazione','Partecipante','Valori confermati']]);
  if (base.getMaxRows()<values.length+1) base.insertRowsAfter(base.getMaxRows(),values.length+1-base.getMaxRows());
  if (values.length) base.getRange(2,1,values.length,3).setValues(values);
  proteggiProiezione_(base);
  base.hideSheet();
}

/** A signed receipt can be acknowledged by a newer complete replica too:
 * the queue may coalesce the intermediate revision containing the accepted text.
 * Until that revision arrives, leave the base intact to prevent stale overwrites.
 */
function allineaBaseConVista_(sheet, vista) {
  const pending = modificheCorrentiFoglio_(sheet);
  if (!pending.changes.length || pending.errors.length) return;
  const incoming = Object.create(null);
  vista.righe.forEach(row=>incoming[JSON.stringify([String(row.codice_ordine),Number(row.numero_partecipante)])]=row);
  const base=sheet.getParent().getSheetByName('_MI_BASE');
  if (!base || base.getLastRow()<2) return;
  const baseRows=base.getRange(2,1,base.getLastRow()-1,4).getValues();
  const receipts=Object.create(null);
  baseRows.forEach(row=>receipts[JSON.stringify([String(row[0]),Number(row[1])])]=JSON.parse(String(row[3]||'{}')));
  const confirmed = Object.create(null);
  pending.changes.forEach(change=>{
    const id=JSON.stringify([change.order_code,change.number]), row=incoming[id], values=row&&row.valori;
    const receipt=receipts[id] && receipts[id][change.key];
    if (receipt) {
      const revision=Number(row && row.workspace_revision);
      if (Number.isSafeInteger(revision) && revision>=receipt.revision && change.before===receipt.before) {
        if (!confirmed[id]) confirmed[id]={};
        // A later local edit stays pending, now against the accepted database value.
        confirmed[id][change.key]=receipt.accepted;
        delete receipts[id][change.key];
      }
    } else if (values && Object.prototype.hasOwnProperty.call(values,change.key) && normalizzaTesto_(values[change.key],5000)===change.after) {
      if (!confirmed[id]) confirmed[id]={};
      confirmed[id][change.key]=change.after;
    }
  });
  baseRows.forEach((row,index)=>{
    const id=JSON.stringify([String(row[0]),Number(row[1])]), changes=confirmed[id];
    if (changes) {
      base.getRange(index+2,3).setValue(JSON.stringify(Object.assign(JSON.parse(String(row[2])),changes)));
      base.getRange(index+2,4).setValue(JSON.stringify(receipts[id]));
    }
  });
}

function leggiModificheEventoMysql_(payload) {
  const eventId=String(payload.event_id||'');
  if (payload.direct_projection!==true) throw new Error('USE_DIRECT_PROJECTION');
  if (!/^[1-9][0-9]*$/.test(eventId)) throw new Error('Evento non valido.');
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  try {
	const sheet=apriFoglioEventoFirmato_(payload,false).sheet;
    if (!sheet) throw new Error('Scheda Dati operativi non disponibile.');
    // Il controllo leggero del portale deve anche terminare una proiezione
    // interrotta dopo la preparazione del giornale. In caso contrario una
    // ricevuta WordPress ancora valida può riaprire una griglia parziale.
    if (typeof riprendiScritturaProiezione_==='function') riprendiScritturaProiezione_(sheet);
    const result=modificheCorrentiFoglio_(sheet);
    if (!result.initialized) throw new Error('Aggiorna il foglio evento prima di sincronizzarlo.');
    return {ok:true,changes:result.changes,errors:result.errors};
  } finally {lock.releaseLock();}
}

/** Signed MySQL receipts normalize only the submitted cell, never a newer edit.
 * Keep the base unchanged: only the returning canonical view acknowledges it.
 */
function confermaModificheFoglio_(payload) {
  const eventId=String(payload.event_id||''), receipts=payload.confirmations;
  if (payload.direct_projection!==true) throw new Error('USE_DIRECT_PROJECTION');
  if (!/^[1-9][0-9]*$/.test(eventId) || !Array.isArray(receipts) || receipts.length>500) throw new Error('INVALID_SHEET_RECEIPT');
	const lock=LockService.getScriptLock(); lock.waitLock(30000);
	try {
		const sheet=apriFoglioEventoFirmato_(payload,false).sheet;
    if (!sheet) throw new Error('EVENT_SHEET_MISSING');
    let updated=0;
    const protection=sheet.getProtections(SpreadsheetApp.ProtectionType.SHEET).find(p=>p.getDescription()==='MI_PROIEZIONE');
    const editable=protection?protection.getUnprotectedRanges():[];
    proteggiProiezione_(sheet); SpreadsheetApp.flush();
    try {
    const base=leggiBaseFoglio_(sheet.getParent()), columns=mappaColonneEvento_(sheet);
    if (!base || !columns._ordine || !columns._numero) throw new Error('SHEET_BASE_MISSING');
    const rows=sheet.getLastRow()>1?sheet.getRange(2,1,sheet.getLastRow()-1,sheet.getLastColumn()).getDisplayValues():[];
    // Index once, retaining ambiguous identities as null rather than picking a row.
    const positions=new Map();
    rows.forEach((row,index)=>{const id=JSON.stringify([String(row[columns._ordine-1]),Number(row[columns._numero-1])]);positions.set(id,positions.has(id)?null:index);});
    const baseSheet=sheet.getParent().getSheetByName('_MI_BASE');
    const baseRows=baseSheet.getLastRow()>1?baseSheet.getRange(2,1,baseSheet.getLastRow()-1,4).getValues():[];
    const basePositions=new Map();
    baseRows.forEach((row,index)=>basePositions.set(JSON.stringify([String(row[0]),Number(row[1])]),index));
    receipts.forEach(receipt=>{
      if (!campoModificabileFoglio_(receipt.key) || !columns[receipt.key] || typeof receipt.accepted!=='string') return;
      const identity=JSON.stringify([String(receipt.order_code),Number(receipt.number)]);
      if (!base[identity] || String(base[identity][receipt.key]??'')!==receipt.before) return;
      const position=positions.get(identity), basePosition=basePositions.get(identity);
      if (position==null || basePosition==null) return;
      const revision=Number(receipt.workspace_revision), versioned=Number.isSafeInteger(revision) && revision>0;
      const cell=sheet.getRange(position+2,columns[receipt.key]);
      if (cell.getDisplayValue()!==receipt.after && !(versioned && cell.getDisplayValue()===receipt.accepted)) return;
      cell.setNumberFormat('@').setValue(neutralizzaFormula_(receipt.accepted,5000)); updated++;
      if (versioned) {
        const stored=JSON.parse(String(baseRows[basePosition][3]||'{}'));
        stored[receipt.key]={before:receipt.before,accepted:receipt.accepted,revision:revision};
        baseRows[basePosition][3]=JSON.stringify(stored);
        baseSheet.getRange(basePosition+2,4).setValue(baseRows[basePosition][3]);
      }
    }); SpreadsheetApp.flush();
    } finally {proteggiProiezione_(sheet,editable);}
    return {ok:true,updated:updated};
  } finally {lock.releaseLock();}
}

/** Verify the exact MySQL revisions, then refresh the event under the same lock. */
function preparaAperturaFoglio_(payload) {
  const eventId=String(payload.event_id||''), expected=payload.registrations;
  if (!/^[1-9][0-9]*$/.test(eventId) || !Array.isArray(expected)) throw new Error('INVALID_OPEN_REQUEST');
  const lock=LockService.getScriptLock();
  if (!lock.tryLock(1000)) return {ok:true,ready:false,busy:true,retry_after:3};
  try {
    if (typeof eventoInEliminazione_==='function' && eventoInEliminazione_(eventId)) throw new Error('EVENT_DELETED');
    const rows=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(row=>String(row.id_evento)===eventId);
    const codes=new Set(expected.map(row=>String(row.order_code)));
    if (codes.size!==expected.length || rows.some(row=>!codes.has(String(row.codice_ordine)))) throw new Error('REPLICA_MISMATCH');
    const needs=expected.filter(row=>{
      const found=rows.filter(value=>String(value.codice_ordine)===String(row.order_code));
      return found.length!==1 || String(found[0].workspace_revision)!==String(row.revision) || String(found[0].replica_completa_revision)!==String(row.revision);
    }).map(row=>String(row.order_code));
    if (needs.length) return {ok:true,ready:false,needs_sync:needs};
	const properties=PropertiesService.getScriptProperties(), readyKey='MI_READY_VIEW_'+eventId;
	const identity=serializzaInModoStabile_({registrations:expected,rooms:payload.rooms,revision:payload.workspace_event_revision,schema:payload.event_schema,profile:payload.operational_profile});
	const signature=Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,identity));
	let receipt;try{receipt=JSON.parse(properties.getProperty(readyKey)||'null');}catch(error){}
	if(receipt && receipt.signature===signature && Date.now()-receipt.at<300000){
	  try{
	    const link=trovaCollegamentoFoglioOperativo_(eventId), book=SpreadsheetApp.openById(String(link.id_foglio));
	    const sheet=book.getSheetByName('Dati operativi')||book.getSheets()[0];
	    const edits=modificheCorrentiFoglio_(sheet);
	    if(book.getUrl()===receipt.url && !edits.changes.length && !edits.errors.length)return {ok:true,ready:true,event_sheet_complete:true,read_only:receipt.read_only===true,event_schema:payload.event_schema,operational_profile:payload.operational_profile,url_foglio:receipt.url};
	  }catch(error){/* Rebuild and verify when a sheet has been replaced or is unavailable. */}
	}
	properties.deleteProperty(readyKey);
    sincronizzaCamereMysql_(eventId,payload.rooms,payload.workspace_event_revision);
    const revision=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REPLICA_REVISIONS)).find(row=>String(row.id_evento)===eventId);
    if (!revision || String(revision.revisione_camere)!==String(payload.workspace_event_revision)) throw new Error('REPLICA_MISMATCH');
    if (payload.operational_profile !== undefined) aggiornaProfiloEventoMysql_(eventId, payload.operational_profile);
    if (payload.event_schema !== undefined) aggiornaSchemaEventoMysql_(eventId, payload.event_schema);
    const result=aggiornaFoglioOperativoEventoConLock_({id_evento:eventId,soloModificati:true});
    const complete=!!result.ok && !!result.esito && !result.esito.manuali && !result.esito.conflitti;
    SpreadsheetApp.flush();
    const response={ok:true,ready:complete,event_sheet_complete:complete,read_only:result.read_only===true,event_schema:payload.event_schema,operational_profile:payload.operational_profile,url_foglio:complete?result.url_foglio:undefined};
	if(complete)properties.setProperty(readyKey,JSON.stringify({signature:signature,at:Date.now(),url:response.url_foglio,read_only:response.read_only}));
	return response;
  } finally {lock.releaseLock();}
}

/** Called only under the script lock, from an authenticated WordPress request. */
function aggiornaProfiloEventoMysql_(eventId, profile) {
  if (!['MINIMO','QUOTA_UNICA','SERVIZI_MULTIPLI','VIAGGIO_COMPLESSO'].includes(profile)) throw new Error('INVALID_OPERATIONAL_PROFILE');
  const sheet=ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const row=convertiRigheInOggetti_(sheet).find(value=>String(value.id_evento)===eventId);
  if (!row) throw new Error('EVENT_NOT_FOUND');
  if (String(row.profilo_operativo || '') === profile) return;
  if (sheet.getMaxColumns()<12) sheet.insertColumnsAfter(sheet.getMaxColumns(),12-sheet.getMaxColumns());
  sheet.getRange(1,12).setValue('profilo_operativo');
  sheet.getRange(row._row,12).setValue(profile);
}

function aggiornaSchemaEventoMysql_(eventId, schema) {
  if (!schema || !Array.isArray(schema.fields) || !Array.isArray(schema.options) || typeof schema.room !== 'boolean' || typeof schema.pricing !== 'string') throw new Error('INVALID_EVENT_SCHEMA');
  const sheet=ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const row=convertiRigheInOggetti_(sheet).find(value=>String(value.id_evento)===eventId);
  if (!row) throw new Error('EVENT_NOT_FOUND');
  if (serializzaInModoStabile_(decodificaOggetto_(row.schema_vista_json)) === serializzaInModoStabile_(schema) && String(row.modalita_prezzo) === schema.pricing) return;
  if (sheet.getMaxColumns()<13) sheet.insertColumnsAfter(sheet.getMaxColumns(),13-sheet.getMaxColumns());
  sheet.getRange(1,13).setValue('schema_vista_json');
  sheet.getRange(row._row,13).setValue(JSON.stringify(schema));
  sheet.getRange(row._row,8).setValue(schema.pricing);
}
