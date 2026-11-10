# Rilascio 3.27.44 — fogli evento e conservazione delle ricevute email

10 novembre 2026. Aggiornamento del plugin WordPress e del solo progetto Google operativo **MODULI AUTONOMO**.

## Installazione

1. In MODULI AUTONOMO sostituire il sorgente unico con `dist/Codice-Workspace-Progetto-3.27.44.gs`, salvare e aggiornare la distribuzione Web App esistente mantenendo URL e impostazioni.
2. Installare in WordPress `dist/modulo-iscrizioni-3.27.44.zip`. La variante `-pubblico.zip` esclude la configurazione privata del saldo.
3. Aprire dal portale i fogli evento da riallineare. La nuova revisione della proiezione invalida una sola volta la precedente impronta; le successive aperture invariate mantengono il comportamento incrementale.

Non servono file HTML aggiuntivi. Questi file locali non installano aggiornamenti nei servizi remoti.

## Correzioni

- Le domande configurate nello schema corrente prevalgono sulle etichette degli snapshot. Le domande rimosse e le risposte storiche restano visibili.
- I subtotali economici della prenotazione compaiono sulla prima persona attiva; se tutte le persone sono annullate, sulla prima riga storica. Le quote personali restano attribuite alle rispettive persone, senza nuove ripartizioni automatiche.
- La scheda Pagamenti viene protetta prima delle scritture anche se era stata creata da un tentativo interrotto. I movimenti sono ordinati globalmente per data. In sola lettura, Dati operativi viene reso visibile prima di nascondere Gestione evento.
- La verifica multipla restituisce anche SHEET_UNAVAILABLE. WordPress registra l'indisponibilità separatamente dall'assenza accertata del documento, conserva il collegamento e mostra un avviso nel pannello delle produzioni. Una successiva verifica riuscita rimuove l'avviso.
- Il foglio tecnico Consegne estende la propria griglia prima di archiviare le ricevute. Le proprietà vengono eliminate soltanto dopo la scrittura e il flush della copia.
- Rimossa la dichiarazione CSS che sovrascriveva `resize:none` per le textarea della gestione.

## Conservazione del registro email

Decisione dell'utente del 10 novembre: le ricevute tecniche degli invii accettati possono essere eliminate **90 giorni dopo la conclusione dell'evento**; se la conclusione non è indicata, vale la data d'inizio.

È disponibile una conclusione facoltativa nel passaggio Date e posti, nei dettagli principali dell'evento e nell'editor amministrativo. La conclusione deve essere una data valida, uguale o successiva all'inizio. Per gli eventi esistenti il campo resta vuoto, quindi vale l'inizio già registrato.

La pulizia legge le date correnti da WordPress tramite il canale firmato: un rinvio dell'evento rinvia anche la scadenza. Si eliminano soltanto stati ACCEPTED associabili all'evento e scaduti, sia dalle proprietà sia da Consegne. Per prudenza, un invio effettuato dopo la conclusione mantiene comunque almeno 90 giorni dalla sua accettazione. Date mancanti o invalide, eventi non reperibili, ricevute senza associazione ed esiti incerti SENDING restano conservati.

Le ricevute nuove includono l'identificativo dell'evento, senza destinatario o contenuto. Le vecchie ricevute senza associazione non vengono cancellate in base alla sola età; un replay firmato può associarle all'evento senza ripetere l'invio e senza cambiare il momento originario dell'accettazione.

Il controllo si esegue al massimo una volta al giorno durante gli invii e, anche senza nuovi invii, tramite il richiamo `avviaCronWordPress` già disponibile nel progetto. Se quel trigger non è attivo, eseguire una volta `attivaCronWordPress` dall'editor Apps Script per attivare il richiamo ogni cinque minuti. Non vengono aggiunti trigger specifici di pulizia. In alternativa `manutenzioneRicevuteEmail` consente un controllo manuale, senza inviare email.

Consegne viene letto una volta per controllo; si eliminano blocchi contigui dal basso senza riscrivere le altre ricevute. Il lock è condiviso con le consegne. Un'interruzione può lasciare una pulizia parziale ripetibile; gli esiti incerti non vengono sovrascritti. Se WordPress non risponde sulle date, la sola pulizia viene rimandata, senza impedire un nuovo invio valido.

Questa regola riguarda il registro tecnico Google, non la casella di posta e non la coda autorevole o i riepiloghi WordPress. Non viene ripristinato alcun componente centrale dismesso. Le scelte accettate sul saldo pubblico restano invariate.

## Verifiche

Regressioni locali su espansione oltre 1.000 righe, pulizia dalle due sedi, eventi futuri, rinvii, fuso orario, ricevute recenti e storiche, esiti incerti, ripresa dopo interruzione e indisponibilità del servizio date. Prove aggiuntive su etichette correnti/storiche, importi con annullati, protezione prima della scrittura, ordinamento fra prenotazioni, visibilità delle schede e verifica multipla.

Verifica standard completata: **459 test Node**, più 7 prove della cache sul minificato, asset e manifest aggiornati, sanitizzazione, sintassi e suite PHP. Prova browser superata su sorgenti e minificati a 390 e 1280 px: conclusione facoltativa, ordine delle date, ripiego vuoto, assenza di overflow e textarea. I due ZIP vengono confrontati voce per voce con i sorgenti; gli archivi precedenti conservati non vengono ricostruiti.
