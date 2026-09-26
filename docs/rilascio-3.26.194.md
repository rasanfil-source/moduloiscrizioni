# 3.26.194 — solo nome nella conferma

Nuovo segnaposto facoltativo `{{sottoscrittore.nome}}`, alimentato dal campo nome del sottoscrittore senza suddividere il nome completo. Mantiene quindi i nomi composti. Disponibile anche nelle anteprime e nella ricostruzione delle conferme. Il segnaposto `{{sottoscrittore.nome_completo}}` e i modelli già salvati restano invariati.

Dopo l'installazione, modificare il saluto del modello in `Ciao {{sottoscrittore.nome}},`. Aggiornamento solo WordPress, nessuna modifica Apps Script. Test locale su nome composto, compatibilità nome completo e resa HTML/testo.
