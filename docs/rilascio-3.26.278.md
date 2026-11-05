# Modulo Iscrizioni 3.26.278

Rilascio del 5 novembre 2026. Ottimizza il caricamento delle schermate del portale. Il progetto Google operativo resta **MODULI AUTONOMO**.

## Miglioramenti

- `portal-management.css` viene inviato solo alle viste Iscrizioni e Dettaglio; login, pagine pubbliche, pagamenti, configurazione gruppi e lista eventi non lo caricano. Anche `portal.js` viene omesso da login, risposte pubbliche e account senza accesso al portale.
- Gli script del QR amministrativo vengono richiesti quando una scheda evento con QR entra nella pagina, anche quando la scheda arriva tramite caricamento AJAX. In caso di errore di rete, il pulsante consente un nuovo tentativo. L'editor evento accoda gli script solo quando mostra il QR.
- La lista eventi e lo storico mostrano 20 schede per pagina. I contatori aggregati sono calcolati soltanto per le schede visibili; i collegamenti alle schede selezionate e alla cronologia mantengono la pagina corretta.
- Gli stili condizionali del portale autonomo sono dichiarati nell'intestazione HTML, così il browser li scopre prima di dipingere la schermata.
- Rigenerati gli asset minificati e il manifest.

La classificazione degli eventi continua a leggere l'insieme accessibile per rispettare le date e gli archivi; la paginazione riduce markup, immagini attivate, operazioni sulle card e query dei contatori, ma non introduce un cursore sul database. Nessun cambiamento al codice Google Apps Script di **MODULI AUTONOMO**.

## Distribuzione

- `dist/modulo-iscrizioni-3.26.278.zip`: pacchetto locale.
- `dist/modulo-iscrizioni-3.26.278-pubblico.zip`: pacchetto pubblico senza `public-balance-config.json`.
- I pacchetti precedenti restano invariati, incluso il pacchetto 3.26.226.

## Verifiche

- Rigenerazione degli asset minificati e del manifest.
- Coerenza del numero di versione tra intestazione plugin, `MI_VERSION`, note e nomi degli archivi.
- Verifica degli archivi e dei checksum; controllo che gli archivi precedenti siano rimasti invariati.
- Le suite automatiche PHP e Node non sono state eseguite.
