# Backend Workspace

Rilascio 3.26.144: schema 1.13.0, preparazione dei fogli in background e apertura rapida delle viste già verificate. Aggiornare i sorgenti e la distribuzione Web App: questo rilascio non modifica lo schema e non richiede di rieseguire la configurazione. La replica completa viene certificata nella colonna `replica_completa_revision`; le modifiche pendenti non vengono sovrascritte. Vedere [installazione e collaudo](../docs/rilascio-3.26.144.md).

La versione corrente usa MySQL WordPress come registro autorevole. I fogli evento sono proiezioni con celle operative modificabili: Sincronizza apre il confronto e la conferma nel portale. Il centrale non richiede inserimenti manuali.

- configuraCartellaDiLavoro() prepara le schede tecniche e rimuove i moduli a celle dismessi.
- apriGestioneWeb() apre il medesimo portale dal menu centrale.
- sincronizzaFogliEventi() rigenera le proiezioni con retry e cursore.
- attivaCronWordPress() crea una sola volta l'avvio del cron WordPress ogni cinque minuti, separato dalla proiezione. Serve sull'hosting corrente, dove WP-Cron tramite visite è disabilitato.
- Pagamenti, correzioni e camere si salvano in MySQL. La replica firmata conserva identificativi e revisioni; il portale verifica permesso e ambito evento.
- Nessun identificativo operativo o segreto deve essere inserito nei sorgenti.

Gli elenchi operativi, i report personalizzati e la ricerca nella sidebar leggono la copia locale sincronizzata: importi e stati possono non includere gli ultimi incassi, rimborsi o annullamenti. L'avviso compare anche nell'intestazione stampabile degli elenchi. Prima di richiedere pagamenti o rimborsi, verificare la posizione nel portale WordPress. Generare un report non forza una sincronizzazione e non certifica la freschezza dei dati.

L'azione firmata `SCHEDA_GESTIONE_PORTALE` è ritirata e risponde `USE_MYSQL_MANAGEMENT`, come la corrispondente azione di modifica. Il portale corrente usa la gestione MySQL; il menu Sheets «Configura elenco operativo» è un percorso distinto e resta disponibile con l'avviso sopra.

I sorgenti GS completi vengono generati con node tools/prepara-codice-workspace.mjs. Per installazione e collaudo vedere 3.25.1 (documento storico rimosso).
