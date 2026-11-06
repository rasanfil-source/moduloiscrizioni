# Modulo Iscrizioni 3.26.286

Rilascio del 6 novembre 2026. Revisione delle scelte e delle indicazioni nella pagina pubblica di iscrizione per eventi con trasporto in auto private.

- Tolta la scelta separata «Utilizzo i mezzi pubblici»: non aveva un comportamento diverso da «Mi organizzo autonomamente». I dati e le richieste esistenti con modalità `PUBLIC` restano compatibili.
- La disponibilità dei passaggi si carica automaticamente quando si sceglie «Cerco un passaggio in auto». Il pulsante «Aggiorna disponibilità» è stato rimosso; «Riprova» compare soltanto se il caricamento automatico non riesce.
- Nella simulazione locale, la disponibilità si aggiorna selezionando un’altra modalità e poi di nuovo «Cerco un passaggio in auto».
- Chiarito che le proposte sono individuali e possono riguardare auto diverse, eliminando «successive» che poteva suggerire un ordine o una fase ulteriore.

Verifiche: controlli statici delle etichette e delle opzioni di viaggio, caricamento automatico e riprova dopo errore, anteprima locale su desktop e mobile, suite di progetto, asset minificati e integrità dei pacchetti.

Pacchetti `modulo-iscrizioni-3.26.286.zip` e variante `-pubblico`. Nessuna installazione in produzione; nessuna modifica Google richiesta. Archivi precedenti invariati.
