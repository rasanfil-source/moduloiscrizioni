# Rilascio 3.26.152 — stabilizzazione e pacchetti verificati

Riunisce le modifiche della [3.26.151](rilascio-3.26.151.md) e la [campagna del 22 settembre](campagna-stabilizzazione-2026-09-22.md): transazioni controllate, retry concorrenti anche sull'ultimo posto, annullamento e notifiche atomici, gestione degli errori di pianificazione dopo il commit e ottimizzazione del catalogo servizi.

## Installazione

1. Aggiornare il deployment GAS esistente con il sorgente autonomo 3.26.152, preservando URL, esecutore, accessi, proprietà e trigger.
2. In WordPress caricare `modulo-iscrizioni-3.26.152.zip` e confermare la sostituzione del plugin installato. Lo ZIP locale conserva la configurazione privata già presente nella cartella del plugin.
3. Verificare dal portale connessione Workspace e sincronizzazione di un evento di prova con sole identità fittizie.

La pubblicazione GitHub non installa il plugin sul sito. Il pacchetto `modulo-iscrizioni-3.26.152-pubblico.zip` contiene esclusivamente i 79 file tracciati del plugin ed esclude la configurazione privata: per questo sito usare lo ZIP locale fornito, composto da 80 file. Non pubblicare lo ZIP locale.

`Workspace-3.26.152.zip` contiene `Codice.gs` autonomo e manifest; il sorgente singolo è disponibile anche come `Codice-Workspace-Progetto-3.26.152.gs`. Gli archivi e il sorgente autonomo sono accompagnati da SHA-256. La procedura riproducibile è `tools/package-3.26.152.ps1` e confronta ogni file degli archivi con il sorgente.

## Verifiche e rollback

Verifica della release superata: 341 test Node, 7 test asset, 22 suite PHP, lint e sanitizzazione. La campagna ha inoltre superato 11 suite PHP/InnoDB e 6 prove browser; dettagli e limiti residui nel rapporto. Il collaudo locale non equivale alla verifica funzionale sul sito dopo l'installazione.

Conservare ZIP precedente e versione GAS precedente. Dopo la prima proiezione versionata, il rollback al vecchio protocollo richiede il ripristino coordinato di plugin e GAS, come descritto nella 3.26.151. Non eliminare il workbook storico o i relativi trigger.

## Stato operativo

Pacchetti generati e verificati il 22 settembre 2026. GAS aggiornato nello stesso deployment alla **versione 7**, il 22 settembre alle 12:54 (ora visualizzata da Google), con sorgente 3.26.152 confrontato integralmente con il bundle locale dopo il salvataggio. URL, esecutore e accessi conservati; proprietà e trigger non modificati. La versione precedente per il rollback è la 6. Il nome storico del progetto, che termina in 3.26.149, non rappresenta la versione attiva.

Installazione WordPress completata dall'utente e versione 3.26.152 riscontrata negli asset serviti dal sito. Verifica autenticata successiva: collegamento firmato riuscito e capacità del deployment autonomo confermate (proiezione diretta, prelievo firmato, nessun accesso al workbook centrale). Il primo ping ha restituito `mi_workspace_unreachable`; il controllo delle capacità e il secondo ping sono riusciti. La causa del primo errore non è stata determinata.

Questi controlli verificano connessione e capacità dichiarate, non una nuova sincronizzazione completa di un evento. Nessuna iscrizione, pagamento o email di prova è stata generata durante questa verifica.
