/** Collaudo isolato: crea UN file nuovo, non apre DB_MODULI e non invia richieste o email. */
function collaudaFogliIncrementaliIsolati() {
  const file = SpreadsheetApp.create('COLLAUDO MODULI - dati fittizi - ' + new Date().toISOString());
  const risultati = file.getSheets()[0].setName('Esiti');
  risultati.appendRow(['Prova', 'Esito', 'Dettaglio']);
  console.log('File di collaudo: ' + file.getUrl());
  const verifica = function (nome, condizione) {
    if (!condizione) throw new Error(nome);
    risultati.appendRow([nome, 'OK', '']);
  };
  const vista = {
    evento: { id: 'COLLAUDO_ISOLATO' },
    colonne: [{ key: 'phone', label: 'Cellulare', gruppo: 'persona' }, { key: 'email', label: 'Email', gruppo: 'persona' }],
    righe: [{ codice_ordine: 'ORD-COLLAUDO', numero_partecipante: 1, valori: { phone: '00123', email: 'demo@example.invalid' } }]
  };
  const scheda = file.insertSheet('Dati operativi');
  const mappa = function () { return mappaColonneEvento_(scheda); };
  try {
    scriviFoglioOperativoEvento_(scheda, vista);
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Refresh ripetuto senza duplicati', scheda.getLastRow() === 2);
    verifica('Zeri iniziali conservati', scheda.getRange(2, mappa().phone).getValue() === '00123');
    scheda.getRange(1, mappa().phone).setValue('Telefono rinominato');
    scheda.moveColumns(scheda.getRange(1, mappa().phone, scheda.getMaxRows(), 1), scheda.getLastColumn() + 1);
    vista.righe[0].valori.phone = '00456';
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Colonna spostata e rinominata', scheda.getRange(2, mappa().phone).getValue() === '00456' && scheda.getRange(1, mappa().phone).getValue() === 'Telefono rinominato');
    scheda.getRange(2, mappa().phone).setValue('00999');
    verifica('Modifica manuale conservata', scriviFoglioOperativoEvento_(scheda, vista).manuali === 1);
    vista.righe[0].valori.phone = '00888';
    verifica('Conflitto riconosciuto', scriviFoglioOperativoEvento_(scheda, vista).conflitti === 1 && scheda.getRange(2, mappa().phone).getValue() === '00999');
    vista.colonne.splice(1, 0, { key: 'transport', label: 'Tratta dimostrativa', gruppo: 'servizi' });
    vista.righe[0].valori.transport = 'Pullman prova';
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Nuova colonna adiacente', mappa().transport === mappa().phone + 1 && scheda.getRange(2, mappa().transport).getValue() === 'Pullman prova');
    verifica('Gruppo comprimibile', scheda.getColumnGroupDepth(mappa().transport) > 0);
    vista.colonne = vista.colonne.filter(function (campo) { return campo.key !== 'transport'; });
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Colonna storica conservata', scheda.getRange(2, mappa().transport).getValue() === 'Pullman prova');
    scheda.getRange(2, mappa().email).setFormula('="Formula manuale"');
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Formula manuale conservata', scheda.getRange(2, mappa().email).getFormula() === '="Formula manuale"');
    scheda.getRange(3, mappa().phone).setValue('Bozza dimostrativa');
    scriviFoglioOperativoEvento_(scheda, vista);
    verifica('Riga incompleta non acquisita', !scheda.getRange(3, mappa()._ordine).getValue());
    // Eliminazione limitata alla riga fittizia del file appena creato.
    scheda.deleteRow(2);
    verifica('Riga centrale ripristinata', scriviFoglioOperativoEvento_(scheda, vista).aggiunte === 1 && scheda.getLastRow() === 3);
    const storico = file.insertSheet('Migrazione');
    storico.getRange(1, 1, 2, 4).setValues([['Codice prenotazione', 'Numero partecipante', 'Cellulare', 'Email'], ['ORD-COLLAUDO', 1, 'Modifica precedente', 'demo@example.invalid']]);
    storico.addDeveloperMetadata('MI_CAMPI', '["phone","email"]');
    verifica('Migrazione conserva divergenze', scriviFoglioOperativoEvento_(storico, vista).conflitti === 1 && storico.getRange(2, mappaColonneEvento_(storico).phone).getValue() === 'Modifica precedente');
    const pagamenti = preparaPagamentiEvento_(file, vista.evento.id);
    const colonnePagamenti = mappaColonneEvento_(pagamenti);
    verifica('Struttura Pagamenti', Object.keys(colonnePagamenti).length === colonnePagamentiEvento_().length);
    verifica('Convalida esplicita predisposta', !!pagamenti.getRange(2, colonnePagamenti.convalida).getDataValidation());
    verifica('Modalità con elenco', !!pagamenti.getRange(2, colonnePagamenti.fonte).getDataValidation());
    risultati.appendRow(['RISULTATO', 'SUPERATO', 'Solo struttura e aggiornamento locale; flussi centrali e WordPress ancora da collaudare.']);
    SpreadsheetApp.flush();
    return { ok: true, url: file.getUrl() };
  } catch (errore) {
    risultati.appendRow(['RISULTATO', 'FALLITO', neutralizzaFormula_(errore.message, 1000)]);
    SpreadsheetApp.flush();
    throw new Error('Collaudo fallito; consultare Esiti nel file: ' + file.getUrl() + '. ' + errore.message);
  }
}
