# Segreteria eventi 3.26.81

Implementati i miglioramenti confermati dalla valutazione dell’audit grafico sulla 3.26.80.

- Nei link evento, entro 480 px l’indirizzo occupa una riga intera e i comandi rimangono visibili sotto. Corretta anche la larghezza effettiva dell’icona Condividi, 44 px.
- Lo storico Pagamenti ha intestazioni, bordi, righe alternate e un contenitore scorrevole da tastiera. I riferimenti lunghi non allargano più l’intera pagina; date, importi, tipi e metodi restano leggibili. La stampa conserva tutta la tabella.
- I selettori del numero camera passano da 34 a 44 px di altezza.
- Entro 480 px, frecce e chiusura della scheda prenotazione si trovano in una barra superiore. A viewport 360 px il contenuto passa da circa 261 a 328 px, con 16 px di margine per lato. La barra resta visibile durante lo scorrimento e la finestra segue l’altezza disponibile.

Il pulsante Cancella ricerca era già 44×44 px: nessuna modifica per il falso positivo dell’audit. Il layout delle frecce sopra 480 px conserva la disposizione precedente.

## Verifica

Collaudo browser sintetico in `tools/test-portal-visual-regressions-browser.cjs`: viewport da 320 a 1280 px, riferimenti fino a 120 caratteri, scorrimento locale e tastiera, stampa, dimensioni dei controlli, scheda → riepilogo camere, frecce, focus, Escape e protezione delle bozze. Screenshot e misure vengono salvati in `.tmp/portal-visual-regressions/`.

Superati 253 test Node, i due controlli PHP del saldo pubblico previsti dalla CI, lint del file principale e sanitizzazione. Verificati anche ricerca pagamenti con 65 risultati, bozza del pagamento contestuale, retry idempotente e aggiornamento del saldo.

Evidenze locali: [link a 360 px](../.tmp/portal-visual-regressions/link-360.png), [scheda a 360 px](../.tmp/portal-visual-regressions/scheda-360.png), [storico a 800 px](../.tmp/portal-visual-regressions/pagamenti-800.png).

I dati di collaudo sono sintetici; la prova non equivale a un’installazione WordPress con il tema del sito.

## Installazione

Archivio `dist/modulo-iscrizioni-3.26.81.zip`, con checksum SHA-256. Da WordPress: Plugin → Aggiungi plugin → Carica plugin, quindi sostituire la versione esistente. Questo aggiornamento non richiede modifiche Apps Script rispetto alla 3.26.80.

Il pacchetto viene preparato localmente. La creazione dello ZIP non aggiorna il sito e non pubblica il repository.
