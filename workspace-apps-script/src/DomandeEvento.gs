/** Include both optional and required questions, even in saved operational views. */
function aggiungiColonneDomande_(colonne, evento, iscrizioni, partecipanti) {
  const definitions = new Map();
  const add = field => {
    if (!field || !/^custom_[A-Za-z0-9_-]{1,73}$/.test(String(field.key || ''))) return;
    definitions.set(field.key, String(field.label || field.key));
  };
  iscrizioni.forEach(r => {
    const snapshot = decodificaOggetto_(r.snapshot_json);
    ((snapshot.event || {}).participant_fields || []).forEach(add);
  });
  decodificaElenco_(evento.domande_json).forEach(add);
  partecipanti.forEach(p => Object.keys(decodificaOggetto_(p.dati_aggiuntivi_json)).forEach(key => {
    if (!definitions.has(key)) add({key:key,label:key.replace(/^custom_/, '').replace(/_/g, ' ')});
  }));
  definitions.forEach((label, key) => {
    const existing = colonne.find(c => c.key === key);
    if (existing) existing.label = label;
    else colonne.push({key:key,label:label,gruppo:'Domande',comprimibile:false});
  });
}

/** The configured fields, not a generic travel profile, define the event sheet. */
function vistaEventoSolaLettura_(evento, colonne) {
  const schema = decodificaOggetto_(evento.schema_vista_json);
  return Array.isArray(schema.fields) && Array.isArray(schema.options) && schema.pricing === 'ZERO' && !colonne.some(c => c.key === 'total');
}

function applicaSchemaColonneEvento_(colonne, evento, iscrizioni, partecipanti, pagamenti) {
  const schema = decodificaOggetto_(evento.schema_vista_json);
  if (!Array.isArray(schema.fields) || !Array.isArray(schema.options)) return;
  const catalogo = campiElencoOperativo_(false);
  const result = [];
  const add = (key, label) => {
    if (!/^[a-z][a-z0-9_-]{0,79}$/.test(String(key)) || result.some(c=>c.key===key)) return;
    const known = catalogo.find(c=>c.key===key);
    result.push({key:key,label:String(label || (known && known.label) || key),gruppo:gruppoCampoVistaOperativa_(key),comprimibile:['paid_cash','paid_transfer','paid_card'].includes(key)});
  };
  ['last_name','first_name','phone'].forEach(key=>add(key));
  schema.fields.forEach(field=>{if (field) add(field.key, field.label);});
  if (schema.room) add('room');
  aggiungiColonneServizi_(result, schema.options);
  if (schema.special_requests) add('special_requests');
  const codes = new Set(iscrizioni.map(r=>String(r.codice_ordine)));
  const economic = schema.pricing !== 'ZERO' || iscrizioni.some(r=>Number(r.totale_centesimi)>0 || Number(r.versato_centesimi)>0) || pagamenti.some(p=>codes.has(String(p.codice_ordine)));
  if (economic) ['total','paid','paid_cash','paid_transfer','paid_card','balance'].forEach(key=>add(key));
  if (partecipanti.some(p=>['PRESENT','ABSENT','UNRECORDED'].includes(decodificaOggetto_(p.dati_aggiuntivi_json).attendance))) add('attendance','Presenza effettiva');
  colonne.splice(0, colonne.length, ...result);
}
