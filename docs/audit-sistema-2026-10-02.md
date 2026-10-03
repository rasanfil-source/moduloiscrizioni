# Audit del sistema — 2 ottobre 2026

Analisi dei sorgenti locali WordPress **3.26.215** e delle verifiche del progetto Google Apps Script. Base Git `1676895`, con le modifiche locali preesistenti incluse nell'analisi. **Sei difetti riprodotti con dati sintetici: cinque P2 e uno P3.**

I sorgenti applicativi non sono stati modificati. Gli artefatti aggiunti sono questo rapporto e gli script diagnostici in `.tmp`. Nessuna operazione sul sito, sui dati reali, sui deployment o sulle email reali.

## 1. P2 — Una caparra incoerente nasconde un saldo individuale ancora dovuto

Riferimenti:

- `wordpress-plugin/modulo-iscrizioni/includes/class-mi-registration-service.php:875–879`.
- `wordpress-plugin/modulo-iscrizioni/includes/class-mi-payment-ledger.php:47–51`; consumo del saldo in `includes/class-mi-admin.php:459`.
- `wordpress-plugin/modulo-iscrizioni/includes/class-mi-event-projection.php:92–95` e `:110–112`.

Questi lettori usano `summary['known']`, che richiede anche caparre coerenti, per decidere se conservare gli importi individuali. Quando quote e attribuzioni dei pagamenti sono note ma la caparra è incoerente, ritornano alla sottrazione sul totale della prenotazione. Questa può compensare il credito di una persona con il debito di un'altra, contro la logica individuale del registro.

Prova: due persone devono 100 € ciascuna; un versamento di 200 € è attribuito interamente alla prima. La seconda deve ancora 100 €. Lasciando tutto invariato e rendendo la somma delle caparre individuali diversa dalla caparra dell'ordine, si ottiene:

| Lettura | Caparre coerenti | Caparre incoerenti |
| --- | ---: | ---: |
| Residuo individuale calcolato | 100 € | 100 € |
| Stato pubblico della prenotazione | 100 € | **0 €, «Saldo completato»** |
| Saldo nel dettaglio amministrativo WordPress | 100 € | **0 €** |
| Saldo ordine nella proiezione Google | 100 € | **0 €** |
| Dettaglio del registro pagamenti | 100 € | 100 € |

La proiezione perde anche gli importi individuali, sostituendoli con stringhe vuote. Il dettaglio del registro pagamenti usa già `totals_known` e conserva il residuo corretto.

Il difetto richiede un'incoerenza preesistente della caparra; la prova non dimostra che questo percorso la generi o che esista in produzione. Riguarda lo stato pubblico della prenotazione: il distinto servizio `MI_Public_Balance` contiene già il blocco introdotto nella 3.26.213.

**Correzione suggerita:** usare `totals_known` per totale, versato e residuo; trattare separatamente la disponibilità degli importi di caparra. Aggiungere una regressione che confronti tutte le letture della stessa posizione.

## 2. P2 — Annullare una prenotazione dal portale non fa scorrere la lista d'attesa

Riferimento: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-management.php:85`. Firma e utilizzo del parametro in `includes/class-mi-registration-service.php:928–929` e `:1106`.

Il gestore AJAX passa `false` come terzo argomento di `cancel_registration()`. Questo argomento è `promote_waitlist`: la prenotazione viene annullata e libera i posti, ma i candidati in attesa non vengono esaminati.

Prova con capienza uno, una prenotazione confermata e una compatibile in attesa:

- Attraverso l'AJAX del portale: prenotazione annullata, posto libero, candidato ancora `WAITLISTED`, nessuna proposta accodata.
- Attraverso lo stesso servizio con i parametri predefiniti: candidato `WAITLIST_OFFERED`, posto riservato alla proposta, una notifica nella coda sintetica.

Non è emerso un processo periodico che recuperi automaticamente questa omissione; gli altri percorsi di scorrimento dipendono da ulteriori transizioni. Il posto può quindi restare libero mentre esistono persone in attesa.

**Correzione suggerita:** abilitare lo scorrimento per l'annullamento della singola prenotazione dal portale. Conservare invece la disabilitazione nel distinto annullamento dell'intero evento. Coprire con un test il gestore AJAX, non soltanto il servizio sottostante.

## 3. P2 — La scadenza della cache produce un falso avviso di dati modificati

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php:164–168`, `:242–245` e `:278–280`; `includes/class-mi-management-list.php:72–76`; `assets/portal-management.js:558` e `:586`.

Il percorso con cache e quello con lettura dal database calcolano `fingerprint` con strutture diverse. La cache del riepilogo scade dopo 300 secondi (`includes/class-mi-event-read-cache.php:38`). Se una pagina viene letta prima della scadenza e la successiva dopo, il client interpreta il cambio di percorso come un cambiamento dei dati.

Prova con 31 persone, stesso modello, stessa versione canonica e ordinamento identico: le righe restituite prima e dopo la rimozione della sola cache sono uguali, ma le impronte differiscono. Il controllo di «Carica altri» rifiuta la seconda pagina con «I dati sono cambiati. Aggiorna il riepilogo per continuare». Lo stesso confronto è usato dalle esportazioni, se il cambio avviene fra due blocchi.

**Correzione suggerita:** definire un'unica impronta della selezione, indipendente dalla disponibilità della cache, conservando la rilevazione dei cambiamenti reali e dei filtri temporali. Verificare entrambi i passaggi fra lettura con cache e senza cache.

## 4. P2 — Le date personalizzate dei vecchi moduli restano limitate al passato in gestione

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php:67` e `:1026–1030`; `includes/class-mi-field-schema.php:271–274`.

La 3.26.213 ammette date generiche per i campi `custom_*`, anche quando le vecchie definizioni non hanno `date_rule`. L'adattatore della gestione, però, aggiunge `date_rule = ''`. Il validatore usa `??` per applicare il valore predefinito: una stringa vuota non attiva il valore `any` e ricade nella validazione delle date passate.

Prova su una domanda precedente «Data di arrivo»: una data dieci giorni nel futuro è accettata dal validatore pubblico, ma la modifica dello stesso campo attraverso il metodo di salvataggio della gestione fallisce con «Controlla le date dei partecipanti». La stessa definizione con `date_rule = any` viene salvata.

Il metodo è condiviso da modifica partecipante e applicazione delle modifiche del foglio. Il difetto riguarda l'inserimento o la variazione del valore; il codice salta la validazione se il valore resta invariato.

**Correzione suggerita:** preservare l'assenza della regola oppure applicare lo stesso valore predefinito per i campi personalizzati. Aggiungere una regressione che attraversi adattatore e salvataggio, oltre al solo validatore.

## 5. P2 — L'ordinamento per stanza cambia ai confini delle pagine

Riferimenti: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-management-service.php:205`, `:213` e `:237`; `includes/class-mi-management-list.php:92–93`.

Senza cache, il database seleziona la pagina ordinando il codice stanza come testo e applica `LIMIT/OFFSET`. Il risultato viene poi riordinato con `strnatcasecmp`, che considera la parte numerica. Il riordino della sola pagina non può recuperare gli elementi già esclusi dal limite SQL.

Prova con stanze da `S1` a `S31` e pagine da 30: la prima pagina contiene `S1…S8, S10…S31`; la seconda contiene **S9**. L'elenco completo o con cache restituisce invece `S1…S31`. Le due pagine senza cache hanno la stessa impronta, quindi il client le concatena senza rilevare il problema.

La prova esegue il servizio reale con un adattatore che simula l'ordinamento testuale SQL. Non è una verifica della collation di un database di produzione.

**Correzione suggerita:** rendere identica la selezione ordinata prima della paginazione nei due percorsi, compresi i criteri di spareggio. Verificare il risultato concatenato di più pagine con codici alfanumerici.

## 6. P3 — Dopo un annullamento, il saldo pubblico trasferisce gli obblighi ONE

Riferimento: `wordpress-plugin/modulo-iscrizioni/includes/class-mi-public-balance.php:242–244`. Regola attesa: `UX-CONTRACT.md:70`.

Per determinare chi deve compilare i campi obbligatori con ambito `ONE`, il saldo pubblico seleziona il primo partecipante ancora attivo. Il contratto funzionale mantiene invece questi obblighi sul primo partecipante originario, anche se annullato.

Prova: il primo partecipante ha compilato «Data di arrivo», obbligatoria solo per lui; il secondo l'ha correttamente lasciata vuota. Prima dell'annullamento il riepilogo del secondo non segnala dati mancanti. Dopo l'annullamento del primo, lo stesso riepilogo segnala **«Data di arrivo»** come mancante. L'elenco alimenta anche le istruzioni dell'email (`includes/class-mi-public-balance.php:153`).

È una richiesta impropria di informazioni; nella prova non blocca il salvataggio.

**Correzione suggerita:** individuare il titolare originario degli obblighi sulla lista completa dei partecipanti, coerentemente con il riepilogo gestionale. Verificare anteprima e contenuto dell'email dopo un annullamento individuale.

## Verifiche e riproduzioni

`tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe` completato con codice 0:

| Controllo | Esito |
| --- | --- |
| Test Node del progetto | 393 superati |
| Test degli asset generati | 7 superati |
| Suite PHP elencate nel verificatore | 53 superate |
| Lint dei file PHP del plugin | 43 file senza errori |
| Coerenza asset generati e sanitizzazione | Superate |

Log: `.tmp/audit-2026-10-02-verify.log`. Le stampe PHP indirizzate a `Out-Host` sono nell'output della sessione e non tutte nel file. Il superamento della suite ordinaria non esclude i sei casi aggiuntivi.

Le riproduzioni eseguono i metodi applicativi reali, con servizi WordPress e accesso al database simulati. Sono state rieseguite tutte con codice 0: **le loro asserzioni confermano la presenza dei difetti**, non il comportamento corretto dopo una futura correzione.

| Difetto | Script in `.tmp` |
| --- | --- |
| 1 — Saldi | `audit-2026-10-02-economics.php` |
| 2 — Lista d'attesa | `audit-2026-10-02-waitlist.php` |
| 3 — Cache | `audit-2026-10-02-page-cache.php` |
| 4 — Date | `audit-2026-10-02-dates.php` |
| 5 — Paginazione | `audit-2026-10-02-pagination.php` |
| 6 — Ambito ONE | `audit-2026-10-02-one-scope.php` |

Ogni script ha un file `.log` omonimo. Per rieseguire tutte le riproduzioni dalla directory del progetto e aggiornare i log:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "node .tmp/audit-2026-10-02-summary.mjs"
```

Gli script `.tmp` sono artefatti diagnostici locali, non test permanenti della suite. Per correggere i difetti occorreranno regressioni che verifichino il risultato atteso.

Sono stati esaminati percorsi di iscrizione, pagamenti, annullamento, lista d'attesa, salvataggio partecipanti e servizi, elenchi, cache e proiezione Google. Non sono state eseguite prove aggiuntive su MariaDB/InnoDB, interazioni browser o verifiche dei deployment WordPress e Google. L'analisi non certifica ogni ramo del sistema né la presenza dei casi descritti nei dati reali.
