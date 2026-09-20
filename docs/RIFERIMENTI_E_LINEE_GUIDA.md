# Riferimenti legacy e linea visiva

Data analisi: 24 agosto 2026

## 1. Scopo

Questo documento registra quali elementi dei moduli già utilizzati possono essere recuperati e quali devono essere sostituiti. I file legacy sono riferimenti tecnici, non specifiche vincolanti e non vengono copiati nel nuovo progetto.

Riferimenti esaminati:

- `ISCRIZIONI/HTM.txt`, revisione del 21 aprile 2026;
- `ISCRIZIONI/saldo_cammino.html`, revisione del 2 aprile 2026;
- `ISCRIZIONI/GAS.txt`;
- `ISCRIZIONI/README.md`;
- `Assisi/iscrizione_assisi.html`;
- `Assisi/gas_iscrizione.js`;
- <https://www.parrocchiasanteugenio.it/>;
- <https://www.parrocchiasanteugenio.it/cammino-di-santiago-2026/>.

## 2. Pattern da riutilizzare

### Frontend

- layout desktop a due colonne con riepilogo sticky;
- passaggio a colonna unica su schermi stretti;
- card dinamiche per i partecipanti, evolute in `Biglietto n`;
- aggiornamento immediato del riepilogo economico;
- blocco dell'azione finale durante l'invio;
- stati di caricamento, successo ed errore con `aria-live`;
- timeout e annullamento delle richieste obsolete;
- modali brevi con focus iniziale, focus trap e ripristino del focus;
- copia assistita di importo, IBAN e causale;
- formattazione italiana degli importi.

### Google Apps Script

- letture batch del foglio e indici costruiti in memoria;
- `CacheService` con chiavi limitate a evento e revisione;
- invalidazione della cache dopo le scritture;
- `LockService` limitato alla sezione critica su idempotenza e capienza;
- escaping dei valori inseriti nelle email;
- email HTML accompagnata da una versione testo semplice;
- fixture di test senza invio a destinatari reali.

## 3. Pattern da sostituire

- selettori CSS globali come `body`, `h1`, `button`, `label`, `input`, `aside` e reset universali applicati alla pagina WordPress;
- singolo modulo molto lungo al posto del wizard progressivo;
- endpoint GAS chiamato direttamente dal browser;
- chiave API facoltativa, vuota o presente nel frontend;
- lookup pubblico per nome e cognome e restituzione di dati personali;
- prezzi, date, foglio, destinatari, coordinate bancarie e link di pagamento hardcoded;
- totale, caparra e saldo calcolati nel DOM e accettati come autorevoli dal server;
- righe del foglio usate come identità e progressivi basati su `getLastRow()`;
- serie di `appendRow` senza idempotenza e prenotazione atomica dei posti;
- invio email o accesso a servizi esterni dentro il lock;
- risposta positiva dopo un errore di scrittura o di posta;
- eccezioni interne e dati personali restituiti o scritti nei log pubblici;
- email costruite con dati utente non sottoposti a escaping;
- `ALLOWALL` e incorporamento GAS non necessario;
- scorciatoie globali da tastiera che possono inviare il modulo involontariamente;
- click sul link carta interpretato come pagamento ricevuto.

## 4. Linea visiva del sito

I valori seguenti descrivono il linguaggio visivo osservato sul sito e costituiscono una base, non una dipendenza dal tema:

- blu notte dell'intestazione: `#151b38`;
- blu delle CTA già usato nella pagina evento: `#337ab7`;
- titoli principali: stack `Roboto Slab, Georgia, "Times New Roman", serif`;
- testo del sito: stack `"Open Sans", Arial, sans-serif`;
- testo principale e secondario: grigi vicini a `#333333` e `#666666`;
- superfici prevalentemente bianche, con grigi molto chiari per separare le sezioni;
- hero fotografica con velo scuro e titolo bianco;
- pulsante principale ampio, blu, con testo bianco e angoli arrotondati.

Il plugin usa questi stack senza caricare copie aggiuntive dei font. Se il tema non li fornisce, i fallback mantengono leggibilità e proporzioni corrette.

### Osservazione sull'incorporamento corrente

Nella pagina del Cammino il vecchio modulo modifica ancora il `body` e altri elementi globali: cambia font, sfondo, larghezza e padding dell'intera pagina. Il nuovo CSS deve vivere sotto il solo contenitore `.se-booking`; gli eventuali stili della pagina dimostrativa devono stare in un file separato e non saranno inclusi nel plugin.

## 5. Conseguenze per il nuovo sistema

- il modulo deve apparire coerente con il sito ma rimanere leggibile anche con un tema diverso;
- logo attività, logo evento e immagine hero sono asset distinti;
- sotto il logo resta sempre il testo `Organizzato da [attività] · [parrocchia]`;
- il browser mostra un'anteprima del prezzo, mentre GAS ricalcola ogni importo in centesimi;
- WordPress carica gli asset soltanto sulla pagina che contiene il modulo;
- nessun riferimento legacy viene utilizzato come endpoint, segreto o fonte dati di produzione;
- prima del rilascio si confrontano home page e pagina evento con plugin attivo ma modulo assente, verificando che il plugin non aggiunga CSS, JavaScript o richieste di rete.

