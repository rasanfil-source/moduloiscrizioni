# Comunicazioni tramite Apps Script

WordPress accoda le comunicazioni operative e invia al progetto Apps Script di MODULI AUTONOMO una richiesta firmata. Apps Script utilizza `MailApp` e spedisce con l'account che esegue la distribuzione. Il canale è separato dalla posta PHP dell'hosting.

## Configurazione

1. Nel progetto Apps Script autonomo impostare `MI_EMAIL_SENDER` con l'indirizzo mittente autorizzato.
2. Impostare `MI_EMAIL_TEST_RECIPIENT` con la casella usata per i controlli in modalità Prova.
3. Conservare nelle proprietà di script l'endpoint WordPress e il segreto condiviso già configurati. Non inserirli nei sorgenti, nei fogli o nei pacchetti pubblicabili.
4. Verificare il canale con `statoCanaleEmail_()` e accertare che la risposta indichi `ok=true`.
5. In WordPress mantenere la modalità Prova e controllare che il destinatario corrisponda alla casella autorizzata.
6. Inviare una comunicazione di prova e verificare mittente, contenuto e autenticazione del dominio con gli strumenti del provider email.
7. Attivare l'invio operativo solo dopo la verifica della configurazione e del destinatario.

L'indirizzo mittente deve appartenere all'account che esegue Apps Script o a un mittente configurato per quell'account. Le autorizzazioni di Google e l'accesso alla casella restano gestiti dal titolare del progetto.

## Coda e tracciamento

La coda WordPress registra stato, chiave tecnica e data di elaborazione; il registro Apps Script conserva l'identificativo di invio senza il contenuto dell'email. La stessa chiave rende idempotente la richiesta: un invio già accettato non viene duplicato da una ripetizione della chiamata.

`ACCEPTED` indica che MailApp ha accettato l'invio. `SENDING` indica che l'esito va verificato nei registri di Apps Script prima di intervenire. Gli stati di errore riportano la causa disponibile e consentono la gestione dalla schermata amministrativa. La conferma del provider non certifica la consegna nella casella del destinatario.

La coda usa la scadenza della singola prenotazione e il fuso orario WordPress per comporre le informazioni di pagamento. Le comunicazioni già archiviate mantengono il contenuto registrato al momento dell'accodamento.
