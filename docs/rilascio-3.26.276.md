# Modulo Iscrizioni 3.26.276

Rilascio del 5 novembre 2026. Corregge quattro problemi funzionali individuati nella revisione statica. Il progetto Google operativo resta **MODULI AUTONOMO**.

## Correzioni

- **Coda delle proiezioni:** il recupero delle iscrizioni pendenti ruota tra i gruppi di eventi. Un evento bloccato o privo di foglio non occupa sempre i primi dieci posti della coda.
- **Opzioni di trasporto e servizi:** rimuovendo una tratta o una voce aggiuntiva dal configuratore attivo, il salvataggio elimina l'opzione non più presente. Opzioni esterne al wizard e configurazioni conservate quando il calcolo dei servizi è disattivato restano intatte.
- **Domande a scelta:** il wizard permette di inserire almeno due opzioni per le nuove domande di tipo “Scelta” e conserva le opzioni già configurate.
- **Annullamento pubblico:** la pagina mostra l'esito della richiesta senza richiedere l'accesso degli operatori. Il flusso autenticato continua a mostrare l'esito nel portale.
- Rigenerati i file minificati del portale e il relativo manifest.

## Distribuzione

- `dist/modulo-iscrizioni-3.26.276.zip`: pacchetto locale, con l'eventuale configurazione locale del saldo pubblico.
- `dist/modulo-iscrizioni-3.26.276-pubblico.zip`: pacchetto senza `public-balance-config.json`.
- `dist/Codice-Workspace-Progetto-3.26.276.gs`: sorgente unico aggiornato per **MODULI AUTONOMO**. I sorgenti Apps Script non hanno richiesto modifiche funzionali; il file aggregato è stato rigenerato e allineato alla versione del plugin.

Aggiornare il plugin con il pacchetto appropriato. Per Google sostituire il contenuto di `Codice.gs` nel progetto operativo e aggiornare la distribuzione Web App esistente a una nuova versione, mantenendo URL e proprietà dello script. Nessun aggiornamento è stato applicato al sito o al progetto Google di produzione.

Gli archivi precedenti restano invariati, incluso il pacchetto 3.26.226.

## Verifiche

- Rigenerazione e controllo degli asset minificati e del manifest.
- Generazione del sorgente MODULI AUTONOMO con controllo sintattico Apps Script.
- Verifica degli archivi nuovi, dei contenuti e dei checksum; controllo che gli archivi precedenti siano rimasti invariati.
- Le suite automatiche Node e PHP non sono state eseguite in questo rilascio.
