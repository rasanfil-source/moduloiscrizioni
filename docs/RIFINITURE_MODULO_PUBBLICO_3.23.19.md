# Rifiniture modulo pubblico 3.23.19

La pagina concentrata di iscrizione non carica più gli asset CSS e JavaScript di Divi. La pulizia è limitata alle richieste con `mi_iscrizione`; il resto del sito mantiene il tema invariato. Il modulo conserva i propri asset e il riquadro disponibilità usa una regola più specifica per mantenere lo stesso spazio sopra e sotto anche in presenza di stili globali.

Nel secondo passaggio il suggerimento organizzativo non compare più sotto la data di nascita. Quando manca un dato obbligatorio, il primo campo non valido riceve il focus e viene portato al centro della schermata.

La pagina Privacy configurata in WordPress resta la prima scelta. Se non è stata associata nelle impostazioni, il plugin usa la pagina pubblicata `/privacy-policy/`. Creazione e salvataggio dell’evento compilano automaticamente la versione mensile e gli identificativi `privacy-{ID evento}` e `marketing-{ID evento}`. Anche le revisioni pubblicate storiche prive di questi valori vengono riallineate al primo accesso.

Nella gestione eventi il menu a tre puntini mostra soltanto l’azione. La conferma di annullamento si apre successivamente in una finestra ampia, con area del motivo leggibile, comando Indietro e conferma esplicita prima dell’azione definitiva.
