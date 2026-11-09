# Modulo Iscrizioni 3.27.31

Rilascio del 9 novembre 2026. Data e ora di iscrizione e cancellazione nei nuovi eventi.

- Le colonne automatiche **Iscrizione (data e ora)** e **Cancellazione (data e ora)** sono aggiunte agli Excel dell'evento e al foglio Google operativo. Restano in fondo, prima dell'eventuale codice prenotazione.
- Il formato e `AAAA-MM-GG HH:MM:SS`, con secondi e fuso orario del sito, compresa l'ora legale. La cancellazione resta vuota per le persone attive e per le scadenze automatiche, che non sono annullamenti.
- Il sistema usa le date canoniche gia registrate da WordPress: creazione della prenotazione, annullamento individuale oppure annullamento dell'intera prenotazione. Una cancellazione individuale precedente conserva il proprio orario anche se in seguito viene annullato l'intero ordine. I dati mancanti restano vuoti.
- La funzione si attiva esclusivamente alla creazione di un nuovo evento dopo l'aggiornamento. La modifica o ripubblicazione di eventi gia esistenti non la attiva. Una nuova copia di un evento adotta la funzione.
- Nei nuovi fogli Google le persone annullate restano leggibili con stato **Annullata** e data della cancellazione; i loro progressivi sono conservati. Le due date sono protette dalle modifiche manuali.
- Nel portale riepiloghi e conteggi restano invariati. Per includere i cancellati nell'Excel selezionare **Filtri > Mostra anche le iscrizioni chiuse**. Le due colonne sono automatiche e non dipendono dalle precedenti preferenze di esportazione.
- Inclusa la correzione 3.27.30 del caricamento iniziale di Messaggi; composizione e invii email non sono modificati.

## Installazione

Aggiornare prima il solo progetto **MODULI AUTONOMO** con `Codice-Workspace-Progetto-3.27.31.gs`, salvare e aggiornare la distribuzione Web App mantenendo lo stesso URL. Installare poi `modulo-iscrizioni-3.27.31.zip` oppure la consueta variante `-pubblico`. Gli eventi gia esistenti non richiedono interventi.

## Verifiche

Suite completa `tools/verify.ps1` superata: 440 test JavaScript, 7 test degli asset generati, script PHP, sintassi e sanitizzazione. Verifiche dedicate su attivazione dei soli nuovi eventi, fuso orario e ora legale, date mancanti, annullamenti individuali e completi, proiezione Google firmata, ordine delle colonne, protezione delle date, esportazione Excel e permanenza dei conteggi attivi. Collaudo browser WebKit superato con sorgente e asset minificato: esportazione reale XLSX degli attivi, inclusione degli annullati, date assenti dal riepilogo e formato precedente per gli eventi esistenti. Il collaudo Edge locale non e stato eseguibile per un errore di avvio del browser nell'ambiente ristretto; il collaudo alternativo WebKit e stato completato. Nessuna installazione sul sito e nessun invio reale.

Tutti i pacchetti precedenti, incluso 3.26.226, rimangono invariati.
