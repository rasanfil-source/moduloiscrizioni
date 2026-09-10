# Segreteria eventi 3.26.6

Riepilogo evento semplificato: iscritti, confermati e pagamento completato secondo il piano; attesa, posto proposto e dati mancanti visibili solo quando presenti. Data di aggiornamento discreta e allineata a destra.

Gestione camere: filtro Tutte / Assegnate / Da assegnare, inizialmente Da assegnare; colonne Persona iscritta, Codice, Numero. Rimossa la proposta manuale di numerazione automatica: resta l’assegnazione alla registrazione nei soli casi già definiti dalla richiesta degli iscritti. Nessun abbinamento automatico fra iscrizioni diverse.

Cambio sistemazione: diciture più chiare per camera nuova o esistente, annotazioni facoltative inizialmente chiuse, comandi Verifica il cambio e Annulla. Conferma e registrazione della cronologia restano esplicite.

Apri foglio Google è un pulsante con icona a destra; il comando Sincronizza rimane nel foglio, da cui apre il confronto nel portale. Report pagamenti e rimborsi è distinto dal CSV dell’elenco iscritti. Testo amministrativo dei movimenti semplificato.

## Pacchetto e verifiche

Pacchetto `dist/modulo-iscrizioni-3.26.6.zip` con SHA256 separato. Aggiornare il plugin esistente senza disinstallarlo. Apps Script invariato rispetto al pacchetto 3.26.1.

230 test Node superati. Verifiche browser del riepilogo, assegnazioni camere e cambio sistemazione con dati sintetici. Controlli sintattici PHP/JavaScript, sanitizzazione e confronto del contenuto ZIP con i sorgenti.

Il caricamento su WordPress non è ancora eseguito: il controllo del browser è stato bloccato dal limite d’uso del servizio. L’audit statico generale segnala anomalie preesistenti anche in prototipi e copie temporanee; non costituisce una certificazione dell’intero progetto.
