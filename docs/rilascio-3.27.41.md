# Rilascio 3.27.41 — correzioni degli audit email e permessi

9 novembre 2026. Aggiornamento coordinato WordPress e **MODULI AUTONOMO**.

## Installazione

1. Aggiornare il solo progetto Google operativo MODULI AUTONOMO con `dist/Codice-Workspace-Progetto-3.27.41.gs`. Salvare e aggiornare la distribuzione web esistente a una nuova versione, conservandone l'URL e le impostazioni di esecuzione/accesso. Non aggiungere Segreteria.html e non modificare MODULI o DB_MODULI.
2. Installare `dist/modulo-iscrizioni-3.27.41.zip` in WordPress. La variante `-pubblico.zip` esclude la configurazione privata del saldo.
3. Il rilascio non invia prove o campagne da solo. La prova delle impostazioni ora usa il trasporto con ricevuta e verifica anche il mittente richiesto: richiede il nuovo sorgente Google.

Il codice Google conserva le ricevute nel normale archivio proprieta; prima che questo cresca eccessivamente trasferisce le ricevute in un foglio tecnico separato, creato automaticamente dall'account della distribuzione. Contiene soltanto chiavi tecniche e stati di consegna, senza nominativi, destinatari o corpi email. Le proprieta vengono rimosse soltanto dopo scrittura e flush della copia. Gli esiti SENDING incerti restano protetti contro il reinvio anche nell'archivio. Non e un ripristino del workbook centrale dismesso.

## Comportamenti corretti

- Permessi: i sospesi non possono modificare tipi di comunicazione, recuperare testi o inviare messaggi. La voce Messaggi compare soltanto con il permesso relativo; Riaccoda rispetta il permesso amministrativo anche nella visualizzazione.
- Destinatari: email assenti/non valide escluse prima della preparazione e indicate separatamente; prova anonima ancora disponibile con un campione senza recapito. Il servizio rifiuta esplicitamente oltre 1000 prenotazioni senza troncare silenziosamente.
- Importi: sollecito caparra sul residuo effettivo, esclusione delle caparre gia coperte, istruzioni/scadenza/causale mantenute quando la caparra e zero ma il saldo e positivo. Nessun riferimento automatico al pagamento per eventi NONE/ZERO senza somme dovute.
- Composizione: segnaposto sostituiti anche negli avvisi generici, personalizzati e di verifica partecipazione; etichetta del tipo personalizzato usata in oggetto/titolo; nomi lunghi con prefissi uguali generano codici distinti. Testo svuotato dei tipi che richiedono un messaggio respinto chiaramente. Invito al collegamento personale sostituito con contatto dell'organizzazione quando nessun link e disponibile.
- Formattazione: grassetto/corsivo annidati e collegamenti Markdown coerenti; asterischi delle moltiplicazioni conservati; normalizzazione degli stili idempotente; codici scritti liberamente nei nuovi testi non scambiati per identificativi tecnici. Rimane la rimozione compatibile dei vecchi identificativi negli snapshot storici.
- Impostazioni: override assente riconosciuto correttamente; validazione completa prima di salvare stile/modello; decodifica POST una sola volta con backslash preservati. Tolto il selettore email QR/barcode senza effetto; resta il QR pubblico di iscrizione.
- Aggiornamenti prenotazione: notifica autonoma dalla conferma iniziale disabilitata, sempre soggetta alla modalita globale di spedizione.
- Preparazione: modalita effettiva delle bozze ANTEPRIMA, senza richiedere un canale di invio verificato; scansione oltre i primi dieci job sospesi; puntatori separati per prova e campagna reale; anteprime preparabili durante la pausa del trasporto.
- Coda: orfani marcati falliti senza invio; eventi bloccati lasciati al recupero periodico dopo una scansione completa, senza ciclo rapido perpetuo; EVENT_BUSY limita la pausa al relativo evento; backoff crescente per errori ordinari. Le nuove consegne di prova sono TEST_SENT, conteggiate e distinte nell'avanzamento. Gli stati SENT storici non vengono reinterpretati.
- Trasporto Google: Reply-To delle prove diretto al recapito di prova; recipient validato piu rigorosamente; identita e alias riutilizzati nello stesso lotto mantenendo il lock per messaggio. Guasto di storage prima del trasporto rinviato come EMAIL_STORAGE_UNAVAILABLE; errore dopo l'inizio del trasporto resta incerto e non viene ritentato alla cieca.
- Il tipo generico REGISTRATION_CANCELLATION seleziona correttamente le prenotazioni annullate.

## Decisioni e limiti dell'audit

Consultazione pubblica del saldo per cognome e invio al recapito valido scelto dal richiedente **mantenuti per scelta esplicita**: vedere `decisione-saldo-pubblico-2026-11-09.md` e AGENTS.md. Nessuna modifica alla relativa classe.

Non applicate le ipotesi non confermate sui lock di altri processi; conservati i controlli di proprieta e il rilascio allo shutdown. Non riattivate le vecchie schermate Segreteria.html, ne aggiornati i progetti Google archiviati. La preparazione del portale rimane a blocchi di dieci; il vecchio servizio firmato diretto resta sincrono con limite esplicito di 1000. ACTION_NOT_ALLOWED resta un rinvio protettivo per distribuzioni Google non allineate. Nessuna modifica al testo italiano approvato del promemoria.

## Verifica

Suite Node completa, suite PHP applicativa, sintassi PHP, asset generati e sanitizzazione. Nuovi test di regressione su permessi/sospensione, collisioni dei tipi, destinatari e caparre, placeholder/Markdown/salvataggi, modalita/job e recupero della coda. Test Google con guasti simulati prima/dopo il trasporto, archivio ricevute, replay, recipient e caching alias. Nessuna email reale inviata durante lo sviluppo. Pacchetti precedenti verificati invariati.
