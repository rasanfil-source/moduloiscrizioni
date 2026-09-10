# Gestione iscrizioni 3.26.2

Aggiornamento dell’interfaccia WordPress. Apps Script e file Google non richiedono modifiche.

- Rimosso il titolo visibile Periodo; il selettore mantiene un nome accessibile.
- Voce Gestione iscrizioni attiva con sfondo blu, testo bianco e indicazione della pagina corrente.
- Riepilogo subito dopo Evento, seguito da comandi, collegamenti, stato di aggiornamento e pulsante evidente Inserisci una nuova iscrizione.
- Singolare corretto per una prenotazione totale e una aperta.
- Rapporto annuale spostato prima delle colonne CSV/stampa e mostrato dopo la selezione dell’evento.
- Riepilogo contestuale: camere solo con inventario o opzioni di alloggio; incassi solo con gestione economica; caparra solo nel piano pertinente; filtri richieste, scadenze offerte e servizi solo in presenza dei relativi dati.

Le opzioni di alloggio abilitate consentono di creare la prima camera anche quando l’inventario è vuoto. Le camere già registrate restano accessibili per non nascondere assegnazioni storiche.

Verifiche: 230 test Node, test PHP del riepilogo, browser ricerca/paginazione/stampa e prova dedicata evento senza pernottamento/incassi, singolari, ordine dei comandi, rapporto annuale e aggiornamento ripetuto. Sintassi PHP/JS e sanitizzazione superate.

Pacchetto locale `dist/modulo-iscrizioni-3.26.2.zip`. Non ancora installato sul sito: aggiornare il plugin tramite Installa/Sostituisci e ricaricare il portale con Ctrl+F5. Nessuna modifica ai dati, nessuna eliminazione di file Google.
