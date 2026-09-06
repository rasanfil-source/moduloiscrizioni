# Correzione dati dimostrativi 3.23.16

Il generatore amministrativo di iscrizioni fittizie ora completa sugli eventi storici i metadati privacy introdotti per gli eventi nuovi: versione dell’informativa e identificativo del consenso. Usa sempre la pagina privacy configurata in WordPress e non aggira l’accettazione richiesta dal servizio centrale.

Se manca la pagina privacy globale, il generatore si ferma prima di creare dati e indica espressamente quale configurazione completare. Il servizio di iscrizione distingue inoltre la mancata accettazione da una configurazione incompleta dell’evento, evitando il precedente messaggio fuorviante.
