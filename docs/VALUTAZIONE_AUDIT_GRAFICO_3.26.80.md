# Valutazione dell’audit grafico sulla versione attuale

13 settembre 2026 — plugin locale **3.26.80**.

Documenti esaminati: `audit-portale-segreteria-3.26.73.md` ed `evidenze-visive-audit-portale.html`, forniti dall’utente. Le proposte contenute nei documenti sono state valutate come oggetto dell’analisi.

**Esito: tre rilievi confermati, uno parzialmente confermato e uno falso positivo. L’audit individua problemi utili, ma le ricostruzioni HTML non sostengono la precisione dichiarata. La priorità dei link mobili va aumentata.**

## Base della verifica

La versione di riferimento è quella dichiarata in `wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php`, 3.26.80. Non ho scartato rilievi per la differenza di versione: rispetto al commit della 3.26.73, i due CSS management/pagamenti e i rispettivi JavaScript risultano invariati; le aggiunte a `portal.css` non modificano le regole responsabili dei cinque punti esaminati.

Ho controllato template PHP, JavaScript e cascata completa dei tre fogli CSS. Ho poi utilizzato Edge 153, tramite Playwright, con viewport reali da 320 a 1280 CSS px. Gestione camere, ricerca, scheda persona e storico pagamenti vengono generati dal JavaScript attuale; la struttura iniziale deriva dai template PHP, con attributi e dati sintetici. La finestra della scheda viene creata dal vero `portal.js`. I collegamenti evento riproducono i contenitori e i controlli del PHP attuale.

Le richieste vengono intercettate e ricevono risposte sintetiche: nessun accesso a dati operativi, nessun salvataggio, nessuna modifica al codice del plugin. È una verifica dell’interfaccia attuale in un browser, non un collaudo di un’installazione WordPress reale o di eventuali CSS aggiunti dal tema. Nessun errore JavaScript rilevato.

## Esito dei cinque rilievi

La numerazione «punto» segue il documento Markdown; quella «evidenza» segue l’HTML, che usa un ordine diverso.

| Punto / evidenza | Oggetto | Valutazione | Priorità rivista |
|---|---|---|---|
| 4 / 4 | Link dell’evento su mobile | Confermato, con conseguenze più gravi di quelle descritte | Alta, primo intervento |
| 1 / 3 | Storico movimenti in Pagamenti | Confermato; overflow dipendente da dati e larghezza | Alta |
| 2 / 1 | Numero camera da 34 px | Confermato | Media |
| 3 / 5 | Spazio nella scheda mobile | Confermato per lo spazio; scenario camere da precisare | Media |
| 5 / 2 | «Cancella ricerca» da circa 26 px | Falso positivo: è già 44×44 px | Nessun intervento per il difetto descritto |

### Link dell’evento: l’audit sottostima il difetto

Il problema non è soltanto `flex-wrap:nowrap!important`. Sotto i 480 px interviene anche **`.mi-output-copy button{width:100%}`**, in `portal.css:54`. Questa regola si applica sia a «Copia» sia al pulsante di condivisione. La regola di quest’ultimo che assegna 44 px ha specificità inferiore; quella dei link che assegna `flex:0 0 auto` impedisce ai pulsanti di restringersi.

Nel caso provato a **360 px**:

- il campo dell’indirizzo misura circa **24 px complessivi**, quasi interamente occupati da bordo e padding;
- «Copia» e condivisione misurano ciascuno **268 px**;
- «Apri» inizia a circa **623 px**, fuori dal viewport;
- la larghezza scorrevole del documento raggiunge **685 px**.

Il comportamento si riproduce anche a 320, 390 e 480 px. La riga del saldo presenta lo stesso conflitto su «Copia», pur non contenendo il pulsante di condivisione. L’affermazione dell’audit secondo cui gli altri controlli mantengono tutti una larghezza compatta non descrive la cascata completa.

**Intervento consigliato:** correggere insieme larghezze e disposizione sotto la soglia stretta: indirizzo su una riga intera, azioni compatte nella riga successiva e condivisione effettivamente da 44 px. Aggiungere soltanto `flex-wrap:wrap` non basta: occorre gestire il `!important` esistente e la regola `width:100%` applicata ai pulsanti. Non privilegerei la sostituzione di «Apri» con una sola icona: conservare l’etichetta è possibile correggendo il layout.

Riferimenti: `portal.css:37`, `:54`, `:79`, `:81`; `class-mi-portal.php:1429` e `:1433`.

[Screenshot a 360 px](../.tmp/audit-grafico-3.26.80/link-360.png).

### Pagamenti: difetto reale, con una soglia verificabile

`showHistory()` crea una tabella senza classe specifica e la inserisce direttamente in `[data-payment-history]`. `portal-payments.css` non ne definisce celle, intestazioni o contenimento. La protezione generale di `portal.css:18` interviene solo entro 760 px, quando la tabella è discendente di `.mi-portal`.

Misure con il modulo effettivo e dati sintetici:

| Viewport | Riferimento senza spazi | Larghezza scorrevole della pagina |
|---|---|---|
| 800 px | 35 caratteri | 837 px |
| 1024 px | 63 caratteri, stringa dell’allegato | 1084 px |
| 1280 px | 120 caratteri, limite ammesso dal campo | 1757 px |

Entro 760 px, nei casi provati, lo scorrimento rimane invece confinato alla tabella grazie alla regola generale. Non tutti i riferimenti producono overflow su qualsiasi desktop: la vulnerabilità grafica è confermata, la frequenza su dati operativi resta sconosciuta. La stringa di 63 caratteri è un dato di stress; non è necessario considerarla rappresentativa di un CRO/TRN tipico per dimostrare il difetto.

Il giudizio di disomogeneità visiva è condivisibile. L’assenza di righe alternate è una scelta stilistica; l’assenza di contenimento è il problema funzionale da risolvere per primo. L’espressione «bordo browser di default» è imprecisa: una tabella HTML senza stile non riceve necessariamente bordi visibili.

**Intervento consigliato:** uno stile circoscritto allo storico e scorrimento locale per le tabelle larghe, con interruzione dei riferimenti lunghi e numeri allineati. Se si sceglie un contenitore dedicato attorno alla tabella, occorre una piccola modifica a `showHistory()`: la proposta dell’audit di aggiungere un wrapper «senza impatto su JS o PHP» non è esatta. Resta un intervento piccolo.

Nel modulo pagamenti incorporato in Gestione iscrizioni, `showHistory()` nasconde questo storico (`embedded`): il rilievo riguarda il percorso Pagamenti separato.

Riferimenti: `portal-payments.js:56–66`, `portal.css:18`, `class-mi-portal-payments.php`, campo `riferimento` con `maxlength="120"`.

[Screenshot a 800 px](../.tmp/audit-grafico-3.26.80/pagamenti-800.png).

### Camere: confermata l’altezza di 34 px

Il selettore generato da `portal-management.js:185` possiede realmente `data-room-person`. La regola in `portal-management.css:16` prevale sulla base generale e produce **68×34 px**, sia a 1280 px sia a 390, 360 e 320 px. Il campo «Posti» dell’inventario segue invece il minimo da 44 px.

**Intervento consigliato:** portare il selettore a **44 px**. L’alternativa da 40 px proposta nell’audit lascerebbe una differenza rispetto all’obiettivo dichiarato. È una correzione di coerenza e comodità d’uso, non una conclusione automatica sulla conformità normativa dell’intera interfaccia.

Il rilievo è valido grazie al controllo del codice e del componente effettivo; la ricostruzione allegata, priva dell’attributo necessario, non lo dimostra correttamente.

[Screenshot del selettore effettivo](../.tmp/audit-grafico-3.26.80/camere-1280.png).

### Scheda mobile: spazio ridotto confermato, percorso camere diverso

Il padding di `3.1rem` per lato è reale. La scheda compatta dispone di circa **261 px a 360 px**, **291 px a 390 px** e **221 px a 320 px**. A 360 px, i campi all’interno del riquadro dati scendono a circa **223 px**. Il costo in spazio è quindi significativo, anche quando le frecce sono disabilitate.

Il dettaglio persona iniziale, tuttavia, **non genera il pianificatore camere**: mostra la stanza e i servizi come testo, insieme ai campi della persona. Nel caso con pernottamento provato, la scheda iniziale contiene zero tabelle. Non è corretto attribuirle automaticamente una tabella da 480 px solo perché l’evento prevede camere.

C’è una precisazione utile: nella versione attuale, premendo **«Torna al riepilogo» dentro la modale**, si può arrivare al riepilogo dell’evento nella stessa finestra. Aprendo poi Gestione camere, la tabella da **480 px** compare effettivamente in una regione larga circa **203 px** a viewport 360. Lo scorrimento annidato è dunque raggiungibile, ma attraverso questo percorso aggiuntivo, assente dalla spiegazione dell’audit.

**Intervento consigliato:** recuperare spazio su mobile stretto spostando le frecce in una zona dedicata sopra o sotto il contenuto. Eviterei frecce semitrasparenti sovrapposte ai campi, perché potrebbero coprire testo e comandi. Verificare anche il passaggio scheda → riepilogo nella modale. La priorità resta inferiore ai link con comandi fuori schermo e all’overflow dei pagamenti.

Riferimenti: `portal.css:17–19`, `portal.js:799`, `class-mi-portal.php:1522`, `portal-management.js:485–520`; stile compatto in `portal-management.css:90`.

[Screenshot della scheda iniziale a 360 px](../.tmp/audit-grafico-3.26.80/scheda-360.png).

### «Cancella ricerca»: falso positivo della cascata

La regola generale **`.mi-management button{min-height:44px}`**, già presente all’inizio di `portal-management.css`, continua ad applicarsi. Le regole successive di `.mi-search-input button` modificano posizione, larghezza, padding e bordo, ma **non annullano il minimo di altezza**. Non serve che ogni selettore ripeta tutte le proprietà già applicabili.

Il controllo generato dal vero JavaScript misura **44×44 px** a tutte le quattro larghezze provate: 1280, 390, 360 e 320 px. La descrizione di un’area 44×26 px e di una correzione «lasciata a metà» va quindi ritirata. Non consiglierei di aggiungere altezza e traslazione verticale per risolvere questo presunto difetto.

Riferimenti: `portal-management.css:1`, `:58`, `:177`; `portal-management.js:322`.

[Screenshot del filtro effettivo a 360 px](../.tmp/audit-grafico-3.26.80/filtri-360.png).

## Affidabilità dell’HTML allegato

L’allegato è utile come illustrazione dell’intento, ma non è una riproduzione fedele verificata della cascata completa:

- **Manca `.mi-management` nell’intero documento.** Le regole di base per i suoi controlli, comprese quelle del pulsante di cancellazione, non possono applicarsi.
- **Manca `data-room-person` nei selettori illustrati.** La regola citata per dimostrare l’altezza da 34 px non li seleziona.
- A viewport 1280 px, il selettore illustrato misura in realtà **30×19 px**, il campo «Posti» **120×21 px** e la cancellazione circa **24×21 px**: non le misure riportate nelle annotazioni.
- Le cornici da 360 px sono semplici `div`, non viewport autonomi. A finestra desktop le media query mobili non si attivano dentro quelle cornici. Il riquadro modale usa infatti padding da **60 px** per lato, invece dei 49,6 px dichiarati per mobile.
- Padding e bordi si sommano alle larghezze dichiarate delle cornici: quella etichettata «360px» misura complessivamente **390 px**; quella etichettata «700px» ha `width:420px` e misura **450 px** complessivi.
- Sono presenti solo estratti dei CSS e ritocchi inline ai font. Manca, fra le altre, proprio la regola `width:100%` che rende più grave il problema dei link.

La dichiarazione «ogni problema è verificato nella cascata completa» è quindi troppo forte. Anche i giudizi positivi estesi a intere schermate vanno letti come valutazioni del codice, non come certificazione dell’interfaccia reale: questa verifica ha approfondito i cinque rilievi, senza certificare tutti gli altri flussi del plugin.

## Decisione proposta

Accogliere gli interventi su link mobili, storico pagamenti e selettore camere; mantenere il miglioramento dello spazio della modale con la motivazione corretta; scartare la correzione del pulsante di cancellazione. Conservare palette, identità e flussi esistenti.

Ordine suggerito: **link mobili → storico pagamenti → numero camera → spazio della modale**. Le modifiche al plugin non sono state applicate in questa valutazione.

Materiale riproducibile locale: [script della verifica](../.tmp/audit-grafico-attuale.cjs), [misure complete](../.tmp/audit-grafico-3.26.80/misure.json). Gli screenshot collegati sono acquisizioni del browser con dati sintetici e asset attuali, non immagini del sito operativo.

## Implementazione successiva

Su richiesta dell’utente, i quattro miglioramenti ritenuti validi sono stati implementati nella [3.26.81](rilascio-3.26.81.md). Le misure e le schermate precedenti restano la fotografia della 3.26.80 usata per questa valutazione; quelle della correzione sono separate in `.tmp/portal-visual-regressions/`.
