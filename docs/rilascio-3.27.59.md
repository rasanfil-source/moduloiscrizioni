# Rilascio 3.27.59

Include la revisione email della 3.27.58 e i sei interventi autorizzati dell'audit.

- Annullare una proposta parziale libera e ripropone subito il posto; audit con stato originario.
- Un pagamento parziale conserva l'avviso di revisione della scadenza sospesa finché la quota richiesta non è coperta.
- Un salvataggio amministrativo rifiutato per importi o fine evento non genera una revisione pubblicata; un evento già pubblicato conserva anche titolo e contenuto precedenti.
- Le letture delle modifiche Sheets riconoscono subito le ricevute firmate, anche per una seconda modifica prima della proiezione. Le ricevute legacy aggiornano la base della cella.
- Dopo restore, GAS richiede con firma a WordPress di avanzare oltre la generazione Google. La richiesta verifica l'impronta corrente; il contatore Google non viene abbassato e la proiezione ritenta.
- L'impronta di eliminazione esclude tentativi, esiti e tempi tecnici di sincronizzazione/invio; conserva i dati di dominio.

Installare lo ZIP 3.27.59. Nel solo progetto MODULI AUTONOMO sostituire Codice.gs con il sorgente 3.27.59 e aggiornare la distribuzione Web App mantenendo lo stesso URL. Nessun reinvio delle email storiche. Il caricamento in produzione resta a cura del gestore.

Verifica: suite Node e PHP completa, regressioni delle ricevute e recupero generazioni; MariaDB/InnoDB per annullamenti parziali, revisione scadenze, eliminazione e controlli economici. Nessun invio reale dai test.
