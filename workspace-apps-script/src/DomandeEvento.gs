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
