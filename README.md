# Modulo iscrizioni multi-attività

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni attività può avere logo, colori e contatti propri; ogni evento può applicare override controllati.

## Stato

La versione `0.3.4` è installata, attiva e verificata sul sito. La `0.4.0` è pronta per il collaudo e aggiunge per evento i quattro casi economici: sola iscrizione, prezzo informativo, versamento completo oppure caparra e saldo. La percentuale della caparra e le fonti ammesse tra bonifico, carta e contante sono validate e conservate nella configurazione privata. Non vengono memorizzati IBAN o collegamenti carta, non esiste alcuna riscossione e la coda email resta `PREVIEW`.

Il backend Google Workspace in `workspace-apps-script/` è installato come progetto `MODULI` sullo spreadsheet riservato `DB_MODULI`: crea le schede operative, convalida i pagamenti manuali, conserva audit e outbox in anteprima e accetta soltanto richieste WordPress firmate. Il controllo `PING` e una registrazione sintetica end-to-end sono stati verificati, compreso il replay idempotente senza duplicati. L'evento di prova è tornato in bozza e nessuna email è stata inviata. Il repository resta sanitizzato e non contiene ID del foglio, URL di distribuzione, segreti, codici di collaudo o destinatari reali.

- [Progetto funzionale e tecnico](PROGETTO.md)
- [Decisioni della Fase A](docs/DECISIONI_FASE_A.md)
- [Schema dati](docs/SCHEMA_DATI.md)
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
