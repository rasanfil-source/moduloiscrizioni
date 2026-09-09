# Rilascio 3.25.1 — MySQL e gestione operativa

Plugin installato il 9 settembre 2026. Web App Google versione 64; il progetto corrente comprende anche l'attivatore `avviaCronWordPress`, eseguito separatamente ogni cinque minuti.

## Comportamento

- MySQL è autorevole per pagamenti, partecipanti, camere e assegnazioni.
- Il foglio evento è accessibile dal portale. Le celle azzurre si modificano nel foglio e si confermano tramite il confronto Sincronizza; i pagamenti si registrano nell'interfaccia web.
- Stati brevi italiani e codici per nuove prenotazioni basati sulle iniziali del titolo, con progressivo transazionale. I codici esistenti restano validi.
- Le voci economiche aggiuntive producono colonne dinamiche nel foglio. Il riepilogo pubblico privilegia nominativi, servizi e costi.
- Timeout lettura modifiche Google esteso a 45 secondi dopo un collaudo reale da 20 secondi.
- La coda limita il lavoro per processo PHP e conserva gli elementi da ritentare.

## Esecuzione periodica

Sul sito WP-Cron è disabilitato. L'attivatore Google chiama il `wp-cron.php` del sito configurato, senza mantenere lock Apps Script e senza seguire redirect. La proiezione dei fogli ha un attivatore distinto. Questo avvio dipende dall'account Google e dalle sue quote: verificare entrambi gli attivatori dopo cambi di proprietario o progetto.

## Verifiche

222 test Node superati dopo la correzione finale del recupero; test PHP dei codici e lint superati. Il collaudo browser sintetico verifica salvataggio, retry stabile, conferma e schermo stretto.

Prova reale: assegnazione pullman TEST-P1 → TEST-P2 dal foglio, confronto e salvataggio nel portale, rilettura in MySQL e replica centrale confermata. Pagamento di prova unico da 1 euro, residuo 1084 euro invariato. Coda completata: 38 sincronizzate, 0 pendenti.

Il collaudo operativo è completato. Esiti e dipendenze di esercizio sono in [Passaggio MySQL](PASSAGGIO_MYSQL.md).
