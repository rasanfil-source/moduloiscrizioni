# Analisi dei bug — 2 novembre 2026

**Aggiornamento:** i tre problemi riprodotti e il difetto della fixture sono corretti nel [rilascio 3.26.275](rilascio-3.26.275.md). Il testo seguente documenta la situazione iniziale 3.26.274; le verifiche del rilascio sono riportate nelle nuove note.

Analisi dei sorgenti locali del plugin **3.26.274** e dei flussi destinati a **MODULI AUTONOMO**. Sono stati esaminati iscrizioni, annullamenti, scadenze, registro economico, code email, notifiche push e integrazione Google. Le modifiche locali preesistenti sono state conservate. Nessun sorgente operativo o ZIP è stato modificato; nessuna operazione è stata eseguita sul sito o sui servizi Google.

Tre anomalie funzionali sono state riprodotte con le classi PHP effettive e adattatori locali. I servizi esterni sono simulati e i dati sono sintetici. Non sono stati riscontrati bug di priorità P1 nelle prove svolte; questo non dimostra l'assenza di altri difetti.

## 1. P2 — Il totale dell'email di aggiornamento include le persone annullate

**Riferimento:** `wordpress-plugin/modulo-iscrizioni/includes/class-mi-booking-update-email.php:41`.

**Caso:** prenotazione di due persone da 100 euro; una partecipazione viene annullata. Successivamente una modifica gestionale ammessa, per esempio l'assegnazione di una camera alla persona rimasta, genera un'email `BOOKING_UPDATED`.

**Causa:** `cancel_participant()` conserva il totale storico della prenotazione e cambia lo stato della persona. Il modello economico operativo esclude le persone inattive tramite `MI_Payment_People::summary()`. L'email, invece, esclude correttamente le persone annullate dal dettaglio ma stampa direttamente `r.total_cents` come quota complessiva della situazione attuale.

**Riproduzione:** il modello operativo restituisce **10.000 centesimi**. La stessa email mostra una sola persona, quota **100,00 EUR**, residuo **100,00 EUR**, e poi **«Quota complessiva della prenotazione: 200,00 EUR»**.

**Impatto:** comunicazione economica contraddittoria, con rischio di indurre il destinatario a interpretare come ancora dovuta la quota della persona annullata. La prova riguarda il testo dell'email; non dimostra una scrittura errata nel registro pagamenti.

**Correzione suggerita:** ricavare il totale corrente dallo stesso riepilogo individuale usato dal portale. Quando le attribuzioni non sono note, indicare che il totale deve essere verificato; un eventuale totale storico va etichettato esplicitamente.

## 2. P2 — La scansione delle proposte in lista d'attesa può saltare scadenze già superate

**Riferimento:** `wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php:1445`, metodo `expire_due_waitlist_offers()`.

**Caso:** sono presenti almeno 200 proposte con scadenza individuale futura. Una proposta successiva nell'ordinamento appartiene a un evento la cui scadenza comune è stata anticipata ed è già trascorsa.

**Causa:** la query legge soltanto le prime 200 righe, ordinate per `waitlist_offer_expires_at`, senza cursore o paginazione. La scadenza comune corrente dell'evento viene applicata soltanto dopo questa selezione. Una proposta può quindi essere già scaduta secondo la regola effettiva pur restando fuori dalle righe lette.

**Riproduzione:** 200 proposte con termine individuale fra un'ora, seguite da una proposta con termine individuale fra due ore e termine comune trascorso da un'ora. In **tre esecuzioni consecutive** il metodo non verifica mai l'evento della proposta 201. Togliendo dalla selezione le prime 200, lo stesso metodo tenta immediatamente di far scadere la proposta 201. Il tentativo è intercettato prima delle scritture.

**Impatto:** il collegamento pubblico può già risultare scaduto mentre il posto resta prenotato come `WAITLIST_OFFERED`; rilascio della disponibilità e successivo avanzamento della lista vengono ritardati. Il ritardo persiste finché le righe precedenti non escono dalla selezione.

**Correzione suggerita:** introdurre una scansione paginata con cursore persistente, oppure rendere selezionabile la scadenza effettiva nel database. Non basta ordinare per la sola scadenza individuale.

## 3. P3 — Il retry push reinvia anche ai dispositivi che hanno già ricevuto l'avviso

**Riferimenti:** `wordpress-plugin/modulo-iscrizioni/includes/class-mi-web-push.php:301`, `:310`, `:337`.

**Caso:** due sottoscrizioni autorizzate; il servizio push accetta la prima con HTTP 201 e risponde HTTP 503 alla seconda.

**Causa:** basta un errore ritentabile per pianificare nuovamente `send_registration(registration_id, attempt)`. Il nuovo tentativo rilegge tutte le sottoscrizioni e non conserva l'elenco di quelle già servite.

**Riproduzione:** usando crittografia e codice di invio effettivi, con trasporto HTTP simulato, due tentativi producono questa sequenza: **successo, errore, successo, errore**. Il dispositivo già servito riceve quindi due richieste per la stessa iscrizione.

**Impatto:** richieste duplicate e possibile ricomparsa di un avviso già chiuso dall'utente. Il worker usa lo stesso `tag` e `renotify: false`: se la notifica è ancora visibile può essere sostituita senza un nuovo avviso sonoro. Il difetto non implica che ogni retry produca due notifiche contemporaneamente.

**Correzione suggerita:** memorizzare l'esito per coppia iscrizione/sottoscrizione o passare al retry soltanto gli identificativi delle consegne fallite, ricontrollando comunque le autorizzazioni.

## Difetto distinto nella verifica automatica

`wordpress-plugin/tests/group-links.php` non simula `do_action()`, ora invocata dal resolver in `class-mi-group-links.php:141`. Il test termina con errore fatale e `tools/verify.ps1` interrompe la verifica al primo script PHP.

Questo è un difetto dell'ambiente di test: WordPress reale fornisce `do_action()`. Aggiungendo esclusivamente un adattatore temporaneo mediante `auto_prepend_file`, senza modificare plugin o test, lo stesso test passa. Occorre completare la fixture e, preferibilmente, verificare anche l'hook che impedisce la cache.

## Verifiche eseguite e limiti

- **409/409 test Node** superati, inclusi i test Apps Script.
- **7/7 controlli aggiuntivi** sugli asset generati superati; minificati allineati ai sorgenti con verifica senza ricostruzione.
- Sintassi di tutti i file PHP del plugin e controllo di sanitizzazione superati.
- Eseguiti separatamente tutti i **73 script PHP** elencati in `tools/verify.ps1`: **72 superati, 1 fallito**, come descritto sopra.
- Il test fallito passa con il solo adattatore temporaneo di `do_action()`.
- Tre riproduzioni mirate confermate; nessun invio reale di email, notifiche o richieste Google.
- Non eseguiti collaudi nel browser, suite con database InnoDB reale o verifiche delle distribuzioni WordPress/Google. Le prove della scansione riproducono ordinamento e limite SQL in un adattatore locale.

## Ripetizione delle prove

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./.tmp/audit-2026-11-02-repro.ps1"
pwsh.exe -NoLogo -NoProfile -Command "& ./.tmp/audit-2026-11-02-php.ps1"
```

Le riproduzioni sono in `.tmp/audit-2026-11-02-repro.php`; il launcher configura OpenSSL soltanto per il processo di prova. Gli esiti sono in `.tmp/audit-2026-11-02-repro.jsonl` e `.tmp/audit-2026-11-02-php.json`. Questi file temporanei non sono pacchetti di distribuzione.
