# 3.26.187 — separatori email deterministici

Eliminate le euristiche che trasformavano lettere n/nn in a capo sulla base di parole, maiuscole e punteggiatura circostanti. Si normalizzano soltanto separatori espliciti: CR/LF, sequenze letterali con barra e hard break Markdown (barra seguita da un vero ritorno a capo). Testo e HTML dello snapshot sono verificati prima della serializzazione, con test anche su etichette arbitrarie.

Una coppia di lettere nn già salvata senza barre resta testo: non è possibile distinguerla automaticamente da contenuto legittimo. Il modello dist/modello-conferma-acapo-3.26.187.txt ricostruisce il messaggio segnalato e il confine che l'utente ha esplicitamente identificato come errato. Incollarlo nel testo dell'email di conferma dell'evento e salvare. Se il modello contiene altre parti personalizzate, conservarle e correggere soltanto i separatori confermati.

Il pacchetto non modifica i modelli sul sito né gli snapshot già accodati. Verificare una nuova anteprima dopo il salvataggio. Nessun ulteriore aggiornamento Apps Script rispetto alla 3.26.186; quella versione resta necessaria per il nome mittente configurabile.

Verifiche: regressioni PHP su salvataggio/snapshot/identità, separatori espliciti, conservazione di n/nn nude e 187 controlli Node superati. Nessun invio reale effettuato.
