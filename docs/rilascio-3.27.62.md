# Rilascio 3.27.62

13 novembre 2026. In Servizi e quote l'organizzatore può richiedere le ultime quattro cifre del telefono prima di aprire la pagina saldo. L'opzione è inizialmente disattivata e vale per l'intero evento. Il popup mostra il numero nascondendo solo le quattro cifre finali; usa il recapito personale valido, altrimenti quello del referente.

Dopo cinque errori la verifica resta sospesa per cinque minuti, senza contatori né avvisi sui tentativi disponibili. Il limite è lato server e usa un lock MySQL per serializzare gli aggiornamenti anche con richieste concorrenti. Nessun dato economico viene restituito prima della verifica. Calcolo e conferma richiedono un'autorizzazione firmata di trenta minuti, legata a persona, evento e numero corrente. Senza numero valido occorre contattare la segreteria. Ogni persona aggiunta verifica il proprio recapito; le schede matrimoniali non vengono caricate automaticamente negli eventi protetti.

I file PHP, JavaScript e CSS dedicati vengono caricati solo nella pagina saldo degli eventi con opzione attiva. Nessun caricamento aggiuntivo negli altri percorsi. Il recapito email del riepilogo resta liberamente selezionabile. Dettagli in `decisione-verifica-telefono-saldo-2026-11-13.md`.

Installare lo ZIP WordPress 3.27.62; poi attivare la verifica negli eventi desiderati. MODULI AUTONOMO è invariato e non richiede un nuovo deployment Google per questa modifica. Include le modifiche della 3.27.61.

Verifiche: suite locale completa, test PHP della protezione e dell'accesso al saldo, browser desktop/mobile con asset sorgenti e minificati, pausa persistente al reload, nessun download dei file opzionali a verifica disattivata. Le prove usano dati sintetici senza invii o pagamenti reali. Archivi e checksum verificati senza rigenerare pacchetti precedenti; conservati gli ultimi cinque rilasci.
