# Backend Workspace

Il rilascio installato va verificato separatamente. Il codice locale contiene un percorso di proiezione diretta ancora da distribuire e collaudare: usare il [piano di dismissione](../docs/piano-dismissione-db-moduli.md) prima di cambiare il deployment o archiviare `DB_MODULI`.

MySQL WordPress è il registro autorevole. I fogli evento sono proiezioni con celle operative modificabili: Sincronizza apre il confronto e la conferma nel portale. La nuova Web App legge la proiezione firmata da WordPress e non richiede il workbook centrale per le operazioni dirette.

- `configuraCartellaDiLavoro()`, il menu del workbook e `sincronizzaFogliEventi()` sono percorsi storici del progetto vincolato a `DB_MODULI`. Non sono parte della nuova installazione autonoma.
- Il nuovo percorso `PROIETTA_EVENTO` materializza la vista firmata nel foglio evento; i retry sono accorpati per evento da WordPress.
- Oltre 1,8 MB compressi, la Web App preleva l'istantanea da WordPress con il comando HMAC `GET_EVENT_PROJECTION`: configurare `MI_WORDPRESS_COMMAND_URL` nel progetto autonomo e distribuire entrambe le estremità prima di usare eventi grandi.
- attivaCronWordPress() crea una sola volta l'avvio del cron WordPress ogni cinque minuti, separato dalla proiezione. Serve sull'hosting corrente, dove WP-Cron tramite visite è disabilitato.
- Pagamenti, correzioni e camere si salvano in MySQL. La replica firmata conserva identificativi e revisioni; il portale verifica permesso e ambito evento.
- Nessun identificativo operativo o segreto deve essere inserito nei sorgenti.

Gli elenchi operativi, i report personalizzati e la ricerca nella vecchia sidebar leggono ancora la copia del workbook centrale e non devono essere considerati aggiornati. La loro migrazione o rimozione è un criterio di completamento del piano. Per importi e stati usare il portale WordPress.

L'azione firmata `SCHEDA_GESTIONE_PORTALE` è ritirata e risponde `USE_MYSQL_MANAGEMENT`, come la corrispondente azione di modifica. Il portale corrente usa la gestione MySQL; il menu Sheets «Configura elenco operativo» è un percorso distinto e resta disponibile con l'avviso sopra.

I sorgenti GS completi vengono generati con `node tools/prepara-codice-workspace.mjs`. Il file `Codice-Workspace-Progetto-<versione>.gs` abilita `MI_STANDALONE_MODE`: disattiva il menu del workbook e fa fallire ogni accesso a `DB_MODULI`; può essere caricato in un progetto Apps Script autonomo. Il file senza `-Progetto-` resta il pacchetto storico e non va usato per il nuovo deployment. Prima del rilascio occorre verificare il manifest, l'account esecutore, le proprietà private, la Web App e i test end-to-end. Non eliminare il progetto vincolato finché il rollback non è pronto.
