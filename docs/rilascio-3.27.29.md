# Modulo Iscrizioni 3.27.29

Rilascio del 9 novembre 2026. Messaggi e invii numerosi senza attendere Google nella pagina.

- Conferma e prova salvano una richiesta persistente; il server prepara al massimo dieci prenotazioni per passaggio, poi spedisce in background.
- Lotti firmati di massimo dieci email personalizzate: una richiesta Google per lotto, chiavi individuali stabili, nessuna lista destinatari condivisa.
- Worker con esclusione reciproca, risveglio HTTP autenticato dopo la risposta e cron di recupero.
- Le nuove prove conservano la priorità sugli invii massivi. Le prove interrotte al quinto tentativo vengono recuperate anche in anteprima, senza reinvio.
- Modalità operativa non abilitata: errore esplicito; sorgente Google non aggiornato: sospensione senza consumare tentativi.
- Avanzamento nella schermata: preparate, accettate da Google, attese, errori, anteprime e sospensioni.
- Quota esaurita e Google occupato conservano le email senza consumare i tentativi; ripresa rispettivamente dopo 15 minuti e 30 secondi. Gli esiti incerti richiedono verifica manuale.
- Le richieste di preparazione mantengono la modalità originale; un cambio modalità le sospende. Ogni lotto rilegge prenotazioni e saldi, escludendo chi non è più idoneo.
- Il cron futuro viene anticipato quando occorre partire; niente attesa fissa di 30 secondi tra lotti riusciti.

Installazione: aggiornare prima il solo MODULI AUTONOMO con Codice-Workspace-Progetto-3.27.29.gs, salvare e aggiornare la distribuzione Web App mantenendo l'URL. Poi installare modulo-iscrizioni-3.27.29.zip (o variante pubblico). La nuova azione INVIA_EMAIL_LOTTO richiede il sorgente Google aggiornato.

Verifiche: 434 test Node, 7 test degli asset generati, 82 script PHP, sintassi e sanitizzazione; campagne InnoDB sintetiche da 198 destinatari, quota parziale, risposta persa, esito incerto, priorità della prova e isolamento dei canali. Dettagli e limiti nel rapporto audit-messaggi-invii-3.27.29.md. Nessuna email reale inviata e nessuna installazione in produzione. Gli archivi precedenti, incluso 3.26.226, restano invariati.
