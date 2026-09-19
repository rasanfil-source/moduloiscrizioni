import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile,readdir} from 'node:fs/promises';
const src=new URL('../src/',import.meta.url);
const source=(await Promise.all(['WebApp.gs','PagamentiPortale.gs'].map(f=>readFile(new URL(f,src),'utf8')))).join('\n');
test('vecchia registrazione pagamenti rifiutata, lettura saldo conserva il vincolo evento',()=>{
 const c=vm.createContext({normalizzaTesto_:v=>String(v||''),MI_SHEETS:{REGISTRATIONS:'orders'},ottieniSchedaObbligatoria_:()=>[{codice_ordine:'A',id_evento:'42'}],convertiRigheInOggetti_:v=>v,Utilities:{formatDate:()=> '2026-09-19'},ottieniFoglioDiLavoroAssociato_:()=>({getSpreadsheetTimeZone:()=> 'Europe/Rome'}),caricaSaldoFinestraPagamenti:()=>({balance:1200})});
 vm.runInContext(source,c);c.verificaBusta_=()=>({ok:true});c.creaRispostaJson_=v=>v;
 assert.equal(c.doPost({postData:{contents:JSON.stringify({action:'REGISTRA_PAGAMENTO_PORTALE',payload:{}})}}).error,'USE_MYSQL_PAYMENT_LEDGER');
 assert.equal(c.saldoPagamentoPortale_({event_id:'42',order_code:'A'}).saldo.balance,1200);
 assert.throws(()=>c.saldoPagamentoPortale_({event_id:'43',order_code:'A'}),/non disponibile/);
});
test('nessun writer ritirato è presente nei sorgenti distribuiti',async()=>{
 const names=(await readdir(src)).filter(f=>f.endsWith('.gs'));
 const all=(await Promise.all(names.map(f=>readFile(new URL(f,src),'utf8')))).join('\n');
 assert.doesNotMatch(all,/function (registraPagamentoPortale_|registraPagamentoValidato_|registraPagamentoConLock_|pagamentoCorrisponde_)/);
});
