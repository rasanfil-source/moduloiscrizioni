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
    const economicSummary = root.querySelector('[data-mi-economic-summary]');
	const buyerFirstName = form.elements.namedItem('buyerFirstName');
	const buyerLastName = form.elements.namedItem('buyerLastName');
	const steps = Array.from(root.querySelectorAll('[data-mi-step]'));
	const nextButton = root.querySelector('[data-mi-next]');
	const backButton = root.querySelector('[data-mi-back]');
	const stickySummary = root.querySelector('[data-mi-sticky-summary]');
	const marketingInput = form.elements.namedItem('marketingAccepted');
	const marketingText = marketingInput?.closest('label')?.querySelector('span');
	if (marketingText) marketingText.textContent = 'Vuoi essere avvisato delle future iniziative? Il consenso è facoltativo e può essere revocato.';
	const participantDetailsRoot = document.createElement('div');
	participantDetailsRoot.className = 'mi-registration__participant-details';
	participantDetailsRoot.dataset.miParticipantDetails = '';
	steps[2]?.querySelector('.mi-registration__grid')?.before(participantDetailsRoot);
	if (config.event.special_requests_enabled) {
	  const specialRequestsLabel = document.createElement('label');
	  specialRequestsLabel.className = 'mi-registration__special-requests';
	  specialRequestsLabel.textContent = 'Richieste particolari (facoltativo)';
	  const specialRequestsInput = document.createElement('textarea');
	  specialRequestsInput.name = 'specialRequests';
	  specialRequestsInput.rows = 4;
	  specialRequestsInput.maxLength = 2000;
	  specialRequestsInput.placeholder = 'Segnala esigenze organizzative, alimentari o di accessibilità che ritieni utile comunicare.';
	  specialRequestsLabel.append(specialRequestsInput);
	  steps[2]?.querySelector('.mi-registration__grid')?.before(specialRequestsLabel);
	}
	let currentStep = 1;
    let requestKey = makeRequestKey();
    let participantValues = [];
	const buyerEdited = { firstName: false, lastName: false };
	buyerFirstName?.addEventListener('input', () => { buyerEdited.firstName = true; });
	buyerLastName?.addEventListener('input', () => { buyerEdited.lastName = true; });

	function prefillBuyerFromFirstParticipant() {
	  const firstParticipant = participantsRoot.querySelector('.mi-registration__participant');
	  if (!firstParticipant) return;
	  if (!buyerEdited.firstName && buyerFirstName) buyerFirstName.value = firstParticipant.querySelector('[data-mi-first-name]')?.value.trim() || '';
	  if (!buyerEdited.lastName && buyerLastName) buyerLastName.value = firstParticipant.querySelector('[data-mi-last-name]')?.value.trim() || '';
	}

	function updateBuyerStepHeading() {
	  const heading = steps[2]?.querySelector('h2');
	  if (!heading) return;
	  heading.textContent = 'Qualche dato aggiuntivo';
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

    function totalCents() {
	  const prices = Object.fromEntries((config.event.ticket_types || []).map((ticket) => [ticket.code, config.event.pricing_mode === 'FIXED' ? Number(config.event.fixed_price_cents) || 0 : Number(ticket.price_cents) || 0]));
      const optionPrices = Object.fromEntries((config.event.options || []).map((option) => [option.code, Number(option.price_cents) || 0]));
      const ticketTotal = Object.entries(ticketSelection()).reduce((total, [code, quantity]) => total + (prices[code] || 0) * quantity, 0);
      const orderTotal = Object.entries(orderOptionSelection()).reduce((total, [code, quantity]) => total + (optionPrices[code] || 0) * quantity, 0);
      const participantTotal = Array.from(participantsRoot.querySelectorAll('[data-mi-participant-option]')).reduce((total, input) => total + (optionPrices[input.dataset.miParticipantOption] || 0) * (Number.parseInt(input.value, 10) || 0), 0);
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
		const initial = fixedDeposit ? Math.min(total, Number(config.event.deposit_fixed_cents || 0)) : Math.round(total * Number(config.event.deposit_percentage || 30) / 100);
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
	  const priceLabel = ['FIXED', 'CALCULATED'].includes(config.event.pricing_mode) ? formatCurrency(totalCents()) : (config.event.pricing_mode === 'ZERO' ? 'evento gratuito' : '');
	  stickySummary.textContent = quantity ? `${quantityLabel}${priceLabel ? ` · ${priceLabel}` : ''}` : 'Nessuna iscrizione';
	  root.querySelectorAll('[data-mi-ticket]').forEach((input) => {
		input.closest('.mi-registration__ticket')?.classList.toggle('is-selected', Number(input.value) > 0);
	  });
	}

	function showStep(step, focusHeading = true) {
	  currentStep = Math.min(3, Math.max(1, step));
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
	  nextButton.textContent = currentStep === 2 ? 'Vai alla conferma' : 'Continua';
	  if (focusHeading) steps[currentStep - 1]?.querySelector('h2')?.focus();
	}

	function currentStepIsValid() {
	  errorBox.hidden = true;
	  if (currentStep === 1 && totalQuantity() < 1) {
		showError('Seleziona almeno una iscrizione per continuare.');
		return false;
	  }
	  const fields = Array.from(steps[currentStep - 1].querySelectorAll('input, select, textarea'));
	  const invalid = fields.find((field) => !field.checkValidity());
	  if (invalid) { invalid.reportValidity(); return false; }
	  return true;
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
		fields: previous.fields || {},
		options: previous.options || {}
		};
	  });
    }

    function renderParticipants() {
      captureParticipants();
      const quantity = totalQuantity();
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
        legend.textContent = `Partecipante ${index + 1} · ${ticket.name}`;
        const grid = document.createElement('div');
        grid.className = 'mi-registration__grid';
        grid.append(
          participantField('Nome', 'given-name', 'firstName', previous.firstName || '', index),
          participantField('Cognome', 'family-name', 'lastName', previous.lastName || '', index)
        );
        row.append(legend, grid);
		participantsRoot.append(row);
      });
      renderEconomicSummary();
      updateStickySummary();
    }

    function participantField(labelText, autocomplete, key, value, index) {
      const label = document.createElement('label');
      label.textContent = labelText;
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

	function renderParticipantDetails() {
	  captureParticipants();
	  participantDetailsRoot.replaceChildren();
	  const fields = config.event.participant_fields || [];
	  const options = (config.event.options || []).filter((option) => option.scope === 'TICKET');
	  if (!fields.length && !options.length) return;
	  const allRequired = config.event.participant_extra_scope === 'ALL';
	  const optionalRows = document.createElement('div');
	  optionalRows.className = 'mi-registration__additional-participants';
	  optionalRows.hidden = !allRequired;
	  participantValues.forEach((participant, index) => {
		const row = document.createElement('fieldset');
		row.className = 'mi-registration__participant-detail';
		row.dataset.miParticipantKey = participant.key;
		const legend = document.createElement('legend');
		legend.textContent = `Partecipante ${index + 1}: ${participant.firstName} ${participant.lastName}`.trim();
		const grid = document.createElement('div');
		grid.className = 'mi-registration__grid';
		grid.append(identityDetailField('Nome', 'firstName', participant, index, allRequired || index === 0), identityDetailField('Cognome', 'lastName', participant, index, allRequired || index === 0));
		fields.forEach((field) => grid.append(configuredParticipantField(field, participant.fields?.[field.key] || '', index, allRequired || index === 0)));
		options.forEach((option) => grid.append(configuredParticipantOption(option, participant.options?.[option.code] || '0', index)));
		row.append(legend, grid);
		if (index === 0 || allRequired) participantDetailsRoot.append(row);
		else optionalRows.append(row);
	  });
	  if (!allRequired && participantValues.length > 1) {
		const toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'mi-registration__secondary-button';
		toggle.textContent = 'Aggiungi, se vuoi, anche i dati degli altri partecipanti';
		toggle.addEventListener('click', () => { optionalRows.hidden = false; toggle.hidden = true; optionalRows.querySelector('input, select, textarea')?.focus(); });
		participantDetailsRoot.append(toggle, optionalRows);
	  } else participantDetailsRoot.append(optionalRows);
	}

	function identityDetailField(labelText, property, participant, index, required) {
	  const label = document.createElement('label');
	  label.textContent = labelText;
	  const input = document.createElement('input');
	  input.required = required;
	  input.maxLength = 80;
	  input.value = participant[property] || '';
	  input.dataset.miDetailIdentity = property;
	  input.autocomplete = `section-participant-details-${index + 1} ${property === 'firstName' ? 'given-name' : 'family-name'}`;
	  input.addEventListener('input', () => {
		participant[property] = input.value;
		const sourceRow = Array.from(participantsRoot.querySelectorAll('.mi-registration__participant'))[index];
		const selector = property === 'firstName' ? '[data-mi-first-name]' : '[data-mi-last-name]';
		const sourceInput = sourceRow?.querySelector(selector);
		if (sourceInput) sourceInput.value = input.value;
		const legend = input.closest('fieldset')?.querySelector('legend');
		if (legend) legend.textContent = `Partecipante ${index + 1}: ${participant.firstName} ${participant.lastName}`.trim();
	  });
	  label.append(input);
	  return label;
	}

    function configuredParticipantField(field, value, index, required = false) {
      const label = document.createElement('label');
	  label.textContent = required && field.required ? `${field.label} *` : field.label;
      let input;
      if (field.type === 'select') {
        input = document.createElement('select');
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = 'Seleziona';
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
      label.append(input);
      if (field.help) {
        const help = document.createElement('small');
        help.className = 'mi-registration__field-help';
        help.textContent = field.help;
        label.append(help);
      }
      return label;
    }

    function configuredParticipantOption(option, value, index) {
      const label = document.createElement('label');
      label.textContent = `${option.name}${Number(option.price_cents) > 0 ? ` · ${formatCurrency(option.price_cents)}` : ''}`;
      const input = document.createElement('input');
      input.type = 'number';
      input.min = '0';
      input.max = String(option.max_quantity || 1);
      input.value = value;
      input.name = `participant-${index}-option-${option.code}`;
      input.dataset.miParticipantOption = option.code;
      input.addEventListener('input', () => { renderEconomicSummary(); updateStickySummary(); });
      label.append(input);
      return label;
    }

    function participantPayload() {
	  captureParticipants();
	  participantDetailsRoot.querySelectorAll('.mi-registration__participant-detail').forEach((row) => {
		const participant = participantValues.find((value) => value.key === row.dataset.miParticipantKey);
		if (!participant) return;
		const identityInputs = row.querySelectorAll('[data-mi-detail-identity]');
		identityInputs.forEach((input) => { participant[input.dataset.miDetailIdentity] = input.value.trim(); });
		participant.fields = Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.value.trim()]));
		participant.options = Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-option]')).map((input) => [input.dataset.miParticipantOption, core.clampQuantity(input.value, input.max)]));
	  });
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

	nextButton.addEventListener('click', () => {
	  if (!currentStepIsValid()) return;
	  if (currentStep === 1) renderParticipants();
	  if (currentStep === 2) {
		captureParticipants();
		renderParticipantDetails();
		prefillBuyerFromFirstParticipant();
		updateBuyerStepHeading();
	  }
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
      if (!form.reportValidity()) return;
      if (!core.isValidPhone(String(new FormData(form).get('buyerPhone') || ''))) {
        showError('Inserisci il cellulare con prefisso internazionale, per esempio +39.');
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
		const successText = result.status === 'WAITLISTED'
		  ? `Richiesta inserita in lista d’attesa. Codice: ${result.order_code}`
		  : result.status === 'PENDING_PAYMENT'
			? `Prenotazione registrata e in attesa di pagamento. Codice: ${result.order_code}. Importo da versare: ${formatCurrency(result.economic_summary.initial_due_cents)}`
			: `Iscrizione confermata. Codice: ${result.order_code}`;
        successBox.textContent = successText;
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
