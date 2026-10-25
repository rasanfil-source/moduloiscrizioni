# Modulo Iscrizioni 3.26.259

- Il riepilogo di eliminazione ordina per chiave primaria ogni insieme di righe incluso nel fingerprint, evitando richieste di riconferma dovute a un ordine SQL non deterministico.
- Il cambio manuale del gruppo evento acquisisce il lock dell'evento prima di leggere o aggiornare l'associazione e interrompe l'operazione se il conteggio iniziale delle iscrizioni non è disponibile.
- Le date dei movimenti inviate a Workspace sono espresse come data locale `AAAA-MM-GG`, coerente con la data scelta dall'operatore.
- Il saldo pubblico viene agganciato una sola volta; la cache gestisce in sicurezza transient incompleti o non decodificabili.
- Il filtro camera accetta sia il contesto con prefisso `room:` sia il codice camera grezzo.
