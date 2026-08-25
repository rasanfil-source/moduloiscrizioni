# Modulo Iscrizioni — versione 1.0.0

Plugin WordPress dimostrativo per la Fase 2. Implementa:

- attività con logo tramite immagine in evidenza;
- eventi indipendenti con bozza, pubblicazione e archiviazione;
- apertura e chiusura automatica delle iscrizioni;
- capienza server-side e lista d'attesa;
- più tipologie di iscrizione nello stesso evento;
- profili dati Minimo, Standard e Viaggio, con campi partecipante attivabili singolarmente;
- anteprima amministrativa dei campi e validazione allowlist lato server;
- codice ordine univoco;
- email conservate nell'outbox, con tentativi limitati e acquisizione atomica delle spedizioni;
- editor dell'email di conferma con segnaposto italiani, anteprima sintetica e invio controllato;
- istantanea immutabile del modello risolto nell'outbox e consultazione protetta dal pannello;
- identità storica dell'attività nell'anteprima, con nome, logo e testo alternativo;
- anteprima testuale aggiornata durante la digitazione e blocco dei segnaposto sconosciuti nel browser e sul server;
- identità email per evento con nome mittente, indirizzo per le risposte e fino a dieci destinatari interni validati;
- configurazione economica per sola iscrizione, prezzo informativo, versamento completo oppure caparra e saldo;
- fonti di pagamento ammesse tra bonifico, carta e contante, senza coordinate operative;
- guida dinamica dei campi economici e blocco della pubblicazione per configurazioni incoerenti;
- riepilogo immutabile di totale, primo versamento e saldo per ogni nuova iscrizione;
- importi economici visibili nel pannello e inclusi nell’esportazione CSV;
- totale, caparra e saldo calcolati in tempo reale nel modulo pubblico;
- fonti ammesse mostrate in italiano con registrazione manuale esplicitata;
- replica Workspace del riepilogo economico storico;
- controllo firmato non invasivo dello schema economico Workspace;
- testata pubblica con copertina, data, ora, luogo e identità dell’attività;
- schede orizzontali per tipologia, percorso in tre passaggi e azione primaria ancorata;
- modello pagina “Iscrizione — modalità concentrata” senza navigazione del tema;
- elemento nativo “Modulo iscrizioni” per Divi 4, con scelta dell’evento e rendering server-side del medesimo motore pubblico;
- ruolo `Gestore iscrizioni` limitabile alle attività assegnate;
- shortcode `[modulo_iscrizioni event="ID"]`.

## Limiti intenzionali

Questa versione non invia email, non riscuote pagamenti e non genera QR/barcode. L'editor per evento salva oggetto, preheader, corpo HTML limitato, testo semplice e footer e ne mostra un'anteprima con dati esclusivamente sintetici. I valori dei segnaposto vengono puliti per i contesti testuali e sottoposti a escaping nel corpo HTML. Ogni nuova voce locale conserva il modello risolto e il riepilogo economico. La modalità `ZERO` dichiara esplicitamente un evento gratuito e viene mostrata come “Gratuito”; è compatibile soltanto con “Solo iscrizione”. Modalità, primo versamento, saldo e fonti ammesse vengono replicate a Workspace in modo asincrono dopo il salvataggio locale. Il pannello mostra conteggi e filtro per stato Workspace nel perimetro delle attività accessibili; il dettaglio espone tentativi, ultimo errore e data di sincronizzazione e consente di riaccodare una replica in attesa senza ripetere l’iscrizione. Il pacchetto 0.6.0 non contiene IBAN, collegamenti carta o altre coordinate operative ed è destinato al collaudo sul sito autorizzato.

Il pacchetto descritto in questa pagina è la versione 1.0.0. Parte in modalità email `ANTEPRIMA`; la prova usa soltanto dati sintetici e la modalità `OPERATIVO` richiede prima una prova accettata dal sistema di posta. Le email fallite possono essere riaccodate manualmente dal pannello. Mostra inoltre i posti residui, segnala chiaramente il passaggio alla lista d’attesa, blocca il modulo quando i posti sono esauriti senza lista e ricontrolla la scadenza dentro la transazione degli ultimi posti.

## Installazione di prova

1. comprimere la cartella `modulo-iscrizioni` in uno ZIP;
2. installare lo ZIP da WordPress soltanto nell'ambiente autorizzato;
3. creare una attività e impostarne il logo;
4. creare e pubblicare un evento completo;
5. inserire lo shortcode mostrato nel riquadro “Pubblicazione nel sito”.

Non usare dati reali durante il primo collaudo.
