# Modulo Iscrizioni 3.27.32

Rilascio del 9 novembre 2026. Conservazione del grassetto nelle email delle comunicazioni.

## Difetto riprodotto

Un testo con grassetti corretti e un marcatore `**` finale spaiato produceva HTML con elementi `strong` e gli asterischi residui. Alla seconda normalizzazione, la presenza di quegli asterischi causava la ricostruzione dell'intero HTML a partire dal testo semplice gia ripulito: il grassetto del messaggio spariva. Il titolo dell'evento restava evidenziato da un passaggio separato.

## Correzione

La normalizzazione conserva l'HTML gia formattato e interpreta gli eventuali marcatori Markdown soltanto nei nodi di testo. Tag, attributi e collegamenti esistenti restano intatti. Il comportamento e applicato alle istantanee e alla riparazione dei modelli testuali. L'HTML in ingresso continua a passare dalla sanitizzazione.

I marcatori spaiati sono testo letterale: non si deduce automaticamente dove l'autore intendesse aprire o chiudere un grassetto. Nel messaggio segnalato eliminare i due asterischi finali dopo l'ultima frase. Dopo l'aggiornamento preparare una nuova anteprima e una nuova prova; le email gia ricevute non possono cambiare.

Unica modifica funzionale rispetto alla 3.27.31: riparazione della formattazione nel modello email. Invariati destinatari, code, trasporto Google, correzione Messaggi e colonne degli orari per i nuovi eventi.

## Installazione

Aggiornare soltanto il plugin WordPress con `modulo-iscrizioni-3.27.32.zip` oppure la consueta variante `-pubblico`. Nessun nuovo aggiornamento Google richiesto da questa correzione. Per le colonne degli orari rimane il sorgente MODULI AUTONOMO 3.27.31 gia consegnato.

## Verifiche

Regressione che fallisce con il codice precedente al secondo passaggio e conserva i tre grassetti dopo cinque normalizzazioni con la correzione. Verificati anche avviso della prova, HTML finale, collegamenti, attributi contenenti asterischi, modelli salvati e testo HTML inserito dall'utente. Suite completa `tools/verify.ps1` superata: 440 test JavaScript, 7 test degli asset generati, script PHP, sintassi e sanitizzazione. Collaudo browser WebKit e verifica visiva dell'HTML finale superati: tutti e tre i passaggi risultano in grassetto, con font-weight 700. Nessuna email reale inviata e nessuna installazione in produzione.

Gli archivi precedenti, incluso 3.26.226, restano invariati.
