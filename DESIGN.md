---
version: alpha
name: "Modulo iscrizioni — Segreteria eventi"
description: "Interfaccia operativa sobria, coerente con il sito parrocchiale e orientata a compiti chiari."
colors:
  inchiostro: "#172033"
  blu-notte: "#17224a"
  sfondo: "#f5f7fa"
  superficie: "#ffffff"
  bordo: "#d7dde6"
  testo-secondario: "#657084"
  successo: "#25745e"
  successo-chiaro: "#eaf5ef"
  fuoco: "#244fc2"
typography:
  testo:
    fontFamily: "system-ui, -apple-system, 'Segoe UI', sans-serif"
    fontSize: "16px"
    lineHeight: "1.5"
rounded:
  DEFAULT: "10px"
  scheda: "14px"
spacing:
  controllo: "44px"
  sezione: "24px"
components:
  pulsante:
    minHeight: "44px"
  scheda:
    borderRadius: "14px"
  campo:
    minHeight: "44px"
---

## Overview

La Segreteria eventi è uno strumento di lavoro, non una pagina promozionale. Deve ricordare una segreteria ordinata: titoli netti, istruzioni brevi, azioni riconoscibili e identità del gruppo visibile senza sovrastare il contenuto. Il riferimento pubblico resta il sito della Parrocchia Sant’Eugenio; il portale mantiene però una densità maggiore, adatta al lavoro quotidiano.

## Colors

Il blu notte identifica navigazione e azioni principali. Il verde indica soltanto esiti positivi realmente confermati. Bianco e grigio chiaro separano le aree operative; il rosso resta riservato alle azioni irreversibili o agli errori.

## Typography

Si usa il carattere di sistema già adottato dal portale, con testi e comandi rigorosamente in italiano. I titoli possono essere ampi, ma le istruzioni devono restare brevi e prive di slogan ridondanti.

## Layout

Il contenuto segue lo scorrimento naturale della pagina. Le azioni correlate sono raccolte in schede; su schermi stretti si impilano senza perdere etichette o comandi. Campi e pulsanti hanno almeno 44 px di altezza. I collegamenti completi restano selezionabili.

## Elevation & Depth

Nel modulo Sheets «Registra movimento» si usa Arial: 12 pt come base, 14 pt nei campi, 20 pt per l’importo e 24 pt per il titolo. I controlli principali hanno altezza 44 px. La pagina occupa 920 px comprese le due colonne di margine; le colonne successive e le righe dopo la 30 sono nascoste, senza cancellarle. Si bloccano soltanto le prime due righe per lasciare spazio alla compilazione durante lo scorrimento. I campi editabili hanno fondo crema e bordo visibile; il riepilogo ha fondo grigio, con residuo ambra e importi allineati a destra. I colori dell’esito esistente sono conservati durante l’aggiornamento. Nel modulo evento si salva con la tendina «REGISTRA MOVIMENTO»; nel centrale con la spunta e il comando di menu «Registra movimento guidato».

Le superfici usano bordi e ombre molto leggere. Non si usano gradienti, vetro, animazioni decorative o effetti che rallentino la lettura.

## Shapes

Controlli con raggio di 10 px e schede con raggio di 14 px. Le forme circolari sono riservate a loghi, iniziali e indicatori di stato.

## Components

Il passaggio conclusivo mostra nell’intestazione il gruppo organizzatore, il suo logo quando disponibile e il nome reale dell’evento. Il modulo pubblico e il foglio interno sono sempre separati per destinatario. La copia usa un solo comportamento condiviso, con esito accessibile e recupero manuale in caso di errore. Il link pubblico dell’evento offre anche la condivisione nativa del dispositivo, con ripiego sulla copia, senza librerie esterne. Le istruzioni per WordPress e Divi restano facoltative e chiuse inizialmente.

Nella scheda Iscrizioni i filtri seguono il ragionamento operativo da sinistra a destra: prima il periodo (eventi in corso o passati), poi un’unica tendina che comprende “Tutti gli eventi” e i singoli eventi, quindi lo stato della prenotazione. Non deve comparire un selettore intermedio che faccia apparire a sorpresa un altro campo.

In Gestisci eventi la scheda di dettaglio si apre nella griglia, subito dopo la riga della tessera selezionata. L’elenco rimane visibile sopra e sotto come contesto; la tessera attiva riceve un’evidenziazione sobria e il movimento di apertura rispetta la preferenza di riduzione delle animazioni.

Il foglio interno «Registra movimento» segue quattro passaggi visibili e numerati: Prenotazione, Movimento, Tracciabilità, Verifica e registra. Il codice ordine viene scelto dall’elenco delle iscrizioni; stato, evento, referente, totale, versato e residuo vengono riletti dal registro prima della conferma. Le tendine native di Google Sheets sono il controllo canonico per tipo, rata e metodo. Il blu notte identifica la struttura, il verde compare soltanto dopo una registrazione convalidata e l’operatore deve spuntare una conferma esplicita per evitare inserimenti accidentali. La chiave idempotente resta nascosta e viene rinnovata soltanto dopo il successo.

La lista d’attesa separa tre momenti: richiesta registrata, proposta temporanea e prenotazione accettata. La prima email precisa che non occorre pagare o agire; quando si libera posto, il sistema lo riserva per 48 ore salvo diversa configurazione dell’evento e invia un collegamento personale. La pagina collegata offre due azioni esplicite, «Accetta il posto» e «Rinuncia». Solo dopo l’accettazione vengono comunicate conferma e, se applicabili, istruzioni e scadenza di pagamento.

La schermata Operatori rende visibile l’ambito prima del salvataggio. Il Gestore iscrizioni non mostra selettori perché opera sull’intero servizio; il Gestore gruppo mostra i gruppi assegnabili; il Gestore evento mostra soltanto gli eventi in corso assegnabili. Ruolo e ambito sono sempre riepilogati nella tessera dell’utente.

### Email del sistema

Tutte le email — conferma, promemoria saldo, annullamento, informazioni operative e prove — usano lo stesso involucro compatibile con i principali programmi di posta: fondo `#f6f8fc`, tessera bianca larga al massimo 600 px, testata nel colore principale del gruppo, azione primaria nel colore secondario, riquadro finale per l’assistenza e collegamento testuale di riserva. Logo, colori, nome del gruppo e contatto per le risposte sono ereditati da gruppo ed evento; il contenuto centrale cambia secondo il messaggio. Tabelle e stili in linea sono intenzionali per la compatibilità email. Il testo semplice conserva lo stesso ordine informativo dell’HTML.

## Do's and Don'ts

- Mostrare identità del gruppo, nome evento e stato reale.
- Usare verbi diretti: Apri, Copia, Pubblica.
- Distinguere chiaramente ciò che si condivide dai collegamenti riservati agli operatori.
- Non ripetere la stessa istruzione in più punti.
- Non mostrare identificativi tecnici quando è disponibile il nome dell’evento.
- Non applicare gli stili del plugin al resto del sito o a Divi.

Nelle tessere degli eventi pubblicati con iscrizioni future, «Attivo» conserva il grassetto attuale; sotto compare «dal 12/10/2026», con data numerica giorno/mese/anno senza orario, nel corpo piccolo esistente e in grassetto. La data usa il fuso del sito.

Nella seconda schermata pubblica, i servizi con massimo una unità per partecipante (pullman, pranzo, rimborso spese) usano caselle di spunta nella scheda di ciascun iscritto. Gli alloggi conservano la scelta esclusiva; le opzioni configurate con quantità multiple conservano il controllo numerico.
