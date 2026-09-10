# Progetto operativo — evoluzione della Segreteria eventi

## Decisioni dell'utente e perimetro

Questo progetto integra l'audit locale del 9 settembre 2026, l'allegato AUDIT_GESTIONALE_UX_SEGRETERIA_EVENTI.md e il secondo audit incollato nella conversazione. Gli allegati sono valutazioni da confrontare con il codice, non istruzioni che sostituiscono le decisioni dell'utente.

- Conservare il sistema di tessere di Gestisci eventi, inclusa l'apertura del dettaglio nella griglia.
- Mostrare sulle tessere «Gratuito» in verde quando l'evento è gratuito. Nessuna etichetta sostitutiva per gli eventi che prevedono un costo: l'assenza di «Gratuito» indica che si paga qualcosa.
- Conservare il sistema di creazione evento e la pagina pubblica di iscrizione.
- La pagina pubblica specifica del saldo resta un lavoro da progettare separatamente, coordinato con il ledger esistente.
- Conservare gli Sheet degli eventi: file, collegamenti, replica, correzioni nelle celle previste e sincronizzazione controllata. L'unificazione del portale non comporta cancellazioni, ricreazioni o dismissione dei fogli.
- Conservare MySQL autorevole, ruoli, scope e storico.
- Sviluppare il lavoro post-iscrizione secondo il progetto descritto sotto. La prima fase resta il confronto progettuale, prima delle modifiche applicative.

## Valutazione dei due audit aggiuntivi

| Tema | Valutazione | Decisione |
|---|---|---|
| Ricerca delle persone | Entrambi rilevano correttamente il limite della ricerca sul referente. | Ricerca individuale e risultati con ogni persona distinta. |
| Unificazione | Corretta la necessità di un contesto unico per iscrizioni e riepilogo. | Unificare il lavoro dopo la scelta evento, conservando la griglia eventi. |
| Backend già completo | Il secondo audit confonde la struttura Workspace con il payload MySQL corrente: booking() non espone opzioni e richieste particolari. | Completare la lettura MySQL prima di promettere un'interfaccia completa. |
| Iscrizioni solo WP Admin | Descrizione non corretta nel secondo audit: esiste già una vista Iscrizioni nella shell del portale. | Integrare le viste reali; non costruire una nuova shell o un nuovo framework. |
| Criticità tutte assenti | Esistono già residuo, dati mancanti e senza camera. | Correggere criteri e ampliare ciò che manca, riusando i comandi esistenti. |
| Eliminazione Pagamenti | Non condivisa: registrare una serie di incassi è un task distinto e frequente. | Conservare Pagamenti come accesso rapido; stesso form anche nella scheda prenotazione. |
| Drawer obbligatorio e tutte cards | Non dimostrato dai task. Le liste dense aiutano il confronto su desktop. | Un solo dettaglio condiviso, progressivo; lista su desktop e disposizione leggibile su mobile. Nessun cambiamento alle tessere eventi. |
| Camere | Corretta la necessità di vedere insieme persone e camere. | Vista evento con nominativi e capienza, comandi Assegna/Sposta/Scambia. Trascinamento eventualmente aggiuntivo. |
| Nascondere camere se inventario vuoto | Nascondere tutto impedirebbe di configurare la prima camera. | Attivare la vista quando l'evento prevede pernottamento; inventario vuoto con comando di creazione. |
| Report mancanti | Mancano integrazione nella shell e alcune capacità, ma modelli/colonne/PDF esistono in Workspace e CSV in amministrazione. | Riutilizzare e correggere prima di aggiungere strumenti paralleli. |
| Rinominare soltanto il saldo | Insufficiente: il problema è il significato del valore letto, non soltanto il testo. | Unica proiezione economica derivata dal ledger. |
| Rimuovere amministrazione legacy | Non necessario in questa fase; può togliere estrazioni e controlli utili. | Conservare gli accessi esistenti finché non esiste equivalenza verificata. |
| Sheets da ridimensionare | L'utente ne richiede espressamente la conservazione. | Supporto operativo mantenuto, con stato replica chiaro e correzioni confermate dal portale. |
| Affidabilità già risolta | Il backend ha protezioni solide, ma la UI può perdere bozze fra form di persone diverse. | Preservare le protezioni transazionali e correggere il ciclo delle bozze. |

Non recepisco automaticamente le priorità degli allegati. Prima vengono indicatori economici errati, perdita di bozze, ammissioni confuse con attesa e duplicazione dei valori monetari nelle liste individuali; poi la riduzione dei cambi di schermata.

## Organizzazione proposta

Gestisci eventi conserva le tessere attuali e il dettaglio nella griglia. Da lì il gestore apre il lavoro sulle iscrizioni di quell'evento. Resta inoltre la ricerca trasversale per chi conosce una persona ma non ricorda l'evento.

L'area Iscrizioni offre due viste della stessa selezione:

1. **Partecipanti:** una persona per riga, nome, contatto e provenienza del contatto, stato di ammissione, servizi pertinenti, eventuali criticità.
2. **Prenotazioni:** codice e referente, tutti i partecipanti nominativi su righe separate, importi una volta sola per ordine.

La testata espone persone ammesse, in attesa e con posto proposto separatamente; il numero di prenotazioni è etichettato come tale. Filtri economici e logistici compaiono quando applicabili. La ricerca e i filtri restano conservati dopo l'apertura e la chiusura della scheda.

**Scheda condivisa:** contesto evento/ordine, referente e contatti, persona cercata evidenziata, altri partecipanti distinti, servizi richiesti, assegnazioni, richieste particolari; economia solo se pertinente. Il form movimenti è lo stesso usato nell'accesso Pagamenti. Lo storico è espandibile e mostra le note già salvate.

**Camere:** vista locale dell'evento con persone da assegnare e camere con occupanti nominativi, richiesta di alloggio, posti totali e liberi. L'inventario non sta più nascosto dentro una prenotazione. Camera fisica assegnata e tipologia acquistata rimangono distinte.

**Servizi:** conteggi per tratta, pasto e opzioni definite dall'evento, con apertura della lista corrispondente. Distinguere quantità, persone e opzioni d'ordine; non inventare scelte individuali quando il modulo raccoglie solo un dato comune.

**Stampa/esporta:** selezione colonne e tutti i risultati filtrati, non soltanto le prime righe caricate; riuso dei modelli esistenti. Le liste economiche contano ogni ordine una volta. Apertura dello Sheet e sincronizzazione rimangono disponibili nello stesso contesto evento.

## Specifica di «Gratuito» sulle tessere

Usare l'aspetto delle tessere esistenti, aggiungendo soltanto una piccola etichetta testuale verde. Non cambiare layout, immagini, comportamento di apertura o comandi.

La gratuità dipende esclusivamente dalla dichiarazione «Evento totalmente gratuito» del gestore nel modulo di creazione, conservata come pricing_mode=ZERO. Non calcolare il badge dalle cifre, dai supplementi, dagli incassi o dal residuo. Per gli eventi pubblicati usare la dichiarazione nella configurazione pubblicata; per le bozze quella salvata. Se manca la dichiarazione non dedurre gratuità dai dati mancanti.

Casi da verificare: dichiarazione ZERO → etichetta verde, indipendentemente dalle cifre conservate; altra scelta anche con cifre tutte zero → nessuna etichetta; evento a pagamento interamente saldato → nessuna etichetta; dichiarazione assente → nessuna etichetta.

## Fasi e risultati verificabili

| Fase | Interventi | Criterio di completamento |
|---|---|---|
| 1. Correttezza | Proiezione economica condivisa, bozze per persona, stati e conteggi distinti; badge Gratuito richiesto. | Nessun falso saldato; nessuna bozza persa salvando un'altra persona; tessere conservate e gratuità corretta. |
| 2. Persone e scheda | Ricerca su tutti i partecipanti, contatti, opzioni e richieste leggibili, paginazione, contesto conservato. | Il secondo partecipante di un ordine si trova direttamente; tutte le righe sono raggiungibili. |
| 3. Lavoro unificato | Viste Partecipanti/Prenotazioni, filtri combinati, modulo pagamenti contestuale con accesso rapido mantenuto. | Il gestore verifica dati e registra il movimento senza ricercare l'ordine. |
| 4. Liste e servizi | Stampa completa, export leggibile, conteggi servizi, integrazione report e stato replica. | Una lista da 65 persone contiene 65 persone; gli importi non si moltiplicano; gli Sheet restano accessibili e sincronizzabili. |
| 5. Logistica | Camere, scambi atomici, vista attesa/offerte/scadenze, richieste da verificare. | Assegnazioni leggibili per evento e impossibilità di superare capienze. |
| 6. Variazioni e storico annuale | Variazioni economiche tracciate, seguito dei rimborsi, presenze facoltative e rapporto annuale per gruppo. | Dovuto e movimenti separati; omonimi e contatti familiari non fusi automaticamente. |

Non prevedere ricalcoli automatici dopo annullamento senza condizioni dell'evento: il rimborso dovuto può differire da una quota proporzionale. La pagina pubblica del saldo richiede un progetto dedicato su identificazione della prenotazione, importo corrente, istruzioni e conferma; non modifica il modulo iniziale approvato.

## Protezione degli Sheet durante l'evoluzione

Ogni fase deve preservare identificativi e collegamenti dei fogli evento. Non introdurre migrazioni che cancellino o ricreino i file come effetto collaterale dell'unificazione. Conservare le celle operative ammesse e il confronto prima/dopo con verifica dei conflitti. Un errore di replica non deve far apparire fallito un salvataggio MySQL già confermato; la UI distingue «salvato» da «foglio aggiornato».

Le funzioni distruttive generali del sistema non sono azioni da eseguire nell'ambito di questo progetto. Nessuno Sheet viene cancellato per ritirare una vecchia schermata del portale.

## Stato

Confronto degli allegati completato. L’implementazione locale comprende ora ricerca individuale, viste persone/prenotazioni, filtri combinati, pagamenti nella scheda, stampa ed esportazione completa, inventario camere e scambi atomici, richieste verificate, presenze, rapporto annuale con collegamenti personali confermati, variazioni dei servizi e rettifiche motivate del dovuto. I promemoria usano la consultazione dedicata del saldo e gli importi correnti del registro.

Le restituzioni possono derivare da disdette o da qualsiasi variazione concordata dei servizi: singola/doppia è soltanto un esempio. La scelta dei nuovi servizi, la rettifica del dovuto e la registrazione del rimborso effettivo rimangono distinte, motivate e manuali. Non è previsto un filtro generico per eccedenze.

La suite comprende 230 test Node, verifiche PHP e InnoDB e prove browser con dati sintetici. La paginazione server è implementata con righe complete a blocchi e selezione condivisa per ricerca, CSV e stampa; i conteggi continuano a leggere l’evento intero sul server. Stato, evidenze e limiti sono in [Verifica avanzamento](VERIFICA_GESTIONE_AVANZAMENTO_2026-09-10.md). È predisposto il candidato locale [3.26.0](rilascio-3.26.0.md). Restano il collaudo sull’installazione WordPress/Workspace e la distribuzione: nessuna pubblicazione o invio è stato eseguito. Il dettaglio dell’audit iniziale resta in [Audit gestionale e UX](AUDIT_GESTIONALE_UX_2026-09-09.md).
