(() => {
  window.MIFormSubmit = (form, submitter) => {
    if (!form) return;
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit(submitter || undefined);
      return;
    }
    // Un click conserva validazione, submitter e gestori submit (anche AJAX).
    // submit() e dispatchEvent() da soli non riproducono questi comportamenti.
    if (submitter) { submitter.click(); return; }
    const button = document.createElement('button');
    button.type = 'submit';
    button.hidden = true;
    form.append(button);
    try { button.click(); } finally { button.remove(); }
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-mi-waitlist-decision]') || event.defaultPrevented) return;
    if (form.dataset.miSubmitting) {
      event.preventDefault();
      return;
    }
    const submitter = event.submitter;
    if (!submitter || submitter.name !== 'decision') return;

    // I pulsanti disabilitati non vengono inclusi nel POST.
    const decision = document.createElement('input');
    decision.type = 'hidden';
    decision.name = submitter.name;
    decision.value = submitter.value;
    form.append(decision);
    form.dataset.miSubmitting = '1';
    const buttons = Array.from(form.querySelectorAll('button[name="decision"]:enabled'));
    buttons.forEach(button => { button.disabled = true; });
    const status = form.querySelector('[data-mi-waitlist-progress]');
    status.textContent = 'Attendere, prego…';

    window.addEventListener('pageshow', () => {
      decision.remove();
      delete form.dataset.miSubmitting;
      buttons.forEach(button => { button.disabled = false; });
      status.textContent = '';
    }, { once: true });
  });
})();
