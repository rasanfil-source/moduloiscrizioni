# Rilascio 3.26.143

Il periodo del rapporto presenze si configura nella scheda del gruppo, tramite mese iniziale e finale inclusivo, anche a cavallo di due anni. La configurazione viene salvata sul gruppo e usata per tutti i suoi eventi. Per i gruppi ancora senza periodo salvato viene proposto l'anno corrente.

Il rapporto e l'esportazione Excel si aprono da **Gruppi → scheda del gruppo → Rapporto presenze del gruppo**. Il selettore del periodo e il rapporto aggregato sono rimossi da Gestione iscrizioni; la rilevazione delle presenze individuali resta nell'evento.

Il server verifica l'abilitazione del rapporto, il permesso sul gruppo e sui singoli eventi. Le date eventualmente inviate dal browser non sostituiscono quelle salvate. Il periodo si configura anche nel pannello amministrativo WordPress del gruppo.

Verifiche: suite Node WordPress/Workspace, test PHP del rapporto e dell'accesso per gruppo, prova browser di generazione ed esportazione Excel senza selezionare un evento, compilazione asset e controllo ZIP.

Installare `modulo-iscrizioni-3.26.143.zip` su WordPress. Workspace non cambia: resta il rilascio 3.26.142, distribuzione Apps Script 86.
