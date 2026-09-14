# Segreteria eventi 3.26.73

Consolidamento visivo del portale e miglioramenti di leggibilità e accessibilità.

- Conferme dei form del portale uniformate; rimossa la doppia conferma di annullamento evento.
- Tabelle di configurazione e coda email con scorrimento locale; etichette accessibili dei campi e dei pulsanti dinamici.
- Filtri secondari richiudibili, conteggio attivo e segnalazioni accanto a Gestisci.
- Scheda persona con riepilogo economico riferito all'intera prenotazione.
- Riepilogo mobile compatto, pannelli camere/presenze/report coerenti e avvisi distinti per caricamento, errore ed esito.
- Pulsanti, badge, SVG di contatto, dialoghi e token condivisi; titolo compatto, toolbar senza blur e importo senza pulsazione.

Verifica: 251 test automatici, lint PHP e collaudi browser sintetici di gestione completa a 320/390px, conferme, ricerca pagamenti e componenti visivi. Le prove non sostituiscono il controllo sul tema e sui dati dell'installazione di destinazione.

## Installazione manuale

Caricare `modulo-iscrizioni-3.26.73.zip` da Plugin → Aggiungi plugin → Carica plugin e confermare la sostituzione della versione esistente. Verificare che WordPress mostri 3.26.73 e ricaricare il portale. Questo rilascio non richiede modifiche al codice Apps Script rispetto alla 3.26.72.

La pubblicazione GitHub non aggiorna automaticamente WordPress. Archivio installabile e checksum SHA-256 sono generati in `dist/`; gli ZIP restano locali secondo le regole del repository.
