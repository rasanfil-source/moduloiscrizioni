document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-mi-payments]');
  if (!root) return;
  const el = selector => root.querySelector(selector);
  const search = el('#mi-payment-search'), results = el('[data-results]');
  const status = el('[data-status]'), searchStatus = el('#mi-payment-search-status');
  const form = el('[data-payment-form]'), fields = el('[data-payment-fields]');
  const save = el('[data-save]'), next = el('[data-new]'), error = el('#mi-payment-error');
  const searchFields = el('[data-search-fields]'), retryDetail = el('[data-retry-detail]');
  let generation = 0, timer, controller, selected = null, pending = null, busy = false;
  const field = name => form.elements.namedItem(name);
  const money = cents => (Number(cents) / 100).toLocaleString('it-IT', {style:'currency', currency:'EUR'});
  const say = (text, failed = false) => {
    status.textContent = text;
    status.className = 'mi-payment-status' + (failed ? ' mi-portal-error' : '');
  };
  async function request(operation, data, signal) {
    const own = new AbortController();
    const abort = () => own.abort();
    if (signal) signal.addEventListener('abort', abort, {once:true});
    if (signal?.aborted) own.abort();
    const timeout = setTimeout(abort, 60000);
    try {
      const body = new URLSearchParams({action:'mi_portal_payment', nonce:root.dataset.nonce, operation, ...data});
      const response = await fetch(root.dataset.endpoint, {method:'POST', credentials:'same-origin', cache:'no-store', body, signal:own.signal});
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.data?.message || 'Risposta non disponibile. Verifica la connessione e riprova.');
      return result.data;
    } finally {
      clearTimeout(timeout);
      if (signal) signal.removeEventListener('abort', abort);
    }
  }
  function showHistory(movements) {
    const host=el('[data-payment-history]');host.replaceChildren();
    const title=document.createElement('h3');title.textContent='Movimenti registrati';host.append(title);
    if(!movements.length){const p=document.createElement('p');p.textContent='Nessun movimento registrato.';host.append(p);return;}
    const table=document.createElement('table'),head=table.createTHead().insertRow();
    for(const label of ['Data','Tipo','Importo','Metodo','Riferimento','Operatore']){const th=document.createElement('th');th.textContent=label;head.append(th);}
    const body=table.createTBody();
    for(const m of movements){const row=body.insertRow();for(const value of [new Date(m.data).toLocaleDateString('it-IT'),m.tipo,money(m.importo),m.metodo,m.riferimento,m.operatore])row.insertCell().textContent=value;}
    host.append(table);
  }
  function invalidate() {
    clearTimeout(timer); controller?.abort(); generation++;
    selected = null; form.hidden = true; retryDetail.hidden = true; results.replaceChildren();
    say(''); error.textContent = '';
  }
  async function find() {
    const query = search.value.trim();
    if (query.length < 2) { searchStatus.textContent = 'Digita almeno due caratteri.'; return; }
    const ticket = generation;
    controller = new AbortController();
    searchStatus.textContent = 'Ricerca in corso…';
    try {
      const data = await request('search', {query}, controller.signal);
      if (ticket !== generation) return;
      results.replaceChildren();
      for (const p of data.prenotazioni) {
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'mi-payment-choice';
        const name = document.createElement('strong'), detail = document.createElement('small');
        name.textContent = p.nome; detail.textContent = p.evento + ' · ' + p.codice;
        button.append(name, detail); button.addEventListener('click', () => choose(p));
        results.append(button);
      }
      searchStatus.textContent = data.prenotazioni.length
        ? data.prenotazioni.length + ' prenotazioni trovate.' + (data.prenotazioni.length === 30 ? ' Sono mostrate le prime 30: precisa la ricerca.' : '')
        : 'Nessuna prenotazione trovata nelle iniziative a cui hai accesso.';
    } catch (e) {
      if (ticket !== generation) return;
      searchStatus.textContent = 'Ricerca non riuscita. Premi Invio nel campo per riprovare. ' + (e.name === 'AbortError' ? 'Tempo di attesa esaurito.' : e.message);
    }
  }
  async function choose(p) {
    controller?.abort(); const ticket = ++generation;
    controller = new AbortController(); selected = p; form.hidden = true; retryDetail.hidden = true;
    results.replaceChildren(); say('Caricamento del saldo aggiornato…');
    try {
      const data = await request('detail', {registration_id:p.id}, controller.signal);
      if (ticket !== generation) return;
      const r = data.saldo;
      el('[data-person]').textContent = p.nome;
      el('[data-event]').textContent = r.evento;
      el('[data-order]').textContent = 'Prenotazione ' + p.codice;
      el('[data-total]').textContent = money(r.totale);
      el('[data-paid]').textContent = money(r.versato);
      el('[data-balance]').textContent = money(r.residuo);
      form.reset(); fields.disabled = false; field('data').value = data.data; field('rata').value = 'NON_ASSEGNATO';
      showHistory(r.movimenti || []);
      save.hidden = false; save.disabled = false; save.textContent = 'Registra pagamento'; next.hidden = true;
      pending = null; error.textContent = ''; form.hidden = false;
      say('Saldo aggiornato. Compila il movimento e verifica i dati prima di registrare.');
      field('importo').focus();
    } catch (e) {
      if (ticket !== generation) return;
      say('Saldo non disponibile. ' + (e.name === 'AbortError' ? 'Tempo di attesa esaurito.' : e.message), true);
      retryDetail.hidden = false;
    }
  }
  search.addEventListener('input', e => {
    invalidate(); el('[data-clear]').hidden = !search.value;
    searchStatus.textContent = search.value.trim().length < 2 ? 'Digita almeno due caratteri.' : 'Ricerca…';
    if (!e.isComposing) timer = setTimeout(find, 300);
  });
  search.addEventListener('compositionend', () => { clearTimeout(timer); timer = setTimeout(find, 300); });
  search.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.isComposing) { e.preventDefault(); invalidate(); find(); }
  });
  el('[data-clear]').addEventListener('click', () => {
    search.value = ''; invalidate(); el('[data-clear]').hidden = true;
    searchStatus.textContent = 'Digita almeno due caratteri.'; search.focus();
  });
  retryDetail.addEventListener('click', () => { if (selected) choose(selected); });
  field('tipo').addEventListener('change', () => { if (field('tipo').value !== 'INCASSO') field('rata').value = 'NON_ASSEGNATO'; });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (busy || !selected) return;
    if (!pending) {
      for (const input of form.querySelectorAll('[aria-invalid]')) input.removeAttribute('aria-invalid');
      const invalid = [...form.elements].find(input => input.willValidate && !input.validity.valid);
      if (invalid) {
        invalid.setAttribute('aria-invalid', 'true');
        error.textContent = invalid.name === 'conferma' ? 'Conferma di aver verificato i dati.' : 'Controlla importo e data: usa un importo positivo con al massimo due decimali.';
        invalid.focus(); return;
      }
      pending = {registration_id:selected.id, request_id:crypto.randomUUID()};
      for (const name of ['importo','data','tipo','rata','metodo','riferimento','nota']) pending[name] = field(name).value;
    }
    busy = true; fields.disabled = true; searchFields.disabled = true; save.disabled = true;
    form.setAttribute('aria-busy','true'); error.textContent = ''; say('Registrazione in corso…');
    try {
      const data = await request('save', pending);
      if (!data.saved) {
        pending = null; fields.disabled = false; searchFields.disabled = false;
        error.textContent = data.message; say('Movimento non registrato. Correggi i dati oppure seleziona di nuovo la prenotazione per aggiornare il saldo.', true);
        field('importo').focus(); return;
      }
      document.dispatchEvent(new Event('mi:operational-saved'));
      pending = null; say(data.message); save.hidden = true;
      try { const updated=await request('detail',{registration_id:selected.id});const r=updated.saldo;el('[data-total]').textContent=money(r.totale);el('[data-paid]').textContent=money(r.versato);el('[data-balance]').textContent=money(r.residuo);showHistory(r.movimenti||[]); } catch(e) { say(data.message+' Saldo e storico richiedono un aggiornamento.'); }
       next.hidden = false; next.focus();
    } catch (e) {
      say('Esito non ricevuto. Mantieni aperta questa pagina e premi Riprova registrazione: lo stesso movimento non verrà duplicato. ' + (e.name === 'AbortError' ? 'Tempo di attesa esaurito.' : e.message), true);
      save.textContent = 'Riprova registrazione';
    } finally { busy = false; save.disabled = false; form.removeAttribute('aria-busy'); }
  });
  if(root.dataset.initialOrder){search.value=root.dataset.initialOrder;find();}
  next.addEventListener('click', () => {
    searchFields.disabled = false; fields.disabled = false; search.value = '';
    el('[data-clear]').hidden = true; invalidate(); form.reset();
    searchStatus.textContent = 'Digita almeno due caratteri.'; search.focus();
  });
});
