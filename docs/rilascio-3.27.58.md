# Rilascio 3.27.58

12 novembre 2026. Revisione delle comunicazioni automatiche di Modulo Iscrizioni.

- Saluto «Buongiorno Nome Cognome.» nelle conferme e nelle comunicazioni nominative; tono impersonale nei testi, nelle anteprime, negli oggetti e nei pulsanti. Revisione di lista d’attesa, offerte complete e parziali, promemoria, aggiornamenti della prenotazione, annullamenti, passaggi e riepiloghi del saldo.
- Il riquadro comune riporta «Per assistenza» e «Scrivere a:». La versione testuale usa la stessa indicazione.
- La sezione Messaggi usa i nuovi testi precompilati per informazioni prima dell’evento, verifica della partecipazione e promemoria del saldo, anche con «Ripristina testo suggerito». Le bozze con formulazioni standard riconosciute sono uniformate nella composizione, nell’anteprima e nella preparazione dell’invio.
- Nelle conferme dei gratuiti vengono esclusi anche i segnaposto di saldo e importo dovuto. Gli annullamenti gratuiti non propongono rimborsi; resta la spiegazione dell’eventuale annullamento automatico per mancato passaggio.
- La conferma della cancellazione di una persona cita le altre partecipazioni soltanto se la prenotazione contiene altre persone nello storico. La distinzione resta corretta anche quando viene cancellata l’ultima persona di una prenotazione multipla.
- I modelli salvati sono aggiornati in lettura per le sole formulazioni standard riconosciute. Nessuna sovrascrittura dei metadati; tag e attributi HTML, URL e segnaposto restano conservati. I testi liberi con formulazioni diverse richiedono revisione redazionale; le ricevute e le email già inviate restano storiche.

Installare il nuovo ZIP WordPress. Il sorgente MODULI AUTONOMO è invariato rispetto alla 3.27.57 e non richiede aggiornamenti. Il pacchetto pubblico esclude la configurazione locale riservata del saldo pubblico.

Verifica: 468 test Node, sintassi PHP, 108 controlli PHP e regressioni specifiche su tono, saluti, assistenza, gratuiti, composizione storica della prenotazione e integrità dei modelli. Controllata la sezione Messaggi con i tre testi precompilati, composizione e anteprima di una bozza storica. Verificate visivamente le anteprime desktop e mobile (390 px, senza scorrimento orizzontale).

I contenuti di entrambi gli ZIP sono stati confrontati file per file con i sorgenti; i dieci archivi precedenti sono risultati invariati prima della rotazione. Il sorgente MODULI AUTONOMO della 3.27.58 ha lo stesso SHA-256 della 3.27.57.
