(() => {
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = n => (Number(n || 0)/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'});
  async function ask(message,action='Continua') {
    const previous=document.activeElement,dialog=document.createElement('dialog');dialog.className='mi-management-confirm';
    dialog.innerHTML='<form method="dialog" novalidate><h2>Conferma operazione</h2><p></p><button value="cancel" autofocus>Torna indietro</button> <button value="accept"></button></form>';
    dialog.querySelector('p').textContent=message;dialog.querySelector('[value=accept]').textContent=action;
    const title='mi-confirm-'+crypto.randomUUID();dialog.querySelector('h2').id=title;dialog.setAttribute('aria-labelledby',title);
    document.body.append(dialog);dialog.showModal();
    return new Promise(resolve=>dialog.addEventListener('close',()=>{const accepted=dialog.returnValue==='accept';dialog.remove();previous?.focus();resolve(accepted);},{once:true}));
  }
  function init(root) {
    if(root.dataset.ready)return;root.dataset.ready='1';
    const content=root.querySelector('[data-management-content]'),status=root.querySelector('[data-management-status]'),select=root.querySelector('[data-event-select]');
    let event=Number(root.dataset.event)>0?root.dataset.event:'',order=root.dataset.order,booking=null,busy=false,generation=0,pending=null,dirty=false;
    const periodSelect=root.querySelector('[data-period-select]'),eventOptions=[...select.options].map(option=>option.cloneNode(true));
    let period=periodSelect?.value||'current';
    const filterEvents=()=>{if(!periodSelect)return;select.replaceChildren(...eventOptions.filter(option=>!option.value||option.dataset.period===period).map(option=>option.cloneNode(true)));select.value=event;};
    filterEvents();
    const updateLocation=()=>{const url=new URL(location.href);url.searchParams.set('mi_portal_period',period);url.searchParams.set('mi_portal_event',event);url.searchParams.delete('mi_order');url.searchParams.delete('mi_sheet_sync');history.replaceState(null,'',url);};
    if(periodSelect)periodSelect.onchange=async()=>{const next=periodSelect.value;if(!await canLeave()){periodSelect.value=period;return;}period=next;event='';filterEvents();updateLocation();await summary();if(select.options.length===1)say(period==='past'?'Non ci sono eventi passati.':'Non ci sono eventi attivi.');};
    const say=t=>status.textContent=t;
    async function request(operation,data={}) {
      const abort=new AbortController(),timeout=setTimeout(()=>abort.abort(),90000);
      try{const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal:abort.signal,body:new URLSearchParams({action:'mi_portal_management',nonce:root.dataset.nonce,event_id:event,order_code:order,operation,...data})});
      const json=await response.json();if(!response.ok||!json.success)throw new Error(json.data?.message||'Operazione non riuscita.');
      const sheetLink=root.querySelector('[data-open-sheet]');
      if(sheetLink){const url=json.data?.sheet_url||'';sheetLink.hidden=!/^https:\/\/docs\.google\.com\/spreadsheets\//.test(url);if(!sheetLink.hidden)sheetLink.href=url;else sheetLink.removeAttribute('href');}
      if(['participant','room_save','room_delete','cancel','sheet_save'].includes(operation)&&json.data?.saved!==false)document.dispatchEvent(new Event('mi:operational-saved'));
      return json.data;}finally{clearTimeout(timeout);}
    }
    content.addEventListener('input',e=>{e.target.removeAttribute('aria-invalid');if(e.target.closest('form'))dirty=true;});
    window.addEventListener('beforeunload',e=>{if(root.isConnected&&(dirty||pending||busy)){e.preventDefault();e.returnValue='';}});
    document.addEventListener('click',e=>{
      if(!root.isConnected||(!dirty&&!pending&&!busy))return;
      const target=e.target.closest('a,[data-mi-portal-booking-close],[data-mi-portal-booking-next],[data-mi-portal-booking-previous]');
      if(!target)return;e.preventDefault();e.stopImmediatePropagation();
      if(busy||pending){say('Completa o verifica il salvataggio prima di uscire.');return;}
      ask('Le modifiche non salvate verranno perse.','Esci senza salvare').then(ok=>{if(ok){dirty=false;target.click();}});
    },true);
    document.addEventListener('keydown',e=>{if(root.isConnected&&(dirty||pending||busy)&&e.key==='Escape'&&!document.querySelector('dialog[open]')){e.preventDefault();e.stopImmediatePropagation();say('Salva le modifiche oppure usa il comando di chiusura.');}},true);
    async function canLeave(){if(pending||busy){say('Completa o verifica il salvataggio prima di cambiare pagina.');return false;}if(dirty&&!await ask('Le modifiche non salvate verranno perse.','Esci senza salvare'))return false;dirty=false;return true;}
    async function summary(){
      if(!await canLeave())return;const ticket=++generation;order='';booking=null;content.replaceChildren();const sheetLink=root.querySelector('[data-open-sheet]');if(sheetLink){sheetLink.hidden=true;sheetLink.removeAttribute('href');}if(!event){say('Scegli un evento.');return;}say('Caricamento riepilogo…');
      try{const data=await request('summary');if(ticket!==generation)return;
        const active=data.items.filter(x=>x.active),sum=k=>active.reduce((n,x)=>n+x[k],0);
        content.innerHTML=`<div class="mi-management-totals"><button data-filter="all">${active.length} prenotazioni · ${sum('participants')} partecipanti</button><button data-filter="balance">${money(sum('balance'))} da incassare</button><button data-filter="missing">${sum('missing')} partecipanti con dati mancanti</button><button data-filter="unassigned">${sum('unassigned')} senza camera</button></div><p>Incassato: <strong>${money(sum('paid'))}</strong> · Totale prenotazioni attive: ${money(sum('total'))}</p><p><a href="${esc(data.registration_url||'#')}" target="_blank" rel="noopener">Inserisci una nuova iscrizione</a></p><label>Cerca nome o codice <input type="search" data-query></label><button data-clear-query>Cancella ricerca</button><div data-list></div>`;
        let filter='all',shown=30;const draw=()=>{const query=content.querySelector('[data-query]').value.toLocaleLowerCase('it');const all=data.items.filter(x=>(filter==='all'||(x.active&&x[filter]>0))&&(x.name+' '+x.code).toLocaleLowerCase('it').includes(query));const list=all.slice(0,shown);
          content.querySelector('[data-list]').innerHTML=`<table><thead><tr><th>Prenotazione</th><th>Referente</th><th>Stato</th><th>Versato</th><th>Residuo</th><th></th></tr></thead><tbody>${list.map(x=>`<tr><td>${esc(x.code)}</td><td>${esc(x.name)}</td><td>${esc(({PENDING_PAYMENT:'Da pagare',WAITLISTED:'Lista d’attesa',WAITLIST_OFFERED:'Posto proposto',CONFIRMED:'Confermata',PENDING:'In attesa',CANCELLED:'Annullata',EXPIRED:'Scaduta',WAITLIST:'Lista di attesa'})[x.status]||x.status)}</td><td>${money(x.paid)}</td><td>${money(x.balance)}</td><td><button data-open="${esc(x.code)}">Gestisci</button></td></tr>`).join('')}</tbody></table><p>${list.length} di ${all.length} prenotazioni</p>${all.length>shown?'<button data-more>Mostra altre 30</button>':''}`;const more=content.querySelector('[data-more]');if(more)more.onclick=()=>{shown+=30;draw();};};
        content.querySelectorAll('[data-filter]').forEach(b=>b.onclick=()=>{filter=b.dataset.filter;shown=30;draw();});content.querySelector('[data-clear-query]').onclick=()=>{const q=content.querySelector('[data-query]');q.value='';shown=30;draw();q.focus();};content.querySelector('[data-query]').oninput=()=>{shown=30;draw();};draw();say('Aggiornato: '+new Date(data.updated_at).toLocaleString('it-IT'));
      }catch(e){if(ticket===generation)say('Riepilogo non disponibile. '+e.message);}
    }
    async function syncSheet(){
      if(!await canLeave())return;if(!event){say('Scegli un evento.');return;}
      const ticket=++generation;busy=true;select.disabled=true;content.replaceChildren();say('Lettura delle modifiche nel foglio Google…');
      try{
        const result=await request('sheet_changes');if(ticket!==generation)return;
        if(result.errors?.length){say(result.errors.join(' '));return;}
        const changes=result.changes||[];
        if(!changes.length){say('Il foglio non contiene modifiche da sincronizzare.');return;}
        content.innerHTML=`<h3>Modifiche dal foglio Google</h3><p>${changes.length} celle da sincronizzare. In caso di conflitto nessuna modifica verrà applicata.</p><table><thead><tr><th>Prenotazione</th><th>Partecipante</th><th>Campo</th><th>Prima</th><th>Dopo</th></tr></thead><tbody>${changes.map(c=>`<tr><td>${esc(c.order_code)}</td><td>${esc(c.number)}</td><td>${esc(c.key)}</td><td>${esc(c.before)}</td><td>${esc(c.after)}</td></tr>`).join('')}</tbody></table><button type="button" data-apply-sheet>Sincronizza ${changes.length} celle</button>`;
        const button=content.querySelector('[data-apply-sheet]');
        button.onclick=async()=>{
          if(busy)return;busy=true;button.disabled=true;select.disabled=true;
          pending=pending||{operation:'sheet_save',data:JSON.stringify(changes),request_id:crypto.randomUUID()};say('Sincronizzazione…');
          try{
            const saved=await request('sheet_save',pending);
            if(saved.saved===false){if(saved.rejected){pending=null;say(saved.message);button.textContent='Rileggi le modifiche';button.onclick=syncSheet;return;}throw new Error(saved.message);}
            pending=null;busy=false;await summary();say(saved.message+' Il foglio si aggiornerà dopo la replica.');
          }catch(error){say(error.message);button.textContent='Riprova la stessa sincronizzazione';}
          finally{busy=false;button.disabled=false;select.disabled=false;}
        };
        say('Controlla le modifiche e premi Sincronizza.');
      }catch(error){say('Foglio non disponibile. '+error.message);}
      finally{busy=false;select.disabled=false;}
    }
    function input(key,label,value,type='text'){return `<label>${esc(label)}<input name="${esc(key)}" value="${esc(value)}" type="${['email','date','number','tel'].includes(type)?type:'text'}" maxlength="1000"></label>`;}
    function fieldInput(f,value){
      const name='field:'+f.key,label=f.label+(f.required?' (richiesto)':'');
      if(['select','yesno'].includes(f.type)){const options=f.options||[];const choices=options.includes(value)||!value?options:[value,...options];return '<label>'+esc(label)+'<select name="'+esc(name)+'"><option value="">Non indicato</option>'+choices.map(v=>'<option value="'+esc(v)+'" '+(String(v)===String(value)?'selected':'')+'>'+esc(v)+'</option>').join('')+'</select></label>';}
      if(f.type==='textarea')return '<label>'+esc(label)+'<textarea name="'+esc(name)+'" maxlength="1000">'+esc(value)+'</textarea></label>';
      return input(name,label,value,f.type);
    }
    async function detail(code){
      if(!await canLeave())return;const ticket=++generation;order=code;content.replaceChildren();say('Caricamento scheda…');
      try{const b=await request('detail');if(ticket!==generation)return;booking=b;dirty=false;select.disabled=false;document.title=b.event_title+' — Gestione prenotazione';
        content.innerHTML=`<button data-back>Torna al riepilogo</button><h3>${esc(b.buyer.first_name)} ${esc(b.buyer.last_name)} · ${esc(b.order_code)}</h3><p>Totale ${money(b.total_cents)} · Versato ${money(b.paid_cents)} · Residuo ${money(b.balance_cents)}</p>${b.payment_url?`<p><a class="mi-primary" href="${esc(b.payment_url)}">Registra un pagamento</a></p>`:''}<details open><summary>Storico movimenti</summary><table><thead><tr><th>Data</th><th>Tipo</th><th>Importo</th><th>Metodo</th><th>Riferimento</th><th>Operatore</th></tr></thead><tbody>${b.movements.map(m=>`<tr><td>${esc(new Date(m.data).toLocaleDateString('it-IT'))}</td><td>${esc(m.tipo)}</td><td>${money(m.importo)}</td><td>${esc(m.metodo)}</td><td>${esc(m.riferimento)}</td><td>${esc(m.operatore)}</td></tr>`).join('')||'<tr><td colspan="6">Nessun movimento registrato.</td></tr>'}</tbody></table></details>`;
        for(const p of b.participants){const definitions=new Map(b.fields.map(f=>[f.key,f]));Object.keys(p.fields).forEach(k=>{if(!definitions.has(k))definitions.set(k,{key:k,label:k});});
          content.insertAdjacentHTML('beforeend',`<form data-person="${p.number}" novalidate><fieldset ${p.status==='CANCELLED'?'disabled':''}><legend>${esc(p.first_name)} ${esc(p.last_name)}${p.status==='CANCELLED'?' — Annullato':''}</legend><div class="mi-management-grid">${input('first_name','Nome',p.first_name)}${input('last_name','Cognome',p.last_name)}${[...definitions.values()].map(f=>fieldInput(f,p.fields[f.key]??'')).join('')}<label>Camera<select name="room"><option value="">Non assegnata</option>${b.accommodations.map(r=>`<option value="${esc(r.code)}" ${p.room===r.code?'selected':''} ${r.available<1&&p.room!==r.code?'disabled':''}>${esc(r.name)} · ${r.available} posti liberi</option>`).join('')}</select></label></div><p>Per rimuovere un dato facoltativo, svuota il campo e salva.</p><button type="submit">Salva partecipante</button> <button type="button" data-cancel="${p.id}">Annulla partecipazione</button></fieldset></form>`);
        }
        content.insertAdjacentHTML('beforeend',`<details><summary>Gestisci camere</summary><p>Aggiungi una camera o modifica nome e capienza usando lo stesso codice.</p><form data-room novalidate>${input('code','Codice camera','')}${input('name','Nome camera','')}${input('capacity','Posti',1,'number')}<button>Salva camera</button></form><ul>${b.accommodations.map(r=>`<li>${esc(r.code)} — ${esc(r.name)}: ${r.occupied}/${r.capacity} <button data-delete-room="${esc(r.code)}" ${r.occupied?'disabled':''}>Elimina</button></li>`).join('')}</ul></details>`);
        say('Dati aggiornati. Le modifiche vengono salvate nel registro centrale.');if(b.pending){pending=b.pending;say('Un salvataggio interrotto può essere completato.');const resume=document.createElement('button');resume.textContent='Completa il salvataggio interrotto';resume.onclick=()=>mutate(pending.operation,JSON.parse(pending.data));status.append(' ',resume);}
      }catch(e){if(ticket===generation)say('Scheda non disponibile. '+e.message);}
    }
    async function mutate(operation,data){
      if(busy)return;busy=true;
      root.querySelectorAll('button,input,select,textarea').forEach(el=>{el.dataset.wasDisabled=el.disabled?'1':'0';el.disabled=true;});
      pending=pending||{operation,data:JSON.stringify(data),version:booking.version,request_id:crypto.randomUUID()};say('Salvataggio…');
      try{const result=await request(pending.operation,pending);if(result.saved===false){if(result.rejected){pending=null;say(result.message);return;}throw new Error(result.message);}pending=null;dirty=false;busy=false;await detail(order);say(result.message||'Modifica salvata.');}
      catch(e){say('Salvataggio non confermato: '+e.message);const retry=document.createElement('button');retry.textContent='Riprova lo stesso salvataggio';retry.onclick=()=>mutate(operation,data);status.append(' ',retry);const reload=document.createElement('button');reload.textContent='Ricarica la scheda';reload.onclick=()=>{pending=null;dirty=false;detail(order);};status.append(' ',reload);}
      finally{busy=false;select.disabled=false;root.querySelectorAll('[data-was-disabled]').forEach(el=>{el.disabled=el.dataset.wasDisabled==='1';delete el.dataset.wasDisabled;});}
    }
    content.addEventListener('submit',e=>{e.preventDefault();const f=e.target;const invalid=[...f.elements].find(x=>x.willValidate&&!x.validity.valid);if(invalid){invalid.setAttribute('aria-invalid','true');say('Controlla il formato del campo evidenziato.');invalid.focus();return;}const values=Object.fromEntries(new FormData(f));if(f.hasAttribute('data-person')){const fields={};for(const [k,v]of Object.entries(values))if(k.startsWith('field:'))fields[k.slice(6)]=v;mutate('participant',{number:Number(f.dataset.person),first_name:values.first_name,last_name:values.last_name,room:values.room,fields});}else if(f.hasAttribute('data-room'))mutate('room_save',{...values,capacity:Number(values.capacity)});});
    content.addEventListener('click',async e=>{const b=e.target.closest('button');if(!b||busy)return;if(b.dataset.open)detail(b.dataset.open);if(b.hasAttribute('data-back'))summary();if(b.dataset.deleteRoom&&await ask('Eliminare la camera '+b.dataset.deleteRoom+'? La camera è vuota.','Elimina camera'))mutate('room_delete',{code:b.dataset.deleteRoom});if(b.dataset.cancel&&await ask('Annullare la partecipazione? Eventuali rimborsi si registrano separatamente.','Annulla partecipazione')){busy=true;b.disabled=true;try{const result=await request('cancel',{participant_id:b.dataset.cancel});dirty=false;busy=false;await detail(order);say(result.message);}catch(err){say(err.message);}finally{busy=false;b.disabled=false;}}});
    select.onchange=async()=>{const next=select.value;if(!await canLeave()){select.value=event;return;}event=next;updateLocation();summary();};root.querySelector('[data-refresh]').onclick=()=>order?detail(order):summary();root.querySelector('[data-print]').onclick=()=>{root.querySelectorAll('.mi-print-value').forEach(el=>el.remove());root.querySelectorAll('form input,form select,form textarea').forEach(el=>{const span=document.createElement('span');span.className='mi-print-value';span.textContent=el.tagName==='SELECT'?(el.selectedOptions[0]?.textContent||''):el.value;el.after(span);});window.print();};
    root.querySelector('[data-sheet-sync]').onclick=syncSheet;
    if(root.querySelector('[data-sheet-sync]').dataset.auto==='1'&&event)syncSheet();else if(order&&event)detail(order);else summary();
  }
  const scan=()=>document.querySelectorAll('[data-mi-management]').forEach(init);
  document.addEventListener('DOMContentLoaded',()=>{scan();new MutationObserver(scan).observe(document.body,{childList:true,subtree:true});});
})();
