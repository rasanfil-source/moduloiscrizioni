# Segreteria eventi 3.26.0 — candidato locale per collaudo

Aggiornamento dopo il nuovo audit: il [confronto prepubblicazione](CONFRONTO_AUDIT_PREPUBBLICAZIONE_2026-09-10.md) individua ulteriori integrazioni consigliate (caparra, contesto evento, colonne di stampa, ricerca e accesso ai report movimenti). Il candidato non va quindi considerato funzionalmente concluso sulla sola base dei test già superati. I pacchetti non sono stati modificati durante questa revisione.

## Contenuto

Implementazione del [progetto operativo](PROGETTO_GESTIONE_ISCRIZIONI.md): persone e prenotazioni, ricerca e filtri combinati, pagamenti nella scheda, stampa/CSV completi, inventario camere e scambi atomici, servizi individuali e d’ordine, richieste verificate, presenze e rapporto annuale. La consultazione dedicata del saldo legge il residuo corrente e viene collegata dai promemoria.

Le variazioni riguardano qualsiasi servizio configurato. Variazione, rettifica motivata del dovuto e rimborso effettivo restano operazioni distinte e manuali. I collegamenti fra persone per il rapporto annuale richiedono verifica e conferma; non vengono dedotti da nomi o contatti condivisi.

Conservati tessere eventi, wizard e modulo iniziale. Il badge verde Gratuito dipende esclusivamente dalla dichiarazione del gestore (`pricing_mode=ZERO`). Nessuna cancellazione o ricreazione degli Sheet evento è prevista da questo aggiornamento.

## Pacchetti locali

- `dist/modulo-iscrizioni-3.26.0.zip`: plugin WordPress.
- `dist/Workspace-3.26.0.zip`: sorgenti Workspace, inclusi i file HTML.
- `dist/Codice-Workspace-3.26.0.gs` e `dist/Codice-Workspace-Progetto-3.26.0.gs`: backend unificato generato; non sostituisce da solo i file HTML del progetto.

I pacchetti delle versioni precedenti sono conservati. La versione nuova distingue anche le risorse CSS/JavaScript nelle cache. Non è stata eseguita installazione, distribuzione Apps Script o spedizione email.

## Verifiche e limiti

Le prove locali comprendono Node, PHP, transazioni concorrenti su MariaDB dedicato e browser. La prova di paginazione usa il JavaScript effettivo e la selezione PHP effettiva con 265 record sintetici: pagine da 30, ricerca oltre la prima pagina, filtri combinati, CSV/stampa completi e rifiuto di un’esportazione se i dati cambiano fra due letture.

Il riepilogo invia nominativi e assegnazioni necessari ai conteggi e alle camere; campi completi e contatti individuali arrivano con le pagine. La selezione delle pagine avviene sul server, ma il servizio continua a leggere l’evento intero per calcolare il riepilogo. Non è una paginazione SQL con costo costante: per eventi molto grandi serve una successiva ottimizzazione delle query e una misura su dati rappresentativi.

Queste prove non sostituiscono un collaudo sull’installazione WordPress completa, sul tema effettivo e su Workspace distribuito. Il candidato non va presentato come già verificato in produzione.

## Collaudo integrato da eseguire nell’ambiente di prova

1. Installare il candidato in un ambiente separato e aggiornare i sorgenti del progetto Workspace di prova, mantenendo configurazione e identificativi esistenti.
2. Usare un evento sintetico e operatori con ambiti diversi. Verificare che elenco, scheda, pagamenti, presenze e rapporto annuale rispettino l’ambito anche nelle richieste dirette.
3. Verificare secondo partecipante, ricerca/pagine/ritorno dalla scheda, bozze e navigazione indietro, viste desktop/mobile e assenza di conflitti con il tema.
4. Registrare un movimento sintetico, una variazione di servizio, una rettifica motivata e un rimborso manuale. Confrontare totale, storico e saldo pubblico.
5. Confermare replica, collegamento allo stesso Sheet e sincronizzazione delle sole celle consentite; provare un conflitto senza applicazioni parziali.
6. Eseguire un report Workspace filtrato, verificando nominativi e importi una volta per ordine. Controllare il promemoria in anteprima, senza inviarlo.

Soltanto dopo questi esiti si può decidere la distribuzione al servizio reale. Le rettifiche e le variazioni già salvate in MySQL non devono essere annullate ripristinando indiscriminatamente un database precedente.
