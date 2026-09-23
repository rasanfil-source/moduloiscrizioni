# Rilascio 3.26.159 — colonne compatte nell’elenco partecipanti

Include tutte le modifiche della [3.26.158](rilascio-3.26.158.md) e completa il comportamento condizionale della colonna **Stato**.

Quando nessuna riga visualizzata richiede una qualifica diversa da **Partecipante**, la colonna **Stato** non viene costruita. **Contatti**, **Dati mancanti** e **Gestisci** occupano quindi subito le posizioni successive, senza conservare larghezze o stili legati alla posizione precedente di Stato.

Le colonne dell’elenco ricevono ora classi semantiche. Le regole di larghezza e allineamento seguono il contenuto della colonna anziché `nth-child`, così restano corrette anche quando Stato, Presente, Stanza o Dati mancanti compaiono condizionatamente.

La prova browser delle presenze verifica anche il caso gratuito senza Stato: le intestazioni visibili risultano `N.`, `Partecipante`, `Contatti`, `Gestisci`, in quest’ordine e senza celle intermedie.

La modifica riguarda il plugin WordPress. Non richiede un nuovo deployment Apps Script.
