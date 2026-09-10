# Prima fase — correttezza della gestione

## Modifiche locali

- Badge «Gratuito» nelle tessere esistenti solo per la dichiarazione del gestore «Evento totalmente gratuito» (`pricing_mode=ZERO`). Nessuna deduzione da prezzi, opzioni o incassi. Per eventi pubblicati segue la configurazione pubblicata; per le bozze quella salvata.
- Elenco Iscrizioni e preparazione delle comunicazioni leggono i movimenti MySQL in blocco e usano la stessa aritmetica del dettaglio. La rata futura originaria non viene scambiata per saldo attuale. Il piano originario non viene riscritto.
- Gli eventi con sola indicazione del prezzo non vengono presentati come somme da incassare; attesa e posto proposto sono esclusi dal totale da incassare.
- Il riepilogo distingue persone confermate, pagamento atteso, attesa e posto proposto; il versato netto comprende gli ordini chiusi. Il filtro attivo è riconoscibile.
- L'annullamento del primo partecipante non trasferisce automaticamente gli obblighi ONE al secondo nel conteggio dei dati mancanti.
- Si modifica un modulo alla volta: gli altri form sono bloccati mentre ci sono modifiche non salvate. Salvataggio, conferma di scarto e retry restano espliciti; annullamento e cancellazione camera non cancellano implicitamente una bozza. Un errore di lettura del dettaglio conserva il contenuto visibile.

## Verifiche eseguite

| Verifica | Esito |
|---|---|
| `node --test workspace-apps-script/tests/*.test.mjs wordpress-plugin/tests/*.test.mjs` | 227 test superati, nessun errore. |
| PHP lint sui tre file PHP modificati; `node --check` su portal-management.js | Superati. |
| `wordpress-plugin/tests/payment-ledger.php` con PHP e mbstring locali | Superato: importi, retry, saldo, rollback e coda su database simulato. |
| `wordpress-plugin/tests/operational-position.php` | Superato: saldo corrente, lettura aggregata, errore database, dichiarazione gratuita indipendente dagli importi, ONE/ALL, attesa esclusa dagli incassi. |
| `tools/test-gestione-browser.cjs` con Edge headless / backend sintetico | Superato: salvataggio, validazione, retry, dialogo/Escape, periodo, sincronizzazione e viewport mobile. |
| `tools/test-phase1-browser.cjs` con Edge headless / backend sintetico | Superato: due partecipanti, blocco di altre bozze, annullamento durante modifica, conferma scarto, retry stabile, errore dettaglio, conteggi, filtro residuo, mobile e sincronizzazione. |
| Screenshot sintetici desktop e mobile | Esaminata la vista mobile: contenuto accessibile; le tabelle conservano lo scorrimento orizzontale previsto. |
| `tools/check-sanitization.ps1` | Superato dopo aver reso relativi i collegamenti al codice nel documento di audit. |

La scansione generica `audit_project.py --mode strict` è stata eseguita ma **non supera il controllo globale**: produce 41 segnalazioni. 36 riguardano il prototipo storico e una copia temporanea, non l'interfaccia modificata; cinque riguardano pulsanti generati in portal-management.js e portal.js dei quali il rilevatore statico non riconosce il collegamento agli handler. La sincronizzazione e il submit sono esercitati dai test browser. Non si dichiara per questo la conformità globale del portale al controllo premium. Rapporto grezzo locale: `.tmp/phase1-premium-audit.json`.

Non sono stati eseguiti collaudi sul sito distribuito o sul database di produzione. Non sono stati eseguiti gli scenari InnoDB con concorrenza reale in questa fase; i percorsi di scrittura transazionali restano invariati. Non esiste un build frontend da generare per questi asset standalone.

## Conservazione e lavoro successivo

Nessuna modifica al codice Apps Script, nessuno Sheet cancellato o ricreato, nessuna email inviata e nessuna pubblicazione. Tessere, apertura dettaglio nella griglia, wizard e pagina pubblica conservati.

Questa fase non conclude l'intero progetto: restano ricerca individuale e paginazione, scheda completa e pagamento contestuale, stampa ed esportazione integrate, panoramica camere, conteggi servizi e rapporto annuale. Anche la pagina pubblica specifica del saldo resta da realizzare.
