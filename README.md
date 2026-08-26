# Modulo iscrizioni multi-attività

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni attività può avere logo, colori e contatti propri; ogni evento può applicare override controllati.

## Stato

Il repository contiene il plugin WordPress `3.4.18` e lo schema Apps Script `1.2.4`. Il modulo conserva revisioni e snapshot immutabili, applica ACL per attività anche alle capability WordPress, gestisce capienza globale e per tipologia, opzioni per ordine o partecipante, consensi versionati, scadenza/annullamento con rilascio dei posti, pagamenti manuali auditabili e replica Workspace riconciliante. Email, QR e barcode sono generati localmente; la modalità email iniziale resta `ANTEPRIMA` e `OPERATIVO` richiede una prova sintetica accettata dal sistema di posta. L’anteprima riservata inizializza un contesto WordPress isolato e non mostra gli avvisi della toolbar o del tema.

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
