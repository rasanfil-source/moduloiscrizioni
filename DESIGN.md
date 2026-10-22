---
name: "Modulo iscrizioni — Segreteria eventi"
description: "Interfaccia operativa sobria, coerente con il sito parrocchiale e orientata a compiti chiari."
colors:
  inchiostro: "#172033"
  blu-notte: "#17224a"
  sfondo: "#f5f7fa"
  superficie: "#ffffff"
  bordo: "#d7dde6"
  testo-secondario: "#657084"
  successo: "#126b38"
  successo-chiaro: "#dff6e9"
  attenzione: "#765500"
  attenzione-chiaro: "#fff3cd"
  errore: "#9f1930"
  errore-chiaro: "#fdecef"
  fuoco: "#244fc2"
typography:
  testo:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', sans-serif"
    fontSize: "16px"
    lineHeight: "1.5"
rounded:
  controllo: "10px"
  scheda: "14px"
  pannello: "16px"
spacing:
  controllo: "44px"
  sezione: "24px"
components:
  pulsante:
    minHeight: "44px"
  campo:
    minHeight: "44px"
---

## Identità

La Segreteria eventi è uno strumento di lavoro, non una pagina promozionale. L’interfaccia usa titoli netti, istruzioni brevi e azioni riconoscibili. Il gruppo organizzatore è visibile senza prevalere sui dati dell’evento.

Il tono è ordinato e concreto. Tutti i testi rivolti agli operatori sono in italiano; importi in euro e date nel formato giorno/mese/anno. Le icone accompagnano etichette testuali e non sostituiscono il nome di un’azione.

## Colori

Il blu notte identifica navigazione e azioni principali. Il verde segnala un esito positivo confermato. Ambra e rosso distinguono rispettivamente attenzione richiesta e azione distruttiva o errore. Bianco e grigio chiaro separano aree operative e superfici di filtro.

Il badge «Gratuito» compare solo quando l’evento è stato dichiarato senza quote (`pricing_mode=ZERO`). Non viene dedotto dagli importi o dal saldo. Il colore non sostituisce mai lo stato testuale.

I token dell’interfaccia sono definiti nel perimetro `.mi-portal`: `--ink`, `--navy`, `--line`, `--bg`, `--muted`, `--success`, `--warning`, `--danger` e `--info`. Gli stili del plugin restano circoscritti al portale e non modificano il resto del sito o Divi.

## Tipografia e controlli

Il testo usa il carattere di sistema con corpo base di 16 px e interlinea 1,5. I pesi 400, 600 e 700 distinguono testo, etichette e titoli. I dati da leggere o confrontare restano almeno a 16 px; metadati e note secondarie possono essere più compatti.

Campi e pulsanti hanno almeno 44 px di altezza e un indicatore di fuoco visibile da tastiera. Schede e controlli usano angoli moderati. Checkbox, radio e date picker mantengono l’interazione nativa del browser.

## Impaginazione

La pagina segue lo scorrimento naturale. Schede e sezioni raccolgono compiti correlati; su schermi stretti si dispongono in una colonna senza nascondere etichette, azioni o dati.

Tabelle ampie scorrono nel proprio contenitore. Su mobile le informazioni secondarie possono disporsi sotto il nome, mantenendo visibili progressivo, contatti e stato. Gli elenchi lunghi usano paginazione e, quando utile, scorrimento interno limitato all’area di lavoro.

Il modulo pubblico e la conferma condividono una colonna centrata con margini laterali adeguati. Gli indirizzi email e le note lunghe vanno a capo senza allargare la pagina. La scheda della prenotazione mantiene i controlli di navigazione separati dal contenuto scorrevole.

## Componenti operativi

- Le tessere evento mostrano nome, gruppo e stato reale. I comandi principali mantengono etichette esplicite e una gerarchia coerente.
- Il riepilogo della gestione separa persone, importi pertinenti e dati da completare. I numeri sono allineati e usano cifre tabellari; i totali non sembrano pulsanti.
- La scheda della prenotazione distingue referente e partecipanti. Gli importi della prenotazione vengono mostrati una sola volta nei riepiloghi aggregati.
- Pagamenti evidenzia nome, quota, importo e metodo. Storico e totali restano leggibili anche su mobile; gli stati di saldo usano etichetta, colore e testo.
- Camere e presenze usano intestazioni semplici e raggruppamenti leggibili. Le tabelle non si trasformano in tessere cliccabili che nascondono i dati.
- Stati di caricamento, errore, assenza di risultati e conferma hanno messaggi distinti. Il colore accompagna sempre un testo comprensibile.

I dialog confermano le azioni che incidono sui dati. Escape chiude il dialog e restituisce il fuoco al comando che lo ha aperto. Le conferme non si affidano a finestre native non coerenti con il portale.

## Email e stampa

Le email usano un involucro semplice e compatibile con i principali programmi di posta: fondo chiaro, scheda centrale bianca, testata con l’identità del gruppo, contenuto leggibile e riferimenti di assistenza. HTML e testo semplice mantengono lo stesso ordine informativo.

La stampa nasconde navigazione, selettori, comandi e pannelli di modifica. I dati della prenotazione e i nomi dei partecipanti restano testo selezionabile; il numero di pagine dipende dal contenuto.

## Scelte da mantenere

- Preferire verbi diretti come «Apri», «Copia», «Pubblica» e «Salva».
- Tenere visibili ricerca, stato e azioni usate nel lavoro quotidiano.
- Riutilizzare i controlli nativi del browser per selezioni e date.
- Limitare ombre, gradienti, animazioni decorative e superfici sovrapposte.
- Non mostrare identificativi tecnici quando il nome dell’evento è disponibile.
- Non applicare gli stili del plugin ad altre parti del sito.
