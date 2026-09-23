# Rilascio 3.26.154 — email e gestione iscritti

## Modifiche richieste

- Email di nuova prenotazione alla segreteria o al gruppo: pulsanti affiancati «Apri prenotazione» e «Apri elenco iscritti», con collegamenti distinti alla prenotazione e all'evento.
- Titolo nella scheda decodificato come nell'elenco: apostrofi e lineette non mostrano più entità HTML. Causa e test documentati in [analisi della correzione](correzione-titoli-gestione-2026-09-23.md).
- «Vai a Elenco iscritti» in alto e in fondo alla scheda.
- «Stampa riepilogo prenotazione» affiancato a «Salva iscritto», con «Annulla partecipazione» a destra sugli schermi larghi; ordine verticale sui piccoli schermi. Le azioni di salvataggio e annullamento mantengono l'ambito della singola persona.
- Quando la rilevazione presenze è disponibile, «Presente» occupa il posto di «Stato»; «Stato» si sposta dopo «Gestisci». Condizioni e orario di disponibilità restano invariati.

Il pacchetto conserva anche gli interventi già presenti nei sorgenti 3.26.153 su retry, presenze, annullamenti, validazione della pubblicazione e proiezioni Workspace.

## Pacchetto e verifiche

Caricare in WordPress `dist/modulo-iscrizioni-3.26.154.zip` e confermare la sostituzione del plugin. Lo ZIP locale mantiene la configurazione privata: non pubblicarlo. Il pacchetto separato `modulo-iscrizioni-3.26.154-pubblico.zip` la esclude. Gli archivi sono confrontati file per file con i sorgenti e accompagnati da SHA-256.

Verifiche superate: 345 test Node, 7 test asset, suite PHP e lint del verificatore generale; 11 suite PHP/InnoDB e 6 suite browser della campagna; controllo visuale automatico 320–1280 px con navigazione, focus, stampa e protezione delle bozze. Superata anche la prova browser della colonna presenze. I selettori del vecchio test visuale sono stati adeguati alla scheda attuale: due pulsanti di ritorno, identificativo nel riepilogo prenotazione e larghezza del contenitore camere anziché della tabella interna con padding.

## GAS e stato di distribuzione

Il 23 settembre 2026 il progetto **MODULI AUTONOMO 3.26.149** è stato aggiornato nello stesso deployment alla **versione 9**, con bundle 3.26.154. Sorgente salvato confrontato integralmente con quello locale; rispetto al precedente sorgente 3.26.153 cambia solo il commento di versione. URL, esecutore, accessi, proprietà e trigger conservati. La precedente versione 8 resta disponibile per rollback.

Installazione WordPress a cura dell'utente. La pubblicazione GAS è confermata dall'interfaccia Google; il collaudo del collegamento e della sincronizzazione dal sito dopo il caricamento del nuovo ZIP resta da eseguire.
