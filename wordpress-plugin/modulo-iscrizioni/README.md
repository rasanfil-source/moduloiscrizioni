# Modulo Iscrizioni

Plugin WordPress per creare e pubblicare moduli di iscrizione e gestire le attività della segreteria.

## Funzioni

- Gruppi ed eventi con identità visiva, date, capienza e condizioni di iscrizione.
- Moduli configurabili con tipologie, servizi e domande per partecipante.
- Gestione di prenotazioni, partecipanti, presenze, sistemazioni e lista d'attesa.
- Registro di incassi, rimborsi e saldi conservato in MySQL.
- Comunicazioni email con modelli configurabili, anteprima e coda di invio.
- Notifiche push per le nuove iscrizioni, attivabili per dispositivo e caratterizzate dal logo del gruppo.
- Collegamenti pubblici per iscriversi e consultare prenotazione e saldo.
- Più link permanenti per gruppo, uno per categoria di eventi, con passaggio automatico al prossimo evento pubblicato e pagina di attesa personalizzabile.
- Foglio operativo Workspace associato a ogni evento.
- Accessi al portale regolati da ruoli, gruppi e autorizzazioni sull'evento.

## Architettura

WordPress e MySQL gestiscono i dati e le operazioni. Il progetto Apps Script **MODULI AUTONOMO** riceve richieste firmate, prepara la vista Google Sheets del singolo evento e consegna al portale le modifiche confermate. Il foglio è una vista operativa collegata al registro MySQL.

I pagamenti si registrano manualmente nel portale. Il plugin non riceve coordinate bancarie né numeri completi delle carte. Le comunicazioni email possono essere configurate in anteprima o in modalità operativa.

## Installazione e configurazione

Il plugin richiede WordPress 6.4 o successivo e PHP 7.4 o successivo.

1. Installa e attiva la cartella `modulo-iscrizioni` dall'area plugin di WordPress.
2. Configura i gruppi e le autorizzazioni operative dal portale **Modulo Iscrizioni**.
3. Configura il collegamento firmato al progetto Apps Script MODULI AUTONOMO.
4. Crea l'evento, seleziona i dati richiesti e pubblica il modulo.
5. Per incorporare il modulo in una pagina, usa lo shortcode `[modulo_iscrizioni event="ID"]` con l'identificativo dell'evento.

Le impostazioni dell'evento includono la modalità delle quote, i servizi disponibili, la capienza, le domande e le comunicazioni. I dettagli economici e le autorizzazioni vengono verificati dal server.

## Link permanenti

In creazione o configurazione del gruppo, apri **Link permanenti per le iscrizioni** e aggiungi le categorie (per esempio Catechesi e Gite) con indirizzi distinti. Ogni categoria può avere titolo, messaggio e immagine di attesa. Il collegamento si copia dalla stessa sezione.

Nella creazione o modifica completa dell'evento, scegli **Categoria / link permanente** dopo il gruppo. Il link apre il prossimo evento pubblicato della categoria, mantenendolo disponibile per tutta la giornata di inizio. La data usata è quella pubblicata. Il modulo dell'evento conserva la gestione attuale delle iscrizioni, comprese quelle non ancora aperte. Quando non ci sono prossimi eventi pubblici, appare la pagina di attesa.

Gli indirizzi salvati sono riservati e possono essere disattivati e riattivati. Un eventuale vecchio redirect del server sullo stesso percorso deve essere rimosso dopo la configurazione: il plugin gestisce il percorso tramite WordPress e non modifica il `.htaccess`. Le normali regole WordPress devono restare attive. Eventuali cache esterne devono escludere questi percorsi.
