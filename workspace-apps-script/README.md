# Backend Google Workspace — schema 1.2.1

Progetto Google Apps Script associato a uno spreadsheet dedicato. Il repository non contiene ID del foglio, URL di deployment, destinatari reali, coordinate bancarie o segreti.

## Funzioni disponibili

- `configuraCartellaDiLavoro()` crea o aggiorna in modo idempotente le schede tecniche con etichette italiane;
- `convalidaPagamentiSelezionati()` convalida le righe selezionate in `Inserimento pagamenti`;
- `convalidaPagamentiInAttesa()` elabora tutte le righe nuove;
- `doPost()` accetta soltanto richieste firmate provenienti dal proxy WordPress;
- l'azione firmata `PING` verifica firma e anti-replay senza leggere o scrivere dati personali;
- `APPEND_REGISTRATION` riconcilia iscrizione, partecipanti, outbox e pagamenti senza duplicati;
- revisioni, snapshot, consensi, tipologie e opzioni vengono conservati in colonne esplicite;
- la risposta dichiara `complete: true` soltanto dopo aver verificato tutte le proiezioni;
- l'outbox Workspace resta normalmente nello stato `PREVIEW`; una spedizione GAS è possibile soltanto con la modalità esplicita `TEST` e sostituisce sempre il destinatario con quello conservato nelle proprietà private dello script.

## Collaudo email protetto

1. Dal menu **Modulo iscrizioni** scegliere **Configura destinatario email di test**. L'indirizzo viene salvato nella proprietà privata `MI_EMAIL_TEST_RECIPIENT`, mai nel repository o nelle celle.
2. Nel foglio `Configurazione` impostare `modalita_email` su `TEST`.
3. Scegliere **Invia coda al solo destinatario di test**. Vengono elaborate solo le righe `PREVIEW` e ogni messaggio va esclusivamente al destinatario privato.
4. Al termine riportare `modalita_email` su `ANTEPRIMA`. La spedizione operativa ai destinatari reali resta responsabilità di WordPress.

## Replica dei pagamenti

Le iscrizioni WordPress possono contenere movimenti manuali già registrati nel
foglio `Pagamenti`. La Web App li replica soltanto dopo la verifica della busta
firmata, usando una chiave origine deterministica che comprende ordine, tipo di
movimento, tipologia rata, data, importo, fonte e riferimento. In questo modo
caparra e saldo restano distinti e il replay della stessa iscrizione non crea
righe duplicate. Le date non valide vengono ignorate senza interrompere la
registrazione; la modalità resta sempre `PREVIEW`.

## Prima configurazione

1. Aprire lo spreadsheet dedicato con un account proprietario.
2. Usare **Estensioni → Apps Script** per creare un progetto associato al foglio.
3. Copiare i file di `src/` nel progetto e sostituire il manifest con `appsscript.json`.
4. Eseguire `configuraCartellaDiLavoro()` e concedere esclusivamente le autorizzazioni richieste da Sheets.
5. Nelle proprietà dello script creare `MI_SHARED_SECRET` con almeno 32 caratteri casuali. Non copiarlo nel repository o nel browser pubblico.
6. Ridistribuire la Web App mantenendo invariati URL e autorizzazioni, quindi usare “Verifica schema economico” da WordPress.
7. Mantenere lo spreadsheet privato e concedere l'accesso soltanto agli operatori autorizzati.

La Web App non deve ricevere iscrizioni finché non è stato completato prima il collaudo `PING` firmato e poi un collaudo con identità fittizie.

## Pagamenti manuali

Gli operatori modificano soltanto `Inserimento pagamenti`. Le fonti ammesse e mostrate nel foglio sono `BONIFICO`, `CARTA` e `CONTANTE`; non devono mai essere inseriti numero completo della carta, scadenza o CVV. I movimenti convalidati vengono copiati in `Pagamenti` e non vengono sovrascritti.
