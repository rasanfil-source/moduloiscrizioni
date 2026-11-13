# Rilascio 3.27.64

13 novembre 2026. Raccoglie le modifiche del wizard e del modulo pubblico successive alla 3.27.63, includendo la verifica telefonica facoltativa del saldo della 3.27.62.

- Date e posti precedono i passaggi condivisi; ripresa delle bozze aggiornata. Navigando avanti e indietro si torna all'inizio del passaggio.
- Testi più semplici per passaggi, date, link, pagina finale e consensi. Capienza della lista d'attesa visibile solo quando attiva, conservando il valore impostato.
- Trasferimenti organizzati dopo la scelta delle quote e visibili solo con quote in base ai servizi. Campi nascosti disabilitati; configurazione temporanea mantenuta cambiando modalità prima del salvataggio.
- Caparra fissa come scelta iniziale nei nuovi eventi; importo vuoto o zero blocca l'avanzamento. Configurazioni precedenti conservate.
- Logo email con nome del gruppo e scelte Sì/No; distinte personalizzazione dell'aspetto e del testo di conferma iscrizione, rimosse spiegazioni tecniche.
- Per l'iscrizione singola il contatore è sostituito da «Iscrizione per una persona». Messaggi dei limiti in forma impersonale. Nomi coincidenti non duplicati nel riepilogo; consensi più ravvicinati.
- Il messaggio di errore della consultazione dei passaggi non parla di evento indisponibile. Il contributo nella spiegazione delle proposte è citato solo per una quota fissa positiva.

Installare il nuovo ZIP WordPress. Il sorgente MODULI AUTONOMO è invariato rispetto alla 3.27.63: nessuna modifica GAS o nuovo deployment Google necessario.

Verifiche: suite locale Node/PHP, asset minificati e collaudi browser desktop/mobile del wizard e del saldo con verifica telefonica. Pacchetti confrontati con i sorgenti e checksum SHA-256. Conservati soltanto gli ultimi cinque rilasci, 3.27.60–3.27.64. Il pacchetto pubblico esclude la configurazione locale riservata.
