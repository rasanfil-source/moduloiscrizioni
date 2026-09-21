# Candidato plugin 3.26.150 — editor evento senza avvisi PHP

Questo micro-rilascio aggiorna soltanto il plugin WordPress. La Web App autonoma Workspace resta alla versione 3.26.149 già pubblicata.

## Correzione

- Il catalogo del campo `phone` espone sempre la chiave descrittiva `help`.
- Il rendering dell’editor evento usa inoltre un valore vuoto di sicurezza quando un’estensione o un catalogo incompleto omette `help`.
- È aggiunto un controllo di regressione per entrambe le garanzie.

## Verifica

- 333 test Node principali e 7 test cache superati nella verifica finale del 21 settembre 2026.
- Lint di tutti i file PHP e suite PHP superati con PHP 8.3.33 e `mbstring`.
- Asset generati e sanitizzazione superati.
- `git diff --check` non segnala errori di whitespace.
- `modulo-iscrizioni-3.26.150.zip`: SHA-256 `5f5348f484f2292ab5264c37ffa29a19e2d066bf24ac76ad116967d855cea20d`.

## Installazione e rollback

Installare `modulo-iscrizioni-3.26.150.zip` sopra la versione 3.26.149 e riaprire l’evento 8083: l’avviso `Undefined array key "help"` non deve più comparire. Conservare `modulo-iscrizioni-3.26.149.zip` come rollback. Non modificare il deployment Apps Script per questo rilascio.

## Esito di produzione

Installazione verificata il 21 settembre 2026:

- la pagina dei plugin riporta Modulo Iscrizioni 3.26.150 attivo;
- il portale pubblico carica gli asset con versione 3.26.150 e non mostra errori PHP;
- dopo il ricaricamento dell’evento 8083 non compaiono né `Undefined array key "help"` né altri warning PHP;
- il deployment Workspace autonomo 3.26.149 non è stato modificato.

## Correzione Workspace successiva (21 settembre 2026)

L'apertura del foglio da Gestione Iscrizioni ha evidenziato `INVALID_EVENT_PROJECTION` anche su un evento senza iscrizioni. La causa era `Utilities.ungzip()` applicato a un blob senza tipo MIME. Il decoder ora crea il blob con `application/gzip`; una prova in Apps Script ha confermato la decompressione di una proiezione sintetica vuota. Il deployment esistente è stato aggiornato alla versione **3**, conservando URL e impostazioni di accesso. È stato aggiunto un test di regressione locale.

Il collaudo completo di produzione è terminato il 21 settembre 2026 su due eventi esclusivamente fittizi. Sono stati verificati percorso gratuito, incasso e rimborso simulati, proiezione e ricostruzione, sincronizzazione Sheet → MySQL, retry e consegna email in modalità Prova. Le notifiche finali risultano `SENT`; gli eventi di collaudo e i relativi dati sono stati eliminati, i fogli Google spostati nel cestino e la modalità email ripristinata a **Operativo**. Non sono necessari un ulteriore caricamento del plugin o un nuovo deployment Apps Script.
