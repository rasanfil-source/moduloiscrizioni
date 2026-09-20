# 3.26.135 — Colonne Sheet coerenti con l’evento

La sola richiesta della data di nascita selezionava il profilo Viaggio complesso, che aggiungeva indiscriminatamente documenti, alloggio, trasporto, pasti, assicurazione e importi. Il foglio ora riceve la configurazione effettiva dell’evento, anche senza iscrizioni: mostra i nominativi e il contatto, i soli campi e servizi configurati e le domande aggiuntive con le loro etichette. Alloggio compare solo quando previsto. I profili generici restano un ripiego per richieste provenienti da versioni precedenti.

Gli eventi gratuiti non mostrano colonne economiche, salvo importi o movimenti già registrati da conservare nella consultazione. Le presenze effettive restano visibili quando rilevate. I dati originali nel database non vengono cancellati.

La configurazione viene inviata sia alla creazione sia ad ogni apertura dal pulsante «Apri», aggiornata sotto lock e inclusa nella verifica delle modifiche concorrenti. La proiezione corregge anche i fogli esistenti, rispettando le modifiche manuali ancora pendenti. La nuova colonna tecnica schema_vista_json viene aggiunta automaticamente al registro Eventi senza richiedere di ricreare i fogli.

Verifiche: 309 test Node; test PHP del contratto di apertura, delle modifiche concorrenti e della configurazione effettiva; lint PHP e verifica dei pacchetti. WordPress da installare manualmente; Apps Script richiede la nuova distribuzione allo stesso endpoint.
