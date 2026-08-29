# Architettura operativa: WordPress e Google Sheets

## Regola generale

WordPress configura e pubblica il modulo, raccoglie l'iscrizione iniziale e la consegna a Google Sheets. Dopo la consegna, le attività della segreteria si svolgono in Sheets: modifiche, servizi, camere, pasti, trasporti, rinunce, pagamenti, rimborsi, note e integrazioni successive.

WordPress conserva soltanto quanto serve alla raccolta pubblica, alla visualizzazione dello stato, ai report e alle comunicazioni. Non deve diventare il gestionale quotidiano della segreteria.

## Documenti personali

Il sistema non chiede e non raccoglie mai fotografie, scansioni o file dei documenti di identità.

Quando un'iniziativa richiede dati del documento, sono ammessi esclusivamente campi testuali strutturati:

- tipo di documento;
- numero;
- Paese di rilascio;
- data di scadenza.

Se questi dati sono richiesti nel modulo iniziale, transitano temporaneamente da WordPress per essere consegnati a Sheets. Dopo la conferma della consegna vengono rimossi dai dati del partecipante conservati in WordPress. Se vengono acquisiti in un secondo momento, la segreteria li inserisce direttamente in Sheets.

Non aggiungere campi di caricamento file, collegamenti a servizi di upload o istruzioni che invitino a inviare documenti via email.

## Segreteria eventi unificata

La Segreteria eventi è il portale WordPress autonomo e leggero. Riunisce eventi, creazione, schede delle iscrizioni e comunicazioni. Il ruolo `Segretario iscrizioni` accede a tutti i gruppi; l’operatore accede soltanto ai gruppi assegnati mediante credenziali WordPress personali e non dispone del normale pannello amministrativo.

Google Sheets non espone una seconda segreteria concorrente: resta l’ambiente operativo dei fogli evento, delle assegnazioni e dei movimenti economici, sincronizzato in modo esplicito e auditabile con `DB_MODULI`.

## Strumenti del foglio

La creazione e pubblicazione dell'evento restano in WordPress, perché è WordPress a produrre il modulo pubblico. Il foglio non crea un secondo evento concorrente.

Dal menu **Modulo iscrizioni** del foglio la segreteria dispone di quattro percorsi:

- **Apri segreteria**: ricerca per persona o codice, filtri rapidi e scheda prenotazione in dialogo;
- **Configura elenco operativo**: scelta delle sole colonne utili per l'evento e PDF A4 stampabile;
- **Comunicazioni operative**: promemoria pre-evento e promemoria saldo affidati alla coda WordPress;
- convalida dei pagamenti inseriti nel foglio tecnico, disponibile come percorso alternativo alla scheda rapida.

Il foglio `Sistemazioni` definisce camere o soluzioni con codice, nome e capienza. La scheda mostra i posti liberi e blocca l'assegnazione oltre capienza. I campi personalizzati effettivamente raccolti diventano selezionabili nell'elenco operativo senza aggiungere colonne fisse per ogni possibile domanda.

Ogni operazione è registrata in modo append-only nel foglio `Operazioni segreteria`; `Stato operativo` presenta invece la situazione corrente. Per ogni evento può essere creato un file Google Sheets operativo separato, collegato tramite `Fogli iniziative`: contiene la vista adatta all'evento e raggruppa le colonne non essenziali. Questi file sono strumenti operativi modificabili e non sostituiscono né il modulo pubblico WordPress né `DB_MODULI`, che resta l'archivio centrale. Ogni sincronizzazione è esplicita e auditabile.

## Stato pubblico e comunicazioni

Il referente può controllare soltanto stato della prenotazione, stato del pagamento, versato e saldo. L'accesso richiede codice ordine ed email oppure il collegamento firmato inserito nella conferma; la pagina è `noindex` e non mostra partecipanti, contatti, note o richieste particolari.

Sheets non invia direttamente le comunicazioni operative ai partecipanti. Invia a WordPress una richiesta HMAC con i soli codici ammessi e i saldi calcolati dal registro autorevole. WordPress ricontrolla evento e stato delle iscrizioni e crea le righe nella propria outbox. In `ANTEPRIMA` non parte alcuna email; una bozza resta sempre in anteprima anche se la modalità globale fosse operativa.
