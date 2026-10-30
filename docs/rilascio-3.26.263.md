# Modulo Iscrizioni 3.26.263

- Gli operatori sospesi non possono gestire operatori o gruppi neppure usando una sessione già aperta.
- Gli incassi che lasciano la prenotazione in attesa conservano le scadenze esistenti; uno storno che riapre una prenotazione confermata continua ad applicare la finestra prevista.
- Il rapporto presenze unisce iscrizioni soltanto tramite collegamenti di identità confermati. Un cellulare condiviso non somma più le presenze di persone diverse.
- Aggiunte prove di regressione integrate e riallineate le fixture delle verifiche alle regole correnti sulle scadenze e sugli importi non validi.

Il rilascio riguarda il plugin WordPress. Nessun aggiornamento Apps Script necessario per MODULI AUTONOMO. Archivi precedenti conservati invariati.

## Verifiche

- Verifica locale completa superata: 409 test Node, 7 test della cache, 71 script di test PHP, sintassi PHP e sanitizzazione.
- Asset rigenerati e verificati dopo l'aggiornamento del testo del rapporto presenze.
- ZIP locale (93 file) e pubblico (92 file) verificati confrontando ogni file con i sorgenti; checksum SHA-256 salvati accanto agli archivi.
- Tutti i 74 ZIP precedenti invariati, inclusi i pacchetti 3.26.226.
- Nessuna installazione sul sito o modifica ai servizi Google. Il collaudo non comprende browser di produzione o database InnoDB reale.
