# Rilascio 3.26.132 — profili operativi e regole condivise

Include le [correzioni della 3.26.131](rilascio-3.26.131.md): apertura verificata con etichetta **Apri**, ricevute delle modifiche normalizzate, controllo delle quote personali e replica completa.

## Cambiamenti

- Il profilo operativo viene risolto in PHP dalla configurazione dell'evento e riportato negli snapshot nuovi. La preparazione del foglio lo memorizza nella replica `Eventi.profilo_operativo`; ogni apertura dal portale invia e verifica il profilo corrente, anche senza iscritti. Il profilo dell'evento prevale sugli snapshot storici; le euristiche GAS restano come compatibilità per dati precedenti. Le viste personalizzate della segreteria conservano le colonne salvate.
- Il riconoscimento di alloggi, pullman e gruppi di alternative è condiviso fra iscrizione, gestione e saldo pubblico. Un gruppo vuoto non disattiva più il fallback dei codici storici `alloggio-`. Categoria e gruppo rimangono distinti: le notti aggiuntive cumulabili non diventano alternative. Gli extra senza gruppo vengono salvati con `null`; restano leggibili le vecchie stringhe vuote. Le categorie esistenti sono conservate.
- Un servizio riconosciuto come alloggio non diventa modificabile dal saldo pubblico soltanto perché il codice inizia con `pullman-`.
- Anche il calcolo delle caparre fisse residue è condiviso in `MI_Payment_People`, insieme a quello percentuale già comune. I limiti delle quote si applicano per persona; permessi, controlli di versione, transazioni e condizioni di applicazione restano nei rispettivi percorsi. Non sono stati unificati i token pubblici con quelli di cancellazione né le impronte di versione con quelle di idempotenza.
- Rimosse le implementazioni GAS di gestione e registrazione pagamenti già disattivate nelle API. Restano gli errori espliciti `USE_MYSQL_MANAGEMENT` e `USE_MYSQL_PAYMENT_LEDGER`, la lettura del saldo e la funzione di impronta usata dalla proiezione. I test dei writer eliminati sono sostituiti da prove dei confini delle API; i controlli economici restano coperti sul registro PHP/MySQL.

## Installazione

1. Aggiornare i sorgenti Apps Script usando `Workspace-3.26.132.zip` oppure il sorgente aggregato della stessa versione. Con il progetto a file separati, eliminare anche il vecchio `Payments.gs`: non deve restare accanto ai file aggiornati. Sostituire integralmente `GestionePortale.gs` e `PagamentiPortale.gs`. Non duplicare i sorgenti aggregati e quelli separati; conservare proprietà private e autorizzazioni.
2. Eseguire `configuraCartellaDiLavoro()` nel file centrale: schema **1.13.0**, nuova colonna finale `profilo_operativo` in `Eventi`. La migrazione accetta anche la struttura precedente senza `domande_json` e conserva le righe esistenti.
3. Aggiornare la distribuzione Web App esistente; poi installare `modulo-iscrizioni-3.26.132.zip` in WordPress. L'apertura rifiuta una versione Workspace che non conferma il profilo corrente.
4. Collaudare un evento vuoto, un evento con vecchie iscrizioni e un cambio di profilo; verificare una vista personalizzata e un supplemento cumulabile.

Le aperture dirette da Google Drive non passano dalla verifica del pulsante Apri. Le vecchie iscrizioni non vengono riscritte soltanto per cambiare il profilo; l'apertura aggiorna la configurazione corrente della replica.

## Verifiche locali

- 305 test Node WordPress/Apps Script: profilo corrente e storico, evento vuoto, migrazioni, ricevute, replica e API ritirate.
- 20 suite PHP, comprese prove MariaDB/InnoDB di pagamenti, concorrenza, quote individuali, cambi servizi/alloggio e sincronizzazione.
- Due prove browser Edge con dati sintetici: apertura obbligatoria con errori/retry e cambio alloggio multiplo.
- Controllo sintattico PHP, asset, sanitizzazione e confronto SHA-256 dei pacchetti con i sorgenti.

Pacchetti preparati localmente. La pubblicazione del codice su GitHub non installa il plugin sul sito e non aggiorna la Web App Google.
