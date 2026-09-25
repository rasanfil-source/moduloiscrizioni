# 3.26.179 — ricerca iscrizioni e scadenze dopo rettifica

La ricerca in Iscrizioni → Tutti gli eventi dispone ora del filtro per stato della prenotazione, mantenendo periodo, ricerca testuale e paginazione. La ricerca include anche nome e cognome dell'intestatario. Le due vecchie funzioni PHP della ricerca, non più raggiungibili dal router, sono state rimosse; le schede continuano a usare la gestione corrente.

Il nuovo campo evento «Tempo per pagare dopo una rettifica», disponibile nel portale e nell'amministrazione WordPress, è indipendente dal termine per accettare un posto liberato dalla lista d'attesa. Il valore predefinito è 48 ore, configurabile da 1 a 168. Una scadenza ancora futura viene conservata. Per una nuova scadenza si usa il valore dedicato salvato nella prenotazione; per le prenotazioni precedenti si usa l'impostazione dedicata dell'evento oppure 48 ore. Le scadenze già registrate non vengono migrate retroattivamente.

Il pacchetto include le modifiche locali della 3.26.178: tessere Eventi inizialmente chiuse e selezione iniziale dell'evento più recente in Iscrizioni, rispettando le selezioni esplicite e «Tutti gli eventi».

Verifiche: 352 test Node superati, sintassi PHP, suite PHP con regressioni su scadenze, ricerca e configurazione evento, asset minificati e controllo di sanitizzazione. La prova browser su sorgenti e asset minificati verifica filtro per stato, paginazione, azzeramento dell'offset, apertura scheda e interazione desktop/mobile.

## Pacchetti

- `dist/modulo-iscrizioni-3.26.179.zip`: pacchetto locale per l'installazione, con la configurazione privata presente nella cartella del plugin.
- `dist/modulo-iscrizioni-3.26.179-pubblico.zip`: pacchetto pubblicabile su GitHub, senza configurazione privata.
- Entrambi sono accompagnati dal checksum SHA-256 e verificati confrontando ogni file con i sorgenti.

Nessun aggiornamento Workspace/Google Apps Script necessario: non cambiano il protocollo di sincronizzazione o il tracciato dei fogli. L'installazione WordPress è a cura dell'utente; la pubblicazione su GitHub non aggiorna il sito.
