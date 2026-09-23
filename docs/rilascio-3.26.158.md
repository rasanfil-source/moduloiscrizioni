# Rilascio 3.26.158 — recupero del progressivo nei fogli precedenti

Include tutte le modifiche della [3.26.157](rilascio-3.26.157.md) e risolve il blocco che può verificarsi durante il passaggio al nuovo progressivo del foglio evento.

## Recupero controllato

Nel vecchio tracciato la prima colonna conteneva il numero interno del partecipante. Se in quella colonna è stato inserito manualmente il progressivo generale `1…N`, la lettura può segnalare contemporaneamente righe senza prenotazione riconosciuta e righe rimosse.

Prima della conversione al nuovo layout, GAS riconosce e ripara automaticamente questo caso soltanto quando sono vere tutte le condizioni seguenti:

- il foglio usa ancora il vecchio tracciato e la prima colonna è ancora l’identificatore tecnico;
- il numero e l’ordine delle righe coincidono con la base protetta;
- i codici di prenotazione e tutti gli altri valori coincidono con la base protetta;
- la prima colonna contiene esattamente il progressivo completo `1, 2, 3…N`.

Se una sola condizione non è soddisfatta, il foglio non viene modificato e resta disponibile la normale segnalazione del conflitto. Quando il recupero riesce, gli identificatori interni vengono ripristinati dalla base protetta e il foglio viene ricostruito nel formato corrente: `N.` resta visibile, mentre prenotazione e numero interno sono collocati in fondo e nascosti.

Il recupero non crea, annulla o modifica iscrizioni in WordPress. Il progressivo è un dato locale di presentazione e non viene inviato al registro centrale.

## Comando nel portale

Quando l’evento dispone di un foglio, il portale ne controlla in background lo stato dopo il caricamento del riepilogo. **Sincronizza** appare accanto ad **Apri** soltanto se vengono rilevate modifiche o conflitti pendenti; resta nascosto quando il foglio è allineato. La regola vale anche per i fogli in sola lettura e consente di confermare le modifiche senza ricorrere a un indirizzo interno.

## Distribuzione

Il progetto GAS **MODULI AUTONOMO 3.26.149** è stato aggiornato con `Codice-Workspace-Progetto-3.26.158.gs` e pubblicato come deployment **11**. ID, URL, proprietà e trigger sono rimasti invariati.

Il plugin WordPress 3.26.158 contiene lo stesso comportamento applicativo della 3.26.157 e allinea soltanto il numero di rilascio e la documentazione. Il recupero diventa operativo con il deployment GAS.

## Verifica

Una prova dedicata verifica il recupero del progressivo completo e conferma che il foglio resti intatto se un altro dato diverge dalla base o se la numerazione non è il progressivo completo `1…N`. La suite completa ha superato 350 test.

Il recupero è stato inoltre verificato sull’evento interessato: il foglio si apre nel nuovo formato, la colonna `N.` contiene `1…9` e il controllo successivo dichiara che non esistono modifiche da sincronizzare.
