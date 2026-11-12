const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.MI_PLAYWRIGHT_MODULE || 'playwright');
const changes = Array.from({ length: 501 }, (_, index) => ({
  order_code: 'BOOKING' + index, number: 1, key: 'first_name', before: 'Nome', after: 'Nome aggiornato'
}));
const markup = '<section data-mi-management data-event="42" data-endpoint="/ajax" data-nonce="test"><select data-event-select><option value="42">Prova</option></select><button data-refresh>Aggiorna</button><button data-print>Stampa</button><button data-sheet-sync>Sincronizza</button><a data-open-sheet hidden>Apri</a><p data-management-status></p><div data-management-content></div></section><script src="/portal-management.js"></script>';

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const attempts = [], errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://sheet-test.invalid/**', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/') return route.fulfill({ contentType: 'text/html', body: markup });
      if (path === '/portal-management.js') {
        const script = 'wordpress-plugin/modulo-iscrizioni/assets/' + (process.env.MI_TEST_MIN ? 'min/' : '') + 'portal-management.js';
        return route.fulfill({ contentType: 'text/javascript', body: fs.readFileSync(script, 'utf8') });
      }
      if (path !== '/ajax') return route.abort();
      const fields = Object.fromEntries(new URLSearchParams(route.request().postData()));
      if (fields.operation === 'sheet_changes') return route.fulfill({ json: { success: true, data: { changes, errors: [] } } });
      if (fields.operation === 'sheet_save') {
        attempts.push(fields);
        if (attempts.length === 1) return route.fulfill({ json: { success: true, data: { saved: true, ack_pending: true, message: 'Conferma del foglio da riprovare.' } } });
        return route.fulfill({ json: { success: true, data: { saved: true, replayed: true, message: '501 celle sincronizzate.' } } });
      }
      return route.fulfill({ json: { success: true, data: { sheet_url: '/?mi_open_sheet=42', features: {}, people: [], items: [], rooms: [] } } });
    });
    await page.goto('https://sheet-test.invalid/');
    await page.locator('[data-open-sheet]:not([hidden])').waitFor();
    await page.locator('[data-sheet-sync]').click();
    await page.getByRole('button', { name: 'Sincronizza 501 celle' }).click();
    await page.getByRole('button', { name: 'Riprova la stessa sincronizzazione' }).click();
    await page.waitForFunction(() => document.querySelector('[data-management-status]').textContent.includes('501 celle sincronizzate'));
    assert.equal(attempts.length, 2);
    assert.equal(attempts[0].request_id, attempts[1].request_id);
    assert.deepEqual(JSON.parse(attempts[0].data), changes);
    assert.equal(attempts[0].data, attempts[1].data);
    assert.deepEqual(errors, []);
    console.log('PASS browser: 501 celle e retry della conferma mantengono dati e identificativo' + (process.env.MI_TEST_MIN ? ' (minificato)' : ''));
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
