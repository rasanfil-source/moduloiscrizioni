# Modulo Iscrizioni 3.27.33

Rilascio del 9 novembre 2026. Prova del collegamento di cancellazione, saluto personalizzato e testo piu conciso.

La prova anonimizzata non generava i collegamenti personali, riservati all'invio operativo. Di conseguenza la copia ricevuta mostrava solo il pulsante della pagina evento, senza permettere di verificare il percorso di cancellazione.

La nuova prova contiene il pulsante **Annulla la tua iscrizione** e una nota che lo identifica come dimostrativo. Il collegamento apre una pagina pubblica con **Persona di prova** e il comando **Simula annullamento**, seguito dall'esito **Simulazione completata**. Nessuna prenotazione viene letta o modificata e non vengono creati token personali. Anche eventuali POST operativi sulla pagina dimostrativa sono ignorati.

La demo e presente nella prova puntuale e nelle anteprime/copie non operative dello stesso tipo di messaggio. Le email operative continuano a usare esclusivamente i link effettivi dei partecipanti attivi. Gli altri tipi di comunicazione non ricevono nuovi pulsanti.

Il pulsante e incluso sia nell'HTML sia nella versione testuale. La correzione del grassetto 3.27.32, il caricamento Messaggi e gli orari dei nuovi eventi restano inclusi.

Il saluto della comunicazione Verifica della partecipazione e ora **Buongiorno Nome Cognome,**. Nell'invio operativo i nomi provengono dai partecipanti attivi associati ai collegamenti personali, anche quando il referente della prenotazione e una persona diversa. Per una prenotazione con piu partecipanti il saluto ne elenca i nomi. La prova puntuale resta anonimizzata con **Buongiorno Persona di prova,**.

Il testo suggerito e piu breve: interesse per l'iniziativa, disponibilita dei posti, invito ad annullare se non si puo partecipare e ringraziamento. L'introduzione ripetitiva e stata rimossa. I testi gia personalizzati dall'operatore restano conservati; il nuovo testo si puo caricare con **Ripristina testo suggerito**. La firma configurata, compreso **A presto!**, precede il riquadro **Assistenza** sia nell'HTML sia nel testo semplice.

Installazione: aggiornare soltanto WordPress con `modulo-iscrizioni-3.27.33.zip` oppure la consueta variante `-pubblico`. Non serve un nuovo aggiornamento Google; per gli orari resta il sorgente MODULI AUTONOMO 3.27.31 gia consegnato. Dopo l'aggiornamento richiedere una nuova email di prova: i messaggi gia ricevuti non cambiano.

Verifica generale `tools/verify.ps1` superata: 440 test JavaScript, 7 test degli asset generati, script PHP, sintassi e sanitizzazione. Verifiche dedicate su preparazione reale della coda con database sintetico, HTML e testo, anonimizzazione, assenza di token reali nella prova, mantenimento dei link operativi, pagine dimostrative e isolamento dalle operazioni reali. La prova della personalizzazione usa il servizio applicativo effettivo che legge nome e cognome dei partecipanti e crea i token, con un referente diverso: verificati corrispondenza nome/token, saluto individuale e familiare, testo breve, grassetto e firma prima di Assistenza. Collaudo browser WebKit del percorso email > pulsante > conferma > simulazione completata superato. Nessuna email reale inviata e nessuna installazione sul sito.

Tutti gli ZIP precedenti, incluso 3.26.226, restano invariati.
