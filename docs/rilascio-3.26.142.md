# Rilascio 3.26.142

## Modifiche visibili

- I rapporti partecipanti e il rapporto delle presenze esportano file Excel `.xlsx`, con intestazioni D1/D2 per le domande aggiuntive, filtri e prima riga bloccata. Telefoni, zeri iniziali e risposte che iniziano con `=` restano testo; gli importi sono numerici.
- Il saldo pubblico mantiene la ricerca per cognome. L'email registrata viene restituita al browser solo mascherata, anche nelle anteprime, nelle conferme e nei retry. Lasciando il campo invariato, il server usa l'indirizzo registrato; sostituendolo con un indirizzo completo, il riepilogo viene inviato al nuovo recapito, come prima. Questa modifica non introduce una verifica dell'identità e non cambia i dati economici consultabili tramite la ricerca.

## Audit: correzioni applicate

- Rilascio del lock evento prima delle chiamate Google di replica e apertura, con riacquisizione prima della certificazione finale e controllo della revisione. Il lock delle scritture Workspace rimane separato. Le operazioni di email e cancellazione conservano la propria esclusione.
- Espansione esplicita della griglia prima delle scritture centrali di partecipanti e camere; le cancellazioni restano raggruppate per intervalli contigui.
- Formato testo prima della scrittura della proiezione; gli importi numerici, compresi quelli negativi, restano numeri.
- Memoizzazione delle schede per esecuzione e invalidazione durante la configurazione.
- Ricevuta di apertura conservata quando la proiezione è invariata.
- Richieste firmate senza seconda copia del payload; limite esplicito di 2 milioni di caratteri. Il controllo replay persistente è ripartito in proprietà piccole e conserva ogni nonce per tutta la finestra valida. La sola cache volatile suggerita dall'audit non è sufficiente contro i replay dopo un'espulsione dalla cache.
- GET_EMAIL_MODE usa un payload non vuoto, evitando la discrepanza di firma fra oggetto e array vuoti.
- La verifica multipla dei fogli non interpreta un errore di accesso come prova di cancellazione.
- Importi personali nella vista evento; gli importi disponibili solo per ordine e i subtotali per metodo non vengono ripetuti su tutte le persone.
- Annullamento evento senza notifiche individuali duplicate; contesa temporanea della coda email senza consumo dei tentativi di consegna.
- Duplicazione evento senza slug pubblico e indicatori di pubblicazione del modello.
- Ricerca persone sui valori dei contatti, anziché sui nomi delle chiavi JSON; risposte aggiuntive `0` conservate.
- Link pubblico nelle email ricavato dal percorso di iscrizione; login dalla pagina con shortcode; operatori amministratori esclusi dalle modifiche consentite ai gestori.
- Compatibilità dei moduli WooCommerce Divi e conservazione delle dipendenze del mini-carrello.
- Comunicazioni Workspace con a capo conservati; gruppi archiviati esclusi dalla ricreazione e inclusi nella verifica duplicati; nomi di gruppi e modelli protetti dalle formule.
- Intestazioni unite dei report separate prima della rigenerazione; cron WordPress compatibile con sottocartelle; messaggio dello schema e argomenti superflui di `decode()` corretti.

## Proposte valutate ma non comprese in questo rilascio

L'audit comprende anche rifacimenti architetturali: replica ed email in batch, snapshot per riferimento, letture selettive generalizzate, cache del riepilogo, indici con migrazione, pubblicazione e annullamento tramite job. Sono opportunità di miglioramento, non correzioni equivalenti da applicare senza cambiare contratti e procedure di recupero. Richiedono un intervento separato con test di migrazione, concorrenza e ripresa dopo interruzione.

Non viene rimossa la coda email centrale: il percorso di invio di prova la usa ancora. Non si eliminano indiscriminatamente i lock o codice ritenuto inutilizzato. Non si usa il solo massimo di `workspace_revision` per una cache: una modifica di una riga con revisione inferiore può lasciare quel massimo invariato. Il fingerprint prima della costruzione della vista richiede inoltre un'invalidazione completa per modifiche manuali, pagamenti, schema e camere.

Gli errori Drive di file non accessibile restano distinti dai file sicuramente nel cestino: un errore ambiguo non autorizza la ricreazione o il completamento di una cancellazione. La personalizzazione delle colonne deve restare subordinata allo schema effettivo dell'evento; la sua interazione con le viste salvate e il filtro `WAITLIST_OFFERED` richiedono ancora un intervento dedicato. Le ipotesi sul costo di Divi e le ulteriori micro-ottimizzazioni non sono presentate come problemi misurati sul sito.

## Verifica e installazione

- 317 test Node superati per WordPress e Workspace.
- Test PHP del saldo, profili operativi, servizi, apertura, presenze, wizard e gestione partecipanti.
- Prova browser su dati sintetici: paginazione e filtro su 265 persone, stampa completa, rifiuto di export con dati mutati; lettura del file Excel con openpyxl.
- Test delle repliche alternate da 2 a 20 partecipanti e di oltre 600 richieste firmate con svuotamento della cache.
- Asset compilati, controllo di sanitizzazione e verifica del contenuto ZIP contro i sorgenti.

Aggiornare il plugin caricando `modulo-iscrizioni-3.26.142.zip`. Workspace richiede nuovi sorgenti e una nuova versione della distribuzione esistente; lo schema resta 1.13.0, senza nuova inizializzazione. L'installazione WordPress è a cura del proprietario del sito.
