# Rilascio 3.27.43 — correzione dei rilievi comprovati dell'audit

10 novembre 2026. Aggiornamento WordPress e del solo progetto Google operativo **MODULI AUTONOMO**.

## Installazione

1. In MODULI AUTONOMO sostituire il sorgente unico con `dist/Codice-Workspace-Progetto-3.27.43.gs`, salvare e aggiornare la distribuzione Web App esistente mantenendo URL e impostazioni. Questo aggiornamento permette al cron di seguire il redirect canonico del sito.
2. Caricare in WordPress `dist/modulo-iscrizioni-3.27.43.zip`. La variante `-pubblico.zip` esclude la configurazione privata del saldo ed è quella pubblicata su GitHub.
3. La pubblicazione GitHub non installa il plugin o il sorgente Google e non avvia email, prove o campagne.

Non servono altri file HTML. Il sorgente completo include anche le correzioni GAS già presenti nella 3.27.41.

## Rilievi corretti

| Rilievo | Comportamento finale |
| --- | --- |
| Pagamento unico | FULL_PAYMENT usa il totale iniziale anziché la caparra zero. Il sollecito dell'importo iniziale usa il residuo corrente e il testo standard parla di pagamento; i pagamenti estinti sono esclusi. Il promemoria saldo conserva il link personale. Le caparre a rate restano calcolate sul proprio residuo. |
| Modello disabilitato | Il checkbox continua a disabilitare la sola conferma iniziale. Le notifiche di lista d'attesa e di offerta posto sono autonome, come già le altre comunicazioni. La modalità globale di invio resta rispettata. |
| Eliminazione e titoli | Il confronto di conferma decodifica le entità HTML su entrambi i lati; restano obbligatori titolo corretto, permessi, riepilogo aggiornato e conferma esplicita. |
| Riferimenti numerici | Il controllo Luhn si limita a sequenze complete di 15–16 cifre e non estrae sottosequenze da riferimenti più lunghi o alfanumerici. Rimane il rifiuto dei numeri completi riconosciuti nelle note/riferimenti. |
| Indice storico | Revisione schema 6: l'indice type_actor con colonne obsolete viene sostituito con un solo ALTER; la revisione viene registrata solo dopo verifica delle colonne. Un errore lascia la migrazione da riprovare. |
| Testo semplice | Oggetto e titolo evento anteposti al corpo sono decodificati nel risultato finale, sia istituzionale sia ordinario. |
| Workspace occupato | Il lock locale e i codici remoti WORKSPACE_BUSY/EVENT_BUSY diventano attesa con sessione conservata e nuovo tentativo dopo tre secondi. Non consumano i tentativi del backoff ordinario. Gli altri errori restano errori. Messaggio italiano dedicato a WORKSPACE_BUSY. |
| Disattivazione | Rimossi anche gli hook mi_web_push_registration e mi_riprova_notifica_pubblicazione, comprese le occorrenze con argomenti. |
| Cron GAS | Il richiamo a wp-cron.php segue i redirect HTTP e verifica il codice finale. Un errore del sito resta visibile. |

Inclusa la 3.27.42: riepilogo dell'invio nascosto dopo 30 minuti dalla conclusione rilevata, senza cancellare gli esiti o reinviare le email fallite.

## Punti già risolti e verifiche non comprovate

Già presenti le correzioni su selettore email QR/barcode rimosso, archivio tecnico delle ricevute Google, asset non HTTPS isolati per gruppo, collisione di pagine con link di gruppo già salvati e testo MIT completo.

Il singolo spazio nella PRIMARY KEY non è la causa ipotizzata nei WordPress attuali. La chiamata get_option del resolver non dimostra una query SQL aggiuntiva a ogni URL. La visualizzazione dell'apostrofo nei telefoni Google Sheets resta da verificare sul campo: non è un difetto comprovato e non è stato rimosso il neutralizzatore delle formule.

Consultazione del saldo per cognome e invio al recapito valido scelto dal richiedente mantenuti secondo la decisione esplicita del 9 novembre 2026.

## Verifica

Regressioni applicative su quote individuali FULL_PAYMENT e pagamento parziale/estinto, caparre, link personale HTML/testo, modello disabilitato, entità, riferimenti Luhn, indice storico e cron. Prove MariaDB/InnoDB sulla sostituzione reale dell'indice e sulla conferma eliminazione con titolo codificato; campagna sintetica da 198 prenotazioni con trasporto simulato.

Verifica standard del progetto: test Node, asset generati, sintassi e suite PHP, sanitizzazione. I due ZIP vengono verificati voce per voce contro i sorgenti; i pacchetti precedenti sono confrontati mediante SHA-256 e non vengono ricostruiti. Nessuna email reale o modifica alla produzione durante le prove.
