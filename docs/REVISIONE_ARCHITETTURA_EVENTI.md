# Revisione architetturale dei fogli evento — chiusura v1

Il file dell'evento è l'ambiente operativo della segreteria. DB_MODULI conserva
il registro centrale e lo storico; WordPress resta responsabile della disponibilità
pubblica dei posti. La v1 del percorso concordato è completata; i limiti residui sono
scelte esplicite di ambito e non percorsi applicativi lasciati a metà.

## Implementato

- Aggiornamento incrementale di `Dati operativi`: nessun `clear`, nessuna eliminazione
  delle colonne storiche, delle righe manuali o delle colonne aggiunte dall'operatore.
- Identificativi `MI_CAMPO` associati alle colonne tramite i metadati Google. Dopo
  la migrazione, rinominare o spostare una colonna non cambia il collegamento.
- Identità partecipante composta da evento, prenotazione e numero partecipante;
  la versione di confronto nascosta è legata a questa identità. Identificativi
  duplicati o alterati sospendono l'aggiornamento.
- Confronto fra ultima versione sincronizzata, valore locale e valore centrale.
  Le formule manuali restano inalterate. La colonna **Verifica sincronizzazione**
  distingue modifiche da convalidare, conflitti e righe non più attive.
- Nuove colonne inserite accanto a quelle previste dalla vista; raggruppamenti
  comprimibili dei servizi e del dettaglio economico senza rimuovere quelli esistenti.
- Migrazione del formato precedente soltanto se metadati e tutte le intestazioni
  corrispondono alla struttura attesa. Le divergenze preesistenti non vengono
  attribuite arbitrariamente al foglio o al centro: restano da verificare.
- Una riga operativa eliminata viene ripristinata dal centro al refresh; non annulla
  l'iscrizione. Una riga manuale incompleta non impegna posti.
- Scheda **Pagamenti** nel file dell'evento: data, prenotazione, movimento,
  importo, modalità, causale, note, riferimento, operatore, spunta **Convalida**, esito.
  La convalida riusa i limiti economici centrali, assegna l'identificativo prima
  dell'incasso e rifiuta prenotazioni appartenenti a un altro evento.
- Movimenti centrali proiettati nella stessa scheda; nessuna modifica silenziosa
  della storia. Una correzione economica richiede una nuova riga di storno/rimborso.
  Le cancellazioni locali dei movimenti non cancellano il libro centrale.
- Il riuso di un identificativo con dati diversi viene segnalato; un errore dopo
  l'incasso e prima della conferma nel foglio è recuperabile senza un secondo incasso.
- `APPEND_REGISTRATION` conferma la consegna soltanto dopo l'aggiornamento del
  file evento. Se questo fallisce, il centro rimane salvato e la risposta è
  `EVENT_SHEET_PENDING`, ripetibile dal sistema di consegna WordPress.
- Menu di DB_MODULI per sincronizzare fogli e pagamenti. L'attivazione separata
  del timer avvia il controllo ogni cinque minuti e aggiorna i riepiloghi dopo
  la convalida dei movimenti. Non invia email.
- Domande personalizzate e tratte di pullman conservano un identificativo opaco
  quando cambiano etichetta o posizione.
- La scheda **Registra iscrizione** carica da WordPress tipologie, campi e opzioni,
  raccoglie fino a venti partecipanti e invia una richiesta HMAC. WordPress applica
  gli stessi lock, contatori, prezzi, idempotenza e storico del modulo pubblico.
- L'anteprima e la conferma delle modifiche sono disponibili dal foglio e dalla
  segreteria web. Camere e pullman operativi usano comandi dedicati e auditabili;
  le modifiche alla configurazione pubblica passano da una revisione WordPress.

I riepiloghi economici attuali sono per **prenotazione** e compaiono sulle righe
dei relativi partecipanti: non vanno sommati fra partecipanti della stessa
prenotazione. La ripartizione individuale non è ancora implementata.

## Limiti dichiarati della v1

1. I riepiloghi economici sono per prenotazione. Una futura ripartizione individuale
   potrà generare più movimenti dopo aver verificato la somma complessiva.
2. Un `ScriptLock` serializza gli script, ma Google Sheets non può impedire a una
   persona di digitare durante la breve rilettura di una cella. La conferma ricalcola
   firma e differenze e si arresta se il foglio è cambiato.
3. La garanzia di carico fino a 300 persone richiede il collaudo prestazionale
   previsto prima della produzione.
4. Riscossione diretta, API bancarie, cancellazione automatica per retention e
   infrastruttura distribuita restano fuori dallo scope della v1.

## Verifica e distribuzione

I test Node comprendono migrazione, rinomina/spostamento di colonne, riordino di
righe, formule, conflitti, colonne libere, cancellazioni locali, incassi e rimborsi,
limiti economici, prenotazioni di altro evento, retry e consegna parziale.

La pubblicazione GitHub non aggiorna Apps Script. Prima della distribuzione:

1. Conservare la versione precedente del progetto e identificare il deployment
   effettivamente collegato a WordPress, senza cambiarne URL o autorizzazioni pubbliche.
2. Aggiornare i sorgenti e il manifest. Il timer è facoltativo e resta disattivato
   finché il proprietario non lo richiede esplicitamente.
3. Eseguire il controllo firmato e un collaudo su un evento con identità fittizie,
   senza spedire email. Verificare anche una migrazione con modifica manuale preesistente.
4. L'operatività manuale non richiede timer. L'eventuale sincronizzazione periodica
   può essere attivata in seguito con una decisione separata.

Nessuna cartella viene creata o spostata durante i test locali. Le funzioni Drive
esistenti riusano `EVENTI` nella radice e `EVENTI PASSATI` al suo interno.
