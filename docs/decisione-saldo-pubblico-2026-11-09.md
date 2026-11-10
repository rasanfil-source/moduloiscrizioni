# Decisione: saldo pubblico per cognome e invio al recapito scelto

Data: 9 novembre 2026. Decisione esplicita dell'utente dopo la valutazione degli audit sulla versione 3.27.40.

## Comportamento accettato

Si accetta per scelta che chiunque acceda alla pagina pubblica di verifica del saldo e digiti un cognome possa conoscere quanto deve pagare la persona individuata. Per il momento la consultazione resta disponibile senza login, codice OTP o verifica dell'identita del richiedente.

La disponibilita pubblica di questa informazione e quindi un requisito intenzionale accettato, non un difetto da correggere d'ufficio. Non introdurre restrizioni alla consultazione nell'ambito delle correzioni agli audit senza una successiva indicazione dell'utente.

Con successiva conferma nella stessa conversazione, l'utente accetta esplicitamente anche l'invio del riepilogo a qualsiasi indirizzo email valido indicato dal richiedente nella pagina, anche diverso da quello registrato nella prenotazione, senza verifica OTP del recapito. Anche questo comportamento deve restare invariato per il momento.

## Distinzione rispetto alla segnalazione email

La precedente classificazione «Priorita alta — saldo pubblico» comprendeva due aspetti distinti:

1. La possibilita di leggere i dati del saldo conoscendo il cognome. Questo aspetto e ora esplicitamente accettato e deve restare invariato.
2. La possibilita di indicare un indirizzo email diverso da quello registrato e far accodare a tale indirizzo il riepilogo della prenotazione. Anche questo aspetto e stato successivamente accettato in modo esplicito e deve restare invariato.

La prova locale dell'audit ha confermato il secondo comportamento con dati sintetici: recapito registrato `demo@example.invalid`, riepilogo accodato a `unrelated@example.invalid`. Non e stato dimostrato un abuso reale e non sono state inviate email durante la valutazione.

La classificazione iniziale derivava dalla possibilita per un terzo di leggere informazioni della prenotazione e farne spedire il riepilogo a un recapito scelto, usando la casella e la quota email del sistema. L'utente ha ora accettato entrambi i comportamenti come funzionalita volute. La classificazione non costituisce quindi una priorita di correzione da applicare automaticamente. L'email contiene il riepilogo generato dal portale, non un contenuto libero scritto dal richiedente.

## Stato dell'intervento

Questa richiesta riguarda esclusivamente documentazione e spiegazione. Sia la consultazione sia l'invio email restano invariati. Nessuna modifica al plugin, nessuna installazione sul sito e nessun nuovo ZIP sono necessari per registrare questa decisione.

Restano valide le altre segnalazioni degli audit, da valutare indipendentemente da questa scelta funzionale.
