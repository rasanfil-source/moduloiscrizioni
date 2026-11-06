# Modulo Iscrizioni 3.26.283

Rilascio del 6 novembre 2026.

- Chiusura iscrizioni facoltativa nel wizard e nella modifica rapida del portale. Se vuota viene calcolata 30 minuti prima dell'inizio, nel fuso del sito. Il titolo resta invariato e la spiegazione compare sotto il campo, come per l'apertura. Il server salva la scadenza effettiva per le iscrizioni, le proiezioni e la pubblicazione; conserva inoltre la modalità automatica per mostrare nuovamente il campo vuoto in modifica. Modificando l'inizio nel portale la chiusura automatica segue la nuova data. Le scadenze esplicite conservano il comportamento precedente. Rimangono i controlli sulle date trascorse e sull'ordine apertura/chiusura.
- Il riepilogo mostra la chiusura effettiva anche quando automatica.
- Etichetta «Da un link permanente per eventi che si ripetono». Se selezionata appare «Quale link permanente del gruppo vuoi usare?», con i soli link già configurati nei Gruppi.
- Le regole dei passaggi spiegano che il sistema propone automaticamente quelli disponibili alle persone in attesa dopo l'ammissione all'evento e che l'assegnazione si completa con l'accettazione della proposta.

Verifiche: suite Node/PHP, sintassi, asset e sanitizzazione; salvataggio di chiusura automatica, cambio di giorno, nuova data ed esplicita; browser desktop/mobile, sorgenti e minificati, riepilogo della scadenza e anteprima navigabile. ZIP confrontati con i sorgenti e archivi precedenti conservati invariati.

Pacchetti: `modulo-iscrizioni-3.26.283.zip` e variante `-pubblico`. Solo plugin; nessun aggiornamento Google richiesto rispetto alla 3.26.279 e nessuna installazione in produzione.
