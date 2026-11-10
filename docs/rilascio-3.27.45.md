# Rilascio 3.27.45 — correzioni dell’audit sulla 3.27.44

10 novembre 2026. Tutti e cinque i rilievi sono confermati sui sorgenti locali.

## Installazione

1. In **MODULI AUTONOMO**, sostituire il sorgente con `dist/Codice-Workspace-Progetto-3.27.45.gs`, salvare e aggiornare la distribuzione Web App esistente mantenendo il suo URL.
2. In WordPress, installare `dist/modulo-iscrizioni-3.27.45.zip`. Per la distribuzione pubblica usare `dist/modulo-iscrizioni-3.27.45-pubblico.zip`, che esclude la configurazione privata del saldo. Il plugin crea la tabella degli identificativi dei comandi durante l’aggiornamento.

## Correzioni e comportamento operativo

- **Foglio Google.** La verifica e la proiezione diretta individuano la scheda partecipanti tramite il metadato di scheda `MI_ID_EVENTO`. Se è stata rinominata, ripristinano il nome «Dati operativi». Se il nome è già occupato da un’altra scheda o i metadati indicano schede incompatibili, segnalano indisponibilità e conservano il collegamento. Un foglio precedente con la scheda «Dati operativi» priva di metadato può ancora essere riconosciuto.
- **Coda email.** Se la lettura SQL dell’iscrizione fallisce, l’email resta in attesa e conserva il numero di tentativi. Il recupero automatico riprova la lettura. Una lettura riuscita che accerta l’assenza dell’iscrizione produce ancora un errore definitivo. Per un’email già segnata come fallita dalla 3.27.44 con zero tentativi, verificare che l’iscrizione esista e usare «Riaccoda» se l’invio è ancora dovuto.
- **Eventi di più giorni.** Messaggi, schede del portale e organizzazione Drive usano la conclusione dell’evento fino alla fine del relativo giorno nel fuso di WordPress. In mancanza della conclusione vale l’inizio, con la chiusura iscrizioni come ultimo ripiego per le schede storiche. Un foglio già spostato prematuramente torna tra i correnti al successivo ciclo di organizzazione se l’evento è ancora in corso.
- **Capienza pubblica.** Se fallisce la lettura dei contatori dell’evento o delle singole quote, il modulo mostra un invito a riprovare senza esporre un numero di posti. L’API risponde con HTTP 503. Una lettura riuscita senza contatori per un evento nuovo continua a rappresentare zero prenotazioni.
- **Comandi firmati.** Prima di eseguire il comando, WordPress registra l’impronta dell’identificativo monouso in MySQL con una chiave primaria univoca. La ricevuta copre l’intera validità della firma calcolata sul timestamp del mittente, compreso lo scarto ammesso fra gli orologi. Se la scrittura fallisce, il comando riceve HTTP 503 e può essere ritentato. Le ricevute scadute vengono eliminate a lotti durante le richieste autenticate successive.

## Verifiche

Superati 460 test Node, 7 prove aggiuntive sugli asset minificati e la verifica PHP del progetto. Le prove locali coprono scheda rinominata, identità in conflitto, ripresa della coda operativa e di prova, evento in corso, guasti ai due livelli di contatori, perdita della cache e orologio mittente avanti di 110 secondi. Su MariaDB 11.4 otto processi PHP hanno presentato contemporaneamente lo stesso comando firmato. Uno è stato accettato e sette hanno ricevuto HTTP 409. Anche una successiva ripetizione da un nuovo processo è stata respinta.
