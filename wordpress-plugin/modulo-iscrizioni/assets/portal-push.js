(() => {
  const root = document.querySelector('[data-mi-web-push]');
  if (!root) return;

  const button = root.querySelector('[data-mi-web-push-toggle]');
  const label = root.querySelector('[data-mi-web-push-label]');
  const status = root.querySelector('[data-mi-web-push-status]');
  let statusTimeout = null;
  const supported = window.isSecureContext
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;
  const showStatus = (message, duration = 0) => {
    if (statusTimeout) window.clearTimeout(statusTimeout);
    if (status) status.textContent = message;
    statusTimeout = duration ? window.setTimeout(() => {
      if (status) status.textContent = '';
      statusTimeout = null;
    }, duration) : null;
  };

  const setActive = (active) => {
    if (button) button.setAttribute('aria-pressed', active ? 'true' : 'false');
    if (button) button.setAttribute('aria-label', active ? 'Disattiva gli avvisi per le nuove iscrizioni' : 'Attiva gli avvisi per le nuove iscrizioni degli eventi che puoi gestire');
    if (button) button.title = active ? 'Disattiva notifiche' : 'Attiva notifiche';
    if (label) label.textContent = active ? 'Disattiva notifiche' : 'Attiva notifiche';
  };
  setActive(false);

  const base64UrlToBytes = (value) => {
    const padded = `${value}${'='.repeat((4 - value.length % 4) % 4)}`;
    const binary = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(binary, (character) => character.charCodeAt(0));
  };

  const postSubscription = async (operation, subscription) => {
    const body = new URLSearchParams({
      action: 'mi_web_push_subscription',
      nonce: root.dataset.nonce || '',
      operation,
      subscription: JSON.stringify(subscription),
    });
    const response = await fetch(root.dataset.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body,
    });
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Risposta del server non valida.'); }
    if (!response.ok || !result.success) throw new Error(result.data?.message || 'Impostazione non salvata.');
    return result.data || {};
  };

  const waitForActiveWorker = (registration) => {
    const worker = registration.installing || registration.waiting || registration.active;
    if (!worker) return Promise.reject(new Error('Il servizio notifiche non è pronto. Ricarica il portale e riprova.'));
    if (worker === registration.active && worker.state === 'activated' && !registration.installing && !registration.waiting) {
      return Promise.resolve(registration);
    }

    return new Promise((resolve, reject) => {
      const timeout = window.setTimeout(() => {
        worker.removeEventListener('statechange', onStateChange);
        reject(new Error('Il servizio notifiche non è diventato attivo. Ricarica il portale e riprova.'));
      }, 15000);
      const onStateChange = () => {
        if (registration.active === worker && worker.state === 'activated') {
          window.clearTimeout(timeout);
          worker.removeEventListener('statechange', onStateChange);
          resolve(registration);
        } else if (worker.state === 'redundant') {
          window.clearTimeout(timeout);
          worker.removeEventListener('statechange', onStateChange);
          reject(new Error('Il servizio notifiche non è stato installato. Ricarica il portale e riprova.'));
        }
      };
      worker.addEventListener('statechange', onStateChange);
      onStateChange();
    });
  };

  const getRegistration = async () => {
    const registration = await navigator.serviceWorker.register(root.dataset.workerUrl, {
      scope: root.dataset.workerScope,
      updateViaCache: 'none',
    });
    return waitForActiveWorker(registration);
  };

  const explainUnsupported = () => {
    if (button) button.disabled = true;
    showStatus('Questo browser non supporta le notifiche push. Su iPhone, aggiungi il portale alla schermata Home e aprilo da lì.');
  };

  if (!supported) {
    explainUnsupported();
    return;
  }

  if (Notification.permission === 'denied') {
    if (button) button.disabled = true;
    if (button) button.setAttribute('aria-label', 'Notifiche bloccate nelle impostazioni del browser');
    if (button) button.title = 'Notifiche bloccate';
    if (label) label.textContent = 'Notifiche bloccate';
    showStatus('Per attivarle, modifica il permesso nelle impostazioni del browser.');
    return;
  }

  if (Notification.permission === 'granted') {
    getRegistration().then((registration) => registration.pushManager.getSubscription()).then(async (subscription) => {
      if (!subscription) return;
      await postSubscription('subscribe', subscription.toJSON());
      setActive(true);
      showStatus('Notifiche attive su questo dispositivo.', 4000);
    }).catch(() => {
      setActive(false);
      showStatus('Ricarica il portale per verificare le notifiche.');
    });
  }

  button?.addEventListener('click', async () => {
    const disabling = button.getAttribute('aria-pressed') === 'true';
    let permissionRequest = null;
    if (!disabling && Notification.permission !== 'granted') {
      try { permissionRequest = Notification.requestPermission(); } catch (error) { permissionRequest = Promise.reject(error); }
    }
    button.disabled = true;
    showStatus('');
    try {
      if (disabling) {
        const registration = await getRegistration();
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await subscription.unsubscribe();
          await postSubscription('unsubscribe', { endpoint: subscription.endpoint });
        }
        setActive(false);
        showStatus('Notifiche disattivate su questo dispositivo.', 4000);
        return;
      }

      const permission = permissionRequest ? await permissionRequest : Notification.permission;
      if (permission !== 'granted') {
        if (permission === 'denied') {
          if (label) label.textContent = 'Notifiche bloccate';
          showStatus('Per attivarle, modifica il permesso nelle impostazioni del browser.');
        } else {
          showStatus('Consenti le notifiche dal messaggio del browser per completare l’attivazione.');
        }
        return;
      }

      const registration = await getRegistration();
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64UrlToBytes(root.dataset.publicKey || ''),
        });
      }
      await postSubscription('subscribe', subscription.toJSON());
      setActive(true);
      showStatus('Notifiche attive su questo dispositivo.', 4000);
    } catch (error) {
      setActive(false);
      showStatus(error?.message || 'Non è stato possibile attivare le notifiche. Riprova.');
    } finally {
      button.disabled = Notification.permission === 'denied';
    }
  });
})();
