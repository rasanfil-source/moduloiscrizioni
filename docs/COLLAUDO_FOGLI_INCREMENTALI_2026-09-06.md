# Collaudo isolato Google del 6 settembre 2026

Esito: SUPERATO, 15 verifiche OK. Eseguita soltanto `collaudaFogliIncrementaliIsolati` nel progetto MODULI, dalle 08:07:50 alle 08:09:05 (Europe/Berlin), su un nuovo file con dati fittizi.

Il primo tentativo aveva fallito prima della prima verifica: Google rifiutava i developer metadata su un intervallo delimitato numericamente. Il sorgente ora usa riferimenti a colonne intere (`A:A`, ecc.) sia per `MI_CAMPO` sia per `MI_BASE_VUOTA`. Aggiornato e salvato Codice.gs dal bundle rigenerato; CollaudoIsolato.gs non ha richiesto modifiche.

Verifiche superate:

- Refresh ripetuto senza duplicati e conservazione degli zeri iniziali.
- Identità della colonna dopo spostamento e rinomina.
- Conservazione delle modifiche manuali e riconoscimento dei conflitti.
- Nuove colonne adiacenti e gruppo comprimibile.
- Conservazione di colonne storiche e formule manuali.
- Righe incomplete non acquisite e ripristino della riga fittizia eliminata.
- Migrazione con conservazione delle divergenze.
- Struttura Pagamenti, convalida esplicita e modalità con elenco.

Anche i 50 test locali Apps Script passano. Il doppio di test rifiuta ora i metadati su intervalli delimitati, per intercettare la regressione.

Nessun deployment, timer, invio email o accesso a DB_MODULI. I flussi centrali, l'acquisizione effettiva dei pagamenti e l'integrazione WordPress non sono coperti da questo collaudo e restano da verificare. Il superamento non autorizza la messa in produzione.
