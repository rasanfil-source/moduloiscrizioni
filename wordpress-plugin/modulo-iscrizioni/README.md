# Modulo Iscrizioni — vertical slice 0.2.2

Plugin WordPress dimostrativo per la Fase 2. Implementa:

- attività con logo tramite immagine in evidenza;
- eventi indipendenti con bozza, pubblicazione e archiviazione;
- apertura e chiusura automatica delle iscrizioni;
- capienza server-side e lista d'attesa;
- più tipologie di iscrizione nello stesso evento;
- profili dati Minimo, Standard e Viaggio, con campi partecipante attivabili singolarmente;
- anteprima amministrativa dei campi e validazione allowlist lato server;
- codice ordine univoco;
- email registrate soltanto nell'outbox `PREVIEW`;
- ruolo `Gestore iscrizioni` limitabile alle attività assegnate;
- shortcode `[modulo_iscrizioni event="ID"]`.

## Limiti intenzionali

Questa versione non invia email, non sincronizza ancora le iscrizioni, non gestisce pagamenti e non genera QR/barcode. Include il client HMAC e il controllo `PING` firmato necessari al successivo collaudo Workspace. Le costanti private `MI_WORKSPACE_WEBAPP_URL` e `MI_WORKSPACE_SHARED_SECRET` devono essere definite fuori dal repository, per esempio in `wp-config.php`; il segreto deve contenere almeno 32 caratteri. Il pacchetto 0.2.0 è installato e collaudato sul sito autorizzato; attività ed evento di prova restano in bozza.

## Installazione di prova

1. comprimere la cartella `modulo-iscrizioni` in uno ZIP;
2. installare lo ZIP da WordPress soltanto nell'ambiente autorizzato;
3. creare una attività e impostarne il logo;
4. creare e pubblicare un evento completo;
5. inserire lo shortcode mostrato nel riquadro “Pubblicazione nel sito”.

Non usare dati reali durante il primo collaudo.
