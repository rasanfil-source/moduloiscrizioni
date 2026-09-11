
'use strict';

// ─── CONFIG ──────────────────────────────────────────────────────────────────
const GAS_ENDPOINT = MIBalance.endpoint;

const FETCH_TIMEOUT_MS       = 15000; // scrittura foglio (operazione veloce)
const FETCH_TIMEOUT_EMAIL_MS = 35000; // invio email + adempimenti (più lenta)
const TINABA_URL = MIBalance.cardUrl;


const LABELS = {
  calc:        'Conferma le tue scelte',
  recalc:      '↻ Aggiorna e re-invia email',
  confirmed:   '✔ Scelte confermate',
  lookupHint:  'Inserisci cognome per iniziare',
};


// ─── FLASH VALORE ─────────────────────────────────────────────────────────────
function flashValue(el) {
  if (!el) return;
  el.classList.remove('value-flash');
  // reflow per re-triggerare l'animazione
  void el.offsetWidth;
  el.classList.add('value-flash');
}

function setTextFlash(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  const changed = el.textContent !== text;
  el.textContent = text;
  if (changed) flashValue(el);
}

// ─── STATO PRENOTAZIONE ───────────────────────────────────────────────────────
function setBookingStatus(state) {
  const el = document.getElementById('bookingStatus');
  if (!el) return;
  if (!state) { el.style.display = 'none'; el.textContent = ''; return; }
  el.style.display = 'block';
  el.className = '';
  if (state === 'editing') {
    el.classList.add('editing');
    el.innerHTML = '● Modifica delle opzioni';
  } else if (state === 'confirmed') {
    el.classList.add('confirmed');
    el.innerHTML = '✔ Scelte registrate<br><span style="font-weight:400;font-size:.78rem;">In attesa di pagamento</span>';
  }
}


// ─── NORMALIZZAZIONE CLIENT-SIDE ─────────────────────────────────────────────
/**
 * Stessa logica del GAS norm(): accenti, apostrofi, trattini, spazi.
 * Usata per inviare al server la forma normalizzata (evita mismatch).
 */
function normClient(s) {
  return String(s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[''`ʼ\u2018\u2019\u02bc]/g, '')
    .replace(/[-–—]/g, ' ')
    .replace(/\u00a0/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function capitalize(str) {
  if (!str) return '';
  return str.toLowerCase()
    .replace(/(?:^|[\s\-])([a-zàèéìòùäöü])/g, (m, c) => m.replace(c, c.toUpperCase()))
    .replace(/([''`])([a-zàèéìòùäöü])/g, (_, apos, c) => apos + c.toUpperCase());
}

function escapeHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}


// ─── ALLOGGIO HELPERS ─────────────────────────────────────────────────────────





// ─── FORMATTAZIONE VALUTA ─────────────────────────────────────────────────────
const euroFmt = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
function formatEuro(v) {
  const n = Number(v);
  return Number.isFinite(n) ? euroFmt.format(n) : '€ —';
}


// ─── FETCH CON TIMEOUT ────────────────────────────────────────────────────────
async function fetchWithTimeout(url, options = {}, ms = FETCH_TIMEOUT_MS) {
  const ac = new AbortController();
  let isTimeout = false;
  const timer = setTimeout(() => {
    isTimeout = true;
    ac.abort(new Error('Timeout: il server ha impiegato troppo tempo — riprova tra qualche secondo'));
  }, ms);

  const caller = options.signal;
  if (caller) {
    if (caller.aborted) { clearTimeout(timer); ac.abort(); }
    else caller.addEventListener('abort', () => ac.abort(), { once: true });
  }

  try {
    const res = await fetch(url, { ...options, signal: ac.signal });
    return res;
  } catch (err) {
    if (isTimeout || err.name === 'AbortError') {
      throw new Error('La richiesta ha impiegato troppo tempo (Timeout). Riprova, i dati potrebbero essere stati comunque salvati.');
    }
    throw err;
  } finally {
    clearTimeout(timer);
  }
}

async function gasPost(payload, signal, timeoutMs = FETCH_TIMEOUT_MS) {
  const res = await fetchWithTimeout(GAS_ENDPOINT, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({...payload, nonce: MIBalance.nonce}), signal }, timeoutMs);
  const text = await res.text();
  let data; try { data = JSON.parse(text); } catch { throw new Error('Risposta non valida dal server. Riprova.'); }
  return data;
}


// ─── STATO APPLICAZIONE ──────────────────────────────────────────────────────
let persone        = [];
let personCounter  = 0;
let lastCalcResult = null;
let recalcNeeded   = false;
let _lastSavedHash = null;
let _calcInProgress= false;
let _calcButtonsHideTimer = null;
let gasWarmupInProgress = false;

const lookupTimers      = {};
const lookupControllers = {};
const latestLookupToken = {};
const lookupCache       = new Map();
const CACHE_TTL_MS      = 300000; // 5 minuti

function getFromCache(key) {
  const entry = lookupCache.get(key);
  if (!entry) return null;
  if (Date.now() - entry.timestamp > CACHE_TTL_MS) {
    lookupCache.delete(key);
    return null;
  }
  return entry.data;
}

function setInCache(key, data) {
  lookupCache.set(key, { data, timestamp: Date.now() });
}

function makeCacheKey(nome, cognome) {
  return `${normClient(nome)}|${normClient(cognome)}`;
}


// ─── DOM REFS ────────────────────────────────────────────────────────────────
const container         = document.getElementById('personsContainer');
const tmpl              = document.getElementById('personCardTemplate');
const btnAdd            = document.getElementById('btnAddPerson');
const btnCalc           = document.getElementById('calcBtn');
const btnCalcDesktop    = document.getElementById('calcBtnDesktop');
const desktopShortcut   = document.getElementById('desktopCalcShortcut');
const calcButtons       = [btnCalc, btnCalcDesktop].filter(Boolean);
const btnPay            = document.getElementById('payBtn');
const summaryBox        = document.getElementById('summaryBox');
const breakdownBox      = document.getElementById('breakdownBox');
const calcBtnContainer  = document.getElementById('calcBtnContainer');
const recalcBanner      = document.getElementById('recalcBanner');
const totalBox          = document.getElementById('totalBox');
const globalStatus      = document.getElementById('globalStatus');
const emailInput        = document.getElementById('emailInput');
const actionSection     = document.getElementById('actionSection');
const emailSection      = document.getElementById('emailSection');
const payPopup          = document.getElementById('payPopup');


// ─── MOBILE: STICKY CALC BUTTON ──────────────────────────────────────────────
const calcBtnAnchor = document.createComment('calc-btn-anchor');
if (calcBtnContainer?.parentNode)
  calcBtnContainer.parentNode.insertBefore(calcBtnAnchor, calcBtnContainer);

function mountCalcBtn() {
  if (!calcBtnContainer) return;
  const isMobile = window.matchMedia('(max-width: 900px)').matches;
  if (isMobile) {
    if (calcBtnContainer.parentNode !== document.body)
      document.body.appendChild(calcBtnContainer);
  } else {
    if (calcBtnAnchor.parentNode && calcBtnContainer.parentNode !== calcBtnAnchor.parentNode)
      calcBtnAnchor.parentNode.insertBefore(calcBtnContainer, calcBtnAnchor.nextSibling);
    document.body.classList.remove('has-mobile-cta');
  }
}

mountCalcBtn();
window.addEventListener('resize', mountCalcBtn);

function setSearchButtonsDisabled(disabled) {
  document.querySelectorAll('.btn-search-person').forEach(b => { b.disabled = !!disabled; });
}


// ─── WARM-UP GAS ─────────────────────────────────────────────────────────────
async function warmupGas() {
  const badge = document.getElementById('warmupBadge');
  if (badge) {
    badge.style.display = 'block';
    badge.textContent = '⏳ Connessione in corso…';
    badge.classList.remove('ready');
  }
  gasWarmupInProgress = true;
  setSearchButtonsDisabled(true);

  const MAX_ATTEMPTS = 3;
  const RETRY_DELAY_MS = 4000;
  let success = false;

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    try {
      await gasPost({ action: '__ping__' });
      success = true;
      break;
    } catch (_) {
      if (attempt < MAX_ATTEMPTS) {
        if (badge) badge.textContent = `⏳ Tentativo ${attempt + 1}/${MAX_ATTEMPTS}…`;
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS));
      }
    }
  }

  if (badge) {
    if (success) {
      badge.textContent = '✅ Siamo pronti!';
      badge.classList.add('ready');
      setTimeout(() => {
        badge.style.display = 'none';
        if (desktopShortcut) desktopShortcut.style.display = '';
      }, 2500);
    } else {
      badge.style.display = 'none';
      if (desktopShortcut) desktopShortcut.style.display = '';
    }
  } else if (desktopShortcut) {
    desktopShortcut.style.display = '';
  }

  gasWarmupInProgress = false;
  setSearchButtonsDisabled(false);
}


// ─── STEPPER ─────────────────────────────────────────────────────────────────
function updateStepper() {
  const steps      = document.querySelectorAll('#stepper .step');
  const loadedN    = persone.filter(p => p.loaded).length;
  const step1Done  = loadedN > 0;
  const step3Done  = !!lastCalcResult && !recalcNeeded;
  const active     = step3Done ? 3 : step1Done ? 2 : 1;

  steps.forEach(el => {
    const n = +el.dataset.step;
    el.classList.remove('is-active', 'is-done', 'is-stale');
    if ((n === 1 && step1Done) || (n === 2 && step1Done) || (n === 3 && step3Done))
      el.classList.add('is-done');
    if (n === active)        el.classList.add('is-active');
    if (n === 3 && recalcNeeded) el.classList.add('is-stale');
  });

  const dot3 = document.querySelector('#stepper .step[data-step="3"] .step-dot');
  if (dot3) dot3.textContent = recalcNeeded ? '!' : (step3Done ? '✓' : '3');
}

updateStepper();


// ─── STATUS HELPERS ──────────────────────────────────────────────────────────
function setGlobalStatus(type, msg) {
  globalStatus.innerHTML  = msg;
  globalStatus.className  = 'status' + (type ? ` ${type}` : '');
}

function setCardStatus(card, type, msg) {
  const el       = card.querySelector('.person-status');
  el.innerHTML   = msg;
  el.className   = 'status person-status' + (type ? ` ${type}` : '');
}


// ─── CALC BUTTONS ────────────────────────────────────────────────────────────
function setCalcButtons({ text, display, disabled }) {
  const isMobile = window.matchMedia('(max-width: 900px)').matches;

  calcButtons.forEach(btn => {
    if (text    != null) btn.textContent = text;
    if (display != null) btn.style.display = display;
    if (disabled!= null) btn.disabled = disabled;
  });

  if (display != null) {
    const show = display === 'block';
    if (isMobile && calcBtnContainer) {
      calcBtnContainer.classList.toggle('active', show);
      document.body.classList.toggle('has-mobile-cta', show);
    }
    if (desktopShortcut) desktopShortcut.style.display = (show && !isMobile) ? 'block' : 'none';
  }
}

function clearCalcButtonsHideTimer() {
  if (_calcButtonsHideTimer != null) {
    clearTimeout(_calcButtonsHideTimer);
    _calcButtonsHideTimer = null;
  }
}

function renderLoadedSummary() {
  const loaded = persone.filter(p => p.loaded);
  summaryBox.innerHTML = loaded.map(p =>
    `<div class="summary-line">👤 ${escapeHtml(p.cognome)} ${escapeHtml(p.nome)}</div>`
  ).join('');
}


// ─── BREAKDOWN LIVE ──────────────────────────────────────────────────────────
/**
 * Aggiorna il riepilogo costi in tempo reale ogni volta che:
 * - viene caricata una prenotazione
 * - viene spuntato/tolto un transfer
 */
function updateLiveBreakdown() {
  const loaded=persone.filter(p=>p.loaded);renderLoadedSummary();
  if(!loaded.length){breakdownBox.style.display='none';totalBox.style.display='none';emailSection.style.display='none';return;}
  let total=0,paid=0,deposit=0,depositPaid=0,depositDue=0,saldoDue=0,balance=0;const lines=new Map();
  loaded.forEach(p=>{const d=p.model;const choices=getTransfer(p.cardEl);let subtotal=d.fixed;
    d.locked.forEach(o=>lines.set(o.name,(lines.get(o.name)||0)+o.price));
    const base=d.fixed-d.locked.reduce((sum,o)=>sum+o.price,0);if(base)lines.set('Quota base e rettifiche',(lines.get('Quota base e rettifiche')||0)+base);
    d.services.forEach(s=>{if(choices[s.code]){subtotal+=s.price;lines.set(s.name,(lines.get(s.name)||0)+s.price);}});
    const cap=Math.min(Math.max(0,subtotal),d.deposit||0);
    total+=subtotal;paid+=d.paid;deposit+=cap;depositPaid+=Math.min(cap,d.paid);depositDue+=Math.max(0,cap-d.paid);saldoDue+=Math.max(0,subtotal-Math.max(cap,d.paid));balance+=Math.max(0,subtotal-d.paid);
  });
  showAmounts({total,paid,deposit,depositPaid,depositDue,saldoDue,balance},lines);
  emailSection.style.display='block';setBookingStatus('editing');
}

// Alias per retrocompatibilità con loadPartnerCard() e altri punti
function updatePartialTotal() { updateLiveBreakdown(); }


// ─── BANNER RICALCOLO ────────────────────────────────────────────────────────
function showRecalcBanner() {
  clearCalcButtonsHideTimer();
  recalcNeeded = true;
  recalcBanner.classList.add('active');
  setCalcButtons({ display: 'block', text: LABELS.recalc, disabled: false });
  btnPay.disabled  = true;
  totalBox.classList.add('stale');
  _lastSavedHash = null;
  updateStepper();
}

function hideRecalcBanner() {
  recalcNeeded = false;
  recalcBanner.classList.remove('active');
  totalBox.classList.remove('stale');
  updateStepper();
}

function onOptionsChanged() {
  if(_calcInProgress)return;
  const confirmed=!!lastCalcResult;lastCalcResult=null;_pendingBalanceSave=null;updateLiveBreakdown();actionSection.style.display='none';btnPay.disabled=true;
  setCalcButtons({text:confirmed?LABELS.recalc:LABELS.calc,display:'block',disabled:false});
  if(confirmed)showRecalcBanner();setGlobalStatus('','');
}


// ─── MODALI ACCESSIBILI (payPopup, confirmModal, ibanPopup) ──────────────────
let _focusBeforeModal = null;
let _activeModal      = null;

function getFocusables(el) {
  return Array.from(el.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  )).filter(e => !e.disabled && e.offsetParent !== null);
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  _focusBeforeModal = document.activeElement;
  _activeModal      = modal;
  modal.classList.add('active');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  (getFocusables(modal)[0] || modal).focus();
  document.addEventListener('keydown', onModalKey, true);
}

function closeModal() {
  if (!_activeModal) return;
  _activeModal.classList.remove('active');
  _activeModal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onModalKey, true);
  _focusBeforeModal?.focus();
  _activeModal = null;
}

function onModalKey(e) {
  if (!_activeModal) return;
  if (e.key === 'Escape') { e.preventDefault(); if (_activeModal.id === 'confirmModal') document.getElementById('confirmCancelBtn').click(); else closeModal(); return; }
  if (e.key === 'Enter' && _activeModal.id === 'ibanPopup') {
    e.preventDefault();
    document.getElementById('ibanCloseBtn')?.click();
    return;
  }
  if (e.key !== 'Tab') return;
  const f = getFocusables(_activeModal);
  if (!f.length) return;
  if (e.shiftKey && document.activeElement === f[0]) {
    e.preventDefault(); f[f.length - 1].focus();
  } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) {
    e.preventDefault(); f[0].focus();
  }
}

document.getElementById('popupCloseBtn')?.addEventListener('click', closeModal);
document.getElementById('ibanCloseBtn')?.addEventListener('click', closeModal);

[payPopup, document.getElementById('confirmModal'), document.getElementById('ibanPopup')].forEach(m => {
  m?.addEventListener('mousedown', e => { if (e.target === m) { if(m.id==='confirmModal')document.getElementById('confirmCancelBtn').click();else closeModal(); } });
});


function showConfirmModal(title, bodyHtml) {
  return new Promise(resolve => {
    const titleEl   = document.getElementById('confirmTitle');
    const bodyEl    = document.getElementById('confirmBody');
    const okBtn     = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');

    titleEl.textContent = title;
    bodyEl.innerHTML    = bodyHtml;

    okBtn.onclick     = () => done(true);
    cancelBtn.onclick = () => done(false);

    openModal('confirmModal');

    const done = val => {
      closeModal();
      okBtn.onclick     = null;
      cancelBtn.onclick = null;
      resolve(val);
    };
  });
}


// ─── RICERCA PERSONA ─────────────────────────────────────────────────────────
function cancelLookup(index) {
  clearTimeout(lookupTimers[index]);
  delete lookupTimers[index];
  lookupControllers[index]?.abort();
  delete lookupControllers[index];
  delete latestLookupToken[index];
}

function debouncedLookup(card, index, delay = 120) {
  clearTimeout(lookupTimers[index]);
  lookupTimers[index] = setTimeout(() => {
    lookupPersona(card, index);
    delete lookupTimers[index];
  }, delay);
}

async function lookupPersona(card, index) {
  const p = persone.find(p => p.index === index);
  if (!p || p.loaded) return;
  const cognomeRaw = card.querySelector('.person-cognome').value.trim();
  const nomeRaw = card.querySelector('.person-nome').value.trim();
  const cognome = normClient(cognomeRaw), nome = normClient(nomeRaw);
  if (!cognome) return;
  cancelLookup(index);
  const ctrl = new AbortController(), token = Symbol();
  lookupControllers[index] = ctrl; latestLookupToken[index] = token;
  setCardStatus(card, 'loading', '<span class="spinner"></span>Caricamento…');
  try {
    let data = await gasPost({action:'lookupByCognome', cognome}, ctrl.signal);
    if (latestLookupToken[index] !== token) return;
    if (data.error === 'duplicate') {
      const matches = data.candidates.filter(c => normClient(c.nome) === nome);
      if (matches.length === 1 || p.candidate) {
        data = await gasPost({action:'lookupPersona', cognome, nome, candidate:p.candidate || matches[0].row}, ctrl.signal);
        if (latestLookupToken[index] !== token) return;
      }
    }
    p.candidate = null;
    if (!data.success) {
      if (data.error === 'duplicate') {
        setCardStatus(card, 'error', '<div style="margin-top:8px;">Ci sono più persone con questo cognome. Quale sei?</div><div class="candidate-buttons"></div>');
        const target = card.querySelector('.candidate-buttons');
        data.candidates.forEach(c => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'secondary';
          const sameName = data.candidates.filter(x => normClient(x.nome) === normClient(c.nome)).length > 1;
          button.textContent = 'Sono ' + c.nome + (sameName && c.room ? ' — ' + c.room : '');
          if (sameName && (!c.room || data.candidates.some(x=>x.row!==c.row&&normClient(x.nome)===normClient(c.nome)&&x.room===c.room))) { button.textContent += ' — contatta la segreteria'; button.disabled = true; }
          button.addEventListener('click', () => { p.candidate = c.row; card.querySelector('.person-nome').value = c.nome; lookupPersona(card,index); });
          target.appendChild(button);
        });
        card.querySelector('.person-nome').classList.add('has-error');
      } else if (data.error === 'not_found') {
        setCardStatus(card,'error', `Prenotazione non trovata per "<strong>${escapeHtml(cognomeRaw)}</strong>". Controlla l'ortografia o <a href="mailto:${escapeHtml(MIBalance.contact)}">contattaci</a>.`);
      } else setCardStatus(card,'error',escapeHtml(data.message || data.error));
      return;
    }
    applyResult(card,p,data,nomeRaw,cognomeRaw);
  } catch (error) {
    if (latestLookupToken[index] !== token) return;
    setCardStatus(card,'error', error.name === 'AbortError' ? 'Il server sta impiegando troppo tempo. Premi Trova per riprovare.' : 'Errore di connessione. Premi Trova per riprovare.');
  } finally { if (lookupControllers[index] === ctrl) delete lookupControllers[index]; }
}





function applyResult(card, person, data, nomeRaw, cognomeRaw) {
  if (persone.some(p => p.loaded && p.row === data.row && p !== person)) { setCardStatus(card,'error','Questa persona è già presente nel riepilogo.'); return; }
  const d = data.persona;
  Object.assign(person,{nome:d.nome,cognome:d.cognome,row:data.row,loaded:true,alloggio:d.alloggio,siglaAlloggio:d.siglaAlloggio,model:d});
  card.querySelector('.person-cognome').value = d.cognome; card.querySelector('.person-nome').value = d.nome;
  card.querySelectorAll('.person-cognome,.person-nome').forEach(el => {el.readOnly=true;el.classList.remove('has-error');});
  card.querySelector('.person-cognome').parentElement.querySelector('.field-hint').style.visibility='hidden';
  card.classList.add('locked'); setSearchBtnVisible(card,false);
  const locked = card.querySelector('.promemoria-bozza'); locked.replaceChildren();
  const accommodations=d.locked.filter(o=>o.accommodation),otherOptions=d.locked.filter(o=>!o.accommodation);
  if(accommodations.length){const label=document.createElement('label');label.className='label-alloggio';label.textContent='Alloggio';locked.appendChild(label);const line=document.createElement('div');line.className='readonly-box alloggio-box';line.textContent=accommodations.map(o=>o.name.replace(/^Alloggio:\s*/i,'')).join(', ')+(/^DM\d+$/.test(d.alloggio)?' ('+d.alloggio+')':'');locked.appendChild(line);}
  const options=document.createElement('div');options.className='options readonly';
  otherOptions.forEach(o=>{const label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.checked=true;input.disabled=true;label.style.cursor='default';label.append(input,document.createTextNode(' 🔒 '+o.name+(d.managed?' ('+formatEuro(o.price/100)+')':'')));options.appendChild(label);});locked.appendChild(options);
  if(d.shared){const note=document.createElement('p');note.className='field-hint';note.textContent='Quote comuni e versamenti della prenotazione sono ripartiti fra le persone iscritte insieme.';locked.appendChild(note);}
  if(d.locked.length){const note=document.createElement('div');note.className='nota-locked';note.textContent='Opzioni non più modificabili';locked.appendChild(note);}
  const grid=card.querySelector('.transfer-grid');grid.replaceChildren();
  for(const group of ['All’andata','Al ritorno','Trasferimenti']) {
    const services=d.services.filter(s=>(s.direction || 'Trasferimenti')===group);if(!services.length)continue;
    const block=document.createElement('div');block.className='options transfer-options';
    const title=document.createElement('div');title.className='transfer-group-title';title.textContent=group==='All’andata'?'🛫 '+group:group==='Al ritorno'?'🛬 '+group:group;block.appendChild(title);
    services.forEach(s=>{const label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.checked=s.selected;input.dataset.service=s.code;label.appendChild(input);label.append(document.createTextNode(' '+s.name+' ('+formatEuro(s.price/100)+')'));
      if(s.selected){const badge=document.createElement('span');badge.className='badge-preselected';badge.textContent='già incluso';label.appendChild(badge);}
      input.addEventListener('change',()=>{if(input.checked&&s.group)d.services.filter(other=>other.group===s.group&&other.code!==s.code).forEach(other=>{grid.querySelectorAll('input').forEach(el=>{if(el.dataset.service===other.code)el.checked=false;});});onOptionsChanged();});block.appendChild(label);});grid.appendChild(block);
  }
  card.querySelector('.transfer-heading').hidden=!d.services.length;grid.hidden=!d.services.length;
  card.querySelector('.loaded-data-section').style.display='block';
  if(d.email)emailInput.value=d.email;
  setCardStatus(card,'ok','Prenotazione caricata ✓');updateCardTitles();resetCalcState();
  if(data.partner?.persona && !persone.some(p=>p.loaded&&p.row===data.partner.row)) person.partnerIndex=loadPartnerCard(data.partner,false);
  setTimeout(()=>{if(!person.loaded)return;const first=grid.querySelector('input');if(first){grid.scrollIntoView({behavior:'smooth',block:'center'});first.focus({preventScroll:true});first.classList.add('tr-rf-focus-hint');setTimeout(()=>first.classList.remove('tr-rf-focus-hint'),1600);}},350);
}


// ─── GESTIONE CARD ────────────────────────────────────────────────────────────
function setSearchBtnVisible(card, visible) {
  const btn = card.querySelector('.btn-search-person');
  if (!btn) return;
  const col = btn.closest('.field-col');
  btn.style.display = visible ? '' : 'none';
  if (col) col.style.display = visible ? '' : 'none';
}

function bindLookupEvents(card, index) {
  if (card.dataset.lookupBound === 'true') return;

  const cognomeInp = card.querySelector('.person-cognome');
  const nomeInp    = card.querySelector('.person-nome');
  const searchBtn  = card.querySelector('.btn-search-person');

  const trigger = () => debouncedLookup(card, index, 0);
  const onEnter = e => { if (e.key === 'Enter') { e.preventDefault(); trigger(); } };

  searchBtn?.addEventListener('click', trigger);
  cognomeInp.addEventListener('keydown', onEnter);
  nomeInp.addEventListener('keydown', onEnter);

  cognomeInp.addEventListener('blur', () => {
    cognomeInp.value = capitalize(cognomeInp.value);
  });
  nomeInp.addEventListener('blur', () => {
    if (!nomeInp.hasAttribute('readonly'))
      nomeInp.value = capitalize(nomeInp.value);
  });

  // Modifica cognome → reset se non locked
  cognomeInp.addEventListener('input', () => {
    const p = persone.find(p => p.index === index);
    if (p?.loaded) return;
    cancelLookup(index);
    resetCard(card, index);
  });

  // Modifica nome → solo pulizia errore, non reset completo
  nomeInp.addEventListener('input', () => {
    const p = persone.find(p => p.index === index);
    if (p?.loaded) return;
    cancelLookup(index);
    if (p) p.loaded = false;
    setCardStatus(card, '', '');
    nomeInp.classList.remove('has-error');
  });

  card.dataset.lookupBound = 'true';
}

function addPersonCard() {
  const clone = tmpl.content.cloneNode(true);
  const card  = clone.querySelector('.person-card');
  const index = personCounter++;

  card.dataset.personIndex = index;
  if (persone.length > 0) card.classList.add('removable');

  // ID univoci accessibilità
  const cogInp   = card.querySelector('.person-cognome');
  const nomInp   = card.querySelector('.person-nome');
  const cogLbl   = card.querySelector('.person-cognome-label');
  const nomLbl   = card.querySelector('.person-nome-label');
  const statDiv  = card.querySelector('.person-status');
  const lockHelp = card.querySelector('.locked-options-help');
  const colLock  = card.querySelector('.colazione');
  const assLock  = card.querySelector('.assicurazione');

  cogInp.id = `cognome-${index}`;
  nomInp.id = `nome-${index}`;
  statDiv.id= `status-${index}`;

  cogLbl?.setAttribute('for', cogInp.id);
  nomLbl?.setAttribute('for', nomInp.id);
  cogInp.setAttribute('aria-describedby', statDiv.id);
  nomInp.setAttribute('aria-describedby', statDiv.id);

  if (lockHelp) {
    lockHelp.id = `lockedhelp-${index}`;
    colLock?.setAttribute('aria-describedby', lockHelp.id);
    assLock?.setAttribute('aria-describedby', lockHelp.id);
  }

  ['rf','pt','sc','fr'].forEach(k => {
    const chk = card.querySelector(`.tr-${k}`);
    const lbl = card.querySelector(`.label-tr-${k}`);
    if (chk) { chk.id = `p${index}_tr_${k}`; lbl?.setAttribute('for', chk.id); }
  });

  // Transfer → opzioni changed
  card.querySelectorAll('.transfer-options input[type="checkbox"]').forEach(chk => {
    chk.addEventListener('change', onOptionsChanged);
  });

  card.querySelector('.btn-remove').addEventListener('click', () => removeCard(index));
  card.querySelector('.btn-change').addEventListener('click', () => unlockCard(card, index));

  bindLookupEvents(card, index);
  container.appendChild(card);

  if (gasWarmupInProgress) setSearchButtonsDisabled(true);

  persone.push({
    index, nome: '', cognome: '', row: null,
    alloggio: '', siglaAlloggio: '', colazione: false, assicurazione: false,
    loaded: false, cardEl: card,
  });

  updateCardTitles();
  resetCalcState();

  // Focus primo input della nuova card
  if (persone.length > 1) card.querySelector('.person-cognome').focus();
}

function removeCard(index) {
  const i = persone.findIndex(p => p.index === index);
  if (i === -1) return;
  cancelLookup(index);

  const partnerIdx = persone[i].partnerIndex;
  persone[i].cardEl?.remove();
  persone.splice(i, 1);

  if (partnerIdx != null) {
    const pi = persone.findIndex(p => p.index === partnerIdx);
    if (pi !== -1) { persone[pi].cardEl?.remove(); persone.splice(pi, 1); }
  }

  persone.forEach(p => { if (p.partnerIndex === index) p.partnerIndex = null; });

  updateCardTitles();
  resetCalcState();
}


function unlockCard(card,index) {
  const p=persone.find(p=>p.index===index);if(!p)return;
  cancelLookup(index);if(p.partnerIndex!=null){removeCard(p.partnerIndex);p.partnerIndex=null;}
  Object.assign(p,{loaded:false,model:null,row:null,nome:'',cognome:'',candidate:null});
  card.querySelectorAll('.person-cognome,.person-nome').forEach(el=>{el.readOnly=false;el.value='';el.classList.remove('has-error');});
  card.querySelector('.loaded-data-section').style.display='none';card.classList.remove('locked');
  card.querySelector('.person-cognome').parentElement.querySelector('.field-hint').style.visibility='visible';
  setSearchBtnVisible(card,true);setCardStatus(card,'','');updateCardTitles();resetCalcState();card.querySelector('.person-cognome').focus();
}

function resetCard(card,index) {
  const p=persone.find(p=>p.index===index);if(!p)return;
  cancelLookup(index);Object.assign(p,{loaded:false,model:null,row:null,candidate:null});
  card.querySelector('.loaded-data-section').style.display='none';card.classList.remove('locked');
  setCardStatus(card,'','');updateCardTitles();resetCalcState();
}

function updateCardTitles() {
  persone.forEach((p, i) => {
    if (!p.cardEl) return;
    const title = p.cardEl.querySelector('.card-title');
    if (!title) return;

    const loadedLabel   = p.isPartner
      ? '👥 Partner camera'
      : (i === 0 ? 'La tua prenotazione' : 'Partecipante aggiuntivo');
    const unloadedLabel = p.isPartner
      ? '👥 Partner camera'
      : 'Cerca la tua prenotazione';

    title.textContent = p.loaded ? loadedLabel : unloadedLabel;

    p.cardEl.classList.toggle('removable',
      i === 0 ? persone.length > 1 : true);
  });
}


// ─── CARICAMENTO PARTNER ──────────────────────────────────────────────────────
function loadPartnerCard(data, shouldScroll=true) {
  if(persone.some(p=>p.loaded&&p.row===data.row))return null;
  addPersonCard();const p=persone[persone.length-1];p.isPartner=true;
  applyResult(p.cardEl,p,data,data.persona.nome,data.persona.cognome);
  setCardStatus(p.cardEl,'ok','👫 Compagno/a di camera aggiunto automaticamente.');
  setGlobalStatus('ok','👫 Camera doppia: abbiamo caricato anche il/la tuo/a compagno/a di stanza.');
  setTimeout(()=>{p.cardEl.classList.add('partner-highlight');if(shouldScroll)p.cardEl.scrollIntoView({behavior:'smooth',block:'center'});},200);
  return p.index;
}


// ─── CALCOLO ──────────────────────────────────────────────────────────────────
function getTransfer(card) {
  const choices={};card.querySelectorAll('[data-service]').forEach(el=>choices[el.dataset.service]=el.checked?1:0);return choices;
}

const EMAIL_DOMAIN_CORRECTIONS = {
  'icluoud.com': 'icloud.com',
};

function normalizeEmailAddress(rawEmail) {
  const trimmed = String(rawEmail || '').trim().toLowerCase();
  const atIndex = trimmed.lastIndexOf('@');
  if (atIndex <= 0 || atIndex === trimmed.length - 1) {
    return { email: trimmed, corrected: false };
  }

  const localPart = trimmed.slice(0, atIndex);
  const domain = trimmed.slice(atIndex + 1);
  const correctedDomain = EMAIL_DOMAIN_CORRECTIONS[domain] || domain;

  return {
    email: `${localPart}@${correctedDomain}`,
    corrected: correctedDomain !== domain,
    originalDomain: domain,
    correctedDomain,
  };
}

function getValidatedEmail() {
  const normalized = normalizeEmailAddress(emailInput?.value || '');
  const email = normalized.email;

  if (normalized.corrected && emailInput) {
    emailInput.value = email;
    setGlobalStatus('ok', `Dominio email corretto automaticamente: ${escapeHtml(normalized.originalDomain)} → ${escapeHtml(normalized.correctedDomain)}.`);
  }

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    setGlobalStatus('error', "Inserisci un'email valida prima di confermare.");
    emailInput?.focus();
    return null;
  }

  return email;
}


// ─── CONFERMA E INVIA (sostituisce calcolaTotale + salvaEInvia) ───────────────
async function confermaEInvia() {
  if(_calcInProgress)return;
  const loaded=persone.filter(p=>p.loaded);if(!loaded.length)return;
  const email=getValidatedEmail();if(!email)return;
  _calcInProgress=true;setCalcButtons({disabled:true});
  const busyControls=[...document.querySelectorAll('#personsContainer input:not(:disabled),#personsContainer button:not(:disabled),#btnAddPerson,#emailInput')];
  busyControls.forEach(el=>el.disabled=true);
  try {
    const people=loaded.map(p=>({row:p.row,token:p.model.token,version:p.model.version,services:getTransfer(p.cardEl)}));
    const hash=JSON.stringify({people,email});
    if(!_pendingBalanceSave||_pendingBalanceSave.hash!==hash){
      setGlobalStatus('loading','<span class="spinner"></span>Controllo degli importi…');
      const preview=await gasPost({action:'preview',persone:people,email});if(!preview.success)throw new Error(preview.message||preview.error);
      let body='<p>Confermi la prenotazione di <strong>'+loaded.map(p=>escapeHtml(p.cognome+' '+p.nome)).join(', ')+'</strong>?</p>';
      body+='<ul>'+preview.people.flatMap(p=>p.lines.map(l=>'<li>'+escapeHtml(p.name+': '+l.name)+' — '+formatEuro(l.price/100)+'</li>')).join('')+'</ul>';
      if(preview.deposit)body+='<p>Caparra ancora da versare: <strong>'+formatEuro(preview.depositDue/100)+'</strong><br>Saldo ancora da versare: <strong>'+formatEuro(preview.saldoDue/100)+'</strong></p>';
      body+='<p>Totale da versare: <strong>'+formatEuro(preview.balance/100)+'</strong></p><p>Verrà inviata un’email di riepilogo a <strong>'+escapeHtml(email)+'</strong>.</p>';
      setGlobalStatus('','');if(!await showConfirmModal('Riepilogo e conferma',body))return;
      _pendingBalanceSave={hash,action:'salvaTransfer',persone:people,email,fingerprint:preview.fingerprint,requestId:crypto.randomUUID()};
    }
    setGlobalStatus('loading','<span class="spinner"></span>Attendere prego…');
    const result=await gasPost(_pendingBalanceSave);if(!result.success){_pendingBalanceSave=null;throw new Error(result.message||result.error);}
    lastCalcResult=result;_pendingBalanceSave=null;
    loaded.forEach(p=>{p.model.version=result.versions[p.row];p.model.services.forEach(s=>s.selected=!!getTransfer(p.cardEl)[s.code]);});
    renderLoadedSummary();const lines=new Map();result.people.forEach(p=>p.lines.forEach(l=>lines.set(l.name,(lines.get(l.name)||0)+l.price)));
    showAmounts(result,lines);document.getElementById('totalBoxLabel').textContent='Totale da versare:';
    setBookingStatus('confirmed');setCalcButtons({text:LABELS.confirmed,disabled:true});clearCalcButtonsHideTimer();_calcButtonsHideTimer=setTimeout(()=>setCalcButtons({display:'none'}),1200);
    hideRecalcBanner();emailSection.style.display='none';
    btnPay.disabled=false;actionSection.style.display=result.balance>0&&MIBalance.methods.includes('CARD')?'block':'none';
    setGlobalStatus(result.emailQueued?'ok':'error',result.emailQueued?'✅ Scelte confermate — riepilogo in invio':'Scelte confermate. L’invio email è in modalità anteprima: il riepilogo è disponibile alla segreteria.');
    document.getElementById('ibanIntro').textContent=result.emailQueued?'Le tue scelte sono state registrate. Riceverai una email con il riepilogo della prenotazione e le istruzioni per il pagamento.':'Le tue scelte sono state registrate. L’email non viene inviata perché la segreteria ha attivato la modalità anteprima.';
    document.getElementById('bankDetails').hidden=!(result.balance>0&&MIBalance.methods.includes('BANK_TRANSFER'));
    openModal('ibanPopup');
  } catch(error){setGlobalStatus('error','Impossibile confermare: '+escapeHtml(error.message));}
  finally{_calcInProgress=false;busyControls.forEach(el=>el.disabled=false);if(!lastCalcResult)setCalcButtons({disabled:false});}
}

// ─── REIMPOSTA TUTTO ─────────────────────────────────────────────────────────
function resetAll() {
  // Rimuove tutte le card tranne la prima, poi resetta quella
  while (persone.length > 1) removeCard(persone[persone.length - 1].index);
  if (persone.length === 1) unlockCard(persone[0].cardEl, persone[0].index);
  if (emailInput) emailInput.value = '';
  document.getElementById('resetLink').style.display = 'none';
  const lbl = document.getElementById('totalBoxLabel');
  if (lbl) lbl.textContent = 'Saldo stimato da versare:';
  setBookingStatus(null);
}

function resetCalcState() {
  clearCalcButtonsHideTimer();lastCalcResult=null;_pendingBalanceSave=null;btnPay.disabled=true;actionSection.style.display='none';
  const loaded=persone.filter(p=>p.loaded);summaryBox.textContent=loaded.length?'':LABELS.lookupHint;
  if(loaded.length){setCalcButtons({text:LABELS.calc,display:'block',disabled:false});updateLiveBreakdown();setBookingStatus('editing');}
  else{setCalcButtons({display:'none',disabled:false});setBookingStatus(null);breakdownBox.style.display='none';totalBox.style.display='none';emailSection.style.display='none';}
  hideRecalcBanner();setGlobalStatus('','');
}


// ─── SALVA TRANSFER ──────────────────────────────────────────────────────────


// salvaEInvia() rimossa — il flusso è ora gestito da confermaEInvia()
// che calcola, salva e invia email in un unico passaggio.


// ─── PAGA CON CARTA ──────────────────────────────────────────────────────────
async function openPayPopup() {
  if(!lastCalcResult)return;
  document.getElementById('popupImporto').textContent=formatEuro(lastCalcResult.balance/100);
  document.getElementById('popupCausale').textContent=lastCalcResult.causale;
  openModal('payPopup');
}

async function copyPopupField(fieldId, btn) {
  const val = document.getElementById(fieldId).textContent;
  try {
    await navigator.clipboard.writeText(val);
    btn.textContent = '✅';
    btn.classList.add('copied');
    setTimeout(() => { btn.textContent = '📋'; btn.classList.remove('copied'); }, 2000);
  } catch {
    prompt('Copia manualmente:', val);
  }
}

function openTinaba() {
  window.open(TINABA_URL, '_blank');
}

function onGlobalEnterShortcut(e) {
  if (e.key !== 'Enter' || e.defaultPrevented) return;
  if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
  if (_activeModal) return;
  if (!persone.some(p => p.loaded)) return;

  const activeCalcBtn = calcButtons.find(btn => {
    if (!btn || btn.disabled) return false;
    if (btn.offsetParent === null) return false;
    return getComputedStyle(btn).display !== 'none';
  });

  if (!activeCalcBtn) return;
  e.preventDefault();
  activeCalcBtn.click();
}


let _pendingBalanceSave = null;
function showAmounts(result,lines) {
  const box=document.getElementById('costLines');box.replaceChildren();
  if(lines)for(const [name,price]of lines){const line=document.createElement('div');line.className='breakdown-row';const label=document.createElement('span'),amount=document.createElement('span');label.textContent=name;amount.textContent=formatEuro(price/100);line.append(label,amount);box.appendChild(line);}
  setTextFlash('totOpzioni',formatEuro(result.total/100));setTextFlash('caparraVersata',formatEuro((result.depositPaid||0)/100));
  setTextFlash('caparraDaVersare',formatEuro((result.depositDue||0)/100));setTextFlash('quotaSaldo',formatEuro((result.saldoDue||0)/100));
  setTextFlash('versatoEffettivo',formatEuro(result.paid/100));setTextFlash('saldoDaVersare',formatEuro(result.balance/100));
  document.getElementById('rowCaparra').style.display=result.deposit?'':'none';document.getElementById('rowCaparraDue').style.display=result.deposit?'':'none';document.getElementById('rowSaldo').style.display=result.deposit?'':'none';
  breakdownBox.style.display='block';totalBox.style.display='flex';
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
addPersonCard();

btnAdd.addEventListener('click', addPersonCard);
btnCalc.addEventListener('click', confermaEInvia);
btnCalcDesktop?.addEventListener('click', confermaEInvia);
btnPay.addEventListener('click', openPayPopup);
document.getElementById('popupOpenBtn')?.addEventListener('click', openTinaba);
document.getElementById('ibanCloseBtn')?.addEventListener('click', closeModal);
document.addEventListener('keydown', onGlobalEnterShortcut);

// Warm-up dopo che la prima card è stata creata e i bottoni esistono nel DOM
warmupGas();
