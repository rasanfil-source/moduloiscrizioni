# Modulo Iscrizioni 3.26.282

Rilascio del 6 novembre 2026. Affina il percorso di creazione evento dopo la revisione dell'anteprima 3.26.280.

- Spostamenti senza preselezione nei nuovi eventi: scelta esplicita tra nessun trasporto, passaggi condivisi e pullman. Passaggi e pullman possono coesistere. In modifica o copia si conservano le scelte dell'evento di origine.
- Le tre opzioni hanno lo stesso rilievo. I dettagli appaiono sotto l'opzione attivata. Rimossa la casella sull'assegnazione a un pullman determinato: il campo di assegnazione è disponibile nella gestione quando è previsto un pullman.
- Testi dei passaggi ridotti. Il pagamento resta sospeso fino a soddisfare le richieste indispensabili della singola prenotazione. Indicazioni di ritrovo con esempio nel placeholder, senza testo dimostrativo salvato.
- Nota sulla distinzione tra le attese presente solo con passaggi auto attivi.
- Quote accessorie apribili su richiesta; importi visibili solo per le singole voci selezionate.
- Scelta del link individuale oppure permanente. Il nome abbreviato individuale compare solo nel primo caso; nel secondo si sceglie un link già configurato nei Gruppi. Il server consente esplicitamente il link individuale anche nei gruppi dotati di permanenti.
- QR con etichetta semplificata. In assenza del logo del gruppo usa il logo del sito quando configurato, altrimenti l'immagine parrocchiale inclusa nel plugin.
- Anteprima navigabile aggiornata con spostamenti da scegliere e indicazioni di ritrovo vuote.

Verifiche: suite Node/PHP, asset e sanitizzazione; salvataggio con modalità di link esplicite; QR con immagine di ripiego; browser desktop/mobile sui sorgenti e sui minificati; controllo ZIP rispetto ai sorgenti e integrità degli archivi precedenti.

Distribuzione: `modulo-iscrizioni-3.26.282.zip` e variante `-pubblico`, senza configurazione locale del saldo. Rilascio del solo plugin; non occorre aggiornare MODULI AUTONOMO se già allineato alla 3.26.279. Nessuna installazione in produzione. Gli ZIP precedenti restano invariati.

La 3.26.282 include la verifica aggiuntiva che nasconde le quote accessorie anche passando dalla modalità per servizi alla quota unica. Gli archivi 3.26.281 generati durante il collaudo restano invariati.
