# Modulo Iscrizioni

Plugin WordPress per creare e pubblicare moduli di iscrizione e gestire le attività della segreteria.

## Funzioni

- Gruppi ed eventi con identità visiva, date, capienza e condizioni di iscrizione.
- Moduli configurabili con tipologie, servizi e domande per partecipante.
- Gestione di prenotazioni, partecipanti, presenze, sistemazioni e lista d'attesa.
- Registro di incassi, rimborsi e saldi conservato in MySQL.
- Comunicazioni email con modelli configurabili, anteprima e coda di invio.
- Collegamenti pubblici per iscriversi e consultare prenotazione e saldo.
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
