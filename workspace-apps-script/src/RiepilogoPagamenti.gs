function riepilogoPagamenti_(orderCode, lettura) {
  lettura = lettura || creaLetturaGestione_();
  const registration = lettura.righe(MI_SHEETS.REGISTRATIONS).find(function (item) {
    return String(item.codice_ordine) === orderCode;
  });
  if (!registration) throw new Error('Prenotazione non trovata.');
  const payments = lettura.righe(MI_SHEETS.PAYMENTS).filter(function (item) {
    return String(item.codice_ordine) === orderCode;
  });
  const paid = Math.max(0, payments.reduce(function (total, item) {
    const amount = Math.max(0, Number(item.importo_centesimi) || 0);
    return total + (['RIMBORSO', 'STORNO'].indexOf(String(item.tipo_movimento).toUpperCase()) >= 0 ? -amount : amount);
  }, 0));
  const total = Math.max(0, Number(registration.totale_centesimi) || 0);
  const event = lettura.righe(MI_SHEETS.EVENTS).find(function (item) {
    return String(item.id_evento) === String(registration.id_evento);
  });
  return {
    registration: registration,
    movements: payments.slice().sort((a,b)=>new Date(a.data_effettiva)-new Date(b.data_effettiva)).map(serializzaMovimento_),
    eventTitle: event ? String(event.titolo || '') : 'Evento ' + String(registration.id_evento),
    referent: [registration.nome_referente, registration.cognome_referente].filter(String).join(' '),
    total: total,
    paid: paid,
    balance: Math.max(0, total - paid)
  };
}
