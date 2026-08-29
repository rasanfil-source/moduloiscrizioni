# Modulo iscrizioni multi-evento

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni gruppo può avere logo, immagine, colori e contatti propri; ogni evento può applicare sostituzioni controllate.

## Stato

Il repository contiene il plugin WordPress `3.9.1` e lo schema Apps Script `1.8.0`. Il portale WordPress autenticato è l’unica **Segreteria eventi**: riunisce gestione degli eventi, creazione, schede delle iscrizioni e comunicazioni. Il segretario generale opera su tutti i gruppi; ogni operatore usa credenziali personali WordPress e vede soltanto gli eventi dei gruppi assegnati, senza entrare nel resto di `wp-admin`.

Una bozza è una creazione interrotta: selezionandola da **Gestisci eventi** si riapre lo stesso percorso guidato, già popolato con i dati salvati. Al termine, la sezione **Produzioni dell’evento** espone il codice del pulsante iscrizioni e può richiedere a Workspace la creazione idempotente del foglio operativo dedicato. WordPress conserva l’identificativo restituito dal foglio per collegare, negli eventi con caparra e saldo, il successivo modulo di completamento del pagamento. La produzione non pubblica l’evento e non invia email.

WordPress raccoglie le iscrizioni e le consegna in modo firmato a Workspace. I fogli Google restano lo strumento operativo per pagamenti, sistemazioni, assegnazioni e viste adattive, mentre `DB_MODULI` conserva l’archivio centrale e lo storico. Il referente può consultare conferma e saldo senza vedere note interne o altri dati personali. Email, QR e barcode sono generati localmente; la modalità email iniziale resta `ANTEPRIMA` e `OPERATIVO` richiede una prova sintetica accettata dal sistema di posta.

Il sistema non richiede mai fotografie o scansioni dei documenti. Se indispensabili per l'iniziativa, raccoglie soltanto dati testuali strutturati e li rimuove da WordPress dopo la consegna confermata a Sheets. La separazione operativa e le regole sui dati personali sono descritte in [Architettura operativa: WordPress e Google Sheets](docs/ARCHITETTURA_SEGRETERIA_SHEETS.md).

Il codice non memorizza IBAN, numeri completi di carta, link operativi, ID del foglio, URL di distribuzione, segreti o destinatari reali. L’aggiornamento del repository non equivale a un deploy: prima dell’uso occorre aggiornare il plugin, eseguire `configuraCartellaDiLavoro()` sul progetto Apps Script aggiornato e collaudare in ambiente autorizzato con sole identità fittizie.

- [Progetto funzionale e tecnico](PROGETTO.md)
- [Decisioni della Fase A](docs/DECISIONI_FASE_A.md)
- [Schema dati](docs/SCHEMA_DATI.md)
- [Allineamento tra documentazione e codice](docs/ALLINEAMENTO_CODICE_DOCUMENTAZIONE.md)
- [Guida rapida per l'operatore](docs/GUIDA_OPERATORE.md)
- [Criteri di accettazione della prima vertical slice](docs/CRITERI_ACCETTAZIONE_VERTICAL_SLICE.md)
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
