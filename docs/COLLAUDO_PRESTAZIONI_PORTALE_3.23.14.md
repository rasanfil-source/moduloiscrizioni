# Collaudo prestazioni portale 3.23.14

## Punto di partenza

Il 6 settembre 2026 il portale autenticato ha impiegato circa 2,4–2,5 secondi per completare ciascuna navigazione tradizionale fra le viste principali. La misura è stata rilevata nel browser in-app sulla sessione reale; il tempo comprende rete, bootstrap WordPress, query, generazione HTML e caricamento della pagina.

| Vista | Tempo osservato | Elementi DOM | Form |
|---|---:|---:|---:|
| Gestisci eventi | 2.419 ms | 224 | 6 |
| Crea evento | 2.372 ms | 339 | 1 |
| Iscrizioni | 2.495 ms | 118 | 1 |
| Comunicazioni | 2.440 ms | 64 | 2 |
| Gruppi | 2.448 ms | 283 | 9 |
| Operatori | 2.444 ms | 114 | 2 |

La sostanziale uniformità dei tempi indica che il costo dominante è la richiesta completa a WordPress, più che la quantità di HTML della singola vista.

## Interventi

- Le schede degli eventi pubblicati si caricano come frammenti autenticati dentro la pagina già aperta. Il collegamento tradizionale resta disponibile come fallback.
- Il frammento viene richiesto in anticipo al passaggio del puntatore o al focus da tastiera e conservato nella cache della pagina.
- Cronologia, pulsante Indietro, stato espanso, focus e indicatore di caricamento restano coerenti.
- I due conteggi SQL ripetuti della scheda evento sono riuniti in una sola aggregazione; nella pagina completa vengono riusati i conteggi già calcolati per le tessere.
- La vista Gruppi usa una sola query aggregata per contare gli eventi collegati, eliminando una query per gruppo.
- Tessere lunghe di gruppi e operatori usano il rendering differito del browser; i vincoli responsive evitano overflow di testo e controlli.

## Verifiche automatiche

- `node --check wordpress-plugin/modulo-iscrizioni/assets/portal.js`
- `node --test workspace-apps-script/tests/*.test.mjs wordpress-plugin/tests/*.test.mjs`: 208 test superati.
- `pwsh.exe -NoLogo -NoProfile -File .\tools\check-sanitization.ps1`: superato.
- Audit Premium UI sulla cartella del plugin: nessuna proprietà canonica irrisolta; le tre segnalazioni riguardano i pulsanti del popup prenotazione creati come stringa HTML e collegati subito dopo tramite listener, quindi sono falsi positivi dell’analisi statica.

Il confronto dopo la distribuzione deve misurare soprattutto il tempo percepito tra selezione della tessera e comparsa della scheda, perché questo percorso non comporta più una navigazione completa.
