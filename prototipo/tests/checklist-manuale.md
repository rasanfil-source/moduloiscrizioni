# Checklist manuale del prototipo

La checklist riguarda la demo offline, non certifica il futuro plugin né il backend. Non usare dati personali reali.

Registrare una riga per ogni ambiente provato:

| Data | Tester | Sistema/dispositivo | Browser e versione | Viewport/zoom | Tecnologia assistiva | Esito complessivo | Note |
|---|---|---|---|---|---|---|---|
|  |  |  |  |  |  |  |  |

Legenda suggerita per la colonna `Esito`: `OK`, `KO`, `NA`. Per ogni `KO` annotare passaggio, risultato osservato, risultato atteso, screenshot e condizioni di riproduzione.

## Matrice offline e isolamento

| ID | Prova | Procedura | Risultato atteso | Esito/note |
|---|---|---|---|---|
| OFF-01 | Apertura diretta | Aprire `prototipo/index.html` come `file://` e controllare la console. | Il wizard parte senza errori; la copia negli appunti può usare il fallback previsto. |  |
| OFF-02 | Server locale | Avviare il server indicato nel README e ricaricare con Network aperto. | Solo documenti e asset del prototipo da `127.0.0.1`; nessuna richiesta verso host esterni. |  |
| OFF-03 | Assenza backend | Completare l'intero flusso con Network aperto. | Invio, email, bonifico, carta e contanti restano simulazioni; non parte alcuna richiesta applicativa. |  |
| OFF-04 | Stato volatile | Compilare dati riconoscibili, navigare avanti/indietro e poi ricaricare. | I dati persistono durante la navigazione interna ma spariscono al refresh. |  |
| OFF-05 | Nessuna PII persistita | Dopo la compilazione ispezionare URL, Local Storage, Session Storage e cookie. | Nessun dato digitato è presente; la query può contenere soltanto `brand`. |  |
| SAFE-01 | Dati dimostrativi | Cercare nel sorgente endpoint, indirizzi email, IBAN e link pagamento. | Nessun dato reale; asset e fixture sono locali e dimostrativi. |  |
| ISO-01 | Namespace CSS | Ispezionare `assets/css/wizard.css`, incluse regole dentro le media query. | Ogni selettore è radicato in `.se-booking`; nessun `body`, `html`, `h1`, `button` o `input` globale. |  |
| ISO-02 | Elementi fratelli | Con DevTools aggiungere fuori da `.se-booking` un titolo, un pulsante e un input; disabilitare `demo-shell.css`. | `wizard.css` non ne modifica aspetto o layout. |  |
| ISO-03 | Guscio standalone | Riabilitare `demo-shell.css` e ispezionarne i selettori. | Gli stili globali sono attribuibili soltanto al guscio demo e sono documentati come non incorporabili nel plugin. |  |
| ISO-04 | Confine JavaScript | Interagire con tutto il wizard osservando elementi esterni. | Nessun fratello viene alterato, salvo il selettore e la descrizione `Scenario tecnico del logo`, eccezione intenzionale della demo. |  |
| ISO-05 | Caricamento condizionale | Rieseguire quando esisterà il plugin su una pagina WordPress senza shortcode/blocco. | CSS, JS e richieste del modulo assenti. `NA` per il prototipo standalone. |  |

## Matrice branding multi-attività

| ID | Scenario/procedura | Logo e colori attesi | Testo atteso | Esito/note |
|---|---|---|---|---|
| BR-01 | Selettore `Logo attività` oppure `?brand=activity` | `logo-attivita.svg`; colori dell'attività | `Pellegrinaggi e Cammini`; fonte `Logo ereditato dall'attività` |  |
| BR-02 | Selettore `Logo evento` oppure `?brand=event` | `logo-evento.svg`; override e colori evento | titolo evento; fonte `Logo specifico dell'evento` |  |
| BR-03 | Selettore `Logo parrocchia` oppure `?brand=parish` | `logo-parrocchia.svg`; fallback e colori parrocchia | nome parrocchia; fonte `Logo di fallback della parrocchia` |  |
| BR-04 | Aprire `?brand=valore-sconosciuto` | fallback sicuro allo scenario attività | descrizione del logo attività, nessun errore console |  |
| BR-05 | Ripetere BR-01/03 a 320 px e BR-01/03 a 1440 px | logo intero con `object-fit: contain`, senza taglio o deformazione | la riga `Organizzato da attività · parrocchia` resta sempre leggibile |  |
| BR-06 | Sostituire temporaneamente via DevTools il logo con un asset quadrato, verticale e molto largo | contenimento stabile, senza variazioni distruttive del layout | nome testuale e provenienza restano disponibili |  |
| BR-07 | Rendere temporaneamente irraggiungibile il `src` del logo via DevTools | l'immagine può fallire, ma il layout rimane usabile | nome del brand e riga organizzatore continuano a identificare l'ente |  |
| BR-08 | Osservare la hero a tutte le larghezze | `hero-cammino.svg` riempie il riquadro con `object-fit: cover` senza deformarsi | testo alternativo coerente nell'albero accessibile |  |

## Navigazione e dati

- [ ] Il wizard percorre tutti e sei i passaggi con Avanti e Indietro.
- [ ] I dati restano presenti tornando ai passaggi precedenti.
- [ ] Con zero posti non si può proseguire dal passaggio 2.
- [ ] Non si possono superare 6 posti complessivi o il limite del tipo.
- [ ] Il referente è distinto dai partecipanti.
- [ ] `Il referente partecipa` precompila soltanto il primo biglietto.
- [ ] Disattivando l'opzione si possono inserire partecipanti diversi.
- [ ] Riducendo i posti spariscono le card non più necessarie.
- [ ] Le opzioni per ordine si applicano una sola volta.
- [ ] Le opzioni per biglietto si applicano soltanto alla persona selezionata.
- [ ] I pulsanti `Modifica` riportano alla sezione corretta.
- [ ] `Ricomincia la demo` ripristina la fixture iniziale: campi e opzioni vuoti, passaggio 1 e una quota doppia preselezionata.

## Prezzi

- [ ] Tutti gli importi sono formattati in euro con convenzioni italiane.
- [ ] Il totale cambia aggiungendo/rimuovendo biglietti e opzioni.
- [ ] La caparra `Da versare ora` è distinta dal totale.
- [ ] Il saldo successivo non diventa negativo.
- [ ] Il riepilogo specifica che il calcolo browser è dimostrativo.

## Validazione

- [ ] I campi obbligatori vuoti mostrano un errore specifico vicino al controllo.
- [ ] Il riepilogo errori riceve il focus e contiene collegamenti funzionanti.
- [ ] Email non valida e telefono senza prefisso vengono segnalati.
- [ ] Email e cellulare dei partecipanti possono restare vuoti.
- [ ] Un contatto partecipante compilato ma non valido viene segnalato.
- [ ] Il consenso privacy è obbligatorio.
- [ ] Il consenso marketing è facoltativo e non preselezionato.

## Conferma e pagamento

- [ ] Un doppio click su invio non produce due transizioni.
- [ ] Durante la simulazione il pulsante è disabilitato e mostra caricamento.
- [ ] La pagina finale distingue `Prenotazione: Registrata` da `Pagamento: In attesa di verifica`.
- [ ] Sono visibili codice ordine, totale, importo dovuto ora e saldo.
- [ ] I partecipanti e le opzioni selezionate compaiono nel riepilogo finale.
- [ ] Le azioni bonifico/carta/contanti non aprono siti e non cambiano lo stato pagamento.
- [ ] Il fallimento della Clipboard API non blocca il flusso.
- [ ] È esplicito che nessuna email viene realmente inviata.

## Matrice responsive e zoom

Ripetere almeno i passaggi 1, 2, 4, 5 e 6 in ogni riga. In tutti i casi verificare assenza di scroll orizzontale della pagina, testo non sovrapposto, controlli raggiungibili e contenuto non coperto.

| ID | Viewport/condizione | Comportamento atteso | Esito/note |
|---|---|---|---|
| R-01 | 320 × 568 px | colonna unica; brand copy secondaria nascosta ma organizzatore visibile; progress mobile; CTA fissa e riepilogo richiudibile |  |
| R-02 | 375 × 667 px | colonna unica; card, campi e pulsanti senza tagli; tastiera virtuale non blocca l'ultimo controllo |  |
| R-03 | 390 × 844 px su dispositivo/simulatore con safe area | barra inferiore rispetta `safe-area-inset-bottom`; nessun contenuto coperto |  |
| R-04 | 540 px e 541 px | passaggio del breakpoint della brand copy senza salto o sovrapposizione |  |
| R-05 | 760 px e 761 px | a 760 compare esperienza mobile; a 761 tornano stepper e riepilogo desktop senza contenuti compressi |  |
| R-06 | 768 × 1024 px | layout desktop compatto a due colonne e riepilogo visibile |  |
| R-07 | 1024 × 768 px | modulo e riepilogo affiancati; hero leggibile; riepilogo sticky non esce dal viewport |  |
| R-08 | 1440 × 900 px | larghezza massima controllata, spaziature regolari e righe di testo non eccessive |  |
| R-09 | telefono in orizzontale | CTA e riepilogo non coprono il contenuto; scorrimento verticale sempre possibile |  |
| R-10 | zoom browser 200% | reflow senza perdita di contenuto o azioni |  |
| R-11 | zoom browser 400% | esperienza equivalente a viewport stretto, senza scroll orizzontale bidimensionale |  |

Per R-01/R-03 verificare inoltre che il pulsante totale aggiorni `aria-expanded` tra `false` e `true`, richiuda il pannello al cambio passaggio e non intrappoli il focus.

## Matrice accessibilità

| ID | Area/strumento | Procedura | Risultato atteso | Esito/note |
|---|---|---|---|---|
| A-01 | Tastiera | Completare il flusso con Tab, Shift+Tab, Invio, Spazio e frecce dove native. | Tutte le azioni raggiungibili; ordine del focus logico; nessuna trappola. |  |
| A-02 | Focus visibile | Attraversare link, campi, quantità, consensi, riepilogo e pagamento demo. | Indicatore sempre percepibile e non affidato solo al colore. |  |
| A-03 | Cambio passaggio | Attivare Avanti, Indietro e `Modifica`. | Il focus passa al titolo del nuovo passaggio; il contenuto precedente non resta nel percorso Tab. |  |
| A-04 | Avanzamento | Ispezionare desktop e mobile con accessibility tree. | step corrente con `aria-current="step"`; progressbar con valore e testo `Passaggio n di 6`. |  |
| A-05 | Errori | Inviare vuoto o con email/telefono non validi. | riepilogo errori focalizzato, link funzionanti, `aria-invalid` e messaggio vicino al campo. |  |
| A-06 | Partecipanti | Aprire/chiudere le card `<details>` e compilare i campi. | `<summary>` funziona da tastiera; nome/cognome obbligatori e contatti facoltativi sono annunciati correttamente. |  |
| A-07 | Quantità e opzioni | Leggere i pulsanti meno/più, checkbox e prezzi con screen reader. | nomi accessibili specifici, label/legend comprensibili e stato selezionato annunciato. |  |
| A-08 | Consensi | Raggiungere privacy e marketing senza mouse. | privacy obbligatoria; marketing separato, facoltativo e inizialmente non selezionato. |  |
| A-09 | Live region | Cambiare logo, quantità, copiare codice, simulare pagamento e inviare. | aggiornamenti importanti annunciati una volta, senza letture ripetitive o interruzioni eccessive. |  |
| A-10 | NVDA + Firefox o Chrome | Percorrere i sei passaggi. | nessun blocco; titoli, stati prenotazione/pagamento e riepiloghi comprensibili. |  |
| A-11 | VoiceOver + Safari/iOS | Percorrere i sei passaggi e usare la barra mobile. | ordine di lettura coerente; controlli e pannello riepilogo utilizzabili. |  |
| A-12 | Windows High Contrast / `forced-colors` | Provare focus, errori, CTA, pill e card stato. | bordi, focus e stati restano distinguibili. |  |
| A-13 | `prefers-reduced-motion: reduce` | Cambiare passaggio e aprire il riepilogo. | niente scorrimento o animazione significativa non richiesta. |  |
| A-14 | Target touch | Misurare quantità, CTA, link/pulsanti principali su mobile. | area interattiva almeno 44 × 44 px dove previsto. |  |
| A-15 | Contrasto | Analizzare testo, CTA, focus, errori e stati nei tre branding. | nessuna violazione WCAG 2.2 AA per i colori usati. |  |

## Controlli automatici consigliati

- [ ] `node --check assets/js/demo-config.js`.
- [ ] `node --check assets/js/app.js`.
- [ ] Validatore HTML senza errori strutturali bloccanti.
- [ ] axe-core senza violazioni critiche o serie.
- [ ] Lighthouse Accessibility come segnale aggiuntivo, non sostitutivo del test manuale.
- [ ] Verifica statica che ogni selettore di `wizard.css` inizi con `.se-booking`; escludere deliberatamente `demo-shell.css` da questo controllo.

## Esito finale

- [ ] Nessun `KO` bloccante nel flusso principale.
- [ ] Eventuali `KO` non bloccanti hanno una segnalazione riproducibile.
- [ ] Le tre matrici branding, responsive/accessibilità e isolamento sono state eseguite negli ambienti concordati.
- [ ] È esplicito nel verbale che backend, sicurezza server-side, email, capienza, idempotenza, WordPress e pagamenti reali restano fuori dallo scope della demo.
