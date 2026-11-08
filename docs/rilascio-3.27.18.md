# Modulo Iscrizioni 3.27.18

Rilascio del 8 novembre 2026. Completa il blocco degli utenti sospesi: anche la verifica diretta del ruolo di gestore globale ora restituisce accesso negato, non soltanto i controlli di accesso agli eventi, elenchi e ambiti.

La verifica del flusso foglio/portale conferma che le colonne speciali modificabili sono instradate a gestori WordPress espliciti: `room` segue l’assegnazione camere, `transport` è ricondotta al campo `pullman`, e i campi partecipante dinamici provengono dalle definizioni dell’evento. `travel_*`, opzioni, presenze, stato e importi sono di sola lettura o esclusi dall’importazione.

Verifica: suite `tools/verify.ps1`, inclusi test di accesso sospeso, test Node, controlli degli asset, sanitizzazione e suite PHP. Sorgente operativo MODULI AUTONOMO: `dist/Codice-Workspace-Progetto-3.27.18.gs`.

Pacchetti `modulo-iscrizioni-3.27.18.zip` e `modulo-iscrizioni-3.27.18-pubblico.zip`. La pagina saldo pubblica non è stata modificata. Nessuna installazione in produzione e nessuna modifica ai progetti Google dismessi.
