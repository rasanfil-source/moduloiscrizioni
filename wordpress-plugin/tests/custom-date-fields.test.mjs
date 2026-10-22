import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../modulo-iscrizioni/assets/public.js', import.meta.url), 'utf8');
const start = source.indexOf('function configuredParticipantField(');
const end = source.indexOf('function participantOptionQuantity(', start);
const context = vm.createContext({
  document: { createElement: tag => ({ tag, dataset: {}, children: [], append(child) { this.children.push(child); } }) },
  localDateWithYearOffset: years => `year-offset:${years}`,
});
vm.runInContext(source.slice(start, end), context);
const render = field => context.configuredParticipantField({ type: 'date', label: 'Data', ...field }, '2030-10-10', 0).children[0];

test('custom date inputs allow future dates, including previously published definitions', () => {
  for (const field of [{ key: 'custom_arrivo', date_rule: 'any' }, { key: 'custom_arrivo' }]) {
    const input = render(field);
    assert.equal(input.type, 'date');
    assert.equal(input.value, '2030-10-10');
    assert.equal(input.min, undefined);
    assert.equal(input.max, undefined);
  }
});

test('birth, document issue and expiry inputs retain their date limits', () => {
  for (const key of ['birth_date', 'document_issue_date']) {
    const input = render({ key });
    assert.equal(input.min, 'year-offset:-120');
    assert.equal(input.max, 'year-offset:0');
  }
  const expiry = render({ key: 'document_expiry', date_rule: 'future' });
  assert.equal(expiry.min, 'year-offset:0');
  assert.equal(expiry.max, 'year-offset:20');
});
