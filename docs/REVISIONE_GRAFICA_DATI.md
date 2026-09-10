# Consolidamento grafico della presentazione dei dati

Intervento incluso nella versione 3.26.5, basato sui due audit grafici forniti dall’operatore. Per lo stato di pubblicazione vedere [note di rilascio](rilascio-3.26.5.md).

## Applicato

Secondo audit: gerarchia primaria/secondaria nelle liste e nella ricerca pagamenti, dettaglio con etichette sopra i valori, griglia amministrativa dei facts più ordinata, campi amministrativi di almeno 44px e distanza di 24px, badge admin allineati alla palette, tessera evento selezionata con segnale sinistro blu e superfici dati senza ombre. La griglia facts esisteva già: non è stata ricostruita. Non si adottano uppercase forzato, grigio indistinto per ogni attesa o azioni a sole icone, per mantenere leggibilità e significati concordati. Public.css rimane invariato.

- Palette comune per il portale, Gestione iscrizioni e Pagamenti: un grigio secondario, quattro coppie semantiche testo/sfondo, focus e colori di base condivisi. Fallback per dialog fuori dal contenitore.
- Operazione in corso blu informativo; attenzione richiesta ambra. Posto proposto resta ambra perché richiede una risposta. Colori sempre accompagnati da testo.
- Pesi tipografici ridotti a 400/600/700; testi espliciti sotto .75rem portati a .75rem.
- Riepilogo diviso in Persone, Importi e Dati da completare; parte economica solo quando pertinente. Versato netto raccolto nella parte Importi.
- Intestazioni di tabella più leggibili, righe alternate discrete nell’elenco persone, importi e conteggi allineati, gruppi camere evidenti. Nessun aspetto da pulsante per i dati.

## Valutazioni degli audit

Non si applica una sostituzione indiscriminata a public.css o admin.css: hanno contesti e colori di marca propri. Non vengono modificate struttura delle tessere, creazione evento, modulo pubblico, tariffe, filtri o algoritmi di assegnazione. La palette dei badge delle tessere resta semanticamente distinta dallo stato di una prenotazione: un evento concluso non è un errore.

La proposta di uniformare date/fusi orari è utile ma richiede una verifica funzionale separata sul fuso del sito e sui dati memorizzati. Analoga distinzione per i valori mancanti, che possono esprimere assenza, mancata applicabilità o un’operazione ancora da compiere. Soglie della barra di capienza e riorganizzazione dei metadati delle tessere sono interventi successivi.

## Verifiche

Prove browser con dati sintetici: riepilogo gratuito/a pagamento, paginazione, CSV e stampa, camere, anteprima del cambio unico, retry e viewport telefono. Controllo visivo di riepilogo e camere. Rapporti di contrasto delle coppie canoniche: muted su sfondo pagina 4.65:1; positivo 5.81:1; attenzione 6.17:1; pericolo 6.94:1; informazione 7.70:1. Si tratta delle coppie verificate, non di una certificazione completa di accessibilità del portale.
