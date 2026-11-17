# Modulo Iscrizioni 3.27.73

17 novembre 2026. Aggiunta l’opzione facoltativa «Coda rapida negli ultimi giorni» nella creazione/modifica evento e nell’editor WordPress. Inizialmente disattivata, viene mantenuta nella duplicazione dell’evento.

Le nuove proposte della lista d’attesa durano metà del tempo configurato a partire da sette giorni esatti prima dell’inizio evento e un quarto negli ultimi tre giorni. Esempio: 48 → 24 → 12 ore. Sono supportate anche frazioni di ora. Le proposte già emesse conservano la scadenza comunicata. La scadenza comune continua a prevalere quando precedente.

Le email delle proposte rapide, anche per un solo posto su due richiesti, mostrano in rosso «Hai solo NN Ore per poter accettare il posto.» Il numero tiene conto della scadenza comune; il testo semplice contiene lo stesso avviso. Rimane indicata anche la data esatta di scadenza.

Nuovi ZIP locale e pubblico con checksum SHA-256. Installazione WordPress a cura del gestore; MODULI AUTONOMO invariato, nessun deployment Google necessario. Conservati gli ultimi cinque rilasci 3.27.69–3.27.73.

Verifiche superate: suite Node/PHP completa, test dedicato delle soglie e dell’avviso HTML/testo, asset e sanitizzazione, procedura guidata nel browser Firefox con dati sintetici su desktop e mobile. ZIP confrontati file per file con i sorgenti; archivi precedenti verificati invariati prima della conservazione degli ultimi cinque rilasci. Nessun invio reale.
