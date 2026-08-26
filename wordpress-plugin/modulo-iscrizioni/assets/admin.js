(function () {
  'use strict';

  const saveDraftButton = document.getElementById('save-post');
  if (saveDraftButton) {
    saveDraftButton.setAttribute('formnovalidate', 'formnovalidate');
  }

  const profileSelect = document.getElementById('mi_data_profile');
  const fieldConfig = document.querySelector('.mi-field-config[data-mi-profiles]');
  const fieldPreview = document.querySelector('[data-mi-field-preview]');
  if (profileSelect && fieldConfig && fieldPreview) {
    const profiles = JSON.parse(fieldConfig.dataset.miProfiles || '{}');
    const items = Array.from(fieldConfig.querySelectorAll('[data-mi-field]'));

    const refreshFields = () => {
      fieldPreview.replaceChildren();
      const base = document.createElement('li');
      base.textContent = 'Nome e cognome — obbligatori';
      fieldPreview.append(base);
      items.forEach((item) => {
        const enabled = item.querySelector('[data-mi-field-enabled]');
        const required = item.querySelector('[data-mi-field-required]');
        required.disabled = !enabled.checked;
        if (!enabled.checked) required.checked = false;
        item.classList.toggle('is-disabled', !enabled.checked);
        if (enabled.checked) {
          const line = document.createElement('li');
          line.textContent = `${item.querySelector('strong').textContent} — ${required.checked ? 'obbligatorio' : 'facoltativo'}`;
          fieldPreview.append(line);
        }
      });
    };

    profileSelect.addEventListener('change', () => {
      const profile = profiles[profileSelect.value];
      if (!profile || profileSelect.value === 'CUSTOM') return;
      items.forEach((item) => {
        const key = item.dataset.miField;
        item.querySelector('[data-mi-field-enabled]').checked = profile.enabled.includes(key);
        item.querySelector('[data-mi-field-required]').checked = profile.required.includes(key);
      });
      refreshFields();
    });

    fieldConfig.addEventListener('change', () => {
      profileSelect.value = 'CUSTOM';
      refreshFields();
    });
    refreshFields();
  }

  const anteprimaEmail = document.querySelector('[data-mi-email-preview]');
  if (anteprimaEmail) {
    const valoriEsempio = JSON.parse(anteprimaEmail.dataset.miEmailValues || '{}');
    const campiEmail = Array.from(document.querySelectorAll('#mi_email_subject, #mi_email_preheader, #mi_email_html, #mi_email_text, #mi_email_footer'));
    const segnapostoAmmessi = Object.keys(valoriEsempio);
    const risolviSegnaposto = (testo) => segnapostoAmmessi.reduce((risultato, segnaposto) => risultato.split(segnaposto).join(valoriEsempio[segnaposto]), testo);
    const aggiornaAnteprimaEmail = () => {
      const sconosciuti = new Set();
      campiEmail.forEach((campo) => {
        const trovati = campo.value.match(/{{[^{}]+}}/g) || [];
        trovati.filter((voce) => !segnapostoAmmessi.includes(voce)).forEach((voce) => sconosciuti.add(voce));
      });
      campiEmail.forEach((campo) => campo.setCustomValidity(sconosciuti.size ? 'Rimuovi i segnaposto non ammessi.' : ''));
      anteprimaEmail.querySelector('[data-mi-email-preview-subject]').textContent = risolviSegnaposto(document.getElementById('mi_email_subject').value);
      anteprimaEmail.querySelector('[data-mi-email-preview-preheader]').textContent = risolviSegnaposto(document.getElementById('mi_email_preheader').value);
      anteprimaEmail.querySelector('[data-mi-email-preview-text]').textContent = risolviSegnaposto(document.getElementById('mi_email_text').value);
      anteprimaEmail.querySelector('[data-mi-email-preview-footer]').textContent = risolviSegnaposto(document.getElementById('mi_email_footer').value);
      const errore = anteprimaEmail.querySelector('[data-mi-email-placeholder-error]');
      errore.hidden = sconosciuti.size === 0;
      errore.textContent = sconosciuti.size ? `Segnaposto non ammessi: ${Array.from(sconosciuti).join(', ')}` : '';
    };
    campiEmail.forEach((campo) => campo.addEventListener('input', aggiornaAnteprimaEmail));
    aggiornaAnteprimaEmail();
  }

  const modalitaEconomica = document.getElementById('mi_economic_mode');
  const modalitaPrezzo = document.getElementById('mi_pricing_mode');
  const riquadroCaparra = document.querySelector('[data-mi-economic-deposit]');
  const riquadroPagamenti = document.querySelector('[data-mi-economic-payments]');
	const riquadroPrezzoFisso = document.querySelector('[data-mi-fixed-price]');
  const aiutoEconomico = document.querySelector('[data-mi-economic-help]');
  if (modalitaEconomica && modalitaPrezzo && riquadroCaparra && riquadroPagamenti && aiutoEconomico) {
    const aggiornaConfigurazioneEconomica = () => {
      const modalita = modalitaEconomica.value;
      const incassa = ['FULL_PAYMENT', 'DEPOSIT_BALANCE'].includes(modalita);
	  const prezzoCoerente = modalita === 'REGISTRATION_ONLY' ? ['NONE', 'ZERO'].includes(modalitaPrezzo.value) : ['FIXED', 'CALCULATED'].includes(modalitaPrezzo.value);
	  if (riquadroPrezzoFisso) {
		riquadroPrezzoFisso.hidden = modalitaPrezzo.value !== 'FIXED';
		const campoPrezzoFisso = riquadroPrezzoFisso.querySelector('input');
		if (campoPrezzoFisso) { campoPrezzoFisso.required = modalitaPrezzo.value === 'FIXED'; campoPrezzoFisso.disabled = modalitaPrezzo.value !== 'FIXED'; }
	  }
      riquadroCaparra.hidden = modalita !== 'DEPOSIT_BALANCE';
      riquadroPagamenti.hidden = !incassa;
      Array.from(riquadroPagamenti.querySelectorAll('input')).forEach((campo) => { campo.disabled = !incassa; });
	  modalitaPrezzo.setCustomValidity(prezzoCoerente ? '' : modalita === 'REGISTRATION_ONLY' ? 'Per la sola iscrizione scegli “Nessun prezzo” oppure “Gratuito”.' : 'Seleziona una quota uguale per tutti oppure prezzi diversi secondo la tipologia.');
      aiutoEconomico.textContent = modalita === 'REGISTRATION_ONLY' ? (modalitaPrezzo.value === 'ZERO' ? 'L’evento è dichiarato esplicitamente gratuito.' : 'Il modulo raccoglie soltanto le iscrizioni senza dichiarare un prezzo.') : modalita === 'PRICE_ONLY' ? 'Il prezzo viene mostrato, ma non vengono richieste fonti di pagamento.' : modalita === 'FULL_PAYMENT' ? 'È richiesto il versamento dell’intero importo tramite almeno una fonte ammessa.' : 'Sono previsti una caparra percentuale e il successivo saldo.';
    };
    modalitaEconomica.addEventListener('change', aggiornaConfigurazioneEconomica);
    modalitaPrezzo.addEventListener('change', aggiornaConfigurazioneEconomica);
    aggiornaConfigurazioneEconomica();
  }

  const table = document.getElementById('mi-ticket-types');
  const addButton = document.getElementById('mi-add-ticket');
  if (!table || !addButton) return;

  addButton.addEventListener('click', () => {
    const row = document.createElement('tr');
    row.innerHTML = '<td><input name="mi_ticket_code[]" pattern="[a-z0-9-]+" required></td><td><input name="mi_ticket_name[]" required></td><td><input name="mi_ticket_price[]" type="number" min="0" step="0.01" value="0.00" required></td><td><input name="mi_ticket_max[]" type="number" min="1" max="20" value="5" required></td><td><input name="mi_ticket_capacity[]" type="number" min="0" max="10000" value="0"><small>0 = capienza evento</small></td><td><button type="button" class="button mi-remove-ticket">Rimuovi</button></td>';
    table.tBodies[0].append(row);
    row.querySelector('input').focus();
  });

  const optionTable = document.querySelector('#mi-options tbody');
  document.querySelector('#mi-add-option')?.addEventListener('click', () => {
    const row = document.createElement('tr');
    row.innerHTML = '<td><input name="mi_option_code[]" pattern="[a-z0-9-]+" required></td><td><input name="mi_option_name[]" required></td><td><select name="mi_option_scope[]"><option value="ORDER">Ordine</option><option value="TICKET">Partecipante</option></select></td><td><input name="mi_option_price[]" type="number" min="0" step="0.01" value="0.00"></td><td><input name="mi_option_max[]" type="number" min="1" max="20" value="1"></td><td><button type="button" class="button mi-remove-option">Rimuovi</button></td>';
    optionTable?.append(row);
  });
  optionTable?.addEventListener('click', (event) => {
    if (event.target.closest('.mi-remove-option')) event.target.closest('tr')?.remove();
  });

  table.addEventListener('click', (event) => {
    const button = event.target.closest('.mi-remove-ticket');
    if (!button || table.tBodies[0].rows.length <= 1) return;
    button.closest('tr').remove();
  });
}());
