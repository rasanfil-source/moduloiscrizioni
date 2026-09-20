(function () {
  'use strict';

  let qrGeneratorPromise = null;

  document.querySelectorAll('.mi-registration[data-mi-config]').forEach((root) => {
    const core = globalThis.MIRegistrationCore;
    if (!core) throw new Error('Modulo iscrizioni: funzioni di base non disponibili.');
    const config = JSON.parse(root.dataset.miConfig || '{}');
    const form = root.querySelector('.mi-registration__form');
    if (!form) return;
    // Misura il tempo dalla reale inizializzazione nel browser. Il timestamp
    // inserito nell'HTML può diventare vecchio se una cache serve la pagina.
    const startedAt = Math.floor(Date.now() / 1000);

    const participantsRoot = root.querySelector('[data-mi-participants]');
    const errorBox = root.querySelector('[data-mi-error]');
    const successBox = root.querySelector('[data-mi-success]');
    const submitButton = root.querySelector('.mi-registration__submit');
    const paymentInstructionsNote = document.createElement('p');
    paymentInstructionsNote.className = 'mi-registration__payment-instructions-note';
    paymentInstructionsNote.hidden = true;
    root.querySelector('.mi-registration__action-bar').before(paymentInstructionsNote);
    const economicSummary = root.querySelector('[data-mi-economic-summary]');
	const buyerFirstName = form.elements.namedItem('buyerFirstName');
	const buyerLastName = form.elements.namedItem('buyerLastName');
	const buyerEmail = form.elements.namedItem('buyerEmail');
	const buyerPhone = form.elements.namedItem('buyerPhone');
	const steps = Array.from(root.querySelectorAll('[data-mi-step]'));
	const nextButton = root.querySelector('[data-mi-next]');
	const backButton = root.querySelector('[data-mi-back]');
	const stickySummary = root.querySelector('[data-mi-sticky-summary]');
	const marketingInput = form.elements.namedItem('marketingAccepted');
	const marketingText = marketingInput?.closest('label')?.querySelector('span');
	if (marketingText) marketingText.textContent = 'Vuoi essere avvisato delle future iniziative? Il consenso è facoltativo e può essere revocato.';
	if (config.event.special_requests_enabled) {
	  const specialRequestsLabel = document.createElement('label');
	  specialRequestsLabel.className = 'mi-registration__special-requests';
	  specialRequestsLabel.textContent = 'Richieste particolari (facoltativo)';
	  const specialRequestsInput = document.createElement('textarea');
	  specialRequestsInput.name = 'specialRequests';
	  specialRequestsInput.rows = 3;
	  specialRequestsInput.maxLength = 2000;
	  specialRequestsInput.placeholder = 'Segnala esigenze organizzative, alimentari o di accessibilità che ritieni utile comunicare.';
	  specialRequestsLabel.append(specialRequestsInput);
	  steps[2]?.querySelector('h2')?.after(specialRequestsLabel);
	}
	let currentStep = 1;
    let requestKey = makeRequestKey();
    let participantValues = [];
	function prefillFirstBookingFromContact() {
	  const firstParticipant = participantsRoot.querySelector('.mi-registration__participant');
	  if (!firstParticipant) return;
	  const firstName = firstParticipant.querySelector('[data-mi-first-name]');
	  const lastName = firstParticipant.querySelector('[data-mi-last-name]');
	  if (firstName && buyerFirstName) firstName.value = buyerFirstName.value.trim();
	  if (lastName && buyerLastName) lastName.value = buyerLastName.value.trim();
	  const email = firstParticipant.querySelector('[data-mi-participant-field="email"]');
	  const phone = firstParticipant.querySelector('[data-mi-participant-field="phone"]');
	  if (email && buyerEmail && !email.value) email.value = buyerEmail.value.trim();
	  if (phone && buyerPhone && !phone.value) phone.value = buyerPhone.value.trim();
	}
	buyerFirstName?.addEventListener('input', prefillFirstBookingFromContact);
	buyerLastName?.addEventListener('input', prefillFirstBookingFromContact);
	buyerEmail?.addEventListener('input', prefillFirstBookingFromContact);
	buyerPhone?.addEventListener('input', prefillFirstBookingFromContact);
	preparePhoneField(buyerPhone);

	function validatePhoneField(input, reveal = false, normalize = false) {
	  if (!input) return true;
	  const originalValue = String(input.value || '').trim();
	  const value = normalize ? core.normalizePhone(originalValue) : originalValue;
	  if (normalize && value !== originalValue) input.value = value;
	  const invalid = Boolean(value) && !core.isValidPhone(value);
	  const message = 'Inserisci un numero di cellulare completo.';
	  input.setCustomValidity(invalid ? message : '');
	  const showError = invalid && (reveal || input.dataset.miPhoneTouched === '1');
	  input.toggleAttribute('aria-invalid', showError);
	  const error = input.parentElement?.querySelector('[data-mi-phone-error]');
	  if (error) error.hidden = !showError;
	  return !invalid;
	}

	function preparePhoneField(input) {
	  if (!input || input.dataset.miPhoneValidation === '1') return;
	  input.dataset.miPhoneValidation = '1';
	  const error = document.createElement('small');
	  error.className = 'mi-registration__field-error';
	  error.dataset.miPhoneError = '1';
	  error.textContent = 'Inserisci un numero di cellulare completo.';
	  error.hidden = true;
	  input.after(error);
	  input.addEventListener('blur', () => { input.dataset.miPhoneTouched = '1'; validatePhoneField(input, true, true); });
	  input.addEventListener('input', () => validatePhoneField(input));
	  validatePhoneField(input);
	}

    function makeRequestKey() {
      if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
        return globalThis.crypto.randomUUID().replaceAll('-', '');
      }
      return `${Date.now()}_${Math.random().toString(36).slice(2)}_${Math.random().toString(36).slice(2)}`;
    }

	function ensureQrGenerator() {
	  if (typeof globalThis.qrcode === 'function') return Promise.resolve();
	  if (qrGeneratorPromise) return qrGeneratorPromise;
	  const source = String(config.qrScriptUrl || '');
	  if (!source) return Promise.reject(new Error('Generatore QR non disponibile.'));
	  qrGeneratorPromise = new Promise((resolve, reject) => {
		const existing = document.querySelector('script[data-mi-qrcode-generator]');
		if (existing) existing.remove();
		const script = document.createElement('script');
		script.src = source;
		script.async = true;
		script.dataset.miQrcodeGenerator = '';
		script.addEventListener('load', () => {
		  if (typeof globalThis.qrcode === 'function') resolve();
		  else reject(new Error('Generatore QR non disponibile.'));
		}, { once: true });
		script.addEventListener('error', () => reject(new Error('Generatore QR non disponibile.')), { once: true });
		document.head.append(script);
	  }).catch((error) => {
		qrGeneratorPromise = null;
		throw error;
	  });
	  return qrGeneratorPromise;
	}

    function ticketSelection() {
      const tickets = {};
      root.querySelectorAll('[data-mi-ticket]').forEach((input) => {
        tickets[input.dataset.miTicket] = { value: input.value, max: input.max };
      });
      return core.normalizeSelection(tickets);
    }

    function totalQuantity() {
      return core.sumQuantities(ticketSelection());
    }

    function formatCurrency(cents) {
      return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(Math.max(0, cents) / 100);
    }

	function pendingPaymentConfirmation(name, economicSummary = {}) {
	  const total = Math.max(0, Number(economicSummary.total_cents) || 0);
	  if (economicSummary.mode === 'DEPOSIT_BALANCE') {
		const deposit = Math.min(total, Math.max(0, Number(economicSummary.initial_due_cents) || 0));
		const balance = Math.max(0, total - deposit);
		return `Prenotazione registrata a nome di ${name}. Totale: ${formatCurrency(total)}. Da versare ora: ${formatCurrency(deposit)} di caparra. Saldo successivo: ${formatCurrency(balance)}.`;
	  }
	  return `Prenotazione registrata a nome di ${name}. Totale da versare: ${formatCurrency(total)}.`;
	}

	function localDateWithYearOffset(yearOffset = 0) {
	  const date = new Date();
	  date.setFullYear(date.getFullYear() + yearOffset);
	  const year = String(date.getFullYear()).padStart(4, '0');
	  const month = String(date.getMonth() + 1).padStart(2, '0');
	  const day = String(date.getDate()).padStart(2, '0');
	  return `${year}-${month}-${day}`;
	}

    function totalCents() {
	  const prices = Object.fromEntries((config.event.ticket_types || []).map((ticket) => [ticket.code, config.event.pricing_mode === 'FIXED' ? Number(config.event.fixed_price_cents) || 0 : (config.event.pricing_mode === 'CALCULATED' ? Number(ticket.price_cents) || 0 : 0)]));
      const optionPrices = config.event.pricing_mode === 'ZERO'
        ? {}
        : Object.fromEntries((config.event.options || []).map((option) => [option.code, Number(option.price_cents) || 0]));
      const ticketTotal = Object.entries(ticketSelection()).reduce((total, [code, quantity]) => total + (prices[code] || 0) * quantity, 0);
      const orderTotal = Object.entries(orderOptionSelection()).reduce((total, [code, quantity]) => total + (optionPrices[code] || 0) * quantity, 0);
      const participantTotal = Array.from(participantsRoot.querySelectorAll('[data-mi-participant-option]')).reduce((total, input) => total + (optionPrices[input.dataset.miParticipantOption] || 0) * participantOptionQuantity(input), 0);
      return ticketTotal + orderTotal + participantTotal;
    }

    function orderOptionSelection() {
      return Object.fromEntries(Array.from(root.querySelectorAll('[data-mi-order-option]')).map((input) => [input.dataset.miOrderOption, core.clampQuantity(input.value, input.max)]));
    }

    function renderEconomicSummary() {
      if (!economicSummary) return;
      const total = totalCents();
      const mode = config.event.economic_mode;
      const initialRow = economicSummary.querySelector('[data-mi-initial-row]');
      const balanceRow = economicSummary.querySelector('[data-mi-balance-row]');
      const paymentMethodsRow = economicSummary.querySelector('[data-mi-payment-methods-row]');
      const paymentLabels = { BANK_TRANSFER: 'Bonifico', CARD: 'Carta', CASH: 'Contante' };
      const paymentMethods = (config.event.payment_methods || []).map((method) => paymentLabels[method]).filter(Boolean);
      economicSummary.querySelector('[data-mi-total]').textContent = formatCurrency(total);
      initialRow.hidden = !['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(mode);
      balanceRow.hidden = mode !== 'DEPOSIT_BALANCE';
      paymentMethodsRow.hidden = !['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(mode) || paymentMethods.length === 0;
      economicSummary.querySelector('[data-mi-payment-methods]').textContent = paymentMethods.join(', ');
      if (mode === 'FULL_PAYMENT') {
        economicSummary.querySelector('[data-mi-initial-label]').textContent = 'Versamento previsto:';
        economicSummary.querySelector('[data-mi-initial]').textContent = formatCurrency(total);
        economicSummary.querySelector('[data-mi-economic-note]').textContent = 'Il modulo registra l’iscrizione; il versamento sarà registrato manualmente dall’organizzazione.';
      } else if (mode === 'DEPOSIT_BALANCE') {
		const fixedDeposit = config.event.deposit_mode === 'FIXED';
		const initial = fixedDeposit ? Math.min(total, Number(config.event.deposit_fixed_cents || 0) * totalQuantity()) : Math.round(total * Number(config.event.deposit_percentage || 30) / 100);
		economicSummary.querySelector('[data-mi-initial-label]').textContent = fixedDeposit ? 'Caparra fissa:' : `Caparra (${config.event.deposit_percentage}%):`;
        economicSummary.querySelector('[data-mi-initial]').textContent = formatCurrency(initial);
        economicSummary.querySelector('[data-mi-balance]').textContent = formatCurrency(total - initial);
        economicSummary.querySelector('[data-mi-economic-note]').textContent = 'Il modulo registra gli importi previsti; i versamenti saranno registrati manualmente dall’organizzazione.';
      } else {
        economicSummary.querySelector('[data-mi-economic-note]').textContent = 'Importo informativo: nessun versamento viene richiesto dal modulo.';
      }
    }

	function updateStickySummary() {
	  const quantity = totalQuantity();
	  if (!stickySummary) return;
	  const quantityLabel = quantity === 1 ? '1 iscrizione' : `${quantity} iscrizioni`;
	  const total = totalCents();
	  stickySummary.textContent = quantity ? `${quantityLabel}${currentStep >= 2 && total > 0 ? ` · ${formatCurrency(total)}` : ''}` : 'Nessuna iscrizione';
	  root.querySelectorAll('[data-mi-ticket]').forEach((input) => {
		input.closest('.mi-registration__ticket')?.classList.toggle('is-selected', Number(input.value) > 0);
	  });
	}

	function showStep(step, focusHeading = true) {
	  currentStep = Math.min(3, Math.max(1, step));
	  if (currentStep === 3) renderConfirmationSummary();
	  steps.forEach((section) => { section.hidden = Number(section.dataset.miStep) !== currentStep; });
	  root.querySelectorAll('[data-mi-progress]').forEach((item) => {
		const itemStep = Number(item.dataset.miProgress);
		if (itemStep === currentStep) item.setAttribute('aria-current', 'step');
		else item.removeAttribute('aria-current');
		item.classList.toggle('is-complete', itemStep < currentStep);
	  });
	  backButton.hidden = currentStep === 1;
	  nextButton.hidden = currentStep === 3;
	  submitButton.hidden = currentStep !== 3;
      paymentInstructionsNote.hidden = currentStep !== 3 || totalCents() <= 0 || !['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(config.event.economic_mode);
      paymentInstructionsNote.textContent = `Nell’email di conferma ${totalQuantity() > 1 ? 'delle prenotazioni' : 'della prenotazione'} riceverai le istruzioni per il pagamento.`;
	  nextButton.textContent = currentStep === 2 ? 'Vai alla conferma' : 'Continua';
	  updateStickySummary();
	  if (focusHeading) steps[currentStep - 1]?.querySelector('h2')?.focus();
	}

    function renderConfirmationSummary() {
      captureParticipants();
      let summary = steps[2].querySelector('[data-mi-confirmation-summary]');
      if (!summary) {
        summary = document.createElement('div');
        summary.dataset.miConfirmationSummary = '';
        summary.className = 'mi-registration__confirmation-summary';
        steps[2].querySelector('h2').after(summary);
      }
      summary.replaceChildren();
      const line = (parent, title, value, emphasizeValue = false) => {
        const row = document.createElement('p');
        const label = document.createElement('strong');
        label.textContent = title;
        const detail = document.createElement(emphasizeValue ? 'strong' : 'span');
        detail.textContent = value;
        row.append(label, detail);
        parent.append(row);
      };
      const buyerName = [buyerFirstName.value, buyerLastName.value].filter(Boolean).join(' ');
      const total = totalCents();
      summary.classList.toggle('is-free', total === 0);
      if (total === 0) {
        const freeRegistration = document.createElement('p');
        freeRegistration.className = 'mi-registration__free-confirmation';
        const freeRegistrationText = document.createElement('strong');
        freeRegistrationText.textContent = `Iscrizione di ${buyerName}`;
        freeRegistration.append(freeRegistrationText);
        summary.append(freeRegistration);
        return;
      }
      const options = config.event.options || [];
      const participantCosts = participantValues.map((participant) => {
        const ticket = (config.event.ticket_types || []).find((item) => item.code === participant.key.split(':')[0]);
		const base = config.event.pricing_mode === 'FIXED' ? Number(config.event.fixed_price_cents) || 0 : (config.event.pricing_mode === 'CALCULATED' ? Number(ticket?.price_cents) || 0 : 0);
        const selected = options.filter((option) => option.scope === 'TICKET').map((option) => ({ option, quantity: Number(participant.options?.[option.code]) || 0 })).filter((item) => item.quantity > 0).map((item) => ({ ...item, cost: item.quantity * (Number(item.option.price_cents) || 0) }));
        return { participant, base, selected, subtotal: selected.reduce((total, item) => total + item.cost, base) };
      });
      const orderSelection = orderOptionSelection();
      const selectedOrderOptions = options.filter((option) => option.scope === 'ORDER').map((option) => ({ option, quantity: Number(orderSelection[option.code]) || 0 })).filter((item) => item.quantity > 0).map((item) => ({ ...item, cost: item.quantity * (Number(item.option.price_cents) || 0) }));
      const deposit = total > 0 && config.event.economic_mode === 'DEPOSIT_BALANCE'
        ? (config.event.deposit_mode === 'FIXED' ? Math.min(total, (Number(config.event.deposit_fixed_cents) || 0) * totalQuantity()) : Math.round(total * Number(config.event.deposit_percentage || 30) / 100))
        : 0;
      const optionCategory = (option) => {
        const code = String(option.code || ''), name = String(option.name || ''), category = String(option.category || '');
        if (code.startsWith('alloggio-') || category === 'alloggio') return 'alloggio';
        if (code === 'colazione' || code.startsWith('assicurazione-') || /assicurazione|disdetta/i.test(name) || category === 'supplemento') return 'supplemento';
        if (code.startsWith('pullman') || ['pullman', 'trasferimento'].includes(category)) return 'trasferimento';
        if (code === 'pranzo' || category === 'pranzo') return 'pranzo';
        return 'altro';
      };
      const compactDescription = ({ base, selected }) => {
        const grouped = selected.reduce((all, item) => { const category = optionCategory(item.option); (all[category] ??= []).push(item); return all; }, {});
        const parts = [];
        if (base > 0) parts.push('quota di partecipazione');
        if (grouped.alloggio?.length) parts.push(...grouped.alloggio.map(({ option }) => String(option.name || 'Alloggio').replace(/^Alloggio:\s*/i, '').toLocaleLowerCase('it')));
        const transferCount = (grouped.trasferimento || []).reduce((count, item) => count + item.quantity, 0);
        if (transferCount) parts.push(`${transferCount} ${transferCount === 1 ? 'trasferimento' : 'trasferimenti'}`);
        const supplements = grouped.supplemento || [];
        if (supplements.some(({ option }) => option.code === 'colazione' || /colazione/i.test(option.name || ''))) parts.push('colazione');
        const insuranceCount = supplements.filter(({ option }) => String(option.code || '').startsWith('assicurazione-') || /assicurazione|disdetta/i.test(option.name || '')).length;
        if (insuranceCount) parts.push(insuranceCount === 1 ? 'assicurazione' : `${insuranceCount} assicurazioni`);
        const otherSupplements = supplements.filter(({ option }) => option.code !== 'colazione' && !/colazione|assicurazione|disdetta/i.test(String(option.code || '') + ' ' + String(option.name || '')));
        parts.push(...otherSupplements.map(({ option }) => String(option.name || 'Supplemento').toLocaleLowerCase('it')));
        const mealCount = (grouped.pranzo || []).reduce((count, item) => count + item.quantity, 0);
        if (mealCount) parts.push(mealCount === 1 ? 'pasto' : `${mealCount} pasti`);
        parts.push(...(grouped.altro || []).map(({ option }) => String(option.name || 'Altra voce').toLocaleLowerCase('it')));
        return parts.join(' · ') || 'quota compresa';
      };
      const renderParticipantDetail = (parent, cost) => {
        const { participant, base, selected, subtotal } = cost;
        const section = document.createElement('section');
        const heading = document.createElement('h3');
        heading.textContent = `${participant.firstName} ${participant.lastName}`;
        section.append(heading);
        if (base > 0) line(section, 'Quota di partecipazione', formatCurrency(base));
        selected.forEach(({ option, quantity, cost: optionCost }) => {
          line(section, `${option.name}${quantity > 1 ? ` × ${quantity}` : ''}`, formatCurrency(optionCost));
        });
        line(section, 'Totale partecipante', subtotal > 0 ? formatCurrency(subtotal) : 'Gratuito');
        parent.append(section);
      };
      const renderTotals = (parent, showOrderDetails = false) => {
        if (showOrderDetails) selectedOrderOptions.forEach(({ option, quantity, cost }) => line(parent, `${option.name}${quantity > 1 ? ` × ${quantity}` : ''}`, formatCurrency(cost)));
        else if (selectedOrderOptions.length) line(parent, 'Voci comuni', formatCurrency(selectedOrderOptions.reduce((sum, item) => sum + item.cost, 0)));
        line(parent, 'Totale', total > 0 ? formatCurrency(total) : 'Gratuito', true);
        if (deposit > 0) {
          line(parent, 'Caparra', formatCurrency(deposit), true);
          line(parent, 'Saldo', formatCurrency(total - deposit), true);
        }
      };
      if (participantCosts.length > 1 && total > 0) {
        const overview = document.createElement('section');
        overview.className = 'mi-registration__booking-overview';
        const title = document.createElement('h3');
        title.textContent = 'Riepilogo della prenotazione';
        const bookingName = document.createElement('p');
        bookingName.className = 'mi-registration__booking-name';
        const bookingNameLabel = document.createElement('span');
        bookingNameLabel.textContent = 'Iscrizione a nome di';
        const bookingNameValue = document.createElement('strong');
        bookingNameValue.textContent = buyerName;
        bookingName.append(bookingNameLabel, bookingNameValue);
        const list = document.createElement('ul');
        participantCosts.forEach((cost) => {
          const item = document.createElement('li');
          const identity = document.createElement('div');
          identity.className = 'mi-registration__booking-person';
          const heading = document.createElement('strong');
          heading.textContent = `${cost.participant.firstName} ${cost.participant.lastName}`;
          const description = document.createElement('span');
          description.textContent = compactDescription(cost);
          const amount = document.createElement('strong');
          amount.className = 'mi-registration__booking-amount';
          amount.textContent = formatCurrency(cost.subtotal);
          identity.append(heading, description);
          item.append(identity, amount);
          list.append(item);
        });
        const totals = document.createElement('div');
        totals.className = 'mi-registration__booking-totals';
        renderTotals(totals);
        overview.append(title, bookingName, list, totals);
        summary.append(overview);
        const details = document.createElement('details');
        details.className = 'mi-registration__cost-details';
        const detailsSummary = document.createElement('summary');
        detailsSummary.textContent = 'Dettaglio dei costi';
        const detailedCosts = document.createElement('div');
        participantCosts.forEach((cost) => renderParticipantDetail(detailedCosts, cost));
        renderTotals(detailedCosts, true);
        details.append(detailsSummary, detailedCosts);
        summary.append(details);
      } else {
        line(summary, 'Iscrizione a nome di', buyerName);
        participantCosts.forEach((cost) => renderParticipantDetail(summary, cost));
        renderTotals(summary, true);
      }
    }

	function currentStepIsValid() {
	  errorBox.hidden = true;
	  if (currentStep === 1 && totalQuantity() < 1) {
		showError('Seleziona almeno una iscrizione per continuare.');
		return false;
	  }
	  const fields = Array.from(steps[currentStep - 1].querySelectorAll('input, select, textarea'));
	  fields.filter((field) => field.type === 'tel').forEach((field) => validatePhoneField(field, true, true));
	  const invalid = fields.find((field) => !field.checkValidity());
	  if (invalid) { revealInvalidField(invalid); return false; }
	  return true;
	}

	function revealInvalidField(field) {
	  field.focus({ preventScroll: true });
	  field.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
	  field.reportValidity();
	}

    function captureParticipants() {
	  const previousValues = participantValues;
      participantValues = Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).map((row) => {
		const key = `${row.dataset.miTicketType}:${row.dataset.miTicketIndex}`;
		const previous = previousValues.find((value) => value.key === key) || {};
		return {
        key,
        firstName: row.querySelector('[data-mi-first-name]')?.value || '',
        lastName: row.querySelector('[data-mi-last-name]')?.value || '',
		fields: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.type === 'tel' ? core.normalizePhone(input.value) : input.value.trim()])),
		options: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-option]')).map((input) => [input.dataset.miParticipantOption, String(participantOptionQuantity(input))])),
		noAccommodation: Boolean(row.querySelector('[data-mi-no-accommodation]')?.checked)
		};
	  });
    }

    function renderParticipants() {
      captureParticipants();
      const quantity = totalQuantity();
	  const participantsHeading = form.querySelector('[data-mi-participants-heading]');
	  if (participantsHeading) participantsHeading.textContent = quantity > 1 ? 'Prenotazioni' : 'Prenotazione';
      renderEconomicSummary();
	  updateStickySummary();
      participantsRoot.replaceChildren();
      if (!quantity) {
        const hint = document.createElement('p');
        hint.className = 'mi-registration__hint';
        hint.textContent = 'Seleziona almeno una iscrizione.';
        participantsRoot.append(hint);
        return;
      }
      const tickets = [];
      (config.event.ticket_types || []).forEach((ticket) => {
        const selected = ticketSelection()[ticket.code] || 0;
        for (let position = 1; position <= selected; position += 1) tickets.push({ ...ticket, position });
      });
      tickets.forEach((ticket, index) => {
        const key = `${ticket.code}:${ticket.position}`;
        const previous = participantValues.find((value) => value.key === key) || {};
        const row = document.createElement('fieldset');
        row.className = 'mi-registration__participant';
        row.dataset.miTicketType = ticket.code;
        row.dataset.miTicketIndex = String(ticket.position);
        const legend = document.createElement('legend');
		legend.textContent = quantity > 1 ? `Prenotazione ${index + 1}` : 'Prenotazione';
        const grid = document.createElement('div');
        grid.className = 'mi-registration__grid';
        grid.append(
          participantField('Nome', 'given-name', 'firstName', previous.firstName || '', index),
          participantField('Cognome', 'family-name', 'lastName', previous.lastName || '', index)
        );
		const allRequired = config.event.participant_extra_scope === 'ALL';
		const participantFields = config.event.participant_fields || [];
		participantFields
		  .filter((field) => !String(field.key || '').startsWith('custom_'))
		  .forEach((field) => grid.append(configuredParticipantField(field, previous.fields?.[field.key] || '', index, allRequired || index === 0)));
		const answerFields = participantFields.filter((field) => String(field.key || '').startsWith('custom_'));
		if (answerFields.length) {
		  const answers = document.createElement('fieldset');
		  answers.className = 'mi-registration__answers';
		  const answersLegend = document.createElement('legend');
		  answersLegend.textContent = 'Domande per la partecipazione';
		  const answersGrid = document.createElement('div');
		  answersGrid.className = 'mi-registration__answers-grid';
		  answerFields.forEach((field) => answersGrid.append(configuredParticipantField(field, previous.fields?.[field.key] || '', index, allRequired || index === 0)));
		  answers.append(answersLegend, answersGrid);
		  grid.append(answers);
		}
		appendParticipantOptionGroups(grid, (config.event.options || []).filter((option) => option.scope === 'TICKET'), previous, index);
        row.append(legend, grid);
		participantsRoot.append(row);
      });
	  prefillFirstBookingFromContact();
      renderEconomicSummary();
      updateStickySummary();
    }

    function participantField(labelText, autocomplete, key, value, index) {
      const label = document.createElement('label');
      label.textContent = `${labelText} *`;
      const input = document.createElement('input');
      input.name = `participant-${index}-${key}`;
      input.maxLength = 80;
      input.autocomplete = `section-participant-${index + 1} ${autocomplete}`;
	  input.required = true;
      input.value = value;
      input.dataset[key === 'firstName' ? 'miFirstName' : 'miLastName'] = '';
      label.append(input);
      return label;
    }

    function configuredParticipantField(field, value, index, required = false) {
      const label = document.createElement('label');
	  const fieldLabel = field.key === 'document_issue_date' ? 'Data rilascio documento identità' : field.label;
	  label.textContent = required && field.required ? `${fieldLabel} *` : fieldLabel;
      let input;
      if (field.type === 'select' || field.type === 'yesno') {
        input = document.createElement('select');
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = field.type === 'yesno' ? 'Scegli la risposta appropriata' : 'Seleziona';
        input.append(empty);
        (field.options || []).forEach((choice) => {
          const option = document.createElement('option');
          option.value = choice;
          option.textContent = choice;
          input.append(option);
        });
      } else if (field.type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 3;
	  } else {
		input = document.createElement('input');
		input.type = ['date', 'email', 'tel'].includes(field.type) ? field.type : 'text';
      }
      input.name = `participant-${index}-field-${field.key}`;
	  input.required = required && Boolean(field.required);
      input.value = value;
      input.dataset.miParticipantField = field.key;
      if (field.max_length) input.maxLength = field.max_length;
      if (field.autocomplete) input.autocomplete = `section-participant-${index + 1} ${field.autocomplete}`;
	  if (field.type === 'date') {
		const futureDate = field.date_rule === 'future';
		input.min = localDateWithYearOffset(futureDate ? 0 : -120);
		input.max = localDateWithYearOffset(futureDate ? 20 : 0);
	  }
      label.append(input);
	  if (field.type === 'tel') preparePhoneField(input);
	  if (field.help && field.key !== 'birth_date') {
        const help = document.createElement('small');
        help.className = 'mi-registration__field-help';
        help.textContent = field.help;
        label.append(help);
      }
      return label;
    }

    function participantOptionQuantity(input) {
      return ['radio', 'checkbox'].includes(input.type) ? (input.checked ? 1 : 0) : (Number.parseInt(input.value, 10) || 0);
    }

    function participantOptionGroup(option) {
      const code = String(option.code || '').toLowerCase();
      const category = String(option.category || '').toLowerCase();
      if (code.startsWith('alloggio-') || category === 'alloggio') return 'alloggio';
      if (code === 'colazione' || code.startsWith('assicurazione-') || category === 'supplemento') return 'supplemento';
      if (code.startsWith('pullman') || category === 'pullman' || category === 'trasferimento') return 'trasferimento';
      if (code === 'pranzo' || ['pranzo', 'pasto', 'pasti'].includes(category)) return 'pasti';
      return 'altro';
    }

    function appendParticipantOptionGroups(grid, options, previous, index) {
      const definitions = [['alloggio', 'Alloggio'], ['supplemento', 'Supplementi'], ['trasferimento', 'Trasferimenti'], ['pasti', 'Pasti'], ['altro', 'Altro']];
      definitions.forEach(([groupCode, groupLabel]) => {
        const groupOptions = options.filter((option) => participantOptionGroup(option) === groupCode);
        if (!groupOptions.length) return;
        const fieldset = document.createElement('fieldset');
        fieldset.className = 'mi-registration__service-group';
        fieldset.dataset.miServiceGroup = groupCode;
        const legend = document.createElement('legend');
        legend.textContent = groupLabel;
        const choices = document.createElement('div');
        choices.className = 'mi-registration__service-options';
        groupOptions.forEach((option) => choices.append(configuredParticipantOption(option, previous.options?.[option.code] || '0', index)));
        const choiceGroups = new Set(groupOptions.map((option) => option.choice_group || (String(option.code || '').startsWith('alloggio-') ? 'alloggio' : '')).filter(Boolean));
        choiceGroups.forEach((choiceGroup) => {
          if (choiceGroup === 'alloggio') {
            const none = document.createElement('label');
            none.className = 'mi-registration__no-accommodation';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.dataset.miNoAccommodation = '1';
            checkbox.checked = Boolean(previous.noAccommodation) && !choices.querySelector('[data-mi-choice-group="alloggio"]:checked');
            const text = document.createElement('span');
            text.textContent = 'Non desidero alloggio';
            checkbox.addEventListener('change', () => {
              if (checkbox.checked) choices.querySelectorAll('[data-mi-choice-group="alloggio"]').forEach((input) => { input.checked = false; });
              renderEconomicSummary(); updateStickySummary();
            });
            none.append(checkbox, text);
            choices.append(none);
            return;
          }
          const clear = document.createElement('button');
          clear.type = 'button';
          clear.className = 'mi-registration__clear-choice';
          clear.textContent = `Nessuna scelta: ${choiceGroup}`;
          clear.addEventListener('click', () => {
            choices.querySelectorAll('[data-mi-choice-group]').forEach((input) => { if (input.dataset.miChoiceGroup === choiceGroup) input.checked = false; });
            renderEconomicSummary(); updateStickySummary();
          });
          choices.append(clear);
        });
        fieldset.append(legend, choices);
        grid.append(fieldset);
      });
    }

    function configuredParticipantOption(option, value, index) {
      const label = document.createElement('label');
      const isAccommodation = String(option.code || '').startsWith('alloggio-');
      const visualGroup = participantOptionGroup(option);
      const choiceGroup = option.choice_group || (isAccommodation ? 'alloggio' : '');
      const isSingleChoice = Number(option.max_quantity || 1) === 1;
      label.className = isAccommodation || isSingleChoice ? 'mi-registration__option-choice' : '';
      const input = document.createElement('input');
      input.type = choiceGroup ? 'radio' : (isSingleChoice ? 'checkbox' : 'number');
      if (choiceGroup) {
        input.name = `participant-${index}-choice-${choiceGroup}`;
        input.value = '1';
        input.checked = String(value) === '1';
      } else if (isSingleChoice) {
        input.name = `participant-${index}-option-${option.code}`;
        input.value = '1';
        input.checked = String(value) === '1';
      } else {
        input.min = '0';
        input.max = String(option.max_quantity || 1);
        input.value = value;
        input.name = `participant-${index}-option-${option.code}`;
      }
      input.dataset.miParticipantOption = option.code;
      input.dataset.miChoiceGroup = choiceGroup;
      label.dataset.serviceCategory = option.category || (isAccommodation ? 'alloggio' : (String(option.code).startsWith('pullman') ? 'pullman' : (option.code === 'pranzo' ? 'pranzo' : (option.code === 'colazione' || String(option.code).startsWith('assicurazione-') ? 'supplemento' : 'altro'))));
      input.addEventListener(isAccommodation || isSingleChoice ? 'change' : 'input', () => {
        if(choiceGroup==='alloggio'&&input.checked){const none=input.closest('.mi-registration__service-group')?.querySelector('[data-mi-no-accommodation]');if(none)none.checked=false;}
        renderEconomicSummary();updateStickySummary();
      });
      const content = document.createElement('span');
      content.className = 'mi-registration__option-content';
      const name = document.createElement('span');
      name.className = 'mi-registration__option-name';
      const originalName = String(option.name || '');
      name.textContent = visualGroup === 'alloggio' ? originalName.replace(/^Alloggio\s*:\s*/i, '') : (visualGroup === 'trasferimento' ? originalName.replace(/^Pullman\s*[-–—:]?\s*/i, '') : originalName);
      if (visualGroup === 'trasferimento' && /^Pullman\b/i.test(originalName)) {
        const descriptor = document.createElement('small');
        descriptor.className = 'mi-registration__option-descriptor';
        descriptor.textContent = 'Pullman';
        content.append(descriptor);
      }
      content.append(name);
      label.append(input, content);
      if (Number(option.price_cents) > 0) {
        const price = document.createElement('span');
        price.className = 'mi-registration__option-price';
        price.textContent = formatCurrency(option.price_cents);
        label.append(price);
      }
      return label;
    }

    function participantPayload() {
	  captureParticipants();
	  return participantValues.map((participant) => {
		const [ticketTypeCode, ticketIndex] = participant.key.split(':');
		return {
		ticket_type_code: ticketTypeCode,
		ticket_index: Number(ticketIndex),
		first_name: participant.firstName.trim(),
		last_name: participant.lastName.trim(),
		fields: participant.fields || {},
		options: participant.options || {}
		};
	  });
    }

    function showError(message) {
      errorBox.textContent = message;
      errorBox.hidden = false;
      errorBox.focus?.();
    }

    function createBarcode(code) {
      const patterns = {
        0: 'nnnwwnwnn', 1: 'wnnwnnnnw', 2: 'nnwwnnnnw', 3: 'wnwwnnnnn', 4: 'nnnwwnnnw', 5: 'wnnwwnnnn', 6: 'nnwwwnnnn', 7: 'nnnwnnwnw', 8: 'wnnwnnwnn', 9: 'nnwwnnwnn',
        A: 'wnnnnwnnw', B: 'nnwnnwnnw', C: 'wnwnnwnnn', D: 'nnnnwwnnw', E: 'wnnnwwnnn', F: 'nnwnwwnnn', G: 'nnnnnwwnw', H: 'wnnnnwwnn', I: 'nnwnnwwnn', J: 'nnnnwwwnn',
        K: 'wnnnnnnww', L: 'nnwnnnnww', M: 'wnwnnnnwn', N: 'nnnnwnnww', O: 'wnnnwnnwn', P: 'nnwnwnnwn', Q: 'nnnnnnwww', R: 'wnnnnnwwn', S: 'nnwnnnwwn', T: 'nnnnwnwwn',
        U: 'wwnnnnnnw', V: 'nwwnnnnnw', W: 'wwwnnnnnn', X: 'nwnnwnnnw', Y: 'wwnnwnnnn', Z: 'nwwnwnnnn', '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '*': 'nwnnwnwnn'
      };
      const text = String(code).toUpperCase().replace(/[^0-9A-Z. -]/g, '');
      const bars = [];
      let x = 10;
      `*${text}*`.split('').forEach((character) => {
        patterns[character].split('').forEach((width, index) => {
          const units = width === 'w' ? 3 : 1;
          if (index % 2 === 0) bars.push({ x, width: units });
          x += units;
        });
        x += 1;
      });
      const namespace = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(namespace, 'svg');
      svg.setAttribute('viewBox', `0 0 ${x + 10} 82`);
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', `Codice a barre ${text}`);
      const background = document.createElementNS(namespace, 'rect');
      background.setAttribute('width', '100%'); background.setAttribute('height', '100%'); background.setAttribute('fill', 'white');
      svg.append(background);
      bars.forEach((bar) => {
        const rect = document.createElementNS(namespace, 'rect');
        rect.setAttribute('x', String(bar.x)); rect.setAttribute('y', '5'); rect.setAttribute('width', String(bar.width)); rect.setAttribute('height', '60');
        svg.append(rect);
      });
      const label = document.createElementNS(namespace, 'text');
      label.setAttribute('x', '50%'); label.setAttribute('y', '78'); label.setAttribute('text-anchor', 'middle'); label.setAttribute('font-family', 'monospace'); label.setAttribute('font-size', '10');
      label.textContent = text;
      svg.append(label);
      return svg;
    }

    root.querySelectorAll('[data-mi-ticket]').forEach((input) => {
      input.addEventListener('input', renderParticipants);
    });
    root.querySelectorAll('[data-mi-order-option]').forEach((input) => {
      input.addEventListener('input', () => { renderEconomicSummary(); updateStickySummary(); });
    });

    let accommodationNotice = null;
    function confirmMissingAccommodation() {
      accommodationNotice?.remove();
      accommodationNotice = null;
      const missing = Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).filter((row) => {
        const choices = Array.from(row.querySelectorAll('[data-mi-participant-option]')).filter((input) => input.dataset.miChoiceGroup==='alloggio');
        const declined = row.querySelector('[data-mi-no-accommodation]')?.checked;
        return choices.length && !declined && !choices.some((input) => participantOptionQuantity(input) > 0);
      });
      if (!missing.length) return false;
      const notice = document.createElement('section');
      notice.className = 'mi-registration__accommodation-notice';
      notice.tabIndex = -1;
      const title = document.createElement('h3');
      title.textContent = 'Siamo sicuri?';
      const description = document.createElement('p');
      const names = missing.map((row) => [row.querySelector('[data-mi-first-name]')?.value, row.querySelector('[data-mi-last-name]')?.value].map((value) => String(value || '').trim()).filter(Boolean).join(' ') || row.querySelector('legend')?.textContent || 'Iscritto');
      const namesLabel = names.length > 1 ? `${names.slice(0, -1).join(', ')} e ${names[names.length - 1]}` : names[0];
      description.textContent = `${namesLabel} ${names.length === 1 ? 'non ha scelto' : 'non hanno scelto'} alloggio.`;
      const choose = document.createElement('button');
      choose.type = 'button';
      choose.textContent = 'No, scegli l’alloggio';
      choose.addEventListener('click', () => {
        notice.remove();
        missing[0].querySelector('[data-mi-choice-group="alloggio"]')?.focus();
      });
      const proceed = document.createElement('button');
      proceed.type = 'button';
      proceed.textContent = 'Sì, prosegui';
      proceed.addEventListener('click', () => {
        if (!currentStepIsValid()) return;
        notice.remove();
        showStep(3);
      });
      notice.append(description, title, choose, proceed);
      steps[1].append(notice);
      accommodationNotice = notice;
      notice.focus();
      notice.scrollIntoView({ block: 'center' });
      return true;
    }
    participantsRoot.addEventListener('input', () => accommodationNotice?.remove());
	nextButton.addEventListener('click', () => {
	  if (!currentStepIsValid()) return;
	  if (currentStep === 2 && confirmMissingAccommodation()) return;
	  if (currentStep === 1) renderParticipants();
	  showStep(currentStep + 1);
	});
	backButton.addEventListener('click', () => showStep(currentStep - 1));

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      errorBox.hidden = true;
	  if (config.preview) {
		showError('Questa è un’anteprima riservata: nessuna iscrizione è stata inviata.');
		return;
	  }
      if (totalQuantity() < 1) {
        showError('Seleziona almeno una iscrizione.');
        return;
      }
      validatePhoneField(buyerPhone, true, true);
      if (!form.reportValidity()) return;
      if (!core.isValidPhone(String(buyerPhone?.value || ''))) {
		showError('Inserisci un numero di cellulare completo.');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Invio in corso…';
      const formData = new FormData(form);
      const payload = {
        started_at: startedAt,
        website: formData.get('website') || '',
        privacy_accepted: formData.get('privacyAccepted') === 'on',
		marketing_accepted: formData.get('marketingAccepted') === 'on',
        tickets: ticketSelection(),
		order_options: orderOptionSelection(),
        participants: participantPayload(),
		buyer: {
          first_name: String(formData.get('buyerFirstName') || '').trim(),
          last_name: String(formData.get('buyerLastName') || '').trim(),
          email: String(formData.get('buyerEmail') || '').trim(),
		  phone: String(formData.get('buyerPhone') || '').trim()
		},
		special_requests: String(formData.get('specialRequests') || '').trim()
      };

      try {
        const response = await fetch(config.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-Idempotency-Key': requestKey
		},
          body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Invio non riuscito.');
        form.hidden = true;
        successBox.hidden = false;
		const confirmationEmail = String(formData.get('buyerEmail') || '').trim();
		const confirmationName = [formData.get('buyerFirstName'), formData.get('buyerLastName')]
		  .map((value) => String(value || '').trim())
		  .filter(Boolean)
		  .join(' ');
		const successText = result.status === 'WAITLISTED'
		  ? `Richiesta inserita in lista d’attesa. Riceverai gli aggiornamenti alla casella: ${confirmationEmail}`
		  : result.status === 'PENDING_PAYMENT'
			? pendingPaymentConfirmation(confirmationName, result.economic_summary)
			: '';
        if (result.status === 'WAITLISTED' || result.status === 'PENDING_PAYMENT') {
          successBox.textContent = successText;
        } else {
          const successHeading = document.createElement('h2');
          successHeading.className = 'mi-registration__success-heading';
          successHeading.textContent = 'Iscrizione confermata';
          const successMessage = document.createElement('p');
          successMessage.className = 'mi-registration__success-message';
          if (confirmationEmail) {
            successMessage.append('Abbiamo inviato l’email di conferma a ');
            const emailAddress = document.createElement('strong');
            emailAddress.textContent = confirmationEmail;
            successMessage.append(emailAddress, '.');
          } else {
            successMessage.textContent = `L’iscrizione di ${confirmationName} è stata registrata.`;
          }
          successBox.replaceChildren(successHeading, successMessage);
        }
        if (config.event.identifier_display === 'QR') {
		  try {
			await ensureQrGenerator();
			const qr = window.qrcode(0, 'M');
			qr.addData(`modulo-iscrizioni|evento:${config.event.id}|ordine:${result.order_code}`);
			qr.make();
			const qrBox = document.createElement('div');
			qrBox.className = 'mi-registration__qr';
			qrBox.setAttribute('aria-label', 'Codice QR dell’iscrizione');
			qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 4 });
			successBox.appendChild(qrBox);
		  } catch (qrError) {
			// L'iscrizione è già salvata: un problema grafico non deve mai
			// trasformare il successo del server in un falso errore di invio.
		  }
		} else if (config.event.identifier_display === 'BARCODE') {
		  const barcodeBox = document.createElement('div');
		  barcodeBox.className = 'mi-registration__barcode';
		  barcodeBox.appendChild(createBarcode(result.order_code));
		  successBox.appendChild(barcodeBox);
        }
        successBox.focus();
        let completionUrl = config.homeUrl || '/';
        try {
          const destination = new URL(config.event.completion_url || completionUrl, window.location.href);
          if (['http:', 'https:'].includes(destination.protocol)) completionUrl = destination.href;
        } catch (invalidDestination) { /* La homepage rimane la destinazione di riserva. */ }
        const completion = document.createElement('div');
        completion.className = 'mi-registration__completion';
        const countdown = document.createElement('p');
		countdown.className = 'mi-registration__countdown';
        const finish = document.createElement('a');
        finish.className = 'mi-registration__finish';
        finish.href = completionUrl;
        finish.textContent = 'Termina';
        let seconds = 15;
		countdown.textContent = `La pagina successiva si aprirà automaticamente tra ${seconds} secondi.`;
        completion.append(countdown, finish);
        successBox.append(completion);
        const completionTimer = window.setInterval(() => {
          seconds -= 1;
		  countdown.textContent = `La pagina successiva si aprirà automaticamente tra ${seconds} ${seconds === 1 ? 'secondo' : 'secondi'}.`;
          if (seconds <= 0) {
            window.clearInterval(completionTimer);
            window.location.assign(completionUrl);
          }
        }, 1000);
        finish.addEventListener('click', () => window.clearInterval(completionTimer));
        window.addEventListener('pagehide', () => window.clearInterval(completionTimer), { once: true });
      } catch (error) {
        showError(error.message || 'Invio non riuscito. Riprova.');
        submitButton.disabled = false;
        submitButton.textContent = 'Invia iscrizione';
      }
    });

    renderParticipants();
	showStep(1, false);
  });
}());
