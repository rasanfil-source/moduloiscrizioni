# Modulo Iscrizioni 3.27.67

15 novembre 2026. Nuova modalità **Elenco compatto** nei Rapporti partecipanti, disponibile per stampa ed esportazione Excel.

## Uso

Aprire **Scegli i dati del rapporto** e selezionare **Elenco compatto**. Le altre caselle vengono deselezionate automaticamente; selezionare un altro dato disattiva l'elenco compatto. La scelta viene ricordata per evento, quando il browser consente il salvataggio locale.

Il rapporto contiene soltanto il titolo dell'evento e una griglia di cinque colonne, in ordine alfabetico da sinistra a destra. Mostra il cognome; quando il cognome compare più volte tra le persone selezionate, mostra cognome e nome per tutte le occorrenze. Conserva cognomi composti e persone omonime. Celle alternate bianche e grigio chiaro, testo a capo e bordi sottili. Impaginazione A4 orizzontale; il titolo si ripete sulle pagine stampate.

Stampa ed Excel rispettano i filtri correnti e leggono tutte le pagine della selezione. La modalità compatta omette contatti, camere, importi, numerazione e date di iscrizione/annullamento, anche negli eventi che le includono automaticamente nei rapporti ordinari. Una selezione vuota viene segnalata senza generare un documento vuoto. Le protezioni contro cambiamenti dei dati durante la lettura restano attive.

## Distribuzione

Nuovi ZIP locale e pubblico 3.27.67, con checksum SHA-256. Il pacchetto pubblico esclude la configurazione locale del saldo. Installazione dell'aggiornamento WordPress a cura del gestore. Nessuna modifica dei dati anagrafici; le viste di lettura precedenti vengono ricostruite per rendere disponibili nome e cognome separati.

MODULI AUTONOMO è invariato: non serve un nuovo deployment Google. Il sorgente operativo è incluso nel materiale del rilascio.

## Verifiche

Test del formato compatto e del vero serializzatore XLSX; regressioni PHP delle letture con cache e senza cache; collaudo browser di stampa ed Excel con 207 persone, cognomi duplicati tra pagine distinte, cognomi composti, filtri, scelta esclusiva, persistenza, selezione vuota, modifiche concorrenti e schermo mobile. Verifica dei sorgenti, degli asset minificati e degli archivi prima della pubblicazione. Tutte le prove usano dati sintetici.
