# Modulo iscrizioni multi-evento

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni gruppo può avere logo, immagine, colori e contatti propri; ogni evento può applicare sostituzioni controllate.

## Stato

Il repository contiene **3.26.6**, con l’evoluzione gestionale della Segreteria eventi e il comando unico Cambia sistemazione: anteprima per iscrizione e aggiornamento atomico di servizi, camera e dovuto. Pacchetto verificato localmente; l’installazione sul sito è un passaggio separato: [stato e istruzioni](docs/rilascio-3.26.6.md). Ogni operatore vede soltanto gli eventi autorizzati. Gli esiti del precedente passaggio a MySQL sono conservati in [esiti e dipendenze operative](docs/PASSAGGIO_MYSQL.md).

Una bozza è una creazione interrotta: selezionandola da **Gestisci eventi** si riapre dal primo passaggio incompleto, già popolato con i dati salvati. Se la configurazione è completa, si apre direttamente **Attiva l’evento**, con gli indirizzi pronti per i pulsanti **Iscriviti** e, quando previsto, **Saldo**, oltre allo shortcode per WordPress e Divi. Workspace crea in modo idempotente un foglio operativo dedicato nella stessa cartella Drive di `DB_MODULI`, con nome `Evento ID - Titolo`, e ne restituisce il collegamento. La preparazione non pubblica l’evento e non invia email.

MySQL WordPress è il registro autorevole per iscrizioni, pagamenti, camere e dati operativi. Google riceve la replica; nel foglio evento le celle azzurre si correggono e si confermano tramite Sincronizza nel portale. Il referente può consultare conferma e saldo senza vedere note interne o altri dati personali. Email, QR e barcode sono generati localmente; la modalità email iniziale resta `ANTEPRIMA` e `OPERATIVO` richiede una prova sintetica accettata dal sistema di posta.

Il sistema non richiede fotografie o scansioni dei documenti. Se indispensabili per l'iniziativa, raccoglie soltanto dati testuali strutturati. La replica conserva i dati operativi in MySQL, necessari alla gestione centrale.

Il codice non memorizza IBAN, numeri completi di carta, link operativi, ID del foglio, URL di distribuzione, segreti o destinatari reali. L’aggiornamento del repository non equivale a un deploy: prima dell’uso occorre aggiornare il plugin, eseguire `configuraCartellaDiLavoro()` sul progetto Apps Script aggiornato e collaudare in ambiente autorizzato con sole identità fittizie.

- [Progetto funzionale e tecnico](PROGETTO.md)
- [Rilascio corrente](docs/rilascio-3.25.1.md)
- [Schema dati](docs/SCHEMA_DATI.md)
- [Allineamento tra documentazione e codice](docs/ALLINEAMENTO_CODICE_DOCUMENTAZIONE.md)
- [Guida rapida per l'operatore](docs/GUIDA_OPERATORE.md)
- [Contratto della gestione web](UX-CONTRACT.md)
- [Configurazione evento dimostrativa](schema/evento.example.json)
- [Prototipo statico](prototipo/README.md)
- [Plugin WordPress — vertical slice](wordpress-plugin/modulo-iscrizioni/README.md)
- [Backend Google Workspace](workspace-apps-script/README.md)
- [Politica di sanitizzazione](docs/SANITIZZAZIONE.md)

## Dati reali vietati

Questo repository è destinato a essere pubblico. Non devono essere aggiunti dati di iscritti o viaggiatori, fogli di lavoro reali, esportazioni, indirizzi personali, numeri di documento, coordinate bancarie, link di pagamento operativi, credenziali, ID di deployment o destinatari email reali.

Gli esempi usano esclusivamente identità fittizie, domini `example.invalid` e configurazioni marcate come non pubblicabili. Prima di ogni commit eseguire:

```powershell
pwsh.exe -NoLogo -NoProfile -File .\tools\check-sanitization.ps1
```

I fogli Excel ricevuti come riferimento restano fuori dal repository: se ne ricavano soltanto strutture e casi d'uso generalizzati.

## Licenza

La licenza di distribuzione non è ancora stata definita. Finché non viene aggiunto un file `LICENSE`, il codice non è concesso in riuso automatico a terzi.
