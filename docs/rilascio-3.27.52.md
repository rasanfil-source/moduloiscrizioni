# Rilascio 3.27.52

11 novembre 2026. Include tutte le correzioni della [3.27.51](rilascio-3.27.51.md).

Il comando **Copia il collegamento al foglio** copia ora l'indirizzo Google diretto. Il destinatario può consultare il foglio senza autenticarsi, con la condivisione tramite link già prevista da MODULI AUTONOMO. Il comando operativo Apri mantiene il percorso di aggiornamento del foglio. Le prove locali verificano il collegamento generato; i permessi dei fogli già presenti in produzione non sono stati ispezionati.

Storni e rimborsi possono indicare un **incasso di riferimento facoltativo** della stessa prenotazione. Il registro conserva il collegamento e verifica il residuo dell'incasso, l'attribuzione alla persona e il saldo della prenotazione, anche con richieste concorrenti. Il riferimento compare nello storico, nel report del portale, nell'esportazione CSV e nelle note del foglio Google. Le richieste ripetute conservano lo stesso identificativo e lo stesso incasso di riferimento.

I movimenti precedenti restano validi e i riferimenti storici non vengono dedotti automaticamente. Il residuo del singolo incasso sottrae gli storni e rimborsi esplicitamente collegati; gli altri movimenti continuano a incidere sul saldo complessivo e individuale. Lo storno mantiene le regole correnti di ricalcolo dello stato e della scadenza. Lo schema MySQL passa alla revisione 8 con una colonna facoltativa, senza trasformare i pagamenti esistenti.

In **Dati dimostrativi** è disponibile l'annullamento delle prove di un evento in bozza o privato, con email in modalità Anteprima. Sono riconosciute soltanto le iscrizioni create dallo strumento demo, mediante il registro di creazione. La procedura libera i posti e conserva lo storico e gli eventuali pagamenti. Non invia gli avvisi di annullamento e non promuove automaticamente la lista d'attesa. Gli errori parziali vengono mostrati e si può ripetere il comando. Non è una cancellazione definitiva dei dati e non scatta automaticamente alla pubblicazione. La creazione delle prove supporta anche gli eventi con passaggi attivi e le opzioni di risposta «0».

Il testo del consenso alle future iniziative è prodotto da PHP una sola volta: conserva la formulazione già mostrata nel browser e non viene più sostituito da JavaScript.

La verifica della configurazione Google, sia dal plugin sia dallo strumento CLI, controlla lo schema della serie 1 dalla versione **1.14.0** e le capacità della modalità autonoma. Il sorgente incluso dichiara 1.14.0. Il controllo diagnostico segnala distribuzioni vecchie, mancanti o incompatibili; non introduce un blocco aggiuntivo nei flussi operativi.

## Verifiche

- Suite completa: 462 test Node, 7 test sugli asset generati, lint e suite PHP, comprese le nuove prove dei link e delle iscrizioni dimostrative.
- MariaDB reale con dati sintetici: incassi e storni concorrenti, richiesta ripetuta, incasso estraneo alla prenotazione, persona estranea all'incasso e superamento del residuo.
- Selezione demo su MariaDB: escluse iscrizioni ordinarie, già annullate e appartenenti ad altri eventi.
- Edge: inserimento, perdita della risposta e ripetizione di incassi e storni collegati, importi, schermi da 320 a 1024 pixel, asset sorgenti e minificati.

## Installazione e punti ancora aperti

Installare il nuovo ZIP WordPress. Aggiornare **MODULI AUTONOMO** con `Codice-Workspace-Progetto-3.27.52.gs` e aggiornare anche la distribuzione della web app. Eseguire quindi la verifica dello schema dal plugin.

Per recuperare nel modulo pubblico le domande a scelta singola interessate dal difetto precedente, salvare e ripubblicare gli eventi coinvolti. Prima di pubblicare eventi usati per le prove, annullare le iscrizioni dimostrative con il nuovo comando.

Restano da definire il percorso di revoca del consenso, il ciclo di conservazione dei dati WordPress e le integrazioni con esportazione/cancellazione dei dati personali. Restano da verificare in produzione il monitoraggio del cron, la configurazione realmente installata e gli effetti pregressi dei difetti. Il saldo pubblico mantiene i comportamenti richiesti dall'utente. Nessuna installazione, pubblicazione remota o spedizione email reale è stata eseguita durante questo rilascio.
