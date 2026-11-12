# Rilascio 3.27.55

12 novembre 2026. Comprende le funzioni della 3.27.54 e le correzioni autorizzate dell'audit.

- Pagamenti parziali di caparra, saldo e quota intera per una persona selezionata. L'importo deve essere positivo e non superare il residuo della rata. Per più persone resta il versamento completo delle quote selezionate, senza ripartizioni implicite. La prenotazione si conferma solo quando tutte le caparre richieste sono coperte.
- Lock del cron passaggi rilasciato dopo ogni evento. Migrazioni del database serializzate, ritentate dopo cinque minuti in caso di errore, con avviso amministrativo e versione aggiornata soltanto dopo i controlli dello schema.
- Manutenzione delle ricevute email separata dall'invio: la chiamata a WordPress avviene fuori dal lock Google, poi le ricevute vengono rilette sotto lock prima della cancellazione.
- Testi multilinea e risposte lunghe conservati nei fogli; protezione dalle formule mantenuta. Le colonne aggiunte a destra dall'operatore non provocano riscritture dei dati invariati.
- Chiusi i comandi Workspace storici di creazione eventi/gruppi/iscrizioni e accodamento comunicazioni. Il sorgente distribuito conserva gli ingressi di MODULI AUTONOMO e le loro dipendenze, escludendo le interfacce centrali inutilizzate.
- Anteprima email in iframe isolato, con lo stesso documento HTML dell'invio; descrizione corretta del logo QR e pattern HTML compatibili con i browser recenti.
- Link di gruppo: una nuova riga non sovrascrive un indirizzo esistente; una pagina WordPress pubblicata con lo stesso percorso ha precedenza.
- Notifiche push conservate dal servizio fino a 24 ore; rinnovo della sottoscrizione e recupero al successivo accesso se la sessione era scaduta o la rete indisponibile.
- Spareggi dei centesimi ordinati esplicitamente per ID partecipante, anche su PHP 7.4. Cache limitata sulla dimensione finale serializzata e sul limite del database; se non entra, la vista viene ricostruita.

## Installazione

1. Caricare `modulo-iscrizioni-3.27.55.zip` in WordPress.
2. Nel solo progetto **MODULI AUTONOMO**, sostituire `Codice.gs` con `Codice-Workspace-Progetto-3.27.55.gs`, salvare e aggiornare la distribuzione Web App esistente mantenendo lo stesso URL. Lo schema dichiarato è 1.14.1. Il codice GAS cambia rispetto alla 3.27.52.
3. Eseguire `attivaCronWordPress` nell'editor: se il trigger esiste non viene duplicato. Verificare il collegamento/schema dal plugin.

Installazione in produzione a cura del gestore. Nessun reinvio delle email già inviate.

## Requisiti e verifiche

Il database deve supportare più lock nominativi nella stessa connessione: MySQL almeno 5.7.5 oppure MariaDB almeno 10.0.2. Sono soglie tecniche del meccanismo, non versioni consigliate per una nuova installazione. Usare versioni del server ancora supportate; i collaudi locali utilizzano PHP 8.3 o successivo e MariaDB 11.4.

Superati 468 test Node, 7 verifiche degli asset e la suite PHP completa, comprese le regressioni del sorgente GAS generato. Superati i collaudi MariaDB/InnoDB di pagamenti e schema e le prove Edge del modulo pagamenti originale e minificato. Invii email e rete dei test sono simulati. I collaudi non attestano una distribuzione sul sito o su Google.

Ambito completo dell'audit: `docs/esito-audit-3.27.55.md`.
