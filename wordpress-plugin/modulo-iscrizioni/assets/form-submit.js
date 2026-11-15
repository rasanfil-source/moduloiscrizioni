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
})();
