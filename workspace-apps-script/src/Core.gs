function normalizeEnum_(value, allowed) {
  const normalized = String(value || '').trim().toUpperCase();
  return allowed.indexOf(normalized) >= 0 ? normalized : '';
}

function normalizeText_(value, maxLength) {
  let text = String(value == null ? '' : value).replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
  if (text.length > maxLength) text = text.slice(0, maxLength);
  return text;
}

function neutralizeFormula_(value, maxLength) {
  const text = normalizeText_(value, maxLength);
  return /^[=+\-@]/.test(text) ? "'" + text : text;
}

function euroToCents_(value) {
  if (typeof value === 'string') value = value.replace(',', '.').trim();
  const amount = Number(value);
  if (!Number.isFinite(amount) || amount <= 0 || amount > 1000000) return null;
  return Math.round(amount * 100);
}

function containsCardNumberLike_(value) {
  const groups = String(value || '').match(/(?:\d[ -]?){13,19}/g) || [];
  return groups.some(function (group) {
    const digits = group.replace(/\D/g, '');
    if (digits.length < 13 || digits.length > 19) return false;
    let sum = 0;
    let alternate = false;
    for (let index = digits.length - 1; index >= 0; index -= 1) {
      let digit = Number(digits.charAt(index));
      if (alternate) {
        digit *= 2;
        if (digit > 9) digit -= 9;
      }
      sum += digit;
      alternate = !alternate;
    }
    return sum % 10 === 0;
  });
}

function stableStringify_(value) {
  if (value === null || typeof value !== 'object') return JSON.stringify(value);
  if (Array.isArray(value)) return '[' + value.map(stableStringify_).join(',') + ']';
  return '{' + Object.keys(value).sort().map(function (key) {
    return JSON.stringify(key) + ':' + stableStringify_(value[key]);
  }).join(',') + '}';
}

function makeOpaqueId_(prefix) {
  return prefix + '_' + Utilities.getUuid().replace(/-/g, '').slice(0, 24);
}

function jsonResponse_(payload) {
  return ContentService.createTextOutput(JSON.stringify(payload)).setMimeType(ContentService.MimeType.JSON);
}

function appendAudit_(action, entityType, entityRef, outcome, actorLabel, detailCode, channel) {
  getRequiredSheet_(MI_SHEETS.AUDIT_LOG).appendRow([
    makeOpaqueId_('aud'),
    new Date(),
    normalizeText_(channel || 'WORKSPACE', 30),
    normalizeText_(action, 60),
    normalizeText_(entityType, 40),
    neutralizeFormula_(entityRef, 100),
    normalizeEnum_(outcome, ['SUCCESS', 'REJECTED', 'ERROR']) || 'ERROR',
    neutralizeFormula_(actorLabel || 'UNVERIFIED', 100),
    normalizeText_(detailCode || '', 100)
  ]);
}

function headerIndex_(sheet) {
  const lastColumn = sheet.getLastColumn();
  if (lastColumn < 1) return {};
  const headers = sheet.getRange(1, 1, 1, lastColumn).getDisplayValues()[0];
  return headers.reduce(function (index, header, position) {
    if (header) index[header] = position;
    return index;
  }, {});
}

function rowsAsObjects_(sheet) {
  if (sheet.getLastRow() < 2) return [];
  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getDisplayValues()[0];
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, headers.length).getValues().map(function (row, offset) {
    const object = { _row: offset + 2 };
    headers.forEach(function (header, index) { object[header] = row[index]; });
    return object;
  });
}
