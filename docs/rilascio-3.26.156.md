# Rilascio 3.26.156 — progressivo nel foglio evento

Include tutte le modifiche della [3.26.155](rilascio-3.26.155.md) e corregge la prima colonna del foglio operativo.

- La colonna visibile `N.` contiene un progressivo generale dell’elenco: 1, 2, 3 e così via, anche quando più prenotazioni hanno ciascuna il proprio partecipante interno numero 1.
- Una nuova riga riceve il valore della riga precedente più uno.
- La numerazione già modificata a mano viene conservata e non viene inviata a WordPress come dato anagrafico.
- I riferimenti tecnici necessari a collegare una riga alla persona corretta sono spostati in fondo e nascosti.
- Il nuovo layout viene applicato anche ai fogli esistenti al primo aggiornamento eseguito dopo il deployment.

## Installazione

Caricare `modulo-iscrizioni-3.26.156.zip` in WordPress e confermare la sostituzione. Lo ZIP locale conserva la configurazione privata e non va pubblicato; GitHub distribuisce soltanto `modulo-iscrizioni-3.26.156-pubblico.zip`.

Il progetto GAS dal nome storico **MODULI AUTONOMO 3.26.149** è stato aggiornato al sorgente 3.26.156 e al deployment **10**. URL della web app, proprietà e trigger sono rimasti invariati. `Codice-Workspace-Progetto-3.26.156.gs` e `Workspace-3.26.156.zip` restano disponibili nella release per archivio o reinstallazione.

## Verifica

La suite Workspace comprende 135 prove. Il caso specifico verifica che una numerazione manuale venga conservata e che, dopo 7, 8 e 9, una nuova iscrizione riceva 10 senza alterare l’identità tecnica usata dalla sincronizzazione.

La segnalazione separata degli errori HTTP 500 durante il salvataggio di altre pagine del sito resta non diagnosticata in assenza dei log PHP/server.
