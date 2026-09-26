# 3.26.189 — indirizzo mittente per gruppo

Il gruppo dispone del campo «Indirizzo mittente delle email», separato dall'email per le comunicazioni con gli iscritti. Il mittente è ereditato dagli eventi del gruppo, senza sostituzioni da parte dei gestori del singolo evento. Se vuoto, si usa la segreteria. Il campo è disponibile modificando un gruppo già creato.

Le email agli iscritti che usano l'identità dell'evento, comprese conferma, annullamento totale e cancellazione della singola partecipazione, usano il mittente configurato. Il nome visualizzato continua a seguire le impostazioni di identità. Le notifiche istituzionali interne mantengono il mittente parrocchiale. Contatti, destinatari delle notifiche e risposte restano separati dal mittente.

Apps Script controlla che il mittente aggiuntivo sia restituito da GmailApp.getAliases() e lo passa come From a GmailApp.sendEmail(). Non esiste ripiego silenzioso sulla parrocchia. Alias assente o autorizzazione Gmail mancante producono un errore prima dell'invio. Il mittente primario continua a usare MailApp. Le ricevute e la chiave di consegna conservano la protezione dai duplicati e dagli invii con esito incerto.

WordPress usa l'azione firmata INVIA_EMAIL_CONFERMA_MITTENTE per i mittenti alternativi: un vecchio backend la rifiuta anziché ignorare il mittente e spedire dalla parrocchia. Gli errori di configurazione seguono gli attuali tentativi della coda; dopo aver risolto la configurazione, controllare gli eventuali messaggi falliti. Non cancellare ricevute SENDING per forzare un reinvio.

## Installazione

1. Nel progetto Apps Script autonomo esistente, salvare una copia del codice e del manifest correnti. Sostituire Codice.gs con Codice-Workspace-Progetto-3.26.189.gs.
2. Mostrare appsscript.json dalle impostazioni del progetto e aggiungere agli oauthScopes esistenti `https://mail.google.com/`. Conservare le altre impostazioni private del progetto. Il manifest incluso in Workspace-3.26.189.zip contiene gli scope di riferimento.
3. Con l'account parrocchiale che esegue la distribuzione, eseguire `verificaMittentiEmailAutorizzati` dall'editor. Accettare la nuova autorizzazione Gmail e controllare nei log che l'indirizzo aggiunto compaia. La funzione non invia messaggi. GmailApp richiede lo scope Gmail completo, benché questo codice utilizzi soltanto elenco alias e invio.
4. In «Esegui il deployment → Gestisci deployment», modificare la Web App esistente scegliendo una nuova versione. Conservare URL, account esecutore e proprietà dello script.
5. Installare modulo-iscrizioni-3.26.189.zip su WordPress.
6. In «Gruppi», aprire il gruppo interessato. Impostare «Indirizzo mittente delle email» con la casella verificata per l'invio; mantenere la casella condivisa in «Email per le comunicazioni con gli iscritti». In «Aspetto email → Personalizza identità e firma» impostare il nome visualizzato desiderato. Salvare.

Non modificare il mittente predefinito in Gmail. Non inserire password SMTP nel plugin o in Apps Script: Gmail conserva già il collegamento autorizzato.

## Collaudo e limiti

I test locali usano identità fittizie e servizi Google simulati: controllano From, nome e Reply-To, gruppo distinto, annullamenti totale/parziale, rifiuto alias non autorizzati, autorizzazione mancante, prova protetta e idempotenza. Non dimostrano l'installazione né la consegna reale.

Gli snapshot già accodati conservano il mittente registrato quando sono stati creati. Nessuna email già inviata viene reinviata. Per il collaudo usare nuove iscrizioni sintetiche, verificare il campo Da nell'originale della conferma e degli annullamenti e il destinatario proposto premendo Rispondi. Provare anche un evento di un altro gruppo.

In modalità PROVA il destinatario e il Reply-To sono la casella di prova: il mittente alternativo viene comunque verificato e utilizzato. Il Reply-To effettivo del gruppo va controllato con una prova OPERATIVA autorizzata e un destinatario sintetico controllato. Preparazione locale, installazione e collaudo online sono fasi distinte.
