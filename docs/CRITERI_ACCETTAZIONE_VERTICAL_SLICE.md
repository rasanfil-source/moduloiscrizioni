# Criteri di accettazione della prima vertical slice

Questi criteri trasformano i requisiti multi-evento in comportamenti verificabili. Non sostituiscono i vincoli di sicurezza, privacy e integrità descritti negli altri documenti.

## Dashboard organizzatore

- un amministratore WordPress vede tutte le attività e tutti gli eventi;
- un delegato vede e modifica soltanto gli eventi delle attività assegnate;
- un evento attraversa `DRAFT`, `PUBLISHED` e `ARCHIVED` senza cancellare revisioni od ordini;
- la pubblicazione crea una revisione immutabile e segnala errori di configurazione prima di renderla pubblica;
- elenco filtrabile per attività, stato e finestra iscrizioni, senza caricare dati dei partecipanti finché non richiesti.

## Capienza e lista d'attesa

- la capienza è configurabile per evento e viene verificata sul server sotto lock breve;
- il browser non può riservare posti modificando quantità o prezzi;
- quando i posti residui non bastano, l'ordine non viene confermato oltre capienza;
- con waitlist attiva la richiesta diventa `WAITLISTED`, altrimenti viene rifiutata con un messaggio chiaro;
- retry con la stessa idempotency key non occupa posti due volte;
- scadenza o annullamento rilasciano posti soltanto secondo la policy pubblicata e auditata.

## Tipologie di iscrizione

- lo stesso evento può offrire più `ticket_types`, per esempio quota intera, ridotta o gratuita;
- ogni tipo dichiara nome, prezzo in centesimi, limiti per ordine, eventuale capienza propria e stato;
- il totale viene ricalcolato esclusivamente dal server usando la revisione pubblicata;
- un tipo gratuito può convivere con tipi a pagamento senza trasformare l'intero evento in gratuito.

## Email e identificativo

- dopo una scrittura riuscita viene accodata una conferma con evento, data, luogo, riepilogo e codice univoco;
- l'organizzatore sceglie per evento `NONE`, `TEXT`, `QR` o `BARCODE` come presentazione del codice nell'email;
- QR e barcode codificano il medesimo codice testuale e non creano un secondo identificativo;
- nessuna email viene inviata dentro il lock di capienza;
- retry, errore e invio sono tracciati in `EmailOutbox` senza duplicare l'ordine.

## Apertura e chiusura

- ogni evento dichiara data, ora e fuso per apertura e chiusura;
- prima dell'apertura e dopo la chiusura il server rifiuta nuove iscrizioni anche se il frontend è obsoleto;
- il frontend mostra lo stato coerente e non presenta un pulsante operativo fuori finestra;
- una modifica successiva richiede nuova pubblicazione e non altera gli snapshot degli ordini esistenti.

## Prova minima di Fase B

- scenari: sola iscrizione, prezzo non gestito, pagamento completo, caparra e saldo;
- profili dati: `MINIMAL`, `EXTENDED` e rifiuto dei moduli ad alto impatto non approvati;
- tre eventi contemporanei da 100, 50 e 30 partecipanti e margine complessivo fino a 300;
- almeno 20 richieste concorrenti sugli ultimi posti;
- controlli negativi su capability, scope attività, replay, duplicati e payload inattesi;
- workflow di sanitizzazione superato prima di ogni push.
