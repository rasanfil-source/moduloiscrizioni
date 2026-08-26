# Allineamento tra documentazione e codice

Verifica sistematica eseguita per la versione 3.4.18. Questo documento prevale sulle descrizioni progettuali quando occorre distinguere ciò che è già disponibile nella prima versione da ciò che rappresenta l'architettura prevista.

## Implementato e verificato

| Comportamento confermato | Stato nel codice | Verifica |
|---|---|---|
| Evento associato a una sola attività e accesso delegato limitato per attività | Implementato | controlli capability e assegnazione attività nel plugin |
| Revisione pubblicata e snapshot dell'iscrizione | Implementato | revisione, hash e snapshot persistiti e replicati |
| Capienza globale/per tipologia, lista d'attesa, scadenza e rilascio posti | Implementato | servizio iscrizioni e test strutturali/comportamentali |
| Nome e cognome di ogni partecipante obbligatori | Implementato | validazione WordPress e GAS |
| Campi dei partecipanti configurabili per evento | Implementato | campi predefiniti e domande personalizzate tipizzate |
| Email e cellulare dei partecipanti configurabili | Implementato | schema campi e validazione dedicata |
| Richieste particolari facoltative | Implementato | configurazione evento, modulo, database, export e replica Sheets |
| Prezzo assente, informativo, quota comune, pagamento completo o caparra/saldo | Implementato | calcolo autorevole lato server |
| Pagamenti e rimborsi registrati senza sovrascrivere la storia | Implementato | movimenti separati e ricalcolo del totale |
| Riconciliazione dei pagamenti inseriti in `PaymentIntake` | Implementato | GAS espone i movimenti canonici non WordPress; il plugin li importa in modo idempotente |
| `effective_at` uniforme in UTC | Implementato | l'orario inserito nel pannello viene interpretato nel fuso del sito e salvato in UTC |
| HMAC e anti-replay | Implementato | finestra 120 secondi, `ScriptLock`, cache e registro nonce durevole in Script Properties |
| Modalità email sicure | Implementato | anteprima predefinita, prova su destinatario controllato, operativo protetto |
| Sanitizzazione del repository pubblico | Implementato | `tools/check-sanitization.ps1` eseguito anche da GitHub Actions su push e pull request |

## Implementato in forma più semplice nella v1

| Descrizione progettuale | Ambito reale della v1 |
|---|---|
| Entità logiche separate per risposte, selezioni e piano rateale | Il database WordPress usa tabelle normalizzate per le entità principali e JSON controllato per alcune risposte/opzioni; Sheets è una proiezione operativa, non una replica fisica completa dello schema concettuale. |
| Coda email completa con tentativi e notifiche | Sono presenti anteprima, prova controllata, invio protetto e stato della coda; l'osservabilità avanzata resta limitata. |
| Cache pubblica con grafo completo di invalidazione | La configurazione pubblica è calcolata dal backend e legata alla revisione; non è implementato un sottosistema generale di dipendenze e cache. |
| Audit con differenze complete | Sono registrate le operazioni essenziali; non tutte le modifiche producono un diff campo per campo. |
| Profili estesi con finalità e conservazione per singolo campo | I campi sono configurabili e i campi ad alto impatto sono controllati, ma non esiste ancora un motore completo di policy per ogni domanda. |

## Fuori dallo scope della v1

- riscossione diretta o conferma automatica tramite API di banche e carte;
- CAPTCHA e rate limiting distribuito avanzato;
- portale amministrativo separato da `wp-admin`;
- cancellazione automatica dei dati allo scadere di politiche di conservazione configurabili;
- API REST amministrative generiche descritte in `PROGETTO.md` come architettura indicativa;
- infrastruttura a microservizi, code cloud o database applicativi ulteriori;
- garanzia di carico fino a 300 persone senza eseguire il collaudo previsto.

## Regola di manutenzione

Una funzione può essere marcata “confermata” nei documenti soltanto se esiste almeno una verifica ripetibile (test automatico o voce della checklist manuale). Ogni pull request che cambia un comportamento pubblico, amministrativo, economico o di replica deve aggiornare nello stesso commit il test e, se necessario, questa matrice e la guida operatore.
