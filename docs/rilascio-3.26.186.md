# 3.26.186 — nome mittente e firma del gruppo

Le email di conferma usano il nome visualizzato risolto nelle impostazioni di identità (evento, gruppo, default). WordPress lo include nel payload firmato e Apps Script lo passa a MailApp, eliminando il nome fisso che ignorava la configurazione. L'indirizzo effettivo del mittente e il Reply-To mantengono le regole esistenti.

Il saluto del modello, per esempio «A presto!», è seguito dalla firma configurata sia in HTML sia nel testo semplice. Una firma identica al footer non viene duplicata. Le comunicazioni istituzionali mantengono il proprio layout e la propria identità.

Servono entrambi gli aggiornamenti: plugin WordPress e codice del progetto autonomo Google Apps Script. Sostituire il contenuto di Codice.gs con Codice-Workspace-Progetto-3.26.186.gs e aggiornare la distribuzione Web App esistente a una nuova versione, mantenendo lo stesso URL e le proprietà del progetto. Installare quindi lo ZIP del plugin. La sola installazione dello ZIP non corregge il nome fisso nel vecchio Apps Script.

I test simulano snapshot, composizione HTML/testo, payload WordPress e opzioni MailApp, inclusi fallback e override evento, senza inviare messaggi reali.
