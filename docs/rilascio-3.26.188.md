# 3.26.188 — conferma della cancellazione della partecipazione

Il percorso di cancellazione della singola persona, usato anche dal link nell'email, accodava soltanto la notifica agli organizzatori. Ora accoda anche una conferma all'indirizzo del sottoscrittore che ha ricevuto la conferma iniziale.

Il messaggio identifica la persona cancellata e precisa che le eventuali altre partecipazioni restano invariate. Non attribuisce la cancellazione alla segreteria quando la richiesta arriva dal link personale. La nuova conferma rispetta la modalità email impostata e usa il nome mittente risolto per l'evento.

Cancellazione, avviso organizzatori e conferma personale sono nella stessa transazione. Un errore nel salvataggio della conferma provoca rollback; una chiamata ripetuta per un partecipante già annullato non genera duplicati. Il cron viene pianificato anche quando soltanto la conferma personale è da spedire.

Verifiche: fault injection su transazioni, cancellazione totale/parziale, idempotenza e rollback dell'outbox; modelli e payload email; 187 controlli Node. Nessun invio reale e nessuna cancellazione reale eseguiti durante i test.

Aggiornamento solo WordPress. Apps Script 3.26.186 resta sufficiente. Nessun invio retroattivo automatico per cancellazioni già completate. I pacchetti preparati localmente non equivalgono all'installazione sul sito.
