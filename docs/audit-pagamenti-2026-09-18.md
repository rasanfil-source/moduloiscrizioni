# Verifica audit pagamenti — 18 settembre 2026

L'audit è stato confrontato con i sorgenti correnti, senza verifiche o modifiche in produzione.

- **Report Sheets:** rischio di ritardo confermato. La copia locale resta asincrona; aggiunto un avviso nella sidebar e nell'intestazione stampabile condivisa da elenchi operativi e report personalizzati. Consultare WordPress prima di richiedere pagamenti o rimborsi. Non si dichiara un orario di ultimo aggiornamento che non sarebbe verificabile.
- **SCHEDA_GESTIONE_PORTALE:** nessun chiamante trovato nel plugin corrente. Ritirata la vecchia azione firmata con `USE_MYSQL_MANAGEMENT`. Il menu degli elenchi operativi è indipendente da questa azione.
- **Annullamento individuale:** gli importi contrattuali memorizzati non vengono ridotti automaticamente. Aggiunto il promemoria nella conferma e nell'esito del pannello di gestione: verificare il dovuto, usare «Rettifica il dovuto» se necessario e registrare separatamente gli eventuali rimborsi. La copertura del pagamento e i riepiloghi operativi correnti considerano già le persone attive quando le attribuzioni individuali sono note. Ridurre automaticamente il contratto potrebbe cancellare somme ancora dovute per penali o servizi condivisi: occorrerebbe una regola esplicita.
- **Cambio servizi:** rilievo superato. `MI_Management_Service::save()` salva già le variazioni economiche prodotte da `options_plan()`, incluse le caparre individuali, nella transazione.
- **Caparre:** anche i rilievi sul mancato aumento sono parzialmente superati. `MI_Payment_People::projected_deposits()` ricalcola le caparre percentuali quando le quote sono attribuibili; è usato da gestione, cambio alloggio e saldo pubblico. Per la caparra fissa l'aggiunta di servizi non implica automaticamente un aumento. Conservate queste regole, senza introdurre nuove condizioni commerciali.

Procedura operativa dopo un annullamento: controllare la posizione individuale nel portale, concordare l'eventuale rettifica (anche per penali), salvarla con motivazione e registrare l'eventuale rimborso solo quando effettuato. Il solo annullamento non equivale a una rettifica né a un rimborso.

## Pacchetti 3.26.130

`dist/modulo-iscrizioni-3.26.130.zip` aggiorna il plugin WordPress. `dist/Workspace-3.26.130.zip` contiene i sorgenti Apps Script, il manifest e il README. Per Google aggiornare i sorgenti nel progetto esistente, inclusa `Segreteria.html`, e aggiornare la distribuzione Web App alla nuova versione. Usare i singoli `.gs` oppure il file aggregato `Codice-Workspace-3.26.130.gs`, senza duplicare le funzioni. Conservare le proprietà private e le autorizzazioni del progetto.

I pacchetti sono verificati confrontando ogni file con i sorgenti e accompagnati da SHA-256. Come nei rilasci precedenti, gli ZIP rimangono locali; GitHub conserva sorgenti, script di confezionamento e checksum. La pubblicazione Git non installa gli aggiornamenti su WordPress o Google.
