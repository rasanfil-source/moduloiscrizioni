# Segreteria eventi 3.26.8

Gestione iscrizioni riordinata: riepilogo evento, elenco partecipanti con filtri, report e stampe, gestione camere richiudibile, riepilogo servizi richiudibile e rapporto annuale in fondo.

Ordinamento dalle intestazioni Partecipante e Camera, con verso crescente/decrescente condiviso da pagine, stampa e CSV. Rimossi il selettore Camera duplicato e il collegamento ai report economici, disponibile nella scheda Pagamenti.

Colonna Referente sostituita da R cerchiata blu: solo nelle iscrizioni multiple, quando nome e cognome del referente identificano un unico partecipante della stessa prenotazione. Nessun simbolo per casi ambigui o referente esterno.

Stampa e CSV senza colonne di codice prenotazione e referente; indicatore R nel nome. Camere, dati mancanti, richieste, presenze rilevate e scadenze sono proposti quando pertinenti. Campi aggiuntivi con etichette leggibili. Pulsanti principali uniformati a 48 px.

Pacchetto `dist/modulo-iscrizioni-3.26.8.zip` con checksum SHA256. Apps Script invariato rispetto alla 3.26.1.

Verifiche: 230 test Node; browser su riepilogo, assegnazioni camere e cambio sistemazione; sintassi PHP, sanitizzazione e confronto file per file dello ZIP.

Installazione WordPress non eseguita: controllo del browser bloccato dal limite d’uso del servizio. La versione pubblicata sul sito rimane 3.26.7 finché non viene caricato il nuovo pacchetto.
