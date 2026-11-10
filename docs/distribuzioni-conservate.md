# Distribuzioni conservate

Aggiornamento del 10 novembre 2026. La regola permanente è conservare gli ultimi cinque rilasci, senza modificare gli archivi già assegnati e senza riutilizzare i numeri eliminati.

| Versione | Distribuzione locale | GitHub |
| --- | --- | --- |
| 3.27.43 | ZIP locale e pubblico, checksum; sorgente MODULI AUTONOMO e checksum | [Release 3.27.43](https://github.com/rasanfil-source/moduloiscrizioni/releases/tag/v3.27.43) |
| 3.27.42 | ZIP locale e pubblico, checksum | [Release 3.27.42](https://github.com/rasanfil-source/moduloiscrizioni/releases/tag/v3.27.42) |
| 3.27.41 | ZIP locale e pubblico, checksum; sorgente MODULI AUTONOMO e checksum | [Release 3.27.41](https://github.com/rasanfil-source/moduloiscrizioni/releases/tag/v3.27.41) |
| 3.27.40 | ZIP locale e pubblico, checksum | [Release 3.27.40](https://github.com/rasanfil-source/moduloiscrizioni/releases/tag/v3.27.40) |
| 3.27.39 | ZIP locale e pubblico, checksum | [Release 3.27.39](https://github.com/rasanfil-source/moduloiscrizioni/releases/tag/v3.27.39) |

Per ciascuna versione restano note e helper di confezionamento. Su GitHub si pubblicano esclusivamente ZIP pubblici e checksum; quando previsto, si aggiunge il sorgente MODULI AUTONOMO. La configurazione privata del saldo resta soltanto nella variante locale.

La 3.27.42 è pubblicata usando lo ZIP originale già consegnato, senza ricostruirlo. Il relativo snapshot sorgente è ricavato da quel pacchetto pubblico. La 3.27.43 include la correzione della 3.27.42 e i rilievi comprovati dell'audit.

## Conservazione e verifiche

La pulizia iniziale del 9 novembre aveva rimosso 684 file locali di distribuzioni precedenti, lasciando la serie 3.27.37–3.27.41. Con la 3.27.42 è stata rimossa localmente la 3.27.37; con la 3.27.43 è stata rimossa la 3.27.38. Sono eliminati ZIP, checksum, note e helper obsoleti. I dieci ZIP conservati sono stati confrontati mediante SHA-256 e non sono stati modificati.

La pubblicazione delle nuove release precede la rimozione delle release GitHub 3.27.37 e 3.27.38 e dei loro tag. Gli allegati conservati sono confrontati con quelli precedenti e con i digest dei file locali. La 3.27.43 è l'ultima release.

Il ramo operativo viene integrato nel ramo principale tramite pull request verificata. Non si riscrive la cronologia dei commit e non si esegue force push. La rimozione dall'albero corrente e dalle release non cancella automaticamente i vecchi oggetti Git.

Restano i sorgenti operativi, i test, gli audit e le decisioni funzionali. I file temporanei e i dati locali estranei alla distribuzione non fanno parte della pulizia. La 3.26.226 non viene ricostruita o riutilizzata.

La pubblicazione GitHub non installa aggiornamenti in WordPress o in Google. Per la 3.27.43 servono il nuovo ZIP WordPress e l'aggiornamento della distribuzione del solo progetto MODULI AUTONOMO con il sorgente completo 3.27.43.
