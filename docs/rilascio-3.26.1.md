# Segreteria eventi 3.26.1

## Completamento funzionale

Il pacchetto comprende il progetto operativo del candidato 3.26.0 e le cinque integrazioni emerse dal confronto prepubblicazione:

- Caparra prevista, ancora da coprire e caparra coperta con saldo residuo, derivate dal versato netto; filtri solo per prenotazioni ammesse. Caparra zero non equivale a denaro versato.
- Comando «Gestisci iscrizioni di questo evento» nel pannello esistente, anche per eventi annullati; navigazione che conserva il contesto evento.
- Stampa dedicata con colonne selezionate, evento, filtri e tutti i risultati, senza cambiare la lista aperta.
- Regole condivise di ricerca per parole, nominativi dei partecipanti e contatti del referente; ricerca Pagamenti paginata con nominativi visibili nei risultati.
- Collegamento al report movimenti già esistente, filtrato per evento e protetto dalle autorizzazioni attuali.

Conservati tessere, wizard, modulo pubblico iniziale, MySQL autorevole e Sheet degli eventi. Restituzioni manuali per disdetta o variazioni concordate; nessun rimborso automatico.

## Verifiche locali

230 test Node superati, sintassi PHP/JS, controlli di sanitizzazione, test PHP economici e delle liste, transazioni InnoDB e ricerca su 65 prenotazioni sintetiche. Prove browser su bozze, pagamenti, ricerca completa, filtri e stampa/CSV di 265 persone; verificata la stampa limitata alle due colonne selezionate. Nessun movimento reale o invio email eseguito.

La paginazione riduce il trasferimento dei record completi; il riepilogo server continua a leggere l’evento intero per i conteggi. I contatti personali eventualmente raccolti nei campi dinamici non sono confusi con email e telefono del referente.

## Stato della pubblicazione

### Conferma finale dell’operatore e controllo del portale

L’operatore ha successivamente confermato installazione WordPress 3.26.1, aggiornamento dei sorgenti MODULI e deployment sulla distribuzione esistente. Il precedente blocco degli strumenti descritto sotto è quindi superato dalla pubblicazione manuale. Il numero finale della versione Apps Script non è stato rilevato indipendentemente dopo l’ultimo aggiornamento; non si assume che coincida con il numero 67 comunicato prima del chiarimento sui sorgenti.

Controlli in sola lettura sul portale pubblicato, dopo Ctrl+F5: tessere e badge Gratuito presenti, apertura pannello evento e collegamento Gestisci iscrizioni funzionanti, evento mantenuto nel riepilogo, elenco individuale caricato, filtro Caparra e colonne CSV/stampa disponibili. Aperta una prenotazione di collaudo: caparra prevista, importo ancora da coprire e residuo visibili; stato replica allineato con data precedente al rilascio. Il collegamento Google apre il foglio evento associato, con le schede Gestione evento, Dati operativi e Pagamenti. Il report movimenti si apre con l’evento già selezionato e i filtri data/fonte/tipo disponibili.

Passi di pubblicazione chiusi sulla base della conferma dell’operatore e di questi controlli. Nessuna iscrizione, pagamento, configurazione o cella Google modificata durante la verifica. Non è stata forzata una nuova replica né eseguita una prova di scrittura dopo il rilascio: lo stato allineato letto non dimostra una replica effettuata dal nuovo codice.

### Cronologia del blocco precedente

Pubblicazione autorizzata dall’utente. Sessioni WordPress e Google Apps Script disponibili; progetto MODULI individuato. La revisione automatica ha però rifiutato l’apertura del progetto per limite d’uso dell’account, prima di qualsiasi aggiornamento remoto. Nessun tentativo di aggirare il blocco.

**WordPress e Workspace non ancora aggiornati.** Dopo il ripristino dell’accesso agli strumenti, installare il pacchetto 3.26.1 e aggiornare i sorgenti del progetto MODULI esistente, mantenendo distribuzione, proprietà, attivatori e identificativi dei fogli. Verificare la versione installata, i percorsi operativi e la replica. Le prove locali non attestano il collaudo completo del runtime WordPress/Workspace distribuito.

Pacchetti: `dist/modulo-iscrizioni-3.26.1.zip`, `dist/Workspace-3.26.1.zip`; backend unificato `dist/Codice-Workspace-Progetto-3.26.1.gs`. Aggiornare anche i file HTML pertinenti. Le versioni precedenti restano conservate.
