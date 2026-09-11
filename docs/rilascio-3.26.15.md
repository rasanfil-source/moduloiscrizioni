# Versione 3.26.15

Correzione della numerazione: 3.26.14 era già presente nei sorgenti locali prima dell’ultimo aggiornamento. Usare questo pacchetto, che identifica senza ambiguità il nuovo saldo pubblico e tutte le modifiche gestionali.

Il contenuto funzionale coincide con il rilascio precedente; cambiano il numero del plugin e la versione degli asset, così il browser ricarica anche JavaScript e CSS.


Pagina pubblica del saldo ripresa dal modello Cammino fornito: stile responsive, ricerca per cognome con scelta dei nomi, normalizzazione di accenti e apostrofi, schede aggiuntive, compagno della matrimoniale esplicitamente assegnata, opzioni confermate bloccate, transfer modificabili, riepilogo, conferma, email e finestre di pagamento con copia dei dati.

La caparra non è presunta incassata: il registro dei pagamenti e dei rimborsi determina caparra versata, caparra ancora dovuta, saldo e totale da versare. Un’iscrizione tardiva può versare caparra e saldo insieme. Il ricalcolo conserva le rettifiche economiche e non crea movimenti di pagamento. Nei gruppi di iscritti con contabilità comune, quote comuni e incassi sono ripartiti fra le persone; la pagina lo esplicita.

Il server verifica evento, persona, servizi consentiti, prezzi, versione dei dati e riepilogo confermato. Il salvataggio delle persone selezionate è atomico, tracciato e idempotente. MySQL resta autorevole, con replica sul foglio Google. Il riepilogo email rispetta la modalità di spedizione configurata e viene accodato una sola volta; include gli eventuali dati obbligatori mancanti.

Include inoltre gli aggiornamenti gestionali delle versioni locali 3.26.10–13, le colonne condizionali, le iscrizioni chiuse in fondo quando richieste, le avvertenze di eliminazione, il primo passaggio per gli eventi duplicati e l’allineamento dei pulsanti nel passaggio conclusivo.

Lo ZIP allegato alla release include `public-balance-config.json`, con la configurazione di pagamento del modello fornito, pubblicata su esplicita autorizzazione del gestore. Al primo utilizzo i valori vengono conservati nell’opzione WordPress `mi_public_balance_payment`; i metadati evento `_mi_balance_iban`, `_mi_balance_holder`, `_mi_balance_card_url` e `_mi_balance_contact` consentono sostituzioni. Restano disponibili solo i metodi di pagamento abilitati per l’evento.

I sorgenti del repository contengono un esempio di configurazione vuoto. Per un’installazione diversa dal sito destinatario, compilare la propria configurazione o impostare l’opzione WordPress sopra indicata. Non occorre reinserire i valori se già conservati in WordPress.

Verifiche: suite Node WordPress/Workspace; test PHP su ricerca, omonimi, caparra, rimborsi, salvataggio e retry; prove browser su desktop e mobile per saldo, gestione iscrizioni e camere; controllo sintassi e sanitizzazione. Apps Script invariato.

L’installazione WordPress dello ZIP è a cura dell’operatore. Pubblicare il codice su GitHub non installa il plugin sul sito.
