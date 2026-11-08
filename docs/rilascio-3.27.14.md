# Modulo Iscrizioni 3.27.14

Rilascio del 7 novembre 2026. Completa il rilascio 3.27.13: quando il termine per i passaggi è già arrivato, il modulo mantiene visibile una spiegazione anche dopo aver nascosto le scelte non più disponibili. I posti offerti oltre la finestra di 24 ore restano bloccati nell’interfaccia e convalidati anche dal server.

Verifica: suite completa `tools/verify.ps1`, test di integrazione del viaggio su MariaDB sintetico, controlli degli asset e sintassi del test browser. Il test browser Playwright non è stato eseguito perché la dipendenza non è installata.

Pacchetti `modulo-iscrizioni-3.27.14.zip` e variante `-pubblico`. Nessuna installazione in produzione; nessuna modifica Google richiesta. Archivi precedenti, compresi 3.27.12 e 3.27.13, invariati.
