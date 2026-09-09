# Modello corrente — 3.25.1

Il progetto è in sviluppo e usa dati fittizi. Non esiste uno storico operativo da migrare.

## Un sistema, tre accessi

La segreteria lavora nel portale WordPress autenticato. Vi accede direttamente, dal collegamento nel foglio evento o dal menu di DB_MODULI. Tutti gli accessi aprono la stessa interfaccia e usano gli stessi servizi.

WordPress/MySQL gestisce eventi, gruppi, identità, permessi, iscrizioni, disponibilità, pagamenti, camere e correzioni operative. Apps Script riceve una replica e rigenera i fogli evento. Le richieste tra sistemi sono firmate; il browser non sceglie liberamente l'operatore o gli eventi autorizzati.

## Lavoro della segreteria

- Cercare una prenotazione nell'evento e vedere partecipanti, importi e singoli movimenti.
- Registrare incassi e rimborsi dal modulo web dei pagamenti.
- Correggere nome e cognome, completare dati mancanti, svuotare dati facoltativi.
- Assegnare o rimuovere camera e sigla pullman.
- Creare camere, modificarne nome/capienza, eliminare camere vuote.
- Annullare una partecipazione tramite il servizio WordPress che gestisce i posti.
- Inserire nuove iscrizioni usando il modulo dell'evento.

I campi richiesti dipendono dalla configurazione dell'evento. La modifica operativa consente completamenti progressivi; il riepilogo segnala ciò che manca. Non è presente un editor delle opzioni economiche delle prenotazioni già acquisite né l'aggiunta di persone a un ordine esistente: si inserisce una nuova iscrizione.

## Registro economico

Pagamenti è il registro canonico. La validazione avviene sul server. I movimenti conservano identità, data, importo, tipo, metodo, riferimento e operatore. I rimborsi e gli storni sottraggono dal versato; i movimenti confermati non si modificano direttamente.

La UI non richiede di classificare ogni incasso come caparra, intermedio o saldo. Le regole economiche dell'evento rimangono nel backend. Importi e aggregati sono calcolati in centesimi. L'annullamento di una partecipazione e il rimborso sono operazioni distinte.

## Consultazione e report

Il foglio evento è una proiezione protetta, ricostruibile da MySQL. Le celle azzurre consentono correzioni dei dati operativi: Sincronizza apre il confronto e la conferma nel portale. Le modifiche pendenti sono conservate; le colonne economiche restano protette. Il portale mostra un riepilogo filtrabile di prenotazioni, residui, dati mancanti e camere non assegnate. I modelli report esistenti rimangono disponibili per stampe e viste specifiche.

Il trigger aggiorna le proiezioni conservando cursore, limite temporale e gestione degli errori. Un aggiornamento del foglio fallito non annulla un pagamento già registrato: la proiezione viene ritentata.

## Coerenza delle modifiche

I salvataggi operativi verificano versione corrente e capienza sotto lock. Un identificativo di richiesta stabile evita duplicazioni. Il registro persistente consente allo stesso operatore di riprendere un salvataggio interrotto dopo aver riaperto la scheda. La replica delle iscrizioni conserva le correzioni operative.

Il payload originale delle iscrizioni resta disponibile nelle strutture esistenti; non è stata introdotta una nuova espansione asincrona dei partecipanti. Questa è una modifica architetturale distinta dalla semplificazione operativa.

## Distribuzione e manutenzione

Deploy del 9 settembre 2026: plugin WordPress 3.25.1 e Web App Apps Script versione 64, sul collegamento esistente. Verificati salvataggio operativo, pagamento unico e modifica Sheet → MySQL. Due attivatori Google avviano il cron e le proiezioni ogni cinque minuti. Il recupero WordPress è stato corretto e collaudato: 38 repliche sincronizzate, nessuna pendente. Stato e verifiche aggiornate in [Passaggio MySQL](docs/PASSAGGIO_MYSQL.md).

Si conservano le tre versioni più recenti disponibili per famiglia di artefatti e tre note di rilascio. La documentazione di sviluppo superata è stata rimossa; la cronologia Git resta invariata.

- [Architettura operativa](docs/ARCHITETTURA_SEGRETERIA_SHEETS.md)
- [Guida operatore](docs/GUIDA_OPERATORE.md)
- [Installazione e verifiche](docs/rilascio-3.25.1.md)
- [Schema logico](docs/SCHEMA_DATI.md)
- [Contratto dell'interfaccia](UX-CONTRACT.md)
