# Verifica 3.26.110

Modifiche: ricerca individuale in tutti gli eventi, scheda personale e riepilogo prenotazione, ricalcolo servizi con anteprima, conservazione della persona aperta, notifica pubblicazione e email segreteria.

Controlli eseguiti con PHP locale e MariaDB sulla porta 33317, database mi_ledger_test. Nessun accesso ai dati del sito, nessun invio email reale, nessuna cancellazione dello storico.

- Sintassi: tutti i file PHP del plugin; `node --check` sugli asset gestione e pagamenti.
- `wordpress-plugin/tests/individual-management-innodb.php`: anteprima senza scritture, aggiunta/rimozione, isolamento persone, credito senza rimborso automatico, caparra conservata, incasso del saldo di un’altra persona anche in presenza di credito, retry, conflitto versione e ricerca autorizzata. Passato.
- `wordpress-plugin/tests/management-innodb.php`: regressioni gestione, camere e concorrenza. Passato. Corrette due aspettative obsolete sui progressivi, perché la prenotazione mista introdotta precedentemente occupa già S4.
- `wordpress-plugin/tests/payment-people.php` e `payment-people-innodb.php`: quote, selezioni, rata, attribuzioni e retry. Passati.
- `wordpress-plugin/tests/publication-email.php`: HTML/testo, banner proprio/ereditato, indirizzi, transizione a pubblicato, assenza di notifica ai semplici aggiornamenti, deduplicazione InnoDB e TEST_PENDING. Passato; riusa anche php-behavior.php.
- `wordpress-plugin/tests/email-appearance.test.mjs`: passato.
- `tools/test-individual-management-browser.cjs`: ricerca, scheda, riepilogo intero, conferma con credito, salvataggio sulla persona corretta, ritorno alla ricerca e assenza di overflow a 320/390/1024px. Passato.
- `tools/test-payment-people-browser.cjs`: passato.
- `tools/test-secretariat-email-browser.cjs`: ordine logo/titolo/banner, testo e assenza di overflow a 320/390/680px. Passato con immagini sintetiche; non simula tutti i client email.

Audit statico Frontend Design Premium eseguito in modalità strict, sorgenti delimitati da premium-ui.json. Nessun owner irrisolto. Il tool segnala 12 pulsanti preesistenti con handler delegati/assegnati dopo la creazione, che la sua analisi statica non riconosce, e una textarea preesistente. Non si dichiara superato l’audit strict; la verifica dei percorsi modificati è documentata nei test browser. Il report completo è `.tmp/ui-audit-current-110.json`.

Limiti conservati: pagamenti storici multipli senza attribuzioni, quote comuni e rettifiche pregresse non attribuite richiedono verifica; i rimborsi di movimenti individualmente attribuiti richiedono ancora un flusso dedicato. Non vengono inventate ripartizioni né cancellati dati. Installazione WordPress da eseguire; Apps Script non modificato da questa versione.
