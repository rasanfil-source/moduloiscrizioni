# Segreteria eventi 3.26.4

Comprende tutte le modifiche della 3.26.3 e il comando unico **Cambia sistemazione**, nella sezione Gestione camere.

## Uso

1. Aprire Cambia sistemazione e selezionare le persone, anche appartenenti a iscrizioni distinte.
2. Scegliere la nuova sistemazione; indicare il numero per una camera esistente oppure lasciarlo vuoto per una nuova assegnazione. Per più singole o multiple il numero automatico sarà distinto per persona.
3. Indicare il motivo e premere Calcola anteprima.
4. Verificare camere, dovuto precedente/nuovo, differenza, versato e somme da incassare o restituire per ciascuna iscrizione.
5. Premere Conferma cambio e importi. Sistemazione, camera e dovuto sono salvati insieme. Nessun pagamento o rimborso viene creato.

Le tariffe sono quelle conservate nell’iscrizione. Le quote estranee al pernottamento e le rettifiche precedenti restano inalterate. La caparra prevista resta invariata entro il nuovo totale. Tariffe mancanti, camere piene o dati cambiati bloccano il salvataggio; un nuovo versamento dopo l’anteprima richiede un nuovo calcolo. Il comando richiede il permesso pagamenti. Il dettaglio iscrizione conserva lo Storico cambi di sistemazione con motivo, operatore e importi.

Una tripla con una disdetta non diventa automaticamente doppia: il gestore seleziona le persone rimaste e conferma il cambio. Il rimborso della persona che disdice resta distinto dal cambio dei rimanenti.

## Installazione

Sostituire il plugin WordPress con `dist/modulo-iscrizioni-3.26.4.zip`, senza disinstallarlo, quindi Ctrl+F5 sul portale. Apps Script e distribuzione Google restano quelli già aggiornati alla 3.26.1. Non eliminare progetti o fogli. Pacchetto locale: l’installazione sul sito non è stata eseguita dall’assistente.

## Verifiche

Test MySQL su database locale isolato: cambio tra iscrizioni distinte, conservazione delle rettifiche e degli altri servizi, nessuna scrittura nell’anteprima, retry, permessi, conflitti di versione e pagamenti, rollback integrale in caso di errore sulla seconda iscrizione, evento gratuito e assenza di rimborsi automatici. Browser: anteprima, invalidazione dopo modifica, conferma e retry identico. Test generali Node e controlli sintattici/sanitizzazione.
