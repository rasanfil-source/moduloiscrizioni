# Verifica accesso e sincronizzazione — in corso

Configurazione richiesta: lettura mediante link; scrittura soltanto agli editor già autorizzati, senza concedere accesso all'intero dominio. Le celle protette restano riservate al proprietario Apps Script. DB_MODULI non viene condiviso.

Prova reale Google del 20 settembre 2026: documento con soli dati sintetici, condivisione ANYONE_WITH_LINK / VIEW, export CSV letto da UrlFetchApp senza cookie o Authorization. Risposta 200 con il marcatore sintetico atteso. Protezione della proiezione con una sola cella anagrafica modificabile, B2, e saldo protetto. Questo controllo non dimostra ancora il percorso completo MySQL né l'accesso di un secondo editor.

Collaudo MySQL avviato sulla bozza 8103, iscrizione dimostrativa 126. Nessun dato reale modificato. Creazione tramite lo strumento Dati dimostrativi con invii temporaneamente in Anteprima; modalità Operativo ripristinata e verificata subito dopo la creazione. Non registrati incassi.

La pubblicazione della lettura anonima sui fogli evento è ancora sospesa: la revisione automatica richiede conferma esplicita dei dati personali potenzialmente esposti.

## Correzione della conferma delle modifiche (21 settembre, locale)

Il collaudo ha riprodotto un blocco: dopo Sheet → MySQL, una seconda modifica dal portale può accorpare la replica intermedia. Il precedente confronto testuale non riconosce allora la modifica già accettata e la conserva come pendente.

Le ricevute PHP includono ora workspace_revision, verificata nuovamente dopo la lettura dei partecipanti. Apps Script conserva la ricevuta nella quarta colonna della base protetta e la riconosce soltanto con una replica completa di revisione uguale o successiva. Una modifica locale intervenuta nel frattempo resta pendente rispetto al valore accettato. Le ricevute dei plugin precedenti mantengono il comportamento compatibile. L'indice delle righe evita scansioni ripetute e continua a rifiutare identità duplicate.

Verifiche locali: 329 test Node superati; sintassi PHP valida; sheet-receipts-innodb.php superato su MariaDB locale, inclusi i controlli gestione, concorrenza, rollback, ricevute e revisione. La nuova correzione non è ancora pubblicata né collaudata sul sito. I sorgenti dist 147 preparati prima di questa modifica devono essere rigenerati prima del rilascio; non costituiscono un pacchetto finale.

Prova reale Sheet → MySQL completata: nel foglio evento, cella C2, `Totuccio` modificato in `Totuccio ProvaSheet147`. Il portale ha rilevato una sola modifica `first_name`, mostrato prima/dopo e confermato «1 celle sincronizzate». Il nuovo nome è comparso nell'elenco partecipanti letto da MySQL. Accesso Google usato nel collaudo: proprietario parrocchiale, non un secondo editor. La prova non dimostra ancora i permessi di uno specifico collaboratore.

## Nuovo audit ricevuto durante il collaudo

Confermate dalla lettura del codice le chiamate ripetute a registration() in detail(), rooms() e room_types() in summary(), e la scansione delle righe per ogni ricevuta Apps Script. Da ottimizzare preservando il controllo delle righe duplicate.

Una precisazione necessaria: nel codice attuale la generazione di receipt.versions nel saldo pubblico precede COMMIT; non è fuori transazione come sostiene l'audit. Riusare bundle dopo una mutazione senza ricalcolare la versione sarebbe scorretto.

Non è sicuro attribuire automaticamente all'unico partecipante attivo un pagamento storico non attribuito: può appartenere a una persona annullata e rappresentare un suo credito. Il numero dei partecipanti attivi non dimostra la titolarità dell'incasso.

Le altre proposte richiedono verifica separata; non sono dichiarate applicate in questo intervento sui permessi.
