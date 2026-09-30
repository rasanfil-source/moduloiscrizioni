# 3.26.210 — Coda email di test e compatibilità SQL

Include le correzioni della [3.26.209](rilascio-3.26.209.md).

- La coda email di test invia al massimo 25 messaggi per esecuzione, controlla la quota residua e non avvia nuovi invii dopo quattro minuti.
- Un lock impedisce esecuzioni concorrenti. Prima di inviare viene salvato e confermato lo stato TEST_IN_CORSO; dopo l'accettazione viene salvato TEST_INVIATA. Gli esiti incerti richiedono verifica manuale e non vengono reinviati automaticamente. Gli stati restano salvati per singolo messaggio per limitare i duplicati dopo un'interruzione.
- Le quattro inizializzazioni del registro gestionale usano parametri espliciti anche nell'UPDATE, eliminando VALUES(event_id) deprecato.

## Installazione

Lo ZIP WordPress viene caricato dall'utente. Il pacchetto pubblico esclude la configurazione privata; lo ZIP locale la conserva. Aggiornare prima WordPress e poi il progetto Apps Script con il bundle corrispondente: Codice-Workspace-Progetto-3.26.210.gs per il progetto autonomo, Codice-Workspace-3.26.210.gs soltanto per quello storico collegato al workbook. La coda test del workbook appartiene al percorso storico. Pubblicare una nuova versione della Web App per rendere effettivo l'aggiornamento GAS; allegare il sorgente a GitHub non aggiorna Google.

## Verifiche

Verificatore completo superato: 390 test Node, sette test degli asset, 50 suite PHP, controllo sintattico dei 43 file PHP e sanitizzazione dei file destinati al repository.

40 test mirati superati, inclusi otto nuovi test della coda su ripresa, quota, concorrenza, timeout ed errori di invio, stato, log e JSON. Controllo sintattico PHP superato. ZIP verificati file per file tramite SHA-256. Il collaudo InnoDB non è stato eseguito: il database locale sulla porta 33317 non era disponibile. Nessun invio email reale e nessuna installazione WordPress effettuati.
