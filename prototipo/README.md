# Prototipo statico offline — Fase A

Wizard responsive dimostrativo per il sistema multi-evento di iscrizione. È una demo offline composta soltanto da HTML, CSS, JavaScript e immagini locali: non è il plugin WordPress e non dispone di backend, database, API, invio email, capienza reale o pagamento.

Nomi, date, prezzi, disponibilità, codice prenotazione e immagini sono fixture fittizie. Non inserire dati personali reali durante le prove, anche se la pagina non li trasmette né li persiste.

## Avvio locale

Non sono richieste installazioni, build o dipendenze frontend.

Metodo più semplice: aprire `prototipo/index.html` direttamente nel browser. In modalità `file://` tutte le funzioni restano disponibili tranne l'eventuale copia negli appunti, che alcuni browser limitano.

In alternativa, se Python è disponibile, avviare un server statico vincolato alla macchina locale. Dalla radice del progetto:

Esempio, dalla cartella del progetto:

```powershell
pwsh.exe -NoLogo -NoProfile -Command "Set-Location -LiteralPath '.\prototipo'; python -m http.server 8080 --bind 127.0.0.1"
```

Visitare quindi `http://127.0.0.1:8080/` e arrestare il server con `Ctrl+C`. Le richieste visibili negli strumenti di sviluppo devono riguardare soltanto questi file locali serviti da `127.0.0.1`; la demo non contatta host esterni.

## File

- `index.html`: struttura semantica dei sei passaggi;
- `assets/js/demo-config.js`: contenuti e regole esclusivamente dimostrativi;
- `assets/js/app.js`: navigazione, validazione, stato in memoria e calcoli UI;
- `assets/css/demo-shell.css`: stile globale della sola pagina standalone;
- `assets/css/wizard.css`: stile incorporabile, rigorosamente confinato sotto `.se-booking`;
- `assets/img/*.svg`: placeholder locali, non loghi ufficiali;
- `tests/checklist-manuale.md`: piano di collaudo manuale.

## Flusso

1. riepilogo evento;
2. posti e tipi di biglietto;
3. referente dell'ordine;
4. partecipanti separati dal referente;
5. opzioni, consensi e riepilogo;
6. conferma con stati distinti di prenotazione e pagamento.

I dati restano esclusivamente nella memoria della pagina e vengono persi al ricaricamento. Non vengono usati `localStorage`, `sessionStorage`, cookie o parametri URL per i dati personali.

## Gerarchia del logo

La funzione di risoluzione applica questa precedenza:

```text
logo evento (override) → logo attività → logo parrocchia
```

Il selettore tecnico in alto simula i tre casi:

| Scenario | URL locale equivalente | Risultato atteso |
|---|---|---|
| `Logo attività` | `/?brand=activity` | usa `logo-attivita.svg` e i colori dell'attività |
| `Logo evento` | `/?brand=event` | l'override usa `logo-evento.svg` e i colori dell'evento |
| `Logo parrocchia` | `/?brand=parish` | simula l'assenza dei livelli evento/attività e usa il fallback parrocchiale |

Gli URL presuppongono il server locale descritto sopra, per esempio `http://127.0.0.1:8080/?brand=event`. Un valore `brand` sconosciuto ricade in sicurezza sullo scenario attività.

La riga `Organizzato da [attività] · [parrocchia]` non cambia, così la provenienza organizzativa resta chiara anche con un logo evento in override. Il nome del brand e i colori della CTA seguono invece lo scenario risolto.

Il logo usa `object-fit: contain`; l'immagine editoriale dell'evento usa `object-fit: cover`.

## Prezzi e pagamento

Tutti gli importi della fixture sono centesimi interi. Il totale live serve soltanto a validare la UX; il testo del riepilogo ricorda che in produzione prezzi, disponibilità e totale saranno ricalcolati dal server.

## Scenari configurabili

Il prototipo usa esclusivamente query string locali e non invia dati:

- `?flow=registration&fields=minimal`: sola iscrizione, senza prezzo;
- `?flow=priced&fields=minimal`: prezzo informativo, incasso non gestito;
- `?flow=deposit&fields=extended`: caparra/saldo e campi estesi per indirizzo, taglia e numero maglia.

I valori predefiniti sono `flow=deposit` e `fields=minimal`. Tutti i nomi, codici, luoghi e importi sono fixture fittizie.

La pagina finale non contiene coordinate reali e non apre link esterni. Le azioni dimostrative per bonifico, carta e contanti mostrano soltanto un messaggio e non cambiano lo stato `In attesa di verifica`. In produzione la fonte effettiva del versamento sarà registrata manualmente nel foglio protetto previsto dal progetto.

## Accorgimenti di accessibilità presenti

Questi accorgimenti devono essere verificati con la checklist: la loro presenza non equivale da sola a una certificazione WCAG.

- navigazione a tastiera con controlli nativi;
- indicazione compatta `Passaggio n di 6` su mobile e stepper su desktop;
- focus spostato sul titolo del nuovo passaggio;
- errori vicini ai campi e riepilogo errori focalizzabile;
- label visibili, `fieldset` e `legend` per le opzioni;
- regioni live per gli aggiornamenti importanti;
- target interattivi di almeno 44 px;
- supporto a `prefers-reduced-motion` e `forced-colors`;
- CTA mobile con safe area e riepilogo richiudibile.

## Limiti intenzionali

- nessun backend, controllo capienza reale o ricalcolo autorevole;
- nessun invio email o pagamento;
- nessuna persistenza dopo il refresh;
- loghi e immagine sono illustrazioni placeholder;
- testi, prezzi e date non descrivono un evento realmente pubblicato.

Il parametro URL `brand` contiene soltanto lo scenario tecnico del logo; nessun dato del modulo viene scritto nella query string.

## Isolamento: demo standalone e futuro plugin

- `assets/css/wizard.css` contiene gli stili incorporabili ed è confinato sotto `.se-booking`;
- `assets/css/demo-shell.css` usa intenzionalmente selettori globali per la sola pagina standalone e non deve essere caricato dal plugin;
- il selettore `Scenario tecnico del logo` è un controllo della demo esterno a `.se-booking`; `app.js` lo aggiorna intenzionalmente e questo comportamento non fa parte dell'interfaccia pubblica finale;
- la configurazione globale `window.SE_BOOKING_DEMO_CONFIG` è una fixture locale, da sostituire con il contratto dati validato e pubblicato dal backend;
- il requisito di caricare gli asset soltanto nelle pagine WordPress che contengono modulo o pannello dovrà essere verificato durante l'integrazione del plugin, non può essere provato dalla sola pagina standalone.

Prima del riuso nel plugin, eseguire [la checklist manuale](tests/checklist-manuale.md) e sostituire la fixture con il contratto dati pubblicato dall'API WordPress.
