# Rilascio 3.26.147

I fogli evento diventano leggibili da chiunque abbia il link, senza autenticazione Google. La condivisione riguarda tutte le schede del documento, inclusi gli eventuali dati personali ed economici. Il titolare ha confermato esplicitamente questa scelta dopo la prova in incognito. DB_MODULI rimane privato; non vengono aggiunti editor né concessa scrittura al dominio. Le celle protette rimangono protette.

La condivisione viene verificata durante creazione, riuso e aggiornamento dei fogli. Un errore di policy Google viene segnalato, senza dichiarare falsamente disponibile il documento.

Le conferme Sheet → MySQL includono la revisione accettata. Il foglio può riconoscere anche una replica completa successiva quando la coda ha accorpato quella intermedia: non resta quindi bloccato sul vecchio valore. Le modifiche locali successive vengono preservate. Le ricevute dei plugin precedenti rimangono compatibili; per la correzione completa occorre aggiornare anche WordPress.

Installazione: aggiornare Codice.gs e la distribuzione Web App esistente; installare lo ZIP modulo-iscrizioni-3.26.147.zip in WordPress. Nessuna nuova configurazione dello schema richiesta. Lo ZIP WordPress viene installato dal titolare.

Validazione: 329 test Node superati, sintassi PHP verificata e collaudo ricevute/gestione su MariaDB InnoDB superato. La prova di accesso anonimo è stata verificata anche dal titolare sul documento con dati fittizi. Non è ancora comprovato l'accesso con un secondo account editor.

Pubblicazione Workspace verificata il 21 settembre 2026: distribuzione 89 sullo stesso endpoint. Allineati i tre fogli evento esistenti: ANYONE_WITH_LINK / VIEW, editor invariati. Il registro centrale è rimasto nella configurazione preesistente DOMAIN_WITH_LINK (accesso al dominio, non anonimo); nessuna modifica ai suoi permessi.
