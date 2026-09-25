---
version: alpha
name: "Modulo iscrizioni — Segreteria eventi"
description: "Interfaccia operativa sobria, coerente con il sito parrocchiale e orientata a compiti chiari."
colors:
  inchiostro: "#202a3a"
  blu-notte: "#243b6b"
  sfondo: "#f1f3f5"
  superficie: "#ffffff"
  bordo: "#d8dee7"
  testo-secondario: "#526071"
  successo: "#126b38"
  successo-chiaro: "#dff6e9"
  fuoco: "#244fc2"
typography:
  testo:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', sans-serif"
    fontSize: "16px"
    lineHeight: "1.5"
rounded:
  DEFAULT: "10px"
  scheda: "14px"
spacing:
  controllo: "44px"
  sezione: "24px"
components:
  pulsante:
    minHeight: "44px"
  scheda:
    borderRadius: "14px"
  campo:
    minHeight: "44px"
---

## Overview

### Correzioni di usabilità — 3.26.173

Filtri apre i campi sotto la barra Cerca e conserva l'allineamento dei comandi. Le tessere Iscritti e Dati mancanti hanno titoli e valori centrati; il dettaglio ordinario è Confermati. La navigazione usa caratteri da 16 px, consentendo due righe su desktop senza ingrandire i singoli controlli. Il menu mobile aperto mostra tutte le voci autorizzate su due colonne. Per gli elenchi lunghi, lo scorrimento interno mobile occupa al massimo il 55% della finestra. Queste indicazioni sostituiscono le precedenti regole di scorrimento naturale dell'intero elenco mobile e di riduzione dei caratteri del menu.

### Iscrizioni mobile — 3.26.169

I riepiloghi restano sempre visibili, senza soffietto. Su mobile Persone iscritte e Dati mancanti occupano la prima riga di due tesserine compatte; gli altri conteggi condizionali seguono nella griglia a due colonne, anche a 320 px. Aggiorna (icona con etichetta accessibile) e Apri sono affiancati. Stampa ed Esporta Excel conservano il testo e dimensioni compatte, con bersagli di almeno 44 px, sulla stessa riga alle larghezze mobili verificate. Questa indicazione supera la precedente previsione del riepilogo espandibile.

### Correzione accenti — 3.26.168

La palette della specifica v2 usa blu #1B2B52, sfondo avorio #F5F3EE e oro #B08D3F per i dettagli. L'oro sottolinea l'intestazione, la voce attiva e il comando Menu su mobile; il testo piccolo dorato usa #83682F. Il collegamento «+ Crea evento» conserva un solo più, quello testuale. Queste regole riguardano soltanto lo schermo e il portale riservato. Le indicazioni storiche sulla palette grigia e blu #243b6b sono superate.

### Revisione operativa approvata — 24 settembre 2026

Il portale usa superfici bianche su fondo grigio neutro, filtri #f5f6f8, testo antracite e blu #243b6b per le azioni. I campi hanno bordo #7e8da3, più riconoscibile dei divisori. Le icone di contatto esistenti diventano 22px su pulsanti 44×44px con fondo #e7edf7 e raggio 11px. I colori semantici e quelli configurati per i gruppi conservano il proprio significato.

La palette e il ritmo dei controlli sono condivisi da iscrizioni, eventi, wizard, pagamenti, comunicazioni, gruppi e operatori. Su mobile, fino a 760px, il menu è espandibile e non occupa permanentemente lo schermo durante lo scorrimento. L'elenco dei partecipanti dispone le informazioni aggiuntive sotto il nome, conserva progressivo e ordinamenti e mantiene entrambe le icone entro la larghezza disponibile. Il riepilogo compatto si espande senza perdere alcun conteggio. I rapporti conservano la selezione dettagliata delle voci.

Le regole di questa revisione sono limitate a `@media screen`; le impaginazioni di stampa rimangono dedicate. Le indicazioni storiche successive su colori, barra orizzontale e dimensioni del riepilogo vanno lette alla luce di questa revisione. Il vincolo di conservazione delle funzioni è in UX-CONTRACT.md. Il test `tools/test-portal-work-layout-browser.cjs` controlla layout, ordine DOM, colonne condizionali, selezione dei rapporti e contenuto effettivo di stampa/Excel a 320–1280px.

La gestione camere mette in primo piano Tipo di sistemazione e una tabella delle persone raggruppate per codice. Prefissi operativi S, DM, DS, T e M; M identifica singole persone in camerate senza limite. Sezione sobria con bordo blu, intestazioni di gruppo e numeri modificabili, senza tessere cliccabili per i conteggi. Inventario e scambio camere restano funzioni secondarie.

La Segreteria eventi è uno strumento di lavoro, non una pagina promozionale. Deve ricordare una segreteria ordinata: titoli netti, istruzioni brevi, azioni riconoscibili e identità del gruppo visibile senza sovrastare il contenuto. Il riferimento pubblico resta il sito della Parrocchia Sant’Eugenio; il portale mantiene però una densità maggiore, adatta al lavoro quotidiano.

## Colors

Il blu notte identifica navigazione e azioni principali. Il verde indica esiti positivi confermati e, sulle tessere eventi, la dichiarazione esplicita «Evento totalmente gratuito» del gestore. Il badge «Gratuito» dipende da pricing_mode=ZERO, mai dagli importi o dal saldo; in assenza della dichiarazione non compare un badge alternativo. Bianco e grigio chiaro separano le aree operative; il rosso resta riservato alle azioni irreversibili o agli errori.

## Typography

La rifinitura di Gestione iscrizioni usa titoli di sezione da 20px, cifre del riepilogo da 28px e filtri su una superficie tenue. Nella scheda persona il nome precede contatti e importi; il riepilogo economico passa da tre colonne a righe etichetta/importo su mobile. I campi mantengono etichette visibili e focus da tastiera di 3px. Le regole di rifinitura sono limitate allo schermo e non sostituiscono il layout di stampa.

Nel portale operativo i pesi sono 400 (testo), 600 (etichette e comandi) e 700 (titoli e cifre). Nessuna dimensione esplicita in rem sotto 0.75rem; i dati principali restano a 1rem o più. Il minimo di 12px è riservato a metadati, non ai valori da leggere e confrontare.

Si usa il carattere di sistema già adottato dal portale, con testi e comandi rigorosamente in italiano. I titoli possono essere ampi, ma le istruzioni devono restare brevi e prive di slogan ridondanti.

## Layout

Nel modulo pubblico, disponibilità e conferma finale condividono una colonna centrata di massimo 700px, con margini laterali minimi di 16px sugli schermi stretti. La regola comune in public.css conserva l’allineamento dei bordi e permette agli indirizzi email lunghi di andare a capo.

Sotto i 480px, i link dell’evento dedicano una riga intera all’indirizzo e la riga successiva a Copia, Condividi e Apri; l’icona Condividi mantiene 44×44px. La scheda prenotazione mobile raccoglie frecce e chiusura in una barra superiore separata dal contenuto scorrevole, con margini laterali di 16px. I selettori numero camera hanno altezza minima 44px. Lo storico movimenti usa intestazioni, righe alternate sobrie e scorrimento locale accessibile da tastiera; data, tipo, importo e metodo non vengono spezzati, riferimenti e note lunghi vanno a capo.

Su mobile il riepilogo operativo dispone i conteggi in due colonne, con spazi ridotti e comandi di almeno 44px. Camere, presenze e report condividono bordi, intestazioni e spazi interni; le tabelle larghe conservano lo scorrimento locale. Caricamento, errore ed esito confermato hanno superfici semantiche con messaggi testuali; l’assenza di risultati resta uno stato neutro. Verifica sintetica completa disponibile in `tools/test-management-full-event-browser.cjs`, inclusi viewport da 320 e 390px e recupero dopo errore.

L’integrazione dell’audit 3.26.72 mantiene lo scorrimento naturale e le sezioni camere, presenze e servizi già richiudibili. La ricerca persone resta visibile; i filtri secondari sono raccolti in «Altri filtri», con conteggio attivo, inizialmente chiusi sotto 900px se non ci sono filtri applicati. La scheda persona mostra gli importi individuali; gli importi complessivi sono riservati a “Vedi tutta la prenotazione”. I vecchi versamenti non attribuiti restano esplicitamente da verificare. In wp-admin le tabelle di configurazione scorrono nel proprio contenitore e i dettagli secondari della prenotazione sono richiudibili.

Il contenuto segue lo scorrimento naturale della pagina. Le azioni correlate sono raccolte in schede; su schermi stretti si impilano senza perdere etichette o comandi. Campi e pulsanti hanno almeno 44 px di altezza. I collegamenti completi restano selezionabili.

## Elevation & Depth

I vecchi moduli a celle sono ritirati. Il foglio evento permette correzioni nelle celle azzurre dei dati operativi; il comando Sincronizza apre il confronto nel portale e richiede una conferma esplicita. I pagamenti restano nel modulo web.

Le superfici usano bordi e ombre molto leggere. Non si usano gradienti, vetro, animazioni decorative o effetti che rallentino la lettura.

## Shapes

Controlli con raggio di 10 px e schede con raggio di 14 px. Le forme circolari sono riservate a loghi, iniziali e indicatori di stato.

## Components

In Pagamenti le persone della prenotazione sono righe con checkbox nativa a sinistra e Nome Cognome — quota; negli eventi con caparra compare sotto la ripartizione caparra + saldo. La tendina Versamento precede le persone. Selezione con bordo blu, superfici chiare e importi leggibili; il campo importo calcolato resta in sola lettura durante l’incasso. La persona cercata precede le altre senza sostituire la loro identità con quella del referente.

La rifinitura del portale conserva fotografie, loghi e banda blu delle tessere. Il selettore superiore usa piccole icone decorative monocromatiche, etichette sempre visibili e una superficie unica con bordi discreti. I titoli delle tessere sono da 18px, peso 600; spazi interni e distanze seguono il ritmo 16/20px. I bordi delle superfici usano il token locale --line-soft; campi, focus e colori di stato mantengono il proprio contrasto. Su mobile rimane la navigazione orizzontale scorrevole. Queste finiture sono limitate allo schermo e non cambiano la stampa.

La configurazione evento in wp-admin distingue Gruppo e disponibilità, Date e lista d’attesa, Quote e pagamenti, Comunicazioni e richieste mediante intestazioni interne alla griglia. I campi e i filtri del plugin condividono altezza 44px e focus 3px; riquadri e modale usano la palette della segreteria senza alterare il resto dell’amministrazione WordPress.

Il consolidamento dell’audit estetico mantiene palette e geometrie esistenti: token aggiuntivi per spazi 4/32/40/48, raggio controllo 10, scheda 14 e pannello 16. Il titolo del portale è 24px. I pulsanti secondari non diventano primari al passaggio del mouse; gli stati condividono i colori semantici, con posto proposto in attenzione. Le icone di contatto usano gli SVG esistenti su bersagli da 44px. Toolbar e sfondi dei dialoghi non usano blur; l’importo evidenziato nei pagamenti usa un segnale statico, senza pulsazione. I colori configurati dai gruppi restano distinti dalla palette dell’interfaccia. I blocchi servizi mantengono etichette e raggruppamenti, con superficie tenue e bordo uniforme.

L’integrazione del secondo audit distingue dati primari (inchiostro, peso 600) da contatti e metadati (secondario, peso 400). Le righe operative hanno almeno 12px verticali. La scheda attiva conserva un segnale sinistro blu di 4px senza cambiare dimensione; le superfici dati usano bordi al posto di ombre. Ombre di finestre sovrapposte e focus restano funzionali. I comandi mantengono etichette esplicite; badge in normale maiuscolo/minuscolo, senza uppercase forzato.

Nell’amministrazione eventi, mi-booking-facts era già una griglia: si migliorano ritmo, separatori e coppie etichetta/valore. mi-admin-grid adotta gap di 24px e campi testuali/select di almeno 44px, senza ingrandire checkbox, radio o campi nascosti. La palette degli stati viene allineata a quella operativa solo nei componenti del plugin.

### Palette operativa consolidata

Gli audit grafici forniti sono proposte da valutare, non istruzioni per cambiare i flussi. Il consolidamento riguarda portal.css, portal-management.css e portal-payments.css; public.css e admin.css conservano il proprio ambito. Il colore di marca del gruppo non viene sostituito con un colore di stato.

| Ruolo | Testo | Sfondo |
|---|---|---|
| Testo secondario | --muted: #657084 | bianco / --bg: #f5f7fa |
| Positivo | --success: #126b38 | --success-light: #dff6e9 |
| Attenzione richiesta | --warning: #765500 | --warning-light: #fff3cd |
| Errore / azione distruttiva | --danger: #9f1930 | --danger-light: #fdecef |
| Informazione / operazione in corso | --info: #164b85 | --info-light: #e8f0fe |

I token sono dichiarati nello scope .mi-portal; i consumatori hanno fallback per i dialog montati fuori dal contenitore. Il posto proposto rimane in attenzione perché richiede una risposta entro una scadenza. Un evento concluso non viene trasformato graficamente in un errore. Il colore non sostituisce mai l’etichetta testuale.

Il riepilogo distingue Persone, Importi (solo se pertinenti) e Dati da completare mediante intestazioni di gruppo sobrie. Il versato netto appartiene a Importi. Valori allineati a destra, numeri tabellari, niente aspetto da pulsante. Le tabelle operative usano intestazioni leggibili e righe alternate leggere; le camere conservano le intestazioni di gruppo. I padding futuri seguono 8/12/16/20/24px senza rimodellare indiscriminatamente le tessere esistenti.

Fusi orari, convenzioni dei dati assenti, soglie della barra di occupazione e struttura dei metadati delle tessere richiedono verifiche distinte: non fanno parte di questo consolidamento grafico.

I token runtime canonici sono --ink, --navy e --line in portal.css, corrispondenti a inchiostro, blu-notte e bordo. portal-management.css li consuma per testi, bordi e azioni. Select e date picker restano controlli nativi: popup e interazione appartengono al sistema operativo. Il contratto dei comportamenti è in UX-CONTRACT.md.

Il badge «Gratuito» usa --success e --success-light, definiti in portal.css e corrispondenti a successo e successo-chiaro. Conservare tessere e apertura del dettaglio nella griglia; conservare wizard e modulo pubblico. Gli Sheet degli eventi restano disponibili con replica e correzioni sincronizzate.

La riconciliazione 3.24.0 ritira deliberatamente le regole grafiche dei form Sheets, in accordo con la scelta di un solo sistema web. Resta invariata l’identità del portale; i pulsanti di salvataggio sono primari, gli annullamenti hanno tono di pericolo e conferma dedicata.

Il passaggio conclusivo mostra nell’intestazione il gruppo organizzatore, il suo logo quando disponibile e il nome reale dell’evento. Il modulo pubblico e il foglio interno sono sempre separati per destinatario. La copia usa un solo comportamento condiviso, con esito accessibile e recupero manuale in caso di errore. Il link pubblico dell’evento offre anche la condivisione nativa del dispositivo, con ripiego sulla copia, senza librerie esterne. Le istruzioni per WordPress e Divi restano facoltative e chiuse inizialmente.

Nella scheda Iscrizioni i filtri seguono il ragionamento operativo da sinistra a destra: prima il periodo (eventi in corso o passati), poi un’unica tendina che comprende “Tutti gli eventi” e i singoli eventi, quindi lo stato della prenotazione. Non deve comparire un selettore intermedio che faccia apparire a sorpresa un altro campo.

In Gestisci eventi la scheda di dettaglio si apre nella griglia, subito dopo la riga della tessera selezionata. L’elenco rimane visibile sopra e sotto come contesto; la tessera attiva riceve un’evidenziazione sobria e il movimento di apertura rispetta la preferenza di riduzione delle animazioni.

La gestione web riunisce scheda prenotazione, dati mancanti, camere e accesso al modulo pagamenti. Il modulo pagamenti presenta storico e totali; non richiede la classificazione manuale della rata.

Le categorie dei servizi usano variazioni di blu: alloggio #e3edf7, pullman #edf3f9, pranzo #d5e5f3 e altre voci #f2f6fa. Le etichette restano sempre visibili: il colore è un aiuto alla scansione. Le definizioni delle voci sono condivise dal modulo pubblico e dalla proiezione Google. I relativi selettori runtime sono data-service-category in public.css e le classi dei gruppi nel wizard in portal.css.

Nella scheda dell’iscritto, servizi e sistemazioni sono raggruppati in Alloggio, Supplementi, Trasferimenti, Pasti e Altro. Un gruppo compare soltanto quando l’evento prevede una voce pertinente; nel riepilogo personale compare soltanto se la persona l’ha scelta. Colazione e assicurazioni appartengono ai Supplementi, mentre le tratte in pullman appartengono ai Trasferimenti.

La lista d’attesa separa tre momenti: richiesta registrata, proposta temporanea e prenotazione accettata. La prima email precisa che non occorre pagare o agire; quando si libera posto, il sistema lo riserva per 48 ore salvo diversa configurazione dell’evento e invia un collegamento personale. La pagina collegata offre due azioni esplicite, «Accetta il posto» e «Rinuncia». Solo dopo l’accettazione vengono comunicate conferma e, se applicabili, istruzioni e scadenza di pagamento.

La schermata Operatori rende visibile l’ambito prima del salvataggio. Il Gestore iscrizioni non mostra selettori perché opera sull’intero servizio; il Gestore gruppo mostra i gruppi assegnabili; il Gestore evento mostra soltanto gli eventi in corso assegnabili. Ruolo e ambito sono sempre riepilogati nella tessera dell’utente.

### Email del sistema

Tutte le email — conferma, promemoria saldo, annullamento, informazioni operative e prove — usano lo stesso involucro compatibile con i principali programmi di posta: fondo `#f6f8fc`, tessera bianca larga al massimo 600 px, testata nel colore principale del gruppo, azione primaria nel colore secondario, riquadro finale per l’assistenza e collegamento testuale di riserva. Logo, colori, nome del gruppo e contatto per le risposte sono ereditati da gruppo ed evento; il contenuto centrale cambia secondo il messaggio. Tabelle e stili in linea sono intenzionali per la compatibilità email. Il testo semplice conserva lo stesso ordine informativo dell’HTML.

## Do's and Don'ts

- Mostrare identità del gruppo, nome evento e stato reale.
- Usare verbi diretti: Apri, Copia, Pubblica.
- Distinguere chiaramente ciò che si condivide dai collegamenti riservati agli operatori.
- Non ripetere la stessa istruzione in più punti.
- Non mostrare identificativi tecnici quando è disponibile il nome dell’evento.
- Non applicare gli stili del plugin al resto del sito o a Divi.

Nelle tessere degli eventi pubblicati con iscrizioni future, «Attivo» conserva il grassetto attuale; sotto compare «dal 12/10/2026», con data numerica giorno/mese/anno senza orario, nel corpo piccolo esistente e in grassetto. La data usa il fuso del sito.

Nella seconda schermata pubblica, i servizi con massimo una unità per partecipante (pullman, pranzo, rimborso spese) usano caselle di spunta nella scheda di ciascun iscritto. Gli alloggi conservano la scelta esclusiva; le opzioni configurate con quantità multiple conservano il controllo numerico.

Quando sono previsti alloggi, “Non desidero alloggio” è una spunta separata dalle alternative di sistemazione. La spunta e le sistemazioni sono reciprocamente esclusive; l’avviso prima di proseguire resta riservato a chi non ha espresso nessuna delle due decisioni. La rinuncia non viene registrata come servizio né entra nel calcolo economico.

La conferma di una prenotazione con più partecipanti e costi apre con un riepilogo per persona: nome, totale individuale e descrizione sintetica per categorie. Totale, caparra e saldo seguono immediatamente. Le singole voci economiche restano in “Dettaglio dei costi”, un elemento `details` chiuso inizialmente. Per una sola persona o in assenza di costi rimane il riepilogo diretto.

I cellulari del referente e dei partecipanti vengono verificati nel passaggio in cui sono inseriti. Dopo la prima uscita da un campo non valido compare un messaggio accanto al controllo; le correzioni vengono rivalutate durante la digitazione. Il passaggio alla conferma convalida di nuovo tutti i cellulari visibili e porta il focus sul primo errore, senza perdere gli altri dati compilati.

I cellulari italiani completi inseriti senza prefisso (`333…`, `39 333…` o `0039 333…`) vengono completati automaticamente nel formato `+39 333…`. Il passaggio viene bloccato soltanto quando il numero è incompleto o non riconoscibile.

Le email operative partono con l’identità `Segreteria parrocchiale S. Eugenio <info@parrocchiasanteugenio.it>` e usano lo stesso indirizzo per le risposte e il mittente di busta. I modelli testuali convertono gli a capo letterali e il Markdown essenziale (grassetto e collegamenti) in HTML compatibile con i client email; le istantanee già accodate vengono riparate al momento dell’invio.

Le conferme degli eventi gratuiti non contengono collegamenti, pulsanti o testi relativi a stato dei pagamenti e saldo. La regola viene verificata nuovamente al momento dell’invio per proteggere anche le email già accodate. Un vecchio collegamento firmato mostra soltanto evento, codice e stato dell’iscrizione; l’interfaccia pubblica di saldo rifiuta gli eventi senza un flusso di pagamento.

Gli avvisi mi-action-progress usano fondo giallo chiaro #fff4ce, testo #604b13 e bordo #e5cf83. Le righe delle voci aggiuntive allineano i controlli al margine inferiore anche con etichette su due righe. La categoria visibile «Pasti» conserva il codice interno pranzo. In Gestione il periodo precede la scelta evento e inizialmente mostra gli attivi; i collegamenti diretti a un evento passato selezionano il periodo corrispondente.


## Iscrizione e prenotazione — 3.26.110
L’iscrizione è individuale; la prenotazione raccoglie gli iscritti insieme. Gestione iscrizioni comprende Tutti gli eventi e sostituisce l’accesso separato. La scheda mostra importi personali e conserva la persona dopo ogni salvataggio. Vedi tutta la prenotazione espone servizi, sistemazioni e importi individuali e complessivi. Crediti e debiti di persone diverse restano separati. Modifiche servizi hanno anteprima e aggiornano atomicamente le quote, preservando i versamenti. Nessuna attribuzione inventata per lo storico.

## Identità della Segreteria eventi — 3.26.111

La sola area operativa usa una favicon derivata dal logo della Parrocchia Sant’Eugenio: croce, fiore e baldacchino bianchi sul blu notte del portale. Un piccolo registro dorato distingue il lavoro di segreteria dall’identità pubblica del sito. L’icona non contiene scritte e conserva una silhouette leggibile da 32 px; le pagine pubbliche continuano a usare l’icona ordinaria del sito.
