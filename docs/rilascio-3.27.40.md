# Modulo Iscrizioni 3.27.40

Rilascio del 9 novembre 2026. Pulsante Google e disposizione dei campi nella scheda evento.

- Il pulsante del foglio nella scheda Eventi aperta usa gli stessi stili comuni di Iscrizioni, anche quando il CSS della gestione iscritti non viene caricato. Etichetta esplicita «Apri foglio», icona e dimensioni coerenti. Il collegamento continua a passare dall’apertura verificata del foglio, senza modifiche alla sincronizzazione.
- Le etichette e le caselle delle date sono allineate in alto: le note sotto apertura e chiusura non spostano piu gli altri campi. «Apertura iscrizioni» ha una nota separata per il comportamento del valore vuoto.
- Nel passaggio «Date e posti», la nota «Formato richiesto» segue i campi data e precede «Mostra nel modulo di iscrizione il numero dei posti disponibili».

La versione include la tipografia email della 3.27.39. Aggiornare soltanto WordPress con la variante abituale dello ZIP 3.27.40; nessun aggiornamento Google necessario. Nessuna installazione sul sito, invio reale o modifica alle iscrizioni durante il collaudo.

Verifica grafica con HTML PHP reale, confronto del pulsante con il modello Iscrizioni, quattro larghezze (1280, 1048, 760 e 375 px), CSS sorgente e minificato. Controllati allineamento, assenza di overflow, ordine visivo della nota e conservazione dei valori e della destinazione del pulsante. La regressione PHP e inclusa nella verifica locale e in GitHub Actions.

Suite locale completa superata: 440 test Node, 7 test degli asset generati, controlli PHP e sanitizzazione. Confronto del pacchetto rispetto a 3.27.39 limitato agli stili interessati (e derivati minificati), al markup della scheda/wizard e al numero di versione. Gli archivi precedenti, incluso 3.26.226, rimangono invariati.
