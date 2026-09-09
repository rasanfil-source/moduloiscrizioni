/** Identità delle colonne legata ai metadati Google, che seguono gli spostamenti. */
function mappaColonneEvento_(scheda) {
  const mappa = Object.create(null);
  const posizioni = {};
  scheda.createDeveloperMetadataFinder().withKey('MI_CAMPO').find().forEach(function (meta) {
    const range = meta.getLocation().getColumn();
    if (!range) throw new Error('Metadato campo senza colonna.');
    const key = meta.getValue();
    const col = range.getColumn();
    if (mappa[key] || posizioni[col]) throw new Error('Identificativi di colonna duplicati: correggere il foglio.');
    mappa[key] = col;
    posizioni[col] = true;
  });
  return mappa;
}

function identificaColonnaEvento_(scheda, colonna, key) {
  intervalloColonnaEvento_(scheda, colonna).addDeveloperMetadata('MI_CAMPO', key);
}

/** I metadati richiedono una colonna non delimitata, anche se il range copre tutte le righe. */
function intervalloColonnaEvento_(scheda, colonna) {
  let lettere = '';
  for (let n = colonna; n > 0; n = Math.floor((n - 1) / 26)) {
    lettere = String.fromCharCode(65 + (n - 1) % 26) + lettere;
  }
  return scheda.getRange(lettere + ':' + lettere);
}

/** Identità usata dalla verifica di consegna WordPress. */
function identitaRigaEvento_(evento, codice, numero) {
  if (!codice || !Number.isInteger(Number(numero)) || Number(numero) < 1) return '';
  return JSON.stringify([String(evento), String(codice), Number(numero)]);
}
