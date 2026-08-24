# Backend Google Workspace — Fase 2

Progetto Google Apps Script associato a uno spreadsheet dedicato. Il repository non contiene ID del foglio, URL di deployment, destinatari reali, coordinate bancarie o segreti.

## Funzioni disponibili

- `setupWorkbook()` crea o aggiorna in modo idempotente le schede tecniche;
- `validateSelectedPayments()` convalida le righe selezionate in `PaymentIntake`;
- `validatePendingPayments()` elabora tutte le righe nuove;
- `doPost()` accetta soltanto richieste firmate provenienti dal proxy WordPress;
- l'outbox email resta sempre nello stato `PREVIEW`.

## Prima configurazione

1. Aprire lo spreadsheet dedicato con un account proprietario.
2. Usare **Estensioni → Apps Script** per creare un progetto associato al foglio.
3. Copiare i file di `src/` nel progetto e sostituire il manifest con `appsscript.json`.
4. Eseguire `setupWorkbook()` e concedere esclusivamente le autorizzazioni richieste da Sheets.
5. Nelle proprietà dello script creare `MI_SHARED_SECRET` con almeno 32 caratteri casuali. Non copiarlo nel repository o nel browser pubblico.
6. Mantenere lo spreadsheet privato e concedere l'accesso soltanto agli operatori autorizzati.

La Web App non deve essere distribuita finché WordPress non implementa la stessa canonicalizzazione e firma HMAC e non è stato completato un collaudo con identità fittizie.

## Pagamenti manuali

Gli operatori modificano soltanto `PaymentIntake`. Le fonti ammesse sono `BANK_TRANSFER`, `CARD` e `CASH`; non devono mai essere inseriti numero completo della carta, scadenza o CVV. I movimenti convalidati vengono copiati in `Payments` e non vengono sovrascritti.
