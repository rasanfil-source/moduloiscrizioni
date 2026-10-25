# Architettura del sistema

Modulo Iscrizioni collega il portale WordPress ai fogli operativi Google dedicati agli eventi. WordPress e MySQL gestiscono le operazioni e conservano i dati; il progetto Apps Script MODULI AUTONOMO prepara le viste Google e i servizi collegati.

## Componenti

- **Plugin WordPress:** configurazione di gruppi ed eventi, pubblicazione dei moduli, gestione delle iscrizioni e autorizzazioni della segreteria.
- **MySQL:** registro canonico di iscrizioni, partecipanti, disponibilità, servizi, pagamenti, camere e attività.
- **MODULI AUTONOMO:** servizio Apps Script collegato a WordPress tramite richieste firmate. Crea i fogli evento, aggiorna le proiezioni e gestisce le attività Google autorizzate.
- **Fogli evento:** viste operative dei dati MySQL. Le modifiche ammesse vengono sottoposte a confronto e conferma nel portale.

La segreteria accede al portale autenticato e ai fogli dei singoli eventi. Le richieste firmate trasportano l'identità del servizio e il contesto dell'evento; il browser non determina autonomamente le autorizzazioni.

## Iscrizioni e partecipanti

Ogni evento definisce capienza, tipologie, servizi, domande e modalità economica. Il server verifica le regole dell'evento e la disponibilità sotto lock. Le richieste idempotenti conservano un identificativo stabile, così un nuovo invio non duplica la stessa operazione.

I dati richiesti dipendono dal profilo dell'evento. La scheda di gestione permette di completare i dati operativi e mostra le informazioni ancora necessarie. Annullamento di una partecipazione e rimborso sono operazioni distinte.

## Registro economico

Il portale registra incassi e rimborsi in MySQL. I movimenti conservano identità, data, importo, tipo, metodo, riferimento e operatore. Gli importi e gli aggregati sono calcolati in centesimi; il server applica le regole economiche configurate per l'evento.

Un movimento confermato rimane nel registro. La scheda mostra il totale, quanto è stato versato e il saldo o credito risultante. Le operazioni economiche vengono associate alle persone quando l'attribuzione è disponibile.

## Fogli evento e report

Ogni foglio è una proiezione dei dati MySQL, ricostruibile dal registro canonico. La segreteria può modificare le celle operative previste; il portale presenta i valori proposti prima di applicarli ai dati.

Il processo di scrittura conserva una traccia persistente dell'aggiornamento, così può completare il lavoro dopo un'interruzione senza rendere operative righe parziali. Gli aggiornamenti periodici mantengono il contesto di elaborazione e le modifiche ancora da confermare.

Il portale offre elenchi filtrabili, riepiloghi economici e report configurabili per l'evento. La selezione dei dati e l'ordinamento sono applicati in modo coerente nelle viste, nelle esportazioni e nella stampa.

## Email

WordPress compone e conserva i messaggi nella coda. Il servizio email registra l'intento prima dell'invio e rende visibili alla segreteria gli esiti che richiedono una decisione. La modalità di anteprima permette di controllare le comunicazioni prima dell'invio operativo.

I gestori possono attivare le notifiche Web Push sul singolo dispositivo dal portale. Gli avvisi riguardano gli eventi accessibili al loro account e mostrano il nome del gruppo e il suo logo, senza dati personali dell'iscritto.

## Configurazione tecnica

Il plugin richiede WordPress 6.4 o successivo e PHP 7.4 o successivo. Il progetto Apps Script usa proprietà private per l'indirizzo del servizio e la chiave condivisa. La distribuzione collega la Web App al sito WordPress e autorizza i servizi Google richiesti.

I sorgenti Apps Script sono in [`workspace-apps-script/src`](workspace-apps-script/src); le istruzioni di configurazione sono nel [README di MODULI AUTONOMO](workspace-apps-script/README.md). Le opzioni del plugin sono descritte nel [README WordPress](wordpress-plugin/modulo-iscrizioni/README.md).

## Protezione dei dati

Il plugin non conserva coordinate bancarie, numeri completi di carte o credenziali di provider di pagamento. I dati dimostrativi usano identità fittizie; i segreti di servizio sono conservati nelle proprietà riservate della piattaforma.
