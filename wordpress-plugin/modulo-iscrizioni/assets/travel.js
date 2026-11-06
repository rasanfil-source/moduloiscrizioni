(function () {
  'use strict';
  const money = cents => new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
  function capture(row) {
    const host = row.querySelector('[data-mi-travel]');
    if (!host) return undefined;
    const value = key => host.querySelector(`[data-travel="${key}"]`)?.value || '';
    const select = host.querySelector('[data-travel="driver_id"]');
    return { mode: value('mode'), seats: value('seats'), origin: value('mode') === 'NEED' ? value('wanted_origin') : value('origin'), departure: value('departure'), price: value('price'), fallback: value('fallback'), driver_id: Number(value('driver_id')) || 0, price_cents: Number(select?.selectedOptions[0]?.dataset.priceCents) || 0, waitlist: Boolean(host.querySelector('[data-travel="waitlist"]')?.checked) };
  }
  function render(config, previous = {}, index) {
    const host = document.createElement('fieldset');
    host.dataset.miTravel = '';
    host.className = 'mi-registration__travel';
    const legend = document.createElement('legend'); legend.textContent = 'Come raggiungerai l’evento?'; host.append(legend);
    function note(text, parent = host) { const p = document.createElement('p'); p.textContent = text; parent.append(p); return p; }
    function field(label, key, type = 'text', parent = host) {
      const wrap = document.createElement('label'); wrap.append(document.createTextNode(label));
      const input = document.createElement(type === 'select' ? 'select' : 'input');
      if (type !== 'select') input.type = type;
      input.dataset.travel = key; input.name = `travel-${index}-${key}`;
      wrap.append(input); parent.append(wrap); return input;
    }
    function option(select, value, label) { const o = document.createElement('option'); o.value = value; o.textContent = label; select.append(o); return o; }
    const mode = field('Modalità di spostamento', 'mode', 'select');
    [['', 'Scegli come viaggiare'], ['OWN', 'Mi organizzo autonomamente'], ['PUBLIC', 'Utilizzo i mezzi pubblici'], ['DRIVER', 'Vengo in auto: indica quanti posti offri, anche zero'], ['NEED', 'Cerco un passaggio in auto']].forEach(([v, t]) => option(mode, v, t));
    mode.value = previous.mode || ''; mode.required = true;
    if (config.event.travel.instructions) note(config.event.travel.instructions);
    note('I passaggi comprendono andata e ritorno. Il contributo si versa direttamente al conducente ed è separato dalle quote dell’evento.');
    const driver = document.createElement('div'); host.append(driver);
    const seats = field('Posti disponibili per altri iscritti, esclusi conducente e accompagnatori già previsti', 'seats', 'select', driver);
    for (let n = 0; n <= 8; n++) option(seats, String(n), n === 0 ? '0 — Non offro posti' : String(n));
    seats.value = String(previous.seats || 0);
    const details = document.createElement('div'); driver.append(details);
    const origin = field('Località e punto di partenza', 'origin', 'text', details); origin.maxLength = 180; origin.value = previous.mode === 'DRIVER' ? previous.origin || '' : '';
    const departure = field('Data e ora di partenza (gg/mm/aaaa hh:mm)', 'departure', 'text', details); departure.placeholder = 'gg/mm/aaaa hh:mm'; departure.value = previous.departure || '';
    const price = field('Contributo per persona, andata e ritorno (€)', 'price', 'text', details); price.inputMode = 'decimal'; price.value = config.event.travel.price_mode === 'FIXED' ? String(config.event.travel.price_cents / 100).replace('.', ',') : previous.price || '0'; price.readOnly = config.event.travel.price_mode === 'FIXED';
    const need = document.createElement('div'); host.append(need);
    const rides = field('Passaggi disponibili', 'driver_id', 'select', need);
    const status = note('', need); status.setAttribute('role', 'status');
    const reload = document.createElement('button'); reload.type = 'button'; reload.textContent = 'Aggiorna disponibilità'; need.append(reload);
    const wait = field('Se non scelgo un passaggio, desidero entrare nella lista d’attesa auto', 'waitlist', 'checkbox', need); wait.checked = Boolean(previous.waitlist);
    const wantedOrigin = field('Località da cui cerchi il passaggio (per l’abbinamento automatico usa la stessa località indicata dai conducenti)', 'wanted_origin', 'text', need); wantedOrigin.maxLength = 180; wantedOrigin.value = previous.mode === 'NEED' ? previous.origin || '' : '';
    const fallback = field('Se non si trova un passaggio', 'fallback', 'select', need);
    option(fallback, '', 'Scegli come partecipare'); option(fallback, 'REQUIRED', 'Parteciperò solo se trovo un passaggio'); option(fallback, 'ANYWAY', 'Parteciperò comunque e mi organizzerò diversamente'); fallback.value = previous.fallback || '';
    note('Le proposte successive riservano il posto per 24 ore dall’invio. Ti saranno comunicati partenza e contributo prima dell’accettazione. L’attesa auto è distinta dalla lista d’attesa dell’evento.', need);
    let loading = false;
    async function load() {
      if (loading) return;
      loading = true; reload.disabled = true;
      const selected = rides.value || String(previous.driver_id || 0);
      status.textContent = 'Caricamento dei passaggi…';
      try {
        const endpoint = config.endpoint.replace(/\/registrations\/?$/, '/travel');
        const response = await fetch(endpoint, { cache: 'no-store', credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Disponibilità non disponibile.');
        rides.replaceChildren(); option(rides, '0', 'Non scelgo un passaggio disponibile');
        for (const ride of data.rides || []) {
          const o = option(rides, String(ride.id), `${ride.origin} · ${ride.departure} · ${ride.seats} posti · ${money(ride.price_cents)} A/R per persona`);
          o.dataset.priceCents = String(ride.price_cents);
        }
        if (Array.from(rides.options).some(o => o.value === selected)) rides.value = selected;
        status.textContent = data.rides?.length ? 'La disponibilità sarà verificata al momento dell’iscrizione.' : 'Al momento non ci sono posti disponibili. Puoi chiedere di entrare in lista d’attesa auto.';
      } catch (error) {
        rides.replaceChildren(); option(rides, '0', 'Nessun passaggio selezionato');
        status.textContent = `${error.message} Puoi riprovare o richiedere la lista d’attesa auto.`;
      } finally { loading = false; reload.disabled = false; update(); }
    }
    function toggle(parent, visible) {
      parent.hidden = !visible;
      parent.querySelectorAll('input,select,button').forEach(input => { input.disabled = !visible; });
    }
    function update() {
      toggle(driver, mode.value === 'DRIVER'); toggle(details, mode.value === 'DRIVER' && Number(seats.value) > 0); toggle(need, mode.value === 'NEED');
      origin.required = departure.required = price.required = mode.value === 'DRIVER' && Number(seats.value) > 0;
      fallback.required = mode.value === 'NEED';
      wantedOrigin.required = mode.value === 'NEED' && !Number(rides.value);
      wait.required = mode.value === 'NEED' && !Number(rides.value);
    }
    mode.addEventListener('change', () => { update(); if (mode.value === 'NEED') load(); });
    seats.addEventListener('change', update); rides.addEventListener('change', update); reload.addEventListener('click', load);
    option(rides, '0', 'Non scelgo un passaggio disponibile'); update();
    if (mode.value === 'NEED') load();
    return host;
  }
  globalThis.MITravel = { capture, render };
}());
