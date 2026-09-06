# Pagina iscrizione autonoma 3.23.20

Il percorso pubblico `?mi_iscrizione=…` stampa soltanto il foglio stile e i due script del modulo. Non richiama più gli hook generali di testa e piè di pagina del tema, che aggiungevano tre fogli CSS Divi nonostante la pulizia della coda WordPress.

Il riquadro disponibilità usa inoltre un selettore più specifico del reset globale del tema. Il collaudo reale deve confermare `padding-top` e `padding-bottom` uguali e nessun URL Divi tra gli asset della pagina.
