document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.mi-event-wizard');
  if (form) {
    const steps = [...form.querySelectorAll('.mi-wizard-step')];
    const back = form.querySelector('[data-mi-back]');
    const next = form.querySelector('[data-mi-next]');
	const coverImage = form.querySelector('[name="cover_image"][data-mi-max-bytes]');
	const validateCoverImage = () => {
	  if (!coverImage) return true;
	  const file = coverImage.files?.[0];
	  const maximum = Number.parseInt(coverImage.dataset.miMaxBytes || '0', 10);
	  coverImage.setCustomValidity(file && maximum && file.size > maximum ? 'L’immagine in evidenza non può superare 2 MB.' : '');
	  return coverImage.reportValidity();
	};
	coverImage?.addEventListener('change', validateCoverImage);
    let index = 0;
    const show = () => {
      steps.forEach((step, stepIndex) => step.classList.toggle('is-active', stepIndex === index));
      back.disabled = index === 0;
      next.hidden = index === steps.length - 1;
      if (index === steps.length - 1) {
        const value = (name) => form.querySelector(`[name="${name}"]`)?.value || 'Da definire';
        const review = form.querySelector('[data-mi-review]');
        if (review) review.innerHTML = `<strong>${value('title')}</strong><span>Inizio: ${value('starts_at')}</span><span>Chiusura iscrizioni: ${value('closes_at')}</span><span>Posti: ${value('capacity')}</span>`;
      }
    };
    next.addEventListener('click', () => {
      const fields = [...steps[index].querySelectorAll('[required]')];
      if (fields.some((field) => !field.reportValidity())) return;
	  if (coverImage && steps[index].contains(coverImage) && !validateCoverImage()) return;
      index = Math.min(steps.length - 1, index + 1);
      show();
    });
    back.addEventListener('click', () => {
      index = Math.max(0, index - 1);
      show();
    });
    const overnight = form.querySelector('[data-mi-overnight]');
    const rooms = form.querySelector('[data-mi-accommodations]');
    const updateOvernight = () => {
      rooms.hidden = !overnight.checked;
      if (!overnight.checked) rooms.querySelectorAll('input').forEach((input) => { input.checked = false; });
    };
    if (overnight) {
      overnight.addEventListener('change', updateOvernight);
      updateOvernight();
    }
	form.querySelectorAll('[data-mi-service-fee]').forEach((service) => {
	  const price = service.closest('.mi-service-fee')?.querySelector('input[name^="service_price"]');
	  const updateService = () => {
		if (!price) return;
		price.disabled = !service.checked;
		price.required = service.checked;
		if (!service.checked) price.value = '';
	  };
	  service.addEventListener('change', updateService);
	  updateService();
	});
    form.querySelectorAll('[data-mi-required]').forEach((required) => required.addEventListener('change', () => {
      const enabled = form.querySelector(`[data-mi-field="${required.dataset.miRequired}"]`);
      if (required.checked && enabled) enabled.checked = true;
    }));
    form.querySelectorAll('[data-mi-field]').forEach((enabled) => enabled.addEventListener('change', () => {
      const required = form.querySelector(`[data-mi-required="${enabled.dataset.miField}"]`);
      if (!enabled.checked && required) required.checked = false;
    }));
    const pricing = form.querySelector('[data-mi-pricing]');
    const fixedPrice = form.querySelector('[data-mi-fixed-price]');
    const updatePricing = () => { if (pricing && fixedPrice) fixedPrice.hidden = pricing.value !== 'FIXED'; };
    pricing?.addEventListener('change', updatePricing);
    updatePricing();
    const economic = form.querySelector('[data-mi-economic]');
    const payment = form.querySelector('[data-mi-payment]');
    const deposit = form.querySelector('[data-mi-deposit]');
    const updateEconomic = () => {
      const collects = ['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(economic?.value);
      if (payment) payment.hidden = !collects;
      if (deposit) deposit.hidden = economic?.value !== 'DEPOSIT_BALANCE';
    };
    economic?.addEventListener('change', updateEconomic);
    updateEconomic();
    const opensAt = form.querySelector('[data-mi-opens]');
    const closesAt = form.querySelector('[data-mi-closes]');
    const startsAt = form.querySelector('[data-mi-starts]');
    const updateDateLimits = () => {
      if (!closesAt || !startsAt) return;
      closesAt.min = opensAt?.value || '';
      const lowerBounds = [startsAt.dataset.miToday, opensAt?.value, closesAt.value].filter(Boolean);
      lowerBounds.sort();
      startsAt.min = lowerBounds[lowerBounds.length - 1] || startsAt.dataset.miToday;
    };
    opensAt?.addEventListener('change', updateDateLimits);
    closesAt?.addEventListener('change', updateDateLimits);
    updateDateLimits();
    show();
  }

  const selectedEvent = document.querySelector('[data-mi-selected-event]');
  if (selectedEvent) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.requestAnimationFrame(() => selectedEvent.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' }));
  }

  document.querySelectorAll('.mi-registrations-toolbar select[data-mi-auto-submit]').forEach((filter) => {
    filter.addEventListener('change', () => {
      const toolbar = filter.closest('form');
      if (!toolbar) return;
	  if (toolbar.dataset.miSubmitting === '1') return;
	  toolbar.dataset.miSubmitting = '1';
      toolbar.requestSubmit();
    });
  });

  const bookingLinks = [...document.querySelectorAll('[data-mi-portal-booking-open]')]
    .filter((link, index, links) => links.findIndex((candidate) => candidate.href === link.href) === index);
  const inlineDetail = document.getElementById('mi-portal-booking-detail');
  if (!bookingLinks.length && !inlineDetail) return;

  const listUrl = new URL(window.location.href);
  listUrl.searchParams.delete('mi_portal_booking');
  const modal = document.createElement('div');
  modal.className = 'mi-portal-modal';
  modal.hidden = true;
  modal.innerHTML = '<div class="mi-portal-modal__backdrop" data-mi-portal-booking-close></div><section class="mi-portal-modal__dialog" role="dialog" aria-modal="true" aria-label="Scheda prenotazione"><button type="button" class="mi-portal-modal__close" data-mi-portal-booking-close aria-label="Chiudi la scheda">×</button><button type="button" class="mi-portal-modal__nav mi-portal-modal__nav--previous" data-mi-portal-booking-previous aria-label="Scheda precedente" title="Scheda precedente">◀</button><button type="button" class="mi-portal-modal__nav mi-portal-modal__nav--next" data-mi-portal-booking-next aria-label="Scheda successiva" title="Scheda successiva">▶</button><div class="mi-portal-modal__content" aria-live="polite"></div></section>';
  document.body.append(modal);
  const content = modal.querySelector('.mi-portal-modal__content');
  const closeButton = modal.querySelector('.mi-portal-modal__close');
  const previousButton = modal.querySelector('[data-mi-portal-booking-previous]');
  const nextButton = modal.querySelector('[data-mi-portal-booking-next]');
  let previousFocus = null;
  let activeRequest = null;
  let activeBookingIndex = -1;
  const detailCache = new Map();

  const parseDetail = (html) => new DOMParser().parseFromString(html, 'text/html').getElementById('mi-portal-booking-detail');

  const updateNavigation = () => {
    previousButton.disabled = activeBookingIndex <= 0;
    nextButton.disabled = activeBookingIndex < 0 || activeBookingIndex >= bookingLinks.length - 1;
  };
  const showBooking = (detail, url = '', historyMode = '') => {
    content.replaceChildren(detail);
    modal.hidden = false;
    document.body.classList.add('mi-portal-modal-open');
    closeButton.focus();
    updateNavigation();
    if ('push' === historyMode && url) window.history.pushState({}, '', url);
    if ('replace' === historyMode && url) window.history.replaceState({}, '', url);
  };
  const closeBooking = (replaceHistory = true) => {
    if (modal.hidden) return;
    activeRequest?.abort();
    activeRequest = null;
    modal.hidden = true;
    content.replaceChildren();
    document.body.classList.remove('mi-portal-modal-open');
    if (replaceHistory) window.history.replaceState({}, '', listUrl);
    previousFocus?.focus();
  };
  const openBooking = async (link, historyMode = 'push') => {
	activeRequest?.abort();
	previousFocus = link;
	activeBookingIndex = bookingLinks.indexOf(link);
	updateNavigation();
	const cachedDetail = detailCache.get(link.href);
	if (cachedDetail) {
	  const detail = parseDetail(cachedDetail);
	  if (detail) {
		showBooking(detail, link.href, historyMode);
		return;
	  }
	  detailCache.delete(link.href);
	}
	const request = new AbortController();
	activeRequest = request;
	modal.hidden = false;
    document.body.classList.add('mi-portal-modal-open');
    content.innerHTML = '<p class="mi-portal-modal__loading">Apertura della prenotazione…</p>';
    closeButton.focus();
    try {
      const response = await fetch(link.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
      if (!response.ok) throw new Error('detail_unavailable');
	  const detail = parseDetail(await response.text());
	  if (!detail) throw new Error('detail_missing');
	  detailCache.set(link.href, detail.outerHTML);
	  showBooking(detail, link.href, historyMode);
    } catch (error) {
      if ('AbortError' === error.name) return;
      window.location.assign(link.href);
    } finally {
      if (activeRequest === request) activeRequest = null;
    }
  };

  bookingLinks.forEach((link) => link.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openBooking(link);
  }));
  const moveBooking = (offset) => {
    const targetIndex = activeBookingIndex + offset;
    if (targetIndex < 0 || targetIndex >= bookingLinks.length) return;
    openBooking(bookingLinks[targetIndex], 'replace');
  };
  previousButton.addEventListener('click', () => moveBooking(-1));
  nextButton.addEventListener('click', () => moveBooking(1));
  modal.addEventListener('click', (event) => {
    if (event.target.closest('[data-mi-portal-booking-close]')) closeBooking();
  });
  document.addEventListener('keydown', (event) => {
    if ('Escape' === event.key && !modal.hidden) closeBooking();
    const editing = event.target.closest('input, textarea, select, [contenteditable="true"]');
    if (!modal.hidden && !editing && 'ArrowLeft' === event.key) { event.preventDefault(); moveBooking(-1); }
    if (!modal.hidden && !editing && 'ArrowRight' === event.key) { event.preventDefault(); moveBooking(1); }
    if ('Tab' !== event.key || modal.hidden) return;
    const focusable = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
      .filter((element) => !element.hidden && null !== element.offsetParent);
    if (!focusable.length) {
      event.preventDefault();
      closeButton.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  window.addEventListener('popstate', () => closeBooking(false));

  if (inlineDetail) {
    inlineDetail.remove();
    activeBookingIndex = bookingLinks.findIndex((link) => new URL(link.href).searchParams.get('mi_portal_booking') === new URL(window.location.href).searchParams.get('mi_portal_booking'));
    showBooking(inlineDetail);
  }
});
