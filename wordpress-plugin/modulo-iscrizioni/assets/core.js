(function (target) {
  'use strict';

  function clampQuantity(value, maximum) {
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) ? Math.max(0, Math.min(Number(maximum) || 20, parsed)) : 0;
  }

  function normalizeSelection(inputs) {
    const result = {};
    Object.entries(inputs || {}).forEach(([code, entry]) => {
      result[String(code)] = clampQuantity(entry.value, entry.max);
    });
    return result;
  }

  function sumQuantities(selection) {
    return Object.values(selection || {}).reduce((sum, value) => sum + clampQuantity(value, 20), 0);
  }

  function selectionCapacity(event, selection) {
    const availability = event.availability;
    if (!availability || availability.remaining == null || !Number.isFinite(Number(availability.remaining))) return null;
    const remaining = Math.max(0, Number(availability.remaining));
    const selected = Object.entries(selection).filter(([, quantity]) => quantity > 0);
    const quantity = selected.reduce((sum, [, count]) => sum + count, 0);
    const types = selected.map(([code, requested]) => {
      const limit = availability.ticket_types?.[code]?.remaining;
      return {code, requested, available:limit == null ? requested : Math.max(0, Number(limit) || 0)};
    });
    const available = Math.min(remaining, types.reduce((sum, type) => sum + Math.min(type.requested, type.available), 0));
    const waitlistRemaining = availability.waitlist_remaining == null
      ? Math.max(0, Number(event.waitlist_capacity ?? 20) - Number(availability.waitlisted || 0))
      : Math.max(0, Number(availability.waitlist_remaining) || 0);
    return {quantity, available, remaining, types, fits:quantity <= available, waitlistRemaining,
      canWait:Boolean(event.waitlist_enabled) && quantity <= waitlistRemaining};
  }

  function fixedDepositCents(personTotals, sharedTotal, fixedPerPerson) {
    const totals = (Array.isArray(personTotals) ? personTotals : []).map(value => Math.max(0, Math.round(Number(value) || 0)));
    if (!totals.length) return 0;
    const shared = Math.max(0, Math.round(Number(sharedTotal) || 0));
    const fixed = Math.max(0, Math.round(Number(fixedPerPerson) || 0));
    const baseShare = Math.floor(shared / totals.length);
    const remainder = shared % totals.length;
    return totals.reduce((sum, total, index) => sum + Math.min(total + baseShare + (index < remainder ? 1 : 0), fixed), 0);
  }

  function normalizePhone(value) {
    const trimmed = String(value || '').trim();
    const digits = trimmed.replace(/\D/g, '');
    if (/^3\d{9}$/.test(digits) && !trimmed.startsWith('+')) {
      return `+39 ${digits.slice(0, 3)} ${digits.slice(3)}`;
    }
    if (/^(?:39|0039)3\d{9}$/.test(digits) && !trimmed.startsWith('+')) {
      const italianMobile = digits.startsWith('0039') ? digits.slice(4) : digits.slice(2);
      return `+39 ${italianMobile.slice(0, 3)} ${italianMobile.slice(3)}`;
    }
    return trimmed;
  }

  function isValidPhone(value) {
    return /^\+[1-9][0-9().\s-]{6,30}$/.test(normalizePhone(value));
  }

  target.MIRegistrationCore = Object.freeze({ clampQuantity, normalizeSelection, sumQuantities, selectionCapacity, fixedDepositCents, normalizePhone, isValidPhone });
}(globalThis));
