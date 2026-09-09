function apriFinestraPagamenti() { return apriGestioneWeb('payments'); }
function caricaSaldoFinestraPagamenti(codice) {
  const r = riepilogoPagamenti_(normalizzaTesto_(codice, 64));
  return { totale:r.total, versato:r.paid, residuo:r.balance, nome:r.referent, evento:r.eventTitle, stato:String(r.registration.stato || ''), movimenti:r.movements };
}
function serializzaMovimento_(r) {
  const amount = Number(r.importo_centesimi) || 0;
  return { id:String(r.id_pagamento), data:r.data_effettiva instanceof Date ? r.data_effettiva.toISOString() : String(r.data_effettiva || ''), tipo:String(r.tipo_movimento), importo:['RIMBORSO','STORNO'].includes(String(r.tipo_movimento)) ? -amount : amount, metodo:String(r.fonte_pagamento || ''), riferimento:String(r.riferimento_esterno || ''), operatore:String(r.etichetta_operatore || ''), nota:String(r.nota_amministrativa || '') };
}
