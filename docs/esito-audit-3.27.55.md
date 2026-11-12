# Esito dell'audit allegato — 3.27.55

12 novembre 2026. Attuazione dei punti validati e autorizzati, con esclusioni richieste dall'utente.

## Correzioni

| Segnalazione | Esito |
| --- | --- |
| Lock evento nel cron passaggi | Rilascio nel finally di ogni iterazione. |
| Pagamenti parziali | Importo editabile per una persona; controllo autorevole del residuo e conferma solo alla copertura richiesta. |
| Migrazione schema ripetuta/concorrenziale | Lock, backoff, avviso in caso di errore e aggiornamento versione dopo verifica. |
| Ricevute email e chiamata WordPress sotto lock | Chiamata fuori lock, pulizia indipendente dall'invio e rilettura dei valori prima di eliminarli. |
| Interfacce GAS centrali inutilizzabili | Rimosse dal sorgente distribuito mediante selezione degli ingressi operativi e delle dipendenze; sorgenti storici conservati per i test. |
| REST orfani | Rifiutati anche con firma valida i comandi storici di creazione e accodamento. |
| Metabox QR | Testo coerente con logo gruppo, sito e icona predefinita. |
| Anteprima email | Documento completo in iframe sandbox senza script o navigazione della pagina superiore. |
| Testi multilinea/troncati | Conservati nella proiezione operativa, nelle note dei pagamenti e nelle ricevute delle modifiche. |
| Colonne extra | Confronto limitato alle colonne gestite. |
| Slug di gruppo | Rifiuto della nuova riga duplicata e precedenza alle pagine pubblicate. |
| Pattern HTML | Trattini escapati per la sintassi dei browser recenti. |
| Push | TTL 24 ore, gestione rinnovo e recupero al successivo accesso; nessun nuovo permesso richiesto automaticamente. |
| Lock MySQL | Requisito minimo dichiarato nelle note di rilascio. |
| Cent nell'arrotondamento | Spareggio deterministico per ID partecipante. |
| Cache in opzioni | Limite sul dato serializzato, con margine per max_allowed_packet. |

## Esclusioni e verifiche senza modifica funzionale

- Collegamenti pubblici, saldo pubblico e indirizzo di partenza: scelte funzionali confermate dall'utente.
- Tipologie Adulti/Ragazzi e dimensione delle proiezioni per grandi eventi: escluse esplicitamente dall'utente.
- Apostrofo dei telefoni: il confronto applicativo già gestisce la neutralizzazione. Non è stato riprodotto su Google Sheets reale un difetto di visualizzazione; resta la protezione dalle formule.
- Identificativo nelle email forzato a NONE: comportamento intenzionale già documentato in `verifica-messaggi-2026-11-10.md`.
- Slug individuali riservati dopo eliminazione: mantenuta la natura permanente dell'indirizzo, evitando che un vecchio collegamento identifichi un nuovo evento.
- Endpoint push: restano HTTPS validati, senza redirect e con protezione WordPress dagli indirizzi privati. Non è emersa la necessità di una lista chiusa di fornitori.
- Report presenze: anno corrente come intervallo predefinito; le scritture operative normalizzano lo stato in maiuscolo. Nessuna incoerenza riprodotta nel flusso corrente.
- doGet GAS: espone metadati tecnici di schema/modalità, senza dati di iscritti o segreti.

I test verificano codice, trasporto simulato e database sintetico locale. Non sono state inviate email reali né modificati eventi in produzione.
