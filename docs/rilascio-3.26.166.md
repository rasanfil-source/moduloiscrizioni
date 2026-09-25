# Rilascio 3.26.166 — report pagamenti nel gestionale

Il collegamento Report pagamenti apre una pagina della Segreteria eventi con lo stile del gestionale, anziché il pannello amministrativo WordPress. Anche il collegamento al report dalla gestione iscrizioni usa questa destinazione e conserva l'evento selezionato.

Il lettore dei dati è condiviso con il report amministrativo: stesse query, eventi accessibili, permessi di consultazione, filtri per evento/fonte/movimento/intervallo, cinque totali, nove colonne, paginazione da 100 movimenti ed esportazione CSV. I filtri sono riuniti in un unico modulo. Il download usa l'endpoint WordPress esistente con nonce, senza aprire il pannello. Su mobile la tabella scorre nel proprio contenitore. Il report amministrativo resta disponibile.

In Comunicazioni è rimosso soltanto l'avviso «Modalità operativa». Gli avvisi di test e di preparazione senza invio rimangono; anteprima, destinatari e conferma dell'invio non cambiano. La selezione delle voci nei Rapporti partecipanti non è modificata.

## Verifica

- Test PHP dedicato: identità delle query tra report amministrativo e portale, colonne, escape, totali con segno, filtri CSV e nonce, paginazione, permessi e ambito eventi, avvisi delle comunicazioni.
- Test browser con rendering PHP effettivo e dati sintetici: route del portale, filtri combinati, paginazione, link CSV e assenza di overflow della pagina a 1280, 390 e 320 px. Screenshot in `.tmp/payment-report`.
- Verifica generale tramite `tools/verify.ps1`, inclusa la conferma delle comunicazioni e la coerenza degli asset compilati.

Pacchetti in `dist`, con hash SHA-256. Il pacchetto locale conserva la configurazione privata esistente; quello pubblico la esclude. Il nuovo template del report è incluso e verificato in entrambi. Nessuna installazione sul sito, pubblicazione o modifica Google Apps Script.
