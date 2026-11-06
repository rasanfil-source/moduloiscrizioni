# Modulo Iscrizioni 3.26.280

Rilascio del 6 novembre 2026. Riorganizza la creazione e la modifica completa degli eventi per rendere esplicite le scelte sui trasporti, le due attese e l'associazione ai link del gruppo.

## Percorso per la revisione

1. **Come si chiama?** — titolo, gruppo e configurazione da un evento precedente.
2. **Cosa devono sapere le persone?** — luogo e presentazione.
3. **Immagine** — copertina facoltativa.
4. **Spostamenti** — organizzazione autonoma, pullman, passaggi condivisi in auto oppure pullman e passaggi insieme. Le relative impostazioni si aprono quando vengono attivate.
5. **Date e posti** — date, capienza, limite della prenotazione e **lista d'attesa per partecipare all'evento**. Con i passaggi attivi, un richiamo distingue questa lista dall'attesa di un posto auto.
6. **Quali dati chiediamo?** — dati dei partecipanti.
7. **Domande particolari** — domande aggiuntive.
8. **Servizi e quote** — domanda **Come sono determinate le quote?**, servizi e pagamenti.
9. **Controlla e crea la bozza / Controlla le modifiche** — associazione al link del gruppo accanto al link individuale, email e riepilogo di trasporti, quote e attesa dell'evento.

Nel controllo finale, i nomi delle categorie sono separati dall'indirizzo del link. Con una sola categoria disponibile viene proposta quella; con più categorie serve una scelta esplicita. Se il gruppo non ha categorie disponibili il campo è nascosto. Rimane possibile conservare una categoria disattivata già associata all'evento, secondo la regola preesistente.

## Trasporti e quote

I passaggi privati restano disponibili con tutte le modalità delle quote. Il contributo al conducente resta separato dagli incassi dell'evento. L'attesa di un passaggio non richiede di attivare la lista d'attesa per l'ammissione all'evento.

Il pullman è selezionabile anche negli eventi senza quote nel modulo o con quota uguale per tutti. In questi casi le tratte sono salvate senza supplemento: il modulo non calcola importi, oppure il pullman è compreso nella quota unica. Con quote basate sui servizi vengono applicati i supplementi delle tratte scelte. L'interfaccia esplicita questa regola in Spostamenti, Servizi e quote e nel riepilogo finale.

Disattivare il pullman rimuove le tratte gestite dal wizard e la relativa assegnazione; le opzioni avanzate esterne al wizard restano conservate. Tornare indietro prima del salvataggio conserva le scelte. Il rientro in una bozza usa le nuove posizioni di Date e posti e del controllo finale.

Il salvataggio continua a creare una bozza; la pubblicazione resta nell'azione successiva. Non cambia il funzionamento operativo delle attese o dei pagamenti dei passaggi.

## Verifiche

- Suite automatica Node/PHP, sintassi PHP, asset generati e sanitizzazione.
- Salvataggio reale del wizard su metadati sintetici: pullman senza quote, incluso nella quota unica e con supplementi; disattivazione; conservazione opzioni avanzate; importi non validi respinti prima delle scritture; ripresa delle bozze.
- Browser desktop e mobile sui sorgenti e sugli asset minificati: percorso completo, trasporti misti, validazione condizionale, due attese indipendenti, cambio quote, copia di configurazioni, categorie per gruppo e riepilogo con testo sicuro.
- Archivi confrontati con i sorgenti, checksum SHA-256 e verifica che tutti gli ZIP precedenti restino invariati.

## Installazione

Aggiornare il plugin con `dist/modulo-iscrizioni-3.26.280.zip` oppure con la variante `-pubblico`, priva della configurazione locale del saldo. Questo rilascio modifica il solo plugin: se MODULI AUTONOMO è già aggiornato al rilascio 3.26.279, non serve aggiornare il codice Google.

Non sono state eseguite installazioni o prove sui servizi in produzione. Gli archivi già generati restano invariati, incluso 3.26.226.
