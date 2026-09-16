# Segreteria eventi 3.26.121

## Email degli eventi

- La pubblicazione/attivazione invia l’avviso alla segreteria centrale configurata, anche quando il gruppo ha un diverso contatto. L’eventuale gestore riceve la propria comunicazione, senza duplicare destinatari uguali.
- L’annullamento accoda un avviso interno anche senza iscritti; include il motivo, conserva lo stile istituzionale e segnala gli errori di salvataggio. I tentativi ripetuti non duplicano l’avviso.
- L’eliminazione conserva l’avviso interno introdotto nella 3.26.120: resta disponibile dopo la cancellazione dell’evento e viene spedito solo dopo il completamento della procedura.
- In Operativo si elaborano esclusivamente le email operative. Le vecchie email di prova restano escluse e non vengono convertite in invii reali. La modalità selezionata e le impostazioni grafiche sono conservate.

## Domande aggiuntive in Google Sheets

Il pacchetto GAS comprende `DomandeEvento.gs` e registra le definizioni in `Eventi.domande_json`. Le domande obbligatorie e facoltative compaiono come colonne sia nella struttura iniziale del foglio, sia nelle viste salvate e nelle successive sincronizzazioni. Le risposte facoltative mancanti restano celle vuote; le colonne completamente vuote sono conservate. Le etichette sono recuperate anche dalle istantanee storiche delle iscrizioni.

## Verifiche

- 286 test Node superati per WordPress e GAS, inclusa la proiezione di risposte obbligatorie, facoltative, mancanti e l’isolamento fra eventi.
- Test PHP con database locale: pubblicazione, annullamento senza iscritti, destinatario centrale, gestore, deduplicazione, errori di accodamento, invio operativo senza `[PROVA]`, esclusione delle code di test.
- Test InnoDB di eliminazione: permessi, Google offline, rollback, ripresa e conservazione degli avvisi.
- Nessun evento operativo creato, annullato o eliminato per il collaudo; il trasporto email nei test è simulato.

## Installazione

Installare `dist/modulo-iscrizioni-3.26.121.zip` sostituendo il plugin esistente. Aggiornare `Codice.gs` con `dist/Codice-Workspace-Progetto-3.26.121.gs`, salvare e aggiornare la distribuzione web esistente mantenendone l’URL. Eseguire `sincronizzaFogliEventi` per riallineare le proiezioni esistenti.
