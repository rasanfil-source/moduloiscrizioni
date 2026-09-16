# Avviso interno per eliminazione evento

Quando l'eliminazione termina la pulizia Google e avvia la transazione SQL, il plugin accoda due comunicazioni indipendenti:

- un avviso agli iscritti ancora attivi;
- un avviso interno alla casella della segreteria configurata nel collegamento Workspace.

L'avviso interno mantiene il modello grafico istituzionale, include titolo dell'evento e conferma che prenotazioni, partecipanti e dati collegati sono stati rimossi. Usa le stesse modalità email: Operativo invia alla segreteria, Prova reindirizza alla casella di test, Anteprima non invia.

I due messaggi hanno chiavi idempotenti separate e restano nella coda dopo la rimozione dell'evento, così la loro consegna non dipende più dal post cancellato.
