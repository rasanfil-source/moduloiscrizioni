# Modulo Iscrizioni 3.26.265

## Link permanenti delle categorie di un gruppo

In **Gruppi**, sia in creazione sia in configurazione, è disponibile la sezione **Link permanenti per le iscrizioni**. Ogni gruppo può avere fino a 20 categorie, ciascuna con un indirizzo distinto, per esempio:

- Catechesi: `/12cesteroma`.
- Gite e altri eventi: `/12cesteeventi`.

Nel primo passaggio della creazione o modifica completa dell'evento, dopo il gruppo organizzatore, si sceglie **Categoria / link permanente**. La scelta è facoltativa: «Nessuno» conserva il solo link individuale. Le categorie proposte appartengono al gruppo selezionato; il controllo viene ripetuto sul server. La categoria viene applicata al salvataggio dell'evento; una nuova bozza non diventa raggiungibile finché non viene pubblicata.

Il link permanente apre, con reindirizzamento temporaneo 302, l'evento pubblicato con la data di inizio più vicina della propria categoria. Per coerenza con il portale l'evento resta selezionabile durante tutta la giornata di inizio, nel fuso orario WordPress. L'ordinamento usa la data effettivamente pubblicata, senza anticipare le modifiche in bozza. In caso di parità viene scelto l'ID evento minore. Sono esclusi eventi in bozza, privati, passati, annullati, archiviati, eliminati o non disponibili al pubblico.

Non viene applicato alcun filtro sull'apertura o chiusura delle iscrizioni: dopo il reindirizzamento il modulo esistente continua a gestire iscrizioni non ancora aperte, chiuse, posti esauriti e lista d'attesa. I link individuali degli eventi e delle prenotazioni restano distinti.

Quando manca un prossimo evento pubblico della categoria, viene mostrata una pagina di attesa con titolo e messaggio personalizzabili e un'immagine facoltativa (JPG, PNG o WebP, massimo 2 MB). In assenza di immagine specifica viene usata la copertina del gruppo, con il logo come riserva. Se manca anche questa, resta il messaggio testuale. I campi vuoti usano titolo e messaggio predefiniti.

Gli indirizzi salvati restano riservati al gruppo e non si rinominano; è possibile disattivarli e riattivarli. Un link disattivato non apre eventi. Per un altro indirizzo si aggiunge una categoria. Le collisioni con pagine del sito, indirizzi riservati e link di altri gruppi sono respinte; i link evento `/v/...` usano uno spazio distinto.

## Attivazione sul sito

1. Installare il nuovo ZIP WordPress e aprire **Gruppi → Configura → Link permanenti per le iscrizioni**.
2. Aggiungere e salvare le categorie e gli indirizzi desiderati. Non vengono creati automaticamente gruppi o collegamenti nel sito operativo.
3. Nei dettagli degli eventi già presenti, scegliere la categoria pertinente e salvare. I nuovi eventi si collegheranno scegliendo la categoria in creazione.
4. Se `/12cesteroma` appartiene allo stesso sito WordPress, rimuovere **soltanto** la precedente riga personalizzata `RewriteRule ^12cesteroma/?$ ... [R=302,L]` dal `.htaccess`, dopo aver configurato la funzione. Conservare le normali regole WordPress. Il plugin non modifica il `.htaccess`.
5. Verificare i due indirizzi da una finestra privata. Il server deve inoltrare questi percorsi a WordPress, come già avviene per i link abbreviati `/v/...`. Se una cache esterna serve pagine prima di WordPress, escludere i link permanenti dalla cache: il plugin invia intestazioni contro la memorizzazione quando gestisce la richiesta.

Se il vecchio indirizzo appartiene a un altro server, conservarne invece il redirect puntandolo una sola volta al link permanente sul sito WordPress.

Non è necessario aggiornare Apps Script **MODULI AUTONOMO**.

## Verifiche

- Suite locale `tools/verify.ps1`: controlli Node, sintassi PHP, test PHP, asset minificati e sanitizzazione.
- Test dedicati: categorie distinte, collisioni e concorrenza, gruppo corretto, date pubblicate, iscrizioni non ancora aperte, eventi annullati/archiviati/in bozza, cambio automatico, redirect temporaneo senza query estranee, pagina di attesa e contenuti protetti.
- Browser locale con HTML generato dai renderer PHP e dati sintetici: creazione e gestione, aggiunta/rimozione righe nuove, categoria filtrata per gruppo, schermate desktop/mobile e immagine di attesa.
- ZIP confrontati file per file con i sorgenti; archivi precedenti conservati invariati. Nessuna installazione o modifica del server di produzione eseguita.

Il pacchetto locale conserva la configurazione locale già presente; la variante `-pubblico` esclude `public-balance-config.json`.
