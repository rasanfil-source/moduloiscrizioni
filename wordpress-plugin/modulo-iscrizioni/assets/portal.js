document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.mi-event-wizard');
  if (form) {
    const steps = [...form.querySelectorAll('.mi-wizard-step')];
    const back = form.querySelector('[data-mi-back]');
    const next = form.querySelector('[data-mi-next]');
    let index = 0;
    const show = () => {
      steps.forEach((step, stepIndex) => step.classList.toggle('is-active', stepIndex === index));
      back.disabled = index === 0;
      next.hidden = index === steps.length - 1;
    };
    next.addEventListener('click', () => {
      const fields = [...steps[index].querySelectorAll('[required]')];
      if (fields.some((field) => !field.reportValidity())) return;
      index = Math.min(steps.length - 1, index + 1);
      show();
    });
    back.addEventListener('click', () => {
      index = Math.max(0, index - 1);
      show();
    });
    const overnight = form.querySelector('[data-mi-overnight]');
    const rooms = form.querySelector('[data-mi-accommodations]');
    if (overnight) overnight.addEventListener('change', () => {
      rooms.hidden = !overnight.checked;
      if (!overnight.checked) rooms.querySelectorAll('input').forEach((input) => { input.checked = false; });
    });
    show();
  }

  const bookingLinks = [...document.querySelectorAll('[data-mi-portal-booking-open]')];
  const inlineDetail = document.getElementById('mi-portal-booking-detail');
  if (!bookingLinks.length && !inlineDetail) return;

  const listUrl = new URL(window.location.href);
  listUrl.searchParams.delete('mi_portal_booking');
  const modal = document.createElement('div');
  modal.className = 'mi-portal-modal';
  modal.hidden = true;
  modal.innerHTML = '<div class="mi-portal-modal__backdrop" data-mi-portal-booking-close></div><section class="mi-portal-modal__dialog" role="dialog" aria-modal="true" aria-label="Scheda prenotazione"><button type="button" class="mi-portal-modal__close" data-mi-portal-booking-close aria-label="Chiudi la scheda">×</button><div class="mi-portal-modal__content" aria-live="polite"></div></section>';
  document.body.append(modal);
  const content = modal.querySelector('.mi-portal-modal__content');
  const closeButton = modal.querySelector('.mi-portal-modal__close');
  let previousFocus = null;
  let activeRequest = null;

  const showBooking = (detail, url = '', pushHistory = false) => {
    content.replaceChildren(detail);
    modal.hidden = false;
    document.body.classList.add('mi-portal-modal-open');
    closeButton.focus();
    if (pushHistory && url) window.history.pushState({}, '', url);
  };
  const closeBooking = (replaceHistory = true) => {
    if (modal.hidden) return;
    activeRequest?.abort();
    activeRequest = null;
    modal.hidden = true;
    content.replaceChildren();
    document.body.classList.remove('mi-portal-modal-open');
    if (replaceHistory) window.history.replaceState({}, '', listUrl);
    previousFocus?.focus();
  };
  const openBooking = async (link) => {
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    previousFocus = link;
    modal.hidden = false;
    document.body.classList.add('mi-portal-modal-open');
    content.innerHTML = '<p class="mi-portal-modal__loading">Apertura della prenotazione…</p>';
    closeButton.focus();
    try {
      const response = await fetch(link.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
      if (!response.ok) throw new Error('detail_unavailable');
      const detailDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
      const detail = detailDocument.getElementById('mi-portal-booking-detail');
      if (!detail) throw new Error('detail_missing');
      showBooking(detail, link.href, true);
    } catch (error) {
      if ('AbortError' === error.name) return;
      window.location.assign(link.href);
    } finally {
      if (activeRequest === request) activeRequest = null;
    }
  };

  bookingLinks.forEach((link) => link.addEventListener('click', (event) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openBooking(link);
  }));
  modal.addEventListener('click', (event) => {
    if (event.target.closest('[data-mi-portal-booking-close]')) closeBooking();
  });
  document.addEventListener('keydown', (event) => {
    if ('Escape' === event.key && !modal.hidden) closeBooking();
    if ('Tab' !== event.key || modal.hidden) return;
    const focusable = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
      .filter((element) => !element.hidden && null !== element.offsetParent);
    if (!focusable.length) {
      event.preventDefault();
      closeButton.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  window.addEventListener('popstate', () => closeBooking(false));

  if (inlineDetail) {
    inlineDetail.remove();
    showBooking(inlineDetail);
  }
});
