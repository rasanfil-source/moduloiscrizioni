# 3.26.200 — PWA senza registrazione dei tempi

Rimossi la misurazione delle chiamate Google, il salvataggio dei tempi e il pannello «Tempi della sincronizzazione Google». Il client Workspace e la sua pagina di impostazioni tornano al codice precedente all’aggiunta della diagnostica.

Restano la PWA online, le icone personalizzabili per gruppo, l’opzione per disattivare la proposta di installazione e le icone grafiche alleggerite. Nessun nuovo servizio o abbonamento.

## Installazione

Utilizzare `dist/modulo-iscrizioni-3.26.200.zip` al posto del pacchetto 3.26.199. Aggiornare solo il plugin WordPress; Apps Script non cambia.

Nel portale, aprire **App sul telefono**: scegliere il gruppo e, con il ruolo di responsabile del gruppo, caricare l’icona PNG quadrata da 512 a 4096 pixel, massimo 2 MB. Android: menu di Chrome, **Installa app** o **Aggiungi a schermata Home**. iPhone: Safari, **Condividi → Aggiungi alla schermata Home**.

La pagina web rimane sempre disponibile. L’app richiede Internet. La proposta di installazione si disattiva da **Modulo iscrizioni → App sul telefono**; le icone già installate si rimuovono dal telefono.

Lo ZIP locale conserva l’eventuale configurazione privata; quello `-pubblico` la esclude. I pacchetti sono verificati rispetto ai sorgenti e accompagnati da SHA-256. La preparazione dei pacchetti non aggiorna il sito. Restano da provare sul sito il caricamento delle immagini e l’installazione su telefoni reali.
