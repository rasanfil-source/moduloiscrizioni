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

    function captureParticipants() {
      participantValues = Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).map((row) => ({
        firstName: row.querySelector('[data-mi-first-name]')?.value || '',
        lastName: row.querySelector('[data-mi-last-name]')?.value || '',
        fields: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.value]))
      }));
    }

    function renderParticipants() {
      captureParticipants();
      const quantity = totalQuantity();
      participantsRoot.replaceChildren();
      if (!quantity) {
        const hint = document.createElement('p');
        hint.className = 'mi-registration__hint';
        hint.textContent = 'Seleziona almeno una iscrizione.';
        participantsRoot.append(hint);
        return;
      }
      for (let index = 0; index < quantity; index += 1) {
        const row = document.createElement('fieldset');
        row.className = 'mi-registration__participant';
        const legend = document.createElement('legend');
        legend.textContent = `Partecipante ${index + 1}`;
        const grid = document.createElement('div');
        grid.className = 'mi-registration__grid';
        grid.append(
          participantField('Nome', 'given-name', 'firstName', participantValues[index]?.firstName || '', index),
          participantField('Cognome', 'family-name', 'lastName', participantValues[index]?.lastName || '', index)
        );
		(config.event.participant_fields || []).forEach((field) => {
		  grid.append(configuredParticipantField(field, participantValues[index]?.fields?.[field.key] || '', index));
		});
        row.append(legend, grid);
        participantsRoot.append(row);
      }
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

    function participantPayload() {
      return Array.from(participantsRoot.querySelectorAll('.mi-registration__participant')).map((row) => ({
        first_name: row.querySelector('[data-mi-first-name]').value.trim(),
        last_name: row.querySelector('[data-mi-last-name]').value.trim(),
        fields: Object.fromEntries(Array.from(row.querySelectorAll('[data-mi-participant-field]')).map((input) => [input.dataset.miParticipantField, input.value.trim()]))
      }));
    }

    function showError(message) {
      errorBox.textContent = message;
      errorBox.hidden = false;
      errorBox.focus?.();
    }

    root.querySelectorAll('[data-mi-ticket]').forEach((input) => {
      input.addEventListener('input', renderParticipants);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      errorBox.hidden = true;
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
        tickets: ticketSelection(),
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
        successBox.textContent = result.status === 'WAITLISTED'
          ? `Richiesta inserita in lista d’attesa. Codice: ${result.order_code}`
          : `Iscrizione registrata. Codice: ${result.order_code}`;
        successBox.focus();
      } catch (error) {
        showError(error.message || 'Invio non riuscito. Riprova.');
        submitButton.disabled = false;
        submitButton.textContent = 'Invia iscrizione';
      }
    });

    renderParticipants();
  });
}());
