# Rilascio 3.26.136

L'apertura del foglio poteva fallire mentre il giro automatico deteneva il blocco globale di Apps Script. Le richieste di apertura firmate ottengono ora priorità temporanea sui giri automatici; se un processo è già attivo, il portale riprova ogni tre secondi, con un limite di due minuti di contesa. Firma, protezione dai replay e controllo delle revisioni restano obbligatori.

Il pulsante **Apri** aggiorna e verifica il foglio dalla pagina Gestione iscrizioni e poi naviga al documento nella stessa scheda, senza la pagina intermedia. Gli errori restano nella gestione; un nuovo clic permette di riprovare. I vecchi collegamenti diretti conservano il percorso di apertura verificata.

Per gli eventi con prezzo ZERO, schema confermato e nessuno storico economico, Dati operativi non contiene intervalli modificabili. La scheda Gestione evento viene nascosta. Modifiche anagrafiche e presenze si registrano nel portale e vengono replicate al foglio. Le modifiche manuali preesistenti restano da sincronizzare prima della conversione; non vengono scartate. Gli eventi con storico economico mantengono la gestione precedente. Il proprietario Google conserva i poteri previsti da Google sulle protezioni.

Il confronto dello schema restituito da Google ignora l'ordine delle chiavi degli oggetti. Camere, profilo e schema identici non vengono riscritti; una replica camere parziale viene comunque riparata anche a parità di revisione.

## Verifiche

- Suite Node: 313 test superati.
- PHP: apertura verificata, permessi, versioni concorrenti, schema con chiavi riordinate, tentativi e scadenza dell'attesa.
- Browser locale: apertura dalla gestione, errore recuperabile, attesa, doppio clic, destinazione verificata; percorso precedente e presenze.
- ZIP verificati confrontando ogni file con il sorgente.

## Installazione

Aggiornare la distribuzione Web App con il codice Workspace 3.26.136 e installare **modulo-iscrizioni-3.26.136.zip** da Plugin in WordPress. Il file Workspace è destinato ad Apps Script e non è un plugin WordPress. L'installazione WordPress è a cura del proprietario del sito. La verifica completa sul sito richiede entrambi gli aggiornamenti.
