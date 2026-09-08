// Cache volatile: nessun dato delle schede viene salvato nello storage del browser.
function miPanelCache(load, { ttl = 30000, limit = 12 } = {}) {
  const values = new Map();
  const requests = new Map();
  let generation = 0;
  const peek = (key) => {
    const entry = values.get(key);
    if (!entry) return null;
    if (Date.now() - entry.time >= ttl) { values.delete(key); return null; }
    values.delete(key);
    values.set(key, entry);
    return entry.value;
  };
  const get = (key, prefetch = false) => {
    const cached = peek(key);
    if (cached) return Promise.resolve(cached);
    if (requests.has(key)) return requests.get(key).promise;
    // Il passaggio del mouse non deve riempire la coda PHP del server.
    if (prefetch && requests.size) return Promise.resolve(null);
    if (!prefetch) {
      for (const [otherKey, other] of requests) {
        if (otherKey !== key) { other.controller.abort(); requests.delete(otherKey); }
      }
    }
    const controller = new AbortController();
    const requestGeneration = generation;
    const timeout = setTimeout(() => controller.abort(), 15000);
    const entry = { controller, promise: null };
    entry.promise = Promise.resolve().then(() => load(key, controller.signal)).then((value) => {
      if (controller.signal.aborted) throw new DOMException('Richiesta annullata', 'AbortError');
      if (requestGeneration === generation) values.set(key, { value, time: Date.now() });
      while (values.size > limit) values.delete(values.keys().next().value);
      return value;
    }).finally(() => {
      clearTimeout(timeout);
      if (requests.get(key) === entry) requests.delete(key);
    });
    requests.set(key, entry);
    return entry.promise;
  };
  return { get, peek, clear: () => { generation++; values.clear(); } };
}

document.addEventListener('DOMContentLoaded', () => {
  const bindPanelIntent = (link, preload) => {
    let timer;
    const cancel = () => clearTimeout(timer);
    const schedule = () => {
      cancel();
      const connection = navigator.connection;
      if (document.hidden || connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType || '')) return;
      timer = setTimeout(() => preload().catch(() => {}), 120);
    };
    link.addEventListener('pointerenter', schedule, { passive: true });
    link.addEventListener('focus', schedule);
    link.addEventListener('pointerleave', cancel, { passive: true });
    link.addEventListener('blur', cancel);
    link.addEventListener('click', cancel);
  };
  const form = document.querySelector('.mi-event-wizard');
  if (form) {
    const steps = [...form.querySelectorAll('.mi-wizard-step')];
    const back = form.querySelector('[data-mi-back]');
    const next = form.querySelector('[data-mi-next]');
    const save = form.querySelector('button[type="submit"]');
	let validateWizardRelations = () => {};
    if (save) next.parentElement.append(save);
	const backUrl = form.dataset.miBackUrl || '';
	const coverImage = form.querySelector('[name="cover_image"][data-mi-max-bytes]');
	const coverImageStatus = form.querySelector('[data-mi-image-status]');
	let coverImagePreparing = false;
	const validateCoverImage = () => {
	  if (!coverImage) return true;
	  const file = coverImage.files?.[0];
	  const maximum = Number.parseInt(coverImage.dataset.miMaxBytes || '0', 10);
	  coverImage.setCustomValidity(coverImagePreparing ? 'Attendi il completamento della preparazione dell’immagine.' : (file && maximum && file.size > maximum ? 'L’immagine in evidenza non può superare 2 MB.' : ''));
	  return coverImage.reportValidity();
	};
	const resizeCoverImage = async () => {
	  const file = coverImage?.files?.[0];
	  if (!file || !file.type.startsWith('image/')) return validateCoverImage();
	  const maximum = Number.parseInt(coverImage.dataset.miMaxBytes || '0', 10);
	  coverImagePreparing = true;
	  if (coverImageStatus) coverImageStatus.textContent = 'Preparazione dell’immagine…';
	  try {
		const bitmap = await createImageBitmap(file);
		if (file.size <= maximum && bitmap.width <= 2400 && bitmap.height <= 2400) {
		  bitmap.close();
		  if (coverImageStatus) coverImageStatus.textContent = 'Immagine pronta.';
		  return true;
		}
		const scale = Math.min(1, 2400 / bitmap.width, 2400 / bitmap.height);
		const canvas = document.createElement('canvas');
		canvas.width = Math.max(1, Math.round(bitmap.width * scale));
		canvas.height = Math.max(1, Math.round(bitmap.height * scale));
		canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
		bitmap.close();
		const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', .84));
		if (!blob) throw new Error('conversione-non-riuscita');
		const files = new DataTransfer();
		files.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
		coverImage.files = files.files;
		if (coverImageStatus) coverImageStatus.textContent = 'Immagine ottimizzata e pronta.';
		return true;
	  } catch (error) {
		coverImage.setCustomValidity('Non è stato possibile preparare l’immagine. Scegline una più piccola.');
		if (coverImageStatus) coverImageStatus.textContent = 'Non è stato possibile preparare l’immagine.';
		return false;
	  } finally {
		coverImagePreparing = false;
	  }
	};
	coverImage?.addEventListener('change', resizeCoverImage);
    let index = Math.min(steps.length - 1, Math.max(0, Number.parseInt(form.dataset.miInitialStep || '0', 10) || 0));
	const renderConfirmationPreview = () => {
	  const subjectInput = form.querySelector('[name="confirmation_email_subject"]');
	  const textInput = form.querySelector('[name="confirmation_email_text"]');
	  const subjectPreview = form.querySelector('[data-mi-confirmation-subject]');
	  const textPreview = form.querySelector('[data-mi-confirmation-text]');
	  if (!subjectInput || !textInput || !subjectPreview || !textPreview) return;
	  const replacements = {
		'{{sottoscrittore.nome_completo}}': 'Nome di chi sottoscrive l’iscrizione',
		'{{evento.titolo}}': form.querySelector('[name="title"]')?.value || 'Titolo dell’evento',
		'{{evento.data}}': form.querySelector('[name="starts_at"]')?.value || 'Data da definire',
		'{{evento.luogo}}': form.querySelector('[name="location"]')?.value || 'Luogo da definire',
		'{{ordine.codice}}': 'Codice assegnato all’iscrizione',
		'{{ordine.riepilogo}}': 'Riepilogo dei partecipanti e delle scelte',
		'{{ordine.riepilogo_economico}}': 'Riepilogo delle quote',
	  };
	  const fill = (value) => Object.entries(replacements).reduce((result, [placeholder, replacement]) => result.split(placeholder).join(replacement), value);
	  subjectPreview.textContent = fill(subjectInput.value);
	  textPreview.textContent = fill(textInput.value);
	};
    const show = () => {
      steps.forEach((step, stepIndex) => step.classList.toggle('is-active', stepIndex === index));
	  back.disabled = index === 0 && !backUrl;
      next.hidden = index === steps.length - 1;
      if (save) save.hidden = index !== steps.length - 1;
      if (index === steps.length - 1) {
        const value = (name) => form.querySelector(`[name="${name}"]`)?.value || 'Da definire';
		const multipleBooking = form.querySelector('[name="booking_limit_mode"]:checked')?.value === 'MULTIPLE';
		const bookingLimit = multipleBooking ? value('max_per_order') : '1';
        const review = form.querySelector('[data-mi-review]');
		if (review) review.innerHTML = `<strong>${value('title')}</strong><span>Inizio: ${value('starts_at')}</span><span>Chiusura iscrizioni: ${value('closes_at')}</span><span>Posti: ${value('capacity')}</span><span>Massimo per prenotazione: ${bookingLimit}</span>`;
		renderConfirmationPreview();
      }
    };
	form.querySelectorAll('[name="confirmation_email_subject"], [name="confirmation_email_text"]').forEach((field) => field.addEventListener('input', renderConfirmationPreview));
    const advance = () => {
	  validateWizardRelations();
	  const fields = [...steps[index].querySelectorAll('input, select, textarea')].filter((field) => !field.disabled);
      if (fields.some((field) => !field.reportValidity())) return;
	  if (coverImage && steps[index].contains(coverImage) && !validateCoverImage()) return;
      index = Math.min(steps.length - 1, index + 1);
      show();
    };
    next.addEventListener('click', advance);
    back.addEventListener('click', () => {
	  if (index === 0 && backUrl) {
		window.location.assign(backUrl);
		return;
	  }
      index = Math.max(0, index - 1);
      show();
    });
	form.addEventListener('submit', (event) => {
	  validateWizardRelations();
      const invalid = [...form.elements].find((field) => !field.disabled && field.validity && !field.validity.valid);
      if (!invalid) return;
      event.preventDefault();
      const invalidStep = steps.findIndex((step) => step.contains(invalid));
      if (invalidStep >= 0) index = invalidStep;
      show();
      invalid.reportValidity();
    });
    // La validazione nativa precede submit: rendi visibile il campo prima del focus.
    form.addEventListener('invalid', (event) => {
      const invalidStep = steps.findIndex((step) => step.contains(event.target));
      if (invalidStep < 0) return;
      index = invalidStep;
      show();
    }, true);
    save?.addEventListener('click', () => validateWizardRelations());
    const pricing = form.querySelector('[data-mi-pricing]');
	const waitlist = form.querySelector('[data-mi-waitlist]');
	const waitlistOffer = form.querySelector('[data-mi-waitlist-offer]');
	const updateWaitlist = () => {
	  if (!waitlistOffer) return;
	  waitlistOffer.hidden = !waitlist?.checked;
	  const input = waitlistOffer.querySelector('input');
	  if (input) input.disabled = !waitlist?.checked;
	};
	waitlist?.addEventListener('change', updateWaitlist);
	updateWaitlist();
	const bookingLimit = form.querySelector('[data-mi-booking-limit]');
	const bookingLimitValue = form.querySelector('[data-mi-booking-limit-value]');
	const updateBookingLimit = () => {
	  const multiple = bookingLimit?.querySelector('[name="booking_limit_mode"]:checked')?.value === 'MULTIPLE';
	  if (bookingLimitValue) bookingLimitValue.hidden = !multiple;
	  const input = bookingLimitValue?.querySelector('input');
	  if (input) input.disabled = !multiple;
	};
	bookingLimit?.querySelectorAll('[name="booking_limit_mode"]').forEach((option) => option.addEventListener('change', updateBookingLimit));
	const copyEvent = form.querySelector('[data-mi-copy-event]');
	copyEvent?.addEventListener('change', () => {
	  const copiedMaximum = Number.parseInt(copyEvent.selectedOptions[0]?.dataset.miMaxPerOrder || '', 10);
	  if (!copyEvent.value || !Number.isInteger(copiedMaximum)) return;
	  const mode = bookingLimit?.querySelector(`[name="booking_limit_mode"][value="${copiedMaximum > 1 ? 'MULTIPLE' : 'ONE'}"]`);
	  if (mode) mode.checked = true;
	  const input = bookingLimitValue?.querySelector('input');
	  if (input) input.value = String(Math.min(20, Math.max(2, copiedMaximum)));
	  updateBookingLimit();
	});
	updateBookingLimit();
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
	const busRoutes = form.querySelector('[data-mi-bus-routes]');
	const busRoutesList = form.querySelector('[data-mi-bus-routes-list]');
	const busRouteTemplate = form.querySelector('[data-mi-bus-route-template]');
	const updateBusRoutes = () => {
	  const active = pricing?.value === 'NONE';
	  if (busRoutes) busRoutes.hidden = !active;
	  busRoutes?.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
	};
	form.querySelector('[data-mi-add-bus-route]')?.addEventListener('click', () => {
	  if (!busRouteTemplate || !busRoutesList) return;
	  if (busRoutesList.children.length >= 12) return;
	  const row = busRouteTemplate.content.firstElementChild.cloneNode(true);
	  busRoutesList.append(row);
	  row.querySelector('input[name="bus_route_price[]"]')?.setAttribute('required', '');
	  row.querySelector('input[name="bus_route_code[]"]')?.focus();
	});
	busRoutesList?.addEventListener('click', (event) => {
	  const remove = event.target.closest('[data-mi-remove-bus-route]');
	  if (remove) remove.closest('.mi-bus-route')?.remove();
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
	const servicePricingNodes = [overnight?.closest('label'), rooms, ...serviceIntroduction, ...serviceFees, busRoutes].filter(Boolean);
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
	  updateBusRoutes();
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
    const updateDateLimits = () => {
      dateFields.forEach((field) => validateFourDigitYear(field));
      const opening = parseItalianDate(opensAt?.value || '');
      const closing = parseItalianDate(closesAt?.value || '');
      const start = parseItalianDate(startsAt?.value || '');
      if (opening && closing && closing < opening) closesAt.setCustomValidity('La chiusura non può precedere l’apertura delle iscrizioni.');
      if (closing && start && start < closing) startsAt.setCustomValidity('L’inizio dell’evento non può precedere la chiusura delle iscrizioni.');
    };
	validateWizardRelations = updateDateLimits;
	dateFields.forEach((field) => {
	  field.addEventListener('input', () => { enforceFourDigitYear(field); updateDateLimits(); });
	  field.addEventListener('change', updateDateLimits);
	});
    updateDateLimits();
    show();
  }

  const portalSwitcher = document.querySelector('.mi-portal-switcher');
  if (portalSwitcher) {
    const links = [...portalSwitcher.querySelectorAll('a')];
    let switcherTouchStart = null;
    portalSwitcher.addEventListener('touchstart', (event) => {
      if (event.touches.length !== 1) return;
      switcherTouchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
    }, { passive: true });
    portalSwitcher.addEventListener('touchend', (event) => {
      if (!switcherTouchStart || event.changedTouches.length !== 1) return;
      const deltaX = event.changedTouches[0].clientX - switcherTouchStart.x;
      const deltaY = event.changedTouches[0].clientY - switcherTouchStart.y;
      switcherTouchStart = null;
      if (Math.abs(deltaX) < 70 || Math.abs(deltaX) < Math.abs(deltaY) * 1.5) return;
      const activeIndex = links.findIndex((link) => link.classList.contains('is-active'));
      const destination = links[activeIndex + (deltaX < 0 ? 1 : -1)];
      if (destination) window.location.assign(destination.href);
    }, { passive: true });
  }

  const selectedEvent = document.querySelector('[data-mi-selected-event]');
  const eventOutputs = document.querySelector('[data-mi-event-outputs]');
	const placeEventPanel = (panel, selectedCard) => {
	  const grid = selectedCard?.closest('.mi-event-grid');
	  if (!grid || !panel) return;
	  document.querySelector('[data-mi-event-inline-panel]')?.remove();
	  panel.remove();
	  const selectedTop = selectedCard.offsetTop;
	  let lastCard = selectedCard;
	  while (lastCard.nextElementSibling?.classList.contains('mi-event-card-shell') && Math.abs(lastCard.nextElementSibling.offsetTop - selectedTop) < 2) {
		lastCard = lastCard.nextElementSibling;
	  }
	  lastCard.after(panel);
	};
	const inlineEventPanel = document.querySelector('[data-mi-event-inline-panel]');
	if (inlineEventPanel) {
	  const grid = inlineEventPanel.closest('.mi-event-grid');
	  const eventId = inlineEventPanel.dataset.miEventId;
	  const selectedCard = grid?.querySelector(`.mi-event-card[data-mi-event-id="${eventId}"]`)?.closest('.mi-event-card-shell');
	  if (grid && selectedCard) {
		placeEventPanel(inlineEventPanel, selectedCard);
	  }
	}

  const outputStatus = (control) => {
	let status = control?.nextElementSibling;
	if (!status || !status.classList.contains('mi-output-copy-status')) {
	  status = document.createElement('p');
	  status.className = 'mi-output-copy-status';
	  status.setAttribute('role', 'status');
	  status.setAttribute('aria-live', 'polite');
	  control?.after(status);
	}
	return status;
  };

	const bindCopyButtons = (root = document) => root.querySelectorAll('[data-mi-copy]').forEach((copyButton) => {
	if (copyButton.dataset.miCopyBound === '1') return;
	copyButton.dataset.miCopyBound = '1';
	copyButton.addEventListener('click', async () => {
	  if (copyButton.getAttribute('aria-busy') === 'true') return;
	  const copyControl = copyButton.closest('.mi-output-copy') || copyButton.closest('.mi-output-copy-action');
	  const copyInput = copyControl?.querySelector('input');
	  const value = copyButton.dataset.miCopy || copyInput?.value || '';
	  const status = outputStatus(copyControl);
	  copyButton.setAttribute('aria-busy', 'true');
	  copyButton.disabled = true;
	  try {
		if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
		await navigator.clipboard.writeText(value);
		status.classList.remove('is-error');
		if (copyButton.dataset.miCopySuccessLabel) {
		  copyButton.textContent = copyButton.dataset.miCopySuccessLabel;
		  status.textContent = '';
		} else {
		  status.textContent = 'Copiato negli appunti.';
		}
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
	bindCopyButtons();

	const bindShareButtons = (root = document) => root.querySelectorAll('[data-mi-share]').forEach((shareButton) => {
	if (shareButton.dataset.miShareBound === '1') return;
	shareButton.dataset.miShareBound = '1';
	shareButton.addEventListener('click', async () => {
	  if (shareButton.getAttribute('aria-busy') === 'true') return;
	  const shareControl = shareButton.closest('.mi-output-copy');
	  const shareInput = shareControl?.querySelector('input');
	  const url = shareButton.dataset.miShare || shareInput?.value || '';
	  const title = shareButton.dataset.miShareTitle || 'Iscrizione all’evento';
	  const status = outputStatus(shareControl);
	  shareButton.setAttribute('aria-busy', 'true');
	  shareButton.disabled = true;
	  try {
		if (navigator.share) {
		  await navigator.share({ title, text: `Iscriviti a ${title}`, url });
		  status.classList.remove('is-error');
		  status.textContent = 'Condivisione completata.';
		} else {
		  if (!navigator.clipboard?.writeText) throw new Error('condivisione-non-disponibile');
		  await navigator.clipboard.writeText(url);
		  status.classList.remove('is-error');
		  status.textContent = 'Le modalità di condivisione non sono disponibili su questo dispositivo. Il link è stato copiato.';
		}
	  } catch (error) {
		if (error?.name === 'AbortError') {
		  status.classList.remove('is-error');
		  status.textContent = 'Condivisione annullata.';
		} else {
		  if (shareInput) { shareInput.focus(); shareInput.select(); }
		  status.classList.add('is-error');
		  status.textContent = 'Condivisione non disponibile. Il link è selezionato e può essere copiato manualmente.';
		}
	  } finally {
		shareButton.removeAttribute('aria-busy');
		shareButton.disabled = false;
	  }
	});
	});
	bindShareButtons();

	const bindProgressForms = (root = document) => root.querySelectorAll('form').forEach((actionForm) => {
	if (actionForm.dataset.miProgressBound === '1') return;
	const action = actionForm.querySelector('input[name="mi_portal_action"]')?.value;
	if (!['create_event', 'publish_event_portal', 'prepare_event_outputs'].includes(action)) return;
	actionForm.dataset.miProgressBound = '1';
	actionForm.addEventListener('submit', (event) => {
	  if (event.defaultPrevented || !actionForm.checkValidity()) return;
	  const button = event.submitter || actionForm.querySelector('button[type="submit"]');
	  if (button) {
		button.disabled = true;
		if (action !== 'create_event') button.textContent = 'Attendere, prego…';
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
	  if (!progress.textContent.trim()) {
		progress.textContent = action === 'publish_event_portal'
		  ? 'Sto creando il foglio Google e pubblicando l’evento.'
		  : 'Salvataggio in corso…';
	  }
	});
	});
	bindProgressForms();

	const quickEventFormSnapshot = (form) => new URLSearchParams(new FormData(form)).toString();
	const bindQuickEventForms = (root = document) => root.querySelectorAll('[data-mi-event-quick-form]').forEach((quickForm) => {
		if (quickForm.dataset.miQuickFormBound === '1') return;
		const submit = quickForm.querySelector('[data-mi-event-quick-submit]');
		if (!submit) return;
		quickForm.dataset.miQuickFormBound = '1';
		const initialSnapshot = quickEventFormSnapshot(quickForm);
		const updateSubmitVisibility = () => {
			submit.hidden = quickEventFormSnapshot(quickForm) === initialSnapshot;
		};
		quickForm.addEventListener('input', updateSubmitVisibility);
		quickForm.addEventListener('change', updateSubmitVisibility);
		updateSubmitVisibility();
	});
	bindQuickEventForms();

	document.addEventListener('click', (event) => {
	  const opener = event.target.closest('[data-mi-cancel-dialog-open]');
	  if (opener) {
		const dialog = document.getElementById(opener.dataset.miCancelDialogOpen);
		if (dialog?.showModal) dialog.showModal();
		return;
	  }
	  const closer = event.target.closest('[data-mi-cancel-dialog-close]');
	  if (closer) closer.closest('dialog')?.close();
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
	const events = operatorForm.querySelector('[data-mi-operator-events]');
	if (!role || !groups || !events) return;
	const updateOperatorScope = () => {
	  const usesGroups = role.value === 'mi_group_manager';
	  const usesEvents = role.value === 'mi_assigned_event_manager';
	  groups.hidden = !usesGroups;
	  events.hidden = !usesEvents;
	  groups.querySelectorAll('input[name="operator_groups[]"]').forEach((field) => { field.disabled = !usesGroups; });
	  events.querySelectorAll('input[name="operator_events[]"]').forEach((field) => { field.disabled = !usesEvents; });
	};
	role.addEventListener('change', updateOperatorScope);
	updateOperatorScope();
  });

	const eventLinks = [...document.querySelectorAll('[data-mi-event-open]')];
	if (eventLinks.length) {
	  const eventPanelCache = miPanelCache(async (href, signal) => {
		const response = await fetch(href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
		if (!response.ok) throw new Error('event_panel_unavailable');
		const panel = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-mi-event-inline-panel]');
		if (!panel) throw new Error('event_panel_missing');
		return panel;
	  });
	  document.addEventListener('visibilitychange', () => { if (document.hidden) eventPanelCache.clear(); });
	  const listUrl = new URL(window.location.href);
	  listUrl.searchParams.delete('mi_portal_event');
	  listUrl.searchParams.delete('mi_portal_event_panel');
	  let eventNavigationId = 0;
	  const fetchEventPanel = (link, prefetch = false) => {
		const eventId = link.dataset.miEventId;
		const endpoint = new URL(link.href);
		endpoint.searchParams.set('mi_portal_event', eventId);
		endpoint.searchParams.set('mi_portal_event_panel', '1');
		return eventPanelCache.get(endpoint.href, prefetch);
	  };
	  const clearEventSelection = () => {
		document.querySelector('[data-mi-event-inline-panel]')?.remove();
		eventLinks.forEach((candidate) => {
		  candidate.closest('.mi-event-card-shell')?.classList.remove('is-selected');
		  candidate.setAttribute('aria-expanded', 'false');
		  candidate.removeAttribute('aria-controls');
		  candidate.removeAttribute('aria-busy');
		});
	  };
	  const showEventPanel = async (link, historyMode = 'push') => {
		const shell = link.closest('.mi-event-card-shell');
		const eventId = link.dataset.miEventId;
		const currentPanel = document.querySelector(`[data-mi-event-inline-panel][data-mi-event-id="${eventId}"]`);
		if (currentPanel && shell?.classList.contains('is-selected')) {
		  if ('none' === historyMode) return;
		  eventNavigationId++;
		  clearEventSelection();
		  if ('none' !== historyMode) window.history.pushState({}, '', listUrl);
		  link.focus();
		  return;
		}
		const navigationId = ++eventNavigationId;
		clearEventSelection();
		shell?.classList.add('is-selected');
		link.setAttribute('aria-expanded', 'true');
		link.setAttribute('aria-controls', `mi-event-inline-panel-${eventId}`);
		link.setAttribute('aria-busy', 'true');
		const loading = document.createElement('div');
		loading.className = 'mi-event-inline-panel mi-event-inline-panel--loading';
		loading.dataset.miEventInlinePanel = '';
		loading.dataset.miEventId = eventId;
		loading.setAttribute('role', 'status');
		loading.innerHTML = '<span aria-hidden="true"></span><strong>Apro la scheda dell’evento…</strong>';
		placeEventPanel(loading, shell);
		try {
		  const cachedPanel = await fetchEventPanel(link);
		  if (navigationId !== eventNavigationId) return;
		  const panel = cachedPanel.cloneNode(true);
		  bindQuickEventForms(panel);
		  placeEventPanel(panel, shell);
		  bindCopyButtons(panel);
		  bindShareButtons(panel);
		  bindProgressForms(panel);
		  link.removeAttribute('aria-busy');
		  const selectedUrl = new URL(link.href);
		  selectedUrl.searchParams.set('mi_portal_event', eventId);
		  if ('push' === historyMode) window.history.pushState({}, '', selectedUrl);
		  if ('replace' === historyMode) window.history.replaceState({}, '', selectedUrl);
		  panel.querySelector('[data-mi-selected-event]')?.focus({ preventScroll: true });
		  panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
		} catch (error) {
		  if (navigationId !== eventNavigationId) return;
		  window.location.assign(link.href);
		}
	  };
	  eventLinks.forEach((link) => {
		bindPanelIntent(link, () => fetchEventPanel(link, true));
		link.addEventListener('click', (event) => {
		  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		  event.preventDefault();
		  showEventPanel(link);
		});
	  });
	  document.addEventListener('click', (event) => {
		const backLink = event.target.closest('.mi-event-management__back');
		if (!backLink || !backLink.closest('[data-mi-event-inline-panel]')) return;
		event.preventDefault();
		const selectedLink = document.querySelector('.mi-event-card-shell.is-selected [data-mi-event-open]');
		eventNavigationId++;
		clearEventSelection();
		window.history.pushState({}, '', listUrl);
		selectedLink?.focus();
	  });
	  window.addEventListener('popstate', () => {
		const eventId = new URL(window.location.href).searchParams.get('mi_portal_event');
		const target = eventLinks.find((link) => link.dataset.miEventId === eventId);
		if (target) showEventPanel(target, 'none');
		else { eventNavigationId++; clearEventSelection(); }
	  });
	}

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
  let bookingNavigationId = 0;
  let activeBookingIndex = -1;
  const detailCache = miPanelCache(async (href, signal) => {
    const response = await fetch(href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
    if (!response.ok) throw new Error('detail_unavailable');
    const detail = parseDetail(await response.text());
    if (!detail) throw new Error('detail_missing');
    return detail;
  });
  document.addEventListener('visibilitychange', () => { if (document.hidden) detailCache.clear(); });

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
    bookingNavigationId++;
    modal.hidden = true;
    content.replaceChildren();
    document.body.classList.remove('mi-portal-modal-open');
    if (replaceHistory) window.history.replaceState({}, '', listUrl);
    previousFocus?.focus();
  };
  const openBooking = async (link, historyMode = 'push') => {
	const navigationId = ++bookingNavigationId;
	previousFocus = link;
	activeBookingIndex = bookingLinks.indexOf(link);
	updateNavigation();
	const cachedDetail = detailCache.peek(link.href);
	if (cachedDetail) {
	  showBooking(cachedDetail.cloneNode(true), link.href, historyMode);
	  return;
	}
	modal.hidden = false;
    document.body.classList.add('mi-portal-modal-open');
    content.innerHTML = '<p class="mi-portal-modal__loading">Apertura della prenotazione…</p>';
    closeButton.focus();
    try {
      const detail = await detailCache.get(link.href);
      if (navigationId !== bookingNavigationId) return;
	  showBooking(detail.cloneNode(true), link.href, historyMode);
    } catch (error) {
      if (navigationId !== bookingNavigationId) return;
      window.location.assign(link.href);
    }
  };

  bookingLinks.forEach((link) => {
    bindPanelIntent(link, () => detailCache.get(link.href, true));
    link.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openBooking(link);
    });
  });
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
