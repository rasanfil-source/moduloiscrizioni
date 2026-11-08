function elencaGruppi() {
  return convertiRigheInOggetti_(ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS)).map(function (row) {
    const logo = normalizzaAssetEsistenteGruppo_(row.logo_url);
    const image = normalizzaAssetEsistenteGruppo_(row.immagine_url);
    return {
      id: normalizzaTesto_(row.id_gruppo, 64),
      nome: normalizzaTesto_(row.nome, 120),
      slug: normalizzaTesto_(row.slug, 80),
      stato: normalizzaTesto_(row.stato, 20),
      logo_url: logo.url,
      immagine_url: image.url,
      avviso_asset: [logo.avviso, image.avviso].filter(Boolean).join(' ')
    };
  }).filter(function (row) { return row.id && row.nome && row.stato !== 'ARCHIVIATO'; });
}

function aggiungiGruppo(form) {
  form = form || {};
  const nome = normalizzaTesto_(form.nome, 120);
  if (nome.length < 2) throw new Error('Indica il nome del gruppo.');
  const slug = creaSlugGruppo_(form.slug || nome);
  if (!slug) throw new Error('Il nome del gruppo non produce un identificativo valido.');
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  const existing = convertiRigheInOggetti_(sheet);
  if (existing.some(function (group) { return String(group.slug) === slug || String(group.nome).toLowerCase() === nome.toLowerCase(); })) throw new Error('Il gruppo esiste già.');
  const logoUrl = normalizzaUrlImmagineGruppo_(form.logo_url);
  const imageUrl = normalizzaUrlImmagineGruppo_(form.immagine_url);
  const wordpress = inviaComandoWordPress_('CREATE_GROUP', { name: nome, slug: slug, logo_url: logoUrl, image_url: imageUrl });
  const wordpressId = Math.round(Number(wordpress.group_id) || 0);
  if (wordpressId < 1) throw new Error('WordPress non ha restituito l’identificativo del gruppo.');
  const id = String(wordpressId);
  sheet.appendRow([id, neutralizzaFormula_(nome,120), slug, 'ATTIVO', logoUrl, imageUrl, new Date()]);
  return { ok: true, id: id, nome: nome, slug: slug, esistente_in_wordpress: wordpress.existing === true };
}

/** Allinea gli identificativi dei gruppi esistenti con WordPress senza cancellare righe. */
function sincronizzaGruppiConWordPress() {
  const sheet = ottieniSchedaObbligatoria_(MI_SHEETS.GROUPS);
  const rows = convertiRigheInOggetti_(sheet);
  let updated = 0;
  const assetWarnings = [];
  rows.forEach(function (row) {
    const name = normalizzaTesto_(row.nome, 120);
    const slug = creaSlugGruppo_(row.slug || name);
    if (!name || !slug || String(row.stato).toUpperCase() === 'ARCHIVIATO') return;
    const logo = normalizzaAssetEsistenteGruppo_(row.logo_url);
    const image = normalizzaAssetEsistenteGruppo_(row.immagine_url);
    if (logo.avviso || image.avviso) assetWarnings.push(name);
    // WordPress ignora gli URL vuoti: un valore manuale non valido non sovrascrive
    // un’immagine già salvata e non interrompe la sincronizzazione degli altri gruppi.
    const wordpress = inviaComandoWordPress_('CREATE_GROUP', { name: name, slug: slug, logo_url: logo.url, image_url: image.url });
    const wordpressId = Math.round(Number(wordpress.group_id) || 0);
    if (wordpressId < 1) throw new Error('WordPress non ha restituito l’identificativo del gruppo ' + name + '.');
    if (String(row.id_gruppo) !== String(wordpressId)) {
      sheet.getRange(row._row, 1).setValue(String(wordpressId));
      updated += 1;
    }
  });
  aggiungiControllo_('SYNC_GROUPS', 'GROUPS', 'ALL', 'SUCCESS', normalizzaTesto_(Session.getActiveUser().getEmail(), 120), String(updated), 'WORKSPACE_UI');
  const warning = assetWarnings.length ? ' URL logo/immagine non HTTPS ignorati per: ' + assetWarnings.join(', ') + '. Gli asset già presenti in WordPress sono stati conservati.' : '';
  return { ok: true, updated: updated, warning: assetWarnings.length > 0, message: 'Gruppi allineati con WordPress: ' + updated + ' identificativi aggiornati.' + warning };
}

/** Allinea gli identificativi dei gruppi esistenti con WordPress senza cancellare righe. */
function creaSlugGruppo_(value) {
  return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
}

function normalizzaUrlImmagineGruppo_(value) {
  const url = normalizzaTesto_(value, 500);
  if (!url) return '';
  if (!/^https:\/\/[^\s]+$/i.test(url)) throw new Error('Logo o immagine devono usare un URL HTTPS.');
  return url;
}

/** Legge i dati esistenti senza lasciare che una cella manuale errata blocchi l’interfaccia. */
function normalizzaAssetEsistenteGruppo_(value) {
  try {
    return { url: normalizzaUrlImmagineGruppo_(value), avviso: '' };
  } catch (error) {
    return { url: '', avviso: 'Un logo o un’immagine con URL non HTTPS è stato ignorato.' };
  }
}
