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
  scheda.getRange(1, colonna, scheda.getMaxRows(), 1).addDeveloperMetadata('MI_CAMPO', key);
}

function testoCellaEvento_(valore) {
  return valore instanceof Date ? valore.toISOString() : String(valore == null ? '' : valore);
}

function improntaCellaEvento_(valore) {
  return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, testoCellaEvento_(valore), Utilities.Charset.UTF_8)
    .map(function (byte) { return ('0' + (byte & 255).toString(16)).slice(-2); }).join('');
}

function identitaRigaEvento_(evento, codice, numero) {
  if (!codice || !Number.isInteger(Number(numero)) || Number(numero) < 1) return '';
  return JSON.stringify([String(evento), String(codice), Number(numero)]);
}

function baseRigaEvento_(valore, id) {
  if (!valore) return { id: id, campi: {} };
  let base;
  try { base = JSON.parse(String(valore)); } catch (errore) { throw new Error('Versione di confronto danneggiata.'); }
  if (base.id !== id || !base.campi || typeof base.campi !== 'object' || Array.isArray(base.campi)) throw new Error('Identità della riga modificata: sincronizzazione sospesa.');
  return base;
}

/** Non deduce mai una vecchia struttura dai titoli: su fogli popolati serve una migrazione verificata. */
function preparaStrutturaIncrementale_(scheda, vista) {
  let mappa = mappaColonneEvento_(scheda);
  const nuovo = !Object.keys(mappa).length;
  if (nuovo && scheda.getLastRow() > 1) throw new Error('Foglio precedente con dati: occorre verificare la corrispondenza delle colonne prima della migrazione. Nessun dato è stato modificato.');
  if (nuovo && scheda.getLastColumn() > 0) {
    throw new Error('Foglio senza identificativi stabili: verificare la struttura prima della migrazione.');
  }
  const tecniche = [
    { key: '_ordine', label: 'Codice prenotazione' },
    { key: '_numero', label: 'Numero partecipante' },
    { key: '_base', label: 'Versione sincronizzata' }
  ];
  if (!nuovo && tecniche.some(function (campo) { return !mappa[campo.key]; })) throw new Error('Manca una colonna tecnica: ripristinarla prima di aggiornare.');
  const campi = tecniche.concat(vista.colonne).concat([{ key: 'mi_sync_state', label: 'Verifica sincronizzazione', gruppo: 'persona' }]);
  const nuove = {};
  const visti = {};
  campi.forEach(function (campo) {
    if (!campo.key || visti[campo.key]) throw new Error('Identificativo campo duplicato o vuoto.');
    visti[campo.key] = true;
  });
  campi.forEach(function (campo, indice) {
    if (mappa[campo.key]) return;
    nuove[campo.key] = true;
    let precedente = 0;
    for (let i = indice - 1; i >= 0; i -= 1) {
      if (mappa[campi[i].key]) { precedente = mappa[campi[i].key]; break; }
    }
    const col = precedente + 1;
    if (col <= scheda.getLastColumn()) scheda.insertColumnBefore(col);
    else if (col > scheda.getMaxColumns()) scheda.insertColumnsAfter(scheda.getMaxColumns(), col - scheda.getMaxColumns());
    identificaColonnaEvento_(scheda, col, campo.key);
    scheda.getRange(1, col, scheda.getMaxRows(), 1).addDeveloperMetadata('MI_BASE_VUOTA', '1');
    scheda.getRange(1, col).setValue(campo.label).setFontWeight('bold').setBackground('#172554').setFontColor('#ffffff');
    mappa = mappaColonneEvento_(scheda);
    if (campo.key.charAt(0) === '_') scheda.hideColumns(col);
    else if (campo.gruppo && campo.gruppo !== 'persona' && scheda.getColumnGroupDepth(col) === 0) {
      scheda.getRange(1, col, scheda.getMaxRows(), 1).shiftColumnGroupDepth(1);
    }
  });
  scheda.setFrozenRows(1);
  return { mappa: mappa, nuove: nuove };
}

/** Tre versioni: ultima sincronizzata, cella manuale, valore centrale. */
function statoCellaEvento_(locale, centrale, base, formula) {
  const improntaLocale = improntaCellaEvento_(locale);
  const improntaCentrale = improntaCellaEvento_(centrale);
  if (formula) return 'FORMULA_MANUALE';
  if (improntaLocale === improntaCentrale) return 'ALLINEATO';
  if (!base) return 'DA_VERIFICARE';
  if (improntaLocale === base) return 'AGGIORNAMENTO_CENTRALE';
  if (improntaCentrale === base) return 'MODIFICA_MANUALE';
  return 'CONFLITTO';
}

function aggiornaDatiIncrementaliEvento_(scheda, vista) {
  const metadati = scheda.getDeveloperMetadata();
  const evento = metadati.find(function (meta) { return meta.getKey() === 'MI_ID_EVENTO'; });
  if (evento && String(evento.getValue()) !== String(vista.evento.id)) throw new Error('Il foglio appartiene a un altro evento.');
  if (!Object.keys(mappaColonneEvento_(scheda)).length && scheda.getLastColumn() > 0) migraStrutturaEventoVerificata_(scheda, vista);
  const struttura = preparaStrutturaIncrementale_(scheda, vista);
  const mappa = struttura.mappa;
  const basiVuote = {};
  scheda.createDeveloperMetadataFinder().withKey('MI_BASE_VUOTA').find().forEach(function (meta) {
    const range = meta.getLocation().getColumn();
    if (range) basiVuote[range.getColumn()] = true;
  });
  impostaMetadatoVista_(scheda, 'MI_ID_EVENTO', vista.evento.id);
  const dati = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, scheda.getLastColumn()).getValues() : [];
  const indice = Object.create(null);
  // Verifica tutte le identità prima di modificare le celle dei partecipanti.
  dati.forEach(function (riga, offset) {
    if (riga.every(function (valore) { return valore === ''; })) return;
    const id = identitaRigaEvento_(vista.evento.id, riga[mappa._ordine - 1], riga[mappa._numero - 1]);
    if (!id) {
      if (riga[mappa._base - 1]) throw new Error('Identità tecnica rimossa da una riga già sincronizzata.');
      return; // Una riga manuale incompleta non registra una prenotazione.
    }
    if (indice[id]) throw new Error('Identificativo partecipante duplicato nel foglio.');
    baseRigaEvento_(riga[mappa._base - 1], id);
    indice[id] = offset + 2;
  });
  const centrali = {};
  vista.righe.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga.codice_ordine, riga.numero_partecipante);
    if (!id || centrali[id]) throw new Error('Identificativo partecipante centrale non valido o duplicato.');
    centrali[id] = true;
  });
  const risultato = { aggiunte: 0, aggiornate: 0, manuali: 0, conflitti: 0 };
  dati.forEach(function (riga, offset) {
    const id = identitaRigaEvento_(vista.evento.id, riga[mappa._ordine - 1], riga[mappa._numero - 1]);
    if (!id || !centrali[id]) scheda.getRange(offset + 2, mappa.mi_sync_state).setValue(id ? 'Non presente tra le iscrizioni attive: verificare' : 'Bozza manuale: non convalidata, nessun posto impegnato');
  });
  vista.righe.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga.codice_ordine, riga.numero_partecipante);
    const nuova = !indice[id];
    const numero = indice[id] || Math.max(2, scheda.getLastRow() + 1);
    if (numero > scheda.getMaxRows()) scheda.insertRowsAfter(scheda.getMaxRows(), numero - scheda.getMaxRows());
    if (nuova) {
      const iniziale = Array(scheda.getLastColumn()).fill('');
      const baseIniziale = { id: id, campi: {} };
      vista.colonne.forEach(function (campo) {
        const valore = normalizzaTesto_(testoCellaEvento_(riga.valori[campo.key]), 5000);
        iniziale[mappa[campo.key] - 1] = neutralizzaFormula_(valore, 5000);
        baseIniziale.campi[campo.key] = improntaCellaEvento_(valore);
      });
      iniziale[mappa._ordine - 1] = riga.codice_ordine;
      iniziale[mappa._numero - 1] = riga.numero_partecipante;
      iniziale[mappa._base - 1] = JSON.stringify(baseIniziale);
      iniziale[mappa.mi_sync_state - 1] = 'Allineato';
      // Una sola scrittura evita identità parziali se il processo si interrompe.
      scheda.getRange(numero, 1, 1, iniziale.length).setNumberFormat('@').setValues([iniziale]);
      indice[id] = numero;
      risultato.aggiunte += 1;
      risultato.aggiornate += vista.colonne.length;
      return;
    }
    const rangeRiga = scheda.getRange(numero, 1, 1, scheda.getLastColumn());
    const valoriLocali = rangeRiga.getValues()[0];
    const formuleLocali = rangeRiga.getFormulas()[0];
    const base = baseRigaEvento_(valoriLocali[mappa._base - 1], id);
    const prima = { manuali: risultato.manuali, conflitti: risultato.conflitti };
    vista.colonne.forEach(function (campo) {
      const cella = scheda.getRange(numero, mappa[campo.key]);
      const valore = normalizzaTesto_(testoCellaEvento_(riga.valori[campo.key]), 5000);
      if (!base.campi[campo.key] && basiVuote[mappa[campo.key]]) base.campi[campo.key] = improntaCellaEvento_('');
      const stato = statoCellaEvento_(valoriLocali[mappa[campo.key] - 1], valore, base.campi[campo.key], formuleLocali[mappa[campo.key] - 1]);
      if (stato === 'AGGIORNAMENTO_CENTRALE') {
        // Rilettura immediata: il lock serializza gli script, non le digitazioni umane.
        const attuale = cella.getValue();
        if (!nuova && (cella.getFormula() || improntaCellaEvento_(attuale) !== base.campi[campo.key])) { risultato.conflitti += 1; return; }
        cella.setNumberFormat('@').setValue(neutralizzaFormula_(valore, 5000));
        base.campi[campo.key] = improntaCellaEvento_(valore);
        risultato.aggiornate += 1;
      } else if (stato === 'ALLINEATO') base.campi[campo.key] = improntaCellaEvento_(valore);
      else if (stato === 'MODIFICA_MANUALE' || stato === 'FORMULA_MANUALE') risultato.manuali += 1;
      else risultato.conflitti += 1;
    });
    scheda.getRange(numero, mappa._base).setValue(JSON.stringify(base));
    scheda.getRange(numero, mappa.mi_sync_state).setValue(risultato.conflitti > prima.conflitti ? 'Conflitto o dati precedenti da verificare' : (risultato.manuali > prima.manuali ? 'Modifiche manuali da convalidare' : 'Allineato'));
  });
  impostaMetadatoVista_(scheda, 'MI_DATA_AGGIORNAMENTO', new Date().toISOString());
  return risultato;
}

/** Adozione una tantum del vecchio formato solo se ogni intestazione è ancora verificabile. */
function migraStrutturaEventoVerificata_(scheda, vista) {
  const meta = scheda.getDeveloperMetadata().find(function (item) { return item.getKey() === 'MI_CAMPI'; });
  let campi;
  try { campi = JSON.parse(meta ? meta.getValue() : 'null'); } catch (errore) {}
  if (!Array.isArray(campi) || !campi.length || new Set(campi).size !== campi.length || campi.some(function (key) { return typeof key !== 'string' || key.charAt(0) === '_'; })) throw new Error('Struttura precedente non verificabile: nessun dato modificato.');
  const catalogo = campiElencoOperativo_(false).concat(vista.colonne).reduce(function (result, campo) { result[campo.key] = campo.label; return result; }, {});
  const attese = ['Codice prenotazione', 'Numero partecipante'].concat(campi.map(function (key) { return catalogo[key]; }));
  const reali = scheda.getRange(1, 1, 1, attese.length).getValues()[0];
  if (attese.some(function (label, index) { return !label || label !== reali[index]; })) throw new Error('Colonne precedenti spostate o rinominate: verificare la migrazione senza sovrascrivere il foglio.');
  const dati = scheda.getLastRow() > 1 ? scheda.getRange(2, 1, scheda.getLastRow() - 1, 2).getValues() : [];
  const ids = {};
  dati.forEach(function (riga) {
    const id = identitaRigaEvento_(vista.evento.id, riga[0], riga[1]);
    if (id && ids[id]) throw new Error('Identificativi duplicati nel foglio precedente.');
    if (id) ids[id] = true;
  });
  ['_ordine', '_numero'].concat(campi).forEach(function (key, index) { identificaColonnaEvento_(scheda, index + 1, key); });
  const col = scheda.getLastColumn() + 1;
  if (col > scheda.getMaxColumns()) scheda.insertColumnsAfter(scheda.getMaxColumns(), 1);
  scheda.getRange(1, col).setValue('Versione sincronizzata');
  identificaColonnaEvento_(scheda, col, '_base');
  scheda.hideColumns(col);
  // La prima lettura riconosce come base soltanto celle uguali al centro.
  // Le divergenze preesistenti rimangono DA_VERIFICARE, mai attribuite arbitrariamente.
}
