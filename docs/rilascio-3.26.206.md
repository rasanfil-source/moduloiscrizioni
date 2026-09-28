# 3.26.206 — Riepilogo partecipanti aggregato

La risposta iniziale contiene contatori, importi, totali servizi, filtri e definizioni dei rapporti. Non contiene più i record di tutti i partecipanti e delle prenotazioni, né l’inventario camere. L’elenco continua a ricevere pagine da 30 righe.

Camere, presenze e posti proposti caricano i soli dati del rispettivo pannello quando viene aperto. Riaprire un pannello non provoca un’altra richiesta e conserva le selezioni. Gli errori offrono Riprova; risposte tardive non possono modificare un nuovo riepilogo. Ogni richiesta verifica sessione, nonce, autorizzazione all’evento e nome del pannello.

I totali mantengono le regole esistenti: importi in centesimi, versato netto inclusi rimborsi, esclusione degli stati non esigibili dagli incassi e conteggi dei servizi per persona/prenotazione. I prefissi delle camere restano disponibili nella prima pagina. Stampa ed Excel conservano lettura completa filtrata e verifica della versione tra pagine.

## Misura riproducibile

Il test `management-overview.php`, con 1.000 partecipanti sintetici e relative prenotazioni, misura 499.377 byte per il precedente riepilogo compatto e 771 byte per quello aggregato: riduzione di circa 99,85%, prima della compressione HTTP. Con 5.000 partecipanti il riepilogo aggregato occupa 785 byte. Le dimensioni reali dipendono da servizi, campi e configurazione dell’evento; i byte della prima pagina sono aggiuntivi.

Il modello completo resta elaborato e memorizzato nella cache sul server: questa modifica riduce trasferimento e lavoro del browser, non elimina tutti i calcoli MySQL. I pannelli, quando richiesti, ricevono ancora l’insieme pertinente necessario per operazioni collettive.

## Verifiche

- Suite locale Node/PHP, sintassi, asset minificati e sanitizzazione.
- Test aggregati: stati misti, rimborsi, quantità servizi, campi personali esclusi, evento vuoto e crescita 1.000/5.000 persone.
- Test endpoint: nonce, accesso all’evento e pannelli ammessi.
- Browser con JavaScript e serializzatori PHP reali: prima pagina, prefissi camere, servizi, caricamento su richiesta, errore e retry, riapertura senza duplicazioni, risposta tardiva ed evento vuoto.
- Browser paginazione: ricerca su 265 persone, filtri, Excel e stampa completi, rifiuto esportazione se i dati cambiano.
- Evento completo desktop/mobile: camere, presenze e recupero dagli errori.

Pacchetti WordPress locali; nessuna installazione sul sito. Apps Script invariato.
