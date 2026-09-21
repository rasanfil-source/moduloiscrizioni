# Inventario delle dipendenze da DB_MODULI

Inventario del codice locale al 21 settembre 2026. La presenza del codice storico nel pacchetto non prova che il deployment installato sia stato aggiornato. `DB_MODULI` rimane disponibile per il rollback fino al collaudo finale.

| Ingresso | Stato nel progetto autonomo | Fonte dati |
| --- | --- | --- |
| Web App: `PROIETTA_EVENTO` | Diretto | Istantanea MySQL firmata; foglio evento e proprietà dello script |
| Web App: email, verifica/organizzazione fogli, lettura/conferma modifiche, eliminazione | Diretto; le rotte interessate richiedono `direct_projection` | MySQL tramite WordPress, foglio evento, Drive, proprietà dello script |
| Web App: pagamenti e gestione legacy | Rifiutato con `USE_MYSQL_*` | Portale WordPress |
| Web App: vecchie operazioni foglio e registrazione centrale | Nessuna rotta di dispatch | Non disponibili dal nuovo endpoint |
| Menu `onOpen` di `Setup.gs` | Disattivato in `MI_STANDALONE_MODE` | Storicamente `DB_MODULI` |
| `sincronizzaFogliEventi`, `attivaSincronizzazioneFogliEventi` | Non installare il vecchio trigger; l'accesso al workbook centrale fallisce nel progetto autonomo | Storicamente `DB_MODULI` |
| Segreteria, gruppi, modelli report, audit e registro fogli storici | Funzioni ancora presenti nel sorgente condiviso ma il lettore centrale è bloccato nel pacchetto autonomo | Storicamente `DB_MODULI`; funzioni da ritirare o migrare |
| Cron WordPress `avviaCronWordPress` | Conservato | Endpoint WordPress, nessuna lettura del workbook centrale |

Il pacchetto `Codice-Workspace-Progetto-<versione>.gs` antepone `MI_STANDALONE_MODE=true`; `ottieniFoglioDiLavoroAssociato_()` rifiuta ogni accesso prima di leggere proprietà o Google Sheets. Il pacchetto storico senza `-Progetto-` conserva il menu e non deve essere usato per la nuova Web App. Il nuovo progetto va creato come progetto Apps Script **autonomo**, non copiando i trigger del progetto vincolato.

La risposta firmata `STATO_SCHEMA` dichiara `direct_projection`, `standalone`, `projection_pull` e `central_workbook`. WordPress considera superata la verifica solo se i primi tre sono veri e l'ultimo è falso; le rotte dirette rifiutano il progetto storico con `USE_STANDALONE_PROJECT`.

Prima del cambio produzione: controllare i trigger installati in entrambi i progetti, confermare che nel nuovo progetto sia attivo solo il cron WordPress necessario, verificare che `MI_SPREADSHEET_ID` non sia configurato nel nuovo progetto, provare tutte le rotte dirette con eventi fittizi e accertare nei log che nessuna funzione legga `DB_MODULI`. Le funzioni legacy rimaste nel sorgente non vanno considerate migrate solo perché il loro ingresso è bloccato.
