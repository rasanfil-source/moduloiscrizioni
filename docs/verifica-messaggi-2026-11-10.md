# Verifica Messaggi e segnalazioni — 10 novembre 2026

Analisi dei sorgenti locali 3.27.41, prima della correzione del riepilogo permanente distribuita nella 3.27.42. Prove con dati sintetici e servizi simulati: nessuna email reale, accesso agli iscritti o installazione sul sito.

## Collegamento personale nel promemoria saldo

Confermato il percorso completo nei sorgenti:
- accoda_comunicazione_operativa seleziona MI_Portal::balance_url per BALANCE_REMINDER, utilizzando ID, codice prenotazione ed email del destinatario.
- balance_url produce mi_status=balance con evento, ordine e token personale. Una prova con le classi effettive ha verificato che public_status accetta il token generato e respinge quello alterato.
- Il modello conserva status_url; componi_html aggiunge il pulsante «Controlla stato e saldo» e componi_testo aggiunge lo stesso URL. Entrambi verificati eseguendo le funzioni effettive.
- Il router verifica il token e apre MI_Public_Balance con i partecipanti attivi precompilati. Il frontend li carica automaticamente e offre riepilogo e modalità previste dalla configurazione dell'evento.
- Nella prova anonimizzata richiesta da Messaggi il collegamento reale è volutamente assente. Verificato che il campione non include status_url.

Il funzionamento della copia installata e la ricezione effettiva richiedono una prova sul sito: non verificati durante questo intervento.

## Esito delle segnalazioni

| N. | Esito sui sorgenti attuali |
| --- | --- |
| 1 | La condizione che produceva «Non applicabile» con saldo positivo è già corretta: valori_ordine usa due > 0 oppure balance > 0. Riprodotto con importo iniziale zero e saldo 100 euro: scadenza e causale sono presenti. Resta l'uso della caparra individuale come importo iniziale per FULL_PAYMENT; quando la posizione individuale è nota, il promemoria caparra viene escluso perché non esiste caparra, anziché generare normalmente un'email da 0 euro. Il promemoria saldo usa il residuo corretto. |
| 2 | QR/barcode azzerati confermato, ma il selettore senza effetto è già stato rimosso dalla 3.27.41. Rimane il QR pubblico d'iscrizione. Non riattivata la funzione email. |
| 3 | Parzialmente confermata: conferme di lista d'attesa e offerte di posto ereditano ancora attivo. Riprodotto il caso lista d'attesa con modello disabilitato. Comunicazioni operative, annullamenti istituzionali e aggiornamenti prenotazione sono già autonomi dal flag; verificati anche dai test esistenti. |
| 4 | Confermata nel codice la comparazione diretta con il titolo memorizzato in begin. Titoli con entità possono non corrispondere al testo digitato. Non effettuata un'eliminazione reale. |
| 6 | Confermata la ricerca di sequenze di 13–19 cifre con Luhn, anche all'interno di riferimenti più lunghi. Possibili falsi positivi. La probabilità indicata è una stima statistica, non una misura sui riferimenti reali del sito. |
| 7 | Già affrontata: prima della crescita eccessiva, assicuraSpazioRicevuteEmail_ trasferisce le ricevute in un foglio tecnico e le rimuove dalle proprietà solo dopo flush. I test su archiviazione, guasti e replay passano. |
| 8 | Da distinguere: il singolo spazio in PRIMARY KEY non dimostra il difetto nei WordPress attuali, il parser normalizza gli spazi. Confermato invece il rischio condizionale per un type_actor storico con colonne diverse: manca una migrazione esplicita dell'indice e la revisione è registrata solo se le colonne coincidono. Non ispezionato lo schema in produzione. |
| 9 | Parzialmente confermata: ripara_istantanea_codifica decodifica già il corpo, ma componi_testo reinserisce oggetto e titolo evento non decodificati. Riprodotta un'entità nel prefisso del testo semplice istituzionale; non corretto affermare che tutto il corpo resti necessariamente codificato. |
| 10 | Già corretta: elencaGruppi usa normalizzaAssetEsistenteGruppo_, con try/catch che ignora l'asset errato e restituisce un avviso per riga. Passano le regressioni su gruppi con URL non HTTPS. |
| 11 | Collisione dei link salvati già corretta: get_page_by_path viene controllato solo per slug non ancora registrati nel gruppo. Resta una chiamata get_option per gli URL compatibili a un segmento; non equivale necessariamente a una query SQL per ogni richiesta, perché intervengono le cache WordPress. |
| 12 | Confermato nel codice: advance trasforma WP_Error mi_workspace_busy in errore generico prima di gestire il risultato busy; il worker applica il backoff ordinario. WORKSPACE_BUSY non ha una traduzione dedicata nel client. |
| 13 | Mancano i due hook cron indicati in deactivate; avviaCronWordPress rifiuta anche il 301 perché non segue redirect. La licenza QR contiene già il testo MIT completo, quindi quella parte non è confermata. |
| 14 | Da verificare su Google Sheets: il codice antepone l'apostrofo e scrive celle formattate come testo, ma i simulatori locali non dimostrano come Sheets visualizzi il prefisso. Nessuna rimozione del neutralizzatore basata soltanto sull'ipotesi sul formato. |

Per il punto 8 è stato consultato il [sorgente ufficiale di dbDelta](https://developer.wordpress.org/reference/functions/dbdelta/): il parser accetta uno o più caratteri di spazio e normalizza la definizione dell'indice. Il comportamento effettivo dello schema installato resta da controllare separatamente.

Questa verifica non modifica automaticamente le segnalazioni residue. La 3.27.42 interviene soltanto sulla persistenza del riepilogo, come richiesto successivamente.

## Riepilogo permanente

Il portale recuperava sempre mi_email_last_job dell'operatore; nessuna scadenza era prevista. A fine polling il testo rimaneva in pagina e riappariva a ogni accesso. La 3.27.42 nasconde il riepilogo dopo 30 minuti dalla prima rilevazione della conclusione, sia in una pagina aperta sia al successivo caricamento. Per i lavori storici il conteggio parte dalla prima visualizzazione dopo l'aggiornamento.

La scadenza non cancella email o errori e non reinvia i sette messaggi falliti. I 120 messaggi risultano accettati da Google; il dato non dimostra l'arrivo nella posta in entrata. La quota residua 1302 non spiega, da sola, il motivo dei sette fallimenti. Il testo «Contenuto email non valido o inattivo» è il fallback applicativo quando il tentativo restituisce false; per conoscere il caso concreto servono gli snapshot e gli esiti di quelle righe.

Verifica standard completata: 450 test Node, 7 regressioni sugli asset generati, sintassi PHP, suite applicativa PHP compresa la nuova regressione sulla scadenza, sanitizzazione. Nessuna installazione in produzione.

## Chiusura dei rilievi comprovati — 3.27.43

La successiva richiesta dell'utente autorizza la correzione e la pubblicazione. La 3.27.43 corregge i residui dei punti 1, 3, 4, 6, 8 (indice storico), 9, 12 e 13 (hook cron e redirect). Include la scadenza del riepilogo della 3.27.42.

Le correzioni già presenti dei punti 2, 7, 10 e 11 e della licenza MIT sono conservate. Il punto 14 resta una verifica sul campo, senza modifica basata su un'ipotesi. Le [note 3.27.43](rilascio-3.27.43.md) descrivono interventi, prove e installazione.
