# 3.26.207 — Date pagamenti, caparre e firme Workspace

I filtri della pagina Pagamenti e del CSV convertono i giorni locali nel fuso WordPress in confini UTC. Il limite superiore esclude l'inizio del giorno successivo e rispetta i cambi di ora legale.

Le caparre individuali che non coincidono con la caparra complessiva risultano da verificare. La validità dei totali resta distinta dalla validità delle caparre; i riepiloghi non presentano caparre incoerenti come certe o pari a zero. Il blocco dei pagamenti su posizioni incoerenti resta attivo.

Apps Script invia il JSON serializzato e la sua impronta SHA-256 nel protocollo 2. WordPress verifica la firma e usa esclusivamente il contenuto autenticato, evitando le differenze di serializzazione fra oggetti vuoti e array. Restano i controlli su nonce, scadenza e richieste ripetute. Il ricevitore conserva il supporto al vecchio protocollo.

## Aggiornamento

Installare prima il plugin WordPress, poi aggiornare il codice Apps Script e pubblicare una nuova versione della distribuzione. Il vecchio Apps Script continua a funzionare durante l'aggiornamento di WordPress.

Generazione locale: `tools/package-3.26.207.ps1` prepara e verifica gli ZIP; `node tools/prepara-codice-workspace.mjs` genera i sorgenti GAS. Gli ZIP restano esclusi da Git. Il pacchetto pubblico esclude la configurazione privata del saldo.

## Verifiche

Superati 376 test Node, 7 test degli asset, sintassi PHP, sanitizzazione e suite PHP del verificatore. Le regressioni aggiunte coprono confini UTC e cambi d'ora, caparre incoerenti e sane, buste GAS elaborate da PHP, Unicode, oggetti vuoti, compatibilità legacy, alterazioni e replay.

ZIP verificati file per file rispetto ai sorgenti. Nessuna installazione sul sito o distribuzione Apps Script eseguita da questa procedura.
