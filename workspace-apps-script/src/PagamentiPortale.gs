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
function registraPagamentoPortale_(payload) {
  verificaPrenotazionePagamentoPortale_(payload);
  if (!/^wp_[0-9]+_[a-f0-9-]{36}$/i.test(String(payload.request_id)) || !/^WP#[0-9]+ · /.test(String(payload.operator_label))) throw new Error('Operatore o identificativo non valido.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(payload.data))) return { ok: true, saved: false, message: 'Indica una data valida.' };
  const fuso = ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone();
  const data = Utilities.parseDate(payload.data, fuso, 'yyyy-MM-dd');
  if (Utilities.formatDate(data, fuso, 'yyyy-MM-dd') !== payload.data) return { ok: true, saved: false, message: 'Indica una data valida.' };
  const risultato = registraPagamentoValidato_({
    intake_id: payload.request_id, order_code: payload.order_code,
    transaction_kind: payload.tipo, installment_kind: 'NON_ASSEGNATO',
    effective_at: data, amount: payload.importo, payment_source: payload.metodo,
    external_reference: payload.riferimento, operator_label: payload.operator_label,
    administrative_note: payload.nota, recording_channel: 'WORDPRESS_PORTAL'
  });
  if (risultato.status !== 'CONVALIDATO') return { ok: true, saved: false, message: risultato.message };
  let message = 'Movimento registrato in DB_MODULI.';
  try {
    const r = riepilogoPagamenti_(payload.order_code);
    const link = trovaCollegamentoFoglioOperativo_(String(r.registration.id_evento));
    aggiornaProiezionePagamentiPrenotazioneEvento_(SpreadsheetApp.openById(String(link.id_foglio)), String(r.registration.id_evento), payload.order_code);
  } catch (error) { message += ' Il foglio evento richiede un aggiornamento.'; }
  return { ok: true, saved: true, payment_id: risultato.paymentId, message: message };
}
