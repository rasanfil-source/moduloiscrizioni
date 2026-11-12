# Rilascio 3.27.51

11 novembre 2026.

Le domande a scelta singola conservano le opzioni e l'obbligatorietà dopo il salvataggio e la rilettura. Le chiavi delle domande già registrate vengono mantenute anche quando brevi o superiori a 80 caratteri, nei moduli, nel portale e nei fogli evento.

La conferma della sincronizzazione Google conserva gli avvisi che richiedono una verifica manuale dei pagamenti. Chi accetta un posto dalla lista d'attesa e completa successivamente l'attesa di un passaggio riceve la finestra di pagamento prevista anche negli eventi senza un termine generale.

Le modifiche importate da Sheets rispettano l'ammissione all'evento prima di assegnare una camera. La sincronizzazione supporta più di 500 celle nella stessa transazione. Le conferme Google e le richieste ripetute conservano le modifiche già accettate e rispettano i cambiamenti successivi.

La ricerca trova i telefoni anche digitando il numero senza separatori. Il registro pagamenti accetta i separatori delle migliaia previsti dal parser dei prezzi, con il limite dei movimenti già previsto. Il modulo verifica anche il limite individuale dei rimborsi per questi formati.

Le comunicazioni sulla perdita di un passaggio facoltativo descrivono l'iscrizione secondo le sue condizioni di pagamento. Le proposte riportano il termine effettivo per rispondere. La finestra di 24 ore decorre dalla creazione della proposta. Chi sceglie il viaggio autonomo vede le colonne di luogo e orario del vecchio passaggio vuote.

I collegamenti di consultazione inviati si aprono senza autenticazione, come richiesto. I fogli evento mantengono la lettura tramite link.

## Verifiche

- Suite locale completa con 462 test Node, 7 test sugli asset generati e i controlli PHP previsti da `tools/verify.ps1`.
- Integrazione MariaDB con assegnazioni concorrenti, versamenti concorrenti, scadenze, avvisi di pagamento, ricerca telefonica e sincronizzazione di 502 celle, inclusi conflitti e ripetizione della richiesta.
- Prove Google con 501 ricevute e conservazione delle modifiche successive.
- Prove in Edge sui pagamenti e sulla sincronizzazione di 501 celle, con ripetizione della conferma e verifica sia degli asset sorgenti sia di quelli minificati.

## Installazione

Il rilascio comprende il plugin WordPress e il sorgente `Codice-Workspace-Progetto-3.27.51.gs` destinato a MODULI AUTONOMO. Le correzioni delle chiavi lunghe e delle sincronizzazioni oltre 500 celle richiedono l'aggiornamento di entrambi i componenti e della distribuzione della web app Google.

Per gli eventi già pubblicati con domande a scelta singola, salvare e ripubblicare l'evento dopo l'installazione. Il modulo pubblico usa la revisione pubblicata e la ripubblicazione vi inserisce le domande conservate nella configurazione.

Le verifiche usano dati sintetici. Le prenotazioni e i permessi di produzione richiedono una verifica dedicata per determinare eventuali effetti pregressi dei difetti.

## Ambito dell'audit

Questo rilascio corregge i difetti riprodotti descritti nel [rapporto della 3.27.50](analisi-sistema-3.27.50-2026-11-11.md). Restano aperti i temi di ciclo di vita dei dati, gestione del consenso marketing, iscrizioni dimostrative alla pubblicazione, collegamento strutturato degli storni, compatibilità delle versioni Google e monitoraggio dei processi periodici.
