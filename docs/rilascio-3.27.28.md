# Modulo Iscrizioni 3.27.28

Rilascio del 9 novembre 2026. Correzioni della sezione Messaggi dopo l’audit.

- I suggerimenti dei tipi sono disponibili nella pagina: cambi rapidi non possono più sostituire il testo con una risposta precedente. Le modifiche restano distinte per tipo.
- La prova viene conservata nella coda persistente ed elaborata subito, limitatamente alla propria riga. Non attende l’invio massivo né il primo avvio del cron. La schermata riporta l’esito ricevuto da Google oppure l’errore/attesa residua; la consegna finale alla casella dipende comunque dal servizio email.
- Doppi clic e ritrasmissioni della stessa prova mantengono la stessa chiave. Dopo la prova rimane aperta la conferma; «Torna alla composizione» recupera evento, tipo e testo.
- I messaggi manuali funzionano anche se la conferma automatica dell’iscrizione è disabilitata.
- Gli eventi passati vengono richiesti soltanto aprendo lo storico dalla tendina. La voce iniziale resta «Scegli un evento».
- I banner seguono evento → gruppo → parrocchia. Le immagini pesanti vengono ridimensionate e ricomprese in copie dedicate, anche per URL esterni HTTPS raggiungibili, con cache e conservazione degli originali. In caso di formato o servizio non disponibile si usa l’immagine originale.

## Installazione coordinata

1. Sostituire `Codice.gs` nel solo progetto **MODULI AUTONOMO** con `Codice-Workspace-Progetto-3.27.28.gs`, salvare e aggiornare la distribuzione Web App esistente mantenendone l’URL. Il sorgente aggiunge il canale firmato `PROVA_PUNTUALE` per la prova al singolo indirizzo scelto.
2. Installare il plugin `modulo-iscrizioni-3.27.28.zip` oppure la variante `modulo-iscrizioni-3.27.28-pubblico.zip`.

Aggiornare soltanto il plugin lascia incompatibile la nuova prova con il precedente sorgente Google. Nessuna configurazione privata è inclusa nel sorgente MODULI AUTONOMO o nella variante pubblica.

Verifiche: sintassi PHP; regressioni della composizione, ripristino bozza, deduplicazione, invio mirato in tutte le modalità, isolamento dei canali, anonimizzazione e scadenze individuali; 24 test JavaScript del frontend e del trasporto simulato; ottimizzazione locale/remota di un banner sintetico da 1.698.441 a 40.677 byte. Nessuna email reale inviata, nessuna installazione in produzione. Il confezionamento verifica asset, contenuto degli ZIP e integrità degli archivi precedenti.
