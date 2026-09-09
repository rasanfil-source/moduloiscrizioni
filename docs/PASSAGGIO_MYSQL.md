# Passaggio a MySQL

## Sistema distribuito

Il 9 settembre 2026 sono installati il plugin WordPress 3.25.1 e la Web App Apps Script versione 64. MySQL è autorevole per iscrizioni, partecipanti, pagamenti, camere e assegnazioni. Google conserva la replica e i fogli operativi degli eventi.

Pagamenti dal portale; consultazione e correzioni delle celle azzurre dal foglio Google. **Sincronizza** apre il confronto nel portale autenticato per confermare le modifiche. Le colonne economiche restano protette. Revisioni e idempotenza impediscono sovrascritture e duplicazioni; il batch applica tutto oppure nulla. La replica conserva le modifiche locali pendenti.

Servizi configurabili con colonne dinamiche, riepilogo pubblico con nominativi e costi, stati italiani e codici brevi per le nuove prenotazioni sono distribuiti.

## Esecuzione automatica

WP-Cron è disabilitato all'accesso dei visitatori. Due attivatori Google distinti ogni cinque minuti avviano WordPress e aggiornano le viste. La chiamata al cron non mantiene lock Google, perché WordPress può richiamare la Web App.

Il recupero persistente WordPress passa ogni cinque minuti. Legge fino a dieci candidati ordinati per tentativi e identificativo, interrompendo il ciclo dopo venti secondi. Una chiamata già iniziata può superare il budget. Le righe non elaborate rimangono pendenti. La pianificazione precedente oraria viene sostituita automaticamente.

## Verifiche

- 158 test Node WordPress e 64 test Workspace superati. Sintassi PHP, test PHP codici e pianificazione superati; browser sintetico verificato per salvataggio, retry, conferma e schermo stretto.
- InnoDB locale: concorrenza pagamenti, ultimo posto camera, scambio camere piene e rollback verificati nelle prove precedenti; non sono prove dirette sull'istanza Tophost.
- Evento 7342: pullman TEST-P1 → TEST-P2 nel foglio, confronto e conferma nel portale, rilettura MySQL e replica Google riusciti. La successiva lettura non propone più la modifica già sincronizzata.
- Pagamento fittizio unico di 1 euro, riferimento COLLAUDO-MYSQL-3.25: totale 1085 euro, residuo 1084 euro. Non ripetere come nuovo pagamento di collaudo.
- Nuova iscrizione fittizia CDS1, Collaudo Codicebreve: totale 320 euro, nessun pagamento. Riepilogo pubblico e dati email/cellulare nella gestione verificati visivamente.
- Attivatori Google: cron completato fino alle 13:49 e proiezione completata alle 13:47. Una precedente proiezione è indicata da Google come sconosciuta, seguita da una completata.

## Esito finale

Collaudo completato il 9 settembre 2026: **38 repliche sincronizzate, 0 pendenti**, comprese le due nuove iscrizioni inserite durante le verifiche. Recupero periodico e recupero manuale verificati. Gli ultimi tentativi riportano Nessuno come errore. I precedenti rifiuti generici Google non sono stati riprodotti stabilmente dopo i recuperi: la diagnostica amministratore conserva ora il dettaglio prioritario nei 80 caratteri disponibili.

Ogni gestore deve avere accesso al proprio foglio: le condivisioni non vengono ampliate automaticamente. Gli attivatori dipendono dall'account Google e dalle quote; ricontrollarli dopo cambi di proprietario.

Conservate le tre versioni più recenti dei pacchetti disponibili. Nessuna migrazione dello storico fittizio richiesta; cronologia Git invariata.
