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

  const table = document.getElementById('mi-ticket-types');
  const addButton = document.getElementById('mi-add-ticket');
  if (!table || !addButton) return;

  addButton.addEventListener('click', () => {
    const row = document.createElement('tr');
    row.innerHTML = '<td><input name="mi_ticket_code[]" pattern="[a-z0-9-]+" required></td><td><input name="mi_ticket_name[]" required></td><td><input name="mi_ticket_price[]" type="number" min="0" step="0.01" value="0.00" required></td><td><input name="mi_ticket_max[]" type="number" min="1" max="20" value="5" required></td><td><button type="button" class="button mi-remove-ticket">Rimuovi</button></td>';
    table.tBodies[0].append(row);
    row.querySelector('input').focus();
  });

  table.addEventListener('click', (event) => {
    const button = event.target.closest('.mi-remove-ticket');
    if (!button || table.tBodies[0].rows.length <= 1) return;
    button.closest('tr').remove();
  });
}());
