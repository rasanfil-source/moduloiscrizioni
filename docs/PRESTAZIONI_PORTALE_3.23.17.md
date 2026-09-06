# Prestazioni portale 3.23.17

Base: 3.23.16.

- Cache delle schede solo in memoria: durata 30 secondi, massimo 12 elementi per tipo, invalidazione quando la pagina viene nascosta. Nessun localStorage/sessionStorage.
- Prefetch su intenzione di apertura dopo 120 ms; uno solo in corso, disabilitato con risparmio dati e reti 2G. Il clic condivide la richiesta gia avviata.
- Richieste superate interrotte; risposte tardive non riaprono schede chiuse. Timeout di rete 15 secondi con collegamento ordinario come fallback.
- Parsing HTML una sola volta per risposta; riapertura mediante clone DOM.
- Posizionamento del pannello: lettura delle sole tessere successive della riga, anziche di tutta la griglia.
- Query principale degli articoli della home omessa solo per il portale autonomo; query operative e controlli di accesso conservati.
- Copertina delle tessere in formato medium quando disponibile, con fallback alla copertina esistente.

Verifica: 215 test Node (plugin e Workspace), controllo sintassi JavaScript e git diff --check. Collaudo locale con dati fittizi: apertura evento e riapertura con stesso contatore di richieste server. PHP non disponibile nel PATH: lint PHP e collaudo WordPress delle modifiche server ancora necessari. Nessuna misura prima/dopo sul sito di produzione, nessuna percentuale di accelerazione dichiarata. Pacchetto preparato per installazione; non installato automaticamente.
