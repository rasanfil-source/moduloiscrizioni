# 3.24.0 — Gestione web unificata

Il progetto usa esclusivamente dati di prova. La nuova architettura privilegia un solo punto operativo: il portale WordPress autenticato.

## Funzioni

- Gestione evento con riepilogo, ricerca e accesso alla scheda prenotazione.
- Correzione nomi e dati mancanti, assegnazione/rimozione camera, creazione e cancellazione camere vuote, controllo capienza.
- Annullamento individuale tramite il servizio WordPress esistente; nuove iscrizioni tramite il modulo dell'evento.
- Modulo pagamenti unico nel portale; storico completo e residuo aggiornato, senza scelta obbligatoria della rata.
- Fogli evento di sola consultazione con collegamento alla gestione web; nessuna acquisizione di righe manuali o confronto fra versioni delle celle.
- Setup ritira i fogli a celle «Registra movimento», «Inserimento pagamenti» e «Registra iscrizione». Il trigger conserva cursore e retry per aggiornare i file evento.

## Installazione di prova

1. Installare il pacchetto WordPress 3.24.0.
2. Sostituire i sorgenti Apps Script con quelli correnti, evitando vecchi file che ridefiniscono funzioni rimosse. Il file completo in dist contiene tutte le funzioni GS; gli HTML ancora usati sono inclusi nel pacchetto Workspace.
3. Eseguire `configuraCartellaDiLavoro()` e ridistribuire la Web App sul collegamento già configurato.
4. Eseguire `sincronizzaFogliEventi()` per rigenerare le proiezioni e rimuovere i vecchi moduli evento. Il menu centrale «Apri gestione web» conduce al medesimo portale.
5. Provare con identità fittizie salvataggio, retry, camera piena, annullamento, importi e aggiornamento dei fogli.

Deploy eseguito l'8 settembre 2026: plugin WordPress 3.24.0 aggiornato e Web App Apps Script pubblicata alla versione 59 sul collegamento esistente. Setup completato. Rimossi dal progetto Google i vecchi file InterfacciaMovimenti.gs, InterfacciaIscrizioni.gs e FinestraPagamenti.html; il modulo pagamenti resta nel portale WordPress.

Verificati connessione firmata, schema e apertura di una prenotazione presente nel centro con saldo e movimenti. Le proiezioni rimuovono le vecchie convalide delle celle prima di riscrivere i dati. I dati storici fittizi incompleti non vengono recuperati, come richiesto. I test con backend sintetico non equivalgono a un collaudo completo delle scritture sul sito.

La sincronizzazione finale ha completato anche l'evento prima bloccato dalle convalide residue (REFRESH SUCCESS). Verificati sul portale il messaggio iniziale «Scegli un evento» e il riepilogo aggiornato. Gli asset del portale includono la data di modifica nella versione della cache, per rendere visibili anche le correzioni distribuite con lo stesso numero di rilascio.

## Pulizia

### Ottimizzazione successiva al deploy (locale)

La scheda gestione riutilizza le letture nella singola richiesta tramite un contesto privato, anche per disponibilità camere e movimenti. Il test misura sette letture di foglio, una per ciascun foglio necessario, e verifica che una richiesta successiva veda immediatamente un nuovo pagamento. Nessuna cache persiste tra richieste; i controlli di salvataggio rimangono sotto lock. Superati 55 test Workspace. Questa ottimizzazione e l'invalidazione frontend aggiunta dopo il deploy non sono ancora pubblicate.

Si conservano le ultime tre versioni disponibili per famiglia di artefatti e le tre note di rilascio più recenti. La cronologia Git non viene modificata. Documentazione corrente, schema e test restano nel repository.

## Verifiche locali eseguite

- 214 test automatici superati su Workspace e WordPress.
- Analisi sintattica di 23 file PHP; sorgenti JavaScript e bundle GAS verificati.
- Browser Edge con backend sintetico: salvataggio, email non valida, retry con lo stesso identificativo, conferma ed Escape, viewport mobile.
- Controllo di sanitizzazione superato e pacchetti ZIP aperti e verificati.

Pacchetti: dist/modulo-iscrizioni-3.24.0.zip e dist/Workspace-3.24.0.zip. I due bundle Codice-Workspace 3.24.0 contengono lo stesso backend completo: scegliere il bundle unico oppure i file separati, evitando di installarli insieme.
