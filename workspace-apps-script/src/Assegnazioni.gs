/** Carica una vista collettiva per camere e pullman di un evento. */
function caricaAssegnazioniEvento(form) {
  form = form || {};
  const eventId = normalizzaTesto_(form.id_evento, 40);
  if (!eventId) throw new Error('Scegli un evento.');
  const registrations = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.REGISTRATIONS)).filter(function (row) {
    return String(row.id_evento) === eventId && ['ANNULLATO', 'SCADUTO', 'CANCELLED', 'EXPIRED'].indexOf(String(row.stato).toUpperCase()) < 0;
  });
  const allowed = registrations.reduce(function (result, row) { result[String(row.codice_ordine)] = true; return result; }, {});
  const operational = indiceStatoOperativo_();
  const participants = convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.PARTICIPANTS)).filter(function (row) {
    return allowed[String(row.codice_ordine)] && String(row.stato_partecipante || 'ACTIVE').toUpperCase() !== 'CANCELLED';
  }).map(function (row) {
    const orderCode = String(row.codice_ordine); const number = Number(row.numero_partecipante) || 0;
    const fields = datiOperativiPartecipante_(row, operational[orderCode + '|' + number] || {});
    return { codice_ordine: orderCode, numero_partecipante: number, nome: String(row.nome || ''), cognome: String(row.cognome || ''), camera: String(fields.room || fields.camera || fields.alloggio || ''), pullman: String(fields.pullman || fields.transport || '') };
  }).sort(function (left, right) { return (left.cognome + ' ' + left.nome).localeCompare(right.cognome + ' ' + right.nome, 'it', { sensitivity: 'base' }); });
  return { partecipanti: participants, sistemazioni: elencaSistemazioniDisponibili_(eventId) };
}

/** Applica un gruppo di assegnazioni in un unico lock e registra ogni modifica. */
function salvaAssegnazioniEvento(form) {
  form = form || {};
  const eventId = normalizzaTesto_(form.id_evento, 40);
  const changes = Array.isArray(form.modifiche) ? form.modifiche.slice(0, 500) : [];
  if (!eventId || !changes.length) throw new Error('Scegli un evento e almeno una modifica.');
  const operator = normalizzaTesto_(Session.getActiveUser().getEmail() || 'SEGRETERIA', 120);
  const reason = normalizzaTesto_(form.motivo, 500);
  if (reason.length < 3) throw new Error('Indica il motivo delle assegnazioni.');
  const lock = LockService.getDocumentLock(); lock.waitLock(30000);
  let applied = 0;
  try {
  const assignmentContext = caricaAssegnazioniEvento({ id_evento: eventId });
  const validParticipants = assignmentContext.partecipanti.reduce(function (result, row) { result[row.codice_ordine + '|' + row.numero_partecipante] = row; return result; }, {});
  const rooms = assignmentContext.sistemazioni.reduce(function (result, room) { result[String(room.code)] = room; return result; }, {});
  const plannedRooms = assignmentContext.partecipanti.reduce(function (result, row) { result[row.codice_ordine + '|' + row.numero_partecipante] = row.camera; return result; }, {});
  const normalizedChanges = changes.map(function (change) {
    const orderCode = normalizzaTesto_(change.codice_ordine, 64);
    const number = Math.max(0, Math.round(Number(change.numero_partecipante) || 0));
    const key = normalizzaValoreElenco_(change.campo, ['ROOM', 'PULLMAN']);
    const value = normalizzaTesto_(change.valore, 80);
    const participantKey = orderCode + '|' + number;
    if (!validParticipants[participantKey] || !key) throw new Error('Assegnazione non valida o fuori dall’evento selezionato.');
    if (key === 'ROOM' && value && !rooms[value]) throw new Error('La sistemazione “' + value + '” non è disponibile per questo evento.');
    if (key === 'ROOM') plannedRooms[participantKey] = value;
    return { orderCode: orderCode, number: number, participantKey: participantKey, key: key, value: value };
  });
  const finalRoomCounts = Object.keys(plannedRooms).reduce(function (result, participantKey) {
    const code = plannedRooms[participantKey];
    if (code) result[code] = (result[code] || 0) + 1;
    return result;
  }, {});
  Object.keys(finalRoomCounts).forEach(function (code) {
    if (!rooms[code] || finalRoomCounts[code] > rooms[code].capacity) throw new Error('La sistemazione “' + code + '” supererebbe la capienza disponibile.');
  });
    normalizedChanges.forEach(function (change) {
      const stateKey = change.key === 'ROOM' ? 'room' : 'pullman';
      registraOperazioneSegreteria_(change.orderCode, change.number, change.key === 'ROOM' ? 'CHANGE_ACCOMMODATION' : 'CHANGE_TRANSPORT', { key: stateKey, value: change.value }, reason, operator, 'Assegnazione collettiva aggiornata.');
      applied += 1;
    });
    aggiungiControllo_('BULK_ASSIGNMENTS', 'EVENT', eventId, 'SUCCESS', operator, String(applied), 'WORKSPACE_UI');
  } finally { lock.releaseLock(); }
  return { ok: true, applied: applied, message: 'Assegnazioni aggiornate: ' + applied + '.' };
}
