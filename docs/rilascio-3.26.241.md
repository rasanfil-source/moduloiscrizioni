# Modulo Iscrizioni 3.26.241

Il portale comunica con Apps Script tramite risposte JSON e mantiene l'host Google autorizzato durante i reindirizzamenti. Le operazioni di creazione dei gruppi coordinano le richieste concorrenti e conservano il riferimento agli eventi eliminati logicamente.

Le schermate amministrative caricano gli identificativi degli eventi in modo paginato. Il saldo resta consultabile dal collegamento pubblico previsto, senza un'autenticazione WordPress aggiuntiva.

Gli eventi preparati da Apps Script ricevono un profilo operativo derivato dalla configurazione disponibile. L'eliminazione di un evento rimuove le proprietà temporanee associate ai fogli collegati.
