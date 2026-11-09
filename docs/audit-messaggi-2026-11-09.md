# Audit della sezione Messaggi — 9 novembre 2026

Analisi dei sorgenti locali della versione 3.27.27: composizione, suggerimenti, eventi, anteprima, prova, coda WordPress, modelli email e trasporto MODULI AUTONOMO. Sono presenti cinque difetti funzionali confermati, una criticità di distribuzione e due limiti rispetto alle richieste recenti. Diversi difetti riguardano le aggiunte recenti.

Le prove usano dati sintetici e servizi simulati. Non sono state inviate email, interrogati dati degli iscritti o modificati plugin e distribuzioni in produzione. Il tempo effettivo di consegna sul sito richiede cron, coda e log della distribuzione reale: qui sono confermati i meccanismi che possono rallentarlo.

## 1. [P1] La nuova prova richiede anche un aggiornamento di MODULI AUTONOMO

Il plugin invia `mode: PROVA_PUNTUALE`, riconosciuto soltanto dall’ultima modifica locale a `workspace-apps-script/src/Email.gs`. Il sorgente Google precedente accetta soltanto `PROVA` e `OPERATIVO`: rifiuta la nuova richiesta con `INVALID_EMAIL_PAYLOAD`. Il worker WordPress la riprova fino a cinque volte, mentre l’interfaccia resta al messaggio iniziale di accodamento.

Il confezionatore 3.27.27 include esclusivamente la cartella del plugin. Non genera né allega il sorgente MODULI AUTONOMO; l’ultimo sorgente unico presente localmente è `Codice-Workspace-Progetto-3.27.18.gs`. Le note 3.27.27 non indicano questa dipendenza. Non è stato accertato quale sorgente sia distribuito sul servizio Google reale: il difetto è confermato per l’aggiornamento del solo ZIP WordPress su una distribuzione Google precedente.

Riferimenti: `class-mi-spedizione-email.php:651`, `workspace-apps-script/src/Email.gs:30`, `tools/package-3.27.27.ps1:3`.

Riproduzione: lo stesso payload sintetico viene rifiutato dal sorgente Google al commit locale HEAD e accettato dall’attuale `Email.gs`.

Correzione: distribuire il nuovo sorgente unico destinato a MODULI AUTONOMO insieme al plugin e rendere visibile un errore di compatibilità, senza presentarlo come semplice attesa di consegna.

## 2. [P2] La prova aspetta il cron e le email già in coda

Quando non esiste già un worker programmato, `pianifica_spedizione()` pianifica l’esecuzione dopo 30 secondi. Il percorso Messaggi chiama solo questo metodo: non usa l’avvio immediato impiegato dal percorso delle iscrizioni. Dopo il reindirizzamento, la prova attende il cron WordPress o l’attivatore esterno. Se questi non partono puntualmente, la consegna slitta.

La prova viene inoltre selezionata insieme alle altre email, in ordine crescente di ID, con un massimo di dieci tentativi elaborati per esecuzione. Non ha priorità. Con 125 messaggi precedenti idonei e un cursore iniziale a zero, la nuova prova può essere raggiunta soltanto al tredicesimo passaggio. Le richieste a Google sono sequenziali e ciascuna può attendere fino a 60 secondi. Gli eventi bloccati vengono saltati: questo dettaglio non elimina l’attesa dietro messaggi effettivamente elaborabili.

Riferimenti: `class-mi-spedizione-email.php:161`, `:325`, `:529`, `:533`; `class-mi-workspace-client.php:66`; `class-mi-registration-service.php:1614`.

Riproduzione: il metodo di pianificazione è stato eseguito con cron simulato e ha registrato un avvio a +30 secondi, senza spedizione immediata.

Correzione: conservare la prova nella coda persistente e avviare subito un worker limitato a quella prova, senza farle attraversare l’intero invio massivo. Mostrare nella schermata lo stato della prova e l’eventuale errore di consegna.

## 3. [P2] Cambiando rapidamente tipo, testo e selezione possono non corrispondere

Il ritorno a un tipo già in cache avviene prima di interrompere la richiesta precedente e prima di incrementare il contatore delle richieste. Sequenza riprodotta: Informazioni → Saldo → Informazioni, con risposta Saldo ancora in corso. Quando arriva, il testo del saldo sovrascrive la casella, mentre la tendina indica Informazioni.

C’è una seconda manifestazione: `saveCurrent()` conserva anche la casella vuota con l’avviso di caricamento. Sequenza riprodotta: Informazioni → Saldo → Verifica partecipazione → Saldo. Tornando al saldo viene riutilizzato il valore vuoto salvato durante il caricamento e non parte una nuova richiesta. La richiesta dell’altro tipo rimane inoltre in grado di sovrascriverlo.

Riferimenti: `assets/portal-communications.js:14`, `:27`, `:29`, `:45`.

Correzione: invalidare sempre la richiesta precedente prima di leggere la cache; distinguere bozze effettive e caricamenti non completati; non memorizzare gli stati di caricamento come messaggi.

## 4. [P2] Disattivare l’email automatica di conferma blocca anche i messaggi manuali

`crea_istantanea_operativa()` eredita il valore `attivo` dal modello dell’email di conferma dell’iscrizione. Se quel modello è disabilitato, una comunicazione manuale in modalità operativa finisce in `PREVIEW` anziché essere spedita. La nuova prova viene forzata in `TEST_PENDING`, ma il trasporto la rifiuta perché lo snapshot è inattivo: i tentativi non possono portarla a destinazione.

L’opzione è presentata all’operatore come «Modello attivo per l’email di conferma», senza spiegare che blocca anche i messaggi manuali.

Riferimenti: `class-mi-modello-email.php:210`, `:236`, `:348`; `class-mi-spedizione-email.php:156`, `:307`, `:620`.

Riproduzione: con `enabled = 0`, una comunicazione manuale produce `attivo = false`, lo stato ordinario è `PREVIEW` e il metodo di invio della prova restituisce `false` prima di contattare Google.

Correzione: separare l’attivazione delle conferme automatiche dall’autorizzazione esplicita a spedire una comunicazione manuale.

## 5. [P2] Dopo la prova si perde la schermata del messaggio preparato

Dopo aver accodato la prova, il reindirizzamento torna a Comunicazioni senza `mi_comm_preview`. La bozza rimane temporaneamente sul server, ma l’interfaccia riparte dal compositore e non offre il modo di tornare alla stessa conferma. L’operatore che voleva provare e poi confermare il messaggio deve tornare indietro nel browser o ricomporlo.

Anche «Torna alla composizione» apre il compositore senza ricaricare evento, tipo e testo della bozza. Le modifiche al messaggio possono quindi essere perse dal normale percorso di revisione.

Riferimenti: `class-mi-portal.php:719`, `:845`, `:1413`, `:1444`.

Riproduzione: il gestore della prova conserva il transient della bozza ma restituisce un URL privo di `mi_comm_preview`.

Correzione: tornare alla stessa conferma dopo la prova e permettere di modificare la bozza precompilando i dati già inseriti.

## 6. [P2] Un doppio invio del modulo di prova crea due email

Il `communication_id` della prova contiene una nuova stringa casuale generata a ogni POST. Due richieste della stessa prova ricevono chiavi diverse e superano quindi la deduplicazione della coda. Il pulsante non viene disabilitato durante la richiesta. Questo difetto è distinto dall’invio operativo finale, che usa una chiave stabile.

Riferimento: `class-mi-portal.php:713`.

Riproduzione: due POST consecutivi con lo stesso token di anteprima producono due chiamate alla coda con `communication_id` diversi.

Correzione: generare un identificativo del tentativo nel modulo e riutilizzarlo per doppi clic e ritrasmissioni. Un nuovo invio intenzionale deve creare un nuovo tentativo soltanto dopo il ritorno dell’esito.

## Limiti ulteriori

- **Banner pesanti:** la gerarchia evento → gruppo → parrocchia è già presente. Il passaggio a `medium_large` riduce le dimensioni solo quando esiste una variante generata da WordPress. Non aggiunge una compressione con limite di peso; i banner con URL esplicito, inclusi gli override di evento/gruppo e il default parrocchia, vengono riutilizzati direttamente. Non sono stati misurati i file immagine del sito reale. Riferimenti: `class-mi-modello-email.php:75`, `:76`, `:82`.
- **Storico eventi:** l’interfaccia nasconde inizialmente i passati ma li carica comunque tutti dal database e li inserisce nell’HTML. «Visualizza eventi passati» cambia soltanto la visibilità delle opzioni. Risolve l’ingombro visivo, ma non il caricamento su richiesta previsto per un archivio che cresce. Riferimenti: `class-mi-portal.php:1390`, `:1440`; `assets/portal-communications.js:63`.

## Verifiche eseguite

- Sintassi dei tre file PHP principali: valida con PHP 8.3.33.
- Test Apps Script di consegna: 19/19 superati, con servizi Google simulati.
- Test esistenti superati: conferma comunicazione, scadenze individuali dei promemoria, riaccodamento, pianificazione in background, identità e firma del gruppo (quest’ultimo include ulteriori regressioni su modello e cancellazioni).
- Riproduzioni mirate: risposta del tipo precedente, cache del caricamento vuoto, incompatibilità del nuovo protocollo con il vecchio sorgente Google, perdita della conferma dopo la prova, duplicazione dei POST, modello disabilitato e pianificazione a +30 secondi.
- Due test storici non risultano più allineati alla nuova implementazione: `email-mode-isolation.test.mjs` verifica una precisa stringa della vecchia selezione SQL; `email-queue-fairness.php` respinge la nuova query nel database simulato. Questi esiti richiedono l’aggiornamento dei test e non dimostrano, da soli, una fuga di destinatari o un blocco della coda.
- Il test di instradamento con database non è stato completato: il runtime invocato non carica `mysqli`. Non è stata eseguita una prova completa su WordPress e MySQL reali.

Script diagnostici conservati in `.tmp/audit-messaggi-2026-11-09.cjs`, `.tmp/audit-messaggi-preview-2026-11-09.php`, `.tmp/audit-messaggi-model-2026-11-09.php` e `.tmp/audit-messaggi-scheduling-2026-11-09.php`.

I sorgenti del plugin non sono stati modificati durante questo audit; gli ZIP già generati restano invariati. Prima del prossimo rilascio occorre risolvere i difetti ed eseguire le verifiche con la coppia plugin / MODULI AUTONOMO aggiornata.
