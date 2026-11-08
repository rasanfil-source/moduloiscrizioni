# Modulo Iscrizioni 3.27.17

Rilascio del 8 novembre 2026. Corregge i rilievi confermati nell’audit: gli utenti sospesi non ricevono più accesso agli eventi neppure tramite il ruolo di gestore globale; il collegamento di gruppo già esistente resta modificabile anche se una pagina WordPress ne occupa successivamente lo slug.

La sincronizzazione manuale del foglio impedisce di modificare i campi `travel_*`. L’invio di prova delle email rispetta lo stato del canale e la quota giornaliera, e segnala gli esiti incerti senza nascondere il fallimento. La pausa interattiva viene attivata solo dopo lock e controlli anti replay. L’eliminazione dell’evento rimuove anche la proprietà di scrittura associata al file.

Nei report economici filtrati, gli importi dell’ordine restano associati al primo partecipante della prenotazione e non vengono trasferiti a un’altra persona visibile. Le assegnazioni camere restano secondo la regola concordata: codice progressivo individuale per le multiple; assegnazione manuale della segreteria per gruppi eccedenti la capienza di doppie o triple.

Verifica: suite `tools/verify.ps1` completata, inclusi 421 test Node, controlli degli asset, sanitizzazione e suite PHP. Sorgente operativo MODULI AUTONOMO: `dist/Codice-Workspace-Progetto-3.27.17.gs`.

Pacchetti `modulo-iscrizioni-3.27.17.zip` e `modulo-iscrizioni-3.27.17-pubblico.zip`. La pagina saldo pubblica non è stata modificata. Nessuna installazione in produzione e nessuna modifica ai progetti Google dismessi.
