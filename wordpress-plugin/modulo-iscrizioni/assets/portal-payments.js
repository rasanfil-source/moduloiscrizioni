(() => {
function init(root) {
  if(root.dataset.ready)return;root.dataset.ready='1';
  const el = selector => root.querySelector(selector);
  const search = el('#mi-payment-search'), results = el('[data-results]');
  const status = el('[data-status]'), searchStatus = el('#mi-payment-search-status');
  const form = el('[data-payment-form]'), fields = el('[data-payment-fields]');
  const save = el('[data-save]'), next = el('[data-new]'), error = el('#mi-payment-error');
  const searchFields = el('[data-search-fields]'), retryDetail = el('[data-retry-detail]');
  const embedded=!!root.closest('[data-mi-management]');
  if(embedded){
    root.querySelectorAll(':scope > h2,:scope > p:not([data-status])').forEach(node=>node.remove());
    const heading=document.createElement('h3');heading.textContent='Inserisci un pagamento';fields.before(heading);
    const amount=form.elements.namedItem('importo');
    const highlight=()=>{amount.classList.remove('mi-amount-highlight');void amount.offsetWidth;amount.classList.add('mi-amount-highlight');};
    root.closest('details')?.addEventListener('toggle',e=>{if(e.target.open)highlight();});
    amount.classList.add('mi-amount-highlight');
  }
  const ids=new Map();root.querySelectorAll('[id]').forEach(element=>{const previous=element.id;const next=previous+'-'+crypto.randomUUID();ids.set(previous,next);element.id=next;});
  root.querySelectorAll('[for],[aria-describedby],[aria-labelledby]').forEach(element=>{for(const attribute of ['for','aria-describedby','aria-labelledby'])if(element.hasAttribute(attribute))element.setAttribute(attribute,element.getAttribute(attribute).split(/\s+/).map(id=>ids.get(id)||id).join(' '));});
  let generation = 0, timer, controller, selected = null, pending = null, busy = false, foundCount=0;
  const publishDraft=()=>{root.dataset.paymentDraft='1';searchFields.disabled=true;root.closest('[data-mi-management]')?.querySelectorAll('form').forEach(other=>{if(other!==form)other.querySelectorAll('input,select,textarea,button').forEach(control=>{if(!control.disabled){control.dataset.paymentLocked='1';control.disabled=true;}});});};
  form.addEventListener('input',publishDraft);form.addEventListener('change',publishDraft);
  window.addEventListener('beforeunload',e=>{if(root.isConnected&&(pending||busy||root.dataset.paymentDraft==='1')){e.preventDefault();e.returnValue='';}});
  const field = name => form.elements.namedItem(name);
  const unlock=()=>{delete root.dataset.paymentDraft;searchFields.disabled=false;root.closest('[data-mi-management]')?.querySelectorAll('[data-payment-locked]').forEach(control=>{control.disabled=false;delete control.dataset.paymentLocked;});};
  const discard=document.createElement('button');discard.type='button';discard.textContent='Svuota la bozza del movimento';form.querySelector('.mi-payment-actions').append(discard);
  discard.onclick=()=>{if(busy||pending){say('Verifica l’esito della registrazione prima di svuotare il modulo.',true);return;}form.reset();unlock();if(selected)choose(selected);};
  const money = cents => (Number(cents) / 100).toLocaleString('it-IT', {style:'currency', currency:'EUR'});
  document.addEventListener('click',e=>{if(root.isConnected&&!root.closest('[data-mi-management]')&&(pending||busy||root.dataset.paymentDraft==='1')&&e.target.closest('a')){e.preventDefault();e.stopImmediatePropagation();say('Completa la registrazione oppure svuota la bozza prima di cambiare pagina.',true);}},true);
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
  function showDeposit(r) {
      let deposit=el('[data-deposit-summary]');if(!deposit){deposit=document.createElement('p');deposit.dataset.depositSummary='1';el('[data-balance]').closest('.mi-payment-summary').append(deposit);}
      deposit.hidden=!r.deposit_plan;deposit.textContent=r.deposit_plan?'Caparra prevista '+money(r.deposit_due)+' · Ancora da coprire '+money(r.deposit_missing)+(r.deposit_covered&&r.residuo>0?' · Caparra coperta, saldo da completare':r.deposit_due===0?' · Nessuna caparra richiesta':''):'';
  }
  function showHistory(movements) {
    const host=el('[data-payment-history]');host.replaceChildren();
    if(embedded){host.hidden=true;return;}
    const title=document.createElement('h3');title.textContent='Movimenti registrati';host.append(title);
    if(!movements.length){const p=document.createElement('p');p.textContent='Nessun movimento registrato.';host.append(p);return;}
    const table=document.createElement('table'),head=table.createTHead().insertRow();
    for(const label of ['Data','Tipo','Importo','Metodo','Riferimento','Operatore','Nota']){const th=document.createElement('th');th.textContent=label;head.append(th);}
    const body=table.createTBody();
    for(const m of movements){const row=body.insertRow();for(const value of [new Date(m.data).toLocaleDateString('it-IT'),m.tipo,money(m.importo),m.metodo,m.riferimento,m.operatore,m.nota||''])row.insertCell().textContent=value;}
    host.append(table);
  }
  function invalidate() {
    clearTimeout(timer); controller?.abort(); generation++;
    selected = null; form.hidden = true; retryDetail.hidden = true; results.replaceChildren();
    say(''); error.textContent = '';
  }
  async function find(page=1) {
    const query = search.value.trim();
    if (query.length < 2) { searchStatus.textContent = 'Digita almeno due caratteri.'; return; }
    const ticket = generation;
    controller = new AbortController();
    searchStatus.textContent = 'Ricerca in corso…';
    try {
      const data = await request('search', {query,page,event_id:root.dataset.event||0}, controller.signal);
      if (ticket !== generation) return;
      if(page===1){results.replaceChildren();foundCount=0;}else results.querySelector('[data-more-payments]')?.remove();
      for (const p of data.prenotazioni) {
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'mi-payment-choice';
        const name = document.createElement('strong'), detail = document.createElement('small');
        name.textContent = p.nome; detail.textContent = p.evento + ' · ' + p.codice + (p.partecipanti?.length?' · Partecipanti: '+p.partecipanti.join(', '):'');
        button.append(name, detail); button.addEventListener('click', () => choose(p));
        results.append(button);
      }
      foundCount+=data.prenotazioni.length;
      if(data.has_more){const more=document.createElement('button');more.type='button';more.dataset.morePayments='1';more.textContent='Mostra altre 30 prenotazioni';more.onclick=()=>{more.disabled=true;find(page+1).finally(()=>{more.disabled=false;});};results.append(more);}
      searchStatus.textContent = foundCount ? foundCount + ' prenotazioni caricate.' : 'Nessuna prenotazione trovata nelle iniziative a cui hai accesso.';
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
      showDeposit(r);
      form.reset(); fields.disabled = false; field('data').value = data.data; field('rata').value = 'NON_ASSEGNATO';
      showHistory(r.movimenti || []);
      save.hidden = false; save.disabled = false; save.textContent = 'Registra pagamento'; next.hidden = true;
      pending = null; error.textContent = ''; form.hidden = false;
      say(embedded?'':'Saldo aggiornato. Compila il movimento e verifica i dati prima di registrare.');
      if(!root.dataset.registrationId)field('importo').focus();
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
        pending = null; fields.disabled = false; searchFields.disabled = true;
        error.textContent = data.message; say('Movimento non registrato. Correggi i dati oppure seleziona di nuovo la prenotazione per aggiornare il saldo.', true);
        field('importo').focus(); return;
      }
      document.dispatchEvent(new Event('mi:operational-saved'));
      pending = null; unlock(); say(data.message); save.hidden = true;
      try { const updated=await request('detail',{registration_id:selected.id});const r=updated.saldo;el('[data-total]').textContent=money(r.totale);el('[data-paid]').textContent=money(r.versato);el('[data-balance]').textContent=money(r.residuo);showHistory(r.movimenti||[]);showDeposit(r);root.dispatchEvent(new CustomEvent('mi:payment-updated',{bubbles:true,detail:r})); } catch(e) { say(data.message+' Saldo e storico richiedono un aggiornamento.');root.dispatchEvent(new CustomEvent('mi:payment-updated',{bubbles:true,detail:null})); }
       next.hidden = false; next.focus();
    } catch (e) {
      say('Esito non ricevuto. Mantieni aperta questa pagina e premi Riprova registrazione: lo stesso movimento non verrà duplicato. ' + (e.name === 'AbortError' ? 'Tempo di attesa esaurito.' : e.message), true);
      save.textContent = 'Riprova registrazione';
    } finally { busy = false; save.disabled = false; form.removeAttribute('aria-busy'); }
  });
  if(root.dataset.registrationId){searchFields.hidden=true;choose({id:Number(root.dataset.registrationId),nome:root.dataset.buyer,codice:root.dataset.order});}else if(root.dataset.initialOrder){search.value=root.dataset.initialOrder;find();}
  next.addEventListener('click', () => {
    if(root.dataset.registrationId){choose({id:Number(root.dataset.registrationId),nome:root.dataset.buyer,codice:root.dataset.order});return;}
    searchFields.disabled = false; fields.disabled = false; search.value = '';
    el('[data-clear]').hidden = true; invalidate(); form.reset();
    searchStatus.textContent = 'Digita almeno due caratteri.'; search.focus();
  });
}
const scan=()=>document.querySelectorAll('[data-mi-payments]').forEach(init);
document.addEventListener('DOMContentLoaded',()=>{scan();new MutationObserver(scan).observe(document.body,{childList:true,subtree:true});});
})();
