# Modulo iscrizioni multi-evento

Progetto di un modulo WordPress riutilizzabile per iscrizioni a eventi, configurazione dei dati richiesti e gestione economica facoltativa tramite Google Apps Script e Google Sheets.

Il modello distingue:

- sola raccolta delle iscrizioni, senza prezzi;
- iscrizione con calcolo del prezzo, senza gestione dell'incasso;
- iscrizione con pagamento completo oppure caparra, saldo e ricalcolo auditabile.

La parrocchia è l'ente base. Ogni gruppo può avere logo, immagine, colori e contatti propri; ogni evento può applicare sostituzioni controllate.

## Stato

Versione **[3.26.179](docs/rilascio-3.26.179.md)**: filtro per stato nella ricerca cross-evento e finestra dedicata al pagamento dopo una rettifica (48 ore predefinite), indipendente dalla lista d’attesa. Include gli aggiornamenti di navigazione della 3.26.178 e le revisioni precedenti della Segreteria. Il pacchetto pubblico esclude la configurazione privata; l’installazione WordPress resta a cura dell’utente. Nessun aggiornamento Workspace/Google Apps Script necessario. I paragrafi seguenti documentano i rilasci precedenti.

È preparato localmente il plugin **[3.26.166](docs/rilascio-3.26.166.md)**: report pagamenti nel gestionale, con filtri, totali e CSV conservati; rimosso soltanto l’avviso di modalità operativa da Comunicazioni. Include la revisione grafica desktop/mobile della 3.26.165. Pacchetti WordPress in `dist`; nessun aggiornamento Google Apps Script necessario e nessuna installazione sul sito eseguita. Le informazioni seguenti conservano la cronologia precedente.

Il rilascio corrente preparato è **[3.26.158](docs/rilascio-3.26.158.md)**: recupera in modo controllato i fogli rimasti nel vecchio tracciato quando il progressivo `1…N` è stato scritto nella precedente colonna tecnica, quindi applica il nuovo layout con progressivo visibile e identificatori nascosti. Include la colonna “Stato” condizionale della 3.26.157. Il progetto GAS è aggiornato al deployment **11**; l’installazione WordPress resta a cura dell'utente. La segnalazione di errori 500 su altre pagine resta da diagnosticare con i log del server. Le informazioni seguenti conservano la cronologia delle release precedenti.

La [campagna di stabilizzazione del 22 settembre 2026](docs/campagna-stabilizzazione-2026-09-22.md) documenta correzioni su transazioni, retry concorrenti, annullamenti e notifiche, più un'ottimizzazione del catalogo servizi, incluse nella **3.26.152**. Per i test integrati su database sintetico e browser usare `tools/verify-campaign.ps1`, con MariaDB locale sulla porta 33317 e Playwright disponibile; il comando completo è nel rapporto.

Il rilascio **3.26.152** riunisce anche le correzioni degli audit su date, permessi, cache, conteggi individuali e sincronizzazione recuperabile. Pacchetti e stato aggiornato di distribuzione sono descritti nelle [note del rilascio](docs/rilascio-3.26.152.md). Lo ZIP locale conserva la configurazione privata; su GitHub viene distribuito solo lo ZIP pubblico senza tale configurazione. L'installazione WordPress resta a cura dell'utente; la pubblicazione GitHub non aggiorna il sito. Il paragrafo seguente descrive il precedente stato documentato.

Il plugin **3.26.150** e la Web App autonoma Workspace 3.26.149 sono installati. Il micro-rilascio plugin ha eliminato l’avviso PHP osservato nell’editor evento. Il 21 settembre 2026 il deployment della Web App è stato aggiornato alla **versione 3** per correggere la decompressione gzip delle proiezioni evento; il successivo collaudo funzionale da WordPress è stato completato con sole identità fittizie. Il [manuale del segretario](docs/manuale-segretario/Manuale_del_segretario.docx) descrive criteri e procedure operative. Le note sono nei rilasci [3.26.149](docs/rilascio-3.26.149.md) e [3.26.150](docs/rilascio-3.26.150.md). La checklist e il punto esatto di ripresa sono nel [piano di dismissione](docs/piano-dismissione-db-moduli.md).

Una bozza è una creazione interrotta: selezionandola da **Gestisci eventi** si riapre dal primo passaggio incompleto, già popolato con i dati salvati. Se la configurazione è completa, si apre direttamente **Attiva l’evento**, con gli indirizzi pronti per i pulsanti **Iscriviti** e, quando previsto, **Saldo**, oltre allo shortcode per WordPress e Divi. Il nuovo percorso prepara in modo idempotente un foglio operativo dedicato nella cartella Drive `EVENTI`, con nome `Evento ID - Titolo`, e ne restituisce il collegamento. La preparazione non pubblica l’evento e non invia email.

MySQL WordPress è il registro autorevole per iscrizioni, pagamenti, camere e dati operativi. Il nuovo percorso genera i fogli evento direttamente da MySQL; nel foglio evento le celle azzurre si correggono e si confermano tramite Sincronizza nel portale. Il referente può consultare conferma e saldo senza vedere note interne o altri dati personali. Email, QR e barcode sono generati localmente; la modalità email iniziale resta `ANTEPRIMA` e `OPERATIVO` richiede una prova sintetica accettata dal sistema di posta.

Il sistema non richiede fotografie o scansioni dei documenti. Se indispensabili per l'iniziativa, raccoglie soltanto dati testuali strutturati. La Web App autonoma non usa `DB_MODULI`; il workbook e il progetto storico non vanno però eliminati né disattivati prima del periodo di confronto, dei backup e del cutover dei trigger descritti nel piano.

Il codice non memorizza IBAN, numeri completi di carta, link operativi, ID del foglio, URL di distribuzione, segreti o destinatari reali. L’aggiornamento del repository non equivale a un deploy: il percorso diretto richiede un progetto Apps Script autonomo, la configurazione privata dei segreti e un collaudo in ambiente autorizzato con sole identità fittizie. Non eseguire `configuraCartellaDiLavoro()` come requisito del nuovo percorso: inizializza il vecchio workbook centrale.

- [Progetto funzionale e tecnico](PROGETTO.md)
- [Manuale del segretario](docs/manuale-segretario/Manuale_del_segretario.docx)
- [Schema dati](docs/SCHEMA_DATI.md)
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

Per eseguire in locale suite Node, asset, sanitizzazione, lint e test PHP:

```powershell
pwsh.exe -NoLogo -NoProfile -File .\tools\verify.ps1
```

La verifica completa richiede Node.js 22 o successivo e PHP 8.3 con `mbstring`. L’opzione `-SkipPhp` produce soltanto una verifica parziale e lo segnala esplicitamente.
Se PHP non è nel `PATH`, passare il suo eseguibile a `-PhpPath`: il verificatore carica `mbstring` dalla cartella `ext` adiacente quando necessario.

I fogli Excel ricevuti come riferimento restano fuori dal repository: se ne ricavano soltanto strutture e casi d'uso generalizzati.

## Licenza

La licenza di distribuzione non è ancora stata definita. Finché non viene aggiunto un file `LICENSE`, il codice non è concesso in riuso automatico a terzi.
