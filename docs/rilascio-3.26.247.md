# Modulo Iscrizioni 3.26.247

Corregge l’attivazione delle notifiche Web Push attendendo che il service worker sia effettivamente attivo prima di creare la sottoscrizione del browser. Il service worker può attivarsi subito anche durante l’aggiornamento, senza attendere la chiusura delle altre pagine del portale.
