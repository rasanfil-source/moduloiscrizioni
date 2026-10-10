# Pacchetto locale 3.26.224 — 10 ottobre 2026

Su mobile il selettore evento di Gestione iscrizioni è direttamente accessibile, senza aprire il pannello intermedio. Il filtro Eventi attivi / Eventi passati segue il selettore evento. Restano i campi bianchi e il blu originale del portale; desktop conserva i selettori affiancati.

Il pacchetto locale `dist/modulo-iscrizioni-3.26.224.zip` include la configurazione locale ed è basato sui sorgenti locali della versione 3.26.223. La variante `-pubblico.zip` esclude `public-balance-config.json`. Gli archivi sono verificati file per file tramite SHA-256.

Nessun aggiornamento o deployment GAS richiesto da questa modifica. Nessuna installazione sul sito effettuata.

La pubblicazione Git di questo intervento è limitata al selettore, agli asset generati pertinenti e ai test. Le modifiche locali precedenti delle versioni 3.26.221–223 e la versione del bootstrap locale restano separate: il commit del selettore non rappresenta l'intero contenuto dello ZIP locale 3.26.224.

Verifiche del selettore: test browser su mobile (320, 390 e 760 px) e desktop, cambio evento, Tutti gli eventi, filtro periodo, assenza di overflow e campi bianchi; 186 test strutturali superati. Il test browser generale incontra una differenza nella barra Foglio della fixture, estranea al selettore.

Verifica completa locale tramite `tools/verify.ps1 -PhpPath .tmp/php-runtime/php.exe` superata: suite Node, asset, sanitizzazione, lint e suite PHP. Log locale: `.tmp/mobile-release-224-verify.log`.
