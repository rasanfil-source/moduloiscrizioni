# Rilascio 3.27.46

10 novembre 2026. Include le correzioni e i sorgenti MODULI AUTONOMO dei rilasci 3.27.44 e 3.27.45.

- Il modulo distingue la lista d’attesa piena da quella disattivata.
- Invio rispetta il controllo selezionato nella pagina saldo. Annullamenti e timeout delle ricerche hanno esiti distinti.
- L’eliminazione evento rimuove i token aggiuntivi prima dei partecipanti nella stessa transazione.
- Un banner remoto indisponibile viene riprovato dopo cinque minuti.
- I link permanenti riutilizzano le date della revisione pubblicata e leggono la configurazione senza interrogare i contatori dei posti.
- La manutenzione viaggi registra gli errori e li mostra sulla pagina. Dopo una manutenzione riuscita, le visite successive riutilizzano l’esito per un minuto.
- La data effettiva di un nuovo pagamento deve essere entro oggi nel fuso del sito.
- Le impostazioni accettano anche gli URL Google con dominio. Il controllo del collegamento mostra un messaggio coerente con la verifica eseguita.

Il saldo resta consultabile per cognome e il recapito del riepilogo può essere scelto liberamente. Si conserva il limite IP concordato.

Installare lo ZIP WordPress 3.27.46. Il sorgente completo Codice-Workspace-Progetto-3.27.46.gs è destinato a MODULI AUTONOMO. Per chi usa già il sorgente 3.27.45, il codice Google è identico. Per versioni precedenti, sostituire il sorgente unico e aggiornare la Web App mantenendo il suo URL.

La suite locale verifica Node, PHP, sintassi, asset e sanitizzazione. I pacchetti vengono confrontati file per file con i sorgenti.

Le proposte UX approvate per gli errori nei campi appartengono all’intervento successivo. Il test dei tipi numerici sul servizio Google reale e la misura della contesa del lock richiedono una verifica sul servizio. La revoca individuale dei link viaggio resta da implementare.
