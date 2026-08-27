# Modulo Iscrizioni — versione 3.5.2

Plugin WordPress per configurare e pubblicare moduli, raccogliere iscrizioni e consegnarle in modo firmato alla console operativa Google Workspace.

## Funzioni principali

- revisioni pubblicate e snapshot ordine immutabili, identificati da hash SHA-256;
- ACL per attività applicate a elenchi, editor, azioni amministrative ed esportazioni;
- idempotenza risolta anche dopo chiusura evento, timeout o esaurimento posti;
- capienza globale e per tipologia protetta da lock transazionali, con lista d’attesa;
- partecipanti associati in modo univoco alla tipologia e alla posizione acquistata;
- opzioni quantitative per ordine o partecipante, ricalcolate sempre sul server;
- profili dati configurabili e approvazione esplicita dei campi privacy ad alto impatto;
- consenso privacy versionato e consenso facoltativo separato per comunicazioni su future iniziative;
- scadenza delle prenotazioni non saldate e annullamento con rilascio idempotente dei posti;
- pagamenti e rimborsi manuali serializzati, auditati e privi di dati completi di carta;
- outbox email con anteprima, prova sintetica, modalità operativa protetta, retry e recupero;
- identificativo `NONE`, `TEXT`, `QR` o `BARCODE`, generato localmente anche nell’email;
- replica Workspace asincrona e riconciliante, marcata `SYNCED` soltanto dopo verifica completa;
- shortcode `[modulo_iscrizioni event="ID"]`, pagina concentrata e modulo Divi 4.
- anteprima riservata isolata dalla toolbar e dagli hook amministrativi del tema.

## Migrazione amministrativa dell’attività

Quando un evento possiede già iscrizioni, il normale editor impedisce di cambiarne l’attività. Un amministratore può usare **Modulo iscrizioni → Migrazione attività**: l’azione richiede evento, attività di destinazione e una frase di conferma che contiene entrambi gli ID. La migrazione aggiorna soltanto il collegamento dell’evento, verifica il numero di iscrizioni prima e dopo, conserva revisioni e istantanee storiche, registra un audit nei metadati e non pubblica contenuti né invia email.

## Limiti intenzionali

Il plugin non riscuote denaro e non conserva coordinate bancarie, numeri completi di carta o credenziali di provider. Bonifico, carta e contante sono soltanto fonti di movimenti registrati manualmente. L’email parte in `ANTEPRIMA`; `OPERATIVO` resta bloccato finché una prova con dati sintetici non è stata accettata dal sistema di posta.

## Installazione di prova

1. Comprimere la cartella `modulo-iscrizioni` in uno ZIP e aggiornare il plugin nell’ambiente autorizzato.
2. Verificare che l’upgrade abbia impostato `mi_db_version` a `3.4.3` e creato le nuove tabelle.
3. Aggiornare Apps Script allo schema `1.2.0`, eseguire `configuraCartellaDiLavoro()` e verificare lo schema dal pannello WordPress.
4. Aprire e salvare gli eventi pubblicati per creare la prima revisione completa; ripubblicare quelli segnalati dopo modifiche all’attività.
5. Collaudare capienza, retry, annullamento, scadenza, email e replica con sole identità fittizie.

Non usare dati reali durante il primo collaudo.
