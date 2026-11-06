# Modulo Iscrizioni 3.26.284

Rilascio del 6 novembre 2026.

Il campo «Termine per completare le assegnazioni» usa lo stesso inserimento automatico dei separatori delle altre date del wizard: per esempio `011120271000` diventa `01/11/2027 10:00`. Restano la validazione della data e i controlli del server sul termine dei passaggi.

È disponibile anche un'anteprima locale della pagina pubblica di iscrizione a una gita con auto private, generata dal renderer PHP e dagli asset reali del modulo. Evento, auto e disponibilità sono dimostrativi. Si possono provare conducente, richiesta di passaggio con disponibilità e attesa senza posti, con la sospensione del pagamento quando il passaggio è indispensabile. L'invio è bloccato dalla modalità anteprima del modulo e non contatta servizi operativi.

Verifiche: suite Node/PHP e asset; digitazione senza separatori nel wizard; browser desktop/mobile su sorgenti e minificati; pagina pubblica reale e relativi scenari; controllo ZIP e archivi precedenti invariati.

Pacchetti `modulo-iscrizioni-3.26.284.zip` e variante `-pubblico`. Solo plugin, nessuna modifica Google o installazione in produzione.
