# Rilascio 3.26.215

Quando le presenze sono disponibili e compaiono le relative caselle nel riepilogo iscritti, l'elenco viene ordinato automaticamente per cognome A–Z. Vale sia aprendo un evento con presenze già disponibili sia quando la finestra presenze si attiva a pagina aperta.

La scelta automatica precede il caricamento della prima pagina: nessuna richiesta aggiuntiva e nessun caricamento completo degli iscritti. Filtri e ricerca rimangono impostati. L'operatore può scegliere un altro ordinamento; aggiornamenti del riepilogo e salvataggi non lo sovrascrivono. L'automatismo non modifica la preferenza memorizzata, che resta valida negli altri eventi e quando le presenze non sono disponibili (predefinita: iscrizioni più recenti in alto).

Test di regressione su attivazione iniziale e successiva, direzione alfabetica, ripristino dell'ordinamento ordinario, mantenimento della scelta manuale e assenza di scritture automatiche nella preferenza. Asset minificati rigenerati.

Include la 3.26.214. Aggiornare il solo plugin WordPress; Apps Script invariato. Pacchetti locali, installazione sul sito da completare.
