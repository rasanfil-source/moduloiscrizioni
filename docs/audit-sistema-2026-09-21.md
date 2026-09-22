**Audit del sistema — 21 settembre 2026**

Esaminati i sorgenti locali del plugin WordPress 3.26.150 e di Apps Script, comprese le modifiche già presenti nella cartella di lavoro. L'analisi riguarda soprattutto gestione iscrizioni, riepiloghi economici, ricerca, coda e proiezione diretta MySQL → Sheets. Non è una verifica della versione effettivamente installata. Nessuna operazione sul sito o sui fogli di produzione.

## Correzioni implementate dopo l’audit

Le quattro anomalie sono state corrette nei sorgenti locali. La proiezione Google usa ora un journal `_MI_WRITE` prima delle modifiche: dopo un timeout o un errore, la successiva esecuzione termina il journal prima di rileggere le modifiche manuali. Quando schema e identità delle righe non cambiano, aggiorna soltanto i blocchi di righe differenti e la corrispondente base `_MI_BASE`; mantiene il riordino manuale delle righe. Le proiezioni portano una generazione monotona per evento, e Google rifiuta consegne più vecchie o retry con contenuto differente. Ogni query che costruisce lo snapshot viene verificata immediatamente.

Il riepilogo del portale riceve i conteggi di saldo e caparra per partecipante, evitando che il debito di una persona nasconda il saldo di un’altra. Per velocizzare le letture, il servizio riusa camere e occupazione nei blocchi di ricerca e conserva per cinque minuti un modello di lettura dell’evento identificato da revisioni, stato e configurazione; permessi e impronta vengono verificati a ogni richiesta.

La verifica standard passa, ma le riproduzioni diagnostiche confermano quattro difetti. Le prove usano dati sintetici e servizi simulati: confermano i comportamenti del codice descritti sotto, non la loro frequenza in produzione.

**1. P1 — Una scrittura interrotta può bloccare i successivi aggiornamenti del foglio**

Riferimenti: `workspace-apps-script/src/AccessoGestione.gs:49`, in particolare righe 56–64; `workspace-apps-script/src/SincronizzazioneManuale.gs:13`.

`scriviProiezioneEvento_()` controlla le modifiche manuali, rimuove i metadati delle colonne e svuota il foglio prima di ricostruirlo. La precedente base `_MI_BASE` rimane disponibile fino alla conclusione della nuova scrittura. Se un'operazione Google fallisce dopo lo svuotamento, il tentativo seguente rileva metadati mancanti o righe rimosse rispetto alla vecchia base e si ferma per conflitto. Il gestore dell'errore ripristina la protezione, ma non i dati precedenti.

Prova: iniettata un'eccezione subito dopo `clear()`, usando il confronto e lo scrittore reali con un foglio simulato. Il secondo tentativo restituisce `conflitti=1` e lascia il foglio vuoto. Nel caso reale la preventiva rimozione dei metadati può produrre anche l'errore sugli identificativi mancanti.

Correzione: rendere la scrittura recuperabile con uno stato persistente di aggiornamento e una copia temporanea verificata, preservando dati e base precedenti fino alla conferma. Il recupero deve distinguere un aggiornamento interrotto da vere modifiche dell'operatore. Non risolvere eliminando indiscriminatamente `_MI_BASE`.

**2. P1 — La proiezione diretta accetta una revisione precedente a quella già applicata**

Riferimenti: `workspace-apps-script/src/ProiezioneDiretta.gs:90`, specialmente righe 95–103; `wordpress-plugin/modulo-iscrizioni/includes/class-mi-workspace-client.php:20`.

Il percorso diretto usa l'uguaglianza del fingerprint per evitare riscritture identiche, ma non verifica l'ordine delle revisioni. Il valore `workspace_event_revision` arriva nel payload senza essere usato per rifiutare dati superati. Anche le revisioni delle singole prenotazioni sono validate sintatticamente, ma non confrontate con l'ultima proiezione applicata.

Prova: inviate al gestore reale, con servizi remoti simulati, prima la revisione 3 e poi la 2. Entrambe ricevono `ready=true`; la seconda riscrive la vista. I lock locali limitano la concorrenza normale, ma non sostituiscono un controllo remoto contro consegne fuori ordine, per esempio dopo ritardi o timeout. La verifica successiva in WordPress può rilevare la discrepanza, ma avviene dopo la scrittura Google.

Correzione: introdurre una generazione monotona della proiezione per evento, incrementata da tutte le mutazioni rilevanti, conservarla in Google e rifiutare generazioni inferiori prima di modificare celle. La sola revisione delle camere non basta a coprire iscrizioni, pagamenti e schema. Conservare il recupero idempotente degli aggiornamenti interrotti alla stessa generazione.

**3. P1 — Un errore SQL intermedio può diventare una proiezione economica incompleta**

Riferimento: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-event-projection.php:28`–35.

`snapshot()` esegue sette letture e controlla `$wpdb->last_error` soltanto dopo l'ultima. Ogni nuova query WordPress ripulisce lo stato della precedente, compreso l'errore: il controllo finale non prova che tutte le letture siano riuscite. Questo comportamento è verificabile nelle implementazioni ufficiali di [wpdb::query()](https://developer.wordpress.org/reference/classes/wpdb/query/) e [wpdb::flush()](https://developer.wordpress.org/reference/classes/wpdb/flush/).

Prova: simulata la mancata lettura dei pagamenti, seguita da letture riuscite. Il codice produce una proiezione valida con `versato_centesimi=0` e `saldo_centesimi=10000`, anziché segnalare che il registro pagamenti non è disponibile. In presenza di movimenti effettivi, il foglio può quindi mostrare temporaneamente importi errati. La prova simula il database; non provoca errori su un database operativo.

Correzione: controllare l'esito e l'errore immediatamente dopo ogni lettura. In caso di errore non produrre né inviare la proiezione, lasciando valido il foglio precedente e il lavoro in attesa di retry.

**4. P2 — Il riepilogo conta i saldi delle prenotazioni come saldi delle persone**

Riferimento: `wordpress-plugin/modulo-iscrizioni/assets/portal-management.js:192`–193.

`settledPeople` somma i partecipanti soltanto per prenotazioni con residuo complessivo nullo. La gestione incassi, invece, permette a una persona di saldare indipendentemente dalle altre.

Prova sull'espressione estratta dal sorgente: prenotazione con due persone da 100 €, prima persona saldata e seconda ancora debitrice di 100 €. Il conteggio delle persone con pagamento completato restituisce 0; il valore corretto è 1. La riga può scomparire perché viene mostrata soltanto se il contatore è positivo. Il difetto riguarda il riepilogo, non modifica i movimenti registrati.

Correzione: produrre sul server i contatori delle persone a partire dalle attribuzioni individuali, separando le posizioni storiche non attribuibili. Applicare lo stesso criterio al contatore delle caparre.

**Interventi consigliati per la velocità**

| Priorità | Punto del codice | Costo attuale | Intervento |
| --- | --- | --- | --- |
| Alta | `assets/portal-management.js:175`; `class-mi-management-service.php:260` | L'apertura della gestione richiede `summary` senza limiti: legge tutte le prenotazioni, persone, pagamenti e dati di supporto prima che la paginazione sia utile. `compact()` riduce il risultato soltanto dopo il calcolo completo. | Separare contatori del riepilogo e prima pagina; caricare inventario e servizi quando richiesti. Conservare contatori affidabili per revisione, senza compensare debiti e crediti personali. |
| Alta | `class-mi-management-service.php:230` | Qualsiasi ricerca testuale o filtro avanzato scandisce nuovamente tutto l'evento in blocchi da 200, anche per restituire 30 risultati. Ogni blocco ricostruisce il riepilogo economico e logistico. | Predisporre campi di ricerca normalizzati e indicizzati; filtrare in SQL preservando accenti, alias e permessi. Per i filtri economici usare una proiezione locale per persona aggiornata nelle transazioni. |
| Alta, intervento piccolo | `class-mi-management-service.php:64`, `:287`, `:346` | `summary()` richiama due volte `rooms()`, che esegue due query, inclusa l'occupazione dell'intero evento. Il costo si ripete in ogni blocco di ricerca. | Leggere camere e occupazione una volta per richiesta e riusarle. Nei percorsi di scrittura invalidare il dato dopo ogni modifica pertinente. |
| Media | `class-mi-sheet-open.php:92`, `:153`; `class-mi-event-projection.php:104` | Anche per riutilizzare il collegamento a un foglio già pronto viene ricostruita e serializzata la proiezione completa. | Usare una generazione economica da verificare prima dello snapshot completo. Deve coprire anche schema, profilo, nuove iscrizioni, presenze e camere, oltre ai pagamenti. |
| Media | `workspace-apps-script/src/AccessoGestione.gs:61`; `ProiezioneDiretta.gs:81` | Ogni fingerprint cambiato provoca la ricostruzione completa di dati, metadati, protezioni e scheda pagamenti. | Distinguere cambi di struttura da cambi dei dati, aggiornare blocchi contigui modificati e ridurre le letture ripetute di base e celle. Mantenere la verifica delle modifiche manuali. |

La scansione avanzata esegue dieci query interne al riepilogo per blocco, oltre al selettore del blocco e alle letture finali. Con 10.000 persone e blocchi pieni significa circa 550 query prima degli ulteriori controlli e metadati: è un conteggio derivato dal codice, non una misura sul server. Il caricamento ripetuto delle camere contribuisce quattro query per blocco.

Google raccomanda di ridurre le chiamate ai servizi e raggruppare letture e scritture: le ottimizzazioni Sheets proposte seguono queste [indicazioni ufficiali](https://developers.google.com/apps-script/guides/support/best-practices). Parte del lavoro è già presente: `setValues()` scrive in blocco e il riepilogo dei pagamenti usa un indice in `WeakMap`. Il miglioramento ulteriore consiste soprattutto nell'evitare ricostruzioni e chiamate non necessarie.

**Verifiche e limiti delle misure**

- Verifica standard `tools/verify.ps1` completata con PHP 8.3.33 e `mbstring`: 333 test Node, 7 ulteriori test sugli asset generati, controlli asset, sanitizzazione, sintassi PHP e tutte le 18 suite PHP elencate dal verificatore superati.
- Eseguito il benchmark sintetico esistente `tools/benchmark-management.php`: a 500 righe, selettore completo 7,37 ms e scansione avanzata 0,34 ms; a 10.000 righe, rispettivamente 242,03 ms e 104,22 ms. Sono singole esecuzioni locali di due operazioni diverse su dati in memoria, senza SQL, rete, WordPress o Google: non costituiscono un confronto prima/dopo né una previsione dei tempi reali.
- Non eseguiti in questo audit i test con MariaDB/InnoDB né un collaudo browser su produzione. Nessuna misura di TTFB, carico PHP, tempo SQL reale o durata Google.
- Gli script diagnostici locali `.tmp/audit-system-repro-2026-09-21.mjs` e `.tmp/audit-system-repro-2026-09-21.php` riproducono i quattro comportamenti descritti. Il loro esito positivo conferma la presenza dei difetti; dopo le correzioni andranno convertiti in test del comportamento corretto.
- Log della verifica standard: `.tmp/audit-system-verify-2026-09-21.log`.

Ordine proposto: correggere i tre difetti della replica; correggere i contatori personali insieme alla separazione del riepilogo; eliminare le letture duplicate; introdurre ricerca indicizzata e generazione della proiezione. Misurare poi tempi e numero di query su eventi sintetici rappresentativi prima di decidere ulteriori interventi.
