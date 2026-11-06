# Modulo Iscrizioni 3.26.285

Rilascio del 6 novembre 2026. Un solo blocco di viaggio per tutte le persone della prenotazione, come richiesto nella revisione della pagina pubblica.

- Modalità «Vengo in auto» senza aggiunte. Un'offerta di posti per prenotazione, con scelta del conducente tra le persone iscritte; i posti offerti sono quelli liberi per altri iscritti, esclusi conducente e accompagnatori già previsti. Per due auto si fanno due prenotazioni.
- Le richieste di passaggio comprendono un posto per ogni persona della prenotazione. La scelta diretta mostra solo auto con posti sufficienti per tutto il gruppo; disponibilità e contributi vengono comunque verificati nella transazione di iscrizione. Le proposte successive in attesa restano individuali e possono riguardare auto diverse, come indicato nel modulo.
- La normalizzazione sul server attribuisce l'offerta al solo conducente scelto, senza moltiplicare i posti per il numero di persone; rifiuta conducenti estranei alla prenotazione. Restano compatibili le iscrizioni e i dati di viaggio precedenti.
- Nota su andata/ritorno ed eventuale contributo visibile solo scegliendo «Cerco un passaggio in auto». Le indicazioni dimostrative dell'evento sono semplicemente «Partenza da Roma».
- Voce «Pensateci voi» per richiedere un abbinamento; rimangono consenso all'attesa, località e indicazione della partecipazione subordinata al passaggio.
- Richiesta di avviso denominata «Desidero essere avvisato quando si trova un passaggio» e campo «Da dove vorreste partire?» con esempio «Roma». Questi campi sono visibili solo quando si chiede un abbinamento, non scegliendo un'auto disponibile.
- La scelta del viaggio e del conducente è conservata tornando indietro. La quota dell'evento resta separata dal contributo al conducente.

Verifiche: suite Node/PHP e asset; validazione di prenotazioni con tre persone e un solo conducente; rifiuto conducente esterno; richieste per tutte le persone; browser desktop/mobile, singolo blocco di viaggio e conservazione delle scelte; invio sintetico di una prenotazione multipla con sorgenti e minificati; anteprima navigabile e integrità ZIP.

Pacchetti `modulo-iscrizioni-3.26.285.zip` e variante `-pubblico`. Nessuna installazione in produzione; nessuna modifica Google richiesta. Archivi precedenti invariati.
