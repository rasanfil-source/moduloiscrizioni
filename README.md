# Modulo iscrizioni multi-attività

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni attività può avere logo, colori e contatti propri; ogni evento può applicare override controllati.

## Stato

La versione `0.2.0` è installata e attiva sul sito. Il collaudo ha verificato homepage, pagina Santiago, pannello eventi, migrazione compatibile, preset Minimo/Standard/Viaggio, anteprima amministrativa e caricamento condizionale degli asset. La bozza 2027 conserva il profilo Minimo; attività ed evento non sono pubblicati. La versione aggiunge selezione puntuale dei dati dei partecipanti e validazione server allowlist. Email, pagamenti e sincronizzazione Workspace restano non operativi.

Il backend Google Workspace è predisposto in `workspace-apps-script/` come candidato sanitizzato: crea le schede operative, convalida i pagamenti manuali, conserva audit e outbox in anteprima e accetta soltanto richieste WordPress firmate. Non contiene l'ID del foglio, URL di deployment, segreti o destinatari reali e non è ancora installato né eseguito nell'account Workspace.

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
