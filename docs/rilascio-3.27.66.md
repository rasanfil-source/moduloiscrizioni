# Modulo Iscrizioni 3.27.66

15 novembre 2026. Correzioni comprovate dell'audit su email, compatibilità dei moduli, link dei passaggi e manutenzione dei sorgenti.

## Correzioni

- Errori email limitati a 190 caratteri Unicode prima di registrare qualsiasi esito, anche differito. Il recupero delle righe SENDING e la protezione dai doppi invii restano operativi.
- Pulizia dei riepiloghi `mi_email_job_*` e dei relativi marcatori dopo sette giorni dalla conclusione rilevata. Il cron riconosce anche i lavori mai più consultati. Conserva preparazioni attive, invii pendenti o incerti, stati sconosciuti e letture incomplete; una riapertura azzera la conclusione. Scansione di cento opzioni per passaggio, prosecuzione pianificata e lock condiviso con il worker. Non elimina la coda autorevole, gli esiti delle singole email o le ricevute Google.
- Invio dei moduli sui browser privi di `requestSubmit`, compreso Safari 15: mantiene validazione, conferme, pulsante scelto e salvataggi AJAX. Copre filtri delle iscrizioni, selezione PWA e salvataggio delle modifiche prima di uscire da una scheda.
- Link dei passaggi con segreto casuale individuale. In Gestione, nella scheda della persona, il comando «Collegamento degli spostamenti» consente di revocare il link e accodare il nuovo invio al referente registrato. È disponibile anche all'organizzatore nella pagina del viaggio. La sostituzione è transazionale: se registrazione o accodamento falliscono, il link precedente resta valido. Nessun nuovo obbligo di autenticazione per il destinatario.
- Transizione dei vecchi link HMAC: trenta giorni dall'aggiornamento iniziale dello schema. I nuovi partecipanti usano soltanto il segreto casuale; la revoca individuale disabilita subito anche il vecchio HMAC. La finestra non viene prorogata dai rilasci successivi. I nuovi link restano consultabili fino alla revoca o all'eliminazione dei dati, senza una scadenza arbitraria legata alla fine delle assegnazioni.
- Impronta dell'eliminazione evento calcolata su colonne esplicite. Sincronizzazione, trasporto email, timestamp dei contatori e segreti dei link non invalidano da soli la conferma. Contatti, importi, partecipanti e altri dati sostanziali continuano a invalidarla.
- Eliminati nove metodi REST privati non raggiungibili e le tre funzioni della vecchia replica centrale in `WebApp.gs`, con l'helper esclusivo dei pagamenti. Ritirati i test del percorso rimosso e mantenuti i test delle proiezioni dirette; aggiunta la verifica che i comandi centrali non possano modificare i fogli.

## Comportamenti confermati

- `PROVA_PUNTUALE` invia un campione all'indirizzo scelto dal portale, con richiesta firmata e `test_only`; non è vincolata alla casella configurata per la modalità globale `PROVA`.
- Il link permanente di gruppo sceglie il prossimo evento pubblicato anche fuori dalla finestra di iscrizione. Il modulo continua a controllare l'apertura effettiva.
- Rate limit delle iscrizioni: 60 tentativi per IP e 12 per email/recapito, separati per evento. La scadenza è un'ora dall'ultimo tentativo conteggiato. Il consumo prima del controllo finale dei posti resta invariato.
- Restano le decisioni permanenti su saldo pubblico, email al recapito scelto, condivisione dei fogli e assenza delle vecchie schermate Segreteria nel progetto operativo.

## Ripristino del database

`STALE_EVENT_PROJECTION` impedisce a una vecchia proiezione di sovrascrivere una nuova. Dopo un ripristino di MySQL, aprire il foglio dal portale e attendere il completamento dell'aggiornamento. MODULI AUTONOMO legge la generazione Google, richiede con firma `ADVANCE_PROJECTION_GENERATION` a WordPress e ripete la proiezione con un contatore superiore. Se appare `PROJECTION_RECOVERY_DEFERRED`, verificare il collegamento firmato e la raggiungibilità di WordPress, quindi riprovare «Apri». Non cancellare o abbassare i contatori nelle proprietà Google.

## Distribuzione e verifiche

ZIP locale e pubblico distinti, checksum SHA-256 e sorgente unico per MODULI AUTONOMO. La variante pubblica esclude la configurazione locale del saldo. Installazione a cura del gestore; l'aggiornamento WordPress migra lo schema alla revisione 10.

Il sorgente GAS operativo generato è identico a quello della 3.27.65 (SHA-256 `4ba323e2061b03360f4f0b4b51fc324cdb7d8fd829c8d7998eea0ab005a7f450`): i rami eliminati erano già esclusi dall'esportazione. Se MODULI AUTONOMO usa già quel sorgente, non occorre un nuovo deployment Google. Il file della 3.27.66 è comunque fornito per mantenere insieme il materiale del rilascio.

Verifiche: suite Node e PHP locale, test su MariaDB sintetico per eliminazione e revoca dei link, integrazione completa dei passaggi, collaudi browser dei percorsi nativi e senza `requestSubmit`, confronto degli asset minificati e del contenuto degli archivi con i sorgenti. Nessun invio reale o modifica a eventi di produzione.
