/** Editable fields are data, never payment totals or booking identifiers. */
function campoModificabileFoglio_(key) {
  return /^[a-z][a-z0-9_]{0,79}$/.test(key) && !String(key).startsWith('option_') && !['_ordine','_numero','event','order_code','participant_number','status','options','total','paid','paid_cash','paid_transfer','paid_card','balance','special_requests','constructor','prototype'].includes(key);
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

/** Confirm only cells whose edited value has actually returned from MySQL. */
function allineaBaseConVista_(sheet, vista) {
  const pending = modificheCorrentiFoglio_(sheet);
  if (!pending.changes.length || pending.errors.length) return;
  const incoming = Object.create(null);
  vista.righe.forEach(row=>incoming[JSON.stringify([String(row.codice_ordine),Number(row.numero_partecipante)])]=row.valori);
  const confirmed = Object.create(null);
  pending.changes.forEach(change=>{
    const id=JSON.stringify([change.order_code,change.number]), values=incoming[id];
    if (values && Object.prototype.hasOwnProperty.call(values,change.key) && String(values[change.key]??'')===change.after) {
      if (!confirmed[id]) confirmed[id]={};
      confirmed[id][change.key]=change.after;
    }
  });
  const base=sheet.getParent().getSheetByName('_MI_BASE');
  if (!base || base.getLastRow()<2) return;
  base.getRange(2,1,base.getLastRow()-1,3).getValues().forEach((row,index)=>{
    const changes=confirmed[JSON.stringify([String(row[0]),Number(row[1])])];
    if (changes) base.getRange(index+2,3).setValue(JSON.stringify(Object.assign(JSON.parse(String(row[2])),changes)));
  });
}

function leggiModificheEventoMysql_(payload) {
  const eventId=String(payload.event_id||'');
  if (!/^[1-9][0-9]*$/.test(eventId)) throw new Error('Evento non valido.');
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  try {
    const record=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).find(r=>String(r.id_evento)===eventId);
    if (!record || !record.id_foglio) throw new Error('Foglio evento non disponibile.');
    const sheet=SpreadsheetApp.openById(String(record.id_foglio)).getSheetByName('Dati operativi');
    if (!sheet) throw new Error('Scheda Dati operativi non disponibile.');
    const result=modificheCorrentiFoglio_(sheet);
    if (!result.initialized) throw new Error('Aggiorna il foglio evento prima di sincronizzarlo.');
    return {ok:true,changes:result.changes,errors:result.errors};
  } finally {lock.releaseLock();}
}
