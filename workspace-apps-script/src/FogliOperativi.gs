/** Prepara il registro dell'evento e il relativo foglio operativo su richiesta firmata di WordPress. */
/** Accesso anonimo solo in lettura al singolo foglio evento, mai al database centrale.
 * Il link espone tutte le schede del documento: non concede scritture anonime.
 * Non memorizziamo un esito in cache: un errore di dominio deve essere visibile e
 * una successiva preparazione/verifica deve poter ripristinare la condivisione.
 */
function abilitaLetturaFoglioEventoConLink_(idFoglio) {
  const file = DriveApp.getFileById(String(idFoglio));
  if (file.getSharingAccess() !== DriveApp.Access.ANYONE_WITH_LINK || file.getSharingPermission() !== DriveApp.Permission.VIEW) {
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
  }
}

function trovaCollegamentoFoglioOperativo_(idEvento) {
  const id=String(idEvento);
  const rows=convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES)).filter(row=>String(row.id_evento)===id);
  if (rows.length!==1 || !rows[0].id_foglio) throw new Error('Collegamento univoco al foglio operativo non disponibile.');
  return rows[0];
}
function preparaProduzioniEventoDaWordPress_(payload) {
  const lock=LockService.getScriptLock();lock.waitLock(30000);
  try {
    if (eventoInEliminazione_(String((payload||{}).id_evento||''))) return {ok:false,error:'EVENT_DELETED'};
    return preparaProduzioniEventoConLock_(payload);
  } finally {lock.releaseLock();}
}
function preparaProduzioniEventoConLock_(payload) {
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
    payload.evento_gratuito === true ? 'ZERO' : normalizzaTesto_(payload.modalita_prezzo, 40),
    new Date(),
    JSON.stringify(Array.isArray(payload.servizi) ? payload.servizi : []),
    JSON.stringify(Array.isArray(payload.domande_partecipanti) ? payload.domande_partecipanti : decodificaElenco_((esistente || {}).domande_json)),
    normalizzaValoreElenco_(payload.profilo_operativo, ['MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO']) || (esistente || {}).profilo_operativo || ''
  ];
  if (eventi.getMaxColumns() < 12) eventi.insertColumnsAfter(eventi.getMaxColumns(), 12 - eventi.getMaxColumns());
  eventi.getRange(1, 12).setValue('profilo_operativo');
  eventi.getRange(1, 11).setValue('domande_json');
  if (esistente) eventi.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
  else eventi.appendRow(valori);
  if (payload.event_schema !== undefined) aggiornaSchemaEventoMysql_(idEvento, payload.event_schema);
	const profiloOperativo = normalizzaValoreElenco_(payload.profilo_operativo, ['AUTOMATICO', 'MINIMO', 'QUOTA_UNICA', 'SERVIZI_MULTIPLI', 'VIAGGIO_COMPLESSO']) || 'AUTOMATICO';
  const risultato = apriFoglioOperativoConLock_({ id_evento: idEvento, titolo: titolo, profilo_operativo: profiloOperativo });
	const urlIscrizione = normalizzaUrlPubblico_(payload.url_iscrizione);
	const urlSaldo = normalizzaUrlPubblico_(payload.url_saldo);
	const emailGestore = payload.email_gestore ? normalizzaEmailGestore_(payload.email_gestore) : '';
	const condivisione = { ok: true, email: emailGestore, avviso: 'Il foglio è consultabile in sola lettura da chiunque abbia il link.' };
	aggiornaCollegamentiProduzioneEvento_(idEvento, urlIscrizione, urlSaldo);
  aggiungiControllo_('PRODUZIONI_EVENTO', 'PREPARE', idEvento, 'SUCCESS', 'WORDPRESS', risultato.creato ? 'SHEET_CREATED' : 'SHEET_REUSED', 'WORDPRESS_PROXY');
  return { ok: true, id_evento: idEvento, id_foglio: risultato.id_foglio, url_foglio: risultato.url_foglio, url_iscrizione: urlIscrizione, url_saldo: urlSaldo, cartella: risultato.cartella || '', creato: risultato.creato, condivisione: condivisione, mode: 'PREVIEW' };
}

/** Mantiene il proprietario Workspace e un solo gestore esplicitamente indicato da WordPress. */
function condividiFoglioSoltantoConGestore_(idFoglio, emailGestore) {
	const file = DriveApp.getFileById(String(idFoglio));
	try {
		file.addViewer(emailGestore);
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
  const lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try { return apriFoglioOperativoConLock_(form); }
  finally { lock.releaseLock(); }
}

function apriFoglioOperativoConLock_(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  if (typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(idEvento)) throw new Error('Evento eliminato o in eliminazione.');
  const registro = ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES);
  const esistente = convertiRigheInOggetti_(registro).find(function (riga) { return String(riga.id_evento) === idEvento; });
  const proprieta = PropertiesService.getScriptProperties();
  const chiavePreparazione = 'MI_SHEET_PREPARING_' + idEvento;
  let foglioDaCompletare = null;
  if (esistente && esistente.id_foglio) {
		try {
			const fileEsistente = DriveApp.getFileById(String(esistente.id_foglio));
			if (!fileEsistente.isTrashed()) {
				abilitaLetturaFoglioEventoConLink_(esistente.id_foglio);
				const aperto = SpreadsheetApp.openById(String(esistente.id_foglio));
				if (proprieta.getProperty(chiavePreparazione) === String(esistente.id_foglio)) foglioDaCompletare = aperto;
				else return { id_evento: idEvento, id_foglio: String(esistente.id_foglio), url_foglio: String(esistente.url_foglio || ('https://docs.google.com/spreadsheets/d/' + esistente.id_foglio + '/edit')), cartella: '', creato: false };
			}
		} catch (errore) {
			throw new Error('Il foglio registrato non è al momento accessibile. Riprova senza creare un duplicato.');
		}
  }
	// Un evento appena creato non possiede ancora iscrizioni: evitiamo di rileggere
	// l'intero database e prepariamo subito la struttura scelta in WordPress.
	const vista = esistente ? generaVistaOperativaEvento_(idEvento) : generaVistaOperativaIniziale_(idEvento, normalizzaTesto_(form.titolo, 200), normalizzaTesto_(form.profilo_operativo, 30));
	const titoloPulito = String(vista.evento.titolo || idEvento).replace(/[\\/:*?"<>|#%{}]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
	const titolo = 'Evento ' + idEvento + ' - ' + titoloPulito;
  const foglio = foglioDaCompletare || SpreadsheetApp.create(titolo);
  // Persist the identity before formatting: a retry resumes the same document.
  proprieta.setProperty(chiavePreparazione, String(foglio.getId()));
  const valori = [idEvento, neutralizzaFormula_(vista.evento.titolo, 200), foglio.getId(), foglio.getUrl(), '', '', new Date()];
	if (esistente) registro.getRange(esistente._row, 1, 1, valori.length).setValues([valori]);
	else registro.appendRow(valori);
  SpreadsheetApp.flush();
	const cartella = spostaFoglioAccantoAlDatabase_(foglio.getId());
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  scheda.setName('Dati operativi');
  scriviProiezioneEvento_(scheda, vista);
  configuraSchedeEconomicheEvento_(foglio, idEvento, vista.sola_lettura);
  SpreadsheetApp.flush();
  abilitaLetturaFoglioEventoConLink_(foglio.getId());
  proprieta.deleteProperty(chiavePreparazione);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'CREATE', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'CREATED', 'SEGRETERIA');
  return { id_evento: idEvento, id_foglio: foglio.getId(), url_foglio: foglio.getUrl(), cartella: cartella, creato: true };
}

function generaVistaOperativaIniziale_(idEvento, titolo, profiloRichiesto) {
	const profili = {
		MINIMO: ['last_name', 'first_name', 'phone'],
		QUOTA_UNICA: ['last_name', 'first_name', 'phone', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'],
		SERVIZI_MULTIPLI: ['last_name', 'first_name', 'phone', 'transport', 'lunch', 'options', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance'],
		VIAGGIO_COMPLESSO: ['last_name', 'first_name', 'phone', 'birth_date', 'document_type', 'document_number', 'document_issue_date', 'document_expiry_date', 'nationality', 'transport', 'room', 'lunch', 'insurance', 'total', 'paid', 'paid_cash', 'paid_transfer', 'paid_card', 'balance']
	};
	const profilo = profili[profiloRichiesto] ? profiloRichiesto : 'MINIMO';
	const catalogo = campiElencoOperativo_(false).reduce(function (indice, campo) { indice[campo.key] = campo; return indice; }, {});
	const colonne = profili[profilo].filter(function (chiave) { return !!catalogo[chiave]; }).map(function (chiave) {
		return { key: chiave, label: catalogo[chiave].label, gruppo: gruppoCampoVistaOperativa_(chiave), comprimibile: ['paid_cash', 'paid_transfer', 'paid_card'].indexOf(chiave) >= 0 };
	});
	const evento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENTS)).find(r => String(r.id_evento) === String(idEvento)) || {};
	aggiungiColonneDomande_(colonne, evento, [], []);
	aggiungiColonneServizi_(colonne, decodificaElenco_(evento.servizi_json));
	applicaSchemaColonneEvento_(colonne, evento, [], [], []);
	anteponiColonnaProgressiva_(colonne);
	return { evento: { id: idEvento, titolo: titolo || idEvento }, sola_lettura: vistaEventoSolaLettura_(evento, colonne), profilo: profilo, nome_profilo: profilo, personalizzata: false, conservata: false, colonne: colonne, righe: [] };
}

/** Controlla che il documento registrato esista davvero e sia accessibile. */
function verificaFoglioEventoDaWordPress_(payload) {
	const idEvento = normalizzaTesto_((payload || {}).id_evento, 40);
	if ((payload||{}).direct_projection !== true) return {ok:false,error:'USE_DIRECT_PROJECTION'};
	if (!/^\d+$/.test(idEvento)) return { ok: false, error: 'EVENTO_NON_VALIDO' };
	try {
		const opened=apriFoglioEventoFirmato_({event_id:idEvento,sheet_id:String((payload||{}).id_foglio||'')},false);
		abilitaLetturaFoglioEventoConLink_(opened.book.getId());
		return {ok:true,esiste:true,id_evento:idEvento,id_foglio:opened.book.getId(),url_foglio:opened.book.getUrl()};
	} catch(error) {
		const detail=String(error&&error.message?error.message:error);
		if (/EVENT_SHEET_MISSING|file not found|does not exist/i.test(detail)) return {ok:true,esiste:false,id_evento:idEvento};
		return {ok:false,error:'SHEET_UNAVAILABLE',id_evento:idEvento};
	}
}

/** Verifica in una sola richiesta i documenti di più eventi. */
function verificaFogliEventoDaWordPress_(payload) {
	if ((payload||{}).direct_projection !== true || !Array.isArray((payload||{}).fogli)) return {ok:false,error:'USE_DIRECT_PROJECTION'};
	const stati=[];
	payload.fogli.slice(0,100).forEach(item=>{const id=normalizzaTesto_((item||{}).id_evento,40);if(!/^\d+$/.test(id))return;const stato=verificaFoglioEventoDaWordPress_({id_evento:id,id_foglio:String((item||{}).id_foglio||''),direct_projection:true});if(stato.ok)stati.push({id_evento:id,esiste:!!stato.esiste,id_foglio:String(stato.id_foglio||''),url_foglio:String(stato.url_foglio||'')});});
	return {ok:true,stati:stati};
}

/** Riallinea in blocco i fogli esistenti alla stessa distinzione mostrata nel portale. */
function organizzaFogliEventoDaWordPress_(payload) {
	payload = payload || {};
	if (payload.direct_projection !== true || (!Array.isArray(payload.fogli_correnti) && !Array.isArray(payload.fogli_passati))) return {ok:false,error:'USE_DIRECT_PROJECTION'};
	const cartelle=ottieniCartelleEventi_(), risultati=[];
	const move=(items,folder,destination)=>(Array.isArray(items)?items:[]).slice(0,200).forEach(item=>{const id=normalizzaTesto_((item||{}).id_evento,40),sheetId=String((item||{}).id_foglio||'');try{if(!/^\d+$/.test(id)||!seriaIdFoglioDiretto_(sheetId))throw new Error('FOGLIO_NON_DISPONIBILE');const opened=apriFoglioEventoFirmato_({event_id:id,sheet_id:sheetId},false);DriveApp.getFileById(opened.book.getId()).moveTo(folder);risultati.push({id_evento:id,ok:true,cartella:destination});}catch(error){risultati.push({id_evento:id,ok:false,errore:normalizzaTesto_(error&&error.message?error.message:error,200)});}});
	move(payload.fogli_correnti,cartelle.eventi,'EVENTI');
	move(payload.fogli_passati,cartelle.passati,'EVENTI/EVENTI PASSATI');
	return {ok:true,risultati:risultati};
}

function seriaIdFoglioDiretto_(value) { return /^[A-Za-z0-9_-]{20,}$/.test(String(value||'')); }

/** Crea o riusa la struttura EVENTI nella radice di Google Drive. */
function ottieniCartelleEventi_() {
	const principale = DriveApp.getRootFolder();
	const esistenti = principale.getFoldersByName('EVENTI');
	const eventi = esistenti.hasNext() ? esistenti.next() : principale.createFolder('EVENTI');
	const archivi = eventi.getFoldersByName('EVENTI PASSATI');
	const passati = archivi.hasNext() ? archivi.next() : eventi.createFolder('EVENTI PASSATI');
	return { eventi: eventi, passati: passati };
}

/** Sposta il nuovo foglio nella cartella EVENTI della radice Drive. */
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

/** Riallinea dal database; le richieste interattive precedono i giri automatici. */
function aggiornaFoglioOperativoEvento(form) {
  const lock = LockService.getScriptLock();
  if (form && form.background) {
    if (Number(PropertiesService.getScriptProperties().getProperty('MI_INTERACTIVE_OPEN_UNTIL')) > Date.now() || !lock.tryLock(100)) return {ok:false,busy:true};
  } else lock.waitLock(30000);
  try { return aggiornaFoglioOperativoEventoConLock_(form); }
  finally { lock.releaseLock(); }
}

function aggiornaFoglioOperativoEventoConLock_(form) {
  form = form || {};
  const idEvento = normalizzaTesto_(form.id_evento, 40);
  if (!idEvento) throw new Error('Scegli un evento.');
  if (typeof eventoInEliminazione_ === 'function' && eventoInEliminazione_(idEvento)) throw new Error('Evento eliminato o in eliminazione.');
  const registro = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.EVENT_WORKSPACES));
  const collegamento = registro.find(function (riga) { return String(riga.id_evento) === idEvento; });
  if (!collegamento || !collegamento.id_foglio) throw new Error('Crea prima il foglio operativo dell’evento.');
  const foglio = SpreadsheetApp.openById(String(collegamento.id_foglio));
  abilitaLetturaFoglioEventoConLink_(collegamento.id_foglio);
  const scheda = foglio.getSheetByName('Dati operativi') || foglio.getSheets()[0];
  const vista = generaVistaOperativaEvento_(idEvento);
  const proprieta = PropertiesService.getScriptProperties();
  const chiaveProiezione = 'MI_EVENT_VIEW_' + String(collegamento.id_foglio);
  const ordiniEvento = new Set(convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(r=>String(r.id_evento)===idEvento).map(r=>String(r.codice_ordine)));
  const movimentiEvento = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PAYMENTS)).filter(r=>ordiniEvento.has(String(r.codice_ordine))).map(r=>{const copia=Object.assign({},r);delete copia._row;return copia;});
  const impronta = versioneGestione_({versioneProiezione:2,vista:vista,movimenti:movimentiEvento});
  const pending = modificheCorrentiFoglio_(scheda);
  if (form.soloModificati === true && proprieta.getProperty(chiaveProiezione) === impronta && !pending.changes.length && !pending.errors.length) {
    return {ok:true, invariato:true, read_only:!!vista.sola_lettura, url_foglio:foglio.getUrl(), esito:{aggiunte:0,manuali:0,conflitti:0}};
  }
  proprieta.deleteProperty('MI_READY_VIEW_' + idEvento);
  const esito = scriviProiezioneEvento_(scheda, vista);
  if (!esito.manuali && !esito.conflitti) configuraSchedeEconomicheEvento_(foglio, idEvento, vista.sola_lettura);
  aggiornaProiezionePagamentiEventoConLock_(foglio, idEvento);
  // Store only after all writes succeed. Pending edits remain in the sheet;
  // a new canonical value changes the fingerprint and retries acknowledgment.
  if (!esito.manuali && !esito.conflitti) proprieta.setProperty(chiaveProiezione, impronta);
  else proprieta.deleteProperty(chiaveProiezione);
  aggiungiControllo_('FOGLIO_OPERATIVO', 'REFRESH', idEvento, 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120), 'DATABASE_TO_EVENT_SHEET', 'SEGRETERIA');
  return { ok: true, read_only:!!vista.sola_lettura, url_foglio: foglio.getUrl(), righe: vista.righe.length, esito: esito, message: 'Controllo completato. Le modifiche nelle celle blu si inviano con Sincronizza.' };
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
