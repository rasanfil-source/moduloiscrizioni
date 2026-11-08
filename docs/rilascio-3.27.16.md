# Modulo Iscrizioni 3.27.16

Rilascio del 8 novembre 2026. Corregge il lookup dell’indirizzo email personale nella pagina saldo: sono riconosciute le chiavi storiche `participant_email` ed `email`, con ripiego sul recapito del referente. L’anteprima degli importi può essere calcolata anche se non esiste un destinatario valido; la conferma continua a richiederlo.

La caparra fissa mostrata nel modulo pubblico segue la ripartizione individuale autorevole del server, inclusa la quota dei servizi comuni. Le email eliminano solo il codice ordine interno e mantengono il testo libero `Codice: …`; le istantanee storiche senza metadati continuano a nascondere l’etichetta esplicita `Codice iscrizione`. Una ripartizione economica storica incoerente non annulla le modifiche gestionali: l’email di aggiornamento viene accodata senza importi e chiede una verifica alla segreteria. Il portale di gestione restituisce lo status HTTP dichiarato dal servizio.

In MODULI AUTONOMO, URL immagine non HTTPS già presenti nelle righe dei gruppi vengono ignorati e segnalati senza interrompere l’elenco o la sincronizzazione; gli asset validi già salvati in WordPress restano invariati. La licenza MIT del generatore QR include il testo completo e il copyright.

Verifica: suite `tools/verify.ps1`, incluse regressioni su `participant_email`, anteprima senza email, caparra individuale, email, gruppi e status HTTP. Sorgente operativo MODULI AUTONOMO: `dist/Codice-Workspace-Progetto-3.27.16.gs`.

Pacchetti `modulo-iscrizioni-3.27.16.zip` e `modulo-iscrizioni-3.27.16-pubblico.zip`. Nessuna installazione in produzione e nessuna modifica ai progetti Google dismessi.
