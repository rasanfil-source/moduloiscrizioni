(function () {
  'use strict';

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
