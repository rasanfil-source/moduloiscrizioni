# Rilascio 3.26.157 — colonna Stato condizionale

Include tutte le modifiche della [3.26.156](rilascio-3.26.156.md) e semplifica l’Elenco partecipanti.

## Regola

**Nell’Elenco partecipanti, la colonna “Stato” appare se e solo se almeno una riga attualmente visualizzata ha un’etichetta diversa da “Partecipante”.**

Di conseguenza, negli eventi gratuiti senza lista d’attesa la colonna normalmente non compare. Riappare automaticamente in presenza, fra gli altri casi, di pagamento o caparra attesi, saldo atteso, lista d’attesa, posto proposto, annullamento o scadenza. La regola dipende dall’etichetta effettiva e copre quindi anche qualifiche future.

Quando è attiva la registrazione delle presenze, la colonna “Presente” continua a occupare la posizione operativa prevista. “Stato” viene aggiunta in fondo soltanto se richiesta dalla regola precedente.

## Installazione

Caricare `modulo-iscrizioni-3.26.157.zip` in WordPress e confermare la sostituzione. Lo ZIP locale conserva la configurazione privata e non va pubblicato; GitHub distribuisce soltanto `modulo-iscrizioni-3.26.157-pubblico.zip`.

GAS non cambia: il progetto storico **MODULI AUTONOMO 3.26.149** resta al deployment **10**, sorgente 3.26.156.

## Verifica

Una prova browser dedicata verifica l’assenza della colonna quando tutte le righe riportano “Partecipante” e la sua ricomparsa con “Lista d’attesa” e “Pagamento atteso”. La prova delle presenze verifica inoltre la coesistenza e l’ordine delle due colonne.
