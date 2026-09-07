# Interfaccia movimenti nei fogli evento — 3.23.22

Ogni file operativo di un evento che prevede importi contiene due schede distinte:

- **Registra movimento**, modulo visuale per l’operatore;
- **Pagamenti**, storico riconciliato e non modificabile dei movimenti.

Gli eventi configurati come **Evento totalmente gratuito** non mostrano nessuna
delle due schede. Le eventuali schede economiche preesistenti vengono nascoste e
conservate, senza cancellarne i dati. Tutte le altre configurazioni economiche
generano le schede fin dalla creazione del file evento.

Il modulo limita le prenotazioni all’evento corrente e, dopo la scelta, mostra
stato, referente, totale, versato e residuo. I menu cambiano in base allo snapshot
della prenotazione:

- il selettore mostra **nome e cognome** come informazione principale ed è
  ricercabile digitando le prime lettere;
- il codice prenotazione compare dopo il nome, separato e in seconda posizione,
  per distinguere con certezza gli omonimi e alimenta la mappa interna;
- il campo **Riferimento** è facoltativo;
- campi modificabili, riepilogo non modificabile, residuo, comando e messaggi di
  stato hanno segnali grafici distinti;

- soluzione unica: rata **Intero**;
- caparra e saldo: **Caparra**, **Intermedio**, **Saldo**;
- metodi: soltanto quelli abilitati nell’evento;
- **Incasso** soltanto in presenza di residuo;
- **Rimborso** e **Storno** soltanto in presenza di somme già versate.

Il comando finale **REGISTRA MOVIMENTO** è gestito da un trigger `onEdit` specifico
del file evento. Non è un timer e non invia email. Prima della scrittura verifica
che il file sia quello registrato per l’evento, riusa il validatore economico
centrale, impedisce sovraversamenti e rimborsi eccessivi e aggiorna lo storico.

Nel progetto Apps Script **MODULI**, `InterfacciaMovimenti.gs` e
`InterfacciaIscrizioni.gs` restano file separati. Per aggiornare `Codice.gs` si usa
`dist/Codice-Workspace-Progetto-3.23.22.gs`, generato insieme al pacchetto completo:
in questo modo le costanti delle due interfacce non vengono dichiarate due volte.
