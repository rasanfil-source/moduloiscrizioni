import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile,readdir} from 'node:fs/promises';
import {createHash} from 'node:crypto';
const directory=new URL('../src/',import.meta.url);
const production=(await Promise.all((await readdir(directory)).filter(n=>n.endsWith('.gs')).map(n=>readFile(new URL(n,directory),'utf8')))).join('\n');
test('produzione completa: collegamento univoco e hash disponibili senza sostituti',()=>{
 let rows=[['id_evento','id_foglio'],[42,'sheet42'],[43,'sheet43']];
 const sheet={getLastRow:()=>rows.length,getLastColumn:()=>2,getRange:(start,col,count)=>({getDisplayValues:()=>[rows[0]],getValues:()=>rows.slice(start-1,start-1+count)})};
 const c=vm.createContext({SpreadsheetApp:{getActiveSpreadsheet:()=>({getSheetByName:()=>sheet})},Utilities:{DigestAlgorithm:{SHA_256:'sha256'},Charset:{UTF_8:'utf8'},computeDigest:(alg,value)=>[...createHash(alg).update(value).digest()]}});
 vm.runInContext(production,c);
 assert.equal(c.trovaCollegamentoFoglioOperativo_('42').id_foglio,'sheet42');
 assert.equal(c.versioneGestione_({a:1}),createHash('sha256').update('{"a":1}').digest('hex'));
 assert.throws(()=>c.trovaCollegamentoFoglioOperativo_('99'),/univoco/);
 rows.push([42,'duplicate']);assert.throws(()=>c.trovaCollegamentoFoglioOperativo_('42'),/univoco/);
});
test('i lettori ritirati non consentono accessi e il vecchio pagamento resta bloccato',()=>{
 const c=vm.createContext({});vm.runInContext(production,c);
 c.verificaBusta_=()=>({ok:true});c.creaRispostaJson_=x=>x;
 for(const action of ['SALDO_PAGAMENTO_PORTALE','RIEPILOGO_GESTIONE_EVENTO','ELENCA_PAGAMENTI'])assert.equal(c.doPost({postData:{contents:JSON.stringify({action,payload:{}})}}).error,'ACTION_NOT_ALLOWED');
 assert.equal(c.doPost({postData:{contents:'{"action":"REGISTRA_PAGAMENTO_PORTALE"}'}}).error,'USE_MYSQL_PAYMENT_LEDGER');
});
