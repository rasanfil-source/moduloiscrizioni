# Istruzioni permanenti del progetto

## Unico progetto Google operativo

- Il progetto operativo è MODULI AUTONOMO. MODULI e DB_MODULI sono dismessi e conservati soltanto come archivio di rollback.
- Non proporre aggiornamenti, codici o pacchetti per MODULI o DB_MODULI e non rimetterli in discussione nei normali rilasci. Un eventuale ripristino richiede una richiesta esplicita dell'utente.
- Consegnare esclusivamente i sorgenti destinati a MODULI AUTONOMO; non fornire varianti generali/storiche che generino ambiguità.
- Non chiedere di aggiungere Segreteria.html a MODULI AUTONOMO: le vecchie schermate non fanno parte del flusso operativo attuale. L'uso diretto dei fogli evento resta supportato.

## Comandi Windows

Usare sempre PowerShell 7 (`pwsh.exe`), mai `powershell.exe`. Eseguire i comandi PowerShell nella forma `pwsh.exe -NoLogo -NoProfile -Command "<comando>"`.

## Versioni e ZIP — richiesta esplicita dell'utente

- Ogni variazione del plugin da distribuire, anche minima o correttiva, richiede SEMPRE un nuovo numero di versione e un nuovo nome ZIP. Non riutilizzare un numero già assegnato a un pacchetto generato o consegnato.
- Non sovrascrivere, ricostruire con contenuto diverso o sostituire uno ZIP mantenendone il numero. I numeri non vanno risparmiati: incrementare la versione per ogni nuova variazione.
- Prima del confezionamento verificare versione del plugin, archivi già presenti e note di rilascio; usare un numero successivo al massimo già assegnato. Allineare intestazione Version, MI_VERSION, nome ZIP e note del nuovo rilascio.
- Le varianti locale e pubblica dello stesso rilascio possono condividere la versione, distinguendosi con il suffisso `-pubblico`. Per entrambe, non alterare archivi già generati.
- Questa regola è permanente: applicarla senza chiedere all'utente di ripeterla o riconfermarla.
- Il pacchetto 3.26.226 già consegnato deve rimanere invariato, come espressamente richiesto dall'utente. L'aggiunta di queste istruzioni non richiede di modificarlo.
