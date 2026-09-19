/** Richieste riservate al proxy WordPress autenticato tramite verificaBusta_. */
function verificaPrenotazionePagamentoPortale_(payload, lettura) {
  payload = payload || {};
  const codice = normalizzaTesto_(payload.order_code, 64);
  const evento = normalizzaTesto_(payload.event_id, 40);
  if (!codice || !evento) throw new Error('Prenotazione non valida.');
  const prenotazione = (lettura ? lettura.righe(MI_SHEETS.REGISTRATIONS) : convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS))).find(function (r) { return String(r.codice_ordine) === codice; });
  if (!prenotazione || String(prenotazione.id_evento) !== evento) throw new Error('Prenotazione non disponibile per questa iniziativa.');
  return prenotazione;
}
function saldoPagamentoPortale_(payload) {
  verificaPrenotazionePagamentoPortale_(payload);
  return { ok: true, saldo: caricaSaldoFinestraPagamenti(payload.order_code), data: Utilities.formatDate(new Date(), ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone(), 'yyyy-MM-dd') };
}
