# 3.26.146 — valutazione dei tre audit

## Correzioni confermate e applicate

- Servizi delle nuove bozze Workspace: codici canonici `alloggio-*`, categoria esplicita e `pullman-standard`, riconosciuti da camere e saldo pubblico. Non rinominati automaticamente servizi di eventi esistenti: cambiare identità di servizi già acquistati richiederebbe una migrazione.
- Letture gestionali: una ripartizione comune incoerente produce una posizione individuale non disponibile, senza interrompere tutte le altre prenotazioni. Le scritture continuano a rifiutare l'incoerenza. Nessuna quota personale fittizia viene calcolata.
- Email facoltativa: inizializzazione dello stato anche quando il partecipante non ha indirizzo email.
- Cambio gruppo dal portale: con prenotazioni esistenti è richiesta la procedura esplicita di migrazione, come nell'editor admin; anche un errore di lettura blocca lo spostamento.
- Liste, filtri, esportazioni e coda email admin usano l'ambito eventi, includendo correttamente gli operatori assegnati a un singolo evento. L'ambito vuoto resta chiuso.
- Disattivazione plugin: rimozione di tutte le occorrenze dei job, comprese quelle con ID negli argomenti.
- Duplicazione: non copia ricevuta di proiezione e stato organizzazione del foglio originale.
- Cron replica/scadenze: rilascio del lease al termine di ciascuna iterazione.
- Validazione testi in caratteri UTF-8 e date nel fuso WordPress; barcode normalizzato in maiuscolo prima del filtro; esportazione CSV protetta anche da controlli iniziali tab/CR/LF.
- Gruppi numerici in Sheets: confronto dopo conversione a stringa. Chiavi di domande con trattino ammesse nella sincronizzazione, mantenendo esclusi importi, identificativi e servizi.
- Messaggi specifici per replay, evento eliminato e richiesta di apertura non valida.

## Valutazione delle altre proposte

Il secondo audit descrive principalmente correzioni già presenti nelle versioni 144/145: non richiede di applicarle di nuovo. L'apertura rapida resta la scelta richiesta: aprire non equivale a importare o verificare ogni correzione manuale. Il confronto con Google continua prima delle riscritture. Esiste già una verifica giornaliera dei file in background, a lotti, che alimenta `_mi_sheet_missing`; non introdotta una nuova scadenza bloccante. Questa verifica usa l'identità del servizio, non certifica i permessi di ogni singolo utente Google.

La versione di gestione e le revisioni delle prenotazioni sono già comprese nell'impronta. Nessuna ricevuta può impedire che un operatore modifichi i dati dopo il redirect: l'aggiornamento successivo passa dalla coda. Invalidare una cache fuori dalla transazione o usare MAX(revision) non sostituirebbe questo controllo.

I test persistenti introdotti nella 145 non resettano la ricevuta nei casi di apertura ripetuta, perdita dei transient, nuove revisioni, sostituzione file e URL non valido. `fresh_step` serve a isolare i casi del percorso lento, non è l'unica modalità di test. Non dichiarato eseguito un nuovo collaudo multi-browser sul sito.

Non applicati come semplici ottimizzazioni: rimozione dei lock di cancellazione; retry automatico delle email in stato incerto; CacheService come unica difesa replay; saldo pubblico con nuovo fattore obbligatorio (contrario alla scelta dell'utente); confronto SQL del cognome senza equivalenza della normalizzazione; rimozione di funzioni senza analisi completa dei chiamanti.

Restano interventi separati: protocollo 2 GAS→WordPress (richiede rollout compatibile con il plugin ancora installato), annullamento eventi come job riprendibile e avvisi idempotenti, riaccodamento manuale degli invii incerti con conferma, batch email/repliche, schema della coda email e presenze, snapshot separati, cache economica persistente, telemetria e ottimizzazione delle proiezioni. Sono proposte plausibili, non correzioni dichiarate completate da questo rilascio. Il solo ID deterministico per annullamento non risolve il limite dei destinatari e il recupero parziale: occorre progettare il job completo.

## Verifiche e installazione

322 test Node; regressioni PHP su saldi, servizi, presenze, apertura fogli, configurazione eventi e liste; nuovi test eseguibili su autorizzazioni, codici servizi, CSV e quote incoerenti. Sintassi PHP e sanitizzazione. GitHub Actions verifica anche MariaDB.

Aggiornare il plugin tramite ZIP e la distribuzione Workspace. Non occorre riconfigurare i fogli o cambiare le autorizzazioni. L'installazione WordPress rimane a cura dell'utente.
