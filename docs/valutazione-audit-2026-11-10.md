# Valutazione dell'audit esterno — 10 novembre 2026

Aggiornamento: le correzioni autorizzate del flusso operativo sono incluse nel [rilascio 3.27.44](rilascio-3.27.44.md), con conservazione delle ricevute collegata alla conclusione dell'evento. Il seguito conserva l'analisi dei sorgenti 3.27.43 precedente agli interventi.

L'audit contiene difetti reali, ma la sua graduatoria va corretta: mescola il flusso operativo di MODULI AUTONOMO con percorsi storici, interpreta segnalazioni statiche UI come malfunzionamenti e duplica i rilievi fra sorgenti e minificati.

Verifica effettuata sui sorgenti locali con plugin **3.27.43**. Sono state eseguite prove con dati sintetici e adattatori locali; nessuna prova su WordPress o Google in produzione. Nessuna modifica ai sorgenti operativi, alle versioni o agli ZIP. Le modifiche locali preesistenti sono state conservate.

| Segnalazione | Valutazione | Priorità proposta |
| --- | --- | --- |
| 24 pulsanti senza azione | Non confermata come difetto attivo; audit datato e duplicazione sorgente/minificato; gestori presenti nei controlli esaminati | Non alta; rigenerare il controllo |
| 2 textarea senza `resize:none` | Un solo rilievo duplicato; conflitto CSS attuale, senza prova di blocco funzionale | Bassa |
| Etichette correnti sovrascritte dagli snapshot | Confermata anche nella proiezione diretta | Media |
| Subtotali d'ordine sulla prima persona annullata | Confermata con cronologia attiva; distinguere subtotali d'ordine e quote personali | Media |
| Importi assenti nei report senza persona 1 | Riproducibile nella funzione storica, esplicitamente coperto dai test attuali; non prova un difetto nei rapporti WordPress | Fuori dal flusso attuale |
| Archivio email oltre la griglia | Mancata espansione confermata nel codice e riprodotta con adattatore che applica il limite di griglia | Alta |
| Archivio email mai ripulito nel foglio | Confermata l'assenza di pulizia di Consegne; la retention va definita senza eliminare automaticamente gli esiti incerti | Media |
| Pagamenti non protetto dopo un'interruzione | Confermata l'assenza di riparazione su scheda preesistente | Media |
| Pagamenti non ordinato globalmente per data | Confermata; WordPress ordina per prenotazione e poi per data | Bassa |
| Nascondere l'ultima scheda visibile | Confermato il caso limite con Dati operativi già nascosto | Media |
| Navigazione con solo ordine | Eccezione riprodotta, ma il percorso tramite Segreteria.html è storico | Fuori dal flusso attuale |
| Errori omessi dalla verifica multipla | Confermata nel percorso operativo | Media |
| Lettura prima del ripristino del giornale | Ordine delle chiamate confermato nel percorso storico; il percorso diretto ripristina correttamente prima di leggere | Fuori dal flusso attuale |
| Email confrontate distinguendo maiuscole/minuscole | Incoerenza reale; conseguenza operativa non dimostrata | Bassa |
| Offset `inizio + 3` nei raggruppamenti | Incompatibile con il layout attuale, ma funzione senza chiamanti nei sorgenti | Pulizia del codice |
| Helper di condivisione e outcome WARNING | Funzione senza chiamanti; outcome non ammesso confermato | Pulizia del codice |
| File ignorati presenti localmente | Non è un difetto: risultano ignorati e non tracciati | Nessun intervento necessario |

## Difetti del flusso operativo

### Archivio ricevute email

In `workspace-apps-script/src/Email.gs:28`, `assicuraSpazioRicevuteEmail_` accoda le ricevute senza controllare `getMaxRows()` e senza aggiungere righe. La prova con 1.500 ricevute recenti e una scheda di 1.000 righe richiede il range dalla riga 2 alla 1.501: un adattatore che applica il limite genera l'errore prima della cancellazione delle proprietà. Le ricevute restano conservate, ma l'archiviazione non può completarsi.

Il percorso di consegna esegue pulizia e archiviazione **prima** della ricerca di una ricevuta già accettata (`Email.gs:92`). Il problema può quindi bloccare sia nuovi invii sia richieste ripetute che avrebbero potuto restituire un esito già noto. Il numero di ricevute necessario non è fisso: dipende anche dalle altre proprietà e dalla loro dimensione. I 300.000 byte sono una soglia prudenziale interna, non la quota dichiarata del servizio.

La correzione prioritaria consiste nell'estendere la griglia prima della scrittura, mantenendo l'ordine attuale: copia durevole, flush, eliminazione delle proprietà. La distinzione tra numero di righe della griglia e ultima riga occupata è documentata da [Google, classe Sheet](https://developers.google.com/apps-script/reference/spreadsheet/sheet).

`pulisciRicevuteEmail_` elimina dalle proprietà soltanto gli ACCEPTED più vecchi di 90 giorni; non pulisce il foglio Consegne. Serve una politica di manutenzione coerente, preservando gli stati SENDING il cui esito è incerto. L'audit individua correttamente la crescita, ma la sola espansione della griglia e la retention sono due interventi distinti.

### Domande personalizzate

In `DomandeEvento.gs:43`, l'evento fittizio vuoto impedisce alle definizioni correnti di prevalere su quelle storiche. La riproduzione passa attraverso `generaVistaDaProiezioneDiretta_`, caricando le funzioni effettive: la colonna parte da «Etichetta corrente» e finisce con «Etichetta storica».

La causa è corretta. L'effetto dimostrato riguarda le intestazioni, non la perdita delle risposte: priorità media. Passare l'evento corrente è coerente con il payload WordPress, che valorizza `domande_json` con i campi correnti in `class-mi-event-projection.php:138`; gli snapshot devono continuare a consentire la visualizzazione delle domande storiche non più configurate.

### Importi nelle righe con annullati

In `ProiezioneDiretta.gs:58–69`, la prima persona incontrata consuma l'unica esposizione dei subtotali d'ordine, anche se annullata e inclusa per la cronologia.

Prova: prenotazione con due persone, prima CANCELLED e seconda ACTIVE, versato in contanti complessivo 50 €. Il foglio restituisce `paid_cash = 50` sulla prima e stringa vuota sulla seconda. Con quote personali note, entrambe conservano correttamente i propri `total = 100` e `paid = 25`: l'audit non deve far intendere che spariscano anche questi importi. Gli importi d'ordine usati come ripiego hanno invece lo stesso limite della prima riga.

È un problema di collocazione e leggibilità della proiezione; i dati canonici e i movimenti in MySQL non vengono cancellati. Una correzione dovrebbe preferire una persona attiva per i dati d'ordine, con una regola esplicita quando tutte le persone sono annullate. Non deve ripartire arbitrariamente fra persone i subtotali per metodo.

### Scheda Pagamenti

In `ProiezioneDiretta.gs:104–117`, protezione e blocco dell'intestazione dipendono da `created`. Se la scheda esiste dopo una scrittura interrotta, il tentativo successivo può completare i dati senza applicare la protezione. La prova su scheda preesistente rileva zero chiamate a `proteggiProiezione_`. Il difetto non concede da solo permessi di modifica a chi possiede soltanto accesso di lettura.

L'ordine cronologico globale manca davvero: `class-mi-event-projection.php:54` legge i pagamenti per prenotazione e poi per data, e li ricompone per prenotazione. La prova con 10 novembre seguito da 1 novembre conserva quell'ordine. È un problema di presentazione, distinto dalla protezione.

In `ProiezioneDiretta.gs:99`, nascondere Gestione evento fallisce se è l'unica scheda visibile. Riprodotto con un adattatore che vieta di nascondere l'ultima scheda. Occorre che Dati operativi sia stato nascosto in precedenza: non è il normale stato iniziale del documento.

### Verifica multipla dei fogli

In `FogliOperativi.gs:194`, un risultato `{ok:false,error:'SHEET_UNAVAILABLE'}` produce una risposta complessiva `{ok:true,stati:[]}`. La segnalazione è fondata: WordPress non riceve l'anomalia del singolo evento.

La correzione deve riguardare anche `class-mi-portal.php:69–74`: il consumatore interpreta `empty($stato['esiste'])` come documento mancante. Aggiungere semplicemente gli errori a `stati` provocherebbe una classificazione errata. Serve uno stato esplicito di indisponibilità, separato dall'assenza accertata, o un elenco errori distinto gestito dal chiamante. Un errore Drive non dimostra da solo che il file sia stato eliminato definitivamente.

## Rilievi storici o da ridimensionare

I percorsi `generaElencoOperativoConLock_`, `generaVistaOperativaEvento_` e `aggiornaFoglioOperativoEventoConLock_` accedono alle schede centrali tramite `ottieniSchedaObbligatoria_`. In modalità autonoma, `ottieniFoglioDiLavoroAssociato_` solleva intenzionalmente `CENTRAL_WORKBOOK_RETIRED` (`Config.gs:72`). La Web App attuale usa la proiezione diretta; non espone i vecchi report come percorso operativo.

L'omissione degli importi quando la persona 1 è esclusa è riprodotta nell'helper storico, ma è anche l'esito atteso da `tests/report-order-economics.test.mjs:14–23`. Non basta a dimostrare un problema nei rapporti partecipanti WordPress. Analogamente, l'eccezione di `apriGestioneWeb` con solo ordine è reale, ma non giustifica ripristinare Segreteria.html o il database centrale.

La lettura prima del ripristino del giornale è reale nel vecchio aggiornamento. Se l'esecuzione raggiunge `scriviProiezioneEvento_`, quest'ultima ripristina comunque il giornale prima del proprio confronto. Il punto delicato è la decisione anticipata di saltare l'aggiornamento; non è stata dimostrata una corruzione nel flusso attuale. `proiettaEventoDaWordPress_` ripristina prima di leggere (`ProiezioneDiretta.gs:131–134`).

`raggruppaColonneFoglioOperativo_` usa l'offset precedente, ma non ha chiamanti nei sorgenti correnti; il layout incrementale può inoltre conservare l'ordine delle colonne deciso dall'operatore. Un futuro riuso dovrebbe considerare i metadati delle colonne, non limitarsi a cambiare una costante. Anche `condividiFoglioSoltantoConGestore_` non ha chiamanti; WARNING viene effettivamente convertito in ERROR da `Core.gs:41`.

Normalizzare le email in `proteggiProiezione_` è ragionevole, ma non è dimostrata una perdita di accesso dell'esecutore: la [documentazione Google della protezione](https://developers.google.com/apps-script/reference/spreadsheet/protection) precisa che proprietario e utente corrente non possono essere rimossi. La differenza di maiuscole, il tentativo di rimozione e l'effetto del servizio vanno distinti.

## Attendibilità del controllo UI

Il JSON contiene realmente 26 segnalazioni, ma sono **12 rilievi sui pulsanti e uno sulla textarea, duplicati nei minificati**. Non costituiscono 26 difetti indipendenti. Il file locale ha ultima modifica 24 settembre 2026; manca una revisione sorgente verificabile e i riferimenti di riga non coincidono con il codice attuale.

Nei controlli correnti esaminati le azioni esistono: cancellazione ricerca (`portal-management.js:700`), annullamento assegnazioni (`:417`), cambio modalità camere (`:436`), conferma e annullamento cambio alloggio (`:467`, `:470`), inventario e scambio (`:489`, `:494`), navigazione e chiusura scheda (`portal.js:1137–1141`). I pulsanti di salvataggio pertinenti sono submit. L'assenza di un handler inline nel template non dimostra un pulsante inerte.

Per la textarea esiste invece un'incoerenza CSS: `.mi-management textarea` dichiara prima `resize:none;min-height:120px` e poi `resize:vertical` in `portal-management.css:3,5`. Non emerge dai documenti di prodotto verificati un requisito che trasformi da solo la ridimensionabilità verticale in un bug bloccante. Prima di assegnare severità serve un controllo aggiornato della resa e della cascata nel contesto della pagina.

`git check-ignore` conferma che `premium-audit.json` e `[0-9]+` sono ignorati; `git ls-files` non li elenca. Un file locale ignorato può legittimamente restare nel workspace. Non risultano, per questa sola presenza, difetti di repository o necessità di cancellazione.

## Verifiche e ordine suggerito

La riproduzione locale è in `.tmp/valutazione-audit-2026-11-10.mjs` e termina senza errori. Usa le funzioni reali e adattatori per i servizi; per griglia email e ultima scheda visibile applica esplicitamente i vincoli che gli adattatori dei test esistenti non simulano. Sono passati anche tutti i **14 test** dei tre file `email-audit.test.mjs`, `report-order-economics.test.mjs` e `sheet-navigation.test.mjs`. Il loro esito positivo non smentisce le anomalie: i mock email non impongono la capienza, la navigazione non esercita quel ramo in modalità autonoma e il vecchio report verifica proprio l'omissione contestata.

Ordine proposto: griglia email; protezione ripristinabile di Pagamenti; etichette correnti e collocazione dei subtotali; verifica multipla con adeguamento del consumatore WordPress; visibilità delle schede. Seguono manutenzione dell'archivio, ordinamento dei movimenti e controllo UI aggiornato. I percorsi storici e gli helper non invocati restano manutenzione separata, senza riattivare componenti dismessi.
