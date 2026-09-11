# Portale web per la gestione delle iscrizioni

## Obiettivo

Il servizio di gestione deve essere una pagina web pubblicamente raggiungibile, con una breve presentazione iniziale e contenuti operativi visibili soltanto dopo autenticazione.

Il portale non coincide con `wp-admin`. Riusa però l'autenticazione e la sessione sicura di WordPress:

- un amministratore già autenticato entra senza un secondo accesso;
- il Gestore iscrizioni usa il proprio account e amministra tutto il servizio;
- il Gestore gruppo opera soltanto nei gruppi assegnati;
- il Gestore evento opera soltanto nei singoli eventi ricevuti.

## Credenziali e ruoli

Le password non vengono mai salvate nei metadati dell'evento e non vengono mai mostrate dopo la creazione. Gli operatori sono utenti WordPress limitati, autenticati con le funzioni native di WordPress; nel database resta soltanto l'hash della password.

| Ruolo | Ambito |
| --- | --- |
| Amministratore | Tutte le funzioni del portale e le normali funzioni WordPress |
| Gestore iscrizioni | Tutte le funzioni del modulo, compresi gruppi e operatori, senza amministrare il resto del sito |
| Gestore gruppo | Pieni poteri sugli eventi dei gruppi assegnati; può creare nuovi eventi in quei gruppi |
| Gestore evento | Pieni poteri sui singoli eventi in corso assegnati; non può creare eventi |

L'amministratore e il Gestore iscrizioni possono creare e sospendere gli operatori del modulo. La schermata mostra i gruppi soltanto per il Gestore gruppo e gli eventi correnti soltanto per il Gestore evento. Le assegnazioni conservano ID WordPress, mai nomi o password. Un esempio come `Francesco:26` è troppo debole e deve essere rifiutato.

## Pagina iniziale autenticata

Dopo l'accesso compaiono:

1. il pulsante `Crea nuovo evento`, per amministratore, Gestore iscrizioni e Gestore gruppo;
2. gli eventi in corso o in bozza accessibili all'utente;
3. le dieci iscrizioni più recenti comprese nello stesso ambito.

Ogni evento mostra il titolo in evidenza e, in carattere più piccolo, data e ora di inizio, posti prenotati e capienza nel formato `n / nnn`, stato `Bozza` oppure `Attivo` e termine delle iscrizioni.

## Elenco e dettaglio delle iscrizioni

Ogni partecipante occupa una riga distinta, anche quando appartiene a una prenotazione multipla. La riga mostra numero progressivo, data dell'iscrizione, nome, cognome ed email disponibile. Facendo clic su qualunque partecipante si apre l'intera prenotazione comune.

Il dettaglio presenta il riepilogo dell'ordine e una scheda per ciascun partecipante. Mostra esclusivamente campi e opzioni configurati per l'evento e realmente forniti: contatti, data di nascita, servizi, pullman, alloggio, colazione, assicurazione, pranzo, prezzi e campi personalizzati. Replica, revisioni, consensi e audit restano in un pannello tecnico riservato all'amministratore.

## Annullamento

L'annullamento opera sul singolo partecipante:

- ogni partecipante attivo dispone di `Annulla partecipazione`;
- l'azione richiede conferma e produce un evento di audit;
- capienza e contatori della tipologia vengono liberati una sola volta;
- se vengono annullati tutti i partecipanti, l'ordine passa ad annullato;
- la modifica viene accodata per la replica a Sheets;
- eventuali rimborsi restano operazioni separate.

## Collegamento nell'email

L'email include un collegamento di gestione per ciascun partecipante. Il collegamento usa una credenziale casuale a 256 bit: nella scheda del partecipante viene conservato soltanto l'hash e, dopo l'annullamento, anche l'hash viene eliminato. L'identificativo numerico presente nell'URL non autorizza da solo alcuna operazione. La copia immutabile dell'email in coda contiene necessariamente il collegamento destinato al partecipante e segue la politica di conservazione della coda email.

L'apertura mostra una pagina di conferma; l'annullamento avviene soltanto con un secondo comando esplicito. La pagina imposta `noindex` e `no-referrer` per evitare indicizzazione e propagazione del collegamento. L'operazione libera il singolo posto ma non genera automaticamente rimborsi o rettifiche economiche, che restano operazioni esplicite di segreteria.

## Vincoli di sicurezza

- HTTPS valido è obbligatorio prima di usare il portale o distribuire collegamenti email.
- Ogni query e azione verifica sul server l'ambito dell'utente.
- Login e modifiche sono protetti contro CSRF, enumerazione e tentativi ripetuti.
- Il portale non richiede mai foto o scansioni dei documenti.

## Consultazione del referente

La pagina pubblica Stato e saldo dell’evento riprende il modello Cammino: ricerca per cognome, scelta del nome in caso di omonimia, schede delle persone, servizi confermati bloccati e transfer modificabili. Non richiede account, codice prenotazione o email per la ricerca. L’email viene proposta per il riepilogo. Caparra e saldo sono calcolati usando pagamenti e rimborsi registrati. Le modifiche passano da anteprima, conferma e salvataggio atomico con controllo versione; restano privati note e dati amministrativi. I collegamenti firmati precedenti restano compatibili. La pagina applica limite ai tentativi, `noindex` e `no-referrer`.

## Rapporto con Sheets

Il portale offre la vista web e le azioni controllate. Sheets resta la sede delle operazioni quotidiane e dello stato operativo esteso. Le modifiche dal portale producono eventi append-only replicabili, evitando due archivi autorevoli indipendenti.
