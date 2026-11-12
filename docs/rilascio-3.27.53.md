# Rilascio 3.27.53

12 novembre 2026. Include le correzioni della [3.27.52](rilascio-3.27.52.md).

Nel modulo pubblico, **Invio / Avanti sulla tastiera** passa al campo successivo. Un valore non valido resta evidenziato nel campo da correggere. I campi nascosti, disabilitati e di sola lettura vengono saltati. Al termine dei campi si raggiunge il pulsante di avanzamento o conferma: non viene spedita automaticamente una prenotazione. Nei campi per note e richieste Invio mantiene l'a capo; le scelte a elenco e i checkbox conservano il comportamento nativo.

Nome, cognome, email e telefono del contatto vengono copiati nel **primo iscritto** mentre si scrive. Email e telefono non si fermano più al primo carattere. La copia viene aggiornata anche entrando nei campi del partecipante e prima della validazione, per leggere i dati inseriti dall'autocompletamento del browser. Se un campo del partecipante viene modificato manualmente, anche svuotandolo, la modifica resta conservata. Gli altri iscritti mantengono i propri dati. Tornare indietro e cambiare la quantità non sovrascrive le informazioni delle righe conservate.

Le email di lista d'attesa distinguono la quota della prenotazione:

- Prenotazioni gratuite, con importo zero o senza gestione dei pagamenti: **«Per ora non devi fare altro.»**
- Prenotazioni con quota da pagare dopo l'ammissione: **«Per ora non devi pagare né fare altro.»**

Anche la successiva email di offerta del posto mostra l'avviso di non pagare prima dell'accettazione soltanto quando è previsto un pagamento. La distinzione è applicata sia all'HTML sia al testo semplice e considera anche le caparre, per cui in attesa il dovuto corrente è sospeso. Link, scadenze e azioni di accettazione/rinuncia restano quelli della proposta.

La modifica riguarda le nuove email preparate dal plugin. Le istantanee già accodate e le email già spedite conservano il testo originario.

## Verifiche

- Suite locale completa: 462 test Node, 7 sugli asset generati, lint e suite PHP.
- Modelli email verificati su gratuito, quota zero, pagamento completo, caparra e prezzo soltanto informativo.
- Edge a larghezze mobile e desktop, con sorgenti e asset minificati: Invio, errori di validazione, composizione del testo, campi esclusi dalla navigazione, note multilinea, copia completa, autocompletamento, correzioni manuali, variazione della quantità e dati inviati.
- Regressioni del modulo con passaggi in auto e invio simulato.

## Installazione

Aggiornare il plugin WordPress con il nuovo ZIP. Il sorgente MODULI AUTONOMO è invariato rispetto alla 3.27.52: se quel componente è già aggiornato, questo rilascio non richiede una nuova distribuzione Google.

Pacchetti precedenti conservati senza alterazioni; ultimi cinque rilasci locali 3.27.49–3.27.53. Nessuna installazione in produzione o spedizione email reale eseguita durante le verifiche.
