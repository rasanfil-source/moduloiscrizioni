/** Compare cells as Sheets returns them, without treating text as formulas. */
function valoreConfrontoProiezione_(value) {
  if (value instanceof Date) return value.getTime();
  // The leading apostrophe is an input escape, not part of the displayed value.
  return typeof value==='string' ? value.replace(/^'(?=[=+@-])/, '') : (value==null?'':value);
}
function righeProiezioneUguali_(a,b) {
  return !!a && a.length===b.length && a.every((value,i)=>valoreConfrontoProiezione_(value)===valoreConfrontoProiezione_(b[i]));
}
/** Adjacent differences share one service call; unchanged rows are never written. */
function blocchiRigheProiezione_(prima,dopo,primaRiga) {
  const blocks=[];
  dopo.forEach((row,index)=>{
    if(righeProiezioneUguali_(prima[index],row))return;
    const last=blocks[blocks.length-1], position=index+primaRiga;
    if(last && last.row+last.values.length===position)last.values.push(row);
    else blocks.push({row:position,values:[row]});
  });
  return blocks;
}
function estendiGrigliaProiezione_(sheet,rows,columns) {
  if(sheet.getMaxRows()<rows)sheet.insertRowsAfter(sheet.getMaxRows(),rows-sheet.getMaxRows());
  if(sheet.getMaxColumns()<columns)sheet.insertColumnsAfter(sheet.getMaxColumns(),columns-sheet.getMaxColumns());
}
/** Preserve user column/row order when the schema and participant identities allow it. */
function pianoProiezioneIncrementale_(vista,mappa,correnti,intestazioni) {
  const dichiarate=vista.colonne.filter(c=>c.key!=='participant_number');
  const canoniche=[{key:'participant_number',label:'N.',gruppo:'persona',comprimibile:false}].concat(dichiarate,[{key:'_numero',label:'Partecipante interno'},{key:'_ordine',label:'Prenotazione'}]);
  const keys=Object.keys(mappa), same=keys.length===canoniche.length && canoniche.every((c,i)=>mappa[c.key]===i+1);
  const columns=same?canoniche.slice().sort((a,b)=>mappa[a.key]-mappa[b.key]):canoniche;
  const schemaChanged=!same;
  const order=columns.findIndex(c=>c.key==='_ordine'), number=columns.findIndex(c=>c.key==='_numero');
  const progressive=columns.findIndex(c=>c.key==='participant_number');
  const identity=row=>JSON.stringify([String(row[order]),Number(row[number])]);
  const incoming=vista.righe.map((r,i)=>columns.map(c=>c.key==='_numero'?r.numero_partecipante:c.key==='_ordine'?r.codice_ordine:c.key==='participant_number'?i+1:typeof r.valori[c.key]==='number'&&Number.isFinite(r.valori[c.key])?r.valori[c.key]:neutralizzaFormula_(r.valori[c.key],5000)));
  let rows=incoming;
  if(same){
    const remaining=new Map(incoming.map(row=>[identity(row),row]));
    rows=[];let precedente=0;
    correnti.forEach(row=>{
      const key=identity(row);if(!remaining.has(key))return;
      const aggiornato=remaining.get(key);
      if(progressive>=0){const corrente=Number(row[progressive]);aggiornato[progressive]=Number.isSafeInteger(corrente)&&corrente>0?corrente:precedente+1;precedente=Number(aggiornato[progressive]);}
      rows.push(aggiornato);remaining.delete(key);
    });
    remaining.forEach(row=>{if(progressive>=0){row[progressive]=precedente+1;precedente=Number(row[progressive]);}rows.push(row);});
  }
  return {columns:columns,rows:rows,structural:schemaChanged,header:columns.map(c=>c.label),
    header_changed:schemaChanged||!righeProiezioneUguali_(intestazioni,columns.map(c=>c.label)),
    blocks:blocchiRigheProiezione_(schemaChanged?[]:correnti,rows,2),old_rows:correnti.length,read_only:vista.sola_lettura===true};
}

/** Durable write-ahead journal. Only changed rows are stored for ordinary updates.
 * The ready marker is committed after the complete journal reaches Sheets. The
 * operational sheet stays protected until both data and baseline are committed.
 */
function preparaScritturaProiezione_(sheet,plan) {
  const book=sheet.getParent(), props=PropertiesService.getScriptProperties(), key='MI_WRITE_'+book.getId();
  const journal=book.getSheetByName('_MI_WRITE')||book.insertSheet('_MI_WRITE');
  const entries=[];
  plan.blocks.forEach(block=>block.values.forEach((row,index)=>entries.push([block.row+index].concat(row.map(value=>JSON.stringify(value))))));
  const metadata={columns:plan.columns,structural:plan.structural,header:plan.header,header_changed:plan.header_changed,
    target_rows:plan.rows.length,old_rows:plan.old_rows,read_only:plan.read_only,entries:entries.length};
  estendiGrigliaProiezione_(journal,entries.length+1,plan.columns.length+1);
  journal.getRange(1,1).setValue(JSON.stringify(metadata));
  if(entries.length)journal.getRange(2,1,entries.length,plan.columns.length+1).setNumberFormat('@').setValues(entries);
  proteggiProiezione_(journal);journal.hideSheet();
  SpreadsheetApp.flush();
  props.setProperty(key,'READY');
  return metadata;
}

/** Rebuild/patch the baseline by comparing blocks, including after partial writes. */
function aggiornaBaseIncrementale_(sheet,columns,count) {
  const book=sheet.getParent(), base=book.getSheetByName('_MI_BASE')||book.insertSheet('_MI_BASE');
  const display=count?sheet.getRange(2,1,count,columns.length).getDisplayValues():[];
  const order=columns.findIndex(c=>c.key==='_ordine'), number=columns.findIndex(c=>c.key==='_numero');
  const rows=display.map(row=>{
    const values={};columns.forEach((c,i)=>{if(c.key!=='_ordine'&&c.key!=='_numero')values[c.key]=row[i];});
    return [row[order],Number(row[number]),JSON.stringify(values),''];
  });
  const oldCount=Math.max(0,base.getLastRow()-1), old=oldCount?base.getRange(2,1,oldCount,4).getValues():[];
  estendiGrigliaProiezione_(base,rows.length+1,4);
  if(!base.getLastRow())base.getRange(1,1,1,4).setValues([['Prenotazione','Partecipante','Valori confermati','Ricevute']]);
  blocchiRigheProiezione_(old,rows,2).forEach(block=>base.getRange(block.row,1,block.values.length,4).setValues(block.values));
  if(oldCount>rows.length)base.getRange(rows.length+2,1,oldCount-rows.length,4).clearContent();
  proteggiProiezione_(base);base.hideSheet();
}

/** Safe to repeat after any service exception or execution timeout. */
function riprendiScritturaProiezione_(sheet) {
  const book=sheet.getParent(),props=PropertiesService.getScriptProperties(),key='MI_WRITE_'+book.getId();
  const stage=props.getProperty(key);
  if(!stage)return false;
  const journal=book.getSheetByName('_MI_WRITE');
  if(!journal)throw new Error('PROJECTION_JOURNAL_MISSING');
  const plan=JSON.parse(String(journal.getRange(1,1).getValue())),width=plan.columns.length;
  const entries=plan.entries?journal.getRange(2,1,plan.entries,width+1).getValues():[];
  proteggiProiezione_(sheet);SpreadsheetApp.flush();
  if(stage!=='COMMITTED'){
  estendiGrigliaProiezione_(sheet,plan.target_rows+1,width);
  if(plan.structural){
    sheet.createDeveloperMetadataFinder().withKey('MI_CAMPO').find().forEach(m=>m.remove());
    sheet.clear();
    sheet.getRange(1,1,sheet.getMaxRows(),sheet.getMaxColumns()).clearDataValidations().breakApart();
    sheet.showColumns(1,sheet.getMaxColumns());
    plan.columns.forEach((c,i)=>identificaColonnaEvento_(sheet,i+1,c.key));
    plan.columns.forEach((c,i)=>{if(c.key==='_numero'||c.key==='_ordine')sheet.hideColumns(i+1);});
  }
  if(plan.header_changed)sheet.getRange(1,1,1,width).setValues([plan.header]).setFontWeight('bold');
  let blocks=[];
  entries.forEach(entry=>{
    const position=Number(entry[0]),row=entry.slice(1).map(value=>JSON.parse(String(value))),last=blocks[blocks.length-1];
    if(last&&last.row+last.values.length===position)last.values.push(row);else blocks.push({row:position,values:[row]});
  });
  blocks.forEach(block=>sheet.getRange(block.row,1,block.values.length,width).setNumberFormat('@').setValues(block.values));
  if(!plan.structural&&plan.old_rows>plan.target_rows)sheet.getRange(plan.target_rows+2,1,plan.old_rows-plan.target_rows,width).clearContent();
  aggiornaBaseIncrementale_(sheet,plan.columns,plan.target_rows);
  SpreadsheetApp.flush();
  props.setProperty(key,'COMMITTED');
  }
  const editable=[];
  plan.columns.forEach((column,i)=>{
    if(((!plan.read_only&&campoModificabileFoglio_(column.key))||campoLocaleFoglio_(column.key))&&plan.target_rows){
      const range=sheet.getRange(2,i+1,plan.target_rows,1);editable.push(range);
      if(plan.structural)range.setBackground('#eef5fc');
      else if(plan.target_rows>plan.old_rows)sheet.getRange(plan.old_rows+2,i+1,plan.target_rows-plan.old_rows,1).setBackground('#eef5fc');
    }
  });
  if(plan.structural)sheet.setFrozenRows(1);
  // Keep the marker until reopening the editable ranges also succeeds.
  proteggiProiezione_(sheet,editable);SpreadsheetApp.flush();
  props.setProperty('MI_READ_ONLY_'+book.getId(),String(plan.read_only));
  props.deleteProperty(key);
  // Journal contents are no longer needed and may contain removed participants.
  journal.clearContents();
  return true;
}

function scriviVistaIncrementale_(sheet,vista) {
  const map=mappaColonneEvento_(sheet),count=Math.max(0,sheet.getLastRow()-1),width=sheet.getLastColumn();
  const old=count&&width?sheet.getRange(2,1,count,width).getValues():[];
  const header=width?sheet.getRange(1,1,1,width).getValues()[0]:[];
  const plan=pianoProiezioneIncrementale_(vista,map,old,header);
  const modeKey='MI_READ_ONLY_'+sheet.getParent().getId(),props=PropertiesService.getScriptProperties();
  if(props.getProperty(modeKey)!==String(plan.read_only))plan.structural=true;
  if(plan.structural){plan.blocks=blocchiRigheProiezione_([],plan.rows,2);plan.header_changed=true;}
  const changed=plan.blocks.reduce((sum,block)=>sum+block.values.length,0);
  const written=!!(changed||plan.structural||plan.header_changed||plan.old_rows!==plan.rows.length);
  if(written){
    preparaScritturaProiezione_(sheet,plan);
    riprendiScritturaProiezione_(sheet);
    props.setProperty(modeKey,String(plan.read_only));
  }
  return {aggiunte:Math.max(0,plan.rows.length-plan.old_rows),aggiornate:changed,manuali:0,conflitti:0,struttura:plan.structural,scritto:written};
}
