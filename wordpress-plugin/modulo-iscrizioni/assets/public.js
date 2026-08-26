(function () {
  'use strict';

  document.querySelectorAll('.mi-registration[data-mi-config]').forEach((root) => {
    const core = globalThis.MIRegistrationCore;
    if (!core) throw new Error('Modulo iscrizioni: funzioni di base non disponibili.');
    const config = JSON.parse(root.dataset.miConfig || '{}');
    const form = root.querySelector('.mi-registration__form');
    if (!form) return;

    const participantsRoot = root.querySelector('[data-mi-participants]');
    const errorBox = root.querySelector('[data-mi-error]');
    const successBox = root.querySelector('[data-mi-success]');
    const submitButton = root.querySelector('.mi-registration__submit');
    const economicSummary = root.querySelector('[data-mi-economic-summary]');
	const steps = Array.from(root.querySelectorAll('[data-mi-step]'));
	const nextButton = root.querySelector('[data-mi-next]');
	const backButton = root.querySelector('[data-mi-back]');
	const stickySummary = root.querySelector('[data-mi-sticky-summary]');
	let currentStep = 1;
    let requestKey = makeRequestKey();
    let participantValues = [];

    function makeRequestKey() {
      if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
        return globalThis.crypto.randomUUID().replaceAll('-', '');
      }
      return `${Date.now()}_${Math.random().toString(36).slice(2)}_${Math.random().toString(36).slice(2)}`;
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
      const prices = Object.fromEntries((config.event.ticket_types || []).map((ticket) => [ticket.code, Number(ticket.price_cents) || 0]));
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
        const initial = Math.round(total * Number(config.event.deposit_percentage || 30) / 100);
        economicSummary.querySelector('[data-mi-initial-label]').textContent = `Caparra (${config.event.deposit_percentage}%):`;
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
	  stickySummary.textContent = quantity ? `${quantityLabel}${config.event.pricing_mode === 'CALCULATED' ? ` · ${formatCurrency(totalCents())}` : ''}` : 'Nessuna iscrizione';
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
      participantValues = Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).map((row) => ({
        key: `${row.dataset.miTicketType}:${row.dataset.miTicketIndex}`,
        firstName: row.querySelector('[data-mi-first-name]')?.value || '',
        lastName: row.querySelector('[data-mi-last-name]')?.value || '',
        fields: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.value])),
        options: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-option]')).map((input) => [input.dataset.miParticipantOption, input.value]))
      }));
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
		(config.event.participant_fields || []).forEach((field) => {
		  grid.append(configuredParticipantField(field, previous.fields?.[field.key] || '', index));
		});
        (config.event.options || []).filter((option) => option.scope === 'TICKET').forEach((option) => {
          grid.append(configuredParticipantOption(option, previous.options?.[option.code] || '0', index));
        });
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

    function configuredParticipantField(field, value, index) {
      const label = document.createElement('label');
      label.textContent = field.required ? `${field.label} *` : field.label;
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
        input.type = field.type === 'date' ? 'date' : 'text';
      }
      input.name = `participant-${index}-field-${field.key}`;
      input.required = Boolean(field.required);
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
      return Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).map((row) => ({
        ticket_type_code: row.dataset.miTicketType,
        ticket_index: Number(row.dataset.miTicketIndex),
        first_name: row.querySelector('[data-mi-first-name]').value.trim(),
        last_name: row.querySelector('[data-mi-last-name]').value.trim(),
        fields: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.value.trim()])),
        options: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-option]')).map((input) => [input.dataset.miParticipantOption, core.clampQuantity(input.value, input.max)]))
      }));
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
        started_at: config.startedAt,
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
        }
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
          : `Iscrizione registrata. Codice: ${result.order_code}${result.economic_summary?.initial_due_cents > 0 ? `. Primo versamento previsto: ${formatCurrency(result.economic_summary.initial_due_cents)}` : ''}`;
        successBox.textContent = successText;
        if (config.event.identifier_display === 'QR' && typeof window.qrcode === 'function') {
          const qr = window.qrcode(0, 'M');
          qr.addData(`modulo-iscrizioni|evento:${config.event.id}|ordine:${result.order_code}`);
          qr.make();
          const qrBox = document.createElement('div');
          qrBox.className = 'mi-registration__qr';
          qrBox.setAttribute('aria-label', 'Codice QR dell’iscrizione');
          qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 4 });
          successBox.appendChild(qrBox);
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
