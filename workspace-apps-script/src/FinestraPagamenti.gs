/** Finestra riservata agli operatori del foglio centrale, senza endpoint pubblico. */
function contenutoFinestraPagamenti_() {
  return HtmlService.createHtmlOutputFromFile('FinestraPagamenti').getContent();
}

function apriFinestraPagamenti() {
  SpreadsheetApp.getUi().showModalDialog(HtmlService.createHtmlOutput(contenutoFinestraPagamenti_()).setWidth(920).setHeight(720), 'Inserisci pagamento');
}

function caricaPrenotazioniFinestraPagamenti() {
  const eventi = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS));
  const titoli = {};
  eventi.forEach(function (e) { titoli[String(e.id_evento)] = String(e.titolo || ''); });
  return {
    operatore: Session.getActiveUser().getEmail(),
    data: Utilities.formatDate(new Date(), ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone(), 'yyyy-MM-dd'),
    prenotazioni: convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).map(function (p) {
      return { codice: String(p.codice_ordine), nome: [p.nome_referente, p.cognome_referente].filter(String).join(' '), evento: titoli[String(p.id_evento)] || String(p.id_evento) };
    }).sort(function (a, b) { return a.nome.localeCompare(b.nome, 'it'); })
  };
}

function caricaSaldoFinestraPagamenti(codice) {
  const r = riepilogoMovimentoGuidato_(normalizzaTesto_(codice, 64));
  return { totale: r.total, versato: r.paid, residuo: r.balance, nome: r.referent, evento: r.eventTitle, stato: String(r.registration.stato || ''), rata: String(r.registration.modalita_economica) === 'DEPOSIT_BALANCE' ? (r.paid ? 'SALDO' : 'CAPARRA') : 'INTERO' };
}

function salvaFinestraPagamenti(form) {
  if (!form || !/^dlg_[a-zA-Z0-9-]{15,55}$/.test(String(form.request_id))) throw new Error('Identificativo del movimento non valido. Riapri la finestra.');
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(form.data))) throw new Error('Indica una data valida.');
  const data = Utilities.parseDate(form.data, ottieniFoglioDiLavoroAssociato_().getSpreadsheetTimeZone(), 'yyyy-MM-dd');
  const risultato = registraPagamentoValidato_({
    intake_id: form.request_id, order_code: form.codice, transaction_kind: form.tipo,
    installment_kind: form.rata, effective_at: data, amount: form.importo,
    payment_source: form.metodo, external_reference: form.riferimento,
    operator_label: form.operatore, administrative_note: form.nota, recording_channel: 'WORKSPACE_UI'
  });
  if (risultato.status !== 'CONVALIDATO') return { ok: false, message: risultato.message };
  let message = risultato.message;
  try {
    const r = riepilogoMovimentoGuidato_(form.codice);
    const link = trovaCollegamentoFoglioOperativo_(String(r.registration.id_evento));
    aggiornaProiezionePagamentiPrenotazioneEvento_(SpreadsheetApp.openById(String(link.id_foglio)), String(r.registration.id_evento), form.codice);
  } catch (error) { message += ' Il movimento è salvato; lo storico del foglio evento richiede un aggiornamento.'; }
  return { ok: true, message: message };
}
