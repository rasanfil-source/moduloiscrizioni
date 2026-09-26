# Audit del sistema - 26 settembre 2026

Nota successiva: i quattro difetti descritti sotto sono stati corretti nei sorgenti locali. Esiti, regressioni e valutazione degli audit esterni sono documentati in `correzioni-audit-2026-09-26.md`. Il testo seguente conserva le evidenze della fase diagnostica precedente alle correzioni.

Analisi dei sorgenti locali della versione WordPress 3.26.195 e del progetto Apps Script. Quattro difetti riprodotti con dati sintetici, eseguendo i servizi reali con database e trasporto email simulati. Nessuna modifica ai sorgenti applicativi e nessuna operazione su sito, email o fogli di produzione. Le modifiche locali preesistenti sono state conservate.

## 1. P1 - Il cron puo' far scadere una prenotazione appena prorogata

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php:813`, `:1063`; `wordpress-plugin/modulo-iscrizioni/includes/class-mi-payment-ledger.php:165`.

`expire_due_registrations()` seleziona gli ID scaduti prima di acquisire il lock evento e la riga della prenotazione. `transition_registration_status()` rilegge i pagamenti, ma non verifica nuovamente `expires_at` prima di applicare `EXPIRED`.

Scenario: il cron seleziona una prenotazione scaduta di due persone da 100 EUR; prima che acquisisca il lock, la segreteria registra i 100 EUR della prima persona. Il salvataggio mantiene `PENDING_PAYMENT` per la seconda persona e assegna una nuova scadenza con `reopened_payment_deadline()`. Il cron trova ancora un debito e fa scadere comunque la prenotazione, liberando i posti nonostante la proroga.

Riproduzione: scadenza gia' passata alla selezione, scadenza tra 48 ore alla lettura sotto lock, pagamento individuale presente; stato finale `EXPIRED`.

Intervento: ricontrollare stato, scadenza corrente e rilascio dei posti sotto lock; saltare le righe che non soddisfano piu' i criteri del cron. Conservare il controllo sui pagamenti.

## 2. P1 - La modifica della caparra puo' bloccare gli incassi dalla lista d'attesa

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php:1150`, `:1161`; `wordpress-plugin/modulo-iscrizioni/includes/class-mi-payment-people.php:130`.

L'accettazione dell'offerta usa la configurazione attualmente pubblicata dell'evento per calcolare `initial_due_cents`, mentre le quote individuali continuano a essere calcolate dall'istantanea originale della prenotazione. L'aggiornamento non riallinea tale istantanea o le caparre individuali.

Scenario riprodotto: iscrizione in attesa con caparra fissa originaria di 20 EUR, successiva pubblicazione di una caparra di 30 EUR, poi accettazione del posto. L'accettazione restituisce `ACCEPTED`, ma la prenotazione richiede 30 EUR e la quota individuale resta 20 EUR. La pianificazione del versamento viene rifiutata con "La caparra complessiva non coincide con le quote individuali. Verifica la prenotazione." Anche il versamento completo passa dal medesimo controllo `ready`.

Intervento: usare un'unica configurazione economica coerente per l'accettazione e le quote individuali. Se devono valere le nuove condizioni, migrare esplicitamente e atomicamente i dati economici interessati.

## 3. P2 - Un reinvio con dati corretti puo' confermare i dati precedenti

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php:300`; `wordpress-plugin/modulo-iscrizioni/assets/public.js:49`, `:811`, `:818`.

Il browser mantiene la stessa chiave per tutta la vita del modulo. Se il server ha salvato l'iscrizione ma la risposta si perde, l'utente puo' correggere i campi e riprovare. Il server riconosce la chiave, restituisce successo con `replayed=true` e non confronta i dati ricevuti con quelli gia' salvati. La conferma nel browser usa nome ed email del modulo corrente, quindi puo' mostrare come confermati dati che il server ha ignorato.

Riproduzione: prima iscrizione con `test@example.invalid` e nome `Persona`; reinvio con la stessa chiave, `corrected@example.invalid` e nome `Corretto`. Risposta positiva di replay; database ancora con email e nome originari.

Intervento: associare alla chiave un'impronta del contenuto normalizzato e rifiutare un riuso con contenuto differente. Nel browser conservare i dati del tentativo incerto e rendere visibile il conflitto. Generare subito una nuova chiave dopo un timeout rischierebbe invece di duplicare l'iscrizione.

## 4. P2 - Email bloccate definitivamente dopo l'interruzione del quinto tentativo

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-spedizione-email.php:402`, `:407`, `:418`, `:387`.

Il dispatcher incrementa `attempts` prima della chiamata remota. Se l'esecuzione si interrompe durante il quinto tentativo, il recupero delle righe vecchie converte `SENDING` in `PENDING` lasciando `attempts=5`. Le selezioni e la pianificazione successiva considerano soltanto `attempts < 5`. Il comando di riaccodamento interviene soltanto su `FAILED` e `TEST_FAILED`, quindi quella riga non e' recuperabile tramite il percorso previsto.

Riproduzione in entrambe le modalita': dopo l'interruzione e due esecuzioni di recupero, stato `PENDING` oppure `TEST_PENDING`, cinque tentativi, nessuna nuova chiamata al trasporto e nessun retry pianificato.

Intervento: portare le righe esaurite in uno stato esplicito recuperabile e mantenere la medesima chiave di consegna. Distinguere gli invii di esito incerto per preservare la protezione Google contro i duplicati.

## Verifiche

- `tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe`: superato; 360 test Node, 7 test sugli asset, controllo aggiornamento asset, sanitizzazione, lint e suite PHP elencate dal verificatore.
- `.tmp/audit-2026-09-26-registration.php`: riprodotti i problemi 1, 2 e 3. Riusa le dichiarazioni della fixture esistente; esegue i metodi reali del servizio iscrizioni e delle quote individuali.
- `.tmp/audit-2026-09-26-outbox.php`: riprodotto il problema 4 in `OPERATIVO` e `PROVA`, con dispatcher reale e invio simulato.
- Il successo degli script diagnostici significa che il difetto e' stato riprodotto, non che il comportamento sia corretto.

Comandi delle riproduzioni, dalla radice del progetto:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe -d extension_dir=.tmp/php-runtime/ext -d extension=mbstring .tmp/audit-2026-09-26-registration.php"
pwsh.exe -NoLogo -NoProfile -Command "& .tmp/php-runtime/php.exe .tmp/audit-2026-09-26-outbox.php"
```

Log dei controlli Node/asset: `.tmp/audit-2026-09-26-verify.log`. Gli esiti PHP sono stati restituiti anche dall'esecuzione del verificatore.

## Limiti

Non eseguiti collaudi sul sito installato, nel browser operativo o sui servizi Google reali. Il database locale InnoDB sulla porta 33317 non era in ascolto; le suite InnoDB non sono state eseguite. La concorrenza e le interruzioni sono state simulate in punti deterministici. I risultati dimostrano il comportamento dei sorgenti nelle condizioni descritte, non l'avvenuto verificarsi dei problemi in produzione. Non e' una certificazione di assenza di altri bug.
