# Icona del portale: asset, manifest, Home e avvio

Riferimento tecnico per Modulo Iscrizioni / MODULI AUTONOMO e per il progetto Tutti a Tavola. Verificato il 5 novembre 2026 sui sorgenti locali del plugin 3.26.275. Questa scheda documenta il comportamento esistente; non certifica una prova su telefoni reali e non modifica il plugin o i pacchetti.

## 1. Identità e forma dell'asset

Il riferimento è `wordpress-plugin/modulo-iscrizioni/assets/portal-icon-512.png`: simbolo bianco con croce, forma centrale simile a una colomba/fiamma, tenda e persone stilizzate, su fondo blu notte con variazioni tonali. Il blu dell'immagine non è un riempimento uniforme da identificare automaticamente con il `theme_color` del manifest.

La tela è quadrata, 512 × 512 pixel, con canale alfa. La sagoma è un quadrato arrotondato; i quattro pixel agli angoli hanno alfa 0. Non è un quadrato con angoli dipinti di bianco. Anche parte dell'interno ha alfa 252–253/255: il file reale non è integralmente opaco all'interno.

**Raggio degli angoli:** il repository non espone un parametro vettoriale che abbia generato la sagoma. Il raggio è incorporato nei pixel. Una misura del contorno alfa a soglia 128, confrontata con un arco circolare, dà circa **93 px su 512**, ossia **18,2% del lato**. È una stima del raster, non una costante originale né una misura imposta al sistema operativo. Le versioni ridotte hanno un raggio apparente di circa 35–36 px su 192, 33 px su 180 e 6 px su 32. Per una copia fedele usare gli asset esistenti: una nuova maschera CSS al 18% è solo un'approssimazione.

SHA-256 del riferimento 512: `73abfd151ffdea83417cc9709bbdec49ca5afb582ea2fe9cc638f485aab76ae1`.

| File in `wordpress-plugin/modulo-iscrizioni/assets/` | Dimensioni | Impiego effettivo |
| --- | --- | --- |
| `portal-icon-32.png` | 32 × 32 | Favicon PNG; anche badge delle notifiche |
| `portal-icon-180.png` | 180 × 180 | Apple touch icon predefinita |
| `portal-icon-192.png` | 192 × 192 | Manifest PWA; anteprima; fallback delle notifiche |
| `portal-icon-512.png` | 512 × 512 | Manifest PWA, risorsa ad alta risoluzione anche per l'avvio |
| `segreteria-eventi.ico` | Contenitore ICO | Favicon alternativa e uso desktop |

## 2. Come vengono prodotti e inseriti i file

`tools/update-portal-icon.ps1` riceve un PNG sorgente già sagomato. Verifica che i quattro angoli abbiano alfa 0; non disegna gli angoli e non contiene un parametro `radius`. Genera le varianti con `Format32bppArgb`, fondo `Transparent`, composizione `SourceCopy` e interpolazione `HighQualityBicubic`, conservando il canale alfa. Scrive i quattro PNG 32/180/192/512 negli asset e un ICO multirisoluzione 16/32/48/64/256 in `dist/segreteria-eventi.ico`.

Esempio per una futura sostituzione autorizzata, dalla radice del progetto:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "& ./tools/update-portal-icon.ps1 -InputPath 'C:/percorso/icona-sorgente.png'"
```

Il comando modifica gli asset: non eseguirlo per consultare questa scheda. Il controllo dei quattro angoli non equivale alla validazione dell'intero contorno. Lo script scrive l'ICO in `dist`, mentre l'HTML richiama quello in `assets`: in un futuro aggiornamento occorre allineare anche la copia pubblicata. `tools/create-desktop-icon.cjs` è una procedura distinta che crea un ICO 32/192 con protezione contro la sovrascrittura; non confondere i due metodi.

Ogni futura modifica distribuibile al plugin richiede una versione nuova e ZIP nuovi, secondo `AGENTS.md`. La sola documentazione esterna al plugin non richiede un nuovo pacchetto.

## 3. Come l'HTML richiama l'icona

Il punto centrale è `MI_Portal::portal_icon_links()` in `includes/class-mi-portal.php`. È collegato a `wp_head` con priorità 99; la pagina autonoma `?mi_portal=1`, che costruisce il proprio documento HTML, lo richiama esplicitamente nel suo `<head>`.

Schema del markup predefinito; `PLUGIN_URL` e `VERSIONE` sono segnaposto da sostituire:

```html
<link rel="shortcut icon" type="image/x-icon"
      href="PLUGIN_URL/assets/segreteria-eventi.ico?ver=VERSIONE">
<link rel="icon" type="image/png"
      href="PLUGIN_URL/assets/portal-icon-32.png?ver=VERSIONE">
<link rel="apple-touch-icon" sizes="180x180"
      href="PLUGIN_URL/assets/portal-icon-180.png?ver=VERSIONE">
<link rel="manifest"
      href="/?mi_pwa=manifest&amp;group=0&amp;ver=VERSIONE"
      data-mi-pwa-group="0">
<meta name="theme-color" content="#1B2B52">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Segreteria">
```

Gli URL reali sono costruiti con `MI_PLUGIN_URL` e `home_url('/')`: rispettano anche installazioni WordPress in sottocartella. Il parametro `ver` usa `MI_VERSION`. Manifest, metadati PWA e script di installazione sono emessi solo se la funzione è abilitata, la pagina è il portale e la connessione è HTTPS. Non è un cambio globale della favicon di tutto il sito.

Se è selezionato un gruppo con icona personalizzata valida, l'Apple touch icon diventa la sua variante **192 × 192** e il relativo attributo `sizes` viene aggiornato. La favicon 32/ICO rimane quella generale. I metadati Apple qui riportati descrivono il codice esistente; non sono un meccanismo per imporre trasparenza.

## 4. Manifest PWA e significato dei parametri

Il manifest è generato in PHP da `MI_Portal_PWA::manifest()` e servito da `serve_manifest()` all'indirizzo `/?mi_pwa=manifest&group=0&ver=VERSIONE`, con tipo `application/manifest+json; charset=utf-8` e intestazioni contro la cache. **`assets/min/manifest.json` non è questo manifest PWA**: non usarlo come modello dell'installazione.

Equivalente semplificato per un sito alla radice del dominio:

```json
{
  "id": "/?mi_portal=1",
  "name": "Segreteria eventi",
  "short_name": "Segreteria",
  "description": "Il portale della segreteria, disponibile anche dal browser.",
  "lang": "it",
  "start_url": "/?mi_portal=1",
  "scope": "/",
  "display": "standalone",
  "background_color": "#F5F3EE",
  "theme_color": "#1B2B52",
  "prefer_related_applications": false,
  "icons": [
    {"src": "PLUGIN_URL/assets/portal-icon-192.png?ver=VERSIONE", "sizes": "192x192", "type": "image/png", "purpose": "any"},
    {"src": "PLUGIN_URL/assets/portal-icon-512.png?ver=VERSIONE", "sizes": "512x512", "type": "image/png", "purpose": "any"}
  ]
}
```

Il codice reale emette URL assoluti. `src` individua il file, `sizes` ne dichiara le dimensioni intrinseche e non la misura dell'icona sulla Home, `type` il formato. **`purpose: "any"`** dichiara un'icona generale: questo progetto non dichiara queste immagini come `maskable`. Non significa “trasparenza obbligatoria”.

`id` identifica l'app; `start_url` stabilisce cosa aprire toccandone l'icona; `scope` definisce l'ambito di navigazione; `display: "standalone"` richiede la finestra da app. `background_color` è lo sfondo di avvio previsto, non il colore con cui rendere trasparente l'icona; `theme_color` è il colore proposto per l'interfaccia del browser/sistema. Non esistono qui parametri manifest `border_radius`, `corner_radius` o `transparent_icon`.

## 5. Personalizzazione per gruppo e registrazione nel sistema

Nel pannello **App sul telefono**, la scelta del gruppo ricarica la pagina con `mi_pwa_group=ID`. Un solo gruppo consentito viene selezionato automaticamente; con più gruppi è disponibile la scelta esplicita, compresa l'identità generale.

L'upload è un PNG quadrato di 512–4096 px per lato, massimo 2 MB. WordPress lo registra come allegato della libreria media. Il gruppo conserva l'ID dell'allegato nel metadato **`_mi_pwa_icon_id`**; le varianti 192 e 512 sono registrate nei metadati dell'allegato come **`mi_pwa_192`** e **`mi_pwa_512`** quando necessarie. `icon()` accetta soltanto immagini delle dimensioni esatte richieste, con fallback all'icona generale.

Questa procedura di upload controlla formato, dimensioni, permessi e nonce; **non applica il raggio del logo generale, non rimuove un fondo e non impone angoli trasparenti**. Un'immagine personalizzata deve essere preparata prima. Il ridimensionamento passa dalle funzioni immagini di WordPress e va verificato sul relativo hosting.

Per un gruppo valido, `id` e `start_url` diventano `/?mi_portal=1&mi_pwa_group=ID`; `name` diventa `Segreteria — Nome gruppo`, `short_name` è il nome del gruppo limitato a 24 caratteri. Il parametro conserva l'identità grafica durante la navigazione tramite PHP e `portal-pwa.js`. Non concede permessi e non filtra da solo i dati: le autorizzazioni restano quelle dell'account.

## 6. Trasparenza su Android e iPhone: cosa si può ottenere

**La trasparenza del PNG è verificata; la trasparenza verso lo sfondo della Home non è garantita.** Nel browser il canale alfa lascia vedere il fondo dell'elemento/pagina. Il launcher del telefono può comporre l'immagine su un proprio fondo, ridimensionarla e applicare una maschera.

- **Android:** mantenere PNG RGBA e `purpose: "any"` descrive correttamente l'asset già arrotondato di questo progetto. Il launcher può comunque aggiungere una superficie o margini. Se si vuole un'icona adattiva, creare un asset separato a fondo pieno e dichiararlo `maskable`: la zona garantita è un cerchio centrale di raggio pari al 40% del lato, quindi 204,8 px su 512. Non basta rinominare il PNG trasparente o cambiare `purpose` in `any maskable`. La specifica impone che la trasparenza di un'icona maskable sia composta su un riempimento scelto dal browser. [Specifica W3C](https://w3c.github.io/manifest/#icon-masks).
- **iPhone/iPad:** il collegamento `apple-touch-icon` fornisce l'icona dedicata e, quando presente, ha precedenza sulle icone del manifest. Questo è il comportamento dichiarato da [WebKit](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/). Non abilita la trasparenza sulla Home. Per un risultato prevedibile, una futura variante Apple può essere un quadrato pieno, senza angoli preritagliati, lasciando la sagoma al sistema: è una raccomandazione di adattamento, **non** la descrizione dell'attuale PNG 180, che ha angoli trasparenti.
- **Entrambi:** la documentazione PWA di Google segnala che iOS/iPadOS e Android 8+ possono riempire la trasparenza con un colore fuori dal controllo dell'app. Non esiste quindi un'impostazione universale HTML/manifest che obblighi entrambi a mostrare lo sfondo del telefono attraverso gli angoli. [Web app manifest, sezione Icons](https://web.dev/learn/pwa/web-app-manifest/#icons).

Il raggio del PNG, il `border-radius` di un'immagine HTML e la maschera della Home sono tre cose distinte. Neppure `theme_color: "transparent"`, `background_color: "transparent"` o lo stile traslucido della barra di stato risolvono questa distinzione.

## 7. Home, installazione, schermata di avvio e prima pagina

**Anteprima nella pagina.** Il pannello App sul telefono mostra la prima icona del manifest a **48 × 48 CSS px**, con `border-radius: 10px` (`assets/portal.css`, `.mi-pwa-preview img`). Quel raggio vale solo per l'anteprima HTML e non modifica il file né viene trasmesso al launcher.

**Installazione sulla Home.** `assets/portal-pwa.js` intercetta `beforeinstallprompt`, rende disponibile il pulsante Installa app quando il browser lo consente e gestisce `appinstalled`. Rileva l'avvio come app con `matchMedia('(display-mode: standalone)')` e `navigator.standalone`; in quella modalità nasconde il pulsante e le istruzioni di installazione. Su iPhone il percorso indicato è Safari → Condividi → Aggiungi alla schermata Home, con Apri come app web quando disponibile. La disponibilità del comando nativo dipende dal browser.

**Avvio dopo il tocco.** `start_url` apre la pagina iniziale del portale, con vista predefinita `management`, non la scheda evento dalla quale si era installata l'app. L'URL non incorpora evento, persona, token, nonce o filtri temporanei. L'eventuale gruppo mantiene l'identità grafica. Sessione e autorizzazioni continuano a determinare l'accesso; l'icona non effettua l'accesso al posto dell'utente.

**Splash del sistema.** Android può generare la schermata di avvio da nome, icona e colori del manifest. Il fondo `#F5F3EE` è coerente con lo sfondo CSS del portale riservato e riduce lo stacco al caricamento. Il codice non impone durata, dimensione dell'icona o animazione della splash. [Documentazione Chrome](https://developer.chrome.com/docs/lighthouse/pwa/splash-screen/).

**Avvio iOS.** Nei sorgenti esaminati non è presente `apple-touch-startup-image`: non è stata configurata un'immagine di avvio Apple dedicata. L'Apple touch icon e l'immagine di avvio sono risorse differenti; non dedurre dall'icona l'esistenza di una splash personalizzata. L'archivio Apple documenta il collegamento separato per l'immagine di avvio. [Safari Web Content Guide](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html).

**Dati e aggiornamenti.** Il modulo di installazione non introduce una cache offline dei dati: il portale richiede Internet. Questo non significa che l'intero plugin sia privo di service worker, perché le notifiche Web Push sono una funzione separata. Gli URL degli asset predefiniti sono versionati; il pannello avverte che, dopo una sostituzione dell'icona, potrebbe servire rimuovere e reinstallare l'app per vedere subito il cambiamento. Disabilitare l'installabilità non rimuove a distanza le icone già presenti sui telefoni.

## 8. Come riferirsi a questa soluzione in Tutti a Tavola

Adottare questa scheda come riferimento tecnico documentato, distinguendo il modello di Modulo Iscrizioni dalle decisioni del progetto destinatario. La descrizione del simbolo da sola non costituisce una specifica di integrazione.

Nei sorgenti TAT consultati il 5 novembre 2026 esistono già manifest distinti per Prenotazioni e Cucina, icone `any` e varianti `maskable` separate, un `apple-touch-icon` 192 e logica del service worker che contestualizza `id`/`start_url` per centro e funzione. Il riferimento non autorizza a sostituire quelle identità con `mi_portal`, eliminare le varianti maskable o modificare la gestione della cache. I nomi `mi_*` appartengono a Modulo Iscrizioni e vanno tradotti nel modello di TAT solo in un eventuale intervento successivo.

Per un futuro adattamento: documentare origine e hash degli asset, dimensioni e alfa, raggio incorporato o criterio di esportazione, variante Apple, eventuali varianti maskable, collegamenti HTML, manifest di ciascuna funzione, cache/versionamento, URL iniziale e sfondo della prima pagina. Conservare accessibili i testi che identificano le azioni: questa icona identifica l'app, non sostituisce i nomi dei comandi.

## 9. Verifica ripetibile e limiti

La verifica di questa scheda comprende lettura PHP/JS/CSS, ispezione visiva del PNG e misurazione locale dei quattro PNG: tutti hanno dimensioni corrette e alfa 0 nei quattro angoli. La stima del raggio usa il bordo superiore sinistro del raster, soglia alfa 128 e confronto con archi circolari; non certifica una curva vettoriale originale.

Per validare un'implementazione sui dispositivi: controllare risposte HTTP e MIME di manifest e icone, anteprima Manifest in Chrome DevTools, installazione Android e iPhone, Home su sfondi chiari e scuri, splash, pagina iniziale, identità per gruppo/centro, aggiornamento dell'icona e riapertura dell'app. La prova nel browser desktop non dimostra il comportamento della Home. Nessuna prova su telefono reale è stata eseguita per questa scheda.

Punti di codice autorevoli:

- `wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal.php`: `portal_icon_links()`, `render_virtual_page()`, `current_view()`.
- `wordpress-plugin/modulo-iscrizioni/includes/class-mi-portal-pwa.php`: manifest, metadati HTML, gruppo, allegato, caricamento e aiuto installazione.
- `wordpress-plugin/modulo-iscrizioni/assets/portal-pwa.js`: installazione, standalone e conservazione del contesto grafico.
- `wordpress-plugin/modulo-iscrizioni/assets/portal.css`: anteprima 48/10 e sfondo del portale.
- `tools/update-portal-icon.ps1`: produzione PNG/ICO preservando alfa.
- `wordpress-plugin/tests/portal-pwa.php`: contratti su manifest, identità, permessi e caricamento immagini; non è una prova visiva del launcher.
