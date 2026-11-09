# Istruzioni permanenti del progetto

## Unico progetto Google operativo

- Il progetto operativo è MODULI AUTONOMO. MODULI e DB_MODULI sono dismessi e conservati soltanto come archivio di rollback.
- Non proporre aggiornamenti, codici o pacchetti per MODULI o DB_MODULI e non rimetterli in discussione nei normali rilasci. Un eventuale ripristino richiede una richiesta esplicita dell'utente.
- Consegnare esclusivamente i sorgenti destinati a MODULI AUTONOMO; non fornire varianti generali/storiche che generino ambiguità.
- Non chiedere di aggiungere Segreteria.html a MODULI AUTONOMO: le vecchie schermate non fanno parte del flusso operativo attuale. L'uso diretto dei fogli evento resta supportato.

## Comandi Windows

Usare sempre PowerShell 7 (`pwsh.exe`), mai `powershell.exe`. Eseguire i comandi PowerShell nella forma `pwsh.exe -NoLogo -NoProfile -Command "<comando>"`.

## Saldo pubblico — scelta esplicita del 9 novembre 2026

- E accettato per scelta che chiunque, digitando un cognome nella pagina pubblica di verifica del saldo, possa consultare quanto deve pagare la persona individuata, senza autenticazione o verifica del recapito.
- E accettato esplicitamente anche l'invio del riepilogo a un indirizzo email valido scelto dal richiedente nella pagina, anche diverso da quello registrato nella prenotazione, senza verifica OTP del recapito.
- Per il momento mantenere entrambi i comportamenti. Non introdurre login, OTP, collegamenti personali obbligatori o vincoli al solo recapito registrato come correzione automatica degli audit. Una modifica di questa scelta richiede una successiva indicazione dell'utente.
- La consultazione per cognome e l'invio al recapito scelto sono requisiti intenzionali accettati, non difetti da correggere d'ufficio. La decisione non impedisce le correzioni delle altre segnalazioni valide degli audit, successivamente autorizzate dall'utente.
- Decisione e ambito: `docs/decisione-saldo-pubblico-2026-11-09.md`.

## Versioni e ZIP — richiesta esplicita dell'utente

- Ogni variazione del plugin da distribuire, anche minima o correttiva, richiede SEMPRE un nuovo numero di versione e un nuovo nome ZIP. Non riutilizzare un numero già assegnato a un pacchetto generato o consegnato.
- Non sovrascrivere, ricostruire con contenuto diverso o sostituire uno ZIP mantenendone il numero. I numeri non vanno risparmiati: incrementare la versione per ogni nuova variazione.
- Prima del confezionamento verificare versione del plugin, archivi già presenti e note di rilascio; usare un numero successivo al massimo già assegnato. Allineare intestazione Version, MI_VERSION, nome ZIP e note del nuovo rilascio.
- Le varianti locale e pubblica dello stesso rilascio possono condividere la versione, distinguendosi con il suffisso `-pubblico`. Per entrambe, non alterare archivi già generati.
- Questa regola è permanente: applicarla senza chiedere all'utente di ripeterla o riconfermarla.
- Il pacchetto 3.26.226 gia consegnato non va modificato o rigenerato. La successiva richiesta del 9 novembre 2026 autorizza l'eliminazione delle vecchie distribuzioni, compresa questa, mantenendo gli ultimi cinque rilasci.

## Conservazione delle distribuzioni — 9 novembre 2026

- Conservare soltanto gli ultimi cinque rilasci nelle distribuzioni locali e nel materiale di rilascio del repository. Alla data della pulizia: 3.27.37, 3.27.38, 3.27.39, 3.27.40 e 3.27.41, con entrambe le varianti ZIP e relativi checksum.
- Eliminare i vecchi pacchetti, le esportazioni versionate, i checksum e gli helper/note relativi ai rilasci precedenti. Non rigenerare o alterare gli archivi conservati; i numeri eliminati restano gia assegnati e non possono essere riutilizzati.
- La pulizia non cambia i sorgenti operativi e non richiede una nuova versione del plugin. Conservare documentazione operativa, test e decisioni funzionali.
- La cancellazione dei file dall'albero corrente e delle release GitHub non riscrive automaticamente la cronologia dei commit. Un'eventuale riscrittura va definita separatamente.
- Riferimento: `docs/distribuzioni-conservate.md`.
