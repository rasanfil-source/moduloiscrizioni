const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {pathToFileURL} = require('node:url');
const {chromium} = require(process.env.MI_PLAYWRIGHT_MODULE || 'playwright');
const version = fs.readFileSync('wordpress-plugin/modulo-iscrizioni/modulo-iscrizioni.php', 'utf8').match(/Version:\s*([\d.]+)/)[1];
(async () => {
  const browser = await chromium.launch({channel:'msedge', headless:true});
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({viewport:{width,height:900}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(pathToFileURL(path.resolve(`output/revisione-evento-${version}/iscrizione-auto-private.html`)).href);
      await page.locator('[data-mi-next]').click();
      const name = page.locator('[name="buyerFirstName"]');
      await name.evaluate(el => { const hint=document.createElement('small'); hint.id='existing-hint'; hint.textContent='Suggerimento'; el.after(hint); el.setAttribute('aria-describedby',hint.id); });
      await page.locator('[data-mi-next]').click();
      assert.equal(await name.evaluate(el => el === document.activeElement), true);
      assert.equal(await name.getAttribute('aria-invalid'), 'true');
      const descriptions = (await name.getAttribute('aria-describedby')).split(' ');
      assert.equal(descriptions[0], 'existing-hint');
      assert.ok(await page.locator('#'+descriptions[1]).isVisible());
      await name.fill('Ada');
      assert.equal(await name.getAttribute('aria-invalid'), null);
      assert.equal(await page.locator('#'+descriptions[1]).isHidden(), true);
      await page.locator('[name="buyerLastName"]').fill('Esempio');
      const phone=page.locator('[name="buyerPhone"]');
      await phone.fill('123');
      await page.locator('[data-mi-next]').click();
      assert.equal(await phone.getAttribute('aria-invalid'), 'true');
      assert.match(await phone.evaluate(el => document.getElementById(el.getAttribute('aria-describedby').split(' ').at(-1)).textContent), /cellulare/);
      await phone.fill('+393331234567');
      assert.equal(await phone.getAttribute('aria-invalid'), null);
      assert.deepEqual(errors, []);
      await page.close();
    }
    console.log('PASS: mobile/desktop focus, persistent errors, accessible descriptions, phone correction, existing hints preserved');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
