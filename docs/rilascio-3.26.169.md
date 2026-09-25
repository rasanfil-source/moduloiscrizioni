# 3.26.169 — Iscrizioni su mobile

- Aggiornamento e Apri affiancati; aggiornamento usa l'icona esistente e conserva l'etichetta accessibile.
- Stampa ed Esporta Excel hanno larghezza naturale e altezza minima 44 px, affiancati anche a 320 px. Il testo rimane visibile; possono andare a capo se il testo ingrandito richiede più spazio.
- Rimosso il soffietto del riepilogo: tutte le metriche rimangono visibili. Persone iscritte e Dati mancanti sono le prime due tesserine affiancate su mobile, seguite dalle metriche condizionali senza modificarne i calcoli.

Controlli browser aggiornati in `test-portal-work-layout-browser.cjs` e `test-management-full-event-browser.cjs`: geometria e visibilità mobile, dati mancanti, aggiornamento, selezione colonne e contenuti di stampa/Excel. Fixture sintetiche, senza scritture su servizi esterni. Asset minificati rigenerati e pacchetti prodotti tramite `tools/package-3.26.169.ps1`.

Nessuna installazione sul sito o modifica Google Apps Script.
