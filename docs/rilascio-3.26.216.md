# Versione 3.26.216

Corregge i sei difetti riprodotti nell'audit del 2 ottobre 2026.

## Correzioni

- Totale, versato e residuo usano gli importi individuali quando quote e pagamenti sono noti, anche se le caparre sono incoerenti. Stato pubblico, gestione e proiezione Google non compensano più il debito di una persona con il credito di un'altra; la caparra resta indicata come da verificare.
- L'annullamento di una prenotazione dal portale attiva lo scorrimento della lista d'attesa. L'annullamento esplicito dell'evento conserva la soppressione delle proposte.
- Cache e lettura SQL usano la stessa impronta della selezione. La scadenza della cache non blocca più «Carica altri» o le esportazioni quando i dati non sono cambiati; cambi reali e cambi nelle finestre temporali restano rilevati.
- Ordinamenti alfabetici e per stanza vengono applicati prima della paginazione, con selezione a blocchi e confronto naturale. Le pagine non dipendono dalla collation testuale SQL.
- Le definizioni storiche dei campi data personalizzati senza `date_rule` mantengono il comportamento generico anche nella gestione e nel salvataggio dal foglio.
- Gli obblighi `ONE` restano sul primo partecipante originario anche dopo l'annullamento; riepilogo gestionale, saldo pubblico, filtri ed email usano la stessa regola. I servizi condivisi mantengono la gestione del primo partecipante attivo.

## Verifiche

La verifica locale ha superato 393 test Node, 7 test degli asset, 53 suite PHP, lint di 43 file PHP e il controllo di sanitizzazione. Sono stati aggiunti test permanenti per saldi, lista d'attesa, paginazione/cache, date storiche e obblighi `ONE`.

Aggiornare il solo plugin WordPress; Apps Script invariato. La preparazione locale non installa il plugin sul sito.
