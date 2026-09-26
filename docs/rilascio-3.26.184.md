# 3.26.184 — conservazione degli a capo nelle email

Il filtro dei modelli per eventi gratuiti ricomponeva le righe con separatori letterali `\n`. Il salvataggio nei metadati WordPress rimuoveva la barra, lasciando `n` o `nn` nel testo e nell'HTML dello snapshot `email_preview`.

Il filtro usa ora veri ritorni a capo. I salvataggi dei modelli proteggono i dati già decodificati con `wp_slash`; testo, HTML e footer normalizzano i separatori espliciti. Quando viene riparato il testo di un modello storico viene aggiornato anche l'HTML derivato. Non vengono aggiunte sostituzioni indiscriminate delle lettere n.

La regressione `wordpress-plugin/tests/email-newlines.php` riproduce lo slashing dei metadati WordPress e verifica salvataggio, rilettura, snapshot e serializzazione JSON per eventi gratuiti e a pagamento, separatori LF/CRLF/CR e letterali, salvataggi ripetuti, HTML personalizzato, footer e parole italiane.

Rilascio solo WordPress: nessun aggiornamento Google Apps Script necessario. Il pacchetto locale conserva la configurazione privata del sito; quello pubblico la esclude. La creazione dei pacchetti non equivale all'installazione sul sito.
