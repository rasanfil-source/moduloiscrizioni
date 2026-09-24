# Rilascio 3.26.160 — scheda personale accessibile dalla riga

Include tutte le modifiche della [3.26.159](rilascio-3.26.159.md).

Nell’elenco partecipanti la riga intera apre la scheda della persona selezionata. Il comportamento vale sul numero progressivo, sulla stanza, sul nome, sullo stato, sui dati mancanti e sugli spazi liberi della riga.

La colonna e il pulsante **Gestisci** non sono più presenti nella vista per partecipante. Restano presenti nelle viste per prenotazione.

I collegamenti per telefono ed email e i controlli interattivi, compresa la presenza, non attivano l’apertura della scheda. La riga è raggiungibile con Tab e si apre anche con Invio o Barra spaziatrice; passaggio del mouse e fuoco da tastiera hanno un’evidenza azzurra coerente con il portale. L’altezza minima è 48 px: più compatta della versione con il pulsante, ma ancora adatta alla selezione con le dita; le icone di contatto restano da 44 px.

Le prove browser verificano apertura con mouse e tastiera, indipendenza dei contatti e della presenza, assenza della colonna Gestisci e compattezza delle colonne condizionali.

La modifica riguarda il plugin WordPress. Non richiede un nuovo deployment Apps Script.
