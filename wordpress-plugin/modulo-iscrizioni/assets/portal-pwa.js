// Installazione online: nessun service worker, cache persistente o richiesta applicativa.
(() => {
  let installPrompt = null;
  let justInstalled = false;
  const standalone = window.matchMedia('(display-mode: standalone)');
  const panels = () => [...document.querySelectorAll('[data-mi-pwa-install]')];
  const refresh = () => {
    const installed = justInstalled || standalone.matches || navigator.standalone === true;
    panels().forEach((panel) => {
      const button = panel.querySelector('[data-mi-pwa-prompt]');
      if (button) button.hidden = installed || !installPrompt;
      const help = panel.querySelector('[data-mi-pwa-help]');
      if (help) help.hidden = installed;
    });
  };
  window.addEventListener('beforeinstallprompt', (event) => {
    // Non sopprimere il suggerimento nativo sulle pagine prive del nostro comando.
    if (!panels().length) return;
    event.preventDefault();
    installPrompt = event;
    refresh();
  });
  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    justInstalled = true;
    refresh();
  });
  if (standalone.addEventListener) standalone.addEventListener('change', refresh);
  const bind = () => {
    refresh();
    panels().forEach((panel) => {
      const groupChoice = panel.querySelector('select[name="mi_pwa_group"]');
      if (groupChoice) groupChoice.addEventListener('change', () => groupChoice.form.requestSubmit());
      const button = panel.querySelector('[data-mi-pwa-prompt]');
      const status = panel.querySelector('[data-mi-pwa-status]');
      if (!button) return;
      button.addEventListener('click', async () => {
        if (!installPrompt) return;
        const prompt = installPrompt;
        installPrompt = null;
        button.disabled = true;
        try {
          await prompt.prompt();
          const choice = await prompt.userChoice;
          status.textContent = choice.outcome === 'accepted'
            ? 'Installazione richiesta. Segui le indicazioni del browser.'
            : 'Puoi continuare a usare il portale dal browser e installare l’app in seguito dal suo menu.';
        } catch (_) {
          status.textContent = 'Per aggiungere l’app usa il menu del browser, seguendo le istruzioni qui sopra.';
        } finally {
          button.disabled = false;
          refresh();
        }
      });
    });
  };
  // Mantiene l'icona anche nei collegamenti caricati successivamente.
  // Il gruppo grafico non autorizza e non filtra le operazioni.
  document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    const manifest = document.querySelector('link[rel="manifest"][data-mi-pwa-group]');
    if (!link || !manifest || !link.closest('.mi-portal[data-mi-portal-scope="reserved"]')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !url.searchParams.has('mi_portal')) return;
    if (['mi_status', 'mi_waitlist_offer', 'mi_cancel_participant', 'mi_cancel_token'].some(key => url.searchParams.has(key))) return;
    if (!url.searchParams.has('mi_pwa_group')) url.searchParams.set('mi_pwa_group', manifest.dataset.miPwaGroup);
    link.href = url.href;
  }, true);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, { once: true });
  else bind();
})();
