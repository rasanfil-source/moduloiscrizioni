# Versione 3.26.139 — preparazione dei fogli in background

Le modifiche alle iscrizioni accodano subito la replica centrale e la preparazione del foglio evento. Il salvataggio MySQL non attende Google. Le richieste dello stesso evento vengono accorpate; il job conserva un tentativo di recupero anche se l'esecuzione PHP viene interrotta. Gli errori vengono ritentati con attese crescenti.

Una ricevuta viene emessa soltanto dopo il completamento della proiezione e il controllo della configurazione e delle revisioni correnti. Per i fogli gratuiti confermati in sola lettura, Apri può usare la ricevuta per cinque minuti senza chiamare Google. Una nuova revisione, un cambiamento di configurazione, la sostituzione del foglio o una replica pendente impediscono il riuso. Il collegamento da Gestione eventi salta anche la pagina intermedia quando la ricevuta è valida.

Per i fogli modificabili resta un controllo remoto delle celle modificate a mano. Una proiezione già verificata e invariata evita la ricostruzione della vista. La validità del controllo memorizzato è limitata a cinque minuti; errori e modifiche locali richiedono nuovamente il percorso completo.

DB_MODULI rimane la replica centrale usata dalle viste e dalle funzioni Workspace. La sua eliminazione richiederebbe una migrazione di queste dipendenze, separata da questo intervento. Non sono stati misurati guadagni di velocità sul sito con il nuovo plugin ancora da installare.

Richiede plugin WordPress e nuova distribuzione Apps Script. Nessuna modifica allo schema dati o nuova autorizzazione Google. La partenza immediata usa il cron WordPress a fine richiesta; se l'hosting ne impedisce l'avvio, resta il recupero del cron periodico già configurato. In caso di replica ancora pendente, Apri mantiene la verifica obbligatoria.

Verifica: 314 test Node, test PHP di apertura e della coda, controlli di sintassi e sanitizzazione. Coperti riuso della vista, modifiche manuali, versioni concorrenti, isolamento dei permessi, foglio sostituito e recupero dopo indisponibilità di Google.
