# Rilascio 3.26.145 — apertura rapida per tutti gli eventi

Apri confronta la versione MySQL corrente con la ricevuta della proiezione completata anche negli eventi onerosi e nei fogli modificabili. Se coincidono e non ci sono repliche pendenti, apre direttamente il foglio senza una chiamata Google né una scadenza temporale. Restano verificati autorizzazioni, cancellazione evento, URL e segnalazione di foglio mancante.

Iscrizioni, pagamenti, rimborsi, servizi, camere e configurazione continuano ad accodare gli aggiornamenti in background. Se la versione cambia, la ricevuta precedente non autorizza l'apertura rapida: il percorso esistente completa la replica e verifica la proiezione. Gli errori mantengono il recupero pianificato.

La semplice apertura non scrive celle e non conferma le modifiche manuali: queste restano nel foglio fino a Sincronizza. Prima di qualsiasi aggiornamento della proiezione continua a essere applicato il confronto Apps Script che protegge le modifiche pendenti. Aprire il foglio non equivale a certificare che ogni correzione manuale sia stata importata in MySQL.

Test: 320 casi Node; regressioni PHP dell'apertura, incluso evento oneroso invariato senza chiamate remote, perdita della vecchia cache temporanea, nuove revisioni, retry, permessi, fogli sostituiti e URL non validi. GitHub Actions esegue anche le regressioni economiche MariaDB.

Questo rilascio modifica soltanto WordPress. Workspace resta alla 3.26.144, deployment 87: non occorre aggiornarlo. Installare lo ZIP plugin 3.26.145. I tempi effettivi sul sito vanno verificati dopo l'installazione; un aggiornamento ancora pendente può richiedere attesa.
