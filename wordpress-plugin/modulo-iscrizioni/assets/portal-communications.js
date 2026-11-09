// Suggerimenti locali e bozze distinte per tipo.
(() => {
  document.querySelectorAll('.mi-communication-test, .mi-communication-send').forEach((form) => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
        button.textContent = form.classList.contains('mi-communication-test') ? 'Invio della prova in corso…' : 'Preparazione in corso…';
      });
    });
  });
  const form = document.querySelector('[data-mi-communication-messages]');
  if (!form) return;
  const select = form.querySelector('[data-mi-communication-type]');
  const field = form.querySelector('[data-mi-communication-message]');
  const help = form.querySelector('[data-mi-communication-help]');
  const reset = form.querySelector('[data-mi-communication-reset]');
  if (!select || !field || !help || !reset) return;
  const drafts = new Map();
  let activeType = select.value;
  const saveCurrent = () => { if (activeType) drafts.set(activeType, field.value); };
  const showSelected = (restore = false) => {
    const option = select.selectedOptions[0];
    field.value = !restore && drafts.has(select.value) ? drafts.get(select.value) : (option?.dataset.miMessage || '');
    help.textContent = option?.dataset.miHelp || 'Scrivi il messaggio per gli iscritti.';
    reset.hidden = !option?.dataset.miMessage;
    saveCurrent();
  };
  saveCurrent();
  reset.hidden = !select.selectedOptions[0]?.dataset.miMessage;
  select.addEventListener('change', () => { saveCurrent(); activeType = select.value; showSelected(); });
  field.addEventListener('input', saveCurrent);
  reset.addEventListener('click', () => showSelected(true));

  const events = form.querySelector('select[name="event_id"]');
  const status = form.querySelector('[data-mi-events-help]');
  if (!events || !status) return;
  let showingPast = form.dataset.eventsPast === '1';
  const cache = new Map([[showingPast, [...events.options].filter((option) => option.value && option.value !== '__mi_toggle_events__').map((option) => ({id: option.value, title: option.textContent}))]]);
  const renderEvents = (items) => {
    events.replaceChildren(new Option('Scegli un evento', ''));
    items.forEach((item) => events.add(new Option(item.title, String(item.id))));
    events.add(new Option(showingPast ? 'Torna agli eventi attivi' : 'Visualizza eventi passati', '__mi_toggle_events__'));
  };
  events.addEventListener('change', async () => {
    if (events.value !== '__mi_toggle_events__') return;
    events.value = '';
    const target = !showingPast;
    if (cache.has(target)) { showingPast = target; renderEvents(cache.get(target)); status.textContent = ''; return; }
    events.disabled = true;
    status.textContent = 'Caricamento degli eventi passati…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    const data = new FormData();
    data.set('action', 'mi_communication_events');
    data.set('nonce', form.dataset.eventsNonce || '');
    data.set('past', target ? '1' : '0');
    try {
      const response = await fetch(form.dataset.eventsAjax, {method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal});
      const result = await response.json();
      if (!response.ok || !result.success || !Array.isArray(result.data.events)) throw new Error(result.data?.message || 'Elenco non disponibile. Riprova.');
      cache.set(target, result.data.events);
      showingPast = target;
      renderEvents(result.data.events);
      status.textContent = result.data.events.length ? 'Eventi caricati: scegli dalla tendina.' : 'Non ci sono eventi in questo elenco.';
    } catch (error) { status.textContent = error.name === 'AbortError' ? 'Il caricamento ha impiegato troppo tempo. Riprova.' : error.message; }
    finally { clearTimeout(timeout); events.disabled = false; }
  });
})();
