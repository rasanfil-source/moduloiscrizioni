# Contratto operativo del portale

Questo documento descrive il comportamento del portale Segreteria eventi. I nomi dei comandi e degli stati riportati qui corrispondono a quelli mostrati agli operatori.

## Autorità dei dati e accesso

MySQL in WordPress è il registro autorevole per prenotazioni, partecipanti, importi, movimenti e assegnazioni. Google Sheets riceve una proiezione per il lavoro sugli eventi. Le celle operative consentite sono evidenziate; il comando «Sincronizza» confronta i valori locali con il registro e richiede la conferma esplicita delle modifiche.

WordPress verifica sul server l’account, il ruolo e l’ambito di gruppo o evento per ogni operazione. La visibilità di un comando non sostituisce il controllo dei permessi. Il progetto Apps Script destinato all’uso operativo è MODULI AUTONOMO.

La consultazione del saldo tramite il collegamento previsto resta accessibile senza login WordPress. Non aggiungere un passaggio di autenticazione per la sola visualizzazione del saldo; i controlli di ruolo riguardano le operazioni gestionali.

Le operazioni che interessano più righe vengono applicate in transazione: l’intero gruppo di modifiche è confermato insieme. Gli invii ripetuti con la stessa identità di richiesta non duplicano l’operazione.

## Navigazione e ricerca

La navigazione presenta solo le sezioni consentite al ruolo. Su mobile il menu raccoglie gli stessi collegamenti, compreso «Esci». La selezione del periodo e dell’evento aggiorna il contesto della gestione.

Gestione iscrizioni mostra una riga per persona. La prenotazione comune resta consultabile dalla scheda e il suo codice identifica il contesto nelle ricerche e nelle comunicazioni. Ricerca, filtri, paginazione, stampa ed esportazione condividono la stessa selezione di risultati.

L’ordinamento predefinito usa la data di iscrizione più recente. Se l’operatore seleziona un altro criterio, il portale conserva la scelta. La ricerca e i filtri non espongono nomi personali nei parametri dell’indirizzo web.

## Iscrizioni e lista d’attesa

Una prenotazione può contenere una o più persone. Gli importi della prenotazione vengono sommati una volta sola nei riepiloghi; le quote attribuite ai singoli partecipanti restano visibili nella scheda.

Gli stati distinguono iscrizione confermata, pagamento atteso, lista d’attesa, posto proposto e annullamento. Una conferma non certifica la presenza all’evento. L’iscrizione in lista d’attesa non assegna un posto e non richiede il pagamento durante l’attesa.

Quando si libera un posto, il sistema può formulare una proposta temporanea secondo disponibilità e richiesta dell’evento. Il destinatario accetta o rifiuta dal collegamento personale entro la scadenza mostrata; l’accettazione porta alla conferma o alle istruzioni di pagamento previste.

Annullare una partecipazione libera il posto, ma non registra automaticamente storni, rettifiche o rimborsi. I campi aggiuntivi e gli obblighi di risposta dipendono dalla configurazione dell’evento e dalla finalità indicata.

## Servizi, importi e pagamenti

La quota è distinta dal denaro ricevuto. Il registro dei movimenti conserva incassi, storni e rimborsi come operazioni separate. Una rettifica del dovuto registra il nuovo importo e la motivazione; il rimborso registra denaro effettivamente restituito.

Gli importi sono gestiti in centesimi. Il totale è composto da quote e servizi, al netto degli storni e dei crediti. Gli incassi e i crediti di partecipanti diversi restano separati: il credito di una persona non salda il debito di un’altra.

Pagamenti mostra la persona trovata dalla ricerca e consente di attribuire ogni movimento ai partecipanti interessati. Il server calcola il totale da registrare e richiede la conferma del ricevimento. Le caparre e il saldo sono distinti; «Totale» versa il residuo complessivo. Un importo previsto non rappresenta un incasso.

Le registrazioni storiche prive di attribuzione non vengono ripartite automaticamente. Le rettifiche di quote comuni sono registrate nella prenotazione e mantengono la loro motivazione.

## Camere e presenze

L’assegnazione a una camera è distinta dal servizio di alloggio richiesto. Il sistema verifica capienza e tipologia prima di salvare uno scambio. Codici S e M identificano persone singole; DM e DS identificano camere a due posti; T identifica camere a tre posti. I progressivi già assegnati vengono conservati.

Le assegnazioni automatiche raggruppano, entro la capienza della camera, le persone attive della stessa prenotazione che hanno scelto la stessa sistemazione: una coppia che chiede una doppia resta insieme, sia con letti uniti sia separati, e tre persone che chiedono una tripla restano insieme. Una camera occupata non viene completata con persone di un’altra prenotazione.

Un gruppo che riempie la camera viene assegnato a una camera vuota compatibile; se non ce n’è una che possa contenere tutto il gruppo, il sistema apre una camera nuova per quel gruppo anche quando un’altra camera dello stesso tipo ha posti liberi insufficienti. Per esempio, una coppia della stessa prenotazione apre una nuova doppia se l’unica doppia incompleta ha un solo posto libero.

Una persona sola che ha scelto doppia o tripla resta «da assegnare» se esiste una camera di quel tipo con posti liberi; non viene inserita nella camera di un’altra prenotazione. Quando tutte le camere compatibili sono piene, o non ne esiste ancora una, il sistema apre una camera del tipo scelto e vi assegna la persona. I codici già assegnati vengono conservati e il salvataggio delle assegnazioni è esplicito e atomico.

Il cambio della sistemazione mostra l’anteprima delle differenze di camera e quota. La conferma aggiorna servizio, camera e dovuto insieme; non registra incassi o rimborsi. Le altre variazioni economiche usano le funzioni dedicate.

La presenza viene registrata dopo la verifica all’evento, mai dedotta dall’iscrizione. Il rapporto annuale collega persone fra eventi solo dopo il controllo dell’identità; nomi uguali o contatti familiari condivisi non bastano.

## Comunicazioni

Le modalità «Anteprima», «Prova» e «Operativo» distinguono preparazione, invio alla casella di prova e invio ai destinatari reali. Prima di accodare una comunicazione, l’operatore controlla modalità, destinatari e contenuto.

WordPress conserva la coda e l’esito delle richieste. Quando configurato, Apps Script invia le email con l’account della distribuzione. La chiave della comunicazione evita duplicazioni in caso di richieste ripetute. «Accettato» indica che il servizio email ha preso in carico il messaggio, non certifica la lettura o la consegna nella casella.

Le comunicazioni di pagamento usano la scadenza della singola prenotazione e il fuso orario del sito. Le informazioni di contatto e risposta seguono la configurazione del gruppo e dell’evento.

## Applicazione installabile e accessibilità

L’applicazione installabile offre lo stesso portale web, con login, permessi e servizi online. La consultazione e i salvataggi richiedono una connessione; i dati delle prenotazioni non vengono conservati per l’uso offline.

Select e date picker usano i controlli nativi del browser. Dialog e avvisi sono leggibili da tastiera e tecnologie assistive; Escape chiude le conferme compatibili e restituisce il fuoco al comando che le ha aperte. Le azioni non si distinguono soltanto per colore.

## Responsabilità dei componenti

| Area | Componente responsabile | Fonte autorevole |
|---|---|---|
| Accesso e ambito | `MI_Access` | Account WordPress e assegnazioni |
| Importi e movimenti | `MI_Payment_Ledger` | Tabelle pagamenti MySQL |
| Iscrizioni e disponibilità | `MI_Registration_Service` | Prenotazioni MySQL |
| Variazioni operative | `MI_Management_Service` | Stato gestionale MySQL |
| Interfaccia portale | `MI_Portal_Management` | Servizi applicativi WordPress |
| Proiezione Google | Apps Script MODULI AUTONOMO | Dati WordPress confermati |

## Scadenze e pagamenti di gruppo

  Una rettifica economica non crea una scadenza se la prenotazione non aveva un termine comune. Se il termine esiste ancora nel futuro, resta valido; se è trascorso o troppo vicino, la rettifica concede almeno il tempo configurato. L’offerta della lista d’attesa dà un tempo limitato per decidere: scade al primo tra il termine configurato e la scadenza comune dell’evento. Se la scadenza comune è già trascorsa, non si inviano nuove offerte. Quando un’offerta viene accettata e manca un termine comune ancora valido, l’accettazione assegna il termine di pagamento previsto per quella lista.

Alla scadenza di una prenotazione di gruppo, i versamenti attribuiti vengono confrontati con il dovuto di ogni partecipante. Decadono soltanto le persone non coperte; quelle che hanno versato quanto richiesto conservano il posto. I pagamenti delle persone decadute restano registrati come crediti da restituire e non generano rimborsi automatici. Se quote o versamenti non sono attribuibili con certezza, il sistema non fa decadere nessuno e richiede una verifica manuale.
