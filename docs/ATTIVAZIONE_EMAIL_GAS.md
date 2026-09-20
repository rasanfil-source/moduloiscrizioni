# Conferme tramite GAS — aggiornamento candidato

Stato: codice locale verificato; non distribuito e nessuna email reale inviata durante i test.

WordPress invia la coda al progetto GAS esistente con richiesta firmata. GAS usa MailApp e l'account della distribuzione. Non serve un nuovo scope Gmail. Le email della coda non ripiegano sulla posta PHP dell'hosting.

## Installazione e verifica

1. Conservare una copia della distribuzione GAS e del plugin attualmente installati.
2. Aprire il progetto GAS collegato a WordPress, con l'account info@parrocchiasanteugenio.it. Il progetto deve continuare a usare il foglio e il segreto già configurati: non modificare gli accessi e non pubblicare segreti.
3. Sostituire il sorgente con il file candidato Codice.gs. Nelle proprietà script aggiungere MI_EMAIL_SENDER = info@parrocchiasanteugenio.it. Conservare MI_EMAIL_TEST_RECIPIENT con la casella di prova già autorizzata.
4. Eseguire statoCanaleEmail_ e verificare ok=true. Se non è possibile distribuire con info@, fermarsi: MailApp usa l'account esecutore, non un alias arbitrario.
5. Aggiornare la distribuzione web app esistente a una nuova versione mantenendo URL, accesso ed esecuzione come utente che distribuisce. Non creare un URL pubblico privo della verifica della firma.
6. Installare il plugin candidato; mantenere modalità Prova e controllare il destinatario. Il pacchetto include lo stato corrente del progetto locale, comprese modifiche precedenti: non è una patch isolata dei soli file email.
7. Collaudare una nuova conferma Santiago alla sola casella di prova e controllare Ricevuta/originale: mittente info@, server Google, SPF/DKIM/DMARC. SENT indica accettazione Google, non consegna certificata. Le vecchie righe SENT non sono reinviate automaticamente.

## Diagnostica e recupero

Il foglio «Registro invii email» conserva chiave tecnica, stato e data, senza corpo o destinatario. ACCEPTED conferma che MailApp ha accettato l'invio. Un tentativo ripetuto con la stessa chiave non invia nuovamente. SENDING persistente indica esito incerto: controllare i log Google prima di intervenire, non cancellare la riga per forzare una ripetizione.

La colonna Ultimo errore WordPress distingue account non autorizzato, destinatario di prova non coincidente, quota e consegna incerta. Dopo cinque tentativi l'email passa a FAILED/TEST_FAILED. Riaccodare non aggira la protezione GAS contro i duplicati.

La prova sintetica preesistente resta su GAS; il nuovo controllo dell'account è applicato al canale delle conferme. L'attivazione operativa rimane una scelta amministrativa separata.

Verifiche locali: 263 test Node passati; sintassi PHP dei tre file modificati valida. Collaudo live e autenticazione della consegna non eseguiti: accesso browser bloccato per limite d'utilizzo.
