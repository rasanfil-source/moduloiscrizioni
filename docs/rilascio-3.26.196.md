# 3.26.196 - Correzioni degli audit

Include le correzioni documentate in [correzioni-audit-2026-09-26.md](correzioni-audit-2026-09-26.md): scadenza dopo proroga, condizioni economiche della lista d'attesa, retry con dati diversi, recupero email, date facoltative, servizi Alloggio, campi personalizzati, recapiti, presenze, filtri ed esportazioni.

Apps Script conserva le colonne con risposte e servizi storici e riconosce le modifiche multilinea nel recupero senza ricevuta. Non cambia manifest, permessi, proprieta dello script o regole di condivisione.

## Installazione

1. Caricare `modulo-iscrizioni-3.26.196.zip` in WordPress, sostituendo il plugin esistente. Lo ZIP `-pubblico` esclude la configurazione privata locale ed e quello adatto alla distribuzione pubblica.
2. Nel progetto Apps Script autonomo esistente, conservare una copia del codice corrente e sostituire `Codice.gs` con `Codice-Workspace-Progetto-3.26.196.gs`.
3. Aggiornare la distribuzione Web App esistente con una nuova versione, mantenendo URL, account esecutore, manifest e proprieta. Non creare un nuovo endpoint.

Gli archivi preparati non implicano installazione sul sito o distribuzione Google. Gli esiti effettivi delle operazioni remote sono comunicati separatamente.

## Verifiche e compatibilita

362 test Node, 7 test asset, suite PHP, lint, sanitizzazione e regressioni InnoDB locali superati prima del confezionamento. Gli ZIP sono verificati file per file rispetto ai sorgenti; i checksum SHA-256 sono inclusi separatamente.

Per le vecchie richieste prive dell'impronta del payload, un retry chiede verifica alla segreteria invece di confermare dati non confrontabili. Nessuna migrazione distruttiva e nessun reinvio automatico delle email gia concluse. Nessun collaudo con destinatari reali.
