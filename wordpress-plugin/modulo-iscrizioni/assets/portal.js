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
    let index = Math.min(steps.length - 1, Math.max(0, Number.parseInt(form.dataset.miInitialStep || '0', 10) || 0));
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
    form.querySelector('button[type="submit"]')?.addEventListener('click', (event) => {
      const invalid = [...form.elements].find((field) => !field.disabled && field.validity && !field.validity.valid);
      if (!invalid) return;
      event.preventDefault();
      const invalidStep = steps.findIndex((step) => step.contains(invalid));
      if (invalidStep >= 0) index = invalidStep;
      show();
      invalid.reportValidity();
    });
    const pricing = form.querySelector('[data-mi-pricing]');
    const overnight = form.querySelector('[data-mi-overnight]');
    const rooms = form.querySelector('[data-mi-accommodations]');
    const updateOvernight = () => {
	  const servicePricing = pricing?.value === 'NONE';
      rooms.hidden = !servicePricing || !overnight.checked;
      if (!overnight.checked) rooms.querySelectorAll('input').forEach((input) => { input.checked = false; });
	  rooms.querySelectorAll('[data-mi-accommodation]').forEach((accommodation) => {
		const price = accommodation.closest('.mi-accommodation-fee')?.querySelector('input[name^="accommodation_price"]');
		if (!price) return;
		const active = servicePricing && overnight.checked && accommodation.checked;
		price.disabled = !active;
		price.required = active;
		if (!accommodation.checked) price.value = '';
	  });
    };
    if (overnight) {
      overnight.addEventListener('change', updateOvernight);
      updateOvernight();
    }
	form.querySelectorAll('[data-mi-accommodation]').forEach((accommodation) => accommodation.addEventListener('change', updateOvernight));
	const serviceUpdaters = [];
	form.querySelectorAll('[data-mi-service-fee]').forEach((service) => {
	  const price = service.closest('.mi-service-fee')?.querySelector('input[name^="service_price"]');
	  const updateService = () => {
		if (!price) return;
		const active = pricing?.value === 'NONE' && service.checked;
		price.disabled = !active;
		price.required = active;
		if (!service.checked) price.value = '';
	  };
	  serviceUpdaters.push(updateService);
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
    const fixedPrice = form.querySelector('[data-mi-fixed-price]');
    const pricingLabel = pricing?.closest('label');
    const serviceFees = [...form.querySelectorAll('.mi-service-fee')];
    const serviceIntroduction = serviceFees.length ? [serviceFees[0].previousElementSibling, serviceFees[0].previousElementSibling?.previousElementSibling] : [];
    const servicePricingNodes = [overnight?.closest('label'), rooms, ...serviceIntroduction, ...serviceFees].filter(Boolean);
    if (pricingLabel && overnight?.closest('label')) {
	  pricingLabel.firstChild.textContent = 'Come sarà l’evento?';
	  overnight.closest('.mi-wizard-step')?.insertBefore(pricingLabel, overnight.closest('label'));
	}
    const economic = form.querySelector('[data-mi-economic]');
    const payment = form.querySelector('[data-mi-payment]');
	let deposit = form.querySelector('[data-mi-deposit]');
	if (deposit && deposit.tagName === 'LABEL') {
	  deposit.firstChild.textContent = 'Percentuale caparra (%)';
	  deposit.hidden = false;
	  const container = document.createElement('div');
	  container.dataset.miDeposit = '';
	  const modeLabel = document.createElement('label');
	  modeLabel.innerHTML = '<span>Come calcolare la caparra</span><select name="deposit_mode" data-mi-deposit-mode><option value="PERCENTAGE">Percentuale (%)</option><option value="FIXED">Importo fisso (€)</option></select>';
	  const fixedLabel = document.createElement('label');
	  fixedLabel.dataset.miDepositFixed = '';
	  fixedLabel.innerHTML = '<span>Importo fisso della caparra (€)</span><input name="deposit_fixed" inputmode="decimal" placeholder="Es. 150,00">';
	  deposit.dataset.miDepositPercentage = '';
	  deposit.parentNode.insertBefore(container, deposit);
	  container.append(modeLabel, deposit, fixedLabel);
	  modeLabel.querySelector('select').value = form.dataset.miDepositMode === 'FIXED' ? 'FIXED' : 'PERCENTAGE';
	  fixedLabel.querySelector('input').value = form.dataset.miDepositFixed || '';
	  deposit = container;
	}
	const depositMode = form.querySelector('select[name="deposit_mode"]');
	const depositPercentage = form.querySelector('[data-mi-deposit-percentage]');
	const depositFixed = form.querySelector('[data-mi-deposit-fixed]');
	const updateDeposit = () => {
	  const fixed = depositMode?.value === 'FIXED';
	  if (depositPercentage) depositPercentage.hidden = fixed;
	  if (depositFixed) depositFixed.hidden = !fixed;
	  const percentageInput = depositPercentage?.querySelector('input');
	  const fixedInput = depositFixed?.querySelector('input');
	  if (percentageInput) percentageInput.disabled = fixed;
	  if (fixedInput) { fixedInput.disabled = !fixed; fixedInput.required = fixed && economic?.value === 'DEPOSIT_BALANCE'; }
	};
	const economicLabel = economic?.closest('label');
    const updateEconomic = () => {
	  const paidEvent = pricing?.value !== 'ZERO';
	  if (economicLabel) economicLabel.hidden = !paidEvent;
	  const collects = paidEvent && ['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(economic?.value);
      if (payment) payment.hidden = !collects;
      if (deposit) deposit.hidden = economic?.value !== 'DEPOSIT_BALANCE';
	  updateDeposit();
    };
	const updatePricing = () => {
	  if (!pricing) return;
	  if (fixedPrice) fixedPrice.hidden = pricing.value !== 'FIXED';
	  servicePricingNodes.forEach((node) => { node.hidden = pricing.value !== 'NONE'; });
	  updateOvernight();
	  serviceUpdaters.forEach((updateService) => updateService());
	  updateEconomic();
	};
    pricing?.addEventListener('change', updatePricing);
    economic?.addEventListener('change', updateEconomic);
	depositMode?.addEventListener('change', updateDeposit);
    updatePricing();
    const opensAt = form.querySelector('[data-mi-opens]');
    const closesAt = form.querySelector('[data-mi-closes]');
    const startsAt = form.querySelector('[data-mi-starts]');
    const dateFields = [opensAt, closesAt, startsAt].filter(Boolean);
    const enforceFourDigitYear = (field) => {
      const match = field.value.match(/^(\d{2}\/\d{2}\/)(\d{5,})(.*)$/);
      if (!match) return;
      const selection = field.selectionStart;
      field.value = `${match[1]}${match[2].slice(0, 4)}${match[3]}`;
      if (selection !== null) field.setSelectionRange(Math.min(selection - 1, field.value.length), Math.min(selection - 1, field.value.length));
    };
    const parseItalianDate = (value) => {
      const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/);
      if (!match) return null;
      const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]), Number(match[4]), Number(match[5]), 0, 0);
      if (date.getFullYear() !== Number(match[3]) || date.getMonth() !== Number(match[2]) - 1 || date.getDate() !== Number(match[1]) || date.getHours() !== Number(match[4]) || date.getMinutes() !== Number(match[5])) return null;
      return date;
    };
    const validateFourDigitYear = (field) => {
      const value = field.value;
      if (!value) { field.setCustomValidity(''); return; }
      const date = parseItalianDate(value);
      if (!date) { field.setCustomValidity('Usa una data reale nel formato gg/mm/aaaa hh:mm, con ore e minuti completi.'); return; }
      const latest = new Date();
      latest.setFullYear(latest.getFullYear() + 10);
      field.setCustomValidity(date > latest ? 'La data dell’evento non può essere oltre dieci anni nel futuro.' : '');
    };
    dateFields.forEach((field) => {
      field.addEventListener('input', () => { enforceFourDigitYear(field); validateFourDigitYear(field); });
      field.addEventListener('change', () => validateFourDigitYear(field));
      validateFourDigitYear(field);
    });
    const updateDateLimits = () => {
      dateFields.forEach((field) => validateFourDigitYear(field));
      const opening = parseItalianDate(opensAt?.value || '');
      const closing = parseItalianDate(closesAt?.value || '');
      const start = parseItalianDate(startsAt?.value || '');
      if (opening && closing && closing < opening) closesAt.setCustomValidity('La chiusura non può precedere l’apertura delle iscrizioni.');
      if (closing && start && start < closing) startsAt.setCustomValidity('L’inizio dell’evento non può precedere la chiusura delle iscrizioni.');
    };
    opensAt?.addEventListener('change', updateDateLimits);
    closesAt?.addEventListener('change', updateDateLimits);
    updateDateLimits();
    show();
  }

  const selectedEvent = document.querySelector('[data-mi-selected-event]');
  const eventOutputs = document.querySelector('[data-mi-event-outputs]');

  document.querySelectorAll('[data-mi-copy]').forEach((copyButton) => {
	copyButton.addEventListener('click', async () => {
	  if (copyButton.getAttribute('aria-busy') === 'true') return;
	  const copyControl = copyButton.closest('.mi-output-copy');
	  const copyInput = copyControl?.querySelector('input');
	  const value = copyButton.dataset.miCopy || copyInput?.value || '';
	  let status = copyControl?.nextElementSibling;
	  if (!status || !status.classList.contains('mi-output-copy-status')) {
		status = document.createElement('p');
		status.className = 'mi-output-copy-status';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		copyControl?.after(status);
	  }
	  copyButton.setAttribute('aria-busy', 'true');
	  copyButton.disabled = true;
	  try {
		if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
		await navigator.clipboard.writeText(value);
		status.classList.remove('is-error');
		status.textContent = 'Copiato negli appunti.';
	  } catch (error) {
		if (copyInput) { copyInput.focus(); copyInput.select(); }
		status.classList.add('is-error');
		status.textContent = 'Copia automatica non disponibile. Il testo è selezionato: usa Ctrl+C oppure il comando Copia del dispositivo.';
	  } finally {
		copyButton.removeAttribute('aria-busy');
		copyButton.disabled = false;
	  }
	});
  });

  document.querySelectorAll('form').forEach((actionForm) => {
	const action = actionForm.querySelector('input[name="mi_portal_action"]')?.value;
	if (!['create_event', 'publish_event_portal', 'prepare_event_outputs'].includes(action)) return;
	actionForm.addEventListener('submit', (event) => {
	  if (event.defaultPrevented || !actionForm.checkValidity()) return;
	  const button = event.submitter || actionForm.querySelector('button[type="submit"]');
	  if (button) {
		button.disabled = true;
		button.textContent = 'Attendere, prego…';
	  }
	  actionForm.setAttribute('aria-busy', 'true');
	  let progress = actionForm.querySelector('.mi-action-progress');
	  if (!progress) {
		progress = document.createElement('span');
		progress.className = 'mi-action-progress';
		progress.setAttribute('role', 'status');
		progress.setAttribute('aria-live', 'polite');
		actionForm.append(progress);
	  }
	  progress.hidden = false;
	  progress.textContent = action === 'publish_event_portal'
		? 'Attendere, prego: sto creando il foglio Google e pubblicando l’evento.'
		: 'Attendere, prego: sto salvando i dati dell’evento.';
	});
  });

  document.querySelectorAll('textarea[data-mi-max-lines]').forEach((field) => {
	const maximum = Math.max(1, Number(field.dataset.miMaxLines) || 6);
	field.addEventListener('input', () => {
	  const lines = field.value.split(/\r?\n/);
	  if (lines.length <= maximum) return;
	  field.value = lines.slice(0, maximum).join('\n');
	  field.setCustomValidity('Puoi inserire al massimo ' + maximum + ' righe.');
	  field.reportValidity();
	  window.setTimeout(() => field.setCustomValidity(''), 1200);
	});
  });

  if (eventOutputs) {
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	window.requestAnimationFrame(() => {
	  eventOutputs.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
	  eventOutputs.focus({ preventScroll: true });
	});
  } else if (selectedEvent) {
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

  document.querySelectorAll('.mi-event-card-menu').forEach((menu) => {
	menu.addEventListener('toggle', () => {
	  if (!menu.open) return;
	  document.querySelectorAll('.mi-event-card-menu[open]').forEach((other) => {
		if (other !== menu) other.open = false;
	  });
	});
  });

  document.querySelectorAll('[data-mi-operator-form]').forEach((operatorForm) => {
	const role = operatorForm.querySelector('select[name="operator_role"]');
	const groups = operatorForm.querySelector('[data-mi-operator-groups]');
	if (!role || !groups) return;
	const updateOperatorGroups = () => {
	  const globalAccess = role.value === 'mi_secretary';
	  groups.hidden = globalAccess;
	  groups.querySelectorAll('input[name="operator_groups[]"]').forEach((field) => { field.disabled = globalAccess; });
	};
	role.addEventListener('change', updateOperatorGroups);
	updateOperatorGroups();
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
