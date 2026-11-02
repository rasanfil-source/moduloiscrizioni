# Modulo Iscrizioni 3.26.275

Rilascio del 2 novembre 2026. Integra i problemi confermati nell'audit autonomo e nella verifica del controaudit. Il progetto Google operativo resta **MODULI AUTONOMO**.

## Correzioni

- **Camere multiple:** il cambio sistemazione consente una o più persone in una multipla condivisa già presente, verificando la sua capienza reale e l'occupazione finale. Singole e multiple da un posto restano protette. L'assegnazione automatica conserva i progressivi individuali M e l'abbinamento manuale per gruppi doppi/tripli incompleti o troppo numerosi.
- **Eliminazione evento:** l'amministratore può conservare il foglio anche quando WordPress non ne aveva salvato l'ID; Apps Script verifica il riferimento registrato, la stessa richiesta e il passaggio esplicito da cestinamento a conservazione.
- **Formato valuta nei fogli:** il confronto con `_MI_BASE` riconosce gli importi non ambigui in centesimi, inclusi `1.250,00`, `1250,00` e `1,250.00`. Solo il valore numerico grezzo esattamente equivalente viene riallineato. Alterazioni reali, anche nascoste dall'arrotondamento visivo, restano bloccate.
- **Prezzi nell'editor:** il prezzo delle opzioni è obbligatorio anche nelle righe aggiunte dinamicamente. Gli importi vuoti o invalidi annullano l'intero salvataggio dei metadati con un messaggio esplicito. Un evento già pubblicato conserva lo stato e la configurazione precedente; una nuova pubblicazione invalida resta bloccata.
- **Migrazione di gruppo:** azzera la categoria del link permanente del vecchio gruppo, conservandola nel log. Il fallimento del controllo iscrizioni conserva la categoria; un errore nella sua rimozione ripristina il gruppo precedente.
- **Email di aggiornamento prenotazione:** il totale corrente comprende soltanto i partecipanti attivi. Le attribuzioni economiche non ricostruibili sono indicate come da verificare.
- **Offerte in lista d'attesa:** scansione ciclica con cursore persistente, massimo 200 offerte per esecuzione. Le offerte successive vengono esaminate anche quando le prime hanno scadenze future; viene applicata anche la scadenza comune dell'evento. Gli errori SQL non avanzano il cursore.
- **Notifiche push:** i retry includono soltanto le sottoscrizioni fallite con errore ritentabile, ricontrollando l'accesso e rispettando il limite dei tentativi.
- **Avvisi email dell'evento:** filtro SQL per `event_id` con protezione dai JSON malformati e ulteriore verifica PHP. Non vengono più caricati in PHP tutti gli avvisi senza iscrizione.
- **Consegne email incerte:** nuova rigenerazione amministrativa tracciata, descritta sotto. Le ricevute GAS e i retry automatici mantengono la protezione dai duplicati.
- Conservata la correzione 3.26.274 che distingue storni e rimborsi nella proiezione del foglio.

La categoria del link permanente resta configurabile dal portale operativo. Il metabox nativo conserva tale scelta; non viene introdotto un secondo selettore in questo rilascio.

## Recupero di una consegna email incerta

1. Verificare il mancato invio nei messaggi inviati e con il destinatario. Non rigenerare una consegna già ricevuta.
2. Attendere la conclusione dei tentativi: l'azione è disponibile solo in `FAILED` o `TEST_FAILED`. Uno stato `SENDING` interrotto viene recuperato dal normale processo della coda; la ricevuta remota incerta non viene cancellata automaticamente.
3. Come amministratore, aprire **Coda email → Apri dettaglio → Rigenera una consegna dopo verifica del mancato invio**.
4. Confermare la verifica e registrarne il motivo. Viene creata una nuova consegna con un nuovo ID; quella originale diventa `SUPERSEDED` e conserva operatore, data, motivo e ID sostitutivo.

La modalità prova/operativa resta quella della consegna originale. La normale azione **Riaccoda** conserva invece l'identità precedente. Richieste ripetute di rigenerazione restituiscono la stessa sostituzione; inserimento e archiviazione sono transazionali e coordinati con l'eliminazione dell'evento. Nessun invio avviene durante il salvataggio amministrativo.

## Distribuzione

- `dist/modulo-iscrizioni-3.26.275.zip`: pacchetto locale, con l'eventuale configurazione locale del saldo pubblico.
- `dist/modulo-iscrizioni-3.26.275-pubblico.zip`: pacchetto senza `public-balance-config.json`.
- `dist/Codice-Workspace-Progetto-3.26.275.gs`: sorgente unico aggiornato per **MODULI AUTONOMO**.

Aggiornare il plugin con il pacchetto appropriato. Per Google sostituire il contenuto di `Codice.gs` nel progetto operativo con il sorgente fornito e aggiornare la distribuzione Web App esistente a una nuova versione, mantenendo URL e proprietà dello script. I sorgenti GAS modificati sono `EliminazioneEvento.gs` e `SincronizzazioneManuale.gs`; non servono file HTML aggiuntivi.

Tutti i pacchetti precedenti restano invariati, incluso 3.26.226. Le modifiche sono consegnate come sorgenti e pacchetti: nessun aggiornamento è stato applicato al sito o al progetto Google di produzione.

## Verifiche

- 411 test Node, inclusi Apps Script; 7 controlli aggiuntivi sugli asset generati.
- 79 script PHP della verifica locale, compreso il test push con cifratura OpenSSL reale e trasporto HTTP simulato.
- Sintassi PHP, asset minificati, controllo di sanitizzazione e `git diff --check`.
- Due suite InnoDB locali: `accommodation-change-innodb.php` (include gestione, concorrenza, automatismi e nuovi casi multiple) e `audit-275-innodb.php` (include eliminazione, filtro JSON, rigenerazione email e rollback).
- Corretto l'adattatore `do_action()` nel test dei link di gruppo, con controllo dell'hook anticache. Inclusa nella verifica ordinaria la suite della migrazione di gruppo.

I test usano dati sintetici; nessuna email, notifica push o richiesta Google reale. Non eseguito un collaudo interattivo sul sito di produzione.
