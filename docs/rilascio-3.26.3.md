# Pubblicazione 3.26.3

Pacchetto WordPress: `dist/modulo-iscrizioni-3.26.3.zip`. Sostituisce la 3.26.1 installata e il precedente ZIP locale 3.26.2. Non disinstallare il plugin.

## Installazione

1. WordPress → Plugin → Aggiungi nuovo plugin → Carica plugin.
2. Selezionare modulo-iscrizioni-3.26.3.zip, premere Installa e quindi Sostituisci quello attuale con quello caricato.
3. Verificare la versione 3.26.3 nell’elenco plugin.
4. Aprire Segreteria eventi e premere Ctrl+F5.

Apps Script MODULI non richiede aggiornamenti: Codice.gs, Segreteria.html, CollaudoIsolato.gs, appsscript.json e la distribuzione esistente restano quelli della pubblicazione 3.26.1. Nessuna eliminazione di progetti, plugin o fogli; nessuna nuova distribuzione Google necessaria.

## Contenuto

- Riepilogo in tabella con conteggi delle persone; elenco unico per persona, ricerca subito prima dell’elenco, nuova iscrizione evidente, navigazione attiva blu.
- Stato condizionale: assente negli eventi gratuiti; Tutti gli iscritti / Da saldare / Saldato per pagamento unico; Tutti gli iscritti / Nessun versamento / Caparra da completare / Caparra versata / Saldato per caparra e saldo. Default Tutti gli iscritti.
- Richieste particolari con le sole voci Tutte le prenotazioni e Richieste particolari.
- Gestione camere principale per tipo di sistemazione, raggruppamento per codice, numero modificabile anche tra iscrizioni distinte.
- Nuove iscrizioni ammesse: numeri automatici individuali S e M; codice condiviso DM/DS per iscrizioni congiunte di esattamente due persone della stessa sistemazione, T per esattamente tre. M identifica persone in camerate senza limite, non una camera a capienza limitata.
- Iscrizioni esistenti: Proponi numeri automatici prepara le assegnazioni compatibili; Salva assegnazioni le registra. Codici esistenti preservati.
- Controllo capienza, conflitti, audit, retry e salvataggio atomico. Nessun ricalcolo economico nell’assegnazione del numero.

## Verifica dopo installazione

- Evento gratuito: nessun filtro sui pagamenti. Evento a pagamento: voci Stato pertinenti.
- Evento con pernottamento: Tipo di sistemazione visibile; persone raggruppate correttamente. La consultazione non assegna numeri.
- Verificare Apri foglio Google: apre il foglio già associato.
- Collaudare nuove assegnazioni automatiche su un evento di prova, senza inviare iscrizioni fittizie a un evento reale.

## Limiti e stato

Il comando unico Cambia sistemazione con anteprima e aggiornamento contestuale di camera e dovuto non è incluso. Le funzioni esistenti Cambio servizi e Rettifica dovuto restano separate; rimborsi manuali.

Verifiche locali: test Node, test transazionali MySQL per assegnazioni e progressivi concorrenti, prove browser di camere, filtri, paginazione, CSV/stampa e mobile. L’installazione in produzione deve essere confermata dall’operatore.
