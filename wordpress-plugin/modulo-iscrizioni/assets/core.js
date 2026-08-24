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

  function isValidPhone(value) {
    return /^\+[1-9][0-9().\s-]{6,30}$/.test(String(value || '').trim());
  }

  target.MIRegistrationCore = Object.freeze({ clampQuantity, normalizeSelection, sumQuantities, isValidPhone });
}(globalThis));
