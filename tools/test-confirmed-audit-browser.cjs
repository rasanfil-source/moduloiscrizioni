const fs = require('node:fs'), assert = require('node:assert/strict');
const { chromium } = require(process.env.MI_PLAYWRIGHT_MODULE || 'playwright');
const assets = 'wordpress-plugin/modulo-iscrizioni/assets/';
const script = name => fs.readFileSync(assets + name, 'utf8');
const markup = '<main class="mi-portal"><section class="mi-management" data-mi-management data-endpoint="/ajax" data-nonce="test" data-event="0" data-order=""><select data-event-select><option value="">Tutti gli eventi</option><option value="42">Prova</option></select><div data-event-actions><button data-refresh>Aggiorna</button><button data-print>Stampa</button><p data-management-status></p></div><div data-management-content></div></section></main>';
const detail = {
  registration_id: 1, event_id: 42, event_title: 'Prova', order_code: 'TEST', status: 'CONFIRMED', version: 'v1',
  total_cents: 13000, paid_cents: 0, balance_cents: 13000, is_free_event: false, can_change_options: true,
  buyer: { email: '', phone: '' }, fields: [], features: { rooms: false }, option_scope: 'ALL',
  option_definitions: [{ code: 'bus', name: 'Pullman', scope: 'TICKET', price_cents: 1000, max_quantity: 3 }],
  participants: [{ id: 1, number: 1, first_name: 'Persona', last_name: 'Prova', status: 'ACTIVE', room: '', fields: {}, options: [{ code: 'bus', name: 'Pullman', quantity: 3, unit_price_cents: 1000 }] }],
  individual: { ready: true, quotes_known: true, payments_known: true, people: [{ id: 1, total: 13000, paid: 0, balance: 13000, credit: 0 }] }
};
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let saved = null;
    await page.route('https://audit-demo.invalid/**', async route => {
      if (!route.request().url().endsWith('/ajax')) return route.fulfill({ contentType: 'text/html', body: markup });
      const request = Object.fromEntries(new URLSearchParams(route.request().postData()));
      let data;
      if (request.operation === 'all_people') data = { items: [{ first_name: 'Persona', last_name: 'Prova', event_title: 'Prova', event_id: 42, order_code: 'TEST', number: 1, status: 'ACTIVE', booking_status: 'CONFIRMED' }], more: false };
      else if (request.operation === 'detail') data = detail;
      else if (request.operation === 'options_preview') {
        assert.equal(JSON.parse(request.data).options.bus, 2);
        data = { before_total: 13000, after_total: 12000, delta: -1000, credit: 0 };
      } else if (request.operation === 'change_options') {
        saved = JSON.parse(request.data); data = { saved: true, message: 'Servizi aggiornati.' };
      } else throw Error('Unexpected operation ' + request.operation);
      return route.fulfill({ json: { success: true, data } });
    });
    await page.goto('https://audit-demo.invalid/');
    await page.addScriptTag({ content: script('portal-management.js') });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.locator('[data-all-query]').fill('Persona');
    await page.locator('[data-open]').click();
    await page.locator('[data-person-services-editor] summary').click();
    const quantity = page.locator('[name="option:bus"]');
    assert.equal(await quantity.getAttribute('type'), 'number');
    assert.equal(await quantity.inputValue(), '3');
    assert.equal(await quantity.getAttribute('max'), '3');
    await quantity.fill('2');
    await page.getByRole('button', { name: 'Verifica e salva servizi' }).click();
    await page.locator('dialog[open]').waitFor();
    assert.equal(saved, null);
    await page.locator('dialog [value=accept]').click();
    await page.locator('[data-management-status]').filter({ hasText: 'Servizi aggiornati.' }).waitFor();
    assert.equal(saved.options.bus, 2);
    assert.equal(saved.participant_id, 1);
    assert.deepEqual(errors, []);
    await page.close();
    for (const asset of ['admin.js', 'portal.js']) {
      const formPage = await browser.newPage(), formErrors = [];
      formPage.on('pageerror', error => formErrors.push(error.message));
      await formPage.setContent('<form><input type="checkbox" name="mi_annual_attendance_report" value="1"><div data-mi-attendance-period><input type="date" name="mi_attendance_period_start" value=""><input type="date" name="mi_attendance_period_end" value=""></div></form>');
      await formPage.addScriptTag({ content: script(asset) });
      await formPage.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      const checkbox = formPage.locator('[type=checkbox]');
      assert.equal(await formPage.locator('form').evaluate(form => form.checkValidity()), true, asset + ': disabled empty period permits saving');
      assert.equal(await formPage.locator('[type=date]:disabled').count(), 2);
      await checkbox.check();
      assert.equal(await formPage.locator('form').evaluate(form => form.checkValidity()), false, asset + ': enabled empty period rejects saving');
      assert.equal(await formPage.locator('[type=date]:required').count(), 2);
      await checkbox.uncheck();
      assert.equal(await formPage.locator('form').evaluate(form => form.checkValidity()), true);
      assert.deepEqual(formErrors, []);
      await formPage.close();
    }
    console.log('PASS audit browser: quantities 3 → 2 with preview/confirmation; optional attendance period in admin and portal.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
