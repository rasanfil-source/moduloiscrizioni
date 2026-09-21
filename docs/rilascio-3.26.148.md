# Rilascio 3.26.148 — verifica audit e ottimizzazioni PHP

Il generatore QR rifiuta payload oltre 106 byte invece di codificare silenziosamente un identificativo troncato. Il percorso email restituisce un errore comprensibile prima di contattare Google. La coda ordinaria continua a disabilitare i codici grafici come già previsto: questo intervento non li riattiva.

I rapporti partecipanti normalizzano le chiavi di ordinamento una volta per riga. Ordinamento naturale, accenti, priorità degli annullati, criteri di spareggio e impronte delle esportazioni restano invariati. Il raggruppamento delle presenze comprime i percorsi delle identità senza cambiare l'identità scelta o la deduplicazione degli eventi. Eliminata una variabile inutilizzata nella ricerca del portale; escaping e filtri restano nel componente di ricerca condiviso.

Decisioni sull'audit: conservati i lock globali perché i fogli centrali e il lock Apps Script sono condivisi fra eventi; quello di duplicazione coordina anche l'unicità dei nomi delle copie. Nessuna redistribuzione automatica di differenze sulle caparre fisse. Nessun passaggio a FULLTEXT o ricerca per prefissi, che cambierebbe i risultati.

Il budget cron di 20 secondi non limita la durata di una singola chiamata HTTP, che per la preparazione del foglio arriva a 180 secondi, con ulteriore richiesta in caso di redirect Google. Abbassare il solo budget o timeout rischierebbe ritentativi mentre Google sta ancora scrivendo. Un intervento su questo punto richiede un protocollo di avvio e controllo dello stato delle operazioni; non è incluso in questo rilascio. Analogamente l'archiviazione dei fogli centrali richiede regole di conservazione e indici coerenti, non una semplice cache.

Validazione: test PHP su limiti QR in byte e gestione email, equivalenza degli ordinamenti in entrambe le direzioni, catena di 800 identità, rapporti e filtri. Suite Node e controllo sanitizzazione. Workspace rimane alla versione 3.26.147, distribuzione 89: nessuna modifica GAS richiesta. Installare soltanto lo ZIP WordPress 3.26.148.
