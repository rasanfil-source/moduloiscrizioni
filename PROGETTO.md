# Progetto: sistema multi-evento di iscrizione e pagamento

Stato: bozza funzionale e tecnica 0.6  
Data: 24 agosto 2026

## Artefatti della Fase A

- [Decisioni operative](docs/DECISIONI_FASE_A.md)
- [Schema dati iniziale](docs/SCHEMA_DATI.md)
- [Configurazione evento di esempio](schema/evento.example.json)
- [Riferimenti legacy e linea visiva](docs/RIFERIMENTI_E_LINEE_GUIDA.md)
- [Prototipo responsive](prototipo/README.md)

## 1. Obiettivo

Realizzare un sistema riutilizzabile per più eventi, integrato in WordPress, che permetta di:

- mostrare una pagina di iscrizione coerente con l'evento e ottimizzata per mobile e desktop;
- prenotare uno o più posti;
- raccogliere i dati del referente e di ogni partecipante;
- configurare opzioni gratuite o a pagamento, per ordine o per singolo biglietto;
- ricalcolare e mostrare il riepilogo economico;
- registrare l'ordine in Google Sheets tramite Google Apps Script;
- inviare al referente un'email riepilogativa affidabile e configurabile;
- proporre il pagamento tramite bonifico, link carta esterno, contanti o una combinazione configurabile;
- amministrare eventi, schermate, email e prenotazioni da WordPress e consultare nel pannello i pagamenti acquisiti manualmente dallo spreadsheet;
- rappresentare la parrocchia come ente base e le sue diverse attività come organizzatori distinti, ciascuno con identità visiva e logo propri.

Il sistema deve partire da uno schema iniziale ben progettato. L'amministratore potrà personalizzare contenuti e regole senza poter compromettere struttura, accessibilità o sicurezza inserendo JavaScript arbitrario.

## 2. Architettura consigliata

```text
Pagina dell'evento WordPress
        |
        | pulsante Iscriviti + event slug
        v
Wizard HTML/CSS/JavaScript su WordPress
        |
        | richieste sullo stesso dominio
        v
Piccolo plugin WordPress
  - shortcode/blocco
  - API REST proxy
  - autenticazione amministratori
  - rate limit / CAPTCHA
        |
        | richiesta server-to-server firmata
        v
Google Apps Script Web App
  - configurazione eventi
  - validazione e prezzi
  - capienza e idempotenza
  - scrittura ordini
  - coda email
        |
        v
Google Sheets privato
```

Il frontend rimane HTML, CSS e JavaScript. Il piccolo plugin PHP è necessario per usare in sicurezza gli account WordPress, mantenere i segreti fuori dal browser, evitare i problemi CORS e proteggere le funzioni amministrative.

Se il piano WordPress non consente plugin personalizzati, sarà necessario scegliere una variante meno integrata: interfaccia ospitata da Apps Script e amministratori autenticati con account Google.

### Gerarchia organizzativa e branding

La parrocchia è l'ente base e il soggetto legale di riferimento. Contiene una o più attività e ogni evento appartiene obbligatoriamente a una sola attività. Il branding viene risolto per singola proprietà seguendo `predefinito plugin -> parrocchia -> attività -> evento`.

Logo, colori e contatti pubblici possono essere ereditati o sovrascritti entro una allowlist. Il branding non può invece modificare identità legale, titolare del trattamento, informativa privacy o responsabilità della parrocchia. Logo e immagine editoriale dell'evento restano asset distinti.

## 3. Flusso pubblico

Un'unica pagina riutilizzabile, per esempio:

```text
/iscrizione/?evento=cammino-2027
```

Il sistema usa un solo wizard responsive, non due applicazioni diverse.

### Passaggio 1: riepilogo evento

- logo dell'attività organizzatrice, oppure logo specifico dell'evento se configurato;
- nome testuale dell'attività e riferimento alla parrocchia, visibili anche quando il logo contiene già del testo;
- immagine e testo alternativo;
- titolo, date, ora, luogo e descrizione breve;
- stato delle iscrizioni e disponibilità, se configurata;
- prezzo iniziale o indicazione di evento gratuito;
- avvertenze preliminari;
- pulsanti `Iscriviti` e `Torna all'evento`.

### Passaggio 2: posti e tipi di biglietto

- numero di posti con minimo e massimo per ordine;
- eventuali tipi di biglietto, per esempio adulto, minore o accompagnatore;
- prezzo e disponibilità per tipo;
- subtotale aggiornato in tempo reale.

### Passaggio 3: referente

- nome;
- cognome;
- email;
- telefono cellulare con prefisso internazionale;
- eventuali campi aggiuntivi configurati per l'ordine.

### Passaggio 4: partecipanti

- tante schede `Biglietto n` quanti sono i posti;
- il primo biglietto viene precompilato con il referente;
- opzione `Il referente partecipa` per gestire chi prenota per altre persone;
- nome, cognome, email e cellulare per ogni partecipante, con obbligatorietà configurabile;
- eventuali campi aggiuntivi per il singolo biglietto;
- pannelli espandibili su mobile e schede più ampie su desktop.

### Passaggio 5: opzioni, consensi e riepilogo

- opzioni riferite all'intero ordine;
- opzioni riferite a ogni biglietto;
- dettaglio di prezzi, quantità, maggiorazioni e totale;
- informativa privacy e termini;
- eventuali accettazioni obbligatorie;
- consenso marketing separato, facoltativo e non preselezionato;
- pulsante `Concludi la prenotazione`.

Il totale visualizzato dal browser è soltanto un'anteprima. Apps Script ricalcola sempre il totale usando la configurazione pubblicata dell'evento.

### Passaggio 6: conferma e pagamento

Prima di mostrare le istruzioni di pagamento, il server crea un ordine con codice univoco. La pagina finale mostra:

- codice prenotazione;
- stato della prenotazione;
- partecipanti e opzioni;
- importo totale e importo da versare ora;
- avvertenze finali configurate per l'evento;
- metodi di pagamento ammessi;
- indicazione dell'email riepilogativa inviata.

La pagina non deve confondere `prenotazione registrata` con `pagamento ricevuto`.

## 4. Esperienza mobile e desktop

### Mobile

- colonna unica;
- immagine evento compatta;
- indicatore `Passaggio n di n`;
- controlli di almeno 44 x 44 px;
- riepilogo totale richiudibile;
- pulsante principale fisso in basso, rispettando la safe area;
- biglietti in pannelli espandibili.

### Desktop

- contenitore massimo di circa 1100-1200 px;
- modulo a sinistra;
- riepilogo ordine sticky a destra;
- immagine e informazioni evento affiancate nella prima schermata.

In entrambi i casi devono essere preservati i dati tornando indietro, ma i dati personali non vanno lasciati permanentemente in `localStorage`.

### Prestazioni e isolamento nel sito WordPress

Il plugin non deve appesantire la navigazione delle altre pagine del sito.

- CSS e JavaScript pubblici vengono accodati soltanto quando la pagina contiene il blocco o shortcode del modulo;
- gli asset amministrativi vengono caricati soltanto nelle schermate del plugin, mai in tutto `wp-admin`;
- nessuno stile usa selettori globali e nessuno script modifica elementi esterni al contenitore del plugin;
- il plugin non carica una seconda copia di font o librerie già presenti nel tema: usa stack compatibili e fallback di sistema;
- lo script pubblico è caricato con `defer`, suddiviso per responsabilità e privo di dipendenze frontend pesanti nel primo rilascio;
- configurazione evento e disponibilità vengono richieste soltanto nella pagina del modulo, con cache pubblica breve e invalidazione alla pubblicazione dell'evento;
- immagini e loghi usano formati ottimizzati, dimensioni responsive, attributi `width` e `height` e caricamento lazy quando non immediatamente visibili;
- il DOM contiene principalmente il passaggio corrente; le schede dei biglietti vengono create su richiesta;
- nessuna chiamata a GAS, scansione del foglio o elaborazione della coda email viene eseguita durante le normali visite alle altre pagine WordPress;
- hook, query e controlli del plugin escono immediatamente quando la richiesta non riguarda modulo, API o pannello iscrizioni;
- il collaudo confronta almeno home page e pagina evento con plugin attivo ma modulo assente, verificando che non compaiano asset o richieste del plugin.

Budget iniziale del frontend pubblico, da verificare sulle build di produzione: JavaScript proprio del plugin entro circa 80 KB compressi, CSS entro circa 35 KB compressi e nessuna dipendenza che blocchi il rendering. Eventuali superamenti richiedono una motivazione e un nuovo test prestazionale.

### Direzione grafica e semplicità d'uso

La qualità visiva è un requisito funzionale del progetto. L'interfaccia predefinita deve essere elegante, chiara e immediatamente comprensibile anche per chi usa raramente moduli online.

Principi obbligatori:

- una sola azione primaria evidente per schermata;
- pochi campi visibili alla volta e informazioni secondarie mostrate progressivamente;
- ampio spazio bianco, allineamenti coerenti e nessun elemento puramente decorativo che distragga;
- caratteri leggibili, gerarchia tipografica netta e righe di testo non troppo lunghe;
- colori sobri, con il colore dell'evento usato soprattutto per CTA, focus e stato attivo;
- contrasto conforme a WCAG 2.2 AA e informazioni mai affidate soltanto al colore;
- riepilogo prezzi semplice, con totale chiaramente distinto dalle singole voci;
- messaggi di errore brevi, specifici e vicini al campo interessato;
- stati di caricamento rassicuranti e protezione dai doppi click;
- animazioni brevi e discrete, disattivabili con `prefers-reduced-motion`;
- stile coerente con il sito WordPress senza dipendere dal tema per la leggibilità;
- tutti gli stili racchiusi nel contenitore del plugin, senza selettori globali come `body`, `h1`, `button` o `input` che possano modificare il resto della pagina WordPress;
- immagini ottimizzate e mai prevalenti rispetto all'azione da compiere.

Componenti visivi di base:

- card evento con immagine, data, luogo e stato;
- stepper compatto;
- campi con label sempre visibile e testo di aiuto essenziale;
- selettore quantità chiaro;
- card biglietto numerata;
- gruppi di opzioni facilmente confrontabili;
- riepilogo ordine sticky su desktop e richiudibile su mobile;
- modali soltanto per conferme brevi, non per interi passaggi del form;
- schermata finale con codice prenotazione, stato e prossima azione.

Prima dello sviluppo definitivo verranno preparati e approvati almeno quattro wireframe: riepilogo evento, selezione posti, partecipanti/riepilogo e conferma-pagamento, ciascuno in versione mobile e desktop. Successivamente verrà definito un piccolo design system con colori, tipografia, spaziature, raggi, ombre, icone e stati dei componenti.

## 5. Configurazione dell'evento

Ogni evento viene creato a partire da un preset controllato:

- evento gratuito;
- prenotazione con pagamento completo;
- prenotazione con caparra e saldo successivo;
- semplice raccolta nominativi.

Il pannello è suddiviso in sezioni.

### Ente e attività

- la parrocchia è l'ente base dell'installazione;
- l'ente contiene una o più attività organizzatrici, ciascuna con ID stabile, nome, slug, stato e contatti;
- ogni attività può configurare il proprio logo dalla Media Library, con testo alternativo obbligatorio;
- ogni evento appartiene obbligatoriamente a una sola attività;
- il branding effettivo segue l'ordine `predefinito di sistema -> ente -> attività -> evento`, dove i livelli successivi possono sovrascrivere soltanto i valori esplicitamente configurati;
- il logo dell'attività viene usato automaticamente dagli eventi collegati; un evento può impostare un logo specifico senza modificare quello dell'attività;
- la cancellazione di un'attività con eventi collegati non è consentita: può essere archiviata oppure i suoi eventi devono essere prima riassegnati.

### Generale

- ID immutabile e slug pubblico;
- attività organizzatrice;
- stato `Bozza`, `Pubblicato`, `Chiuso`, `Annullato`, `Archiviato`;
- titolo, descrizioni e URL della pagina WordPress;
- date, orari, fuso orario e luogo;
- immagine dalla Media Library e testo alternativo;
- contatti dell'organizzatore.

### Iscrizioni e capienza

- apertura e chiusura automatica;
- capienza complessiva;
- capienza per tipo di biglietto;
- minimo e massimo posti per ordine;
- comportamento a esaurimento posti;
- eventuale lista d'attesa;
- regola di scadenza degli ordini in attesa di pagamento.

### Tipi di biglietto e campi

- nome, descrizione, prezzo e disponibilità del tipo di biglietto;
- campi del referente e del partecipante;
- visibilità, obbligatorietà, aiuto e validazione;
- campi personalizzati di tipo testo, email, telefono, data, scelta e checkbox;
- ambito `ordine` oppure `biglietto`.

### Opzioni

- gruppo e scelte;
- ambito `per_order` oppure `per_ticket`;
- controllo checkbox, scelta singola, menu o quantità;
- prezzo fisso, per posto o per unità;
- obbligatorietà, quantità minima/massima e disponibilità;
- ordine di visualizzazione e testo di aiuto.

### Schermate e messaggi

- titolo, introduzione, testo di aiuto e CTA per ogni passaggio;
- visibilità dell'immagine;
- avvisi informativi;
- avvisi che richiedono accettazione prima dell'invio;
- messaggi finali di conferma;
- anteprima desktop e mobile.

Si consente testo semplice o rich text filtrato con una allowlist. Non si consente JavaScript personalizzato nei contenuti dell'evento.

### Aspetto

- colore principale e secondario entro limiti di contrasto;
- logo ereditato dall'attività, con eventuale override dell'evento, e immagine evento;
- testo alternativo obbligatorio per ogni logo e immagine;
- etichette principali;
- anteprima accessibile.

Il layout e i componenti restano controllati dal sistema per evitare che un singolo evento rompa il wizard.

## 6. Pagamenti

Metodi configurabili per evento:

- nessun pagamento;
- bonifico;
- carta tramite link esterno;
- contanti;
- qualsiasi combinazione dei metodi precedenti;
- futura integrazione API merchant.

Modalità economiche:

- saldo completo al momento dell'iscrizione;
- caparra fissa per ordine;
- caparra fissa per biglietto;
- caparra percentuale;
- saldo successivo;
- ordine gratuito.

Tutti gli importi sono memorizzati come centesimi interi.

Caparra, saldo e profili di pagamento sono sempre configurazioni dell'evento, eventualmente ereditate dall'attività o dalla parrocchia: non diventano costanti globali. Le rate attese sono registrate in `Payments` con tipo `FULL`, `DEPOSIT` oppure `BALANCE`; lo stato aggregato dell'ordine viene derivato dalle rate e non sostituisce il loro dettaglio.

### Registrazione manuale dei versamenti in Google Sheets

Nel primo rilascio i dati dei versamenti vengono inseriti manualmente nel foglio `PaymentIntake` dello stesso spreadsheet che contiene ordini e partecipanti. Questo foglio è un'area di acquisizione controllata, distinta dal registro canonico e protetto `Payments`, e usa colonne e convalide controllate:

- codice ordine;
- data del versamento;
- importo ricevuto;
- fonte obbligatoria scelta da elenco: `BANK_TRANSFER` (`Bonifico / IBAN`), `EXTERNAL_CARD` (`Carta`) oppure `CASH` (`Contante`);
- riferimento esterno facoltativo, per esempio CRO/TRN o ID della transazione, senza dati completi della carta;
- nota amministrativa facoltativa;
- operatore e data di registrazione, quando disponibili;
- esito della validazione e messaggio di errore.

Le colonne tecniche, tra cui `payment_id`, `order_id`, importo canonico in centesimi e stato, sono generate nel registro `Payments`. Una riga di acquisizione conta ai fini di `PARTIALLY_PAID` o `PAID` soltanto dopo che Apps Script ha verificato ordine, valuta, importo e duplicati e ha creato la corrispondente riga canonica. Per i contanti sono obbligatori data, importo e operatore; non vengono mai salvati numeri di carta, codici di sicurezza o altri dati dello strumento di pagamento.

Il foglio può contenere più righe per lo stesso ordine, così caparra, saldo e pagamenti parziali restano ricostruibili. La convalida avviene tramite un'azione Apps Script esplicita o un trigger installabile, non tramite formule considerate autorevoli. La correzione non sovrascrive silenziosamente una riga già convalidata: genera una rettifica auditabile o richiede un'azione amministrativa esplicita.

`PaymentIntake` e le colonne tecniche di `Payments` sono protetti e modificabili soltanto da operatori finanziari globali autorizzati come editor Google dell'intero spreadsheet. Poiché un editor del foglio può vedere dati di più attività, questo accesso non viene concesso ai normali delegati limitati per attività. Questi ultimi continuano a vedere soltanto i pagamenti del proprio ambito attraverso WordPress.

Il canale Sheet è un secondo confine di fiducia, separato dal flusso WordPress -> GAS: l'audit registra `channel = MANUAL_SHEET` e, quando Google rende disponibile l'identità dell'editor, il relativo account. Se l'identità non è disponibile, l'operatore deve essere indicato esplicitamente e l'audit segnala che non è stato verificato automaticamente. Se in futuro servissero operatori finanziari limitati a una sola attività, non verrà condiviso questo spreadsheet globale: si userà il pannello WordPress o un intake separato che non esponga dati delle altre attività.

### Bonifico

Per evento si configurano:

- intestatario;
- IBAN ed eventuale BIC;
- importo dovuto;
- scadenza;
- istruzioni;
- modello della causale.

La causale deve includere un codice ordine univoco e non dipendere soltanto dai cognomi.

### Contanti

Se l'evento accetta contanti, configura luogo, orari o referente, scadenza ed eventuali istruzioni per la ricevuta. La pagina pubblica può mostrare queste indicazioni, ma la scelta o la promessa di pagamento non imposta mai lo stato `PAID`.

Quando il denaro viene effettivamente ricevuto, l'operatore inserisce in `PaymentIntake` codice ordine, data, importo e fonte `CASH`; l'operatore è obbligatorio e l'eventuale numero di ricevuta può essere usato come riferimento esterno.

### Link carta Banca Profilo / Tinaba attuale

La pagina fornita, `https://crowdfunding.bancaprofilo.it/donate-with-card/1020284`, chiede all'utente importo, nome, cognome, email e un messaggio facoltativo. Il link non contiene oggi un checkout specifico dell'ordine né un ritorno applicativo.

Il flusso iniziale sarà quindi:

1. creare l'ordine;
2. mostrare importo e codice ordine con pulsanti di copia;
3. chiedere di inserire il codice nel campo messaggio;
4. aprire il link HTTPS in una nuova scheda con `noopener,noreferrer`;
5. lasciare il pagamento in stato `PENDING`;
6. consentire all'amministratore di riconciliare e confermare manualmente il versamento.

Il semplice click sul link non imposta mai lo stato `PAID`.

### Possibile integrazione Tinaba Pay

Se l'organizzazione possiede un contratto merchant e le relative credenziali, si può realizzare una seconda integrazione:

- creazione checkout con `externalId` uguale al riferimento pagamento;
- importo in centesimi calcolato dal server;
- callback server-to-server;
- verifica firma, importo, valuta e ID;
- controllo periodico dei checkout rimasti pendenti;
- aggiornamento automatico dello stato e invio dell'email di pagamento ricevuto.

Le credenziali merchant non devono mai essere inserite nell'HTML o nel foglio. Nessun dato carta passa da WordPress, GAS o Google Sheets.

### Stati separati

Prenotazione:

- `DRAFT`;
- `HELD`;
- `CONFIRMED`;
- `WAITLISTED`;
- `EXPIRED`;
- `CANCELLED`.

Pagamento:

- `NOT_REQUIRED`;
- `PENDING`;
- `PARTIALLY_PAID`;
- `PAID`;
- `FAILED`;
- `EXPIRED`;
- `REFUNDED`;
- `PARTIALLY_REFUNDED`.

## 7. Email configurabili

Il salvataggio dell'ordine e l'invio email sono operazioni separate. Dopo il commit dell'ordine viene creata una voce nella coda email. Un errore di posta non annulla né duplica la prenotazione.

Template previsti per ogni evento:

- ordine ricevuto / riepilogo iscrizione;
- istruzioni e attesa pagamento;
- pagamento ricevuto;
- promemoria pagamento;
- annullamento;
- notifica interna agli organizzatori.

### Email riepilogativa immediata

Deve includere almeno:

- titolo, data e luogo dell'evento;
- codice ordine e stato;
- referente;
- elenco dei biglietti e dei partecipanti;
- opzioni selezionate;
- dettaglio economico e totale;
- importo dovuto ora;
- istruzioni per bonifico, carta e/o contanti, secondo i metodi attivi;
- scadenza e causale univoca;
- avvertenze finali e contatti;
- link alla privacy e alle condizioni applicate.

### Editor template

Il pannello permette di configurare:

- nome visualizzato del mittente;
- reply-to;
- destinatari interni;
- oggetto, preheader, corpo e footer;
- versione HTML e testo semplice;
- anteprima con dati di esempio;
- invio di un'email di prova;
- attivazione/disattivazione per tipo di messaggio.

I template usano una lista controllata di segnaposto, per esempio:

```text
{{event.title}}
{{event.start_date}}
{{organization.name}}
{{activity.name}}
{{brand.logo_url}}
{{order.code}}
{{order.status}}
{{buyer.full_name}}
{{order.total}}
{{payment.amount_due}}
{{payment.deadline}}
{{payment.reference}}
{{order.summary_table}}
```

Tutti i dati utente sono sottoposti a escaping. I destinatari interni provengono dalla configurazione protetta, non dal payload pubblico. Il sistema registra stato, tentativi ed eventuale errore di invio e può ritentare senza duplicare il messaggio.

L'intestazione dell'email usa il branding risolto della revisione pubblicata, mentre il footer identifica sempre la parrocchia. Il nome dell'attività rimane anche in forma testuale perché i client di posta possono bloccare le immagini remote.

## 8. Pannello amministrativo WordPress

Sezioni previste:

- dashboard con eventi attivi, iscritti, capienza, ordini e pagamenti pendenti;
- elenco e gestione delle attività della parrocchia, inclusi logo, contatti e branding predefinito;
- elenco, creazione, duplicazione, anteprima, pubblicazione e archiviazione eventi;
- filtri di dashboard, eventi, ordini e pagamenti per attività, nel rispetto dell'ambito assegnato all'utente;
- avviso e ripubblicazione controllata degli eventi interessati quando cambia il branding di un'attività;
- editor guidato di tutte le configurazioni descritte sopra;
- anteprima mobile e desktop;
- ricerca e filtri delle prenotazioni;
- dettaglio ordine, biglietti, opzioni, email e pagamenti;
- cambio stato, annullamento e note amministrative;
- consultazione e convalida dei versamenti acquisiti da `PaymentIntake`, inclusi bonifico, carta e contanti;
- reinvio email;
- esportazione CSV sicura;
- audit delle modifiche;
- diagnostica connessione GAS, foglio e coda email.

### Accesso

Si usa sempre un normale account WordPress: il plugin non introduce una seconda password né una seconda sessione.

- durante l'attivazione le capability del plugin vengono assegnate agli amministratori WordPress, che risultano quindi autorizzati automaticamente senza ulteriori accreditamenti; i controlli usano `current_user_can()` e non confrontano lo slug del ruolo;
- per le persone delegate viene creato un ruolo dedicato, per esempio `Gestore iscrizioni`, privo dei permessi di amministrazione generale di WordPress;
- il ruolo dedicato possiede soltanto `read` e capability granulari del plugin, per esempio gestione eventi, ordini, pagamenti, email e attività assegnate;
- gli amministratori WordPress hanno scope su tutte le attività; ogni delegato riceve invece una lista esplicita di una o più attività, che non si estende automaticamente alle attività create in futuro;
- dopo l'accesso il delegato viene indirizzato al pannello iscrizioni e vede soltanto le relative voci di menu;
- nascondere le altre pagine di `wp-admin` è soltanto una misura di interfaccia: ogni pagina, endpoint REST e azione verifica comunque capability e ambito attività lato server;
- i delegati non ricevono capability come `manage_options`, `edit_users`, `install_plugins`, `edit_posts` o accesso alle impostazioni generali del sito;
- nel primo rilascio i delegati possono scegliere immagini già approvate tramite un selettore controllato del plugin; il caricamento di nuovi file nella Media Library resta riservato agli amministratori WordPress;
- creazione, sospensione e assegnazione dei delegati restano riservate agli amministratori WordPress, salvo futura delega granulare esplicita;
- la password, il recupero dell'account, l'eventuale autenticazione a due fattori e la sessione vengono gestiti da WordPress;
- ogni endpoint privato controlla capability e nonce;
- ogni modifica importante viene registrata nell'audit.

Una password condivisa aggiuntiva è sconsigliata. Se fosse obbligatoria, verrebbe verificata soltanto nel backend WordPress e salvata esclusivamente come hash.

## 9. Modello Google Sheets

Si usa un unico spreadsheet per tutti gli eventi, con ID stabili e non con numeri di riga come identità.

| Foglio | Contenuto |
|---|---|
| `Settings` | versione schema, ambiente e impostazioni tecniche non segrete |
| `Organizations` | ente base, denominazione, contatti e branding predefinito |
| `Activities` | attività organizzatrici, logo, contatti, branding, stato e riferimento all'ente |
| `Events` | attività proprietaria, dati generali, stato, date, capienza, override branding e revisione pubblicata |
| `Screens` | testi e impostazioni dei passaggi del wizard |
| `TicketTypes` | tipi di biglietto, prezzi e disponibilità |
| `CustomFields` | campi aggiuntivi e regole di validazione |
| `OptionGroups` | gruppi di opzioni e ambito ordine/biglietto |
| `OptionChoices` | scelte, prezzi, quantità e stock |
| `Messages` | avvisi, consensi e messaggi finali |
| `PaymentMethods` | metodi, modalità, scadenze e istruzioni per evento |
| `EmailTemplates` | template versionati e configurazione invio |
| `Orders` | ordine autorevole, ente, attività, referente, totali, stati, revisione evento, branding risolto e idempotenza |
| `Tickets` | partecipanti e tipo biglietto |
| `Selections` | opzioni scelte con etichetta e prezzo fotografati |
| `PaymentIntake` | area protetta per l'inserimento manuale di codice ordine, data, importo, fonte e riferimento |
| `Payments` | righe canoniche con importi attesi/ricevuti, fonte `BANK_TRANSFER`/`EXTERNAL_CARD`/`CASH`, stato, riferimento, canale e verifica |
| `EmailOutbox` | messaggi da inviare, stato, tentativi ed errore |
| `AuditLog` | utente, data, azione, entità e differenze essenziali |

L'ordine conserva anche uno snapshot canonico JSON. Se una scrittura secondaria fallisce, una procedura di ripristino può ricostruire biglietti e opzioni senza perdere la prenotazione.

## 10. API e regole di integrità

Endpoint WordPress indicativi:

```text
GET  /wp-json/eventi/v1/events/{slug}
POST /wp-json/eventi/v1/orders
GET  /wp-json/eventi/v1/confirmation/{token}
GET  /wp-json/eventi/v1/admin/events
POST /wp-json/eventi/v1/admin/events
PATCH /wp-json/eventi/v1/admin/events/{id}
GET  /wp-json/eventi/v1/admin/organization
PATCH /wp-json/eventi/v1/admin/organization
GET  /wp-json/eventi/v1/admin/activities
POST /wp-json/eventi/v1/admin/activities
PATCH /wp-json/eventi/v1/admin/activities/{id}
GET  /wp-json/eventi/v1/admin/orders
POST /wp-json/eventi/v1/admin/payments/{id}/confirm
POST /wp-json/eventi/v1/admin/emails/{id}/retry
```

Il plugin firma ogni richiesta WordPress -> GAS con HMAC-SHA256, timestamp e nonce anti-replay. Il segreto rimane sul server WordPress e nelle Script Properties.

La configurazione pubblica dell'evento contiene anche uno snapshot del branding effettivo già risolto. Il browser non decide quale logo usare e non costruisce URL arbitrari: riceve URL, testo alternativo e colori validati dal backend.

La pubblicazione verifica anche relazione tra evento e attività, stato dell'attività, revisione delle dipendenze, URL HTTPS degli asset, testo alternativo, dimensioni e contrasto dei colori. Una modifica al branding dell'attività marca gli eventi dipendenti come da ripubblicare, senza cambiare retroattivamente revisioni e ordini storici.

Inviluppo provvisorio di collaudo, non SLA: fino a circa 500 ordini e 1.000 biglietti per evento, 10 invii finali simultanei e una prova di contesa con almeno 20 richieste sugli ultimi posti. I volumi reali devono essere confermati prima della produzione; se sono sensibilmente superiori, l'uso di Sheets/GAS come archivio autorevole viene rivalutato.

Creazione ordine:

1. validare schema e limiti del payload;
2. caricare la revisione pubblicata dell'evento;
3. verificare finestra iscrizioni, biglietti e opzioni;
4. ricalcolare tutti gli importi in centesimi;
5. acquisire uno `ScriptLock` breve;
6. verificare idempotenza e posti disponibili;
7. scrivere l'ordine canonico e riservare i posti;
8. rilasciare il lock;
9. espandere le righe collegate in modo idempotente;
10. accodare le email;
11. restituire codice ordine e token opaco di conferma.

Email, callback e chiamate esterne non rimangono dentro il lock.

## 11. Sicurezza e privacy

- nessuna chiave o password nell'HTML;
- nessuna lookup pubblica per nome o cognome;
- prezzi e disponibilità sempre verificati dal server;
- UUID per ordine e biglietti;
- chiave di idempotenza per ogni invio finale;
- CAPTCHA e rate limit sul proxy WordPress;
- limiti di lunghezza e rifiuto di proprietà inattese;
- neutralizzazione della formula injection in Sheets e CSV;
- testo configurabile filtrato contro stored XSS;
- loghi e immagini selezionati dalla Media Library; SVG disabilitati per impostazione predefinita oppure ammessi soltanto dopo sanitizzazione esplicita;
- foglio privato e accessi minimi;
- niente dati personali nei log ordinari;
- versione dell'informativa e delle accettazioni salvata nell'ordine;
- conservazione configurabile e procedura di esportazione/cancellazione;
- particolare attenzione a dati di minori, salute, allergie o disabilità.

## 12. Riuso del codice esistente

`HTM.txt` del 21 aprile 2026 è la revisione più recente rispetto a `saldo_cammino.html` del 2 aprile 2026.

Pattern utili da riutilizzare:

- layout a due colonne e riepilogo sticky;
- CTA mobile fissa;
- card dinamiche per i partecipanti;
- riepilogo economico live;
- modali con gestione del focus;
- stati loading/success/error con `aria-live`;
- copia di importo, IBAN e causale;
- email HTML e testo semplice;
- formattazione italiana degli importi;
- `LockService` e `CacheService`, con ambito ridisegnato.

Elementi da sostituire:

- evento, prezzi, scadenza, IBAN, BCC e link carta hardcoded;
- API GAS pubblica con `API_KEY` vuota;
- lookup di persone per cognome/nome;
- identificazione tramite numero di riga;
- totale, caparra e saldo accettati dal browser;
- invio email non idempotente;
- assenza di controllo capienza e stato pagamento;
- causale basata soltanto sui cognomi.

Il vecchio modulo del saldo può diventare una funzione separata del nuovo sistema: accesso tramite link firmato e token ordine, ricalcolo server-side, pagamento del residuo e aggiornamento dello stesso registro `Payments`.

### Prototipo iscrizione Assisi 2026

I file `iscrizione_assisi.html` e `gas_iscrizione.js` costituiscono un riferimento ancora più vicino al nuovo flusso di iscrizione. Dimostrano già:

- aggiunta e rimozione dinamica di più partecipanti;
- opzioni a pagamento per ogni persona;
- totale aggiornato in tempo reale;
- layout desktop con modulo e riepilogo laterale;
- adattamento a una colonna su schermi piccoli;
- salvataggio in Google Sheets;
- email HTML riepilogativa;
- passaggio finale a un link carta esterno.

Componenti da evolvere nel nuovo frontend:

- la card `Partecipante` diventa la card configurabile `Biglietto n`;
- il riepilogo laterale resta sticky su desktop e diventa richiudibile/fisso su mobile;
- le opzioni Pullman e Pranzo diventano normali `OptionChoices` per-ticket;
- il template email Assisi diventa uno dei preset dell'editor email;
- il blocco del pulsante durante l'invio e il totale live restano comportamenti standard;
- il blu sobrio, le card bianche e la gerarchia semplice possono ispirare il tema visuale iniziale, con controlli più ampi e maggiore cura tipografica.

Aspetti da non trasferire direttamente:

- singola schermata lunga invece del wizard a passaggi;
- referente coincidente obbligatoriamente con il primo partecipante;
- prezzi, data, evento, destinatario interno e link carta hardcoded;
- totale estratto dal testo della pagina e accettato dal server;
- una riga indipendente per partecipante senza `order_id` e `ticket_id`;
- progressivo ricavato dall'ultima riga del foglio;
- molte `appendRow` senza lock, capienza o idempotenza;
- invio email nello stesso processo di scrittura;
- risposta di successo anche dopo errori di scrittura o di posta;
- dati utente inseriti nell'email senza escaping;
- nessuna informativa, consenso versionato o stato pagamento;
- Web App incorporabile da qualunque sito tramite `ALLOWALL`.

Il nuovo ordine viene prima registrato in modo autorevole e riceve un codice univoco. Soltanto dopo vengono accodate email ed espanse le righe dei partecipanti. In questo modo un timeout, un doppio click o un errore di posta non produce iscrizioni duplicate o false conferme.

## 13. Fasi di realizzazione

### Fase A: decisioni e prototipo

- risposte alle decisioni aperte;
- modello ente, attività ed evento con regole di ereditarietà del branding;
- wireframe responsive;
- schema configurazione e fogli;
- prototipo di un evento campione.

### Fase B: fondazioni

- plugin WordPress;
- endpoint proxy firmati;
- progetto GAS modulare;
- gestione e versionamento di ente, attività e branding ereditato;
- inizializzazione e versionamento dello spreadsheet;
- ruoli amministrativi e audit.

### Fase C: iscrizione

- wizard pubblico;
- tipi di biglietto, campi e opzioni;
- prezzi server-side;
- capienza, idempotenza e pagina di conferma.

### Fase D: pannello ed email

- editor attività e anteprima del branding ereditato;
- editor eventi e schermate;
- editor e anteprima email;
- coda, retry e notifiche interne;
- elenco ordini ed esportazione.

### Fase E: pagamenti

- IBAN, link carta esterno e contanti;
- stati e riconciliazione manuale;
- caparra e saldo;
- eventuale Tinaba Pay API, se disponibile contrattualmente.

### Fase F: collaudo e rilascio

- test mobile, desktop, tastiera e screen reader;
- test concorrenza sugli ultimi posti;
- test retry, timeout e doppi click;
- test email e pagamento;
- test di `PaymentIntake` per bonifico, carta e contanti, inclusi duplicati, rettifiche e importi parziali;
- ambiente di collaudo e produzione separati;
- manuale di configurazione e procedura di backup.

## 14. Decisioni confermate

- Il sito target è `https://www.parrocchiasanteugenio.it/` e il riferimento evento esaminato è la pagina `cammino-di-santiago-2026`.
- WordPress consente il caricamento di plugin ZIP dal computer: il prodotto verrà quindi consegnato come plugin personalizzato installabile.
- La pagina pubblicata conferma l'approccio precedente: HTML e JavaScript sono inseriti direttamente in WordPress e chiamano un endpoint GAS con `fetch`; il modulo non è ospitato in un iframe GAS.
- Nel primo rilascio il pagamento carta usa soltanto un link esterno, senza API merchant e senza conferma automatica.
- Il link di pagamento è una proprietà del singolo evento e non una costante globale.
- Referente: nome, cognome, email e cellulare obbligatori.
- Partecipante: nome e cognome obbligatori; email e cellulare facoltativi ma esplicitamente proposti.
- Sotto i contatti facoltativi comparirà un invito come: `Aggiungi email e cellulare se desideri che il partecipante possa essere contattato direttamente. Se li lasci vuoti, tutte le comunicazioni saranno inviate al referente.`
- Il referente e i biglietti sono entità separate; il primo biglietto viene precompilato soltanto se è selezionato `Il referente partecipa`.
- Il riepilogo completo viene inviato al referente. I partecipanti non ricevono automaticamente dati relativi all'intero ordine.
- Direzione visiva iniziale coerente con il sito: titoli ispirati a `Roboto Slab`, testo semplice nello stile `Open Sans`, bianco e grigi chiari, blu principale vicino a `#337ab7`, accenti blu notte e card pulite.
- Gli stili del plugin saranno completamente namespaced: il codice precedente modifica globalmente il `body` della pagina e questo comportamento non verrà ripetuto.
- La parrocchia è l'ente base, ma contiene più attività organizzatrici. Ogni attività può impostare il proprio logo e usarlo come predefinito per i suoi eventi; il singolo evento può configurare un override esplicito.
- Gli amministratori WordPress accedono automaticamente al pannello del plugin. Le persone delegate usano account WordPress personali con un ruolo dedicato e possono essere limitate al solo pannello iscrizioni e alle attività assegnate.
- Nel primo rilascio il pannello gestionale risiede in `wp-admin`; non è prevista una seconda area amministrativa nel frontend.
- Il plugin carica asset e logica soltanto nelle pagine che usano il modulo e nelle proprie schermate amministrative; sulle altre pagine WordPress deve avere un impatto trascurabile e verificato.
- Caparra, saldo, link carta e profili di bonifico sono configurabili per evento, con ereditarietà controllata; nessuna coordinata o modalità economica è una costante globale.
- I versamenti vengono inseriti manualmente in `PaymentIntake` e trasformati in righe canoniche di `Payments`; ogni riga indica obbligatoriamente se la fonte è bonifico/IBAN, carta oppure contante e viene validata prima di aggiornare lo stato dell'ordine.

## 15. Decisioni ancora aperte

1. Quando uno o più biglietti sono considerati confermati: subito dopo l'invio oppure soltanto dopo la verifica manuale del pagamento? Per gli eventi con capienza, per quanto tempo una prenotazione non pagata deve bloccare i posti?
2. Quali eventi reali useranno pagamento completo, caparra o nessun pagamento, e quali saranno importi e scadenze applicabili?
3. Sono previste iscrizioni di minori o raccolta di dati sensibili, come salute, allergie o disabilità?
4. I volumi reali rientrano nell'inviluppo provvisorio di 500 ordini, 1.000 biglietti e 10 invii simultanei per evento?
5. È disponibile un account Google Workspace organizzativo? Quale indirizzo deve inviare le email e quali alias sono già verificati?
6. Quali profili bancari reali sono approvati e quali attività o eventi sono autorizzati a usarli?
