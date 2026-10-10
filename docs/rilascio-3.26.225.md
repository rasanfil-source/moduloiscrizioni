# Pacchetto locale 3.26.225 — 10 ottobre 2026

Un evento rimane attivo per tutto il giorno della data di inizio e passa tra gli eventi passati alla mezzanotte successiva, nel fuso WordPress. Se manca l'inizio, si usa il giorno della chiusura iscrizioni; l'archiviazione continua a collocarlo subito tra i passati. La regola è condivisa da Gestione iscrizioni, elenco eventi e modelli. Le scadenze delle iscrizioni continuano a rispettare data e ora.

Include il selettore mobile della versione locale 3.26.224. ZIP locale completo e variante pubblica senza configurazione, verificati file per file. Nessun aggiornamento GAS e nessuna installazione remota.

Test: `wordpress-plugin/tests/event-past-day.php` verifica inizio giornata, orario superato, mezzanotte locale, date mancanti, cambio ora legale e scadenze iscrizioni; 186 test strutturali superati.

Come per 3.26.224, il commit Git riguarda questa correzione e i test; non include gli altri lavori locali precedenti né la versione del bootstrap locale. Lo ZIP comprende l'intero plugin locale.
