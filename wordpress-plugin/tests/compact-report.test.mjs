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
 assert.deepEqual(grid(people),[['Bianchi','D’Amico','De Santis','rossi Anna','Rossi Luca'],['Verdi','','','','']]);
 assert.equal(people[0].last_name,'Rossi');
 assert.deepEqual(grid([]),[]);
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
