import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../modulo-iscrizioni/assets/portal-management.js',import.meta.url),'utf8');
const context=vm.createContext({Blob,TextEncoder,Uint8Array,DataView});
vm.runInContext(source.slice(source.indexOf('  function reportColumnsWithTimestamps'),source.indexOf('  const esc =')),context);
const grid=people=>JSON.parse(JSON.stringify(context.compactReportRows(people)));
test('compact grid keeps compound surnames and distinguishes every duplicate across rows',()=>{
 const people=[{last_name:'Rossi',first_name:'Luca'},{last_name:'De Santis',first_name:'Ada'},{last_name:' rossi ',first_name:' Anna '},{last_name:'Bianchi',first_name:'Paola'},{last_name:'D’Amico',first_name:'Carlo'},{last_name:'Verdi',first_name:'Luigi'}];
 assert.deepEqual(grid(people),[['Bianchi','De Santis','Rossi Luca','',''],['D’Amico','rossi Anna','Verdi','','']]);
 assert.equal(people[0].last_name,'Rossi');
 assert.deepEqual(grid([]),[]);
});
test('alphabetical reading runs down each column, then right, for every adaptive column count',()=>{
 const readColumns=rows=>rows.length?Array.from({length:rows[0].length},(_,column)=>rows.map(row=>row[column])).flat().filter(Boolean):[];
 for(const count of [0,1,2,6,9,10,11,50,199,200,201,207,401]){
  const people=Array.from({length:count},(_,index)=>({last_name:'Cognome '+String(count-1-index).padStart(3,'0'),first_name:'Ada'}));
  const expected=Array.from({length:count},(_,index)=>'Cognome '+String(index).padStart(3,'0'));
  for(let columns=5;columns<=6;columns++){
   const rows=JSON.parse(JSON.stringify(context.compactReportRows(people,columns)));
   assert.deepEqual(readColumns(rows),expected,'count='+count+', columns='+columns);
  }
  if(count){const layout=context.compactReportLayout(people,'Evento',(text,size)=>text.length*size*.55);assert.deepEqual(readColumns(layout.rows),expected);}
 }
});
test('normalizes case, composed Unicode and whitespace; retains identical people and safe legacy full names',()=>{
 const result=grid([{last_name:' Andre\u0301 ',first_name:'Ada'},{last_name:'ANDRÉ',first_name:'Luca'},{last_name:'De  Luca',first_name:'Eva'},{last_name:'De Luca',first_name:'Eva'},{name:'Cognome composto Nome'}]).flat().filter(Boolean);
 assert.deepEqual(result,['Cognome composto Nome','André Ada','ANDRÉ Luca','De Luca Eva','De Luca Eva']);
});
test('compact XLSX contains only merged event title and a five-column shaded grid, with literal text',async()=>{
 const rows=[['Evento <prova>','','','',''],...grid([{last_name:'=Rossi',first_name:'Ada'},{last_name:'Bianchi',first_name:'Luca'},{last_name:'Verdi',first_name:'Eva'},{last_name:'Zeta',first_name:'Paola'},{last_name:'Delta',first_name:'Carlo'},{last_name:'Omega',first_name:'Piero'}])];
 const blob=context.reportWorkbook(rows,{compact:true}),xml=await blob.text();
 assert.match(xml,/<mergeCell ref="A1:E1"/);assert.match(xml,/Evento &lt;prova&gt;/);
 assert.match(xml,/<c r="A2" s="2" t="inlineStr"/);assert.match(xml,/<c r="B2" s="3" t="inlineStr"/);assert.match(xml,/<c r="A3" s="3" t="inlineStr"/);
 assert.match(xml,/FFFFFFFF/);assert.match(xml,/FFF2F2F2/);assert.match(xml,/orientation="landscape"/);
 assert.match(xml,/>=Rossi<\/t>/);assert.doesNotMatch(xml,/<f>|<autoFilter|Iscrizione|Cancellazione|Luca|Ada/);
 assert.equal((xml.match(/<row /g)||[]).length,3);
});
test('adaptive layout fits 200 records, adds columns, reduces type for long names and keeps every entry',async()=>{
 const measure=(text,size)=>String(text).length*size*.55;
 const short=Array.from({length:200},(_,i)=>({last_name:'Cognome '+String(i).padStart(3,'0'),first_name:'Ada'}));
 const layout=context.compactReportLayout(short,'Evento',measure);
 assert.equal(layout.columns,6);assert.ok(layout.titleHeight+layout.rowHeights.reduce((sum,h)=>sum+h,0)<=layout.pageHeight);
 assert.equal(layout.rows.flat().filter(Boolean).length,200);assert.equal(layout.onePage,true);
 const long=short.map(person=>({...person,last_name:'De Santis della Rovere '+person.last_name}));
 const dense=context.compactReportLayout(long,'Evento con titolo lungo '.repeat(6),measure);
 assert.ok(dense.fontSize<layout.fontSize);assert.ok(dense.titleHeight+dense.rowHeights.reduce((sum,h)=>sum+h,0)<=dense.pageHeight+.01);
 assert.equal(context.compactReportLayout([...short,short[0]],'Evento',measure).onePage,false);
 const rows=[['Evento',...Array(layout.columns-1).fill('')],...layout.rows],xml=await context.reportWorkbook(rows,{compact:true,layout}).text();
 assert.match(xml,/fitToHeight="1"/);assert.match(xml,new RegExp('<sz val="'+layout.fontSize+'"'));assert.match(xml,/<name val="Arial"/);
 const code=String.fromCharCode(64+layout.columns);assert.match(xml,new RegExp('mergeCell ref="A1:'+code+'1"'));
});
