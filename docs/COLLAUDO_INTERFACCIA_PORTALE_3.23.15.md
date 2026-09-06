# Collaudo interfaccia portale 3.23.15

## Modifiche verificate

- Il contenitore del portale usa `box-sizing: border-box` per evitare lo scorrimento orizzontale sui dispositivi stretti.
- I controlli distruttivi mantengono un'area tattile minima di 44 px.
- Ogni tessera evento mostra in alto a sinistra una doppia freccia; l'orientamento cambia quando la tessera è aperta.
- Nella modifica rapida il pulsante **Salva modifiche** è allineato a destra e compare solo dopo una variazione effettiva dei campi.
- Al passaggio 8 il pulsante **Salva le modifiche** è allineato a destra. Durante la richiesta viene mostrato l'avviso sul ritorno a Gestisci eventi; dopo il redirect compare **Modifiche salvate.**

## Impatto sulle prestazioni

Il rilevamento delle modifiche confronta due serializzazioni del solo modulo già presente nella pagina. Non interroga WordPress, il database o la rete e si attiva soltanto sugli eventi `input` e `change`, quindi non aggiunge lavoro al caricamento iniziale dei dati.

## Verifiche automatiche

Il pacchetto è sottoposto al controllo sintattico JavaScript, ai test strutturali del plugin e alla suite completa condivisa con Apps Script. Il collaudo reale in WordPress viene registrato dopo l'installazione dello ZIP.
