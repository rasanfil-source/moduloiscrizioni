# Candidato 3.26.149 — proiezione autonoma e prestazioni

Questo candidato aggiorna sia WordPress sia Apps Script. Non autorizza la cancellazione di `DB_MODULI`: il workbook centrale resta il rollback in sola lettura finché il collaudo reale e il periodo di confronto non sono conclusi.

## Contenuto

- MySQL è la fonte autorevole; la Web App autonoma materializza direttamente i fogli evento e non può aprire il workbook centrale.
- Creazione, verifica, organizzazione, sincronizzazione inversa, eliminazione ed email usano identità firmate, proprietà dello script e dati WordPress; le rotte storiche sono rifiutate o non esposte.
- Le proiezioni grandi vengono prelevate da WordPress con HMAC, hash e impronta verificati.
- I refresh sono accorpati per evento; la gestione usa pagine SQL ordinarie e scansioni avanzate a cursore con prefisso ordinato limitato.
- Il controllo `tools/verifica-deployment-workspace.php` attesta in sola lettura le capacità del nuovo deployment senza esporre il segreto sulla riga di comando.

## Evidenze locali

- 333 test Node principali e 7 test cache superati nella verifica finale del 21 settembre 2026.
- Lint e suite PHP, asset generati e sanitizzazione superati con PHP 8.3.33.
- Benchmark sintetico da 10.000 righe: circa 96,5 ms per il selettore completo e 34,1 ms per la scansione avanzata; il prefisso conservato è limitato. I tempi non sostituiscono la misura sul database reale.

## Ordine di rilascio e rollback

1. Verificare backup MySQL, fogli evento e `DB_MODULI`; annotare deployment e versione plugin correnti.
2. Creare un progetto Apps Script autonomo e caricare `Codice-Workspace-Progetto-3.26.149.gs`. Non copiare i trigger del progetto vincolato.
3. Configurare le proprietà private richieste, incluso il segreto condiviso, il mittente e `MI_WORDPRESS_COMMAND_URL`; distribuire la nuova Web App con l’identità autorizzata.
4. Eseguire il controllo PHP firmato. Non procedere se `central_workbook=false` e le altre tre capacità non sono confermate.
5. Installare `modulo-iscrizioni-3.26.149.zip`, mantenendo disponibile il plugin precedente.
6. Collaudare con identità fittizie eventi gratuiti e a pagamento, modifiche Sheet → MySQL, email di prova, retry, ricostruzione, archiviazione ed eliminazione.
7. Dopo il periodo di confronto senza differenze, disattivare i vecchi trigger e rendere `DB_MODULI` sola lettura. In caso di errore ripristinare plugin e deployment precedenti; non cancellare dati.

Il rilascio non è dichiarato completato finché i punti 1–7 non sono documentati nell’ambiente reale.

## Evidenze di produzione del 21 settembre 2026

- Plugin 3.26.149 installato e attivo; portale pubblico caricato con asset 3.26.149.
- Creato il progetto Apps Script autonomo `MODULI AUTONOMO 3.26.149`, senza copiare i trigger del progetto storico.
- Web App autonoma pubblicata come versione 2 ed eseguita dall’account parrocchiale; WordPress è stato collegato al nuovo deployment.
- Il controllo firmato ha risposto correttamente in modalità anteprima.
- Il controllo delle capacità ha confermato: proiezione diretta, modalità autonoma, prelievo firmato delle proiezioni grandi e assenza del workbook centrale.
- Il progetto storico `MODULI`, il vecchio deployment e `DB_MODULI` sono rimasti invariati per il rollback.
- Nell’editor dell’evento 8083 è emerso un avviso PHP per il campo `help` mancante nel catalogo del cellulare. La correzione difensiva è inclusa nel micro-rilascio plugin 3.26.150.

## Impronte del candidato

- `modulo-iscrizioni-3.26.149.zip`: `9a80b94423cdfe20665f09bdf1efe58bfc70ff5e51cb6af70a8fb9ff54a3ceb8`
- `Codice-Workspace-Progetto-3.26.149.gs`: `a389e2746c792a7f24e497cb4d42aea68476de5297c398ee4728f5bc6f7023a4`

Il file Workspace senza `-Progetto-` è storico e non deve essere distribuito come nuova Web App autonoma.
