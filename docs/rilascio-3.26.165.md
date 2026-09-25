# Rilascio 3.26.165 — revisione grafica della segreteria

## Ambito

Revisione della presentazione del portale WordPress, desktop e mobile, secondo il progetto approvato il 24 settembre 2026. Nessuna modifica alle API, al database, ai permessi o alle operazioni applicative. La versione del plugin cambia per identificare il pacchetto; Google Apps Script non richiede aggiornamenti.

La regola vincolante è registrata in `UX-CONTRACT.md`: nessuna funzione viene eliminata o sostituita da quanto mostrato nei facsimile. In particolare, Rapporti partecipanti conserva tutte le caselle di selezione delle colonne, comprese le voci specifiche dell'evento, la persistenza della scelta, i filtri correnti e le azioni Stampa/Esporta Excel.

## Modifiche visive

- Palette condivisa: grigio neutro per lo sfondo e i filtri, superfici bianche, antracite per il testo, blu per le azioni. Bordi dei campi più riconoscibili dei divisori.
- Stile comune nelle schede Iscrizioni, Eventi, Crea evento, Pagamenti, Comunicazioni, Gruppi e Operatori. Colori personalizzati dei gruppi e colori semantici conservati.
- Icone telefono/email esistenti da 22px, bersagli da 44×44px, fondo azzurro e angoli arrotondati.
- Fino a 760px: menu espandibile con gli stessi collegamenti autorizzati ed Esci; evento espandibile con selettori invariati; Nuova iscrizione anticipata anche nell'ordine DOM; riepilogo compatto con segnalazione delle anomalie e tutti i dettagli disponibili.
- Elenco mobile senza taglio dei contatti: nomi lunghi a capo, progressivo conservato, informazioni su stanza/presenza/stato/dati mancanti sotto il nome, ordinamenti e paginazione invariati. Scorrimento naturale della pagina sul mobile; il contenitore desktop conserva il proprio comportamento.
- Rapporti e strumenti contestuali separati visivamente; le caselle del rapporto restano integralmente accessibili.

Le nuove finiture sono limitate allo schermo. L'impaginazione dedicata alla stampa, i dati esportati e i flussi di modifica/salvataggio rimangono quelli esistenti. La navigazione mobile sposta i nodi originali, senza duplicare link o azioni; senza JavaScript i collegamenti restano visibili.

## Verifiche

- Verifica generale: 351 test Node superati, 7 test degli asset generati, sanitizzazione, lint PHP e suite PHP del verificatore.
- Nuovo collaudo `tools/test-portal-work-layout-browser.cjs`: viewport 320, 360, 390, 600, 760 e 1280px; ordine DOM, menu ed Escape, pulsanti contatti, colonne condizionali, ricerca, persistenza della selezione del rapporto. Verifica del contenuto effettivo della stampa e del file XLSX scaricato dopo aver deselezionato colonne.
- Prove browser: evento completo con camere/presenze e recupero dagli errori; apertura righe con mouse/tastiera; colonna Stato condizionale; ricerca in tutti gli eventi; scheda individuale e stampa; selezione persone e importi nei pagamenti; assegnazione camere; wizard evento; conferme e accessibilità dei controlli.
- Regressioni visuali esistenti superate: link, storico pagamenti, modali e controlli 44px fra 320 e 1280px.
- Screenshot sintetici in `.tmp/portal-work-layout/`, con verifica visiva di iscrizioni, campi condizionali, wizard, gruppi, operatori e pagamenti.

Il vecchio `test-management-layout-browser.cjs` fallisce sull'aspettativa che il rapporto annuale della sua fixture sia nascosto. Il medesimo fallimento è stato riprodotto usando gli asset di HEAD, prima della revisione; non è una regressione introdotta qui. Le nuove prove non dipendono da quella fixture. Nei test relativi al layout approvato sono state aggiornate l'altezza delle righe, l'espansione esplicita del riepilogo mobile e le ricerche testuali rese ambigue dalla freccia di apertura già esistente.

## Pacchetti e installazione

- `dist/modulo-iscrizioni-3.26.165.zip`: pacchetto locale del plugin.
- `dist/modulo-iscrizioni-3.26.165-pubblico.zip`: solo file tracciati, senza configurazione privata.
- Checksum SHA-256 adiacenti; script `tools/package-3.26.165.ps1` verifica numero, percorso e hash di tutti i file dello ZIP.

Pacchetti preparati localmente; nessuna pubblicazione GitHub e nessuna installazione sul sito. Il collaudo usa risposte e identità sintetiche, non un ambiente WordPress autenticato di produzione. Dopo l'installazione è opportuno verificare gli stessi layout nel tema effettivo; gli asset minificati e il manifest sono inclusi.
