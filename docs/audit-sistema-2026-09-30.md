# Audit del sistema — 30 settembre 2026

**Aggiornamento:** i tre difetti descritti sotto sono stati corretti nei sorgenti della [versione 3.26.213](rilascio-3.26.213.md). Aggiunti test permanenti PHP e JavaScript; verifica locale completa superata. Il testo seguente conserva le condizioni e i risultati dell'audit sulla 3.26.212. Le vecchie riproduzioni `.tmp` descrivono il comportamento difettoso e non sono i test di regressione da usare dopo la correzione.

Analisi dei sorgenti locali del plugin WordPress **3.26.212** e di Google Apps Script. Tre difetti riprodotti con dati sintetici. Nessuna modifica ai sorgenti applicativi; conservate le modifiche locali preesistenti. Nessuna operazione sul sito, distribuzione o invio email reale.

## 1. P2 — Il saldo pubblico modifica una caparra segnalata come incoerente

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-public-balance.php:117` e `:252–260`.

Il controllo di accesso al saldo verifica `quotes_known` e `payments_known`, ma non `deposits_known`. Una posizione che il calcolo economico segnala esplicitamente come incoerente può quindi essere confermata dal pubblico. Il salvataggio somma le caparre individuali e sovrascrive la caparra complessiva, anche senza modificare i servizi.

Riproduzione con una prenotazione sintetica:

- Totale di 500 €, caparra complessiva salvata di 200 €, caparra individuale di 150 €, versamento di 150 €.
- Il calcolo reale restituisce `deposits_known = false`; la prenotazione è `PENDING_PAYMENT`.
- Ricerca nel saldo pubblico, anteprima e conferma senza modifiche ai servizi.
- La caparra complessiva diventa **150 €** e lo stato diventa **CONFIRMED**, senza un nuovo versamento né una rettifica dell'operatore. Il totale rimane 500 €.

Il difetto richiede una **incoerenza preesistente**: la prova non dimostra che questo percorso la generi inizialmente né che esistano prenotazioni simili in produzione. Dimostra che il saldo pubblico ignora la segnalazione e sceglie autonomamente quale importo conservare. La verifica della caparra deve precedere il ricalcolo e il salvataggio; la correzione dei dati incoerenti va demandata a un'operazione esplicita della segreteria.

Riproduzione: `.tmp/audit-2026-09-30-public-deposits.php`; output omonimo `.log`. Utilizza il servizio saldo e il calcolo delle quote reali, con l'adattatore database e i servizi WordPress simulati della fixture esistente.

## 2. P2 — La copia di un evento annullato eredita il blocco delle iscrizioni

Riferimento: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-event-duplicator.php:27–31`.

La duplicazione esclude `_mi_event_cancelled_at` e `_mi_event_cancellation_reason`, ma copia `_mi_event_cancellation_job`. Quest'ultimo resta presente anche dopo un annullamento completato (`class-mi-portal.php:745–770`). Il comando «Duplica evento» è disponibile anche per questi eventi.

Riproduzione:

1. Partire da un evento annullato con il suo job di annullamento persistente.
2. Eseguire il metodo reale `MI_Event_Duplicator::duplicate()`.
3. Rendere pubblica la copia nell'adattatore sintetico e tentare una nuova iscrizione attraverso il servizio reale.

La copia non ha il marcatore di evento annullato, ma conserva il job e la lista delle vecchie prenotazioni. `MI_Registration_Service::create()` restituisce **`mi_event_cancelled`** prima ancora di validare la nuova iscrizione (`class-mi-registration-service.php:308`). Anche una configurazione nuova e completa incontra quindi lo stesso blocco.

Correzione suggerita: escludere `_mi_event_cancellation_job` dai metadati duplicati. Separare i dati di configurazione dagli stati operativi nella politica di copia, per evitare analoghe eredità accidentali.

Riproduzione: `.tmp/audit-2026-09-30-duplicate.php`; output omonimo `.log`. Usa duplicatore e servizio iscrizioni reali, con API WordPress e database simulati. Non esegue il wizard di pubblicazione completo.

## 3. P2 — Le domande personalizzate di tipo data non accettano il futuro

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-field-schema.php:209–219`, `:266–272`; `wordpress-plugin/modulo-iscrizioni/assets/public.js:521–524`.

L'editor consente domande personalizzate di tipo `date`, ma la definizione generata non distingue una data generica da una data di nascita. In assenza di `date_rule = future`, il validatore ammette soltanto l'intervallo da 120 anni fa a oggi. Il modulo pubblico applica lo stesso limite con l'attributo `max`.

Riproduzione: creare tramite il normalizzatore reale la domanda obbligatoria «Data di arrivo» di tipo data. Un valore passato viene accettato; **10 ottobre 2026**, dieci giorni dopo la data dell'audit, viene rifiutato con `mi_participant_date_invalid`. Il valore futuro è una risposta plausibile per la domanda configurata, ma non è inseribile neppure dal modulo normale.

Correzione suggerita: applicare i limiti anagrafici alla data di nascita e quelli di scadenza ai campi specifici. Per le domande personalizzate usare una data generica oppure rendere configurabile e persistente la regola temporale, allineando PHP e JavaScript.

Riproduzione: `.tmp/audit-2026-09-30-custom-date.php`; output omonimo `.log`. Esegue normalizzazione e validazione reali senza database.

## Verifiche e limiti

- `tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe`: completato con successo; **390 test Node e 7 test degli asset**, controllo degli asset generati, sanitizzazione, lint PHP e tutte le suite PHP elencate nel verificatore. Log: `.tmp/audit-2026-09-30-verify.log` (le stampe PHP inviate a `Out-Host` sono nell'output della sessione, non tutte nel file).
- Le tre riproduzioni aggiuntive terminano con codice 0 e `bug_reproduced: true`. In questi script il successo conferma il difetto, non il comportamento atteso.
- Sono stati esaminati percorsi di iscrizione, lista d'attesa, annullamento, pagamenti, modifica dei servizi, duplicazione, autorizzazioni e sincronizzazione. Non è un controllo esaustivo di ogni ramo del progetto.
- I test Apps Script usano simulazioni locali. In questo audit non sono state eseguite le suite aggiuntive su MariaDB/InnoDB, prove browser o verifiche sui deployment WordPress/Google reali.

Per ripetere una riproduzione da questa directory di progetto:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./.tmp/php-runtime/php.exe -n -d extension_dir=.tmp/php-runtime/ext -d extension=mbstring .tmp/audit-2026-09-30-duplicate.php"
```

Sostituire l'ultimo nome con quello delle altre due riproduzioni. Gli script in `.tmp` sono artefatti locali dell'audit e non test permanenti della suite.
