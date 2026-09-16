# Comunicazioni 3.26.115

La schermata permette di comporre il testo, controllare il numero di email e gli indirizzi delle prenotazioni, quindi confermare l'accodamento. In Operativo l'invio è ai destinatari reali; in Prova resta diretto alla casella di test. Gli eventi non pubblicati e la modalità Anteprima non producono invii. Nessuna anteprima storica viene promossa automaticamente.

L'anteprima scade dopo 30 minuti ed è legata all'utente. Conferma e replay usano lo stesso identificativo idempotente della coda. Cambi di modalità, pubblicazione, destinatari o saldo richiedono una nuova anteprima. Oltre 1000 prenotazioni la schermata si ferma, senza troncamento silenzioso.

Il testo supporta **grassetto**, *corsivo* ed elenchi con `- `, anche tramite pulsanti. HTML inserito direttamente non viene eseguito. Il promemoria saldo conserva il modello automatico personalizzato e non usa il testo libero.

L'anteprima mostra il corpo inserito, non una riproduzione completa del layout email. Nessuna email reale viene spedita dai test locali. Apps Script non richiede modifiche per questa release.
