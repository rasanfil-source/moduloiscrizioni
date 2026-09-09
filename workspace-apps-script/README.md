# Backend Workspace

La versione corrente usa MySQL WordPress come registro autorevole. I fogli evento sono proiezioni con celle operative modificabili: Sincronizza apre il confronto e la conferma nel portale. Il centrale non richiede inserimenti manuali.

- configuraCartellaDiLavoro() prepara le schede tecniche e rimuove i moduli a celle dismessi.
- apriGestioneWeb() apre il medesimo portale dal menu centrale.
- sincronizzaFogliEventi() rigenera le proiezioni con retry e cursore.
- attivaCronWordPress() crea una sola volta l'avvio del cron WordPress ogni cinque minuti, separato dalla proiezione. Serve sull'hosting corrente, dove WP-Cron tramite visite è disabilitato.
- Pagamenti, correzioni e camere si salvano in MySQL. La replica firmata conserva identificativi e revisioni; il portale verifica permesso e ambito evento.
- Nessun identificativo operativo o segreto deve essere inserito nei sorgenti.

I sorgenti GS completi vengono generati con node tools/prepara-codice-workspace.mjs. Per installazione e collaudo vedere [3.25.1](../docs/rilascio-3.25.1.md).
