# Modulo Iscrizioni 3.27.39

Rilascio del 9 novembre 2026. Stesso carattere e stessa dimensione per saluto e corpo in tutti i modelli email.

La composizione finale applica Arial/Helvetica e una base di 17 px direttamente agli elementi del corpo, anziche affidarsi alla sola ereditarieta dal contenitore. Se il corpo contiene un formato esplicito maggiore, saluto e testo vengono portati al formato maggiore. Titoli e relativi discendenti conservano il loro stile. Grassetti, corsivi, contenuto, margini, colori e collegamenti restano conservati.

Copertura: conferme di iscrizione, promemoria di partenza/partecipazione/saldo/caparra, annullamenti di evento/iscrizione/partecipazione, lista d’attesa e offerte, notifiche alla segreteria, pubblicazione, riepiloghi del saldo e prove. La correzione viene applicata anche alle istantanee gia in coda quando vengono composte per l’invio, senza modificarne i dati conservati. Le email gia ricevute non cambiano. Pulsanti, firma, assistenza e intestazioni mantengono la gerarchia grafica esterna al corpo.

Installazione: aggiornare soltanto WordPress con la variante abituale dello ZIP 3.27.39. Nessun aggiornamento Google necessario. Nessuna email reale inviata durante il collaudo e nessuna installazione sul sito.

Verifiche superate: 18 modelli reali con dati sintetici e 36 controlli grafici WebKit desktop/mobile, anche simulando stili del client differenti per paragrafi e tabelle. Verificati formati maggiori in pixel e punti, titoli intatti, grassetti/corsivi, attributi dei link e conservazione delle istantanee. Suite locale completa superata, con 440 test Node, 7 test degli asset generati, controlli PHP e sanitizzazione. La regressione tipografica e inclusa sia nella verifica locale sia in GitHub Actions.
