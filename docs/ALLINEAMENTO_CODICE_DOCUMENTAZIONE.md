# Allineamento tra documentazione e codice — 3.24.0

La gestione operativa è descritta in [Architettura corrente](ARCHITETTURA_SEGRETERIA_SHEETS.md) e nella [guida operatore](GUIDA_OPERATORE.md).

| Funzione | Implementazione corrente |
|---|---|
| Pagamenti e rimborsi | Un modulo web, validatore centrale e storico movimenti |
| Dati partecipanti | Correzioni web e completamento dei campi configurati |
| Camere e pullman | Assegnazione individuale; creazione/modifica camere e cancellazione di camere vuote |
| Nuove iscrizioni e annullamenti | Modulo evento e servizio WordPress esistente |
| Consultazione | Foglio evento protetto, riepilogo web filtrabile e modelli report |
| Scritture interrotte | Identificativo stabile, registro persistente e ripresa dal portale dello stesso operatore |
| Interfacce a celle e confronto modifiche locali | Ritirati |

La verifica locale comprende test del backend, controllo sintattico PHP/JavaScript e browser con API sintetiche. Deploy eseguito l'8 settembre 2026: WordPress 3.24.0 e Apps Script versione 59. Verificati sul sito connessione firmata, schema e lettura della gestione prenotazione; resta distinto il collaudo completo delle operazioni di scrittura. Lo storico fittizio incompleto non viene riallineato.

Non è implementato un editor delle opzioni economiche di una prenotazione già acquisita. Le nuove persone entrano con una nuova iscrizione; i rimborsi si registrano separatamente dall'annullamento.
