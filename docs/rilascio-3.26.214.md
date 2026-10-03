# Rilascio 3.26.214

La webapp apre Iscrizioni quando l'indirizzo non specifica una scheda, anche all'avvio della PWA. I collegamenti espliciti a Eventi e alle altre schede conservano la propria destinazione.

La selezione della vista è condivisa fra rendering e caricamento degli asset: lo script Iscrizioni è disponibile subito, mentre la lista e i dettagli della scheda Eventi non vengono preparati all'avvio. Restano il riepilogo aggregato, la lista paginata e il caricamento su apertura dei pannelli camere, presenze e proposte. Nessun cambiamento ai permessi o agli eventi accessibili a ciascun gestore.

Verifica di regressione: asset per 20 combinazioni di scheda e autenticazione; rendering iniziale browser/PWA senza accessi alla lista Eventi; caricamento Eventi solo alla navigazione esplicita. Nessuna misura di latenza sul sito di produzione.

Include le correzioni della 3.26.213. Aggiornare il solo plugin WordPress; Apps Script invariato. I pacchetti sono locali e non sono stati installati sul sito. Usare il pacchetto pubblico per la distribuzione esterna.
