# Modulo iscrizioni multi-attività

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni attività può avere logo, colori e contatti propri; ogni evento può applicare override controllati.

## Stato

La cartella contiene documentazione, schema dati, prototipo statico e la prima vertical slice del plugin WordPress. La versione `0.1.2` è installata e attiva sul sito: il collaudo ha verificato homepage, pagina Santiago, pannello eventi e assenza degli asset del plugin nelle pagine prive di shortcode. La versione non riscrive le regole personalizzate dei permalink e consente di salvare come bozze eventi ancora incompleti, mantenendo la validazione obbligatoria per la pubblicazione. Sono state create, ma non pubblicate, le prime bozze dell'attività Cammino di Santiago e dell'evento 2027. Le email restano in modalità `PREVIEW`; pagamenti e sincronizzazione Workspace non sono ancora operativi.

- [Progetto funzionale e tecnico](PROGETTO.md)
- [Decisioni della Fase A](docs/DECISIONI_FASE_A.md)
- [Schema dati](docs/SCHEMA_DATI.md)
- [Criteri di accettazione della prima vertical slice](docs/CRITERI_ACCETTAZIONE_VERTICAL_SLICE.md)
- [Configurazione evento dimostrativa](schema/evento.example.json)
- [Prototipo statico](prototipo/README.md)
- [Plugin WordPress — vertical slice](wordpress-plugin/modulo-iscrizioni/README.md)
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
