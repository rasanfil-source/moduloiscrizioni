# Modulo Iscrizioni 3.26.268

- I link permanenti di gruppo inviano istruzioni esplicite di mancata memorizzazione per cache condivise, CDN, proxy, Nginx e LiteSpeed, sia quando reindirizzano all’evento sia quando mostrano la pagina d’attesa.
- Il resolver resta escluso dalla cache WordPress tramite `DONOTCACHEPAGE` e attiva anche il controllo di mancata cache LiteSpeed.
- Scopo: impedire che una risposta d’attesa venga riutilizzata al posto del redirect aggiornato per le persone che aprono il link permanente.

La correzione richiede di installare il nuovo ZIP. Una cache che serve una risposta prima di eseguire WordPress deve inoltre essere svuotata o configurata per escludere `/12cesteroma` e gli altri link permanenti. Nessuna modifica al server di produzione è stata eseguita.
