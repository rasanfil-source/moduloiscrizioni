(() => {
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const downloadCsv=(filename,rows)=>{const cell=value=>{let text=String(value??'');if(/^[=+@\-\t\r]/.test(text))text="'"+text;return '"'+text.replaceAll('"','""')+'"';};const url=URL.createObjectURL(new Blob(['\uFEFF'+rows.map(row=>row.map(cell).join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download=filename;link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);};
  const depositText=b=>b.deposit_plan?'Caparra prevista '+money(b.deposit_due)+' · Ancora da coprire '+money(b.deposit_missing)+(b.deposit_covered&&(b.balance??b.balance_cents)>0?' · Caparra coperta, saldo da completare':b.deposit_due===0?' · Nessuna caparra richiesta':''):'';
  const money = n => (Number(n || 0)/100).toLocaleString('it-IT',{style:'currency',currency:'EUR'});
  const emailContact = email => email ? '<a class="mi-contact-icon" href="mailto:'+esc(email)+'" title="Scrivi a '+esc(email)+'" aria-label="Scrivi a '+esc(email)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 5.5h17v13h-17zM4 6l8 6 8-6"/></svg></a>' : '';
  const phoneContact = phone => phone ? '<a class="mi-contact-icon" href="tel:'+esc(phone).replace(/[^+0-9]/g,'')+'" title="Chiama '+esc(phone)+'" aria-label="Chiama '+esc(phone)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3.5 4.7 5.8c-.7.7-.9 1.8-.5 2.7 2.1 5 5.9 8.8 10.9 10.9.9.4 2 .2 2.7-.5l2.3-2.3-3.5-3.5-2.2 1.5c-1.5-.8-2.8-2.1-3.6-3.6l1.5-2.2z"/></svg></a>' : '';
    async function ask(message,action='Continua') {
      const previous=document.activeElement,dialog=document.createElement('dialog');dialog.className='mi-management-confirm'+(/^(Annulla|Elimina|Rimuovi)/i.test(action)?' mi-management-confirm--danger':'');
    dialog.innerHTML='<form method="dialog" novalidate><h2>Conferma operazione</h2><p></p><button value="cancel" autofocus>Torna indietro</button> <button value="accept"></button></form>';
    dialog.querySelector('p').textContent=message;dialog.querySelector('[value=accept]').textContent=action;
    const title='mi-confirm-'+crypto.randomUUID();dialog.querySelector('h2').id=title;dialog.setAttribute('aria-labelledby',title);
    document.body.append(dialog);dialog.showModal();
    return new Promise(resolve=>dialog.addEventListener('close',()=>{const accepted=dialog.returnValue==='accept';dialog.remove();previous?.focus();resolve(accepted);},{once:true}));
  }
  function init(root) {
    if(root.dataset.ready)return;root.dataset.ready='1';
    const content=root.querySelector('[data-management-content]'),status=root.querySelector('[data-management-status]'),select=root.querySelector('[data-event-select]');
    let event=Number(root.dataset.event)>0?root.dataset.event:'',order=root.dataset.order,booking=null,busy=false,generation=0,pending=null,dirty=false,dirtyForm=null;
    const periodSelect=root.querySelector('[data-period-select]'),eventOptions=[...select.options].map(option=>option.cloneNode(true));
    let period=periodSelect?.value||'current';
    let printList=null,listResize=null;
    const annualPanel=root.querySelector('[data-annual-report]'),eventActions=root.querySelector('[data-event-actions]');
    const printButton=root.querySelector('[data-print]');
    const parkPanels=()=>{if(annualPanel){annualPanel.hidden=true;root.append(annualPanel);}if(eventActions){if(printButton)eventActions.append(printButton);eventActions.hidden=!event;root.insertBefore(eventActions,content);}};
    let listContext={query:'',filter:'all',state:'',orderService:'',requests:'',deadline:'',room:'',service:'',sort:'name',view:'people',shown:30},listEvent=event;
    const filterEvents=()=>{if(!periodSelect)return;select.replaceChildren(...eventOptions.filter(option=>!option.value||option.dataset.period===period).map(option=>option.cloneNode(true)));select.value=event;};
    filterEvents();
    const updateLocation=()=>{const url=new URL(location.href);url.searchParams.set('mi_portal_period',period);url.searchParams.set('mi_portal_event',event);url.searchParams.delete('mi_order');url.searchParams.delete('mi_sheet_sync');history.replaceState(null,'',url);};
    if(periodSelect)periodSelect.onchange=async()=>{const next=periodSelect.value;if(!await canLeave()){periodSelect.value=period;return;}period=next;event='';filterEvents();updateLocation();await summary();if(select.options.length===1)say(period==='past'?'Non ci sono eventi passati.':'Non ci sono eventi attivi.');};
    const say=t=>{const text=String(t);status.textContent=text;status.classList.toggle('mi-management-updated',text.startsWith('Aggiornato: '));status.classList.toggle('mi-management-status--error',/(non disponibile|non confermato|non riuscita|errore|impossibile|riprova|controlla)/i.test(text));status.classList.toggle('mi-management-status--busy',/(caricamento|salvataggio|calcolo|sincronizzazione|aggiornamento)/i.test(text));};
    async function request(operation,data={}) {
      const abort=new AbortController(),timeout=setTimeout(()=>abort.abort(),90000);
      try{const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',signal:abort.signal,body:new URLSearchParams({action:'mi_portal_management',nonce:root.dataset.nonce,event_id:event,order_code:order,operation,...data})});
      const json=await response.json();if(!response.ok||!json.success)throw new Error(json.data?.message||'Operazione non riuscita.');
      const sheetLink=root.querySelector('[data-open-sheet]');
      if(sheetLink&&Object.hasOwn(json.data||{},'sheet_url')){const url=json.data?.sheet_url||'';sheetLink.hidden=!/^https:\/\/docs\.google\.com\/spreadsheets\//.test(url);if(!sheetLink.hidden)sheetLink.href=url;else sheetLink.removeAttribute('href');}
      if(['participant','room_save','room_delete','change_options','identity_link','adjust_due','attendance','request_review','cancel','sheet_save','room_swap','room_assign','event_room_save','event_room_delete'].includes(operation)&&json.data?.saved!==false)document.dispatchEvent(new Event('mi:operational-saved'));
      return json.data;}finally{clearTimeout(timeout);}
    }
    const paymentDraft=()=>!!content.querySelector('[data-payment-draft="1"]');
    root.addEventListener('mi:before-booking-navigation',e=>{if(dirty||pending||busy||paymentDraft()){e.preventDefault();say('Salva le modifiche oppure scarta la bozza prima di uscire dalla scheda.');}});
    const annualButton=root.querySelector('[data-load-annual]');
    if(annualButton)annualButton.onclick=async()=>{
      const annualStatus=root.querySelector('[data-annual-status]'),host=root.querySelector('[data-annual-results]');
      const group=root.querySelector('[data-annual-group]').value,year=root.querySelector('[data-annual-year]'),minimum=root.querySelector('[data-annual-minimum]');
      if(!group||!year.checkValidity()||!minimum.checkValidity()){annualStatus.textContent='Scegli gruppo, anno e numero minimo valido.';return;}
      annualButton.disabled=true;annualStatus.textContent='Caricamento delle presenze registrate…';
      try{const result=await request('annual_report',{group_id:group,year:year.value,minimum:minimum.value});host.innerHTML='<table><thead><tr><th>Persona (nomi nelle iscrizioni collegate)</th><th>Eventi frequentati</th><th>Eventi</th><th>Iscrizioni di riferimento</th></tr></thead><tbody>'+result.items.map(person=>'<tr><td>'+esc(person.names.join(' / '))+'</td><td>'+person.count+'</td><td>'+person.events.map(esc).join('<br>')+'</td><td>'+person.records.map(record=>esc(record.code)+' · '+esc(record.name)+' (#'+record.id+')').join('<br>')+'</td></tr>').join('')+'</tbody></table>';annualStatus.textContent=result.items.length+' persone con almeno '+result.minimum+' eventi nel '+result.year+'. '+result.unrecorded+' iscrizioni individuali senza presenza rilevata. Le presenze sono riunite in base al cellulare personale.';const exportButton=document.createElement('button');exportButton.type='button';exportButton.textContent='Esporta rapporto annuale CSV';exportButton.onclick=()=>downloadCsv('presenze-'+result.year+'-gruppo-'+group+'.csv',[['Persona','Eventi frequentati','Eventi','Riferimenti'],...result.items.map(person=>[person.names.join(' / '),person.count,person.events.join(' · '),person.records.map(record=>record.code+' #'+record.id).join(' · ')])]);host.append(exportButton);}catch(error){host.replaceChildren();annualStatus.textContent='Rapporto non disponibile. '+error.message;}finally{annualButton.disabled=false;}
    };
    const resolveDraft=()=>new Promise(resolve=>{const dialog=document.createElement('dialog');dialog.className='mi-management-confirm';dialog.innerHTML='<form method="dialog" novalidate><h2>Modifiche non salvate</h2><p>Vuoi salvare le modifiche prima di proseguire?</p><button value="stay" autofocus>Continua a modificare</button> <button value="discard">Annulla modifiche</button> <button value="save">Salva</button></form>';dialog.addEventListener('close',()=>{const choice=dialog.returnValue||'stay';dialog.remove();resolve(choice);});document.body.append(dialog);dialog.showModal();});
    const trackEdit=e=>{
      if(e.target.closest('[data-mi-payments]'))return;
      e.target.removeAttribute('aria-invalid');const edited=e.target.closest('form');if(!edited)return;dirty=true;dirtyForm=edited;
      say('Modifiche non salvate. Salva il modulo quando hai concluso.');
    };
    content.addEventListener('mi:payment-updated',e=>{const summary=content.querySelector('[data-booking-economics]');if(summary)summary.textContent=e.detail?'Totale '+money(e.detail.totale)+' · Versato '+money(e.detail.versato)+' · Residuo '+money(e.detail.residuo):'Movimento registrato. Aggiorna la scheda per verificare il saldo.';const history=content.querySelector('[data-booking-history]');if(history)history.remove();const deposit=content.querySelector('[data-booking-deposit]');if(deposit&&e.detail)deposit.textContent=depositText({...e.detail,balance:e.detail.residuo});});
    content.addEventListener('input',trackEdit);content.addEventListener('change',trackEdit);
    window.addEventListener('beforeunload',e=>{if(root.isConnected&&(dirty||pending||busy)){e.preventDefault();e.returnValue='';}});
    document.addEventListener('click',async e=>{
      if(!root.isConnected||(!dirty&&!pending&&!busy&&!paymentDraft()))return;
      const target=e.target.closest('a,[data-mi-portal-booking-close],[data-mi-portal-booking-next],[data-mi-portal-booking-previous],[data-open],[data-edit-inventory]');
      if(!target)return;e.preventDefault();e.stopImmediatePropagation();
      if(busy||pending||paymentDraft()){say('Completa o verifica il salvataggio prima di uscire.');return;}
      const choice=await resolveDraft();if(choice==='save'){dirtyForm?.requestSubmit();return;}if(choice==='discard'){dirty=false;dirtyForm=null;target.click();}
    },true);
    document.addEventListener('keydown',e=>{if(root.isConnected&&(dirty||pending||busy||paymentDraft())&&(e.key==='Escape'||(['ArrowLeft','ArrowRight'].includes(e.key)&&!e.target.matches('input,textarea,select')))&&!document.querySelector('dialog[open]')){e.preventDefault();e.stopImmediatePropagation();say('Salva le modifiche oppure usa il comando di chiusura.');}},true);
    async function canLeave(){if(paymentDraft()){say('Completa il movimento in corso prima di uscire dalla scheda.');return false;}if(pending||busy){say('Completa o verifica il salvataggio prima di cambiare pagina.');return false;}if(dirty){const choice=await resolveDraft();if(choice==='save'){dirtyForm?.requestSubmit();return false;}if(choice!=='discard')return false;}dirty=false;dirtyForm=null;return true;}
    async function summary(){
      if(!await canLeave())return;const ticket=++generation;order='';booking=null;printList=null;parkPanels();content.replaceChildren();const sheetLink=root.querySelector('[data-open-sheet]');if(sheetLink){sheetLink.hidden=true;sheetLink.removeAttribute('href');}if(!event){say('Scegli un evento.');return;}say('Caricamento riepilogo…');
      try{const data=await request('summary');if(ticket!==generation)return;
        const active=data.items.filter(x=>x.active),sum=k=>active.reduce((n,x)=>n+Number(x[k]||0),0);
        const people=state=>data.items.filter(x=>x.status===state).reduce((n,x)=>n+x.participants,0);
        const receivable=data.items.filter(x=>x.collectible??['CONFIRMED','PENDING_PAYMENT'].includes(x.status)).reduce((n,x)=>n+x.balance,0);
        const netPaid=data.items.reduce((n,x)=>n+x.paid,0);
        const features=data.features||{rooms:true,payments:true,deposit:true};

        const summaryRow=(label,value,attention=false)=>'<tr'+(attention?' class="mi-summary-attention"':'')+'><th scope="row">'+label+'</th><td>'+value+'</td></tr>';
        const summaryGroup=(title,rows)=>'<tbody><tr class="mi-summary-section"><th scope="rowgroup" colspan="2">'+title+'</th></tr>'+rows+'</tbody>';
        const summaryCard=(label,value,detail='',attention=false)=>'<article class="mi-summary-card'+(attention?' mi-summary-card--attention':'')+'"><span>'+label+'</span><strong>'+value+'</strong>'+(detail?'<small>'+detail+'</small>':'')+'</article>';
        const waitlistRows=(people('WAITLISTED')>0?summaryRow('Persone in lista d’attesa',people('WAITLISTED')):'')+(people('WAITLIST_OFFERED')>0?summaryRow('Persone con posto proposto',people('WAITLIST_OFFERED')):'');
        const registeredPeople=people('CONFIRMED')+people('PENDING_PAYMENT');
        const settledPeople=data.items.filter(x=>['CONFIRMED','PENDING_PAYMENT'].includes(x.status)&&x.balance<=0).reduce((n,x)=>n+x.participants,0);
        const paidPeopleRows=summaryRow('Persone iscritte',registeredPeople)+(features.deposit&&people('CONFIRMED')>0?summaryRow('Caparra versata',people('CONFIRMED')):'')+(settledPeople>0?summaryRow(features.deposit?'Saldo versato':'Persone con pagamento completato',settledPeople):'');
        const peopleRows=(features.payments?paidPeopleRows:summaryRow('Persone confermate',people('CONFIRMED')))+waitlistRows;
        const economicRows=features.payments?summaryGroup('Importi',summaryRow('Da incassare',money(receivable),receivable>0)+'<tr data-net-paid><th scope="row">Versato netto<small>Esclusi rimborsi effettuati</small></th><td>'+money(netPaid)+'</td></tr>'):'';
        const qualityRows=(sum('missing')>0?summaryRow('Partecipanti con dati mancanti',sum('missing'),true):'')+(features.rooms?summaryRow('Partecipanti senza camera',sum('unassigned'),sum('unassigned')>0):'');
        const summaryCards=[summaryCard('Persone iscritte',registeredPeople,features.deposit?'Caparra versata: '+people('CONFIRMED'):'Persone confermate: '+people('CONFIRMED')),features.payments?summaryCard('Da incassare',money(receivable),'Versato netto: '+money(netPaid),receivable>0):'',features.rooms?summaryCard('Senza camera',sum('unassigned'),sum('unassigned')?'Da assegnare':'Nessuna persona',sum('unassigned')>0):'',summaryCard('Dati mancanti',sum('missing'),sum('missing')?'Da completare':'Nessuna azione',sum('missing')>0),waitlistRows?summaryCard('Lista d’attesa',people('WAITLISTED')+people('WAITLIST_OFFERED'),people('WAITLIST_OFFERED')?'Posti proposti: '+people('WAITLIST_OFFERED'):'Nessuna persona'):'' ].filter(Boolean).join('');
        content.innerHTML=`<section class="mi-management-summary" aria-label="Riepilogo evento"><h3>Riepilogo</h3><div class="mi-management-summary-cards">${summaryCards}</div></section><p data-new-registration><a class="mi-primary mi-new-registration" href="${esc(data.registration_url||'#')}" target="_blank" rel="noopener">Inserisci una nuova iscrizione</a></p><label>Cerca nome o codice <input type="search" data-query></label><button data-clear-query>Cancella ricerca</button><div data-list></div>`;
        const newRegistration=content.querySelector('[data-new-registration]');
        if(eventActions){eventActions.hidden=false;const actionBar=document.createElement('div');actionBar.className='mi-management-actionbar';newRegistration.before(actionBar);actionBar.append(eventActions,newRegistration);}
        if(!features.payments)content.querySelector('[data-net-paid]')?.setAttribute('hidden','');

        if(listEvent!==event){listContext={query:'',filter:'all',state:'',requests:'',deadline:'',room:'',service:'',sort:'name',view:'people',shown:30};listEvent=event;}
        listContext.view='people';listContext.orderService='';
        const states={CONFIRMED:'Partecipante',PENDING_PAYMENT:'Pagamento atteso',WAITLISTED:'Lista d’attesa',WAITLIST_OFFERED:'Posto proposto',CANCELLED:'Annullata',EXPIRED:'Scaduta'};
        const stateLabel=row=>{
          if(!features.payments||!['CONFIRMED','PENDING_PAYMENT'].includes(row.status))return states[row.status]||row.status;
          if(row.balance<=0)return 'Saldato';
          if(row.deposit_plan??features.deposit)return row.deposit_missing>0?'Caparra attesa':'Saldo atteso';
          return 'Pagamento atteso';
        };
        const stateClass=row=>({CONFIRMED:'confirmed',PENDING_PAYMENT:'pending',WAITLISTED:'waitlist',WAITLIST_OFFERED:'offered',CANCELLED:'cancelled',EXPIRED:'expired'}[row.status]||'neutral');
        const search=content.querySelector('[data-query]');search.value=listContext.query;
        if(listContext.filter==='balance')listContext.filter='all';
        const criticalOptions=[['all','Tutte le iscrizioni'],['missing','Dati mancanti'],...(features.rooms?[['unassigned','Senza camera']]:[])];
        content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<label>Manca qualcosa?<select data-critical-filter>'+criticalOptions.map(([value,label])=>'<option value="'+value+'">'+label+'</option>').join('')+'</select></label>');
        const criticalFilter=content.querySelector('[data-critical-filter]');
        if(listContext.deposit===undefined)listContext.deposit='';
        const paymentOptions=[['','Tutti gli iscritti'],...(features.deposit?[['none','Nessun versamento'],['partial','Caparra da completare'],['covered','Caparra versata']]:[['unpaid','Da saldare']]),['settled','Saldato']];
        content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<label>Stato<select data-deposit-filter>'+paymentOptions.map(([value,label])=>'<option value="'+value+'">'+label+'</option>').join('')+'</select></label>');
        const depositFilter=content.querySelector('[data-deposit-filter]');depositFilter.value=listContext.deposit||'';depositFilter.onchange=()=>{listContext.deposit=depositFilter.value;listContext.shown=30;draw();};
        listContext.state='';
        const utc=value=>{if(!value)return null;const date=new Date(value.includes('T')?value:value.replace(' ','T')+'Z');return Number.isNaN(date.getTime())?null:date;};
        const deadlineLabel=value=>utc(value)?.toLocaleString('it-IT')||'Non indicata';
        content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<div class="mi-management-grid"><label>Richieste particolari<select data-request-filter><option value="">Tutte le prenotazioni</option><option value="yes">Richieste particolari</option></select></label><label>Scadenza del posto proposto<select data-deadline-filter><option value="">Tutte le scadenze</option><option value="soon">Entro 24 ore</option><option value="expired">Termine trascorso</option></select></label><label>Servizio individuale<select data-service-filter><option value="">Tutti i servizi</option></select></label></div>');
        const requestFilter=content.querySelector('[data-request-filter]'),deadlineFilter=content.querySelector('[data-deadline-filter]'),serviceFilter=content.querySelector('[data-service-filter]');
        content.querySelector('[data-deposit-filter]').closest('label').hidden=!features.payments;
        listContext.room='';
        requestFilter.closest('label').hidden=!data.items.some(x=>String(x.requests||'').trim());
        deadlineFilter.closest('label').hidden=!data.items.some(x=>x.status==='WAITLIST_OFFERED');
        if(!features.rooms){listContext.room='';if(listContext.filter==='unassigned')listContext.filter='all';}
        if(!features.payments&&listContext.filter==='balance')listContext.filter='all';
        if(!features.payments)listContext.deposit='';
        requestFilter.value=listContext.requests;deadlineFilter.value=listContext.deadline;
        const requestMatch=x=>!listContext.requests||(String(x.requests||'').trim()&&(listContext.requests==='yes'||(listContext.requests==='reviewed'?x.requests_reviewed:!x.requests_reviewed)));
        const deadlineMatch=x=>{if(!listContext.deadline)return true;if(x.status!=='WAITLIST_OFFERED')return false;const deadline=utc(x.offer_expires_at);if(!deadline)return false;const remaining=deadline.getTime()-Date.now();return listContext.deadline==='expired'?remaining<=0:remaining>0&&remaining<=86400000;};
        const personLogistics=p=>(!listContext.room||(listContext.room==='unassigned'?!p.room:p.room===listContext.room.slice(5)))&&(!listContext.service||(p.options||[]).some(o=>(o.code||o.name)===listContext.service&&Number(o.quantity)>0));
        const admitted=(data.people||[]).filter(p=>['CONFIRMED','PENDING_PAYMENT'].includes(p.status));
        const rooms=data.rooms||[];
        booking={version:data.rooms_version||''};
        if(features.rooms&&Object.keys(data.room_types||{}).length){
          const types=data.room_types,planner=document.createElement('section');planner.className='mi-room-planner';planner.dataset.roomPlanner='';
          planner.innerHTML='<h3>Assegnazione camere</h3><label><select data-room-type aria-label="Tipo di sistemazione"><option value="">Tutte le sistemazioni</option>'+Object.entries(types).map(([code,type])=>'<option value="'+esc(code)+'">'+esc(type.name)+' ('+esc(type.prefix)+')</option>').join('')+'</select></label><div data-room-assignments></div>';
          content.querySelector('[data-new-registration]').after(planner);
          const typeSelect=planner.querySelector('[data-room-type]'),host=planner.querySelector('[data-room-assignments]');
          typeSelect.closest('label').insertAdjacentHTML('afterend','<label><select data-room-assignment-filter aria-label="Stato assegnazione camere"><option value="all">Tutti gli stati</option><option value="assigned">Assegnate</option><option value="unassigned">Da assegnare</option><option value="partial">Da riempire</option><option value="partial_unassigned">Da riempire e da assegnare</option></select></label>');
          const assignmentFilter=planner.querySelector('[data-room-assignment-filter]');
          const roomFilters=document.createElement('div');roomFilters.className='mi-room-filters';host.before(roomFilters);roomFilters.append(typeSelect.closest('label'),assignmentFilter.closest('label'));
          listContext.roomAssignment=listContext.roomAssignment||'all';assignmentFilter.value=listContext.roomAssignment;
          if(!types[listContext.roomType])listContext.roomType='';typeSelect.value=listContext.roomType;
          const renderAssignments=()=>{
            const selectedType=typeSelect.value;
            const assignedType=p=>p.room&&Object.entries(types).find(([,candidate])=>new RegExp('^'+candidate.prefix+'[1-9][0-9]{0,5}$').test(p.room));
            const requestedType=p=>(p.options||[]).find(o=>types[o.code]&&Number(o.quantity)>0)?.code||'';
            const typeFor=p=>assignedType(p)?.[0]||requestedType(p);
            const isPartial=p=>{const room=rooms.find(candidate=>candidate.code===p.room);return !!(room&&Number(room.occupied)<Number(room.capacity));};
            const persons=admitted.filter(p=>{const code=typeFor(p),state=assignmentFilter.value;return code&&(!selectedType||code===selectedType)&&(state==='all'||(state==='assigned'?!!p.room:state==='unassigned'?!p.room:state==='partial'?isPartial(p):(isPartial(p)||!p.room)));});
            if(!persons.length){host.innerHTML='<p>Nessuna persona corrisponde ai filtri scelti.</p>';return;}
            const groups=new Map();for(const person of persons){const typeCode=typeFor(person),key=typeCode+'|'+(person.room||'');if(!groups.has(key))groups.set(key,{typeCode,code:person.room||'',people:[]});groups.get(key).people.push(person);}
            const ordered=[...groups.values()].sort((a,b)=>{const typeOrder=types[a.typeCode].name.localeCompare(types[b.typeCode].name,'it');if(typeOrder)return typeOrder;const rank=group=>{const room=rooms.find(r=>r.code===group.code);return !group.code?1:(room&&Number(room.occupied)<Number(room.capacity)?0:2);},rankOrder=rank(a)-rank(b);if(rankOrder)return rankOrder;return !a.code?-1:!b.code?1:a.code.localeCompare(b.code,'it',{numeric:true});});
            let previousType='';host.innerHTML='<p>Assegna lo stesso numero alle persone che condividono una camera. I numeri già completi non sono accettati.</p><p class="mi-room-assignment-key"><span class="mi-room-key--unassigned">Senza camera</span><span class="mi-room-key--partial">Camera da completare</span></p><form data-room-assign-form novalidate><div class="mi-room-assignment-table" tabindex="0" role="region" aria-label="Assegnazioni camere"><table><thead><tr><th>Sistemazione</th><th>Numero</th><th>Persona</th></tr></thead><tbody>'+ordered.map((group,groupIndex)=>{
              const type=types[group.typeCode],room=rooms.find(r=>r.code===group.code),partial=room&&room.occupied<room.capacity;
              const heading=group.typeCode===previousType?'':'<tr class="mi-room-type-heading"><th colspan="3">'+esc(type.name)+'</th></tr>';previousType=group.typeCode;const availableNumbers=p=>{const matching=rooms.filter(candidate=>new RegExp('^'+type.prefix+'[1-9][0-9]{0,5}$').test(candidate.code)),used=matching.map(candidate=>Number(candidate.code.slice(type.prefix.length))),choices=matching.filter(candidate=>candidate.available>0||candidate.code===p.room).map(candidate=>Number(candidate.code.slice(type.prefix.length))),highest=Math.max(0,...used);let next=1;while(used.includes(next))next++;choices.push(next,highest+1);return [...new Set(choices)].sort((a,b)=>a-b);};const peopleRows=group.people.sort((a,b)=>a.name.localeCompare(b.name,'it')).map((p,index)=>{
                const match=(p.room||'').match(new RegExp('^'+type.prefix+'([1-9][0-9]{0,5})$')),number=match?match[1]:'';
                return '<tr class="mi-room-member mi-room-band-'+(groupIndex%2)+(partial?' mi-room-needs-occupant':'')+(!group.code?' mi-room-unassigned':'')+'" data-room-key="'+esc(group.code||'unassigned')+'"><td data-room-preview>'+esc(type.prefix+(number||'—'))+'</td><td><select data-room-person="'+p.id+'" data-room-type="'+esc(group.typeCode)+'" data-initial="'+number+'" aria-label="Numero '+esc(type.name)+' per '+esc(p.name)+'"><option value="">—</option>'+availableNumbers(p).map(candidate=>'<option value="'+candidate+'" '+(String(candidate)===number?'selected':'')+'>'+candidate+'</option>').join('')+'</select></td><td>'+esc(p.name)+'</td></tr>';
              }).join('');const vacancy=partial?'<tr class="mi-room-vacancy"><td colspan="3">'+(Number(room.capacity)-Number(room.occupied))+' '+(Number(room.capacity)-Number(room.occupied)===1?'posto libero':'posti liberi')+'</td></tr>':'';return heading+peopleRows+vacancy;
            }).join('')+'</tbody></table></div><p>Lascia il numero vuoto per togliere l’assegnazione. La sistemazione richiesta è il criterio dell’assegnazione.</p><div class="mi-room-assignment-actions"><button type="submit" class="mi-primary">Salva assegnazioni</button><button type="button" data-discard-room-assignments>Annulla</button></div></form>';
            const form=host.querySelector('form');
            const validateNumber=el=>{const type=types[el.dataset.roomType],number=el.value,target=number?type.prefix+Number(number):'',person=persons.find(p=>String(p.id)===el.dataset.roomPerson),room=rooms.find(r=>r.code===target),proposed=[...form.querySelectorAll('[data-room-person]')].filter(other=>{const otherPerson=persons.find(p=>String(p.id)===other.dataset.roomPerson);return other!==el&&other.dataset.roomType===el.dataset.roomType&&other.value===number&&otherPerson.room!==target;}).length,already=(room?admitted.filter(p=>p.room===target&&p.id!==person.id).length:0);el.setCustomValidity(number&&((type.individual&&already+proposed>0)||(!type.individual&&already+proposed>=Number(type.capacity)))?'Questo numero corrisponde a una camera già completa.':'');};
            form.onchange=e=>{if(!e.target.matches('[data-room-person]'))return;const input=e.target,type=types[input.dataset.roomType];validateNumber(input);input.closest('tr').querySelector('[data-room-preview]').textContent=input.value?type.prefix+input.value:'Da assegnare';};
            form.querySelector('[data-discard-room-assignments]').onclick=()=>{if(busy||pending)return;dirty=false;dirtyForm=null;content.querySelectorAll('[data-draft-locked]').forEach(el=>{el.disabled=false;delete el.dataset.draftLocked;});renderAssignments();say('Assegnazioni ripristinate. Nessuna modifica registrata.');};
            form.onsubmit=e=>{
              e.preventDefault();e.stopPropagation();if(busy||pending)return;
              form.querySelectorAll('[data-room-person]').forEach(validateNumber);const invalid=[...form.querySelectorAll('[data-room-person]')].find(el=>!el.checkValidity());if(invalid){invalid.reportValidity();return;}
              const changes=[];for(const el of form.querySelectorAll('[data-room-person]')){
                if(el.value===el.dataset.initial)continue;const person=persons.find(p=>String(p.id)===el.dataset.roomPerson);
                const type=types[el.dataset.roomType];changes.push({order_code:person.code,number:person.number,key:'room',before:person.room||'',after:el.value?type.prefix+Number(el.value):'',type:el.dataset.roomType});
              }
              if(!changes.length){dirty=false;dirtyForm=null;content.querySelectorAll('[data-draft-locked]').forEach(el=>{el.disabled=false;delete el.dataset.draftLocked;});say('Nessuna assegnazione da modificare.');return;}
              if(changes.length>500){say('Salva al massimo 500 assegnazioni alla volta.');return;}
              mutate('room_assign',changes);
            };
          };
          assignmentFilter.onchange=()=>{if(dirty||pending||busy){assignmentFilter.value=listContext.roomAssignment;say('Salva o scarta le assegnazioni prima di cambiare filtro.');return;}listContext.roomAssignment=assignmentFilter.value;renderAssignments();};
          typeSelect.onchange=()=>{if(dirty||pending||busy){typeSelect.value=listContext.roomType||'';say('Salva o scarta le assegnazioni prima di cambiare sistemazione.');return;}listContext.roomType=typeSelect.value;renderAssignments();};renderAssignments();
          if(!features.room_inventory){const demandNames={'alloggio-singola':['singola','singole'],'alloggio-doppia-separati':['doppia con letti separati','doppie con letti separati'],'alloggio-doppia-matrimoniale':['doppia matrimoniale','doppie matrimoniali'],'alloggio-tripla':['tripla','triple'],'alloggio-multipla':['multipla','multiple']};const demand=Object.entries(types).map(([code,type])=>{const people=admitted.filter(p=>(p.options||[]).some(o=>o.code===code&&Number(o.quantity)>0));if(!people.length)return '';const roomsNeeded=type.individual?people.length:Math.ceil(people.length/Number(type.capacity)),names=demandNames[code]||[String(type.name).toLowerCase(),String(type.name).toLowerCase()];return '<li><strong>'+roomsNeeded+' '+esc(names[roomsNeeded===1?0:1])+'</strong> ('+people.length+' '+(people.length===1?'persona':'persone')+')</li>';}).join('');if(demand)planner.insertAdjacentHTML('beforeend','<section class="mi-accommodation-demand"><h4>Riepilogo prenotazioni camere</h4><p>Numero indicativo di camere da prenotare, calcolato sulle preferenze raccolte.</p><ul>'+demand+'</ul></section>');}
          const changePanel=document.createElement('details');changePanel.dataset.accommodationChange='';
          changePanel.innerHTML='<summary>Cambia sistemazione</summary><p>Seleziona le persone a cui cambiare sistemazione.</p><form data-accommodation-form novalidate><label class="mi-accommodation-search">Cerca persona o sistemazione<input type="search" data-accommodation-search placeholder="Nome, cognome o sistemazione"></label><fieldset><legend>Elenco persone</legend>'+admitted.filter(p=>(p.options||[]).some(o=>String(o.code||'').startsWith('alloggio-')&&Number(o.quantity)>0)).map(p=>'<label class="mi-accommodation-person"><input type="checkbox" name="person" value="'+p.id+'"> '+esc(p.name)+' · '+esc(p.room||'da assegnare')+'</label>').join('')+'</fieldset><label>Nuova sistemazione<select name="type">'+Object.entries(types).map(([code,t])=>'<option value="'+esc(code)+'">'+esc(t.name)+'</option>').join('')+'</select></label><label>Numero della camera esistente (facoltativo)<input name="number" type="number" min="1" max="999999" step="1" placeholder="Nuova camera"></label><p>Per spostare le persone selezionate in una camera esistente, indica il suo numero. Lascia vuoto per creare una nuova camera: condivisa dalle persone selezionate per doppie e triple, con codici individuali per singole e camerate.</p><details data-accommodation-notes><summary>Annotazioni (facoltative)</summary><label>Annotazioni<textarea name="reason" maxlength="500"></textarea></label></details><button type="submit">Verifica il cambio</button> <button type="button" data-discard-accommodation>Annulla</button><div data-accommodation-preview aria-live="polite"></div></form>';
          planner.append(changePanel);
          const changeForm=changePanel.querySelector('form'),previewHost=changePanel.querySelector('[data-accommodation-preview]');let changePreview=null;
          const personGroups=new Map();
          for(const label of changeForm.querySelectorAll('.mi-accommodation-person')){
            const person=admitted.find(p=>String(p.id)===label.querySelector('input').value);
            const option=(person.options||[]).find(o=>types[o.code]&&Number(o.quantity)>0);
            const assigned=person.room&&Object.entries(types).find(([,type])=>new RegExp('^'+type.prefix+'[1-9][0-9]{0,5}$').test(person.room));
            const typeCode=assigned?.[0]||option?.code,title=types[typeCode]?.name||'Altra sistemazione',code=person.room||((types[typeCode]?.prefix||'')+'—');
            label.innerHTML='<input type="checkbox" name="person" value="'+person.id+'"><span class="mi-accommodation-person-code">'+esc(code)+'</span><span>'+esc(person.name)+'</span>';
            if(!personGroups.has(title))personGroups.set(title,[]);personGroups.get(title).push(label);
          }
          const peopleBox=changeForm.querySelector('fieldset');
          for(const [title,labels] of [...personGroups].sort(([a],[b])=>a.localeCompare(b,'it',{numeric:true}))){const heading=document.createElement('h4');heading.textContent=title;labels.forEach(label=>label.dataset.accommodationGroup=title);peopleBox.append(heading,...labels.sort((a,b)=>a.textContent.localeCompare(b.textContent,'it')));}
          const accommodationSearch=changeForm.querySelector('[data-accommodation-search]');accommodationSearch.oninput=()=>{const query=accommodationSearch.value.trim().toLocaleLowerCase('it');for(const label of peopleBox.querySelectorAll('.mi-accommodation-person'))label.hidden=!!query&&!((label.textContent+' '+label.dataset.accommodationGroup).toLocaleLowerCase('it').includes(query));for(const heading of peopleBox.querySelectorAll('h4')){let node=heading.nextElementSibling,visible=false;while(node&&!node.matches('h4')){if(!node.hidden)visible=true;node=node.nextElementSibling;}heading.hidden=!visible;}};
          const oldNumber=changeForm.elements.namedItem('number'),destination=document.createElement('select');destination.name='number';oldNumber.closest('label').firstChild.textContent='Camera di destinazione';oldNumber.replaceWith(destination);
          destination.closest('label').nextElementSibling.textContent='Scegli una camera consultando occupanti e posti disponibili, oppure crea una nuova camera con numero automatico.';
          const refreshDestinations=()=>{
            const previous=destination.value,type=types[changeForm.elements.namedItem('type').value];
            const selected=new Set([...changeForm.querySelectorAll('[name=person]:checked')].map(el=>el.value));
            destination.replaceChildren(new Option('Crea nuova camera (numero automatico)',''));
            for(const room of rooms){
              const match=room.code.match(new RegExp('^'+type.prefix+'([1-9][0-9]{0,5})$'));if(!match)continue;
              const occupants=admitted.filter(p=>p.room===room.code),leaving=occupants.filter(p=>selected.has(String(p.id))).length;
              const free=Math.max(0,Number(room.capacity)-Number(room.occupied)),needed=selected.size-leaving;
              const text=room.code+' — '+(occupants.map(p=>p.name).join(', ')||'Vuota')+' — '+(free?free+(free===1?' posto libero':' posti liberi'):'completa');
              const option=new Option(text,match[1]);option.disabled=(free===0&&leaving===0)||needed>free||(type.individual&&selected.size>1);destination.append(option);
            }
            if([...destination.options].some(o=>o.value===previous&&!o.disabled))destination.value=previous;
          };
          changeForm.addEventListener('change',e=>{if(e.target.name==='type'||e.target.name==='person')refreshDestinations();});refreshDestinations();
          const invalidate=()=>{changePreview=null;previewHost.replaceChildren();};changeForm.addEventListener('input',invalidate);changeForm.addEventListener('change',invalidate);
          changeForm.querySelector('[data-discard-accommodation]').onclick=()=>{if(busy||pending)return;changeForm.reset();refreshDestinations();invalidate();dirty=false;dirtyForm=null;content.querySelectorAll('[data-draft-locked]').forEach(el=>{el.disabled=false;delete el.dataset.draftLocked;});say('Cambio annullato. Nessuna modifica registrata.');};
          changeForm.onsubmit=async e=>{
            e.preventDefault();e.stopPropagation();if(busy||pending)return;
            if(!changeForm.reportValidity())return;
            const people=[...changeForm.querySelectorAll('[name=person]:checked')].map(el=>{const p=admitted.find(p=>String(p.id)===el.value);return {code:p.code,number:p.number};});
            if(!people.length){say('Seleziona almeno una persona.');return;}
            const payload={people,type:changeForm.elements.namedItem('type').value,number:changeForm.elements.namedItem('number').value,reason:changeForm.elements.namedItem('reason').value};
            busy=true;invalidate();const controls=[...changeForm.querySelectorAll('input,select,textarea,button')];controls.forEach(el=>el.disabled=true);say('Calcolo dell’anteprima…');
            try{
              const plan=await request('accommodation_preview',{data:JSON.stringify(payload)});if(plan.saved===false)throw new Error(plan.message);
              changePreview={payload,version:plan.version};
              previewHost.innerHTML='<h4>Anteprima del cambio</h4><ul>'+plan.people.map(p=>'<li>'+esc(p.name)+' · '+esc(p.code)+': '+esc(p.before_type)+' → '+esc(p.after_type)+'; '+esc(p.before_room||'senza codice')+' → <strong>'+esc(p.after_room)+'</strong></li>').join('')+'</ul><div class="mi-room-assignment-table" tabindex="0" role="region" aria-label="Variazione importi"><table><thead><tr><th>Iscrizione</th><th>Dovuto prima</th><th>Dovuto dopo</th><th>Differenza</th><th>Versato</th><th>Da incassare</th><th>Da restituire</th></tr></thead><tbody>'+plan.orders.map(o=>'<tr><th scope="row">'+esc(o.code)+'</th><td>'+money(o.before_total)+'</td><td>'+money(o.after_total)+'</td><td>'+money(o.delta)+'</td><td>'+money(o.paid)+'</td><td>'+money(o.due)+'</td><td>'+money(o.refund)+'</td></tr>').join('')+'</tbody></table></div><p>La conferma aggiorna sistemazione, camera e dovuto insieme. Non registra pagamenti né rimborsi. La caparra già prevista resta invariata, entro il nuovo totale.</p><button type="button" class="mi-primary" data-confirm-accommodation>Conferma cambio e importi</button>';
              previewHost.querySelector('[data-confirm-accommodation]').onclick=()=>{if(!changePreview||busy||pending)return;pending={operation:'change_accommodation',data:JSON.stringify(changePreview.payload),preview_version:changePreview.version,request_id:crypto.randomUUID()};mutate('change_accommodation',changePreview.payload);};
              say('Anteprima pronta. Verifica camere e importi prima di confermare.');
            }catch(error){say('Anteprima non disponibile. '+error.message);}finally{busy=false;controls.forEach(el=>el.disabled=false);}
          };
        }
        content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<details data-room-inventory><summary>Gestisci inventario camere</summary><p>Per modificare una camera usa il suo codice. La capienza deve comprendere tutti gli occupanti già assegnati.</p><form data-room novalidate>'+input('code','Codice camera','')+input('name','Nome camera','')+input('capacity','Posti',1,'number')+'<button type="submit">Salva camera</button></form><ul>'+rooms.map(r=>'<li>'+esc(r.code)+' — '+esc(r.name)+': '+r.occupied+'/'+r.capacity+' <button data-edit-inventory="'+esc(r.code)+'">Modifica</button> <button class="mi-danger" data-delete-room="'+esc(r.code)+'" '+(r.occupied?'disabled':'')+'>Elimina camera vuota</button></li>').join('')+'</ul></details>');
        content.querySelector('[data-room-inventory]').hidden=!features.rooms||!features.room_inventory;
        content.querySelectorAll('[data-edit-inventory]').forEach(button=>button.onclick=()=>{if(dirty||pending||busy){say('Completa o scarta prima la modifica in corso.');return;}const room=rooms.find(r=>r.code===button.dataset.editInventory),form=content.querySelector('[data-room]');for(const key of ['code','name','capacity'])form.elements.namedItem(key).value=room[key];form.elements.namedItem('name').focus();});
        if(features.rooms&&rooms.length){
          const occupants=(data.people||[]).filter(p=>!['CANCELLED','EXPIRED'].includes(p.status));
          const choices='<option value="">Scegli una persona</option>'+occupants.map(p=>'<option value="'+p.id+'">'+esc(p.name)+' · '+esc(p.code)+' · '+esc(p.room||'senza camera')+'</option>').join('');
          content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<details data-room-occupants><summary>Camere e occupanti</summary>'+rooms.map(r=>'<h3>'+esc(r.name)+' · '+r.occupied+'/'+r.capacity+' posti</h3><ul>'+occupants.filter(p=>p.room===r.code).map(p=>'<li>'+esc(p.name)+' · '+esc(p.code)+'</li>').join('')+'</ul>').join('')+'<p>'+admitted.filter(p=>!p.room).length+' persone ammesse senza camera.</p><label>Prima persona<select data-swap-first>'+choices+'</select></label><label>Seconda persona<select data-swap-second>'+choices+'</select></label><button data-swap>Scambia le camere</button></details>');
          const swap=content.querySelector('[data-swap]');swap.onclick=async()=>{
            if(busy)return;if(dirty||(pending&&pending.operation!=='room_swap')){say('Completa o scarta la modifica in corso prima di scambiare le camere.');return;}
            const a=occupants.find(p=>String(p.id)===content.querySelector('[data-swap-first]').value),b=occupants.find(p=>String(p.id)===content.querySelector('[data-swap-second]').value);
            if(!pending){if(!a||!b||a.id===b.id||a.room===b.room){say('Scegli due persone diverse con assegnazioni diverse.');return;}if(!await ask('Scambiare le camere di '+a.name+' e '+b.name+'?','Scambia camere'))return;pending={operation:"room_swap",data:JSON.stringify([{order_code:a.code,number:a.number,key:'room',before:a.room,after:b.room},{order_code:b.code,number:b.number,key:'room',before:b.room,after:a.room}]),request_id:crypto.randomUUID()};}
            busy=true;swap.disabled=true;
            try{const result=await request('room_swap',pending);if(result.saved===false){if(result.rejected){pending=null;say(result.message);return;}throw new Error(result.message);}pending=null;busy=false;await summary();say('Camere scambiate. Aggiornamento del foglio accodato.');}catch(error){say('Scambio non confermato. '+error.message);swap.textContent='Riprova lo stesso scambio';}finally{busy=false;swap.disabled=false;}
          };
        }
        const optionDefinitions=new Map((data.option_definitions||[]).map(option=>[option.code,option]));
        const services=new Map();for(const p of admitted)for(const option of p.options||[]){const key=option.code||option.name;if(String(key).startsWith('alloggio-'))continue;const definition=optionDefinitions.get(key)||{};const category=definition.category||(String(key).startsWith('pullman')?'pullman':key==='pranzo'?'pranzo':'altro');const grouped=category+'|'+key,item=services.get(grouped)||{name:option.name||key,quantity:0,people:0,category};item.quantity+=Number(option.quantity||0);if(Number(option.quantity)>0)item.people++;services.set(grouped,item);}
        if(services.size){const labels={pullman:'Pullman',pranzo:'Pasti',altro:'Altro'};const grouped=[...services.values()].reduce((all,service)=>{(all[service.category]??=[]).push(service);return all;},{});content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<details data-person-services><summary>Servizi individuali delle persone ammesse</summary>'+Object.entries(grouped).sort(([a],[b])=>(labels[a]||a).localeCompare(labels[b]||b)).map(([category,items])=>'<h4>'+esc(labels[category]||category)+'</h4><ul>'+items.map(service=>'<li>'+esc(service.name)+': '+service.quantity+' unità · '+service.people+' persone</li>').join('')+'</ul>').join('')+'<p>Le opzioni della prenotazione restano separate e sono consultabili nella scheda.</p></details>');}
        const orderServices=new Map();for(const order of data.items.filter(x=>['CONFIRMED','PENDING_PAYMENT'].includes(x.status)))for(const option of order.order_options||[]){const key=option.code||option.name;const item=orderServices.get(key)||{name:option.name||key,quantity:0,orders:0};item.quantity+=Number(option.quantity||0);if(Number(option.quantity)>0)item.orders++;orderServices.set(key,item);}
        if(orderServices.size)content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<details data-order-services><summary>Servizi acquistati per prenotazione</summary><ul>'+[...orderServices].map(([key,service])=>'<li>'+esc(service.name)+': '+service.quantity+' unità</li>').join('')+'</ul><p>Le quantità sono conteggiate una volta per prenotazione e non attribuite ai singoli partecipanti.</p></details>');
        const allServices=new Map();for(const person of data.people||[])for(const option of person.options||[])allServices.set(option.code||option.name,{name:option.name||option.code});
        serviceFilter.closest('label').hidden=allServices.size===0;
        for(const [key,service] of allServices){const option=document.createElement('option');option.value=key;option.textContent=service.name;serviceFilter.append(option);}serviceFilter.value=listContext.service;
        const offers=data.items.filter(x=>x.status==='WAITLIST_OFFERED');
        if(offers.length)content.querySelector('[data-list]').insertAdjacentHTML('beforebegin','<details><summary>Posti proposti e scadenze ('+offers.length+' prenotazioni)</summary><p>Il termine trascorso non equivale a un annullamento già eseguito: lo stato viene aggiornato dal processo automatico.</p><ul>'+offers.map(x=>'<li>'+esc(x.name)+' · '+esc(x.code)+' · '+esc(deadlineLabel(x.offer_expires_at))+' <button data-open="'+esc(x.code)+'">Apri prenotazione</button></li>').join('')+'</ul></details>');
        const columns=[['name','Partecipante'],['status','Stato'],['email','Email'],['phone','Telefono']];
        if(features.rooms)columns.push(['room','Camera']);
        if(data.items.some(row=>Number(row.missing)>0))columns.push(['missing','Dati mancanti']);
        if(data.items.some(row=>String(row.requests||'').trim()))columns.push(['requests','Richieste particolari']);
        if(offers.length)columns.push(['offer_expires_at','Scadenza posto proposto (ora locale)']);
        if((data.people||[]).some(row=>['PRESENT','ABSENT'].includes(row.attendance?.state)))columns.push(['attendance','Presenza effettiva']);
        const extraKeys=new Set(data.field_keys||(data.people||[]).flatMap(p=>Object.keys(p.fields||{})));for(const key of extraKeys){const label=data.field_labels?.[key]||'',contactField=/\b(e-?mail|posta elettronica|telefono|cellulare|cell\.)\b/i.test(label)||/^(participant_)?(e?mail|phone|mobile|telefono|cellulare)/i.test(key);if(/^[a-z][a-z0-9_]{0,79}$/.test(key)&&label&&!contactField)columns.push(['field:'+key,label]);}
        content.querySelector('[data-list]').insertAdjacentHTML('afterend','<section data-participant-reports><header class="mi-report-heading"><h3>Elenchi partecipanti</h3><div class="mi-booking-detail__actions" data-report-actions><button data-export>Esporta CSV</button></div></header><details data-export-settings><summary>Scegli i dati del report</summary><div class="mi-report-columns">'+columns.map(([key,label])=>'<label><input type="checkbox" data-export-column="'+esc(key)+'" checked> '+esc(label)+'</label>').join('')+'</div></details></section>');
        if(printButton){printButton.textContent='Stampa';content.querySelector('[data-report-actions]').prepend(printButton);}
        if(annualPanel){
          annualPanel.hidden=!data.annual_report_group;annualPanel.open=false;root.append(annualPanel);
          const groupSelect=annualPanel.querySelector('[data-annual-group]');groupSelect.replaceChildren();groupSelect.disabled=true;
          annualPanel.querySelector('[data-annual-results]').replaceChildren();annualPanel.querySelector('[data-annual-status]').textContent='';
          if(data.annual_report_group){const option=document.createElement('option');option.value=data.annual_report_group.id;option.textContent=data.annual_report_group.name;groupSelect.append(option);}
        }
        if(!features.rooms&&listContext.sort==='room')listContext.sort='name';
        const searchBar=document.createElement('div');searchBar.className='mi-management-search';searchBar.append(search.closest('label'),content.querySelector('[data-clear-query]'));content.querySelector('[data-list]').before(searchBar);
        const participantHeading=document.createElement('h3');participantHeading.dataset.participantHeading='';participantHeading.textContent='Elenco partecipanti';
        const listHost=content.querySelector('[data-list]');
        listHost.before(participantHeading);
        const filterSection=document.createElement('section');filterSection.dataset.participantFilters='';filterSection.innerHTML='<header class="mi-filter-heading"><h3>Filtra</h3></header><div class="mi-participant-filters"></div>';participantHeading.before(filterSection);
        const filterControls=filterSection.querySelector('div');filterControls.append(searchBar,depositFilter.closest('label'),criticalFilter.closest('label'),serviceFilter.closest('label'),requestFilter.closest('label'),deadlineFilter.closest('label'));
        const closedLabel=document.createElement('label');closedLabel.className='mi-include-closed';
        const closedToggle=document.createElement('input');closedToggle.type='checkbox';closedToggle.checked=!!listContext.includeClosed;closedToggle.dataset.includeClosed='';closedLabel.append(closedToggle,' Mostra anche le iscrizioni chiuse');filterSection.append(closedLabel);
        closedToggle.onchange=()=>{listContext.includeClosed=closedToggle.checked;listContext.shown=30;draw();};
        search.closest('label').firstChild.textContent='Cerca nome ';serviceFilter.closest('label').firstChild.textContent='Servizio scelto';
        const searchButton=document.createElement('button');searchButton.type='button';searchButton.dataset.runQuery='';searchButton.textContent='Cerca';searchBar.append(searchButton);
        const clearSearch=content.querySelector('[data-clear-query]');clearSearch.type='button';clearSearch.textContent='×';clearSearch.setAttribute('aria-label','Cancella ricerca');clearSearch.title='Cancella ricerca';
        const searchInputWrap=document.createElement('span');searchInputWrap.className='mi-search-input';search.before(searchInputWrap);searchInputWrap.append(search,clearSearch);
        const searchStatus=document.createElement('span');searchStatus.setAttribute('role','status');searchStatus.setAttribute('aria-live','polite');filterSection.querySelector('header').append(searchStatus);
        if(features.rooms){
          const roomSection=document.createElement('details');roomSection.dataset.roomSection='';roomSection.innerHTML='<summary>Gestione camere</summary>';listHost.after(roomSection);
          const planner=content.querySelector('[data-room-planner]');if(planner){planner.querySelector('h3')?.remove();roomSection.append(planner);}
          for(const selector of ['[data-room-inventory]','[data-room-occupants]']){const panel=content.querySelector(selector);if(panel&&!panel.hidden)roomSection.append(panel);}
        }
        const servicePanels=[...content.querySelectorAll('[data-person-services],[data-order-services]')];
        if(servicePanels.length){const serviceSection=document.createElement('details');serviceSection.dataset.serviceSummary='';serviceSection.innerHTML='<summary>Riepilogo servizi richiesti</summary>';content.querySelector('[data-participant-reports]').append(serviceSection);servicePanels.forEach(panel=>serviceSection.append(panel));}
        let exportRows=[],pageRows=[],pageTotal=0,pageFingerprint='',listGeneration=0;
        const readAll=async()=>{
          if(!data.server_paging)return exportRows;
          const context=JSON.stringify(listContext),result=[];let fingerprint='';
          do{const page=await request('list_page',{context,offset:result.length,limit:200});
            if(ticket!==generation||context!==JSON.stringify(listContext))throw new Error('La selezione è cambiata. Ripeti l’operazione.');
            if(fingerprint&&fingerprint!==page.fingerprint)throw new Error('I dati sono cambiati durante la lettura. Aggiorna e ripeti l’operazione.');
            fingerprint=page.fingerprint;result.push(...page.rows);
            if(result.length>=page.total)return result;
            if(!page.rows.length)throw new Error('Lettura incompleta. Riprova.');
          }while(true);
        };
        content.querySelector('[data-export]').onclick=async()=>{
          try { exportRows=await readAll();
          const individual=listContext.view==='people',selected=individual?columns.filter(([key])=>content.querySelector('[data-export-column="'+key+'"]').checked):[['code','Prenotazione'],['name','Persone'],['status','Stato'],['paid','Versato netto (EUR)'],['balance','Residuo (EUR)']];
          if(!selected.length){say('Seleziona almeno una colonna.');return;}
          const cell=value=>{let text=String(value??'');if(/^[=+@\-\t\r]/.test(text))text="'"+text;return '"'+text.replaceAll('"','""')+'"';};
          const rows=[selected.map(([,label])=>label),...exportRows.map(row=>selected.map(([key])=>key==='name'?row.name:key.startsWith('field:')?(row.fields?.[key.slice(6)]??''):key==='attendance'?({PRESENT:'Presente',ABSENT:'Assente',UNRECORDED:'Non rilevata'}[row.attendance?.state||row.attendance]||'Non rilevata'):key==='status'?stateLabel(row):key==='offer_expires_at'?deadlineLabel(row[key]):['paid','balance'].includes(key)?(Number(row[key])/100).toFixed(2).replace('.',','):Array.isArray(row[key])?row[key].join(', '):row[key]))];
          const url=URL.createObjectURL(new Blob(['\uFEFF'+rows.map(row=>row.map(cell).join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'}));const link=document.createElement('a');link.href=url;link.download='evento-'+event+'-'+(individual?'partecipanti':'prenotazioni')+'.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000);say('Esportati '+exportRows.length+' risultati filtrati.');
          }catch(error){say('Esportazione non completata. '+error.message);}
        };
        const draw=async(append=false,provided=null)=>{
          const revision=++listGeneration;
          if(data.server_paging){
            try{
              if(provided){pageRows=provided.rows;pageTotal=provided.total;}
              else{
                const wanted=append?pageRows.length:listContext.shown-30;
                // A restored list is fetched in bounded pages too.
                let rows=append?pageRows:[],fingerprint=append?pageFingerprint:'';
                do{const response=await request('list_page',{context:JSON.stringify(listContext),offset:rows.length,limit:30});
                  if(revision!==listGeneration||ticket!==generation)return;
                  if(fingerprint&&fingerprint!==response.fingerprint)throw new Error('I dati sono cambiati. Aggiorna il riepilogo per continuare.');
                  fingerprint=response.fingerprint;rows=rows.concat(response.rows);pageTotal=response.total;
                  if(!response.rows.length||rows.length>=pageTotal)break;
                }while(rows.length<=wanted);
                pageRows=rows;pageFingerprint=fingerprint;
              }
            }catch(error){if(revision===listGeneration&&ticket===generation)say('Lista non disponibile. '+error.message);return;}
          }
          const query=listContext.query.trim().toLocaleLowerCase('it'),filter=listContext.filter;
          const matches=p=>(p.name+' '+p.buyer+' '+p.code+' '+p.email+' '+p.phone).toLocaleLowerCase('it').includes(query);
          const open=p=>!['CANCELLED','EXPIRED'].includes(p.status);
          const depositMatch=x=>!listContext.deposit||(['CONFIRMED','PENDING_PAYMENT'].includes(x.status)&&(listContext.deposit==='none'?x.paid<=0&&x.balance>0:listContext.deposit==='partial'?x.paid>0&&x.deposit_missing>0&&x.balance>0:listContext.deposit==='covered'?x.deposit_covered&&x.balance>0:listContext.deposit==='settled'?x.balance<=0:x.balance>0));
          const people=(data.people||[]).filter(p=>(listContext.includeClosed||open(p))&&depositMatch(p)&&personLogistics(p)&&deadlineMatch(p)&&requestMatch(p)&&(!listContext.state||p.status===listContext.state)&&matches(p)&&(filter==='all'||(open(p)&&(filter==='balance'?p.collectible:filter==='missing'?(p.missing||[]).length>0:p.unassigned))));
          const orders=data.items.filter(x=>(listContext.includeClosed||open(x))&&depositMatch(x)&&(!listContext.orderService||(x.order_options||[]).some(o=>(o.code||o.name)===listContext.orderService&&Number(o.quantity)>0))&&deadlineMatch(x)&&requestMatch(x)&&(!(listContext.room||listContext.service)||(data.people||[]).some(p=>p.code===x.code&&personLogistics(p)))&&(!listContext.state||x.status===listContext.state)&&(filter==='all'||(x.active&&x[filter]>0&&(filter!=='balance'||(x.collectible??['CONFIRMED','PENDING_PAYMENT'].includes(x.status)))))&&((x.name+' '+x.code).toLocaleLowerCase('it').includes(query)||(data.people||[]).some(p=>p.code===x.code&&matches(p))));
          const individual=listContext.view==='people',all=data.server_paging?pageRows:(individual?people:orders).sort((a,b)=>(Number(!open(a))-Number(!open(b)))||(listContext.direction==='desc'?-1:1)*String(a[listContext.sort]||'').localeCompare(String(b[listContext.sort]||''),'it',{numeric:true,sensitivity:'base'})),list=data.server_paging?all:all.slice(0,listContext.shown),total=data.server_paging?pageTotal:all.length;
          exportRows=all;
          const titleParts=[];
          if(filter==='unassigned')titleParts.push('senza camera assegnata');
          if(filter==='missing')titleParts.push('con dati mancanti');
          const paymentTitle={none:'senza versamenti',partial:'con caparra da completare',covered:'con caparra versata e saldo atteso',settled:'con saldo versato',unpaid:'con pagamento da completare'}[listContext.deposit];
          if(paymentTitle)titleParts.push(paymentTitle);
          if(listContext.requests)titleParts.push(listContext.requests==='pending'?'con richieste particolari da verificare':listContext.requests==='reviewed'?'con richieste particolari verificate':'con richieste particolari');
          if(listContext.deadline)titleParts.push(listContext.deadline==='expired'?'con proposta di posto scaduta':'con proposta di posto in scadenza');
          if(listContext.service)titleParts.push('che hanno scelto '+(serviceFilter.selectedOptions[0]?.textContent||listContext.service));
          if(listContext.query.trim())titleParts.push('per la ricerca «'+listContext.query.trim()+'»');
          participantHeading.textContent='Elenco partecipanti'+(titleParts.length?' '+titleParts.join(', '):'');
          searchStatus.textContent=total+' '+(individual?'persone':'prenotazioni')+' trovate';
          content.querySelector('[data-list]').innerHTML='<table><thead><tr>'+(individual?'<th class="mi-progressive-number">N.</th><th>Partecipante</th><th>Stato</th><th>Contatti</th><th>Dati mancanti</th>':'<th>Prenotazione</th><th>Persone nella prenotazione</th><th>Stato</th><th>Versato</th><th>Residuo</th>')+'<th></th></tr></thead><tbody>'+list.map((x,index)=>'<tr>'+(individual?'<td class="mi-progressive-number">'+(index+1)+'</td><td>'+esc(x.name)+'</td><td><span class="mi-status-pill mi-status-pill--'+stateClass(x)+'">'+esc(stateLabel(x))+'</span></td><td>'+emailContact(x.email)+'<br>'+esc(x.phone)+'</td><td>'+esc(x.room||'—')+(x.missing.length?'<br>Mancano: '+esc(x.missing.join(', ')):'')+'</td>':'<td>'+esc(x.code)+'</td><td>'+esc(x.name)+(data.people||[]).filter(p=>p.code===x.code).map(p=>'<div>'+esc(p.name)+(p.status==='CANCELLED'?' (annullato)':'')+'</div>').join('')+'</td><td><span class="mi-status-pill mi-status-pill--'+stateClass(x)+'">'+esc(stateLabel(x))+'</span></td><td>'+money(x.paid)+'</td><td>'+money(x.balance)+(x.deposit_plan?'<small>'+esc(depositText(x))+'</small>':'')+'</td>')+'<td><button data-open="'+esc(x.code)+'" '+(individual?'data-person-focus="'+x.number+'"':'')+'>Gestisci</button></td></tr>').join('')+'</tbody></table><p>'+list.length+' di '+total+' '+(individual?'persone':'prenotazioni')+'</p>'+(total>list.length?'<button data-more>Mostra altre 30</button>':'')+(all.length===0?'<p>Nessun risultato. Modifica la ricerca o i filtri.</p>':'');
          const showMissing=individual&&list.some(p=>(p.missing||[]).length||(features.rooms&&p.unassigned));
          if(individual){
            content.querySelectorAll('[data-list] tbody tr').forEach((row,index)=>row.classList.toggle('mi-closed-registration',!open(list[index])));
            content.querySelector('[data-list] th:nth-child(5)').hidden=!showMissing;
            content.querySelectorAll('[data-list] tbody tr').forEach((row,index)=>{
              const person=list[index],missing=[...(person.missing||[])];
              if(features.rooms&&person.unassigned)missing.push('Camera da assegnare');
              row.cells[4].textContent=missing.join(', ');row.cells[4].hidden=!showMissing;
            });
          }
          if(individual){
            content.querySelector('[data-list] table').classList.add('mi-participant-table');
            content.querySelectorAll('[data-list] tbody tr').forEach((row,index)=>{
              row.cells[3].innerHTML='<div class="mi-participant-contacts">'+phoneContact(list[index].phone)+emailContact(list[index].email)+'</div>';
            });
          }
          if(!features.payments&&!individual){content.querySelectorAll('[data-list] tr').forEach(row=>{row.children[3].hidden=true;row.children[4].hidden=true;});}
          const more=content.querySelector('[data-more]');if(more)more.onclick=()=>{listContext.shown+=30;draw(true);};
          criticalFilter.value=filter;
          for(const [index,key,label] of [[1,'name','Partecipante'],...(features.rooms?[[4,'room','Dati mancanti']]:[])]){
            const header=content.querySelectorAll('[data-list] thead th')[index];
            const selected=listContext.sort===key,descending=selected&&listContext.direction==='desc';
            header.setAttribute('aria-sort',selected?(descending?'descending':'ascending'):'none');
            header.innerHTML='<button type="button" data-sort-column="'+key+'">'+label+' <span aria-hidden="true">'+(selected?(descending?'↓':'↑'):'↕')+'</span></button>';
            header.querySelector('button').onclick=async()=>{listContext.direction=selected&&!descending?'desc':'asc';listContext.sort=key;listContext.shown=30;await draw();content.querySelector('[data-sort-column="'+key+'"]')?.focus();};
          }
          listResize?.disconnect();
          if(list.length>10){
            const table=content.querySelector('[data-list] table'),viewport=document.createElement('div');
            viewport.className='mi-participant-scroll';viewport.tabIndex=0;viewport.setAttribute('role','region');viewport.setAttribute('aria-label','Elenco partecipanti, scorri per vedere le altre righe');
            table.before(viewport);viewport.append(table);
            const fitRows=()=>{if(!table.isConnected){listResize?.disconnect();return;}const tenth=table.tBodies[0].rows[9];viewport.style.maxHeight=Math.ceil(tenth.getBoundingClientRect().bottom-table.getBoundingClientRect().top+2)+'px';};
            fitRows();listResize=new ResizeObserver(fitRows);listResize.observe(table);
          }
        };
        criticalFilter.onchange=()=>{listContext.filter=criticalFilter.value;listContext.shown=30;draw();};
        let searchTimer;
        const runSearch=()=>{clearTimeout(searchTimer);listContext.query=search.value;listContext.shown=30;searchStatus.textContent='Ricerca in corso…';return draw();};
        content.querySelector('[data-clear-query]').onclick=()=>{search.value='';listContext.orderService='';runSearch();search.focus();};
        search.oninput=()=>{clearTimeout(searchTimer);++listGeneration;searchStatus.textContent='Ricerca in corso…';searchTimer=setTimeout(()=>{if(ticket===generation)runSearch();},200);};
        search.onkeydown=e=>{if(e.key==='Enter'){e.preventDefault();runSearch();}};
        searchButton.onclick=runSearch;

        requestFilter.onchange=()=>{listContext.requests=requestFilter.value;listContext.shown=30;draw();};deadlineFilter.onchange=()=>{listContext.deadline=deadlineFilter.value;listContext.shown=30;draw();};serviceFilter.onchange=()=>{listContext.service=serviceFilter.value;listContext.shown=30;draw();};
        await draw();printList=async()=>{let printHost;const cleanup=()=>{printHost?.remove();root.classList.remove('mi-printing-list');};try{
          const individual=listContext.view==='people',selected=individual?columns.filter(([key])=>content.querySelector('[data-export-column="'+key+'"]').checked):[['code','Prenotazione'],['name','Persone'],['status','Stato'],['paid','Versato netto (EUR)'],['balance','Residuo (EUR)']];
          if(!selected.length){say('Seleziona almeno una colonna.');return;}
          const rows=await readAll();printHost=document.createElement('section');printHost.dataset.printList='1';
          const value=(row,key)=>key==='name'?row.name:key.startsWith('field:')?(row.fields?.[key.slice(6)]??''):key==='attendance'?({PRESENT:'Presente',ABSENT:'Assente',UNRECORDED:'Non rilevata'}[row.attendance?.state||row.attendance]||'Non rilevata'):key==='status'?stateLabel(row):key==='offer_expires_at'?deadlineLabel(row[key]):['paid','balance'].includes(key)?money(row[key]):Array.isArray(row[key])?row[key].join(', '):row[key];
          const filters=[listContext.query?'Ricerca: '+listContext.query:'',...['[data-deposit-filter]','[data-request-filter]','[data-deadline-filter]','[data-service-filter]'].map(selector=>{const input=content.querySelector(selector);return input.value?input.selectedOptions[0].textContent:'';}),listContext.filter!=='all'?criticalFilter.selectedOptions[0].textContent:'',listContext.orderService?orderServices.get(listContext.orderService)?.name:''].filter(Boolean);
          printHost.innerHTML='<h2>'+esc(select.selectedOptions[0]?.textContent||'Evento')+'</h2><p>'+rows.length+' '+(individual?'persone':'prenotazioni')+' · '+esc(filters.join(' · ')||'Tutti i risultati')+'</p><table><thead><tr>'+selected.map(([,label])=>'<th>'+esc(label)+'</th>').join('')+'</tr></thead><tbody>'+rows.map(row=>'<tr>'+selected.map(([key])=>'<td>'+esc(value(row,key))+'</td>').join('')+'</tr>').join('')+'</tbody></table>';root.append(printHost);root.classList.add('mi-printing-list');window.addEventListener('afterprint',cleanup,{once:true});window.print();
        }catch(error){cleanup();say('Stampa non completata. '+error.message);}};say('Aggiornato: '+new Date(data.updated_at).toLocaleString('it-IT'));
      }catch(e){if(ticket===generation)say('Riepilogo non disponibile. '+e.message);}
    }
    async function syncSheet(){
      if(!await canLeave())return;if(!event){say('Scegli un evento.');return;}
      const ticket=++generation;busy=true;select.disabled=true;parkPanels();content.replaceChildren();say('Lettura delle modifiche nel foglio Google…');
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
    function optionsText(options){return (Array.isArray(options)?options:[]).map(o=>esc(o.name||o.code||'Opzione')+' × '+esc(o.quantity??1)).join(' · ')||'Nessuna';}
    function input(key,label,value,type='text'){return `<label>${esc(label)}<input name="${esc(key)}" value="${esc(value)}" type="${['email','date','number','tel'].includes(type)?type:'text'}" maxlength="1000"></label>`;}
    function fieldInput(f,value){
      const name='field:'+f.key,label=f.label+(f.required?' (richiesto)':'');
      if(['select','yesno'].includes(f.type)){const options=f.options||[];const choices=options.includes(value)||!value?options:[value,...options];return '<label>'+esc(label)+'<select name="'+esc(name)+'"><option value="">Non indicato</option>'+choices.map(v=>'<option value="'+esc(v)+'" '+(String(v)===String(value)?'selected':'')+'>'+esc(v)+'</option>').join('')+'</select></label>';}
      if(f.type==='textarea')return '<label>'+esc(label)+'<textarea name="'+esc(name)+'" maxlength="1000">'+esc(value)+'</textarea></label>';
      return input(name,label,value,f.type);
    }
    async function detail(code,personNumber){
      if(!await canLeave())return;const ticket=++generation;order=code;say('Caricamento scheda…');
      try{const b=await request('detail');if(ticket!==generation)return;booking=b;printList=null;parkPanels();dirty=false;select.disabled=false;document.title=b.event_title+' — Gestione prenotazione';
        content.innerHTML=`<button data-back>Torna al riepilogo</button><h3>${esc(b.buyer.first_name)} ${esc(b.buyer.last_name)}</h3><p>Contatti per questa prenotazione: ${esc(b.buyer.email||'Non indicata email')} · ${esc(b.buyer.phone||'Non indicato telefono')}</p><p>Richieste particolari della prenotazione: ${esc(b.special_requests||'Nessuna')}</p><p>Opzioni della prenotazione: ${optionsText(b.order_options)}</p>${b.offer_expires_at?`<p>Scadenza proposta del posto (UTC): ${esc(b.offer_expires_at)}</p>`:''}<p data-booking-economics>Totale ${money(b.total_cents)} · Versato ${money(b.paid_cents)} · Residuo ${money(b.balance_cents)}</p>${b.payment_url?`<p><a class="mi-primary" href="${esc(b.payment_url)}">Registra un pagamento</a></p>`:''}<details data-booking-history open><summary>Storico movimenti</summary><table><thead><tr><th>Data</th><th>Tipo</th><th>Importo</th><th>Metodo</th><th>Riferimento</th><th>Operatore</th><th>Nota</th></tr></thead><tbody>${b.movements.map(m=>`<tr><td>${esc(new Date(m.data).toLocaleDateString('it-IT'))}</td><td>${esc(m.tipo)}</td><td>${money(m.importo)}</td><td>${esc(m.metodo)}</td><td>${esc(m.riferimento)}</td><td>${esc(m.operatore)}</td><td>${esc(m.nota||'')}</td></tr>`).join('')||'<tr><td colspan="7">Nessun movimento registrato.</td></tr>'}</tbody></table></details>`;
        const selectedPerson=b.participants.find(p=>p.number===Number(personNumber))||b.participants[0];
        if(selectedPerson){
          content.querySelector('h3').textContent=selectedPerson.first_name+' '+selectedPerson.last_name;
          const contact=content.querySelector('h3 + p');
          const personal=b.fields.filter(f=>f.required&&['participant_email','email','participant_phone','phone','mobile'].includes(f.key));
          if(personal.length&& (b.option_scope==='ALL'||selectedPerson.number===1))contact.textContent='Contatti dell’iscritto: '+personal.map(f=>selectedPerson.fields?.[f.key]||'Non indicato').join(' · ');
        }
        if(['CONFIRMED','PENDING_PAYMENT'].includes(b.status)&&(b.option_definitions||[]).length){
          const optionForm=(id,title,scope,selected)=>{const definitions=b.option_definitions.filter(o=>o.scope===scope);if(!definitions.length)return '';return '<form data-change-options="'+id+'"><h4>'+esc(title)+'</h4>'+definitions.map(o=>'<label>'+esc(o.name)+(scope==='TICKET'?'<input type="checkbox" name="option:'+esc(o.code)+'" value="1" '+(Number((selected||[]).find(item=>item.code===o.code)?.quantity||0)>0?'checked':'')+'>':'<input type="number" min="0" max="'+Number(o.max_quantity??1)+'" step="1" name="option:'+esc(o.code)+'" value="'+Number((selected||[]).find(item=>item.code===o.code)?.quantity||0)+'">')+'</label>').join('')+'<label>Motivo del cambio<textarea name="reason" required maxlength="500"></textarea></label><button type="submit">Verifica e salva servizi</button></form>';};
          content.insertAdjacentHTML('beforeend','<details><summary>Varia i servizi richiesti</summary><p>Dopo il cambio, verifica il dovuto e registra separatamente eventuali restituzioni.</p>'+optionForm(0,'Servizi della prenotazione','ORDER',b.order_options)+b.participants.filter(p=>p.status==='ACTIVE'&&(b.option_scope==='ALL'||p.number===1)).map(p=>optionForm(p.id,p.first_name+' '+p.last_name,'TICKET',p.options)).join('')+'</details>');
        }
        if(b.deposit_plan)content.querySelector('[data-booking-economics]').insertAdjacentHTML('afterend','<p data-booking-deposit>'+esc(depositText(b))+'</p>');
        if((b.accommodation_changes||[]).length)content.insertAdjacentHTML('beforeend','<details><summary>Storico cambi di sistemazione</summary>'+b.accommodation_changes.map(entry=>'<p><strong>'+esc(entry.created_at)+' · '+esc(entry.actor_label)+'</strong><br>'+esc(entry.change.reason)+'</p><ul>'+(entry.change.people||[]).map(p=>'<li>'+esc(p.name)+': '+esc(p.before_type)+' → '+esc(p.after_type)+' · '+esc(p.before_room||'senza camera')+' → '+esc(p.after_room)+'</li>').join('')+'</ul><p>Dovuto: '+money(entry.change.economics.before_total)+' → '+money(entry.change.economics.after_total)+'</p>').join('')+'</details>');
        if(b.option_changes?.length)content.insertAdjacentHTML('beforeend','<details><summary>Storico variazioni servizi</summary><ul>'+b.option_changes.map(a=>'<li>'+optionsText(a.change.before_options)+' → '+optionsText(a.change.after_options)+' · '+esc(a.change.reason)+' · '+esc(a.actor_label)+' · '+esc(a.created_at)+' UTC</li>').join('')+'</ul></details>');
        if(b.can_adjust_due)content.insertAdjacentHTML('beforeend','<details><summary>Rettifica il dovuto della prenotazione</summary><p>Modifica il totale concordato, lasciando intatti opzioni, incassi e rimborsi.</p><form data-adjust-due novalidate><label>Nuovo totale in euro<input name="total" inputmode="decimal" required pattern="[0-9]+([,.][0-9]{1,2})?" value="'+esc((Number(b.total_cents)/100).toFixed(2).replace('.',','))+'"></label><details class="mi-adjustment-reason"><summary>Motivo della rettifica</summary><textarea name="reason" aria-label="Motivo della rettifica" required maxlength="500"></textarea></details><button type="submit">Verifica e salva rettifica</button></form></details>');
        if(b.adjustments?.length)content.insertAdjacentHTML('beforeend','<details><summary>Storico rettifiche del dovuto</summary><ul>'+b.adjustments.map(a=>'<li>'+money(a.change.before_total)+' → '+money(a.change.after_total)+' · '+esc(a.change.reason)+' · '+esc(a.actor_label)+' · '+esc(a.created_at)+' UTC</li>').join('')+'</ul></details>');
        if(b.special_requests&&b.request_review){content.insertAdjacentHTML('beforeend','<form data-request-review><p>Richiesta particolare: '+esc(b.special_requests)+'</p><p>'+ (b.request_review.reviewed?'Verificata da '+esc(b.request_review.actor)+' · '+esc(b.request_review.at)+' UTC':'Da verificare')+'</p><label><input type="checkbox" name="reviewed" '+(b.request_review.reviewed?'checked':'')+'> Ho verificato la richiesta particolare</label><button type="submit">Salva verifica richiesta</button></form>');}
        if(b.payment_html&&Number(b.total_cents)>0){const panel=document.createElement('details');panel.innerHTML='<summary>Registra un movimento</summary>'+b.payment_html;const payment=panel.querySelector('[data-mi-payments]');payment.dataset.registrationId=b.registration_id;payment.dataset.order=b.order_code;payment.dataset.buyer=b.buyer.first_name+' '+b.buyer.last_name;panel.querySelector('h2 + p').textContent='Controlla il saldo di questa prenotazione e registra il movimento.';content.append(panel);}
        const hasRooms=Boolean(b.features?.rooms);
        for(const p of b.participants){const definitions=new Map(b.fields.map(f=>[f.key,f]));Object.keys(p.fields).forEach(k=>{if(k==='pullman'&&!b.bus_assignment_enabled)return;if(!definitions.has(k))definitions.set(k,{key:k,label:k});});
          const selectedOptions=Array.isArray(p.options)&&p.options.length?`<p>Servizi e sistemazioni scelti: ${optionsText(p.options)}</p>`:'';
          const roomField=hasRooms?`<label>Camera<select name="room"><option value="">Non assegnata</option>${b.accommodations.map(r=>`<option value="${esc(r.code)}" ${p.room===r.code?'selected':''} ${r.available<1&&p.room!==r.code?'disabled':''}>${esc(r.name)} · ${r.available} posti liberi</option>`).join('')}</select></label>`:'';
          content.insertAdjacentHTML('beforeend',`<form data-person="${p.number}" novalidate><fieldset ${p.status==='CANCELLED'?'disabled':''}><legend>${esc(p.first_name)} ${esc(p.last_name)}${p.status==='CANCELLED'?' — Annullato':''}</legend>${selectedOptions}<div class="mi-management-grid">${input('first_name','Nome',p.first_name)}${input('last_name','Cognome',p.last_name)}${[...definitions.values()].map(f=>fieldInput(f,p.fields[f.key]??'')).join('')}${roomField}</div><p>Per rimuovere un dato facoltativo, svuota il campo e salva.</p><button type="submit">Salva partecipante</button> <button class="mi-danger" type="button" data-cancel="${p.id}">Annulla partecipazione</button></fieldset></form>`);
        }
        if(hasRooms)for(const p of b.participants){
          const select=content.querySelector('[data-person="'+p.number+'"] select[name=room]');
          const requested=(p.options||[]).find(o=>Number(o.quantity)>0&&b.room_types?.[o.code]);
          const type=requested?b.room_types[requested.code]:null;
          select.closest('label').classList.add('mi-person-room');
          const preview=document.createElement('span');preview.className='mi-person-room-code';select.before(preview);
          const update=()=>{preview.replaceChildren();if(select.value){preview.textContent=select.value;return;}if(type){preview.append(type.prefix);const n=document.createElement('span');n.className='mi-room-unassigned';n.textContent='n';preview.append(n);const note=document.createElement('small');note.textContent='n = camera da assegnare';preview.append(note);}};
          select.options[0].textContent=type?'Scegli camera…':'';
          select.addEventListener('change',update);update();
          if(type){
            select.hidden=true;
            const number=document.createElement('input');number.type='number';number.min='1';number.max='999999';number.step='1';number.placeholder='Numero';number.setAttribute('aria-label','Numero camera '+type.prefix);number.dataset.roomNumber='';
            const match=(p.room||'').match(new RegExp('^'+type.prefix+'([1-9][0-9]{0,5})$'));number.value=match?match[1]:'';
            const capacity=document.createElement('input');capacity.type='number';capacity.min='1';capacity.max='1000';capacity.placeholder='Posti';capacity.setAttribute('aria-label','Posti della camera multipla');capacity.dataset.roomNumber='';capacity.hidden=type.capacity!==null;
            const existing=b.accommodations.find(r=>r.code===p.room);if(existing)capacity.value=existing.capacity;
            const save=document.createElement('button');save.type='button';save.textContent='Salva camera';
            select.after(number,capacity,save);
            let initialFields=[];queueMicrotask(()=>{initialFields=[...content.querySelectorAll('[data-person] input,[data-person] textarea,[data-person] select')].filter(el=>!el.hasAttribute('data-room-number')).map(el=>[el,el.type==='checkbox'?el.checked:el.value]);});
            number.oninput=()=>{preview.replaceChildren();preview.append(type.prefix);const n=document.createElement('span');n.textContent=number.value||'n';if(!number.value)n.className='mi-room-unassigned';preview.append(n);};
            save.onclick=async()=>{
              if(busy||pending)return;
              if(!number.value||!number.checkValidity()){number.reportValidity();say('Indica il numero della camera da assegnare.');return;}
              const otherChanges=paymentDraft()||initialFields.some(([el,value])=>(el.type==='checkbox'?el.checked:el.value)!==value);
              if(otherChanges){say('Salva prima le altre modifiche della scheda, poi assegna la camera.');return;}
              const after=type.prefix+number.value,room=b.accommodations.find(r=>r.code===after);
              if(type.capacity===null&&!room&&(!capacity.value||!capacity.checkValidity())){capacity.reportValidity();say('Indica i posti della camera multipla.');return;}
              const patch={order_code:b.order_code,number:p.number,key:'room',before:p.room||'',after,type:requested.code};
              if(type.capacity===null)patch.capacity=Number(room?.capacity||capacity.value);
              if(await ask('Assegnare '+p.first_name+' '+p.last_name+' alla camera '+after+'?','Salva camera'))mutate('room_assign',[patch]);
            };
          }
        }
        if(['CONFIRMED','PENDING_PAYMENT'].includes(b.status))content.insertAdjacentHTML('beforeend','<details><summary>Presenze effettive (facoltative)</summary><p>Una prenotazione confermata non equivale a una presenza. Lascia Non rilevata finché non hai verificato.</p>'+b.participants.filter(p=>p.status==='ACTIVE').map(p=>'<form data-attendance="'+p.id+'"><label>'+esc(p.first_name)+' '+esc(p.last_name)+'<select name="attendance">'+[['UNRECORDED','Non rilevata'],['PRESENT','Presente'],['ABSENT','Assente']].map(([value,label])=>'<option value="'+value+'" '+((p.attendance?.state||'UNRECORDED')===value?'selected':'')+'>'+label+'</option>').join('')+'</select></label>'+(p.attendance?.at?'<p>Ultima rilevazione: '+esc(p.attendance.actor)+' · '+esc(p.attendance.at)+' UTC</p>':'')+'<button type="submit">Salva presenza</button></form>').join('')+'</details>');
        if(['CONFIRMED','PENDING_PAYMENT'].includes(b.status))content.insertAdjacentHTML('beforeend','<p><button class="mi-danger" type="button" data-cancel-registration>Annulla tutta la prenotazione</button></p>');
        if(hasRooms)content.insertAdjacentHTML('beforeend',`<details><summary>Gestisci camere</summary><p>Aggiungi una camera o modifica nome e capienza usando lo stesso codice.</p><form data-room novalidate>${input('code','Codice camera','')}${input('name','Nome camera','')}${input('capacity','Posti',1,'number')}<button>Salva camera</button></form><ul>${b.accommodations.map(r=>`<li>${esc(r.code)} — ${esc(r.name)}: ${r.occupied}/${r.capacity} <button class="mi-danger" data-delete-room="${esc(r.code)}" ${r.occupied?'disabled':''}>Elimina</button></li>`).join('')}</ul></details>`);
        if(personNumber){const person=content.querySelector('[data-person="'+Number(personNumber)+'"]');person?.scrollIntoView({block:'center'});person?.querySelector('input')?.focus();}
        say('Dati aggiornati. Le modifiche vengono salvate nel registro centrale.');if(b.pending){pending=b.pending;say('Un salvataggio interrotto può essere completato.');const resume=document.createElement('button');resume.textContent='Completa il salvataggio interrotto';resume.onclick=()=>mutate(pending.operation,JSON.parse(pending.data));status.append(' ',resume);}
      }catch(e){if(ticket===generation)say('Scheda non disponibile. '+e.message);}
    }
    async function mutate(operation,data){
      if(busy)return;busy=true;
      root.querySelectorAll('button,input,select,textarea').forEach(el=>{el.dataset.wasDisabled=el.disabled?'1':'0';el.disabled=true;});
      pending=pending||{operation,data:JSON.stringify(data),version:booking.version,request_id:crypto.randomUUID()};say('Salvataggio…');
      try{const result=await request(pending.operation,pending);if(result.saved===false){if(result.rejected){pending=null;say(result.message);return;}throw new Error(result.message);}pending=null;dirty=false;dirtyForm=null;busy=false;if(order)await detail(order);else await summary();say(result.message||'Modifica salvata.');}
      catch(e){say('Salvataggio non confermato: '+e.message);const retry=document.createElement('button');retry.textContent='Riprova lo stesso salvataggio';retry.onclick=()=>mutate(operation,data);status.append(' ',retry);const reload=document.createElement('button');reload.textContent='Ricarica la scheda';reload.onclick=async()=>{if(!await ask('Ricaricare i dati? Le modifiche non salvate verranno perse.','Ricarica la scheda'))return;pending=null;dirty=false;if(order)detail(order);else summary();};status.append(' ',reload);}
      finally{busy=false;select.disabled=false;root.querySelectorAll('[data-was-disabled]').forEach(el=>{el.disabled=el.dataset.wasDisabled==='1';delete el.dataset.wasDisabled;});}
    }
    content.addEventListener('submit',async e=>{if(e.target.closest('[data-mi-payments]'))return;e.preventDefault();const f=e.target;const invalid=[...f.elements].find(x=>x.willValidate&&!x.validity.valid);if(invalid){const closed=invalid.closest('details');if(closed)closed.open=true;invalid.setAttribute('aria-invalid','true');say('Controlla il formato del campo evidenziato.');invalid.focus();return;}const values=Object.fromEntries(new FormData(f));if(f.hasAttribute('data-change-options')){const options={};f.querySelectorAll('input[type=checkbox][name^="option:"]').forEach(el=>options[el.name.slice(7)]=el.checked?1:0);for(const [key,value]of Object.entries(values))if(key.startsWith('option:'))options[key.slice(7)]=Number(value);if(await ask('Salvare i nuovi servizi? Il dovuto e gli eventuali rimborsi devono essere verificati separatamente.','Salva servizi'))mutate('change_options',{participant_id:Number(f.dataset.changeOptions),options,reason:values.reason});}else if(f.hasAttribute('data-identity')){try{const target=await request('identity_preview',{target_order:values.target_order,target_number:values.target_number});if(await ask('Confermi che '+target.first_name+' '+target.last_name+' è la stessa persona di questa iscrizione?','Conferma collegamento'))mutate('identity_link',{participant_id:Number(f.dataset.identity),target_id:Number(target.id),target_order:values.target_order,target_number:Number(values.target_number)});}catch(error){say(error.message);}}else if(f.hasAttribute('data-adjust-due')){const cents=Math.round(Number(values.total.replace(',','.'))*100);ask('Confermi il nuovo totale di '+money(cents)+'? Incassi e rimborsi restano invariati.','Salva rettifica').then(ok=>{if(ok)mutate('adjust_due',{total_cents:cents,reason:values.reason});});}else if(f.hasAttribute('data-attendance')){mutate('attendance',{participant_id:Number(f.dataset.attendance),attendance:values.attendance});}else if(f.hasAttribute('data-request-review')){mutate('request_review',{reviewed:f.elements.namedItem('reviewed').checked});}else if(f.hasAttribute('data-person')){const fields={};for(const [k,v]of Object.entries(values))if(k.startsWith('field:'))fields[k.slice(6)]=v;mutate('participant',{number:Number(f.dataset.person),first_name:values.first_name,last_name:values.last_name,room:values.room,fields});}else if(f.hasAttribute('data-room'))mutate(order?'room_save':'event_room_save',{...values,capacity:Number(values.capacity)});});
    content.addEventListener('click',async e=>{const b=e.target.closest('button');if(!b||busy||pending)return;if(dirty&&(b.dataset.cancel||b.dataset.deleteRoom||b.hasAttribute('data-cancel-registration'))){say('Salva le modifiche oppure scartale con Aggiorna riepilogo prima di annullare o eliminare.');return;}if(b.closest('[data-mi-payments]'))return;if(b.dataset.unlink){if(dirty){say('Salva o scarta la bozza prima di rimuovere il collegamento.');return;}if(await ask('Rimuovere questo collegamento personale dal rapporto annuale?','Rimuovi collegamento'))mutate('identity_link',{participant_id:Number(b.dataset.unlink),target_id:0});}if(b.dataset.open)detail(b.dataset.open,b.dataset.personFocus);if(b.hasAttribute('data-back'))summary();if(b.dataset.deleteRoom&&await ask('Eliminare la camera '+b.dataset.deleteRoom+'? La camera è vuota.','Elimina camera'))mutate(order?'room_delete':'event_room_delete',{code:b.dataset.deleteRoom});if(b.dataset.cancel&&await ask('Annullare la partecipazione? Eventuali rimborsi si registrano separatamente.','Annulla partecipazione')){busy=true;b.disabled=true;try{const result=await request('cancel',{participant_id:b.dataset.cancel});dirty=false;busy=false;await detail(order);say(result.message);}catch(err){say(err.message);}finally{busy=false;b.disabled=false;}}if(b.hasAttribute('data-cancel-registration')&&await ask('Annullare l’intera prenotazione? Eventuali rimborsi si registrano separatamente.','Annulla prenotazione')){busy=true;b.disabled=true;try{const result=await request('cancel_registration');dirty=false;busy=false;await summary();say(result.message);}catch(err){say(err.message);}finally{busy=false;b.disabled=false;}}});
    select.onchange=async()=>{const next=select.value;if(!await canLeave()){select.value=event;return;}event=next;updateLocation();summary();};root.querySelector('[data-refresh]').onclick=()=>order?detail(order):summary();root.querySelector('[data-print]').onclick=()=>{if(printList){printList();return;}root.querySelectorAll('.mi-print-value').forEach(el=>el.remove());root.querySelectorAll('form input,form select,form textarea').forEach(el=>{const span=document.createElement('span');span.className='mi-print-value';span.textContent=el.tagName==='SELECT'?(el.selectedOptions[0]?.textContent||''):el.value;el.after(span);});window.print();};
    if(eventActions?.dataset.sheetAuto==='1'&&event)syncSheet();else if(order&&event)detail(order);else summary();
  }
  const scan=()=>document.querySelectorAll('[data-mi-management]').forEach(init);
  document.addEventListener('DOMContentLoaded',()=>{scan();new MutationObserver(scan).observe(document.body,{childList:true,subtree:true});});
})();
