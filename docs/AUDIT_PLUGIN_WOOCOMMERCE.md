# Audit plugin WooCommerce del sito

Rilevazione eseguita sul sito attivo il 27 agosto 2026. Nessun plugin è stato disattivato o rimosso durante l'audit.

## Componenti necessari

- **WooCommerce**: base tecnica indispensabile per prodotti, ordini, carrello, checkout e per la piattaforma donazioni.
- **Donation Platform for WooCommerce**: necessaria per la pagina `prodotto/dona/`, per la scelta libera dell'importo e per il modulo dedicato al donatore.
- **Un solo gateway per le carte**: necessario per incassare, ma non è necessario mantenerne due attivi contemporaneamente se svolgono la stessa funzione.

## Sovrapposizione verificata

Sono attivi contemporaneamente:

- **WooCommerce Stripe Gateway**, esposto nel modulo come `Stripe` e selezionato per impostazione predefinita;
- **WooPayments**, esposto nello stesso modulo come `Carta`.

Il donatore vede quindi due scelte carta diverse e la pagina carica risorse frontend di entrambi i gateway. Questa è una sovrapposizione concreta, non soltanto nominale.

Prima di disattivare uno dei due occorre verificare lo storico dei pagamenti e degli accrediti. Il controllo degli ordini ha confermato che **WooPayments è stato realmente usato anche per una donazione completata recente**, mentre Stripe Gateway è il metodo preselezionato nel modulo pubblico corrente. Non è quindi sicuro disattivare subito nessuno dei due: WooPayments serve ancora per consultare e amministrare transazioni/accrediti storici, e Stripe è attualmente offerto per i nuovi pagamenti. La scelta di un solo gateway richiede prima un collaudo di incasso e rimborso sul gateway prescelto, seguito dalla rimozione dell'altro dal checkout senza cancellarne i dati.

## Componenti accessori

- **WooCommerce.com Update Manager**: non gestisce donazioni o pagamenti. Serve agli aggiornamenti e al supporto delle estensioni acquistate su Woo.com. Può essere utile, ma non è parte del flusso di pagamento; prima di rimuoverlo va verificata la provenienza/licenza della piattaforma donazioni e delle altre estensioni.
- **Complianz** e **Complianz - Termini e Condizioni**: non sono componenti WooCommerce; svolgono funzioni distinte. Il secondo genera i termini, il primo gestisce privacy/cookie. Non sono duplicati, anche se va valutato separatamente se entrambi siano ancora usati nei contenuti pubblicati.
- **Divi 100 Hamburger Menu**: non è WooCommerce. È un'estensione molto vecchia e va verificata visivamente prima di una possibile disattivazione; può ancora controllare il menu mobile.

## Ottimizzazione sicura adottata

La versione 3.5.6 rimuove CSS e JavaScript WooCommerce soltanto dalle pagine informative, ripetendo la pulizia anche immediatamente prima della stampa per intercettare gli stili accodati tardivamente. L'ottimizzazione non si applica a:

- prodotti e pagina donazioni;
- negozio e archivi WooCommerce;
- carrello e checkout;
- account utente;
- pagamento ordine e pagina di conferma;
- richieste AJAX/REST WooCommerce;
- pagine che incorporano shortcode o blocchi WooCommerce.

Non vengono disattivati plugin, modificati ordini o cambiati gateway.
