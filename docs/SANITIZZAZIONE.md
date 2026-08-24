# Politica di sanitizzazione del repository

Il repository pubblico contiene soltanto codice, documentazione tecnica e fixture sintetiche. Le fonti operative reali possono essere analizzate localmente, ma non vengono copiate, rinominate o trasformate in file pubblicabili.

## Contenuti vietati

- dati personali di iscritti, referenti, partecipanti, operatori o viaggiatori;
- indirizzi di casa, date di nascita, numeri di documento e dati sanitari reali;
- fogli Excel, CSV, TSV, esportazioni, backup o log di produzione;
- IBAN, BIC, link di pagamento e identificativi merchant operativi;
- email e telefoni reali, destinatari interni e alias organizzativi non pubblici;
- ID di spreadsheet, deployment Apps Script, client, account di servizio o webhook;
- password, token, chiavi API, chiavi private e file `.env`;
- percorsi locali, nomi utente del computer e metadati Office riconducibili agli autori.

## Fixture ammesse

Una fixture pubblicabile deve:

1. dichiarare esplicitamente `demo_only` o un marcatore equivalente;
2. usare nomi generici e non derivati per semplice sostituzione dai nominativi reali;
3. usare domini riservati come `example.invalid`;
4. usare numeri, date, importi e codici completamente sintetici;
5. non conservare la stessa combinazione di attributi di una persona reale;
6. impedire tecnicamente la pubblicazione in un ambiente reale quando rappresenta un evento completo.

## Uso di fogli reali come riferimento

L'analisi locale può ricavare soltanto:

- intestazioni generalizzate e tipi di campo;
- relazioni tra importi, caparra, saldo e rettifiche;
- regole di obbligatorietà e dipendenze;
- necessità logistiche o documentali astratte;
- volumi approssimativi espressi per fasce, non conteggi associabili a un evento identificato.

Non si pubblicano righe, estratti, screenshot, nomi dei gruppi, nomi dei file originali o metadati del workbook.

## Controllo prima del push

Il comando `tools/check-sanitization.ps1` verifica estensioni vietate e indicatori comuni di PII o segreti. Il controllo è preventivo e non sostituisce la revisione umana.

Checklist minima:

- nessun dato reale nei diff e nella cronologia del commit;
- nessun nuovo file tabellare o archivio;
- URL limitati a documentazione pubblica esplicitamente approvata o domini di esempio;
- fixture marcate come demo e non pubblicabili;
- nessun segreto nei file, nei nomi dei file o nei messaggi di commit;
- revisione delle immagini e dei metadati dei documenti binari prima di un eventuale inserimento.

Se un segreto o dato personale viene pubblicato accidentalmente, non basta cancellarlo con un nuovo commit: occorre revocare il segreto, rimuovere il dato dalla cronologia Git e valutare gli obblighi di notifica applicabili.
