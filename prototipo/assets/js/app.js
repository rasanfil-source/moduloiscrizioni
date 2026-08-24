(function () {
  'use strict';

  // Applicazione statica: nessuna richiesta di rete e nessuna persistenza di PII.

  const config = window.SE_BOOKING_DEMO_CONFIG;
  if (!config) {
    throw new Error('Configurazione demo non disponibile.');
  }

  const bookingRoots = Array.from(document.querySelectorAll('.se-booking'));
  const mobileInstanceStates = new Map();
  const mobileInstanceCallbacks = new Map();

  function refreshActiveMobileInstance() {
    const activeRoot = Array.from(mobileInstanceStates.entries())
      .filter(([, status]) => status.isIntersecting)
      .sort((left, right) => right[1].ratio - left[1].ratio || left[1].distance - right[1].distance)[0]?.[0] || null;
    mobileInstanceCallbacks.forEach((callback, candidateRoot) => callback(candidateRoot === activeRoot));
  }

  bookingRoots.forEach((root, index) => initializeBooking(root, index));

  function initializeBooking(root, instanceIndex) {
    const instanceId = `se-booking-${instanceIndex + 1}`;
    root.id = instanceId;

    const idMap = new Map();
    root.querySelectorAll('[id]').forEach((element) => {
      const oldId = element.id;
      const newId = `${instanceId}-${oldId}`;
      idMap.set(oldId, newId);
      element.id = newId;
    });

    root.querySelectorAll('[for], [aria-labelledby], [aria-describedby], [aria-controls], [aria-owns], a[href^="#"]').forEach((element) => {
      ['for', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns'].forEach((attribute) => {
        if (!element.hasAttribute(attribute)) return;
        const mapped = element.getAttribute(attribute)
          .split(/\s+/)
          .map((id) => idMap.get(id) || id)
          .join(' ');
        element.setAttribute(attribute, mapped);
      });
      const href = element.getAttribute('href');
      if (href?.startsWith('#')) {
        const oldTarget = href.slice(1);
        if (idMap.has(oldTarget)) element.setAttribute('href', `#${idMap.get(oldTarget)}`);
      }
    });

    const idFor = (id) => idMap.get(id) || `${instanceId}-${id}`;
    const byId = (id) => root.querySelector(`#${idFor(id)}`);
    const form = byId('bookingForm');
    const bookingLayout = byId('bookingLayout');
    const orderSummary = byId('orderSummary');
    const summaryPanel = byId('summaryPanel');
    const summaryLines = byId('summaryLines');
    const summaryTotal = byId('summaryTotal');
    const summaryDue = byId('summaryDue');
    const mobileBar = byId('mobileBar');
    const mobileTotal = byId('mobileTotal');
    const mobileNextButton = byId('mobileNextButton');
    const mobileSummaryToggle = byId('mobileSummaryToggle');
    const mobileSummaryLabel = mobileSummaryToggle.querySelector('.se-booking__mobile-total-label');
    const liveRegion = byId('bookingLiveRegion');
    const errorSummary = byId('errorSummary');
    const errorSummaryList = byId('errorSummaryList');
    const ticketList = byId('ticketList');
    const ticketGroupError = byId('ticketGroupError');
    const participantList = byId('participantList');
    const optionGroups = byId('optionGroups');
    const reviewGrid = byId('reviewGrid');
    const confirmationDetails = byId('confirmationDetails');
    const submitBookingButton = byId('submitBooking');
    const mobileMedia = window.matchMedia('(max-width: 760px)');

    const activity = config.activities.find((item) => item.id === config.event.activityId);
    if (!activity) {
      throw new Error('Attività demo non trovata.');
    }

    const stepNames = ['Evento', 'Biglietti', 'Referente', 'Partecipanti', 'Riepilogo', 'Conferma'];
    const scenario = config.demoScenario || { flow: 'deposit', fields: 'minimal' };
    const hasPricing = scenario.pricingMode !== 'NONE';
    const hasManagedPayment = scenario.collectionMode === 'TRACKED_MANUAL';
    const state = {
    currentStep: 1,
    ticketQuantities: Object.fromEntries(
      config.event.tickets.map((ticket) => [ticket.id, ticket.initialQuantity || 0])
    ),
    participantSlots: [],
    participants: new Map(),
    orderOptions: new Set(),
    ticketOptions: new Map(),
      isSubmitting: false,
      isRootVisible: true
    };

    const moneyFormatter = new Intl.NumberFormat(config.locale, {
      style: 'currency',
      currency: config.currency
    });

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function formatMoney(cents) {
    return moneyFormatter.format(cents / 100);
  }

  function getPhoneDigits(value) {
    return String(value).trim().slice(1).replace(/[^0-9]/g, '');
  }

  function isValidPhone(value) {
    const trimmed = String(value).trim();
    if (!/^\+[1-9][0-9\s().-]*$/.test(trimmed)) return false;
    const digits = getPhoneDigits(trimmed);
    return digits.length >= 7 && digits.length <= 15;
  }

  function canonicalizePhone(value) {
    const trimmed = String(value).trim();
    return isValidPhone(trimmed) ? `+${getPhoneDigits(trimmed)}` : trimmed;
  }

  function announce(message) {
    liveRegion.textContent = '';
    window.setTimeout(() => {
      liveRegion.textContent = message;
    }, 20);
  }

  function setStaticContent() {
    const event = config.event;
    byId('organizerActivity').textContent = activity.name;
    byId('organizerParish').textContent = config.parish.name;
    byId('step1Title').textContent = event.title;
    byId('eventSubtitle').textContent = event.subtitle;
    byId('eventDates').textContent = `${event.startDateLabel} – ${event.endDateLabel}`;
    byId('eventLocation').textContent = event.location;
    byId('eventDescription').textContent = event.shortDescription;
    byId('eventNotice').textContent = event.preliminaryNotice;
    byId('eventAvailability').lastChild.textContent = ` ${event.availabilityLabel}`;

    const hero = byId('eventHero');
    hero.src = event.heroImage.src;
    hero.alt = event.heroImage.alt;

    byId('confirmationBookingStatus').textContent = event.registrationStatus;
    byId('confirmationPaymentStatus').textContent = hasManagedPayment
      ? event.paymentStatus
      : (hasPricing ? 'Incasso non gestito dal sistema' : 'Non richiesto');
    byId('confirmationCode').textContent = event.bookingCode;
    root.dataset.demoFlow = scenario.flow;
    root.dataset.demoFields = scenario.fields;
    root.querySelectorAll('[data-economic-only]').forEach((element) => {
      element.hidden = !hasPricing;
    });
    root.querySelectorAll('[data-payment-only]').forEach((element) => {
      element.hidden = !hasManagedPayment;
    });
    root.querySelectorAll('[data-extended-only]').forEach((element) => {
      element.hidden = scenario.fields !== 'extended';
    });
    if (!hasManagedPayment) {
      byId('confirmationLead').textContent = hasPricing
        ? 'La prenotazione è registrata; il prezzo è informativo e l’incasso non è gestito dal sistema.'
        : 'La prenotazione è registrata senza prezzo né pagamento. Conserva il codice per ogni comunicazione.';
    }
  }

  function resolveBrand(layers) {
    if (layers.event && layers.event.logo) {
      return { ...layers.event, source: 'event', sourceLabel: 'Logo specifico dell\'evento' };
    }
    if (layers.activity && layers.activity.logo) {
      return { ...layers.activity, source: 'activity', sourceLabel: 'Logo ereditato dall\'attività' };
    }
    return { ...layers.parish, source: 'parish', sourceLabel: 'Logo di fallback della parrocchia' };
  }

  function getBrandForDemoMode(mode) {
    const layers = {
      parish: {
        name: config.parish.name,
        logo: config.parish.logo,
        primaryColor: config.parish.primaryColor,
        primaryDarkColor: config.parish.primaryDarkColor
      },
      activity: mode === 'parish' ? null : {
        name: activity.name,
        logo: activity.logo,
        primaryColor: activity.primaryColor,
        primaryDarkColor: activity.primaryDarkColor
      },
      event: mode === 'event' ? {
        name: config.event.title,
        logo: config.event.logoOverride,
        primaryColor: config.event.primaryColor,
        primaryDarkColor: config.event.primaryDarkColor
      } : null
    };
    return resolveBrand(layers);
  }

  function applyBrandMode(mode) {
    const safeMode = Object.hasOwn(config.demoBrandModes, mode) ? mode : 'activity';
    const resolved = getBrandForDemoMode(safeMode);
    const logo = byId('brandLogo');

    logo.src = resolved.logo.src;
    logo.alt = resolved.logo.alt;
    byId('brandName').textContent = resolved.name;
    byId('brandSource').textContent = resolved.sourceLabel;
    root.style.setProperty('--se-color-primary', resolved.primaryColor);
    root.style.setProperty('--se-color-primary-dark', resolved.primaryDarkColor);

    const selector = document.getElementById('demoBrandMode');
    if (selector) selector.value = safeMode;
    const description = document.getElementById('demoBrandDescription');
    if (description) description.textContent = config.demoBrandModes[safeMode].description;
    announce(`Scenario logo aggiornato: ${config.demoBrandModes[safeMode].label}.`);
  }

  function renderTickets() {
    ticketList.replaceChildren();

    config.event.tickets.forEach((ticket) => {
      const ticketInputId = `${instanceId}-ticket-${ticket.id}`;
      const card = document.createElement('article');
      card.className = 'se-booking__ticket-card';
      card.innerHTML = `
        <div>
          <span class="se-booking__ticket-name">${escapeHtml(ticket.name)}</span>
          <span class="se-booking__ticket-description">${escapeHtml(ticket.description)}</span>
          <span class="se-booking__ticket-availability">${escapeHtml(ticket.availabilityLabel)}</span>
          <span class="se-booking__ticket-price">${hasPricing ? escapeHtml(formatMoney(ticket.priceCents)) : 'Iscrizione'}</span>
        </div>
        <div class="se-booking__quantity">
          <button
            class="se-booking__quantity-button"
            type="button"
            data-ticket-adjust="-1"
            data-ticket-id="${escapeHtml(ticket.id)}"
            aria-label="Riduci ${escapeHtml(ticket.name)}"
          >−</button>
          <label class="se-booking__sr-only" for="${escapeHtml(ticketInputId)}">Quantità ${escapeHtml(ticket.name)}</label>
          <input
            id="${escapeHtml(ticketInputId)}"
            class="se-booking__quantity-input"
            type="number"
            min="0"
            max="${ticket.maxPerOrder}"
            step="1"
            inputmode="numeric"
            value="${state.ticketQuantities[ticket.id]}"
            data-ticket-quantity="${escapeHtml(ticket.id)}"
            aria-describedby="${escapeHtml(idFor('ticketGroupError'))}"
          >
          <button
            class="se-booking__quantity-button"
            type="button"
            data-ticket-adjust="1"
            data-ticket-id="${escapeHtml(ticket.id)}"
            aria-label="Aumenta ${escapeHtml(ticket.name)}"
          >+</button>
        </div>
      `;
      ticketList.append(card);
    });
  }

  function getTicketCount() {
    return Object.values(state.ticketQuantities).reduce((sum, quantity) => sum + quantity, 0);
  }

  function clampTicketQuantity(ticket, proposed) {
    const ownMaximum = Math.min(ticket.maxPerOrder, config.event.maxTicketsPerOrder);
    const otherTickets = getTicketCount() - state.ticketQuantities[ticket.id];
    const availableWithinOrder = Math.max(0, config.event.maxTicketsPerOrder - otherTickets);
    return Math.max(0, Math.min(Math.trunc(proposed || 0), ownMaximum, availableWithinOrder));
  }

  function setTicketQuantity(ticketId, proposed, shouldAnnounce) {
    const ticket = config.event.tickets.find((item) => item.id === ticketId);
    if (!ticket) return;

    const normalized = clampTicketQuantity(ticket, Number(proposed));
    state.ticketQuantities[ticketId] = normalized;

    const input = root.querySelector(`[data-ticket-quantity="${ticketId}"]`);
    if (input) input.value = String(normalized);

    syncParticipantSlots();
    clearTicketGroupError();
    refreshErrorSummary();
    renderSummary(shouldAnnounce);
  }

  function getActiveSlots() {
    const slots = [];
    config.event.tickets.forEach((ticket) => {
      const quantity = state.ticketQuantities[ticket.id];
      for (let index = 1; index <= quantity; index += 1) {
        slots.push({
          key: `${ticket.id}-${index}`,
          ticketId: ticket.id,
          ticketName: ticket.name,
          ticketPriceCents: ticket.priceCents
        });
      }
    });
    return slots;
  }

  function captureParticipantData() {
    participantList.querySelectorAll('[data-participant-key]').forEach((container) => {
      const key = container.dataset.participantKey;
      const existing = state.participants.get(key) || {};
      state.participants.set(key, {
        firstName: container.querySelector('[data-participant-field="firstName"]')?.value.trim() || '',
        lastName: container.querySelector('[data-participant-field="lastName"]')?.value.trim() || '',
        email: container.querySelector('[data-participant-field="email"]')?.value.trim() || '',
        phone: canonicalizePhone(container.querySelector('[data-participant-field="phone"]')?.value || ''),
        shirtSize: container.querySelector('[data-participant-field="shirtSize"]')?.value || '',
        jerseyNumber: container.querySelector('[data-participant-field="jerseyNumber"]')?.value || '',
        prefilled: Boolean(existing.prefilled)
      });
    });
  }

  function syncParticipantSlots() {
    captureParticipantData();
    const slots = getActiveSlots();
    const activeKeys = new Set(slots.map((slot) => slot.key));

    Array.from(state.participants.keys()).forEach((key) => {
      if (!activeKeys.has(key)) state.participants.delete(key);
    });
    Array.from(state.ticketOptions.keys()).forEach((key) => {
      if (!activeKeys.has(key)) state.ticketOptions.delete(key);
    });

    slots.forEach((slot) => {
      if (!state.participants.has(slot.key)) {
        state.participants.set(slot.key, {
          firstName: '',
          lastName: '',
          email: '',
          phone: '',
          shirtSize: '',
          jerseyNumber: '',
          prefilled: false
        });
      }
      if (!state.ticketOptions.has(slot.key)) {
        state.ticketOptions.set(slot.key, new Set());
      }
    });

    state.participantSlots = slots;
    applyBuyerPrefill();
  }

  function getBuyerData() {
    return {
      firstName: byId('buyerFirstName').value.trim(),
      lastName: byId('buyerLastName').value.trim(),
      email: byId('buyerEmail').value.trim(),
      phone: canonicalizePhone(byId('buyerPhone').value)
    };
  }

  function applyBuyerPrefill() {
    const firstSlot = state.participantSlots[0];
    if (!firstSlot) return;

    const participant = state.participants.get(firstSlot.key);
    const buyerParticipates = byId('buyerParticipates').checked;
    const isEmpty = !participant.firstName && !participant.lastName && !participant.email && !participant.phone;

    if (buyerParticipates && (participant.prefilled || isEmpty)) {
      state.participants.set(firstSlot.key, { ...getBuyerData(), prefilled: true });
    } else if (!buyerParticipates && participant.prefilled) {
      state.participants.set(firstSlot.key, {
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        prefilled: false
      });
    }
  }

  function renderParticipants() {
    syncParticipantSlots();
    participantList.replaceChildren();
    const openAll = window.matchMedia('(min-width: 761px)').matches;

    state.participantSlots.forEach((slot, index) => {
      const participant = state.participants.get(slot.key);
      const safeKey = slot.key.replace(/[^a-z0-9-]/gi, '-');
      const inputBaseId = `${instanceId}-participant-${safeKey}`;
      const details = document.createElement('details');
      details.className = 'se-booking__participant';
      details.dataset.participantKey = slot.key;
      details.open = openAll || index === 0;
      details.innerHTML = `
        <summary class="se-booking__participant-summary">
          <span>Biglietto ${index + 1}</span>
          <span class="se-booking__participant-type">${escapeHtml(slot.ticketName)}</span>
        </summary>
        <div class="se-booking__participant-body">
          <div class="se-booking__form-grid">
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-firstName">Nome <span class="se-booking__required-note">(obbligatorio)</span></label>
              <input
                id="${inputBaseId}-firstName"
                class="se-booking__input"
                type="text"
                name="participant-${safeKey}-firstName"
                value="${escapeHtml(participant.firstName)}"
                maxlength="80"
                autocomplete="section-ticket-${index + 1} given-name"
                data-participant-field="firstName"
                required
              >
              <p id="${inputBaseId}-firstNameError" class="se-booking__field-error" data-visible="false"></p>
            </div>
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-lastName">Cognome <span class="se-booking__required-note">(obbligatorio)</span></label>
              <input
                id="${inputBaseId}-lastName"
                class="se-booking__input"
                type="text"
                name="participant-${safeKey}-lastName"
                value="${escapeHtml(participant.lastName)}"
                maxlength="80"
                autocomplete="section-ticket-${index + 1} family-name"
                data-participant-field="lastName"
                required
              >
              <p id="${inputBaseId}-lastNameError" class="se-booking__field-error" data-visible="false"></p>
            </div>
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-email">Email <span class="se-booking__required-note">(facoltativa)</span></label>
              <input
                id="${inputBaseId}-email"
                class="se-booking__input"
                type="email"
                name="participant-${safeKey}-email"
                value="${escapeHtml(participant.email)}"
                maxlength="160"
                inputmode="email"
                autocomplete="section-ticket-${index + 1} email"
                aria-describedby="${inputBaseId}-contactHelp"
                data-participant-field="email"
              >
              <p id="${inputBaseId}-emailError" class="se-booking__field-error" data-visible="false"></p>
            </div>
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-phone">Cellulare <span class="se-booking__required-note">(facoltativo)</span></label>
              <input
                id="${inputBaseId}-phone"
                class="se-booking__input"
                type="tel"
                name="participant-${safeKey}-phone"
                value="${escapeHtml(participant.phone)}"
                maxlength="30"
                inputmode="tel"
                autocomplete="section-ticket-${index + 1} tel"
                aria-describedby="${inputBaseId}-contactHelp"
                data-participant-field="phone"
              >
              <p id="${inputBaseId}-phoneError" class="se-booking__field-error" data-visible="false"></p>
            </div>
            <p id="${inputBaseId}-contactHelp" class="se-booking__field-help se-booking__field--wide">
              Aggiungi email e cellulare se desideri che il partecipante possa essere contattato direttamente. Se li lasci vuoti, tutte le comunicazioni saranno inviate al referente.
            </p>
            ${scenario.fields === 'extended' ? `
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-shirtSize">Taglia maglietta <span class="se-booking__required-note">(profilo esteso)</span></label>
              <select id="${inputBaseId}-shirtSize" class="se-booking__input" name="participant-${safeKey}-shirtSize" data-participant-field="shirtSize">
                <option value="">Seleziona</option>
                ${['S', 'M', 'L', 'XL'].map((size) => `<option value="${size}"${participant.shirtSize === size ? ' selected' : ''}>${size}</option>`).join('')}
              </select>
            </div>
            <div class="se-booking__field">
              <label class="se-booking__label" for="${inputBaseId}-jerseyNumber">Numero maglia <span class="se-booking__required-note">(facoltativo)</span></label>
              <input id="${inputBaseId}-jerseyNumber" class="se-booking__input" type="number" min="0" max="99" name="participant-${safeKey}-jerseyNumber" value="${escapeHtml(participant.jerseyNumber)}" data-participant-field="jerseyNumber">
            </div>` : ''}
          </div>
        </div>
      `;
      participantList.append(details);
    });
  }

  function buildCheckCard({ id, name, description, priceCents, checked, dataset }) {
    const wrapper = document.createElement('div');
    wrapper.className = 'se-booking__option-card';

    const label = document.createElement('label');
    label.className = 'se-booking__check-row';
    label.htmlFor = id;

    const input = document.createElement('input');
    input.id = id;
    input.type = 'checkbox';
    input.checked = checked;
    Object.entries(dataset).forEach(([key, value]) => {
      input.dataset[key] = value;
    });

    const copy = document.createElement('span');
    copy.className = 'se-booking__check-copy';

    const title = document.createElement('span');
    title.className = 'se-booking__check-title';
    title.textContent = name;

    const descriptionElement = document.createElement('span');
    descriptionElement.className = 'se-booking__option-description';
    descriptionElement.textContent = description;

    const price = document.createElement('span');
    price.className = 'se-booking__option-price';
    price.textContent = hasPricing ? `+ ${formatMoney(priceCents)}` : 'Inclusa';

    copy.append(title, descriptionElement, price);
    label.append(input, copy);
    wrapper.append(label);
    return wrapper;
  }

  function renderOptions() {
    captureParticipantData();
    optionGroups.replaceChildren();

    const orderFieldset = document.createElement('fieldset');
    orderFieldset.className = 'se-booking__fieldset';
    const orderLegend = document.createElement('legend');
    orderLegend.className = 'se-booking__fieldset-legend';
    orderLegend.textContent = 'Opzioni per l\'intero ordine';
    orderFieldset.append(orderLegend);

    config.event.orderOptions.forEach((option) => {
      orderFieldset.append(buildCheckCard({
        id: `${instanceId}-order-option-${option.id}`,
        name: option.name,
        description: option.description,
        priceCents: option.priceCents,
        checked: state.orderOptions.has(option.id),
        dataset: { orderOptionId: option.id }
      }));
    });

    const ticketFieldset = document.createElement('fieldset');
    ticketFieldset.className = 'se-booking__fieldset';
    const ticketLegend = document.createElement('legend');
    ticketLegend.className = 'se-booking__fieldset-legend';
    ticketLegend.textContent = 'Opzioni per ogni biglietto';
    ticketFieldset.append(ticketLegend);

    state.participantSlots.forEach((slot, index) => {
      const participant = state.participants.get(slot.key);
      const section = document.createElement('section');
      section.className = 'se-booking__ticket-option-group';

      const heading = document.createElement('h3');
      heading.className = 'se-booking__card-title';
      const participantName = [participant.firstName, participant.lastName].filter(Boolean).join(' ');
      heading.textContent = `Biglietto ${index + 1}${participantName ? ` · ${participantName}` : ''}`;
      section.append(heading);

      const options = document.createElement('div');
      options.className = 'se-booking__participant-options';
      const selectedOptions = state.ticketOptions.get(slot.key) || new Set();

      config.event.ticketOptions.forEach((option) => {
        options.append(buildCheckCard({
          id: `${instanceId}-ticket-option-${slot.key}-${option.id}`,
          name: option.name,
          description: option.description,
          priceCents: option.priceCents,
          checked: selectedOptions.has(option.id),
          dataset: { ticketOptionId: option.id, participantKey: slot.key }
        }));
      });

      section.append(options);
      ticketFieldset.append(section);
    });

    optionGroups.append(orderFieldset, ticketFieldset);
    renderReview();
  }

  function makeReviewCard(title, lines, editStep) {
    const card = document.createElement('article');
    card.className = 'se-booking__review-card';

    const head = document.createElement('div');
    head.className = 'se-booking__review-head';
    const heading = document.createElement('h3');
    heading.className = 'se-booking__card-title';
    heading.textContent = title;
    head.append(heading);

    if (editStep) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'se-booking__text-button';
      button.dataset.editStep = String(editStep);
      button.textContent = 'Modifica';
      button.setAttribute('aria-label', `Modifica ${title.toLowerCase()}`);
      head.append(button);
    }

    const body = document.createElement('div');
    body.className = 'se-booking__review-body';
    lines.forEach((line) => {
      const paragraph = document.createElement('p');
      paragraph.textContent = line;
      body.append(paragraph);
    });

    card.append(head, body);
    return card;
  }

  function renderReview() {
    reviewGrid.replaceChildren();
    const ticketLines = config.event.tickets
      .filter((ticket) => state.ticketQuantities[ticket.id] > 0)
      .map((ticket) => `${state.ticketQuantities[ticket.id]} × ${ticket.name}`);
    const buyer = getBuyerData();
    const participantLines = state.participantSlots.map((slot, index) => {
      const participant = state.participants.get(slot.key);
      return `Biglietto ${index + 1}: ${participant.firstName} ${participant.lastName}`.trim();
    });

    reviewGrid.append(
      makeReviewCard('Biglietti', ticketLines, 2),
      makeReviewCard('Referente', [
        `${buyer.firstName} ${buyer.lastName}`.trim(),
        buyer.email,
        buyer.phone
      ], 3),
      makeReviewCard('Partecipanti', participantLines, 4)
    );
    reviewGrid.lastElementChild.classList.add('se-booking__review-card--wide');
  }

  function calculateTotals() {
    const lineItems = [];
    const activeSlots = getActiveSlots();
    let totalCents = 0;

    config.event.tickets.forEach((ticket) => {
      const quantity = state.ticketQuantities[ticket.id];
      if (quantity > 0) {
        const amount = ticket.priceCents * quantity;
        totalCents += amount;
        lineItems.push({ label: `${ticket.name} × ${quantity}`, amountCents: amount });
      }
    });

    config.event.orderOptions.forEach((option) => {
      if (state.orderOptions.has(option.id)) {
        totalCents += option.priceCents;
        lineItems.push({ label: option.name, amountCents: option.priceCents });
      }
    });

    config.event.ticketOptions.forEach((option) => {
      let count = 0;
      activeSlots.forEach((slot) => {
        if (state.ticketOptions.get(slot.key)?.has(option.id)) count += 1;
      });
      if (count > 0) {
        const amount = option.priceCents * count;
        totalCents += amount;
        lineItems.push({ label: `${option.name} × ${count}`, amountCents: amount });
      }
    });

    const ticketCount = getTicketCount();
    if (!hasPricing) totalCents = 0;
    const dueNowCents = hasManagedPayment
      ? Math.min(totalCents, config.event.depositPerTicketCents * ticketCount)
      : 0;
    return {
      lineItems,
      totalCents,
      dueNowCents,
      balanceCents: Math.max(0, totalCents - dueNowCents),
      ticketCount
    };
  }

  function renderSummary(shouldAnnounce) {
    const totals = calculateTotals();
    summaryLines.replaceChildren();

    if (totals.lineItems.length === 0) {
      const emptyItem = document.createElement('li');
      emptyItem.className = 'se-booking__summary-line';
      emptyItem.textContent = 'Nessun biglietto selezionato';
      summaryLines.append(emptyItem);
    } else {
      totals.lineItems.forEach((line) => {
        const item = document.createElement('li');
        item.className = 'se-booking__summary-line';
        const label = document.createElement('span');
        label.textContent = line.label;
        const amount = document.createElement('span');
        amount.textContent = hasPricing ? formatMoney(line.amountCents) : '';
        item.append(label, amount);
        summaryLines.append(item);
      });
    }

    const totalLabel = hasPricing ? formatMoney(totals.totalCents) : 'Nessun prezzo';
    summaryTotal.textContent = totalLabel;
    summaryDue.textContent = formatMoney(totals.dueNowCents);
    mobileTotal.textContent = totalLabel;

    if (shouldAnnounce) announce(`Totale dimostrativo aggiornato: ${totalLabel}.`);
    return totals;
  }

  function clearTicketGroupError() {
    ticketGroupError.textContent = '';
    ticketGroupError.dataset.visible = 'false';
    ticketList.querySelectorAll('[data-ticket-quantity]').forEach((input) => {
      input.removeAttribute('aria-invalid');
    });
  }

  function validateTicketSelection() {
    clearTicketGroupError();
    const count = getTicketCount();
    let message = '';

    if (count < config.event.minTicketsPerOrder) {
      message = `Seleziona almeno ${config.event.minTicketsPerOrder} posto.`;
    } else if (count > config.event.maxTicketsPerOrder) {
      message = `Puoi selezionare al massimo ${config.event.maxTicketsPerOrder} posti.`;
    }

    if (!message) return true;

    ticketGroupError.textContent = message;
    ticketGroupError.dataset.visible = 'true';
    const firstInput = ticketList.querySelector('[data-ticket-quantity]');
    firstInput?.setAttribute('aria-invalid', 'true');
    showErrorSummary([{ target: firstInput, fieldName: 'Biglietti', message }]);
    return false;
  }

  function appendDescribedBy(input, id) {
    const ids = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    ids.add(id);
    input.setAttribute('aria-describedby', Array.from(ids).join(' '));
  }

  function removeDescribedBy(input, id) {
    const ids = (input.getAttribute('aria-describedby') || '')
      .split(/\s+/)
      .filter((item) => item && item !== id);
    if (ids.length) input.setAttribute('aria-describedby', ids.join(' '));
    else input.removeAttribute('aria-describedby');
  }

  function getErrorElement(input) {
    return root.querySelector(`#${input.id}Error`);
  }

  function clearInputError(input) {
    const error = getErrorElement(input);
    input.removeAttribute('aria-invalid');
    if (!error) return;
    error.textContent = '';
    error.dataset.visible = 'false';
    removeDescribedBy(input, error.id);
  }

  function setInputError(input, message) {
    const error = getErrorElement(input);
    input.setAttribute('aria-invalid', 'true');
    if (!error) return;
    error.textContent = message;
    error.dataset.visible = 'true';
    appendDescribedBy(input, error.id);
  }

  function getInputErrorMessage(input) {
    if (input.type === 'checkbox' && input.required && !input.checked) {
      return 'Questa accettazione è obbligatoria.';
    }
    if (input.required && !input.value.trim()) {
      return 'Compila questo campo.';
    }
    if (input.type === 'email' && input.value && !input.validity.valid) {
      return 'Inserisci un indirizzo email valido.';
    }
    if (input.type === 'tel' && input.value && !isValidPhone(input.value)) {
      return 'Usa + seguito da 7–15 cifre; sono ammessi spazi, punti, parentesi e trattini.';
    }
    return '';
  }

  function getFieldName(input) {
    const explicitLabel = root.querySelector(`label[for="${input.id}"]`);
    const wrappingLabel = input.closest('label');
    const label = explicitLabel || wrappingLabel;
    const preferredText = label?.querySelector('.se-booking__check-title')?.textContent || label?.textContent;
    const cleaned = String(preferredText || input.name || 'Campo')
      .replace(/\s*\((?:obbligatori[oa]|facoltativ[oa])\).*$/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    return cleaned || 'Campo';
  }

  function validateControls(container) {
    const errors = [];
    container.querySelectorAll('input, select, textarea').forEach((input) => {
      clearInputError(input);
      const message = getInputErrorMessage(input);
      if (message) {
        setInputError(input, message);
        errors.push({ target: input, fieldName: getFieldName(input), message });
      }
    });

    if (errors.length) {
      showErrorSummary(errors);
      return false;
    }
    clearErrorSummary();
    return true;
  }

  function showErrorSummary(errors, shouldFocus = true) {
    errorSummaryList.replaceChildren();
    errors.forEach((error) => {
      const item = document.createElement('li');
      const link = document.createElement('a');
      link.className = 'se-booking__error-summary-link';
      link.href = `#${error.target.id}`;
      link.textContent = `${error.fieldName || getFieldName(error.target)}: ${error.message}`;
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const collapsedParticipant = error.target.closest('details:not([open])');
        if (collapsedParticipant) collapsedParticipant.open = true;
        error.target.focus();
      });
      item.append(link);
      errorSummaryList.append(item);
    });
    errorSummary.hidden = false;
    if (shouldFocus) errorSummary.focus();
  }

  function refreshErrorSummary() {
    const visibleStep = getVisibleStep();
    if (!visibleStep) return;
    const errors = [];

    if (state.currentStep === 2 && ticketGroupError.dataset.visible === 'true') {
      const target = ticketList.querySelector('[data-ticket-quantity]');
      if (target) errors.push({ target, fieldName: 'Biglietti', message: ticketGroupError.textContent });
    } else {
      visibleStep.querySelectorAll('[aria-invalid="true"]').forEach((input) => {
        const message = getErrorElement(input)?.textContent;
        if (message) errors.push({ target: input, fieldName: getFieldName(input), message });
      });
    }

    if (errors.length) showErrorSummary(errors, false);
    else clearErrorSummary();
  }

  function clearErrorSummary() {
    errorSummary.hidden = true;
    errorSummaryList.replaceChildren();
  }

  function getVisibleStep() {
    return root.querySelector(`.se-booking__step[data-step="${state.currentStep}"]`);
  }

  function updateProgress() {
    const name = stepNames[state.currentStep - 1];
    byId('mobileProgressText').textContent = `Passaggio ${state.currentStep} di 6 — ${name}`;

    const progressTrack = root.querySelector('.se-booking__progress-track');
    progressTrack.setAttribute('aria-valuenow', String(state.currentStep));
    progressTrack.setAttribute('aria-valuetext', `Passaggio ${state.currentStep} di 6: ${name}`);
    root.querySelector('.se-booking__progress-value').style.width = `${(state.currentStep / 6) * 100}%`;

    root.querySelectorAll('[data-stepper-item]').forEach((item) => {
      const step = Number(item.dataset.stepperItem);
      item.removeAttribute('aria-current');
      item.dataset.complete = step < state.currentStep ? 'true' : 'false';
      if (step === state.currentStep) item.setAttribute('aria-current', 'step');
    });
  }

  function updateMobileAction() {
    const labels = {
      1: 'Iscriviti',
      2: 'Continua',
      3: 'Continua',
      4: 'Continua',
      5: 'Concludi la prenotazione'
    };
    mobileNextButton.textContent = labels[state.currentStep] || '';
    updateMobileBarVisibility();
  }

  function updateMobileBarVisibility() {
    const isActive = mobileMedia.matches && state.currentStep !== 6 && state.isRootVisible;
    mobileBar.hidden = !isActive;
    root.classList.toggle('se-booking--mobile-bar-active', isActive);
  }

  function syncMobileSummary(isOpen = orderSummary.dataset.mobileOpen === 'true') {
    if (!mobileMedia.matches) {
      orderSummary.dataset.mobileOpen = 'false';
      mobileSummaryToggle.setAttribute('aria-expanded', 'false');
      mobileSummaryLabel.textContent = 'Totale · apri riepilogo';
      orderSummary.hidden = state.currentStep === 6;
      orderSummary.removeAttribute('inert');
      orderSummary.removeAttribute('aria-hidden');
      summaryPanel.hidden = false;
      summaryPanel.removeAttribute('inert');
      summaryPanel.removeAttribute('aria-hidden');
      return;
    }

    const shouldOpen = Boolean(isOpen && state.currentStep !== 6);
    orderSummary.dataset.mobileOpen = String(shouldOpen);
    mobileSummaryToggle.setAttribute('aria-expanded', String(shouldOpen));
    mobileSummaryLabel.textContent = shouldOpen ? 'Totale · chiudi riepilogo' : 'Totale · apri riepilogo';
    orderSummary.hidden = !shouldOpen;
    orderSummary.toggleAttribute('inert', !shouldOpen);
    orderSummary.setAttribute('aria-hidden', String(!shouldOpen));
    summaryPanel.hidden = !shouldOpen;
    summaryPanel.toggleAttribute('inert', !shouldOpen);
    summaryPanel.setAttribute('aria-hidden', String(!shouldOpen));
  }

  function closeMobileSummary() {
    syncMobileSummary(false);
  }

  function setupModuleVisibility() {
    const rectangle = root.getBoundingClientRect();
    const visiblePixels = Math.max(0, Math.min(rectangle.bottom, window.innerHeight) - Math.max(rectangle.top, 0));
    mobileInstanceCallbacks.set(root, (isActive) => {
      state.isRootVisible = isActive;
      updateMobileBarVisibility();
    });
    mobileInstanceStates.set(root, {
      isIntersecting: visiblePixels > 0,
      ratio: rectangle.height > 0 ? visiblePixels / Math.min(rectangle.height, window.innerHeight) : 0,
      distance: Math.abs((rectangle.top + rectangle.bottom) / 2 - window.innerHeight / 2)
    });
    refreshActiveMobileInstance();

    if (!('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver(([entry]) => {
      mobileInstanceStates.set(root, {
        isIntersecting: entry.isIntersecting,
        ratio: entry.intersectionRatio,
        distance: Math.abs((entry.boundingClientRect.top + entry.boundingClientRect.bottom) / 2 - window.innerHeight / 2)
      });
      refreshActiveMobileInstance();
    }, { threshold: [0, 0.01, 0.25, 0.5, 0.75, 1] });
    observer.observe(root);
  }

  function showStep(step, focusHeading) {
    const nextStep = Math.max(1, Math.min(6, step));
    root.querySelectorAll('.se-booking__step').forEach((section) => {
      section.hidden = Number(section.dataset.step) !== nextStep;
    });

    state.currentStep = nextStep;
    root.dataset.currentStep = String(nextStep);
    bookingLayout.dataset.confirmation = nextStep === 6 ? 'true' : 'false';
    orderSummary.hidden = nextStep === 6;
    root.setAttribute('aria-busy', 'false');
    closeMobileSummary();
    clearErrorSummary();
    updateProgress();
    updateMobileAction();

    if (focusHeading) {
      const heading = getVisibleStep()?.querySelector('[data-step-title]');
      window.requestAnimationFrame(() => {
        heading?.focus({ preventScroll: true });
        root.scrollIntoView({
          behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
          block: 'start'
        });
      });
    }
  }

  function goToNextStep() {
    const visibleStep = getVisibleStep();
    if (!visibleStep) return;

    if (state.currentStep === 2 && !validateTicketSelection()) return;
    if (state.currentStep === 3 && !validateControls(visibleStep)) return;
    if (state.currentStep === 4) {
      captureParticipantData();
      if (!validateControls(visibleStep)) return;
      renderOptions();
    }
    if (state.currentStep === 3) renderParticipants();
    if (state.currentStep === 5) {
      form.requestSubmit();
      return;
    }

    showStep(state.currentStep + 1, true);
  }

  function goToPreviousStep() {
    if (state.currentStep === 4) captureParticipantData();
    if (state.currentStep > 1 && state.currentStep < 6) {
      showStep(state.currentStep - 1, true);
    }
  }

  function getSelectedOptionLines() {
    const lines = [];
    config.event.orderOptions.forEach((option) => {
      if (state.orderOptions.has(option.id)) lines.push(option.name);
    });
    state.participantSlots.forEach((slot, index) => {
      const selected = state.ticketOptions.get(slot.key) || new Set();
      config.event.ticketOptions.forEach((option) => {
        if (selected.has(option.id)) lines.push(`Biglietto ${index + 1}: ${option.name}`);
      });
    });
    return lines.length ? lines : ['Nessuna opzione aggiuntiva'];
  }

  function renderConfirmation() {
    const totals = renderSummary(false);
    byId('confirmationTotal').textContent = formatMoney(totals.totalCents);
    byId('confirmationDue').textContent = formatMoney(totals.dueNowCents);
    byId('confirmationBalance').textContent = formatMoney(totals.balanceCents);

    const participantLines = state.participantSlots.map((slot, index) => {
      const participant = state.participants.get(slot.key);
      return `Biglietto ${index + 1}: ${participant.firstName} ${participant.lastName}`;
    });
    confirmationDetails.replaceChildren(
      makeReviewCard('Partecipanti', participantLines, null),
      makeReviewCard('Opzioni selezionate', getSelectedOptionLines(), null)
    );
  }

  function setSubmitting(isSubmitting) {
    state.isSubmitting = isSubmitting;
    root.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
    submitBookingButton.disabled = isSubmitting;
    mobileNextButton.disabled = isSubmitting;

    if (isSubmitting) {
      submitBookingButton.replaceChildren();
      const spinner = document.createElement('span');
      spinner.className = 'se-booking__spinner';
      spinner.setAttribute('aria-hidden', 'true');
      submitBookingButton.append(spinner, ' Registrazione demo…');
      mobileNextButton.textContent = 'Registrazione demo…';
    } else {
      submitBookingButton.textContent = 'Concludi la prenotazione';
      updateMobileAction();
    }
  }

  function submitDemo(event) {
    event.preventDefault();
    if (state.isSubmitting || state.currentStep !== 5) return;
    if (!validateControls(getVisibleStep())) return;

    setSubmitting(true);
    announce('Registrazione dimostrativa in corso.');

    window.setTimeout(() => {
      renderConfirmation();
      setSubmitting(false);
      showStep(6, true);
      announce('Prenotazione demo registrata. Il pagamento è in attesa di verifica.');
    }, 700);
  }

  function resetDemo() {
    form.reset();
    state.ticketQuantities = Object.fromEntries(
      config.event.tickets.map((ticket) => [ticket.id, ticket.initialQuantity || 0])
    );
    state.participantSlots = [];
    state.participants.clear();
    state.orderOptions.clear();
    state.ticketOptions.clear();
    participantList.replaceChildren();
    optionGroups.replaceChildren();
    reviewGrid.replaceChildren();
    confirmationDetails.replaceChildren();
    byId('paymentDemoMessage').hidden = true;
    renderTickets();
    renderSummary(false);
    showStep(1, true);
    announce('Demo reimpostata.');
  }

  async function copyBookingCode() {
    const code = config.event.bookingCode;
    const button = byId('copyBookingCode');
    try {
      if (!navigator.clipboard) throw new Error('Clipboard API non disponibile');
      await navigator.clipboard.writeText(code);
      button.textContent = 'Codice copiato';
      announce('Codice prenotazione copiato.');
      window.setTimeout(() => {
        button.textContent = 'Copia codice';
      }, 1800);
    } catch (_error) {
      announce(`Copia non disponibile in questo contesto. Il codice è ${code}.`);
      byId('confirmationCode').closest('.se-booking__booking-code')?.scrollIntoView({ block: 'nearest' });
    }
  }

  function showPaymentDemo(type) {
    const message = byId('paymentDemoMessage');
    if (type === 'card') {
      message.textContent = `Demo: in produzione si aprirebbe una pagina esterna in una nuova scheda. Il pagamento resta “${config.event.paymentStatus}” finché non viene verificato.`;
    } else if (type === 'cash') {
      message.textContent = `Demo: un operatore registrerebbe importo, data e fonte “Contanti” nel foglio protetto. Il pagamento resta “${config.event.paymentStatus}” finché la registrazione non viene validata.`;
    } else {
      message.textContent = 'Demo: le coordinate bancarie verrebbero caricate dalla configurazione protetta dell\'evento. Qui non sono presenti dati bancari reali.';
    }
    message.hidden = false;
    message.focus();
    announce(message.textContent);
  }

  function bindEvents() {
    document.getElementById('demoBrandMode')?.addEventListener('change', (event) => {
      applyBrandMode(event.target.value);
    });

    root.addEventListener('click', (event) => {
      const adjustButton = event.target.closest('[data-ticket-adjust]');
      if (adjustButton) {
        const ticketId = adjustButton.dataset.ticketId;
        const delta = Number(adjustButton.dataset.ticketAdjust);
        setTicketQuantity(ticketId, state.ticketQuantities[ticketId] + delta, true);
        return;
      }

      if (event.target.closest('[data-next-step]')) {
        goToNextStep();
        return;
      }
      if (event.target.closest('[data-previous-step]')) {
        goToPreviousStep();
        return;
      }

      const editButton = event.target.closest('[data-edit-step]');
      if (editButton) {
        showStep(Number(editButton.dataset.editStep), true);
        return;
      }

      if (event.target.closest('[data-restart-demo]')) {
        resetDemo();
        return;
      }

      const paymentButton = event.target.closest('[data-demo-payment]');
      if (paymentButton) {
        showPaymentDemo(paymentButton.dataset.demoPayment);
        return;
      }

      if (event.target.closest('[data-demo-return]')) {
        announce('Demo: il collegamento alla pagina evento non è attivo.');
      }
    });

    root.addEventListener('change', (event) => {
      if (event.target.matches('input[type="tel"]') && isValidPhone(event.target.value)) {
        event.target.value = canonicalizePhone(event.target.value);
      }
      if (event.target.matches('input, select, textarea')) {
        clearInputError(event.target);
        refreshErrorSummary();
      }

      const ticketInput = event.target.closest('[data-ticket-quantity]');
      if (ticketInput) {
        setTicketQuantity(ticketInput.dataset.ticketQuantity, ticketInput.value, true);
        return;
      }

      if (event.target.matches('[data-order-option-id]')) {
        const id = event.target.dataset.orderOptionId;
        if (event.target.checked) state.orderOptions.add(id);
        else state.orderOptions.delete(id);
        renderSummary(true);
        return;
      }

      if (event.target.matches('[data-ticket-option-id]')) {
        const participantKey = event.target.dataset.participantKey;
        const selected = state.ticketOptions.get(participantKey) || new Set();
        if (event.target.checked) selected.add(event.target.dataset.ticketOptionId);
        else selected.delete(event.target.dataset.ticketOptionId);
        state.ticketOptions.set(participantKey, selected);
        renderSummary(true);
      }
    });

    root.addEventListener('input', (event) => {
      if (event.target.matches('[data-ticket-quantity]')) {
        setTicketQuantity(event.target.dataset.ticketQuantity, event.target.value, true);
        return;
      }
      if (event.target.matches('input, select, textarea')) {
        clearInputError(event.target);
        refreshErrorSummary();
      }

      const participantContainer = event.target.closest('[data-participant-key]');
      if (participantContainer && event.target.matches('[data-participant-field]')) {
        const key = participantContainer.dataset.participantKey;
        const participant = state.participants.get(key);
        if (participant) participant.prefilled = false;
      }
    });

    form.addEventListener('submit', submitDemo);
    mobileNextButton.addEventListener('click', goToNextStep);
    mobileSummaryToggle.addEventListener('click', () => {
      const willOpen = mobileMedia.matches && orderSummary.dataset.mobileOpen !== 'true';
      syncMobileSummary(willOpen);
      if (willOpen) summaryPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    const onBreakpointChange = () => {
      syncMobileSummary(false);
      updateMobileBarVisibility();
    };
    if (mobileMedia.addEventListener) mobileMedia.addEventListener('change', onBreakpointChange);
    else mobileMedia.addListener(onBreakpointChange);
    byId('copyBookingCode').addEventListener('click', copyBookingCode);
  }

  function initialize() {
    setStaticContent();
    renderTickets();
    bindEvents();
    renderSummary(false);
    setupModuleVisibility();

    const requestedMode = new URLSearchParams(window.location.search).get('brand');
    applyBrandMode(requestedMode || 'activity');
    showStep(1, false);
  }

  initialize();
  }
}());
