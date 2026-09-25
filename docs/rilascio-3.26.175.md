# 3.26.175 — immagine a filo della tessera Eventi

Correzione limitata a scrollbar-gutter delle tessere nell'area riservata. La regola generale stable riservava 15 px sul lato destro della tessera con overflow hidden, anche senza barra di scorrimento. La tessera ora usa auto; le vere liste a scorrimento mantengono le proprie regole.

Immagini con object-fit cover, altezza 105 px, fascia data e logo conservati. Nessuna modifica a markup, dati o caricamento delle immagini.

Il test browser riproduceva prima della correzione il margine di 15 px. Dopo la correzione verifica bordi superiore e destro, immagini orizzontali/verticali/quadrate, logo, tessere senza immagine e annullate, assenza di overflow a 320, 390, 768 e 1280 px. Esaminati gli screenshot desktop e mobile con immagini sintetiche. Verificati anche isolamento degli stili e asset compilati. Nessuna installazione sul sito.
