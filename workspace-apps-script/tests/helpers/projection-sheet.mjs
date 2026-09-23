import fs from 'node:fs';
import vm from 'node:vm';
export function fixture() {
  const calls=[],properties=new Map();
  const state={fail:null};
  function changed(kind,sheet,detail,run){run();calls.push({kind,sheet:sheet.name,...detail});if(state.fail?.({kind,sheet:sheet.name,...detail})){state.fail=null;throw Error('Injected interruption');}}
  class Range {
    constructor(sheet,row,col,height=1,width=1){Object.assign(this,{sheet,row,col,height,width});}
    getValues(){return Array.from({length:this.height},(_,i)=>Array.from({length:this.width},(_,j)=>this.sheet.cells[this.row+i-1]?.[this.col+j-1]??''));}
    getDisplayValues(){return this.getValues().map(row=>row.map(v=>String(v)));}
    getValue(){return this.getValues()[0][0];}
    getDisplayValue(){return String(this.getValue());}
    setValues(rows){changed('setValues',this.sheet,{row:this.row,count:rows.length,col:this.col},()=>rows.forEach((r,i)=>r.forEach((v,j)=>{(this.sheet.cells[this.row+i-1]??=[])[this.col+j-1]=v;})));return this;}
    setValue(v){return this.setValues([[v]]);}
    clearContent(){return this.setValues(Array.from({length:this.height},()=>Array(this.width).fill('')));}
    clearDataValidations(){return this;}
    breakApart(){return this;}
    setFontWeight(){return this;}
    setNumberFormat(){return this;}
    setBackground(){return this;}
    addDeveloperMetadata(key,value){this.sheet.metadata.push({key,value,col:this.col});return this;}
  }
  class Sheet {
    constructor(name){this.name=name;this.cells=[];this.metadata=[];this.rows=1000;this.cols=26;this.hidden=false;this.editable=[];}
    getName(){return this.name;}getParent(){return book;}getLastRow(){let n=this.cells.length;while(n&&!this.cells[n-1]?.some(v=>v!==''&&v!=null))n--;return n;}
    getLastColumn(){return this.cells.reduce((n,row)=>Math.max(n,row?.reduce((m,v,i)=>v!==''&&v!=null?i+1:m,0)||0),0);}
    getMaxRows(){return this.rows;}getMaxColumns(){return this.cols;}
    getRange(row,col,height,width){if(typeof row==='string'){const letters=row.split(':')[0];col=[...letters].reduce((n,c)=>26*n+c.charCodeAt(0)-64,0);return new Range(this,1,col,this.rows,1);}return new Range(this,row,col,height,width);}
    insertRowsAfter(_row,n){this.rows+=n;}insertColumnsAfter(_col,n){this.cols+=n;}
    getProtections(){return [{getDescription:()=> 'MI_PROIEZIONE',getUnprotectedRanges:()=>this.editable}];}
    clear(){changed('clear',this,{},()=>{this.cells=[];});return this;}clearContents(){return this.clear();}
    setFrozenRows(){}showColumns(){}hideColumns(){}hideSheet(){this.hidden=true;}showSheet(){this.hidden=false;}isSheetHidden(){return this.hidden;}
    createDeveloperMetadataFinder(){return {withKey:key=>({find:()=>this.metadata.filter(m=>m.key===key).map(m=>({getValue:()=>m.value,getLocation:()=>({getColumn:()=>({getColumn:()=>m.col})}),remove:()=>{this.metadata=this.metadata.filter(v=>v!==m);}}))})};}
  }
  const book={sheets:new Map(),getId:()=> 'synthetic-book',getSheetByName(name){return this.sheets.get(name)||null;},insertSheet(name){const sheet=new Sheet(name);this.sheets.set(name,sheet);return sheet;},getSheets(){return [...this.sheets.values()];}};
  const sheet=book.insertSheet('Dati operativi');
  function context(){
    const ctx=vm.createContext({SpreadsheetApp:{ProtectionType:{SHEET:'SHEET'},flush:()=>{}},PropertiesService:{getScriptProperties:()=>({getProperty:k=>properties.get(k)||null,setProperty:(k,v)=>properties.set(k,v),deleteProperty:k=>properties.delete(k)})}});
    for(const file of ['Core','FoglioIncrementale','SincronizzazioneManuale','AccessoGestione','ProiezioneIncrementale','ProiezioneDiretta'])vm.runInContext(fs.readFileSync(new URL('../../src/'+file+'.gs',import.meta.url),'utf8'),ctx);
    ctx.proteggiProiezione_=(target,editable=[])=>{target.editable=editable;};
    return ctx;
  }
  return {book,sheet,calls,properties,state,context};
}
export function view(count=3){return {sola_lettura:false,colonne:[{key:'first_name',label:'Nome'},{key:'balance',label:'Residuo'}],righe:Array.from({length:count},(_,i)=>({codice_ordine:'ORD'+i,numero_partecipante:1,workspace_revision:'1',valori:{first_name:'Persona '+i,balance:100}}))};}
