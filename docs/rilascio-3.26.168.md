# 3.26.168 — accenti oro e Crea evento

Il token oro della specifica v2 era definito ma non applicato agli indicatori della navigazione. Ora sottolinea l'intestazione, la voce attiva e il comando Menu mobile. Blu e avorio rimangono i colori principali. Il collegamento «+ Crea evento» conserva il più testuale e non genera più la seconda icona CSS.

Le modifiche sono limitate a `@media screen` e allo scope riservato. Nessuna modifica a logica, permessi, dati o destinazioni. Asset minificati e manifest rigenerati.

Verifiche: test dedicato `tools/test-portal-gold-browser.cjs` a 320, 390, 768, 1024 e 1440 px; controllo visivo degli screenshot desktop e mobile in `.tmp/portal-gold`; test di isolamento `tools/test-portal-scope-browser.cjs` su sei larghezze e controlli degli asset. Queste verifiche usano pagine sintetiche e non attestano il contenuto attualmente installato sul sito.

Pacchetti locale e pubblico generati con `tools/package-3.26.168.ps1`. Nessuna installazione sul sito o modifica Google Apps Script.
