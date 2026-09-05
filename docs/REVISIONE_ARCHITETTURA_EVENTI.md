# Revisione dei fogli evento — primi due incrementi

Il file dell'evento è l'ambiente operativo della segreteria. DB_MODULI conserva
il registro centrale e lo storico; WordPress resta responsabile della disponibilità
pubblica dei posti. Questa revisione non realizza ancora l'intero percorso concordato.

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

I riepiloghi economici attuali sono per **prenotazione** e compaiono sulle righe
dei relativi partecipanti: non vanno sommati fra partecipanti della stessa
prenotazione. La ripartizione individuale non è ancora implementata.

## Limiti e incrementi ancora necessari

1. Collegare esplicitamente lo schema del wizard alle colonne del foglio. Le nuove
   colonne sono gestite dal motore, ma la creazione automatica per ogni servizio,
   tratta e domanda non è ancora completa. I codici delle domande basati sull'indice
   e delle tratte derivati dalla descrizione vanno resi stabili anche in WordPress.
2. Aggiungere il percorso di validazione delle nuove iscrizioni manuali, con
   prenotazione dei posti su WordPress prima della conferma centrale.
3. Completare i comandi coordinati per servizi, tratte e sistemazioni, con gestione
   dei conflitti e recupero degli errori fra WordPress e DB_MODULI. Nel frattempo
   la sincronizzazione dal foglio blocca queste modifiche; consente soltanto
   i dati personali esplicitamente ammessi.
4. Completare l'interfaccia di risoluzione dei conflitti e l'accesso alternativo
   della segreteria web alle stesse operazioni. Le funzioni di anteprima/conferma
   Apps Script esistono, ma questo incremento non aggiunge una nuova interfaccia web.
5. Collaudare su Google reale spostamenti, ordinamenti, formati, quote di esecuzione
   e modifiche umane durante la scrittura. Il lock serializza gli script, ma non
   può bloccare la digitazione simultanea di un operatore nell'intervallo fra
   rilettura e scrittura di una cella.

## Verifica e distribuzione

I test Node comprendono migrazione, rinomina/spostamento di colonne, riordino di
righe, formule, conflitti, colonne libere, cancellazioni locali, incassi e rimborsi,
limiti economici, prenotazioni di altro evento, retry e consegna parziale.

La pubblicazione GitHub non aggiorna Apps Script. Prima della distribuzione:

1. Conservare la versione precedente del progetto e identificare il deployment
   effettivamente collegato a WordPress, senza cambiarne URL o autorizzazioni pubbliche.
2. Aggiornare i sorgenti e il manifest. Il timer richiede il nuovo ambito
   `script.scriptapp`; la concessione va verificata separatamente dal proprietario.
3. Eseguire il controllo firmato e un collaudo su un evento con identità fittizie,
   senza spedire email. Verificare anche una migrazione con modifica manuale preesistente.
4. Solo dopo il collaudo, attivare il timer dal menu di DB_MODULI. Il comando riusa
   l'attivatore dello stesso utente quando già presente.

Nessuna cartella viene creata o spostata durante i test locali. Le funzioni Drive
esistenti riusano `EVENTI` nella radice e `EVENTI PASSATI` al suo interno.
