import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
test('payment index preserves refunds, methods, order isolation and fresh reads',()=>{
 const c=vm.createContext({});
 vm.runInContext(fs.readFileSync(new URL('../src/Segreteria.gs',import.meta.url),'utf8'),c);
 const rows=[
  {codice_ordine:'A',importo_centesimi:10000,tipo_movimento:'INCASSO',fonte_pagamento:'CONTANTE'},
  {codice_ordine:'A',importo_centesimi:2000,tipo_movimento:'RIMBORSO',fonte_pagamento:'CONTANTE'},
  {codice_ordine:'A',importo_centesimi:3000,tipo_movimento:'INCASSO',fonte_pagamento:'CARTA'},
  {codice_ordine:'A',importo_centesimi:1000,tipo_movimento:'STORNO',fonte_pagamento:'CARTA'},
  {codice_ordine:'B',importo_centesimi:90000,tipo_movimento:'INCASSO',fonte_pagamento:'BONIFICO'}
 ];
 const totals=c.riepilogoPagamentiIndicizzato_(rows);
 assert.equal(totals.A.total,10000);assert.equal(totals.A.CONTANTE,8000);assert.equal(totals.A.CARTA,2000);
 assert.equal(totals.B.total,90000);
 assert.equal(c.riepilogoPagamentiIndicizzato_(rows),totals);
 assert.equal(c.riepilogoPagamentiIndicizzato_(rows.slice(0,2)).A.total,8000);
});
