# 3.26.134 — Presenza effettiva nell'elenco partecipanti

Nei gruppi con verifica presenze attiva, dall'inizio dell'evento compare la casella «Presente» all'estrema destra dell'elenco partecipanti. L'orario è interpretato nel fuso WordPress e verificato anche dal server. La pagina aperta prima dell'inizio si aggiorna automaticamente, attendendo la conclusione delle modifiche in corso.

Spuntare registra PRESENT; togliere la spunta registra ABSENT. Le persone mai rilevate restano UNRECORDED. Il salvataggio riguarda il singolo iscritto, anche in prenotazioni multiple, mantiene lo storico con operatore e data e accoda nella stessa transazione la nuova revisione della replica. I conteggi delle presenze già esistenti leggono lo stesso storico.

Workspace riceve il dato e mostra «Presenza effettiva» nella proiezione evento. La colonna è consultabile: le modifiche si registrano dal portale. In caso di errore del salvataggio, l'interfaccia ripristina la spunta precedente.

Verifiche: test MariaDB su abilitazione, orario, salvataggio, revoca e revisione; prova browser con elenco paginato, rilettura e errore simulato; test GAS su etichette e protezione. Installazione ZIP WordPress a cura dell'utente.
