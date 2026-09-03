/** Prepara il registro dell'evento e il relativo foglio operativo su richiesta firmata di WordPress. */
function preparaProduzioniEventoDaWordPress_(payload) {
  payload = payload || {};
  const idEvento = normalizzaTesto_(payload.id_evento, 40);
  const titolo = normalizzaTesto_(payload.titolo, 200);
  if (!/^\d+$/.test(idEvento) || !titolo) return { ok: false, error: 'EVENTO_NON_VALIDO' };
  const eventi = ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS);
  const esistente = convertiRigheInOggetti_(eventi).find(function (riga) { return String(riga.id_evento) === idEvento; });
  const valori = [
    idEvento,
    normalizzaTesto_(payload.id_gruppo, 40),
    neutralizzaFormula_(titolo, 200),
    normalizzaValoreElenco_(payload.stato, ['BOZZA', 'PUBBLICATO', 'PRIVATO']) || 'BOZZA',
    Math.max(1, Math.round(Number(payload.capienza) || 1)),
    normalizzaTesto_(payload.apertura_iscrizioni, 40),
    normalizzaTesto_(payload.chiusura_iscrizioni, 40),
    normalizzaTesto_(payload.modalita_prezzo, 40),
    new Date()
  ];
  if (esistente) eventi.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
  else eventi.appendRow(valori);
  const risultato = apriFoglioOperativoEvento({ id_evento: idEvento });
	const urlIscrizione = normalizzaUrlPubblico_(payload.url_iscrizione);
	const urlSaldo = normalizzaUrlPubblico_(payload.url_saldo);
	const emailGestore = payload.email_gestore ? normalizzaEmailGestore_(payload.email_gestore) : '';
	const condivisione = { ok: false, email: emailGestore, avviso: emailGestore ? 'Il foglio è privato. La condivisione al gestore va completata separatamente secondo le regole del dominio Workspace.' : 'Nessun gestore indicato: nessuna nuova condivisione. Restano invariati gli accessi Google già autorizzati.' };
	aggiornaCollegamentiProduzioneEvento_(idEvento, urlIscrizione, urlSaldo);
  aggiungiControllo_('PRODUZIONI_EVENTO', 'PREPARE', idEvento, 'SUCCESS', 'WORDPRESS', risultato.creato ? 'SHEET_CREATED' : 'SHEET_REUSED', 'WORDPRESS_PROXY');
  return { ok: true, id_evento: idEvento, id_foglio: risultato.id_foglio, url_foglio: risultato.url_foglio, url_iscrizione: urlIscrizione, url_saldo: urlSaldo, cartella: risultato.cartella || '', creato: risultato.creato, condivisione: condivisione, mode: 'PREVIEW' };
}

/** Mantiene il proprietario Workspace e un solo gestore esplicitamente indicato da WordPress. */
function condividiFoglioSoltantoConGestore_(idFoglio, emailGestore) {
	const file = DriveApp.getFileById(String(idFoglio));
	try {
		file.addEditor(emailGestore);
		return { ok: true, email: emailGestore };
	} catch (errore) {
		aggiungiControllo_('PRODUZIONI_EVENTO', 'SHARE', String(idFoglio), 'WARNING', 'WORDPRESS', normalizzaTesto_(errore && errore.message ? errore.message : errore, 500), 'WORDPRESS_PROXY');
		return { ok: false, email: emailGestore, avviso: 'Il foglio è stato creato, ma Workspace non ha consentito la condivisione automatica con il gestore.' };
	}
}

function normalizzaEmailGestore_(valore) {
	const email = normalizzaTesto_(valore, 254).toLowerCase();
	if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) throw new Error('Indirizzo email del gestore non valido.');
	return email;
}

/** Restituisce il foglio operativo dell'evento, creandolo soltanto se manca. */
function apriFoglioOperativoEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
  const esistente = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (esistente && esistente.id_foglio) {
		try {
			const fileEsistente = DriveApp.getFileById(String(esistente.id_foglio));
			if (!fileEsistente.isTrashed()) {
				SpreadsheetApp.openById(String(esistente.id_foglio));
				return { id_evento: idEvento, id_foglio: String(esistente.id_foglio), url_foglio: String(esistente.url_foglio || ('https://docs.google.com/spreadsheets/d/' + esistente.id_foglio + '/edit')), cartella: '', creato: false };
			}
		} catch (errore) {
			aggiungiControllo_('FOGLIO_OPERATIVO', 'VERIFY', idEvento, 'WARNING', 'WORDPRESS', 'SHEET_MISSING_RECREATE', 'WORDPRESS_PROXY');
		}
  }
  const vista = generaVistaOperativaEvento_(idEvento);
	const titoloPulito = String(vista.evento.titolo || idEvento).replace(/[\\/:*?"<>|#%{}]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
	const titolo = 'Evento ' + idEvento + ' - ' + titoloPulito;
  const foglio = SpreadsheetApp.create(titolo);
	const cartella = spostaFoglioAccantoAlDatabase_(foglio.getId());
  const scheda = foglio.getSheets()[0];
  scheda.setName('Dati operativi');
  scriviFoglioOperativoEvento_(scheda, vista);
  const valori = [idEvento, neutralizzaFormula_(vista.evento.titolo, 200), foglio.getId(), foglio.getUrl(), '', '', new Date()];
	if (esistente) registro.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
	else registro.appendRow(valori);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'CREATE', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'CREATED', 'SEGRETERIA');
  return { id_evento: idEvento, id_foglio: foglio.getId(), url_foglio: foglio.getUrl(), cartella: cartella, creato: true };
}

/** Controlla che il documento registrato esista davvero e sia accessibile. */
function verificaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
	const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
	if (!collegamento || !collegamento.id_foglio) return { ok: true, esiste: false, id_evento: idEvento };
	try {
		const file = DriveApp.getFileById(String(collegamento.id_foglio));
		if (file.isTrashed()) return { ok: true, esiste: false, id_evento: idEvento };
		SpreadsheetApp.openById(String(collegamento.id_foglio));
		return { ok: true, esiste: true, id_evento: idEvento, id_foglio: String(collegamento.id_foglio), url_foglio: String(collegamento.url_foglio || file.getUrl()) };
	} catch (errore) {
		return { ok: true, esiste: false, id_evento: idEvento };
	}
}

/** Verifica in una sola richiesta i documenti di più eventi. */
function verificaFogliEventoDaWordPress_(payload) {
	const ids = (Array.isArray((payload || {}).id_eventi) ? payload.id_eventi : []).slice(0, 100).map(function (id) { return normalizzaTesto_(id, 40); }).filter(function (id) { return /^\d+$/.test(id); });
	const visti = {};
	const stati = [];
	ids.forEach(function (idEvento) {
		if (visti[idEvento]) return;
		visti[idEvento] = true;
		const stato = verificaFoglioEventoDaWordPress_({ id_evento: idEvento });
		stati.push({ id_evento: idEvento, esiste: !!stato.esiste, id_foglio: String(stato.id_foglio || ''), url_foglio: String(stato.url_foglio || '') });
	});
	return { ok: true, stati: stati };
}

/** Sposta in «EVENTI/EVENTI PASSATI» il foglio di un evento passato, senza cancellarlo. */
function archiviaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
	const file = DriveApp.getFileById(String(collegamento.id_foglio));
	if (file.isTrashed()) return { ok: false, error: 'FOGLIO_NON_DISPONIBILE' };
	const archivio = ottieniCartelleEventi_().passati;
	file.moveTo(archivio);
	aggiungiControllo_('FOGLIO_OPERATIVO', 'ARCHIVE', idEvento, 'SUCCESS', 'WORDPRESS', 'MOVED_TO_COMPLETED', 'WORDPRESS_PROXY');
	return { ok: true, id_evento: idEvento, id_foglio: String(collegamento.id_foglio), cartella: 'EVENTI/EVENTI PASSATI' };
}

/** Riallinea in blocco i fogli esistenti alla stessa distinzione mostrata nel portale. */
function organizzaFogliEventoDaWordPress_(payload) {
	payload = payload || {};
	const correnti = normalizzaIdentificativiEvento_(payload.eventi_correnti);
	const passati = normalizzaIdentificativiEvento_(payload.eventi_passati);
	const cartelle = ottieniCartelleEventi_();
	const risultati = [];
	const sposta = function (idEvento, cartella, destinazione) {
		try {
			const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
			const file = DriveApp.getFileById(String(collegamento.id_foglio));
			if (file.isTrashed()) throw new Error('FOGLIO_NON_DISPONIBILE');
			file.moveTo(cartella);
			risultati.push({ id_evento: idEvento, ok: true, cartella: destinazione });
		} catch (errore) {
			risultati.push({ id_evento: idEvento, ok: false, errore: normalizzaTesto_(errore && errore.message ? errore.message : errore, 200) });
		}
	};
	correnti.forEach(function (idEvento) { sposta(idEvento, cartelle.eventi, 'EVENTI'); });
	passati.forEach(function (idEvento) { sposta(idEvento, cartelle.passati, 'EVENTI/EVENTI PASSATI'); });
	return { ok: true, risultati: risultati };
}

function normalizzaIdentificativiEvento_(valori) {
	const visti = {};
	return (Array.isArray(valori) ? valori : []).slice(0, 200).map(function (valore) { return normalizzaTesto_(valore, 40); }).filter(function (valore) {
		if (!/^\d+$/.test(valore) || visti[valore]) return false;
		visti[valore] = true;
		return true;
	});
}

/** Cestina il foglio collegato e rimuove l'associazione di una bozza eliminata. */
function eliminaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
	const collegamento = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
	if (!collegamento) return { ok: true, id_evento: idEvento, eliminato: false };
	if (collegamento.id_foglio) {
		try { DriveApp.getFileById(String(collegamento.id_foglio)).setTrashed(true); } catch (errore) {}
	}
	registro.deleteRow(collegamento._row);
	aggiungiControllo_('FOGLIO_OPERATIVO', 'DELETE', idEvento, 'SUCCESS', 'WORDPRESS', 'MOVED_TO_TRASH', 'WORDPRESS_PROXY');
	return { ok: true, id_evento: idEvento, eliminato: true };
}

/** Crea o riusa la struttura EVENTI accanto a DB_MODULI. */
function ottieniCartelleEventi_() {
	const database = ottieniFoglioDiLavoroAssociato_();
	const cartelle = DriveApp.getFileById(database.getId()).getParents();
	if (!cartelle.hasNext()) throw new Error('DB_MODULI non si trova in una cartella Drive utilizzabile.');
	const principale = cartelle.next();
	const esistenti = principale.getFoldersByName('EVENTI');
	const eventi = esistenti.hasNext() ? esistenti.next() : principale.createFolder('EVENTI');
	const archivi = eventi.getFoldersByName('EVENTI PASSATI');
	const passati = archivi.hasNext() ? archivi.next() : eventi.createFolder('EVENTI PASSATI');
	return { eventi: eventi, passati: passati };
}

/** Sposta il nuovo foglio nella cartella EVENTI accanto a DB_MODULI. */
function spostaFoglioAccantoAlDatabase_(idFoglio) {
	const cartella = ottieniCartelleEventi_().eventi;
	DriveApp.getFileById(String(idFoglio)).moveTo(cartella);
	return 'EVENTI';
}

/** Registra gli indirizzi pubblici prodotti da WordPress senza accettare protocolli diversi da HTTPS. */
function aggiornaCollegamentiProduzioneEvento_(idEvento, urlIscrizione, urlSaldo) {
	const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
	const collegamento = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === String(idEvento); });
	if (!collegamento) throw new Error('Collegamento al foglio operativo non trovato.');
	registro.getRange(collegamento._row, 5, 1, 2).setValues([[urlIscrizione, urlSaldo]]);
}

function normalizzaUrlPubblico_(valore) {
	const url = normalizzaTesto_(valore, 1000);
	if (!url) return '';
	if (!/^https:\/\/[^\s]+$/i.test(url)) throw new Error('Indirizzo pubblico non valido.');
	return neutralizzaFormula_(url, 1000);
}

/** Riallinea dal database soltanto dopo una conferma esplicita nell'interfaccia. */
function aggiornaFoglioOperativoEvento(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
  const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const vista = generaVistaOperativaEvento_(idEvento);
  scriviFoglioOperativoEvento_(scheda, vista);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'REFRESH', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'DATABASE_TO_EVENT_SHEET', 'SEGRETERIA');
  return { ok: true, url_foglio: foglio.getUrl(), righe: vista.righe.length, message: 'Foglio operativo riallineato con ' + vista.righe.length + ' partecipanti.' };
}

/** Confronta il foglio evento con DB_MODULI senza scrivere alcun dato. */
function preparaSincronizzazioneFoglioOperativo(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  const collegamento = trovaCollegamentoFoglioOperativo_(idEvento);
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const metadati = scheda.getDeveloperMetadata().reduce(function (indice, elemento) { indice[elemento.getKey()] = elemento.getValue(); return indice; }, {});
  let campi = [];
  try { campi = JSON.parse(String(metadati.MI_CAMPI || '[]')); } catch (errore) { campi = []; }
  if (!Array.isArray(campi) || !campi.length) throw new Error('Riallinea prima il foglio operativo per abilitarne la sincronizzazione controllata.');
  const vista = generaVistaOperativaEvento_(idEvento, campi);
  const centrali = vista.righe.reduce(function (indice, riga) { indice[riga.codice_ordine + '|' + riga.numero_partecipante] = riga; return indice; }, {});
  const valori = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, 2 + campi.length).getDisplayValues() : [];
  const vietati = ['event', 'order_code', 'participant_number', 'first_name', 'last_name', 'status', 'options', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'];
  const modifiche = [];
  const problemi = [];
  const viste = {};
  vista.colonne.forEach(function (colonna) { viste[colonna.key] = colonna.label; });
  valori.forEach(function (riga, indiceRiga) {
    const codice = normalizzaTesto_(riga[0], 64);
    const numero = Math.max(0, Math.round(Number(riga[1]) || 0));
    const centrale = centrali[codice + '|' + numero];
    if (!codice || !numero || !centrale) { problemi.push('Riga ' + (indiceRiga + 2) + ': collegamento tecnico non valido.'); return; }
    campi.forEach(function (campo, indiceCampo) {
      const nuovo = normalizzaTesto_(riga[indiceCampo + 2], 1000);
      const precedente = normalizzaTesto_(centrale.valori[campo], 1000);
      if (nuovo === precedente) return;
      if (vietati.indexOf(campo) >= 0) { problemi.push('Riga ' + (indiceRiga + 2) + ': “' + (viste[campo] || campo) + '” è calcolato o protetto.'); return; }
      if (campo === 'room' && !nuovo) { problemi.push('Riga ' + (indiceRiga + 2) + ': la sistemazione non può essere cancellata dal foglio.'); return; }
      modifiche.push({ codice_ordine: codice, numero_partecipante: numero, campo: campo, etichetta: viste[campo] || campo, precedente: precedente, nuovo: nuovo });
    });
  });
  if (modifiche.length > 200) throw new Error('Sono state rilevate più di 200 modifiche: suddividere il lavoro in blocchi più piccoli.');
  const firma = creaFirmaSincronizzazioneFoglio_(idEvento, modifiche);
  return { id_evento: idEvento, modifiche: modifiche, problemi: problemi.slice(0, 50), firma: firma, applicabile: modifiche.length > 0 && problemi.length === 0 };
}

/** Applica soltanto una differenza appena ricalcolata e confermata dall'operatore. */
function confermaSincronizzazioneFoglioOperativo(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  const firma = normalizzaTesto_(form.firma, 128);
  const motivo = normalizzaTesto_(form.motivo, 500);
  if (!idEvento || !firma || !motivo) throw new Error('Evento, firma e motivo della sincronizzazione sono obbligatori.');
  const anteprima = preparaSincronizzazioneFoglioOperativo({ id_evento: idEvento });
  if (!anteprima.applicabile || anteprima.firma !== firma) throw new Error('Il foglio è cambiato dopo l’anteprima: controllare nuovamente le differenze.');
  const operatore = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  anteprima.modifiche.forEach(function (modifica) {
    if (modifica.campo === 'room') {
      cambiaSistemazioneSegreteria({ order_code: modifica.codice_ordine, participant_number: modifica.numero_partecipante, room_code: modifica.nuovo, reason: motivo });
      return;
    }
    registraOperazioneSegreteria_(modifica.codice_ordine, modifica.numero_partecipante, 'SYNC_EVENT_SHEET', { key: modifica.campo, value: modifica.nuovo, previous: modifica.precedente }, motivo, operatore, 'Modifica confermata dal foglio operativo dell’evento.');
  });
  aggiungiControllo_('FOGLIO_OPERATIVO', 'SYNC', idEvento, 'SUCCESS', operatore, String(anteprima.modifiche.length), 'SEGRETERIA');
  return { ok: true, count: anteprima.modifiche.length, message: 'Sincronizzate ' + anteprima.modifiche.length + ' modifiche con storico.' };
}

function trovaCollegamentoFoglioOperativo_(idEvento) {
  const collegamento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  return collegamento;
}

function creaFirmaSincronizzazioneFoglio_(idEvento, modifiche) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, idEvento + '|' + JSON.stringify(modifiche), Utilities.Charset.UTF_8).map(function (valore) { return ('0' + (valore & 255).toString(16)).slice(-2); }).join('');
}

function scriviFoglioOperativoEvento_(scheda, vista) {
  rimuoviRaggruppamentiColonne_(scheda);
  scheda.clear();
  const intestazioni = ['Codice prenotazione', 'Numero partecipante'].concat(vista.colonne.map(function (colonna) { return colonna.label; }));
  const righe = vista.righe.map(function (riga) {
    return [neutralizzaFormula_(riga.codice_ordine, 64), Number(riga.numero_partecipante) || 0].concat(vista.colonne.map(function (colonna) { return neutralizzaFormula_(riga.valori[colonna.key], 5000); }));
  });
  scheda.getRange(1, 1, 1, intestazioni.length).setValues([intestazioni]).setFontWeight('bold').setBackground('#172554').setFontColor('#ffffff');
  if (righe.length) scheda.getRange(2, 1, righe.length, intestazioni.length).setValues(righe);
  scheda.setFrozenRows(1);
  scheda.hideColumns(1, 2);
  scheda.autoResizeColumns(3, Math.max(1, intestazioni.length - 2));
  raggruppaColonneFoglioOperativo_(scheda, vista.colonne);
  impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', vista.evento.id);
  impostaMetadatoVista_(scheda, 'MI_CAMPI', JSON.stringify(vista.colonne.map(function (colonna) { return colonna.key; })));
  impostaMetadatoVista_(scheda, 'MI_DATA_AGGIORNAMENTO', new Date().toISOString());
  scheda.getRange('A1').setNote('Le colonne tecniche nascoste collegano ogni riga a DB_MODULI. Usare le procedure di sincronizzazione della Segreteria per rendere definitive le modifiche.');
}

function rimuoviRaggruppamentiColonne_(scheda) {
  for (let colonna = 1; colonna <= scheda.getMaxColumns(); colonna += 1) {
    let profondita = scheda.getColumnGroupDepth(colonna);
    while (profondita > 0) {
      const raggruppamento = scheda.getColumnGroup(colonna, profondita);
      if (!raggruppamento) break;
      raggruppamento.remove();
      profondita = scheda.getColumnGroupDepth(colonna);
    }
  }
}

function raggruppaColonneFoglioOperativo_(scheda, colonne) {
  let inizio = -1;
  let gruppo = '';
  const chiudi = function (fine) {
    if (inizio < 0 || fine < inizio) return;
    scheda.getRange(1, inizio + 3, Math.max(1, scheda.getMaxRows()), fine - inizio + 1).shiftColumnGroupDepth(1);
  };
  colonne.forEach(function (colonna, indice) {
    const corrente = colonna.gruppo === 'persona' ? '' : colonna.gruppo;
    if (corrente === gruppo) return;
    chiudi(indice - 1);
    gruppo = corrente;
    inizio = corrente ? indice : -1;
  });
  chiudi(colonne.length - 1);
}
