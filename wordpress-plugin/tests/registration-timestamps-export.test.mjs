import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=await readFile(new URL('../modulo-iscrizioni/assets/portal-management.js',import.meta.url),'utf8');
const context=vm.createContext({Blob,TextEncoder,Uint8Array,DataView});
vm.runInContext(source.slice(source.indexOf('  function reportColumnsWithTimestamps'),source.indexOf('  const esc =')),context);
test('new event exports automatically append timestamps before optional code; old layouts stay identical',()=>{
 const cols=[['code','Prenotazione'],['name','Partecipante']];
 assert.equal(context.reportColumnsWithTimestamps(cols,false),cols);
 assert.deepEqual(Array.from(context.reportColumnsWithTimestamps(cols,true),c=>c[0]),['name','registration_time','cancellation_time','code']);
 assert.deepEqual(Array.from(context.reportColumnsWithTimestamps([['name','Partecipante']],true),c=>c[0]),['name','registration_time','cancellation_time']);
});
test('real XLSX serializer preserves timestamp seconds and empty cancellation without interpreting text as formulas',async()=>{
 const columns=context.reportColumnsWithTimestamps([['name','Partecipante'],['code','Prenotazione']],true);
 const rows=[Array.from(columns,c=>c[1]),['Persona sintetica','2026-11-09 11:20:30','','ORD-1'],['Altra persona','2026-11-09 11:21:31','2026-11-09 12:30:40','ORD-2']];
 const blob=context.reportWorkbook(rows),bytes=new Uint8Array(await blob.arrayBuffer());
 const xml=new TextDecoder().decode(bytes);
 assert.ok(xml.includes('Iscrizione (data e ora)'));assert.ok(xml.includes('Cancellazione (data e ora)'));
 assert.ok(xml.includes('2026-11-09 12:30:40'));assert.ok(xml.includes('<c r="C2" s="0" t="inlineStr"><is><t xml:space="preserve"></t></is></c>'));
 assert.ok(xml.includes('<c r="D2" s="0" t="inlineStr"><is><t xml:space="preserve">ORD-1</t>'));
 assert.equal(new DataView(bytes.buffer).getUint32(0,true),0x04034b50);
});
