# Rilascio 3.26.131 — correzioni economiche e apertura verificata

Il pulsante del foglio nel portale si chiama **Apri**. L'aggiornamento è obbligatorio: una pagina di attesa sincronizza le prenotazioni pendenti, verifica le revisioni della replica Google e ricostruisce il foglio evento. Il collegamento Google viene aperto soltanto dopo il completamento. Errori, cambiamenti concorrenti e modifiche pendenti nelle celle lasciano visibile il motivo e consentono di riprovare o tornare alla gestione. Il collegamento copiabile dal portale passa attraverso lo stesso percorso e richiede l'accesso WordPress autorizzato.

DB_MODULI resta la replica centrale. Non è stato aggiunto un trigger all'apertura diretta di Google Sheets: i vecchi collegamenti Google e i file aperti direttamente da Drive non passano da questa verifica. L'aggiornamento periodico resta disponibile. La verifica fotografa le revisioni al completamento; non impedisce nuove modifiche successive all'apertura.

## Correzioni

- Il cambio alloggio rifiuta quote personali negative, comprese quelle causate da rettifiche precedenti.
- L'anteprima mantiene distinti debiti e crediti delle persone attive. Se lo storico non consente attribuzioni certe, mostra «Da verificare» anziché un rimborso o residuo individuale inventato.
- Le modifiche Sheet → MySQL restituiscono ricevute con i valori accettati dal server. La ricevuta normalizza soltanto la cella ancora identica alla richiesta; la base viene confermata soltanto quando torna la replica. Retry e valori modificati successivamente restano protetti. Un esito incerto conserva la richiesta per ripeterla.
- Una proiezione bloccata da modifiche pendenti non viene più marcata come invariata e saltata nei controlli successivi.
- Le righe contigue vengono eliminate a blocchi dal basso verso l'alto. I retry della stessa revisione continuano a riparare scritture parziali.
- La replica conserva lo stato della coda email di test; non rimette in PREVIEW una prova già inviata. Il registro degli invii operativi resta separato.
- Ripristinati i tre test PHP obsoleti, inclusa la prova InnoDB di due incassi concorrenti validi sulla stessa caparra.

## Installazione

1. Aggiornare Apps Script con `Workspace-3.26.131.zip` oppure con il sorgente aggregato della stessa versione, senza duplicare le funzioni. Conservare manifest, proprietà private e autorizzazioni esistenti.
2. Eseguire `configuraCartellaDiLavoro()` sul file centrale per passare allo schema **1.12.0**. La colonna finale `replica_completa_revision` distingue una replica completata da una scrittura interrotta dopo l'aggiornamento della revisione.
3. Aggiornare la distribuzione Web App esistente, quindi installare `modulo-iscrizioni-3.26.131.zip` su WordPress.
4. Collaudare con dati fittizi: Apri, normalizzazione di un nome modificato nel foglio, cambio alloggio con credito individuale e rifiuto di una quota negativa.

Alla prima apertura, le repliche precedenti senza marcatore di completamento vengono risincronizzate. Eventi con molte prenotazioni possono richiedere più tempo. Le richieste sono suddivise e non rilasciano un collegamento Google finché la verifica non è completa.

## Verifiche locali

- Suite Node WordPress/Apps Script, con regressioni su ricevute, revisioni, replica interrotta, cancellazioni a blocchi e coda email.
- 16 suite PHP mirate, comprese prove su MariaDB/InnoDB locale per pagamenti, concorrenza, cambi servizi/alloggio e ricevute delle modifiche Sheet.
- Browser Edge: apertura obbligatoria con errore e retry, navigazione soltanto dopo conferma, layout mobile; cambio alloggio multiplo con anteprima e retry idempotente.
- Sintassi PHP, asset minificati, controllo di sanitizzazione e verifica degli ZIP contro i sorgenti con SHA-256.

Queste verifiche non sono un collaudo del sito o dei trigger Google installati. Il rilascio è preparato localmente; nessuna installazione in produzione è stata eseguita in questa attività.
