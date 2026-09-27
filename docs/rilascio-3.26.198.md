# Versione 3.26.198

Corregge i tre difetti confermati nell'audit del saldo pubblico e le parti comprovate di otto segnalazioni dell'audit esterno. Include l'ordinamento predefinito per data di iscrizione della 3.26.197.

## Correzioni

- Le modifiche logistiche e le rettifiche senza variazioni economiche conservano le scadenze, anche quando assenti o già trascorse. Le variazioni economiche reali continuano ad applicare le regole di riapertura, considerando anche le quote individuali.
- Il saldo pubblico rispetta i gruppi esclusivi dei servizi già bloccati e conserva le quantità superiori a uno. La segreteria può modificare le quantità entro il massimo configurato, con anteprima e conferma.
- Il salvataggio delle opzioni da wp-admin conserva categoria e gruppo esclusivo. Validazione e salvataggio dei prezzi usano lo stesso parser per decimali e separatori delle migliaia.
- La cancellazione esplicita di un recapito resta vuota anche nel foglio Google. Telefoni e date modificati rispettano la validazione dello schema; i dati storici invariati non impediscono modifiche ad altri campi. La ricerca comprende gli alias dei recapiti personali.
- Il periodo del rapporto presenze non blocca il salvataggio quando il rapporto è disabilitato.
- La ricerca mobile mantiene la larghezza del campo e dell'icona senza comprimere il comando Ordina oltre il bordo della pagina.
- Nel foglio Pagamenti rimborsi e storni hanno segno negativo. Le colonne monetarie recuperano il formato numerico anche su proiezioni già esistenti.

## Ambito dell'audit esterno

Incluse le segnalazioni 1, 2, 3 e 4; incluse solo le parti comprovate di 5 (periodo disabilitato), 6 (alias di ricerca), 7 (segno nella proiezione) e 10 (formato monetario). Le segnalazioni 8 e 11 riguardano percorsi storici dismessi. La 9 non modifica la condivisione intenzionale con link né la preparazione storica. Non vengono introdotti collegamenti automatici tra familiari o dipendenze implicite tra supplementi e alloggi.

La conservazione dei metadati impedisce nuove perdite: non ricostruisce automaticamente categorie o gruppi esclusivi già cancellati in precedenza.

## Installazione

1. Caricare `modulo-iscrizioni-3.26.198.zip` in WordPress e sostituire il plugin esistente. Questo archivio locale include la configurazione del sito e non va pubblicato. L'archivio `-pubblico.zip` esclude tale configurazione.
2. Aggiornare `Codice.gs` nel progetto Apps Script autonomo con `Codice-Workspace-Progetto-3.26.198.gs`, conservando manifest e proprietà.
3. Aggiornare la distribuzione Web App esistente con una nuova versione e lo stesso endpoint. Le correzioni ai fogli si applicano alla successiva sincronizzazione degli eventi.

L'installazione WordPress resta a cura dell'utente. La preparazione degli archivi non certifica il completamento delle operazioni remote, il cui esito viene comunicato separatamente.

## Verifiche

368 test Node, 7 test asset, lint e suite PHP della verifica generale; 12 suite PHP/InnoDB e 6 suite browser della campagna operativa. Regressioni aggiuntive coprono le quantità dei servizi e il periodo presenze disabilitato nel browser. I test usano dati sintetici e un database locale isolato.

Gli ZIP sono verificati file per file rispetto ai sorgenti; i checksum SHA-256 sono disponibili accanto agli archivi.

Il 27 settembre 2026 il codice GAS 3.26.198 è stato pubblicato nella distribuzione esistente come versione 15. Il contenuto della versione è stato riletto e confrontato con il bundle; manifest ed endpoint sono invariati e la Web App risponde correttamente al controllo di stato.
