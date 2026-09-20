import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';
test('le viste traducono gli stati senza modificare i codici del registro',()=>{
  const ctx=vm.createContext({});
  vm.runInContext(readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),ctx);
  for(const [code,label] of Object.entries({PENDING_PAYMENT:'Da pagare',CONFIRMED:'Confermata',WAITLISTED:'Lista d’attesa',WAITLIST_OFFERED:'Posto proposto',CANCELLED:'Annullata',EXPIRED:'Scaduta'})) {
    const registration={stato:code};
    assert.equal(ctx.valoreCampoElenco_('status',{},registration,{}, {},[]),label);
    assert.equal(registration.stato,code);
  }
});

test('i crediti di una persona non saldano il debito di un altra nei fogli',()=>{
  const ctx=vm.createContext({});
  vm.runInContext(readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),ctx);
  const registration={stato:'CONFIRMED',totale_centesimi:60000,primo_versamento_centesimi:20000,saldo_centesimi:30000};
  assert.equal(ctx.statoPagamento_(registration,60000).code,'CAPARRA_RICEVUTA');
  const first={totale_centesimi:30000,versato_centesimi:60000,saldo_centesimi:0,caparra_centesimi:10000,caparra_residua_centesimi:0};
  const second={totale_centesimi:30000,versato_centesimi:0,saldo_centesimi:30000,caparra_centesimi:10000,caparra_residua_centesimi:10000};
  assert.equal(ctx.statoPagamentoPartecipante_(first,registration,60000).code,'SALDATO');
  assert.equal(ctx.statoPagamentoPartecipante_(second,registration,60000).code,'CAPARRA_DOVUTA');
});
