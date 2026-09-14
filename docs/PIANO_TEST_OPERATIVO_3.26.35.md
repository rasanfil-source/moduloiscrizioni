# Piano di test operativo 3.26.35

La registrazione manuale usa la stessa pipeline di validazione, quote, capienza e idempotenza della registrazione pubblica. Ogni modifica futura a tali controlli va provata su entrambi i percorsi.

## Staging con dati reali

- Pagamenti: nessun versamento, versamento parziale, saldo e prenotazione già saldata; dopo ogni movimento verificare subito Totale, Versato e Residuo. Lo storico deve essere chiuso all'apertura.
- Camere: assegnazione iniziale, camera piena, un solo posto libero, cambio e scambio. Per un cambio di sistemazione verificare Prima, Dopo e Differenza della quota.
- Adesione manuale: iscrizione dopo la chiusura, con e senza disponibilità camera, con pagamento immediato e successivo. Ripetere con un utente non autorizzato e verificare che non possa usare il bypass della finestra temporale.
- Alloggi: provare PREBOOKED e ON_DEMAND. In ON_DEMAND senza camere assegnate il pannello Camere e occupanti non deve comparire; deve comparire appena esiste almeno una camera assegnata. Un operatore che non ha creato l'evento deve capire modalità, limiti e azioni disponibili senza documentazione esterna.
- Partecipanti: ricerca, filtro da saldare, filtro senza camera e vista essenziale Nome, Camera e Stato.

Registrare per ogni caso l'operatore, evento, esito e identificativo della prenotazione. Un difetto CRITICO blocca una funzione richiesta o può rendere errati dati economici o logistici; un difetto IMPORTANTE compromette la comprensione senza bloccare l'operatività.
