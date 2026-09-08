# Leggibilità del modulo pagamenti

Il restyling riguarda «Registra movimento» nel file centrale e nei file evento.
Conserva coordinate dei campi, valori già compilati, convalide, chiave idempotente
e messaggio dell’ultima operazione. Il marcatore grafico in Z4 permette di applicare
la presentazione una sola volta senza ricostruire il modulo.

- Arial con gerarchia esplicita: titolo 24 pt, campi 14 pt, importo 20 pt,
  valori del riepilogo 16 pt, etichette e istruzioni almeno 11 pt.
- Campi principali alti 44 px; evento e referente possono andare a capo.
- Fondo crema per l’inserimento, grigio per il riepilogo, ambra per il residuo.
- Pagina delimitata da margini, griglia nascosta, sole due righe congelate.
- Colonne tecniche e righe esterne al modulo nascoste, mai eliminate.
- Istruzioni finali specifiche per il comando effettivamente disponibile.

## Applicazione

Generare i sorgenti con `node tools/prepara-codice-workspace.mjs`.
Nel progetto MODULI aggiornare Codice.gs con il bundle «Progetto» e aggiornare
anche il file separato InterfacciaMovimenti.gs dal sorgente del repository.
In alternativa usare il bundle completo in un progetto senza copie separate.

Dopo l’aggiornamento del codice, aprire «Apri inserimento guidato» per il modulo
centrale; per gli eventi usare «Prepara moduli movimento nei fogli evento» dal
menu Modulo iscrizioni. La sola sostituzione del sorgente non modifica subito
l’aspetto dei fogli già aperti.

## Verifica

Suite Apps Script e test di conservazione della bozza durante il restyling.
La verifica visiva sul foglio Google reale resta da eseguire dopo l’applicazione:
controllare un nominativo lungo, un titolo evento su più righe, importi con
migliaia e centesimi, tendina finale, messaggi di successo ed errore.
Lo zoom di Google Sheets resta una preferenza dell’operatore: iniziare dal 100%.
Su un portatile il modulo scorre verticalmente mantenendo visibile il titolo.
