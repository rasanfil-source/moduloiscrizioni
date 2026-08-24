# Decisioni operative — Fase A

Stato: default adottabili per prototipo e collaudo, con conferme di produzione ancora aperte  
Data: 24 agosto 2026

## 1. Scopo del documento

Questo documento traduce le decisioni aperte di `PROGETTO.md` in default operativi reversibili. I default consentono di sviluppare il prototipo senza trasformare ipotesi non approvate in regole definitive.

Sono distinti tre livelli:

- **requisito confermato**: vincolo già acquisito e da implementare;
- **default di Fase A**: valore iniziale configurabile, usato per prototipo e test;
- **conferma di produzione**: informazione o policy che l'organizzazione deve approvare prima di pubblicare un evento reale.

Il documento non contiene credenziali, segreti, indirizzi email operativi o dati bancari reali.

## 2. Requisiti confermati

L'architettura rimane volutamente essenziale: plugin monolitico WordPress, singolo progetto Apps Script e singolo spreadsheet in Google Workspace. Non sono previsti microservizi, container, code cloud esterne, cluster o database aggiuntivi. La scelta viene rivalutata soltanto se cambiano sensibilmente i volumi o viene abbandonato l'uso operativo del foglio.

### 2.1 Gerarchia organizzativa

Il modello è composto da tre livelli con ID stabili:

```text
Parrocchia
  └── Attività
        └── Evento
```

- **Parrocchia**: identità generale, impostazioni legali, contatti e default comuni.
- **Attività**: gruppo o iniziativa della parrocchia, con identità visiva, contatti e configurazioni predefinite proprie.
- **Evento**: singola iniziativa pubblicabile, appartenente obbligatoriamente a una sola attività.

Ogni attività deve poter impostare il proprio logo. Logo e immagine principale dell'evento sono asset distinti.

### 2.2 Ereditarietà e override

Per branding, contatti, template e profili di pagamento si applica questa precedenza:

```text
override Evento → valore Attività → valore Parrocchia → fallback sicuro del plugin
```

Per logo e colori sono ammesse soltanto due modalità non ambigue:

- `inherit`: eredita dal livello superiore;
- `override`: usa il valore completo configurato sul livello corrente.

Le funzioni realmente facoltative, come un metodo di pagamento, usano invece un campo tipizzato `enabled: false`; stringhe vuote e `null` non cancellano valori ereditati.

Il logo usa un elemento della Media Library WordPress e richiede testo alternativo. Colori e combinazioni del branding devono superare i controlli di contrasto previsti dal progetto.

Alla pubblicazione il sistema risolve l'ereditarietà e salva una revisione immutabile della configurazione effettiva dell'evento. Una modifica successiva alla parrocchia o all'attività richiede la ripubblicazione dell'evento per diventare effettiva e non modifica ordini, email o consensi storici.

### 2.3 Accesso amministrativo

- Gli utenti WordPress con privilegi di amministratore sono automaticamente autorizzati a tutte le funzioni del plugin. Il controllo usa capability WordPress, non il confronto fragile con il nome del ruolo.
- I delegati ricevono un ruolo dedicato e capability specifiche del plugin, senza capability amministrative generiche.
- Un delegato può accedere soltanto al pannello del plugin e alle attività che gli sono state assegnate.
- L'assegnazione alle attività è una ACL esplicita, verificata dal server in ogni lettura e modifica; nascondere una voce nell'interfaccia non è un controllo sufficiente.
- Un delegato non può assegnarsi altre attività, elevare i propri privilegi, gestire utenti, cambiare impostazioni WordPress o accedere a ordini di attività non assegnate.
- Modifiche a ruoli, assegnazioni, eventi, ordini e pagamenti sono registrate nell'audit.

Default di Fase A: pannello gestionale esclusivamente in `wp-admin`; non viene introdotta una seconda autenticazione né una pagina gestionale nel frontend.

### 2.4 Isolamento dal resto del sito

Il plugin non deve modificare aspetto o comportamento delle pagine estranee al modulo:

- CSS racchiuso in un contenitore del plugin e classi namespaced;
- nessun selettore globale come `body`, `h1`, `button` o `input`;
- JavaScript caricato soltanto nelle pagine del modulo pubblico e del pannello plugin;
- fogli di stile e script amministrativi caricati soltanto nelle schermate del plugin;
- nessuna variabile globale o listener generico che interferisca con tema e altri plugin;
- endpoint REST sotto un namespace dedicato;
- blocco/shortcode privo di effetti quando non è presente nella pagina;
- disattivazione del plugin senza alterare la resa delle altre pagine WordPress.

Questi vincoli fanno parte dei criteri di accettazione, non sono semplici preferenze grafiche.

### 2.5 Registrazione manuale dei pagamenti

I versamenti vengono inseriti manualmente nello stesso spreadsheet che raccoglie ordini e partecipanti. Per non rendere editabile il registro autorevole, gli operatori scrivono nel foglio protetto `PaymentIntake`; Apps Script valida e crea le righe canoniche in `Payments`.

Ogni inserimento dichiara obbligatoriamente una fonte controllata:

- `BANK_TRANSFER` — Bonifico / IBAN;
- `CARD` — Carta, anche quando l'incasso avviene tramite un link esterno;
- `CASH` — Contante.

Soltanto le righe convalidate aggiornano `PENDING`, `PAID_TO_DATE`, `PARTIALLY_PAID`, `PAID` o `OVERPAID`. Ogni movimento dichiara anche se è incasso, rimborso o storno e, quando nota, la rata `FULL`, `DEPOSIT`, `INTERIM` o `BALANCE`. Non si memorizzano dati completi della carta. Il canale di audit è `MANUAL_SHEET`; per i contanti sono obbligatori data, importo e operatore.

L'accesso diretto allo spreadsheet globale è riservato a operatori finanziari globali, perché comporta visibilità sui dati di tutte le attività. I delegati limitati a una o più attività non ricevono accesso al foglio e operano soltanto nel proprio ambito attraverso WordPress.

La convalida concorrente usa un `ScriptLock` breve soltanto per assegnare l'ID e trascrivere atomicamente la riga canonica in `Payments`. Lettura, normalizzazione e controlli non mutanti restano fuori dal lock; duplicati e retry sono risolti con una chiave idempotente.

### 2.6 Profili economici e raccolta dati

Ogni evento dichiara quattro assi economici indipendenti:

- `pricing_mode`: `NONE`, `ZERO`, `CALCULATED`;
- `price_finalization`: `NOT_APPLICABLE`, `AT_ORDER`, `POST_ORDER`;
- `collection_mode`: `NONE`, `NOT_MANAGED`, `TRACKED_MANUAL`, futuro `TRACKED_PROVIDER`;
- `payment_plan`: `NONE`, `FULL_AMOUNT`, `DEPOSIT_BALANCE`.

Sono quindi supportati senza ambiguità sola iscrizione, prezzo senza incasso gestito, pagamento completo e caparra/saldo con eventuale ricalcolo finale. Le rettifiche sono append-only; ricevute e rimborsi sono movimenti separati e non modificano il prezzo originario.

La raccolta dati usa `data_profile: MINIMAL` oppure `EXTENDED`. Il profilo esteso abilita soltanto moduli espliciti, per esempio `APPAREL`, `POSTAL_ADDRESS` o `LOGISTICS`. Ogni campo dichiara ambito, tipo, finalità, conservazione e capability di visualizzazione. Dati di documenti, minori o categorie particolari restano fuori dai preset ordinari.

Le fonti storiche confermano anche un possibile modulo `TRAVEL_DOCUMENTS` con dati di nascita, cittadinanza/nazionalità e documento di viaggio. È separato da `EXTENDED`, disattivato per default e soggetto a revisione privacy e autorizzazioni più restrittive.

### 2.7 Repository pubblico sanitizzato

Fogli reali, esportazioni, dati personali, coordinate bancarie, link di pagamento operativi, credenziali e percorsi locali non entrano nel repository. Le fixture sono completamente sintetiche e marcate come non pubblicabili. Il controllo automatico di sanitizzazione è parte della pipeline GitHub.

L'audit tecnico ricevuto è trattato come revisione consultiva. Sono accolti come requisiti di Fase B: prova di carico anticipata, configurazione esplicita della Web App GAS, nonce anti-replay persistito, lock sulla trascrizione `PaymentIntake` → `Payments`, cache WordPress con invalidazione dipendente, protezione antispam leggera e test automatici delle funzioni economiche pure.

## 3. Default operativi per il prototipo

### 3.1 Conferma della prenotazione e blocco posti

| Caso | Stato iniziale prenotazione | Stato pagamento | Effetto sulla capienza |
|---|---|---|---|
| Sola iscrizione | `CONFIRMED` | `NOT_REQUIRED` | posti occupati definitivamente |
| Evento gratuito | `CONFIRMED` | `NOT_REQUIRED` | posti occupati definitivamente |
| Prezzo con incasso non gestito | `CONFIRMED` | `NOT_MANAGED` | nessun hold economico |
| Evento a pagamento | `HELD` | `PENDING` | posti riservati fino alla scadenza |
| Pagamento dovuto ora verificato | `CONFIRMED` | rata corrente `PAID` | posti occupati definitivamente |
| Hold scaduto e chiuso dall'operatore, oppure scaduto in modalità automatica | `EXPIRED` | `EXPIRED` | posti rilasciati |

Se è prevista una caparra, la prenotazione diventa `CONFIRMED` dopo la verifica della caparra dovuta subito; il saldo futuro resta registrato separatamente.

In questo caso la rata di caparra è `PAID`, la rata di saldo resta `PENDING` e lo stato aggregato del pagamento è `PARTIALLY_PAID` fino al versamento completo.

Default di Fase A:

- durata hold: 72 ore dalla creazione dell'ordine;
- durata configurabile per evento;
- scadenza sempre limitata dalla chiusura iscrizioni o da una scadenza evento più restrittiva;
- promemoria previsto prima della scadenza;
- proroga manuale consentita e tracciata;
- rilascio automatico dei posti non attivo su eventi reali finché la policy non è approvata.

Con `unpaid_order_expiry_mode: MANUAL`, al raggiungimento delle 72 ore l'ordine diventa “da revisionare” ma resta `HELD`; passa a `EXPIRED` e libera i posti soltanto dopo l'azione esplicita dell'operatore. Con modalità `AUTO`, da attivare solo dopo approvazione, il passaggio avviene automaticamente.

Si aggiunge `EXPIRED` agli stati della prenotazione, per non confondere una scadenza automatica con un annullamento intenzionale.

### 3.2 Prezzo, caparra e saldo

I quattro assi economici sono configurabili per singolo evento e non sono regole globali. Default di Fase A per un evento a pagamento tracciato: `CALCULATED / AT_ORDER / TRACKED_MANUAL / FULL_AMOUNT`.

Il modello prevede caparra fissa per ordine, fissa per biglietto o percentuale, zero o più versamenti intermedi e saldo con scadenza successiva. `POST_ORDER` mantiene il prezzo `PROVISIONAL`; quando le obbligazioni correnti sono coperte lo stato è `PAID_TO_DATE`, non `PAID`. `PRICE_FINALIZED` e ogni rettifica sono eventi append-only. Un'eccedenza produce `OVERPAID` ed eventuale rimborso separato.

Tutti gli importi sono interi in centesimi e vengono calcolati dal server sulla revisione pubblicata e sugli eventi economici convalidati.

### 3.3 Minori e dati particolari

Default di Fase A:

- nessun flusso specifico per minori;
- nessun campo relativo a salute, allergie, disabilità o altre categorie particolari di dati;
- soli campi anagrafici e di contatto già previsti dal progetto;
- nessun campo libero destinato implicitamente a raccogliere informazioni sanitarie.

Il modello dei campi resta estensibile. `MINIMAL` è il default; `EXTENDED` abilita soltanto moduli nominati. Indirizzo, taglia o numero maglietta e logistica possono essere aggiunti con finalità e conservazione esplicite. Documenti di viaggio e categorie particolari non vengono abilitati nel prototipo pubblico senza una revisione organizzativa e privacy.

### 3.4 Volumi e concorrenza

Il limite operativo confermato è di tre attività contemporanee da circa 100, 50 e 30 partecipanti, cioè 180 persone complessive nel picco. Il margine di prova arriva a 300 partecipanti complessivi, con 10 invii finali contemporanei e almeno 20 richieste in contesa sugli ultimi posti.

La Web App GAS esegue come proprietario del deployment e riceve le richieste dal proxy WordPress; non usa la sessione Google dell'utente finale. L'HMAC è quindi obbligatorio. La richiesta usa una finestra temporale iniziale massima di 120 secondi, nonce univoco registrato atomicamente sotto `ScriptLock` almeno oltre tale finestra, chiave versionata e ruotabile e idempotency key distinta dal nonce. Il nonce ha anche un archivio durevole minimo, perché una cache può essere evasa prima del TTL. Nessun token OAuth del proprietario viene restituito al client.

Le quote GAS vengono ricontrollate all'avvio della Fase B. Baseline ufficiale consultata il 24 agosto 2026: 6 minuti per esecuzione, 30 esecuzioni simultanee per utente, 1.000 per script, 100 destinatari email al giorno per account consumer e 1.500 per Google Workspace, oltre alle quote giornaliere per trigger e `UrlFetch`. Google può modificarle senza preavviso.

La prova di carico è il primo incremento di Fase B e misura durata, collisioni, retry, quote email e recupero da interruzione. Le elaborazioni massive usano letture e scritture a blocchi, checkpoint idempotenti e batch limitati.

### 3.5 Invio email

La configurazione distingue:

- account tecnico che effettua realmente l'invio;
- nome visualizzato del mittente;
- `reply-to`, ereditabile da parrocchia, attività o evento;
- destinatari interni protetti;
- template versionato.

Ogni ordine ha un codice univoco testuale. Per singolo evento l'organizzatore sceglie `NONE`, `TEXT`, `QR` o `BARCODE` come presentazione nell'email; QR e barcode codificano lo stesso valore e non sono requisiti di accesso salvo futura attivazione esplicita di un flusso check-in.

Default di Fase A: generazione di anteprima ed `EmailOutbox`, con invio esterno disattivato oppure limitato a destinatari di test. Non si presume che un alias possa essere usato finché non risulta verificato dall'account organizzativo.

### 3.6 Profili di pagamento e IBAN

Le coordinate bancarie non sono costanti globali nel codice. Il sistema usa profili di pagamento nominati:

- la parrocchia può indicare un profilo predefinito;
- l'attività può ereditarlo o sceglierne un altro;
- l'evento può ereditare o applicare un override autorizzato;
- ogni ordine conserva lo snapshot delle istruzioni applicate al momento della prenotazione.

Default di Fase A: profilo fittizio/non pubblicabile, senza dati bancari reali. La pubblicazione deve essere impedita se il metodo bonifico è attivo ma il profilo effettivo è incompleto o non approvato.

### 3.7 Pannello gestionale

Default di Fase A: configurazione e pannello gestionale soltanto in `wp-admin`. L'inserimento operativo dei versamenti in `PaymentIntake` è l'unica eccezione prevista e non costituisce una seconda area amministrativa o un secondo sistema di autenticazione WordPress.

Un eventuale pannello frontend è rinviato a una fase successiva e, se richiesto, dovrà riusare le stesse API, capability, ACL per attività e protezioni CSRF. Non dovrà creare account o password paralleli a WordPress.

### 3.8 Antispam e cache WordPress

Per i volumi confermati il default è honeypot accessibile, tempo minimo di compilazione e rate limit nel proxy WordPress. CAPTCHA di terze parti non viene caricato nel primo rilascio; potrà essere attivato soltanto dopo evidenza di abuso e valutazione privacy/prestazioni.

La configurazione pubblica usa la Transients API con TTL breve e rigenerazione sicura in caso di cache miss. La chiave include revisione ed evento; pubblicazione o modifica di organizzazione, attività o evento invalida esplicitamente tutti i transient dipendenti. Un transient può sparire prima del TTL e non è mai fonte autorevole.

## 4. Matrice iniziale di ereditarietà

| Configurazione | Parrocchia | Attività | Evento | Snapshot alla pubblicazione |
|---|---:|---:|---:|---:|
| Logo e testo alternativo | default | override previsto | override o eredita | sì |
| Colori | default | override previsto | override previsto | sì |
| Nome visualizzato email | default | override previsto | override previsto | sì |
| Reply-to | default | override previsto | override previsto | sì |
| Template email | default | override previsto | override previsto | sì |
| Profilo di pagamento | default | override previsto | override previsto | sì |
| Privacy e termini | versione base | eventuale integrazione | versione applicata | sì |
| Immagine principale evento | no | no | valore proprio | sì |
| Prezzi, capienza e date | no | preset facoltativi | valore proprio | sì |

Gli override devono essere visibili nel pannello con indicazione della provenienza del valore e comando esplicito “Ripristina valore ereditato”.

## 5. Conferme necessarie prima della produzione

Le seguenti risposte non bloccano lo sviluppo del prototipo, ma bloccano la pubblicazione di eventi reali interessati.

### 5.1 Policy prenotazioni

- Dopo quante ore o giorni scade un ordine non pagato per ciascun tipo di evento?
- La scadenza deve essere automatica o richiedere revisione manuale?
- Quale margine usare per pagamenti effettuati ma non ancora riconciliati?
- Chi può prorogare un hold e con quali limiti?

### 5.2 Regole economiche

- Quali eventi usano sola iscrizione, prezzo non gestito, saldo completo oppure caparra?
- Quando scade l'eventuale saldo?
- Il pagamento della caparra è sufficiente a confermare definitivamente il posto?
- Quali eventi consentono `POST_ORDER`, chi finalizza il prezzo e fino a quando?
- Quali motivi di rettifica sono ammessi e chi può approvarli dopo la finalizzazione?

### 5.3 Minori e privacy

- Sono ammesse iscrizioni di minori?
- Chi presta le accettazioni necessarie e quali dati del responsabile devono essere conservati?
- Verranno raccolti dati sanitari o altre categorie particolari?
- Quali base giuridica, informativa, tempi di conservazione e gruppi di accesso si applicano?

### 5.4 Dimensionamento

Il limite operativo dichiarato è di tre attività contemporanee con circa 100, 50 e 30 partecipanti, cioè 180 persone complessive nel picco. I test useranno un margine fino a 300 partecipanti complessivi e includeranno la contesa sugli ultimi posti; non è richiesta un'infrastruttura dimensionata per grandi volumi.

- Ordini e partecipanti massimi previsti per evento;
- picchi attesi in occasione dell'apertura iscrizioni;
- numero massimo di posti acquistabili nello stesso ordine.

Se le stime eccedono l'inviluppo di collaudo della Fase A, l'architettura deve essere riesaminata prima del rilascio.

### 5.5 Identità email

- disponibilità e titolarità di un account Google Workspace organizzativo;
- indirizzo tecnico autorizzato all'invio;
- alias già verificati;
- `reply-to` e destinatari interni di ciascuna attività;
- quote disponibili e responsabilità sul mantenimento dell'account.

### 5.6 Pagamenti

- profili bancari effettivamente utilizzati dalla parrocchia e dalle attività;
- attività autorizzate a usare ogni profilo;
- procedura di doppia verifica prima della pubblicazione;
- responsabili della riconciliazione manuale;
- link carta effettivo per ogni evento che lo prevede.
- editor Google autorizzati a usare `PaymentIntake` e procedura per identificarli nell'audit;
- procedura per incassi in contanti, eventuale numero di ricevuta e responsabile della registrazione.

Nessun dato reale deve essere inserito in fixture, esempi, repository o log.

### 5.7 Struttura e deleghe

- elenco iniziale delle attività, rispettivi nomi e loghi;
- contatti e default di branding per ogni attività;
- utenti delegati e attività assegnate;
- eventuale separazione tra chi configura eventi, chi vede gli ordini e chi conferma pagamenti;

## 6. Rischi principali e mitigazioni

| Rischio | Mitigazione prevista |
|---|---|
| Sovraprenotazione sugli ultimi posti | ricalcolo server-side, lock breve, idempotenza e test concorrenti |
| Posti bloccati troppo a lungo o rilasciati prematuramente | hold per evento, promemoria, proroga auditata e attivazione automatica solo dopo approvazione |
| Modifica retroattiva di logo, testi o coordinate | revisione pubblicata e snapshot applicato all'ordine |
| Accesso trasversale di un delegato | ACL per attività verificata su ogni endpoint e test di autorizzazione negativi |
| Interferenza con tema o altri plugin | asset caricati selettivamente, CSS/JS namespaced e test su pagine estranee |
| Invio da identità non autorizzata o superamento quote | account organizzativo, alias verificati, coda con retry e monitoraggio |
| Raccolta impropria di dati di minori o sanitari | campi disattivati per default e gate privacy prima della produzione |
| Pagamento verso coordinate errate | profili approvati, controllo alla pubblicazione, audit e snapshot nell'ordine |
| Sovrascrittura di prezzi o pagamenti storici | eventi economici, incassi, rimborsi e storni append-only con idempotenza |
| Pubblicazione accidentale di dati reali | `.gitignore`, fixture sintetiche, revisione umana e controllo automatico di sanitizzazione |

## 7. Criterio per non bloccare il prototipo

La Fase A può procedere con:

- una parrocchia di esempio;
- una attività campione con logo segnaposto e branding accessibile;
- un evento campione associato all'attività;
- pagamento completo come preset economico predefinito; il solo prototipo visuale può usare una caparra fissa per biglietto per collaudare anche `Da versare ora` e saldo successivo, senza trasformarla in policy di produzione;
- hold configurato a 72 ore ma senza rilascio automatico su dati reali;
- profilo di pagamento fittizio e non pubblicabile;
- email in anteprima/outbox o limitate a destinatari di test;
- nessun minore e nessun dato particolare;
- amministratori WordPress globali e almeno un delegato fittizio usato per testare l'isolamento tra attività;
- pannello soltanto in `wp-admin`.

Il passaggio in produzione richiede la chiusura esplicita delle conferme elencate nella sezione 5 e il superamento dei test di sicurezza, concorrenza, accessibilità e isolamento dal resto del sito.

## 8. Autorizzazione all'avvio della Fase 2

La Fase 2 può iniziare. Sono disponibili:

- una bozza WordPress vuota e non pubblicata destinata alla console dimostrativa;
- un account amministrativo capace di installare plugin, da usare soltanto dopo collaudo locale e conferma immediata prima dell'installazione;
- un account Google Workspace organizzativo con esperienza GAS preesistente;
- un primo scenario approvato: evento fittizio, 30 posti, waitlist attiva, sola iscrizione, profilo `MINIMAL` ed email simulate.

Il repository pubblico non conserva URL amministrativi, ID WordPress, indirizzi operativi, deployment GAS o identificativi di Workspace. Il primo incremento viene sviluppato localmente e usa soltanto fixture sintetiche.
