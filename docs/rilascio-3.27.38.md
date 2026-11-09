# Modulo Iscrizioni 3.27.38

Rilascio del 9 novembre 2026. Carattere uniforme nel promemoria di verifica partecipazione.

Il saluto personalizzato e il corpo del promemoria ora dichiarano entrambi lo stesso stile inline: Arial/Helvetica, 17 px, interlinea 1,68. La dichiarazione esplicita riduce le differenze introdotte dai client di posta tra paragrafi e blocchi. Lo spazio dopo il saluto resta 16 px.

La correzione riguarda le nuove email preparate, operative e di prova. Le email gia ricevute non cambiano. Testo, nomi, grassetti, pulsanti e collegamenti personali restano invariati. Include la diagnostica degli annullamenti della 3.27.37.

Installazione: aggiornare soltanto il plugin WordPress con lo ZIP della variante abituale. Nessun aggiornamento Google necessario. Nessuna email reale inviata e nessuna installazione sul sito durante il collaudo.

Verifiche superate: collaudo grafico WebKit per singolo, multiplo e prova, a 760 e 375 px, anche con uno stile del client che riduce i paragrafi; carattere, dimensione e interlinea identici, grassetti conservati. Verifica locale completa superata, inclusi 440 test Node, 7 test della cache generata, controlli PHP e sanitizzazione. Confronto con 3.27.37 limitato al modello email e al numero di versione.
