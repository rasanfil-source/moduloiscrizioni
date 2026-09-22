# Rilascio 3.26.151 — audit, prestazioni e sincronizzazione recuperabile

Il rilascio riunisce le correzioni locali dei due audit del 21 settembre 2026, per plugin WordPress e Web App autonoma Google Apps Script.

## Modifiche

- Apertura iscrizioni facoltativa coerente: vuota significa apertura immediata alla pubblicazione. Il portale verifica date reali e ordine temporale prima di pubblicare o aggiornare; apertura e chiusura uguali sono rifiutate.
- Permessi globali uniformati per lista e dettaglio evento; conversione UTC dei pagamenti indipendente dall’identificativo stabile.
- Cache leggera delle card pubblicate e del modello di gestione, riuso dei dati delle camere e fingerprint ridotto per il polling.
- Conteggi di saldo e caparra basati sulle singole persone.
- Controllo immediato degli errori SQL nella costruzione delle proiezioni; generazione monotona per impedire consegne obsolete.
- Proiezione Sheets incrementale con journal persistente per riprendere una scrittura interrotta; conservazione dell’ordine manuale quando la struttura lo permette.
- Lock WordPress per evento; conferma della lettura degli snapshot compressi con MIME gzip corretto.

I dettagli, i limiti e le proposte rinviate sono in [audit del sistema](audit-sistema-2026-09-21.md) e [valutazione dell’audit esterno](valutazione-audit-esterno-2026-09-21.md).

## Verifica e pacchetti

Verifica locale con PHP 8.3 e mbstring: 341 test Node, 7 test sugli asset generati, 20 suite PHP, lint e sanitizzazione. Gli archivi vengono confrontati file per file con i sorgenti e accompagnati da SHA-256.

- `modulo-iscrizioni-3.26.151.zip`: plugin installabile da WordPress.
- `Workspace-3.26.151.zip`: progetto autonomo con `Codice.gs` e manifest.
- `Codice-Workspace-Progetto-3.26.151.gs`: sorgente unico per il progetto autonomo; mantiene `MI_STANDALONE_MODE = true`.

## Ordine di aggiornamento e rollback

Aggiornare il deployment GAS esistente conservando URL, esecutore, accessi e proprietà private; poi installare lo ZIP WordPress. Il nuovo GAS accetta il vecchio protocollo senza generazione finché, per quell’evento, non riceve la prima proiezione versionata dal plugin aggiornato. Da quel momento rifiuta le consegne senza generazione per proteggere i dati da rollback involontari.

Conservare il precedente deployment Google e lo ZIP WordPress 3.26.150. Dopo il primo invio versionato, un rollback del solo plugin non è sufficiente: ripristinare anche il deployment Google precedente. Nessuna modifica dei trigger storici, delle proprietà private o del workbook centrale è prevista da questo rilascio.
