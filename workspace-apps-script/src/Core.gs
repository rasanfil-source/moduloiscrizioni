function normalizzaValoreElenco_(value, allowed) {
  const normalized = String(value || '').trim().toUpperCase();
  return allowed.indexOf(normalized) >= 0 ? normalized : '';
}

function normalizzaTesto_(value, maxLength) {
  let text = String(value == null ? '' : value).replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
  if (text.length > maxLength) text = text.slice(0, maxLength);
  return text;
}

function neutralizzaFormula_(value, maxLength) {
  const text = normalizzaTesto_(value, maxLength);
  return /^[=+\-@]/.test(text) ? "'" + text : text;
}

function serializzaInModoStabile_(value) {
  if (value === null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) return '[' + value.map(serializzaInModoStabile_).join(',') + ']';
  return '{' + Object.keys(value).sort().map(function (key) {
    return JSON.stringify(key) + ':' + serializzaInModoStabile_(value[key]);
  }).join(',') + '}';
}

function creaIdentificativoOpaco_(prefix) {
  return prefix + '_' + Utilities.getUuid().replace(/-/g, '').slice(0, 24);
}

function creaRispostaJson_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload)).setMimeType(ContentService.MimeType.JSON);
}

function aggiungiControllo_(action, entityType, entityRef, outcome, actorLabel, detailCode, channel) {
  ottieniSchedaObbligatoria_(MI_SHEETS.AUDIT_LOG).appendRow([
    creaIdentificativoOpaco_('aud'),
    new Date(),
    normalizzaTesto_(channel || 'WORKSPACE', 30),
    normalizzaTesto_(action, 60),
    normalizzaTesto_(entityType, 40),
    neutralizzaFormula_(entityRef, 100),
    normalizzaValoreElenco_(outcome, ['SUCCESS', 'REJECTED', 'ERROR']) || 'ERROR',
    neutralizzaFormula_(actorLabel || 'UNVERIFIED', 100),
    normalizzaTesto_(detailCode || '', 100)
  ]);
}

function creaIndiceIntestazioni_(sheet) {
  const lastColumn = sheet.getLastColumn();
  if (lastColumn < 1) return {};
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  return headers.reduce(function (index, header, position) {
    if (header) index[header] = position;
    return index;
  }, {});
}

function convertiRigheInOggetti_(sheet) {
  if (sheet.getLastRow() < 2) return [];
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, headers.length).getValues().map(function (row, offset) {
    const object = { _row: offset + 2 };
    headers.forEach(function (header, index) { object[header] = row[index]; });
    return object;
  });
}

/** Servizi privati chiamati dal proxy WordPress dopo autorizzazione sull'evento. */
function versioneGestione_(value) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, JSON.stringify(value), Utilities.Charset.UTF_8).map(function(b){return ('0'+(b&255).toString(16)).slice(-2);}).join('');
}
