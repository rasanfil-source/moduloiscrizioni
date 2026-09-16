# 3.26.125 — Destinatari del gruppo e pulsanti affiancati

Se è configurato un indirizzo valido nel gruppo, gli avvisi agli organizzatori per nuove iscrizioni e cancellazioni vanno esclusivamente a quell'indirizzo. Senza recapito valido si usa la segreteria parrocchiale. Restano le conferme rivolte agli iscritti.

Pubblicazione, annullamento ed eliminazione evento notificano gruppo e segreteria, deduplicando indirizzi uguali anche con maiuscole diverse. Non viene aggiunto il recapito personale del gestore. Il recupero di pubblicazione usa la medesima regola.

Contatti, assistenza saldi e Reply-To seguono il recapito del gruppo. WordPress trasmette `reply_to` nella richiesta firmata; Apps Script lo valida e lo usa in modalità OPERATIVO. Le prove restano isolate. Le email già ricevute conservano le vecchie intestazioni.

I pulsanti delle email istituzionali sono affiancati in celle della stessa riga di una tabella di presentazione, con 12 px di spazio; stili e testi restano quelli stabiliti.

Verifiche: test Node, test PHP di routing su database locale per gruppo, assenza recapito, recapito non valido, deduplicazione e Reply-To, e lint PHP. Nessuna email di prova reale inviata.

## Stato pubblicazione

Il backend è stato salvato nell'editor Apps Script e verificato tramite copia integrale. Il deployment operativo è ancora la versione 76: la finestra Gestisci deployment è in modalità modifica, senza conferma di una nuova versione. Il browser è stato bloccato dal controllo automatico per limite di utilizzo. WordPress operativo resta 3.26.123. Il pacchetto finale da installare è 3.26.125; non usare il precedente ZIP 3.26.124, privo dei pulsanti affiancati.

Per completare: pubblicare una nuova versione del deployment Apps Script esistente, mantenendo URL ed autorizzazioni; installare il ZIP 3.26.125 e verificare la connessione firmata. Il codice GAS delle distribuzioni 3.26.124 e 3.26.125 è uguale: la modifica successiva riguarda soltanto il rendering email WordPress.
