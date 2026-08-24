# Schema dati iniziale

Questo documento dettaglia il modello descritto in `PROGETTO.md`. Definisce la gerarchia organizzativa, il formato delle revisioni pubblicate e le regole minime di integrità per WordPress, Google Apps Script e Google Sheets.

La struttura completa di una revisione è esemplificata in `schema/evento.example.json`. La fixture è intenzionalmente non pubblicabile: nomi, indirizzi, profili e URL sono fittizi e marcati `DEMO_ONLY`.

## 1. Principi invarianti

- La parrocchia è rappresentata in `Organizations` con `type: PARISH`.
- Un'organizzazione contiene una o più righe in `Activities`.
- Ogni evento appartiene obbligatoriamente a una sola attività tramite `activity_id`.
- L'organizzazione rimane il soggetto legale anche quando attività o evento usano un proprio logo.
- Il logo identifica il brand; `hero_image` è invece un'immagine editoriale dell'evento. I due asset non sono intercambiabili.
- ID, prezzi e quantità sono dati autorevoli del server. Le righe del foglio e i valori calcolati dal browser non sono identità né fonti attendibili.
- Una revisione pubblicata è immutabile. Le modifiche successive restano in bozza fino a una nuova pubblicazione.
- Un ordine fotografa configurazione, prezzi, consensi e branding della revisione usata al momento della creazione.
- Ruoli, capability e assegnazioni delle attività sono mantenuti e verificati da WordPress, non dal foglio e non dal JSON pubblico.

## 2. Gerarchia e cardinalità

```text
Organizations (type = PARISH)
  1 └── N Activities
             1 └── N Events
                        1 └── N EventRevisions
                        1 └── N Orders
                                   1 └── N Tickets
                                   1 └── N Selections
                                   1 └── N Payments
```

Vincoli principali:

- `Activities.organization_id` deve riferirsi a un'organizzazione esistente e attiva di tipo `PARISH`.
- `Events.activity_id` deve riferirsi a un'attività esistente.
- un evento può essere pubblicato solo se organizzazione e attività sono attive;
- un'attività con eventi non viene cancellata fisicamente: viene archiviata oppure, prima della pubblicazione, gli eventi ancora senza ordini vengono riassegnati;
- dopo il primo ordine, il cambio di `activity_id` richiede una migrazione amministrativa esplicita e auditata;
- gli slug delle attività sono univoci nell'organizzazione; gli slug degli eventi sono univoci nell'installazione iniziale, così l'endpoint pubblico può continuare a usare il solo slug evento.

## 3. Identificatori, revisioni e date

- Gli identificatori sono stringhe opache e immutabili, generate dal server, per esempio `org_...`, `act_...`, `evt_...`, `evrev_...`, `ord_...` e `tkt_...`.
- Un numero di riga di Google Sheets non viene mai esposto come identificatore applicativo.
- Tutte le date tecniche sono ISO 8601 in UTC. Date e orari dell'evento conservano anche un fuso IANA, per esempio `Europe/Rome`.
- Tutti gli importi sono interi in centesimi e hanno una valuta ISO 4217 esplicita.
- Ogni documento JSON dichiara `schema_version`; le migrazioni incrementano la versione e non reinterpretano silenziosamente dati già salvati.

## 4. Entità principali

### `Organizations`

Nel primo rilascio esiste normalmente una sola organizzazione.

| Campo | Tipo | Regola |
|---|---|---|
| `organization_id` | stringa | ID immutabile |
| `type` | enum | inizialmente solo `PARISH` |
| `slug` | stringa | univoco |
| `legal_name` | stringa | denominazione legale obbligatoria |
| `display_name` | stringa | nome mostrato al pubblico |
| `status` | enum | `ACTIVE`, `ARCHIVED` |
| `revision` | intero | incrementato a ogni modifica pubblicabile |
| `public_contacts_json` | JSON | contatti pubblici; nessun destinatario interno |
| `legal_json` | JSON | URL di privacy, condizioni e dati del titolare |
| `branding_json` | JSON | valori di fallback dell'organizzazione |
| `created_at`, `updated_at` | timestamp | UTC |

Il branding non può cambiare `legal_name`, titolare del trattamento o testi legali.

### `Activities`

| Campo | Tipo | Regola |
|---|---|---|
| `activity_id` | stringa | ID immutabile |
| `organization_id` | stringa | FK obbligatoria verso `Organizations` |
| `slug` | stringa | univoco nell'organizzazione |
| `name` | stringa | nome pubblico obbligatorio |
| `description` | stringa | testo filtrato |
| `status` | enum | `DRAFT`, `ACTIVE`, `ARCHIVED` |
| `revision` | intero | incrementato a ogni modifica pubblicabile |
| `public_contacts_json` | JSON | contatti dell'attività, se differenti |
| `branding_json` | JSON | logo proprio e override dei colori |
| `created_at`, `updated_at` | timestamp | UTC |

Un'attività può configurare un proprio logo WordPress con testo alternativo. Per passare ad `ACTIVE` deve esistere almeno un logo effettivo valido dopo la risoluzione: proprio oppure ereditato dall'organizzazione. Il pannello rende sempre disponibile l'override dell'attività e mostra chiaramente quando è in uso il fallback parrocchiale.

### `Events`

`Events` contiene la bozza corrente e i puntatori alla revisione pubblicata, non dati storici mutabili.

| Campo | Tipo | Regola |
|---|---|---|
| `event_id` | stringa | ID immutabile |
| `activity_id` | stringa | FK obbligatoria verso `Activities` |
| `slug` | stringa | univoco nell'installazione iniziale |
| `status` | enum | `DRAFT`, `PUBLISHED`, `CLOSED`, `CANCELLED`, `ARCHIVED` |
| `draft_revision` | intero | versione modificabile corrente |
| `published_revision` | intero/null | ultimo numero pubblicato |
| `published_revision_id` | stringa/null | riferimento alla revisione immutabile |
| `published_config_hash` | stringa/null | SHA-256 del JSON canonico pubblicato |
| `branding_overrides_json` | JSON | override parziali consentiti |
| `hero_asset_json` | JSON | immagine editoriale, distinta dal logo |
| `needs_republish` | booleano | dipendenze organizzazione/attività cambiate |
| `created_at`, `updated_at`, `published_at` | timestamp | UTC |

### `EventRevisions`

È il registro immutabile delle pubblicazioni. Può essere un foglio dedicato oppure un archivio equivalente, purché le revisioni precedenti non vengano sovrascritte.

| Campo | Tipo | Regola |
|---|---|---|
| `event_revision_id` | stringa | ID immutabile |
| `event_id` | stringa | FK verso `Events` |
| `revision` | intero | crescente per evento |
| `organization_revision` | intero | dipendenza fotografata |
| `activity_revision` | intero | dipendenza fotografata |
| `schema_version` | stringa | formato del documento |
| `config_hash` | stringa | SHA-256 del JSON canonico |
| `config_json` | JSON | configurazione completa e risolta |
| `published_at` | timestamp | UTC |
| `published_by_actor_ref` | stringa | riferimento tecnico WordPress auditabile |

I fogli `Screens`, `TicketTypes`, `CustomFields`, `OptionGroups`, `OptionChoices`, `Messages`, `PaymentMethods` ed `EmailTemplates` alimentano la bozza. Alla pubblicazione vengono validati e compilati dentro `EventRevisions.config_json`.

## 5. Ereditarietà del branding

Il branding viene risolto separatamente per ogni campo, nell'ordine:

```text
default di sistema -> Organizations.branding -> Activities.branding -> Events.branding_overrides
```

Nel primo schema sono sovrascrivibili soltanto:

- `logo`;
- `primary_color`;
- `secondary_color`.

Regole di merge:

1. un campo assente eredita il valore del livello precedente;
2. stringa vuota e `null` non cancellano un valore: sono rifiutati in validazione;
3. il logo è un oggetto atomico: un override deve fornire un asset completo e validato, senza combinare URL di un livello e testo alternativo di un altro;
4. `hero_image` non partecipa al merge del branding;
5. CSS, JavaScript, font remoti e proprietà non previste vengono rifiutati;
6. dopo il merge, il backend verifica contrasto WCAG 2.2 AA e completezza degli asset;
7. la revisione conserva sia `resolved_branding` sia la provenienza di ciascun campo, utile per anteprima e audit.

Esempio di provenienza:

```json
{
  "sources": {
    "logo": "activity",
    "primary_color": "activity",
    "secondary_color": "event"
  }
}
```

Il browser riceve il branding già risolto e non decide quale livello prevale.

### Altre configurazioni ereditabili

Lo stesso criterio per campo viene applicato, con allowlist specifiche, anche a:

- `resolved_public_contacts`, per email e telefono mostrati al pubblico;
- `resolved_email_settings`, per nome visualizzato, `reply-to` e riferimenti ai template;
- `resolved_payment_profile`, per il profilo di pagamento autorizzato e la sua revisione.

Ogni sezione conserva la provenienza dei valori. La proiezione pubblica include soltanto ciò che serve al wizard; destinatari interni, coordinate non attive, note di approvazione e altri valori protetti restano nello snapshot server-side. Funzioni facoltative usano un booleano o enum esplicito, non `null` o stringhe vuote per annullare un valore ereditato.

## 6. Asset della Media Library WordPress

Durante la modifica, WordPress conserva un riferimento alla Media Library, almeno `attachment_id`. Prima della pubblicazione il backend deve:

- verificare che l'allegato esista e sia leggibile dall'installazione;
- accettare solo URL HTTPS su host autorizzati;
- rifiutare URL arbitrari, `data:` URL e contenuto attivo;
- accettare inizialmente `image/png`, `image/jpeg` e `image/webp`;
- consentire SVG solo se è presente una procedura esplicita di sanitizzazione;
- richiedere un testo alternativo significativo per logo e hero image;
- verificare dimensioni positive, tipo MIME reale e limiti di peso configurati;
- congelare nella revisione pubblicata `asset_id`, URL, MIME, testo alternativo, larghezza e altezza;
- includere, quando disponibile, un fingerprint del contenuto per rilevare sostituzioni silenziose dell'asset.

L'oggetto asset pubblicato non contiene percorsi locali né credenziali. Un logo deve essere accompagnato dal nome testuale dell'attività perché immagini remote possono non essere caricate, soprattutto nelle email.

## 7. Ciclo di pubblicazione

1. L'amministratore modifica la bozza in WordPress.
2. WordPress verifica capability e scope attività.
3. Il backend carica organizzazione, attività e tutte le entità figlie dell'evento.
4. Valida riferimenti, stati, asset, date, capienza, prezzi, consensi e proprietà inattese.
5. Risolve il branding per campo.
6. Produce JSON canonico, calcola il relativo SHA-256 e crea una nuova riga immutabile in `EventRevisions`.
7. Aggiorna in `Events` i puntatori alla revisione pubblicata.
8. Invalida la cache usando almeno `event_id`, numero revisione e hash.

Una modifica successiva a `Organizations` o `Activities` incrementa la relativa `revision` e marca gli eventi dipendenti con `needs_republish: true`. La configurazione già pubblicata continua a funzionare invariata fino alla ripubblicazione esplicita.

La risposta pubblica espone solo la revisione `PUBLISHED` e una proiezione dei campi necessari al wizard. Sono esclusi destinatari interni, note, dati di audit, bozze, capability e impostazioni tecniche.

## 8. Ordini e snapshot canonico

`Orders` conserva almeno:

- `order_id`, codice pubblico separato e chiave di idempotenza;
- `organization_id`, `activity_id`, `event_id` ed `event_revision_id`;
- `event_revision`, `schema_version` e `config_hash`;
- referente, stati prenotazione/pagamento e totali in centesimi;
- `snapshot_json`, autorevole e immutabile dopo la creazione salvo append di eventi di stato espliciti;
- timestamp e versione dei consensi accettati.

Lo snapshot contiene:

- denominazione dell'organizzazione e dell'attività;
- titolo, date, luogo e fuso dell'evento;
- branding risolto, inclusa la descrizione del logo;
- biglietti, partecipanti e campi raccolti;
- opzioni selezionate con etichetta e prezzo fotografati;
- subtotali, totale, valuta e importo dovuto;
- regole e riferimento di pagamento applicati;
- versioni di privacy, condizioni e accettazioni.

Non contiene segreti, token amministrativi, capability WordPress o destinatari interni. I fogli `Tickets` e `Selections` sono proiezioni idempotenti dello snapshot: possono essere ricostruiti se una scrittura secondaria fallisce.

Le rate sono righe distinte in `Payments` con tipo `FULL`, `DEPOSIT` o `BALANCE`. Lo stato aggregato dell'ordine è derivato dalle rate e può assumere anche `PARTIALLY_PAID`; per esempio una caparra `PAID` con saldo `PENDING` non equivale a pagamento completo.

### Inserimento manuale dei pagamenti

Il primo rilascio usa due fogli nello stesso spreadsheet:

- `PaymentIntake`, area protetta modificabile soltanto dagli operatori finanziari globali autorizzati;
- `Payments`, registro canonico scritto da Apps Script e non modificato direttamente durante il flusso ordinario.

Le colonne editabili di `PaymentIntake` sono almeno:

- `order_code`;
- `received_at`;
- `amount_received` nel formato valuta del foglio;
- `payment_source`, con convalida a elenco obbligatoria: `BANK_TRANSFER`, `EXTERNAL_CARD`, `CASH`;
- `external_reference`, facoltativo e privo di dati completi della carta;
- `operator_label`, obbligatorio per `CASH` e quando Google non espone l'identità dell'editor;
- `administrative_note`, facoltativa.

Apps Script convalida le righe tramite comando esplicito o trigger installabile, converte l'importo nel valore canonico `amount_cents`, risolve `order_id`, verifica valuta e duplicati, assegna `payment_id` e scrive `Payments`. Le colonne di esito dell'area di acquisizione indicano `PENDING`, `VALIDATED` o `REJECTED` e un errore breve. Soltanto righe canoniche convalidate contribuiscono agli aggregati dell'ordine.

Ogni riga canonica conserva anche `recording_channel: MANUAL_SHEET`, riferimento alla riga di acquisizione, timestamp e attore quando disponibile. Una correzione di un pagamento convalidato genera una rettifica collegata invece di sovrascrivere la storia. Per la fonte `EXTERNAL_CARD` non sono ammessi PAN, data di scadenza, CVV o altri dati della carta.

L'accesso Google allo spreadsheet globale consente la visibilità dei dati di tutte le attività ed è quindi distinto dalle ACL WordPress per attività. Non viene concesso ai normali delegati activity-scoped: gli editor di `PaymentIntake` sono operatori finanziari globali. Un futuro operatore finanziario limitato a una singola attività dovrà usare il pannello WordPress oppure un intake separato che non esponga le altre attività.

## 9. Ruoli WordPress, scope attività e audit

WordPress è la fonte autorevole per autenticazione e autorizzazione del pannello e delle API del plugin:

- durante l'attivazione le capability del plugin vengono assegnate agli amministratori WordPress; ogni controllo usa le capability effettive e non il nome del ruolo;
- i delegati usano account personali e capability granulari;
- lo scope `ALL` è riservato agli amministratori WordPress; ogni delegato riceve una lista esplicita di `activity_id`, che non include automaticamente attività create in futuro;
- ruoli e assegnazioni sono salvati in WordPress, non in `Organizations`, `Activities`, `EventRevisions` o Google Sheets;
- nascondere menu non sostituisce mai i controlli lato server.

Nel primo rilascio i delegati selezionano asset già approvati attraverso un endpoint del plugin limitato alle immagini consentite; l'upload di nuovi allegati rimane riservato agli amministratori WordPress, evitando di concedere capability generali sui media.

Per una richiesta amministrativa WordPress costruisce, dopo controllo di sessione, nonce e capability, un contesto server-to-server simile a questo:

```json
{
  "request_id": "req_demo_0001",
  "actor": {
    "kind": "WORDPRESS_USER",
    "actor_ref": "wp_user_demo_7",
    "capability": "eventi_manage_orders",
    "activity_scope": ["act_demo_youth"]
  },
  "target": {
    "organization_id": "org_demo_parish",
    "activity_id": "act_demo_youth"
  }
}
```

Questo contesto:

- non proviene dal browser e non può essere sovrascritto dal payload utente;
- viene incluso nella richiesta HMAC firmata con timestamp e nonce anti-replay;
- viene rifiutato se l'attività target non è nello scope effettivo; soltanto un amministratore WordPress può avere scope amministrativo `ALL`;
- viene ridotto ai soli dati necessari e non contiene password, cookie o nonce WordPress;
- viene registrato in `AuditLog` con `request_id`, `actor_ref`, capability, scope effettivo, organizzazione, attività target, azione, entità, esito e timestamp.

GAS verifica firma, freschezza, nonce e coerenza dello scope prima di leggere o modificare dati privati. Anche esportazione, retry email, modifica pagamento e cambio stato ripetono il controllo. Le richieste pubbliche usano un contesto distinto `PUBLIC` legato all'evento e non ottengono mai capability amministrative.

L'acquisizione manuale da `PaymentIntake` è un canale separato e non finge di provenire da WordPress. Il foglio e gli intervalli sono protetti per operatori finanziari globali autorizzati come editor Google; l'audit usa `channel: MANUAL_SHEET` e registra l'identità dell'editor quando disponibile. Se Google non la espone, conserva l'operatore dichiarato e marca l'identità come non verificata automaticamente.

## 10. Integrità minima

- I payload usano un'allowlist di proprietà, limiti di lunghezza e tipi espliciti.
- Le FK vengono verificate prima di acquisire il lock di scrittura.
- Prezzi, sconti, caparra e saldo sono ricalcolati dalla revisione pubblicata.
- La scadenza degli ordini non pagati dichiara sia `unpaid_order_expiry_minutes` sia `unpaid_order_expiry_mode`, con valori `MANUAL` o `AUTO`; il default di Fase A è 4.320 minuti e `MANUAL`.
- Il lock protegge soltanto idempotenza, disponibilità e commit autorevole; email e chiamate esterne restano fuori.
- Ogni evento, attività e organizzazione archiviati rimangono leggibili per ordini e audit storici.
- Cache di eventi e branding sono indicizzate per ID e revisione; una cache globale condivisa tra eventi non è ammessa.
- Dati destinati a Sheets o CSV sono neutralizzati contro formula injection.
- I log tecnici non contengono dati personali ordinari; l'audit conserva solo quanto necessario a ricostruire l'azione amministrativa.
