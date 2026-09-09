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
