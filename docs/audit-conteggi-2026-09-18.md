# Audit conteggi e sincronizzazione — 18 settembre 2026

**Aggiornamento del 19 settembre:** le correzioni autorizzate sono implementate nel rilascio locale [3.26.131](rilascio-3.26.131.md). Le riproduzioni diagnostiche sono state convertite in regressioni del comportamento corretto. Il testo seguente conserva le evidenze dello stato precedente alle correzioni.

Verifica dei sorgenti locali 3.26.130 e dei test, con dati fittizi. Nessuna lettura o modifica di WordPress o Google in produzione. Questo documento integra, senza sostituire, `audit-pagamenti-2026-09-18.md`. I difetti sotto sono riprodotti, non corretti in questo intervento di analisi.

## Difetti riprodotti

### P1 — Il cambio alloggio può salvare una quota personale negativa

In `class-mi-management-service.php`, `accommodation_plan()` controlla il totale della prenotazione, ma non che ogni quota individuale resti non negativa dopo il cambio. Il controllo è invece presente nel cambio servizi e nella rettifica del dovuto.

Riproduzione: due persone con alloggio da 100 € ciascuna; sulla prima è già presente una rettifica di −80 €. Quote iniziali: 20 € e 100 €, totale 120 €. Cambiando l'alloggio della prima da 100 € a 30 €, il sistema accetta e salva una quota di −50 € per la prima e 100 € per la seconda, perché il totale della prenotazione resta positivo (50 €).

Il successivo calcolo restituisce `ready=false` e «La quota individuale non può essere negativa». I nuovi incassi tramite selezione persone vengono bloccati anche per il partecipante con quota valida. Il difetto richiede una rettifica precedente, quindi può sfuggire ai test ordinari sui cambi tariffari.

Correzione proposta: verificare tutte le quote personali proiettate prima di produrre l'anteprima e prima delle scritture nella transazione; rifiutare il cambio indicando la necessità di rivedere la rettifica.

### P2 — L'anteprima del cambio alloggio compensa debiti e crediti personali

`accommodation_plan()` calcola `due` e `refund` sottraendo il versato complessivo dal nuovo totale della prenotazione. La UI presenta questi valori come «Residuo totale» e «Da restituire», mentre il registro pagamenti mantiene le attribuzioni individuali.

Riproduzione: A e B devono 100 € ciascuno; A ha pagato 100 €, B nulla. L'alloggio di A passa da 100 € a 60 €. L'anteprima mostra residuo 60 € e rimborso 0 €. Le posizioni corrette restano B debitore di 100 € e A creditore di 40 €. Un incasso di 60 € per B non è accettato dal piano di pagamento individuale, che richiede la sua quota intera di 100 €.

Il problema riguarda l'anteprima: il cambio non crea automaticamente movimenti e non trasferisce realmente il denaro tra persone.

Correzione proposta: calcolare separatamente la somma dei residui e quella dei crediti personali dopo il cambio, coerentemente con `MI_Payment_People::summary()`. Gestire esplicitamente gli storici privi di attribuzione, senza presentarli come un calcolo individuale certo.

### P2 — Tre test PHP non sono più utilizzabili come prova del comportamento corrente

- `payment-ledger.php`: il database simulato non implementa le letture individuali richieste dal registro corrente; l'input di incasso non seleziona partecipanti. Termina tentando di trattare un `WP_Error` come array.
- `payment-ledger-innodb.php`: prepara soltanto prenotazioni e pagamenti e invia incassi senza `participant_ids`/rata. L'asserzione «Concorrenza non sicura» non dimostra un difetto di concorrenza: le richieste non rappresentano più incassi validi. La fixture può inoltre dipendere da tabelle lasciate da altri test.
- `operational-position.php`: mancano `get_post_meta()` e campi anagrafici nelle fixture del riepilogo; contiene anche un'aspettativa precedente sul trasferimento dei campi condivisi dopo annullamento.

Correzione proposta: aggiornare le fixture e ripristinare un test concorrente con richieste valide per la stessa quota personale. Non eliminare le verifiche di atomicità.

## Verifiche superate

Le suite Node di WordPress e Apps Script: **309 test superati, nessuno fallito o saltato**. Comprendono sia controlli strutturali sia esecuzione delle funzioni con servizi simulati; non sono 309 prove end-to-end.

Eseguite 14 suite PHP mirate: **11 superate, 3 fallite** come descritto sopra. Superate: `payment-people`, `percentage-deposit-unit`, `public-balance-model`, `workspace-balance`, `extra-services`, `admin-economics`, `payment-people-innodb`, `individual-management-innodb`, `economic-attribution-innodb`, `accommodation-change-innodb`, `public-balance`. Le suite InnoDB usano MariaDB locale su `127.0.0.1:33317`, database sacrificabile `mi_ledger_test`; quelle di gestione includono ulteriori test tramite require.

Riscontri principali:

- Rimborsi e storni sottraggono dal versato; viene rifiutato il rimborso oltre il versato della persona e quello ambiguo su più persone. Il rimborso che scopre una caparra riapre la posizione con una scadenza futura.
- Caparra percentuale ricalcolata in centesimi; caparra fissa conservata entro la quota. Saldo successivo ammesso dopo copertura della caparra delle persone selezionate. Crediti individuali separati dai debiti nel registro pagamenti.
- Prenotazioni con più persone: attribuzioni isolate, selezioni duplicate o estranee rifiutate, retry senza duplicazioni. Cambio alloggio tra prenotazioni atomico; errore sulla seconda prenotazione annulla l'intera operazione.
- Cambio servizi: quote, caparre, ripartizioni e rettifiche salvate; conflitti di versione, permessi, retry e rollback coperti.
- Replica MySQL → Google: test su movimenti distinti di pari importo, oltre 100 movimenti, replay, revisioni obsolete, replica parziale e retry, partecipanti annullati, conservazione del versato in presenza di credito. Il mittente evita di confermare una revisione cambiata durante la lettura.
- Sheet → MySQL: test locali su conflitti, retry, aggiornamenti in blocco e scambio di camere piene. Le modifiche economiche restano di competenza del portale.

Non sono stati verificati la versione effettivamente installata, i trigger Google attivi, la coda reale, le autorizzazioni o la coincidenza dei dati reali tra database e fogli. La replica asincrona può essere in ritardo anche quando i calcoli locali sono corretti.

## Riproduzione dei due bug economici

`wordpress-plugin/tests/audit-accommodation-edge-cases.php` prepara casi fittizi attraverso la fixture locale esistente, verifica il comportamento difettoso corrente e stampa `AUDIT_NETTING` e `AUDIT_NEGATIVE_QUOTE`. È una riproduzione diagnostica: il suo esito positivo conferma i bug, non la correttezza del sistema. Dopo una correzione le asserzioni vanno convertite in regressioni del comportamento desiderato.

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./.tmp/php-runtime/php.exe -d extension_dir=.tmp/php-runtime/ext -d extension=mbstring -d extension=mysqli wordpress-plugin/tests/audit-accommodation-edge-cases.php"
```

Richiede il solo database locale di test già avviato. La fixture ricrea tabelle in `mi_ledger_test`; non va adattata a un database operativo.

## Valutazione dell'audit esterno sulla sincronizzazione

Verifica aggiuntiva sui sorgenti correnti e riproduzione locale con `node workspace-apps-script/tests/audit-sync-edge-cases.mjs`. Lo script diagnostico conferma i comportamenti descritti sotto; non misura tempi reali Google. Rieseguiti anche i 21 test esistenti di sincronizzazione manuale, consegna email e integrazione WebApp: tutti superati.

### P1 — Normalizzazione: blocco della proiezione confermato, impatto da delimitare

In `SincronizzazioneManuale.gs:85`, `allineaBaseConVista_()` richiede identità testuale tra valore nella vista in entrata e modifica nel foglio. Caso riprodotto: base `Anna`, cella modificata `Anna Maria `, valore in entrata `Anna Maria`. Dopo tre repliche la modifica resta pendente e la base resta `Anna`.

`AccessoGestione.gs:57` sospende la riscrittura dell'intera proiezione dell'evento finché esiste una modifica pendente. La normalizzazione esiste sia in PHP (`sanitize_text_field`/`sanitize_textarea_field`) sia in Apps Script (`normalizzaTesto_`, che comprime gli spazi e applica trim).

Non è un loop di esecuzione infinito, né una rottura definitiva della replica bidirezionale: MySQL può salvare e `aggiungiIscrizione_()` può confermare la replica centrale MySQL indipendentemente dalla proiezione. Rimane bloccato il foglio operativo dell'evento, inclusi i successivi aggiornamenti economici visibili. Correggere la cella affinché coincida esattamente con il valore normalizzato in entrata consente l'allineamento al successivo aggiornamento. Un nuovo invio della modifica non normalizzata può inoltre essere rifiutato da `save_sheet()` perché il valore MySQL corrente non coincide più né con `before` né con `after`.

Una correzione robusta deve riconoscere il valore effettivamente accettato dal server e preservare eventuali modifiche successive dell'operatore. Un semplice trim universale nel confronto non copre tutte le normalizzazioni né tutti i tipi di campo.

### Prestazioni — Scritture per riga confermate; timeout non dimostrato

Le eliminazioni singole sono presenti in `registraIscrizioneCentrale_()` (richiamata da `aggiungiIscrizione_()`) e `sincronizzaCamereMysql_()`. Il caso locale con 60 camere produce 60 `deleteRow()` anche quando arriva nuovamente la stessa revisione camere. Il costo delle camere riguarda tutto l'evento e non è limitato ai 20 partecipanti della prenotazione.

È però errata la spiegazione di un lock che scade dopo 30 secondi: `waitLock(30000)` limita l'attesa per acquisirlo, non la durata della sezione protetta. Una sezione lenta può far scadere l'attesa di altre esecuzioni e aumentare il rischio di timeout complessivi, ma non è stato misurato che ciò avvenga con 20 partecipanti. Fonte: [Google Apps Script, classe Lock](https://developers.google.com/apps-script/reference/lock/lock).

Ottimizzazione proposta: eliminare intervalli contigui dal basso verso l'alto con `deleteRows(start,count)`. Non saltare ciecamente le revisioni uguali: il retry della stessa revisione è intenzionale e deve riparare scritture parziali, dato che il marcatore viene salvato prima delle righe.

### P2 — Stato coda email azzerato, ma riguarda la coda di test

Confermato che `WebApp.gs` riscrive `PREVIEW` anche per messaggi preesistenti. Riprodotto il passaggio `TEST_INVIATA` → `PREVIEW` dopo una replica. Una successiva esecuzione di `inviaCodaEmailDiTest()` può quindi reinviare quella prova al destinatario privato configurato.

La parte sull'occultamento degli invii operativi `ACCEPTED`/`SENT` non descrive il percorso corrente: `inviaEmailConfermaDaWordPress_()` conserva `SENDING` e `ACCEPTED` nel distinto foglio **Registro invii email**, indicizzato da `delivery_key`. La replica modifica **Coda email**, non quel registro. Non risultano pertanto provati né la perdita dello storico operativo né duplicazioni degli invii operativi per questa causa. Conservare lo stato preesistente è pertinente alla coda di test; un eventuale reinvio voluto dovrebbe essere esplicito.

### Valutazione delle conclusioni economiche dell'audit esterno

- Rimborsi e caparra: conclusioni sostanzialmente coerenti con le prove già svolte, ma `Payments.gs` non è il registro autorevole corrente. L'azione WebApp `REGISTRA_PAGAMENTO_PORTALE` risponde `USE_MYSQL_PAYMENT_LEDGER`; le garanzie operative vanno cercate nel PHP e nei relativi test.
- Stati caparra: `statoPagamentoPartecipante_()` distingue saldo, caparra coperta e versamento parziale quando i dati individuali replicati sono presenti. Non certifica che la replica sia aggiornata.
- Prenotazioni multiple: limite di 20, mapping e idempotenza sono coperti, ma la presenza di un hash non dimostra da sola atomicità o correttezza economica di ogni flusso.
- Cambio alloggio/servizi: transazioni e controllo versione non annullano i due bug economici riprodotti sopra. Il controllo `selected.available<1 && participant.room!==room` citato appartiene a `GestionePortale.gs`, mentre l'azione remota `AGGIORNA_GESTIONE_PORTALE` è ritirata (`USE_MYSQL_MANAGEMENT`). Non costituisce una verifica del percorso di modifica MySQL corrente.

Priorità risultante: quota personale negativa e proiezione bloccata dalla normalizzazione; poi anteprima debiti/crediti, stato della coda di test e ripristino dei test obsoleti. Le ottimizzazioni delle scritture richiedono anche misure su Google e prove di recupero da errore parziale. Nessuna correzione ai sorgenti applicata in questa valutazione.
